<?php

require_once __DIR__ . '/../../auth/session.php';
require_once __DIR__ . '/../../includes/db.php';

require_staff();

header('Content-Type: application/json');


$window_id=$_SESSION['window_id'];
$office_id=$_SESSION['office_id'];



try{


$stmt=$pdo->prepare("
SELECT id
FROM queue_tickets
WHERE office_id=?
AND window_id=?
AND status IN ('called','in_progress')
LIMIT 1
");


$stmt->execute([
    $office_id,
    $window_id
]);


$ticket=$stmt->fetch();



if(!$ticket){

echo json_encode([
    'success'=>false,
    'message'=>'No active queue.'
]);

exit;

}



$stmt=$pdo->prepare("
UPDATE queue_tickets
SET
status='done',
done_at=NOW()
WHERE id=?
");


$stmt->execute([
    $ticket['id']
]);



echo json_encode([
    'success'=>true,
    'message'=>'Transaction completed.'
]);



}catch(PDOException $e){

echo json_encode([
    'success'=>false,
    'message'=>$e->getMessage()
]);

}