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

    // UPDATED SQL: Uses a subquery to group 2-way conversations and find the absolute latest message
    $res = $conn->query("
        SELECT 
            sub.student_id,
            u.fullname,
            u.profile_pic,
            MAX(sub.created_at) as last_time,
            SUBSTRING_INDEX(MAX(CONCAT(sub.created_at,'|',sub.message_text)),'|',-1) as last_msg,
            SUBSTRING_INDEX(MAX(CONCAT(sub.created_at,'|',sub.sender_id)),'|',-1) as last_sender_id,
            SUM(CASE WHEN sub.sender_id = sub.student_id AND sub.is_read = 0 THEN 1 ELSE 0 END) as unread_count
        FROM (
            SELECT 
                CASE WHEN sender_id = $user_id THEN receiver_id ELSE sender_id END as student_id,
                sender_id,
                message_text,
                created_at,
                is_read
            FROM messages
            WHERE sender_id = $user_id OR receiver_id = $user_id
        ) as sub
        JOIN users u ON sub.student_id = u.id
        GROUP BY sub.student_id
        ORDER BY last_time DESC
    ");

    if($res->num_rows == 0){
        echo "<div style='padding:15px;color:#94a3b8;text-align:center;'>Inbox empty</div>";
    }

    while($row = $res->fetch_assoc()){
        // highlight background slightly if unread
        $bg_color = ($row['unread_count'] > 0) ? "rgba(179, 18, 23, 0.1)" : "transparent";

        // fallback to default avatar if the user hasn't uploaded one
        $avatar = !empty($row['profile_pic']) ? $row['profile_pic'] : 'default-avatar.png';

        // NOTE: updated onclick to use $row['student_id'] instead of sender_id
        echo "<div class='mod-item' onclick='openChat(".$row['student_id'].")' style='cursor:pointer; position:relative; background: $bg_color; display: flex; flex-direction: row; gap: 12px; align-items: center; border-bottom: 1px solid rgba(255,255,255,0.08); padding: 15px;'>";

        $border_color = ($row['unread_count'] > 0) ? "#ef4444" : "rgba(255,255,255,0.1)";
        echo "<img src='".htmlspecialchars($avatar)."' style='width: 45px; height: 45px; border-radius: 50%; object-fit: cover; border: 2px solid $border_color; flex-shrink: 0;'>";

        echo "<div style='flex: 1; overflow: hidden;'>";

        if($row['unread_count'] > 0){
            echo "<div style='position:absolute; right:15px; top:20px; width:10px; height:10px; background:#ef4444; border-radius:50%; box-shadow: 0 0 8px #ef4444;'></div>";
            echo "<b style='color: white; display: block; font-size: 14px; margin-bottom: 2px;'>".htmlspecialchars($row['fullname'])."</b>";
        } else {
            echo "<b style='color: #cbd5e1; display: block; font-size: 14px; margin-bottom: 2px;'>".htmlspecialchars($row['fullname'])."</b>";
        }

        // add "You: " prefix if the moderator was the last one to send a message
        $prefix = ($row['last_sender_id'] == $user_id) ? "You: " : "";

        echo "<span style='font-size:12px; color:#94a3b8; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;'>".
            htmlspecialchars($prefix . $row['last_msg']).
            "</span>";

        echo "<small style='color:#64748b; display: block; margin-top: 4px; font-size: 11px;'>".
            date('M d, g:i A', strtotime($row['last_time'])).
            "</small>";

        echo "</div>";
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
