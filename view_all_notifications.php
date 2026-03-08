<?php
session_start();
$conn = new mysqli("localhost","root","","clubconnect");

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

$result = $conn->prepare("
    SELECT message, created_at, is_read 
    FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$result->bind_param("i",$user_id);
$result->execute();
$data = $result->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>All Notifications</title>
<style>
body{font-family:Segoe UI;background:#111;color:white;padding:40px;}
.notif{padding:15px;margin-bottom:10px;border-radius:10px;background:#222;}
.unread{border-left:5px solid #b31217;}
small{opacity:0.6;}
</style>
</head>
<body>

<h2>All Notifications</h2>

<?php while($row = $data->fetch_assoc()): ?>
<div class="notif <?php echo $row['is_read']==0?'unread':''; ?>">
    <?php echo htmlspecialchars($row['message']); ?>
    <br><small><?php echo $row['created_at']; ?></small>
</div>
<?php endwhile; ?>

</body>
</html>