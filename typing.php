<?php
session_start();
$conn = new mysqli("localhost","root","","clubconnect");

$mod = $_SESSION['user_id'];
$student = (int)$_GET['student_id'];

$conn->query("UPDATE users SET typing_to=$student WHERE id=$mod");
?>