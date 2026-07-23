<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

header('Content-Type: text/html; charset=UTF-8');


$staffId = $_SESSION['staff_id'];


/*
|--------------------------------------------------------------------------
| Get assigned window
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT window_id
    FROM staff
    WHERE id = ?
");

$stmt->execute([$staffId]);

$staff = $stmt->fetch(PDO::FETCH_ASSOC);

$windowId = $staff['window_id'] ?? null;


/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$allowedStatus = [
    'waiting',
    'called',
    'in_progress',
    'completed',
    'cancelled'
];

$allowedType = [
    'walkin',
    'appointment'
];


$fStatus = (
    isset($_GET['status']) &&
    in_array($_GET['status'], $allowedStatus, true)
)
? $_GET['status']
: '';


$fType = (
    isset($_GET['type']) &&
    in_array($_GET['type'], $allowedType, true)
)
? $_GET['type']
: '';


$q = trim($_GET['q'] ?? '');


$page = max(1, (int)($_GET['page'] ?? 1));

$limit = 15;

$offset = ($page - 1) * $limit;



/*
|--------------------------------------------------------------------------
| WHERE
|--------------------------------------------------------------------------
*/

$where = [
    "qt.window_id = :windowId"
];

$params = [
    ":windowId" => $windowId
];


if ($fStatus !== '') {

    $where[] = "qt.status = :status";

    $params[':status'] = $fStatus;

}


if ($fType !== '') {

    $where[] = "qt.type = :type";

    $params[':type'] = $fType;

}


if ($q !== '') {

    $where[] = "
    (
        qt.queue_number LIKE :search1
        OR CONCAT(st.first_name,' ',st.last_name) LIKE :search2
        OR st.sr_code LIKE :search3
    )
    ";

    $searchValue = "%".$q."%";

    $params[':search1'] = $searchValue;
    $params[':search2'] = $searchValue;
    $params[':search3'] = $searchValue;

}


$whereSql = "WHERE " . implode(" AND ", $where);



/*
|--------------------------------------------------------------------------
| Get Transactions
|--------------------------------------------------------------------------
*/

$sql = "

SELECT

    qt.id,
    qt.queue_number,
    qt.type,
    qt.status,
    qt.priority,
    qt.joined_at,
    qt.called_at,
    qt.done_at,

    st.first_name,
    st.last_name,
    st.sr_code,
    sf.name AS assigned_staff,
    sf.position AS assigned_staff_position,


    (
        SELECT GROUP_CONCAT(
            d.name
            SEPARATOR '~~'
        )

        FROM queue_ticket_document qtd

        JOIN documents d
        ON d.id = qtd.document_id

        WHERE qtd.ticket_id = qt.id

    ) AS documents


FROM queue_tickets qt


LEFT JOIN students st
ON st.id = qt.student_id

LEFT JOIN staff sf
ON sf.window_id = qt.window_id


$whereSql


ORDER BY qt.joined_at DESC


LIMIT :limit OFFSET :offset

";


$stmt = $pdo->prepare($sql);


foreach($params as $key=>$value){

    $stmt->bindValue($key,$value);

}


$stmt->bindValue(
    ':limit',
    $limit,
    PDO::PARAM_INT
);


$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);


$stmt->execute();


$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);



?>


<?php if(empty($transactions)): ?>

<div class="empty qt-empty">
    No transactions found.
</div>


<?php else: ?>


<table class="qt-table">

<thead>

<tr>

<th>Queue #</th>
<th>Student</th>
<th>Document(s)</th>
<th>Type</th>
<th>Status</th>
<th>Joined</th>
<th>Called</th>
<th>Done</th>

</tr>

</thead>


<tbody>


<?php foreach($transactions as $row): ?>


<tr>


<td class="qt-cell-qnum">

<?= htmlspecialchars($row['queue_number']) ?>

</td>



<td>

<?= htmlspecialchars(
    ($row['first_name'] ?? '') .
    ' ' .
    ($row['last_name'] ?? '')
) ?>


<br>

<small>
<?= htmlspecialchars($row['sr_code'] ?? '') ?>
</small>

</td>



<td>
<?php

$docs = !empty($row['documents'])
    ? explode('~~', $row['documents'])
    : [];

$visibleCount = 2;
$extraCount = count($docs) - $visibleCount;

?>

<?php if(empty($docs)): ?>

    <span class="doc-tag doc-tag--empty">
        No document
    </span>

<?php else: ?>

<div class="qt-doc-list">

    <?php foreach($docs as $i => $doc): ?>

        <span class="doc-tag <?= $i >= $visibleCount ? 'qt-doc-extra' : '' ?>">
            <?= htmlspecialchars($doc) ?>
        </span>

    <?php endforeach; ?>


    <?php if($extraCount > 0): ?>

        <button
            type="button"
            class="qt-doc-toggle"
            data-more="+<?= $extraCount ?> more"
            data-less="Show less">

            +<?= $extraCount ?> more

        </button>

    <?php endif; ?>


</div>

<?php endif; ?>

</td>



<td>

<?= ucfirst($row['type']) ?>

</td>



<td>

<?= ucfirst(
    str_replace('_',' ',$row['status'])
) ?>

</td>



<td>

<?= $row['joined_at']
? date(
    'M j,Y g:i A',
    strtotime($row['joined_at'])
)
: '-'
?>

</td>



<td>

<?= $row['called_at']
? date(
    'M j,Y g:i A',
    strtotime($row['called_at'])
)
: '-'
?>

</td>



<td>

<?= $row['done_at']
? date(
    'M j,Y g:i A',
    strtotime($row['done_at'])
)
: '-'
?>

</td>


</tr>


<?php endforeach; ?>


</tbody>


</table>


<?php endif; ?>