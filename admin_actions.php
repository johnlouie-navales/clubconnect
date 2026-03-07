<?php
session_start();

// Enable error reporting to diagnose "white screen" issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$conn = new mysqli("localhost", "root", "", "clubconnect");

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Ensure only admins can access this script
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    exit('Denied: Unauthorized Access');
}

$action = $_GET['action'] ?? '';

/* ===============================
    POST PUBLIC ANNOUNCEMENT
================================ */
if ($action === 'post_announcement') {
    // Sanitize inputs to prevent SQL injection
    $title = $conn->real_escape_string($_POST['title']);
    $message = $conn->real_escape_string($_POST['message']);
    $admin_id = $_SESSION['user_id'];

    $sql = "INSERT INTO announcements (admin_id, title, message) VALUES ('$admin_id', '$title', '$message')";

    if ($conn->query($sql)) {
        header("Location: admin_dashboard.php?status=announced");
        exit();
    } else {
        // This will show the exact SQL error if the table is missing or columns are wrong
        echo "Error: " . $conn->error;
        exit();
    }
}

/* ===============================
    SAVE CLUB (CREATE/UPDATE)
================================ */
if ($action === 'save_club') {
    $club_id = $conn->real_escape_string($_POST['club_id']);
    $name = $conn->real_escape_string($_POST['club_name']);
    $desc = $conn->real_escape_string($_POST['description']);
    $color = $conn->real_escape_string($_POST['hex_color']);

    // Handle Image Upload
    $banner_sql = "";
    if (!empty($_FILES['banner_image']['name'])) {
        $target_dir = "assetimages/";
        $file_name = time() . "_" . basename($_FILES["banner_image"]["name"]);
        $target_file = $target_dir . $file_name;
        if (move_uploaded_file($_FILES["banner_image"]["tmp_name"], $target_file)) {
            $banner_sql = ", banner_image = '$target_file'";
        }
    }

    if (!empty($club_id)) {
        // UPDATE EXISTING
        $conn->query("UPDATE clubs SET club_name='$name', description='$desc', hex_color='$color' $banner_sql WHERE id=$club_id");
    } else {
        // CREATE NEW
        $banner_val = !empty($file_name) ? "assetimages/$file_name" : "assetimages/default-banner.jpg";
        $conn->query("INSERT INTO clubs (club_name, description, hex_color, banner_image) VALUES ('$name', '$desc', '$color', '$banner_val')");
    }
    header("Location: admin_dashboard.php");
    exit();
}

/* ===============================
    DELETE POST
================================ */
if ($action === 'delete_post') {
    $id = (int)$_GET['id'];
    $conn->query("DELETE FROM club_posts WHERE id = $id");
    header("Location: admin_dashboard.php");
    exit();
}
?>