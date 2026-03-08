<?php
session_start();
$conn = new mysqli("localhost","root","","clubconnect");

if(!isset($_SESSION['user_id'])) exit;

$user_id = $_SESSION['user_id'];

$result = $conn->prepare("
    SELECT id, message, created_at, is_read 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
");
$result->bind_param("i",$user_id);
$result->execute();
$data = $result->get_result();

$notifications = [];
$unread = 0;

while($row = $data->fetch_assoc()){
    if($row['is_read'] == 0) $unread++;
    $notifications[] = $row;
}

echo json_encode([
    "notifications" => $notifications,
    "unread" => $unread
]);