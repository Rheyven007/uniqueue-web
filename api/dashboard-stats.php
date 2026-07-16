<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

require_once __DIR__ . '/../includes/db.php';

try {

    $office_id = isset($_GET['office_id']) ? (int)$_GET['office_id'] : 0;

    if (!$office_id) {
        throw new Exception("Invalid office id.");
    }


    /* =========================
       QUEUE STATUS
       Completed vs Cancelled
    ========================== */

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(status IN ('done','completed')),0) AS completed,
            COALESCE(SUM(status='cancelled'),0) AS cancelled
        FROM queue_tickets
        WHERE office_id = ?
        AND DATE(joined_at)=CURDATE()
    ");

    $stmt->execute([$office_id]);

    $status = $stmt->fetch(PDO::FETCH_ASSOC);



    /* =========================
       QUEUE TYPES
    ========================== */

    $stmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(type='walkin'),0) AS walkin,
            COALESCE(SUM(type='appointment'),0) AS appointment
        FROM queue_tickets
        WHERE office_id=?
        AND DATE(joined_at)=CURDATE()
    ");

    $stmt->execute([$office_id]);

    $types = $stmt->fetch(PDO::FETCH_ASSOC);



    /* =========================
       TRANSACTIONS PER HOUR
    ========================== */

    $hourLabels = [];
    $hourData = [];


    // Office hours 8AM - 5PM
    for ($i = 8; $i <= 17; $i++) {

        $hourLabels[] = sprintf("%02d:00", $i);
        $hourData[] = 0;

    }


    $stmt = $pdo->prepare("
        SELECT
            HOUR(joined_at) AS hr,
            COUNT(*) AS total
        FROM queue_tickets
        WHERE office_id=?
        AND DATE(joined_at)=CURDATE()
        GROUP BY HOUR(joined_at)
        ORDER BY hr
    ");


    $stmt->execute([$office_id]);


    while($row = $stmt->fetch(PDO::FETCH_ASSOC)){

        $index = (int)$row['hr'] - 8;


        if(isset($hourData[$index])){

            $hourData[$index] = (int)$row['total'];

        }

    }



    /* =========================
       WINDOW PERFORMANCE
    ========================== */

    $windowLabels = [];
    $windowData = [];


    $stmt = $pdo->prepare("
        SELECT
            w.name,
            COUNT(q.id) AS total

        FROM windows w

        LEFT JOIN queue_tickets q
            ON q.window_id=w.id
            AND q.status IN ('done','completed')
            AND DATE(q.done_at)=CURDATE()

        WHERE w.office_id=?

        GROUP BY w.id

        ORDER BY w.name
    ");


    $stmt->execute([$office_id]);


    while($row=$stmt->fetch(PDO::FETCH_ASSOC)){

        $windowLabels[] = $row['name'];
        $windowData[] = (int)$row['total'];

    }



    /* =========================
       DOCUMENT REQUESTS
    ========================== */

    $documentLabels = [];
    $documentData = [];


    $stmt = $pdo->prepare("
        SELECT
            d.name,
            COUNT(qtd.document_id) AS total

        FROM queue_ticket_document qtd

        INNER JOIN documents d
            ON d.id=qtd.document_id

        INNER JOIN queue_tickets qt
            ON qt.id=qtd.ticket_id

        WHERE qt.office_id=?
        AND DATE(qt.joined_at)=CURDATE()

        GROUP BY d.id

        ORDER BY total DESC

        LIMIT 10
    ");


    $stmt->execute([$office_id]);


    while($row=$stmt->fetch(PDO::FETCH_ASSOC)){

        $documentLabels[] = $row['name'];
        $documentData[] = (int)$row['total'];

    }



    echo json_encode([

        "success"=>true,


        "queueStatus"=>[
            (int)$status['completed'],
            (int)$status['cancelled']
        ],


        "queueTypes"=>[
            (int)$types['walkin'],
            (int)$types['appointment']
        ],


        "hourlyLabels"=>$hourLabels,
        "hourlyData"=>$hourData,


        "windowLabels"=>$windowLabels,
        "windowData"=>$windowData,


        "documentLabels"=>$documentLabels,
        "documentData"=>$documentData

    ]);



} catch(Throwable $e){

    echo json_encode([

        "success"=>false,
        "message"=>$e->getMessage()

    ]);

}