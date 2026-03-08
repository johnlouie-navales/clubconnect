<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clubconnect");

if (!isset($_SESSION['user_id'])) exit();

$student_id = $_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// get club_id from either GET (fetch) or POST (send)
$club_id = isset($_GET['club_id']) ? (int)$_GET['club_id'] : (isset($_POST['club_id']) ? (int)$_POST['club_id'] : 0);

// find the moderator's ID for this specific club
$mod_res = $conn->query("SELECT id FROM users WHERE LOWER(role) = 'moderator' AND managed_club_id = $club_id LIMIT 1");
if ($mod_res->num_rows === 0) exit("Moderator not found.");
$mod_id = $mod_res->fetch_assoc()['id'];

/* =========================
   FETCH CHAT HISTORY
========================= */
if ($action === 'fetch') {
    // mark moderator's messages as read by the student
    $conn->query("UPDATE messages SET is_read = 1 WHERE sender_id = $mod_id AND receiver_id = $student_id AND is_read = 0");

    // fetch the two-way conversation
    $res = $conn->query("
        SELECT * FROM messages 
        WHERE (sender_id = $student_id AND receiver_id = $mod_id) 
           OR (sender_id = $mod_id AND receiver_id = $student_id)
        ORDER BY created_at ASC
    ");

    while ($row = $res->fetch_assoc()) {
        if ($row['sender_id'] == $student_id) {
            // student's own message (Blue bubble, aligned right)
            echo "<div style='background: #2563eb; padding: 8px 12px; border-radius: 8px; margin: 4px 0 4px auto; max-width: 80%; width: fit-content; text-align: left;'>";
            echo htmlspecialchars($row['message_text']);
            echo "<br><small style='color: #cbd5e1; font-size: 10px;'>".date('M d g:i A', strtotime($row['created_at']))."</small>";
            echo "</div>";
        } else {
            // moderator's reply (Dark grey bubble, aligned left)
            echo "<div style='background: #334155; padding: 8px 12px; border-radius: 8px; margin: 4px auto 4px 0; max-width: 80%; width: fit-content; text-align: left; border-left: 3px solid #b31217;'>";
            echo htmlspecialchars($row['message_text']);
            echo "<br><small style='color: #94a3b8; font-size: 10px;'>".date('M d g:i A', strtotime($row['created_at']))."</small>";
            echo "</div>";
        }
    }
    exit();
}

/* =========================
   CHECK UNREAD MESSAGES (STUDENT)
========================= */
if ($action === 'check_unread') {
    // count how many unread messages the student has received from this club's moderator
    $res = $conn->query("SELECT COUNT(*) as unread FROM messages WHERE receiver_id = $student_id AND sender_id = $mod_id AND is_read = 0");
    $count = $res->fetch_assoc()['unread'];

    // return as a JSON object
    echo json_encode(['unread' => (int)$count]);
    exit();
}

/* =========================
   SEND NEW MESSAGE
========================= */
if ($action === 'send') {
    $msg = $conn->real_escape_string($_POST['message']);
    $conn->query("INSERT INTO messages (sender_id, receiver_id, message_text, created_at, is_read) VALUES ($student_id, $mod_id, '$msg', NOW(), 0)");
    exit();
}

/* =========================
   TYPING INDICATOR LOGIC
========================= */
if ($action === 'set_typing') {
    $sender_id = $_SESSION['user_id'];
    $target_id = (int)$_POST['receiver_id'];
    $is_typing = (int)$_POST['is_typing'];

    $file = "typing_status_{$sender_id}_{$target_id}.txt";

    if ($is_typing === 1) {
        file_put_contents($file, time()); // create file with current timestamp
    } else {
        if (file_exists($file)) unlink($file); // delete file when stopped typing
    }
    exit();
}

if ($action === 'check_typing') {
    // the student checks if their specific moderator is typing
    $file = "typing_status_{$mod_id}_{$student_id}.txt";
    $is_typing = false;

    if (file_exists($file)) {
        // if the file was updated less than 3 seconds ago, they are actively typing
        if (time() - filemtime($file) < 3) {
            $is_typing = true;
        } else {
            unlink($file); // clean up old abandoned file
        }
    }
    echo json_encode(['typing' => $is_typing]);
    exit();
}
