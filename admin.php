<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'Admin') {
    header("Location: login.php");
    exit();
}
?>

<h2>Admin Dashboard</h2>
<p>Welcome <?php echo $_SESSION['fullname']; ?> (Admin)</p>

<ul>
    <li>Manage Clubs</li>
    <li>Manage Users</li>
    <li>System Settings</li>
</ul>

<a href="logout.php">Logout</a>