<?php
session_start();
$conn = new mysqli("localhost","root","","clubconnect");

if(!isset($_SESSION['user_id'])) exit();

$mod_id = $_SESSION['user_id'];
$student_id = (int)$_GET['student_id'];

// mark unread messages from this student as READ
$conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = $student_id AND receiver_id = $mod_id AND is_read = 0");

// fetch the chat history
$res = $conn->query("
    SELECT m.*, u.fullname, u.profile_pic
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE 
    (m.sender_id = $student_id AND m.receiver_id = $mod_id)
    OR
    (m.sender_id = $mod_id AND m.receiver_id = $student_id)
    ORDER BY m.created_at ASC
");

while($row = $res->fetch_assoc()){
    $class = ($row['sender_id'] == $mod_id) ? "mod-msg" : "student-msg";

    echo "<div class='$class'>";
    echo htmlspecialchars($row['message_text']);
    echo "<br><small>".date('M d g:i A', strtotime($row['created_at']))."</small>";
    echo "</div>";
}
