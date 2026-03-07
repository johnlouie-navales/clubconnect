<?php
session_start();
$conn = new mysqli("localhost","root","","clubconnect");

if(!isset($_SESSION['user_id'])) exit();

$user_id = $_SESSION['user_id'];
$type = $_GET['type'] ?? '';

/* =========================
   1. COUNT LOGIC
========================= */
if($type === "count"){

    $notif = $conn->query("SELECT COUNT(*) as c FROM notifications WHERE user_id = $user_id AND is_read = 0")->fetch_assoc()['c'];

    $msg = $conn->query("SELECT COUNT(*) as c FROM messages WHERE receiver_id = $user_id AND is_read = 0")->fetch_assoc()['c'];

    echo json_encode(["total" => (int)$notif + (int)$msg]);
    exit();
}

/* =========================
   2. NOTIFICATION LIST
========================= */
if($type === "notif"){

    $res = $conn->query("
        SELECT id, message, created_at
        FROM notifications
        WHERE user_id = $user_id
        AND is_read = 0
        ORDER BY created_at DESC
        LIMIT 10
    ");

    if($res->num_rows === 0){
        echo "<div style='padding:15px; color:#94a3b8; text-align:center;'>No new notifications</div>";
    }

    while($row = $res->fetch_assoc()){

        echo "<div class='mod-item' id='notif-".$row['id']."'>";
        echo "<span>".htmlspecialchars($row['message'])."</span>";
        echo "<br><small style='color:#64748b;'>".date('M d, g:i A', strtotime($row['created_at']))."</small>";

        echo "<br>
        <button onclick='markNotif(".$row['id'].")'
        style='background:none;border:none;color:#f87171;cursor:pointer;padding:5px 0;font-size:11px;text-transform:uppercase;'>
        Mark as Read
        </button>";

        echo "</div>";
    }

    exit();
}

/* =========================
   3. MESSAGE LIST
========================= */
if($type === "msg"){

$res = $conn->query("
SELECT 
    u.id as sender_id,
    u.fullname,
    MAX(m.created_at) as last_time,
    SUBSTRING_INDEX(MAX(CONCAT(m.created_at,'|',m.message_text)),'|',-1) as last_msg
FROM messages m
JOIN users u ON m.sender_id = u.id
WHERE m.receiver_id = $user_id
GROUP BY m.sender_id
ORDER BY last_time DESC
");

if($res->num_rows == 0){
    echo "<div style='padding:15px;color:#94a3b8;text-align:center;'>Inbox empty</div>";
}

while($row = $res->fetch_assoc()){

echo "<div class='mod-item' onclick='openChat(".$row['sender_id'].")' style='cursor:pointer;'>";

echo "<b>".htmlspecialchars($row['fullname'])."</b>";

echo "<br><span style='font-size:12px;color:#94a3b8;'>".
htmlspecialchars(substr($row['last_msg'],0,40)).
"</span>";

echo "<br><small style='color:#64748b;'>".
date('M d, g:i A', strtotime($row['last_time'])).
"</small>";

echo "</div>";
}

exit();
}

/* =========================
   4. MARK NOTIFICATION READ
========================= */
if($type === "mark_notif"){

    $id = (int)$_GET['id'];

    $conn->query("
        UPDATE notifications
        SET is_read = 1
        WHERE id = $id
        AND user_id = $user_id
    ");

    exit();
}

/* =========================
   5. MARK MESSAGE READ
========================= */
if($type === "msg"){

$res = $conn->query("
SELECT 
    u.id as sender_id,
    u.fullname,
    MAX(m.created_at) as last_time,
    SUBSTRING_INDEX(MAX(CONCAT(m.created_at,'|',m.message_text)),'|',-1) as last_msg
FROM messages m
JOIN users u ON m.sender_id = u.id
WHERE m.receiver_id = $user_id
GROUP BY m.sender_id
ORDER BY last_time DESC
");

if($res->num_rows == 0){
    echo "<div style='padding:15px;color:#94a3b8;text-align:center;'>Inbox empty</div>";
}

while($row = $res->fetch_assoc()){

echo "<div class='mod-item' onclick='openChat(".$row['sender_id'].")' style='cursor:pointer;'>";

echo "<b>".htmlspecialchars($row['fullname'])."</b>";

echo "<br><span style='font-size:12px;color:#94a3b8;'>".
htmlspecialchars(substr($row['last_msg'],0,40)).
"</span>";

echo "<br><small style='color:#64748b;'>".
date('M d, g:i A', strtotime($row['last_time'])).
"</small>";

echo "</div>";
}

exit();
}
?>