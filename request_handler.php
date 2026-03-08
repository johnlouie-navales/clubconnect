<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clubconnect");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Verify user is a moderator
if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'moderator') {
    header("Location: home.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $request_id = (int)($_POST['request_id'] ?? 0);
    $applicant_id = (int)$_POST['applicant_id'];
    $club_id = (int)$_POST['club_id'];
    
    // Get club name for notification
    $club_res = $conn->query("SELECT club_name FROM clubs WHERE id = $club_id");
    if (!$club_res) {
        die("Error fetching club: " . $conn->error);
    }
    $club_data = $club_res->fetch_assoc();
    $club_name = $club_data['club_name'] ?? 'the club';
    
    if (isset($_POST['approve'])) {
        // 1. Mark request as approved
        if ($request_id > 0) {
            if (!$conn->query("UPDATE membership_requests SET status = 'approved' WHERE id = $request_id")) {
                die("Error updating request: " . $conn->error);
            }
        }
        
        // 2. Add user to official club members (using INSERT IGNORE to avoid duplicates)
        if (!$conn->query("INSERT IGNORE INTO club_memberships (user_id, club_id) VALUES ($applicant_id, $club_id)")) {
            die("Error adding member: " . $conn->error);
        }
        
        // 3. Notify the student of approval (optional - table may not exist)
        $notif_msg = "Your request to join $club_name has been approved! Welcome to the club!";
        $notif_msg = $conn->real_escape_string($notif_msg);
        $conn->query("INSERT INTO notifications (user_id, message, type) VALUES ($applicant_id, '$notif_msg', 'join_request')");
        
    } elseif (isset($_POST['decline'])) {
        // 1. Mark request as rejected
        if ($request_id > 0) {
            if (!$conn->query("UPDATE membership_requests SET status = 'rejected' WHERE id = $request_id")) {
                die("Error updating request: " . $conn->error);
            }
        }
        
        // 2. Notify the student of rejection (optional - table may not exist)
        $notif_msg = "Your request to join $club_name was declined.";
        $notif_msg = $conn->real_escape_string($notif_msg);
        $conn->query("INSERT INTO notifications (user_id, message, type) VALUES ($applicant_id, '$notif_msg', 'join_request')");
        
    } elseif (isset($_POST['remove_member'])) {
        // 1. Remove from official club members
        if (!$conn->query("DELETE FROM club_memberships WHERE user_id = $applicant_id AND club_id = $club_id")) {
            die("Error removing member: " . $conn->error);
        }
        
        // 2. Clear any pending requests so they can rejoin if they want
        if (!$conn->query("DELETE FROM membership_requests WHERE user_id = $applicant_id AND club_id = $club_id")) {
            die("Error clearing requests: " . $conn->error);
        }
        
        // 3. Notify the student they were removed (optional - table may not exist)
        $notif_msg = "You have been removed from $club_name.";
        $notif_msg = $conn->real_escape_string($notif_msg);
        $conn->query("INSERT INTO notifications (user_id, message, type) VALUES ($applicant_id, '$notif_msg', 'join_request')");
    }
    
    // Redirect back to mod dashboard
    header("Location: mod_dashboard.php?view=members");
    exit();
}
?>

