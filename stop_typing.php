<?php
session_start();
$conn = new mysqli("localhost","root","","clubconnect");

$mod = $_SESSION['user_id'];

$conn->query("UPDATE users SET typing_to=0 WHERE id=$mod");
?>