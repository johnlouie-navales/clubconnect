<?php
session_start();
$conn = new mysqli("localhost","root","","clubconnect");

if(!isset($_SESSION['user_id'])) exit();

$mod_id = $_SESSION['user_id'];
$student_id = (int)$_GET['student_id'];

$res = $conn->query("
SELECT m.*, u.fullname
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
?>