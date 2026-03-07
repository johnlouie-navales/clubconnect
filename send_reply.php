<?php
session_start();
$conn = new mysqli("localhost","root","","clubconnect");

if(!isset($_SESSION['user_id'])) exit();

$mod_id = $_SESSION['user_id'];
$student_id = (int)$_POST['student_id'];
$msg = $conn->real_escape_string($_POST['message']);

$conn->query("
INSERT INTO messages (sender_id,receiver_id,message_text,created_at,is_read)
VALUES ($mod_id,$student_id,'$msg',NOW(),0)
");
?>