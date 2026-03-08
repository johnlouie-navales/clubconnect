<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clubconnect");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_msg = "";
$error_msg = "";

// 1. Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Logic for Profile Picture
    if (!empty($_FILES["profile_pic"]["name"])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        
        $file_ext = pathinfo($_FILES["profile_pic"]["name"], PATHINFO_EXTENSION);
        $target_file = $target_dir . "profile_" . $user_id . "." . $file_ext;

        if (move_uploaded_file($_FILES["profile_pic"]["tmp_name"], $target_file)) {
            $conn->query("UPDATE users SET profile_pic='$target_file' WHERE id='$user_id'");
            $_SESSION['profile_pic'] = $target_file;
        }
    }

    // Logic for Password Update
    if (!empty($new_password)) {
        if ($new_password === $confirm_password) {
            // Hash the password for security
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $conn->query("UPDATE users SET password='$hashed_password' WHERE id='$user_id'");
            $success_msg = "Profile and Password updated!";
        } else {
            $error_msg = "Passwords do not match!";
        }
    }

    // Update Email
    $conn->query("UPDATE users SET email='$email' WHERE id='$user_id'");
    if(empty($error_msg)) $success_msg = "Profile updated successfully!";
}

// 2. Fetch current data
$result = $conn->query("SELECT * FROM users WHERE id='$user_id'");
$user = $result->fetch_assoc();
if (!$user) {
    $user = ['email' => '', 'profile_pic' => 'assetimages/default-user.png'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Settings - ClubConnect</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --primary: #b31217; --dark: #0f2027; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--dark); color: white; display: flex; justify-content: center; align-items: center; min-height: 100vh; margin:0; }
        .card { background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); padding: 30px; border-radius: 20px; width: 100%; max-width: 400px; border: 1px solid rgba(255,255,255,0.1); }
        .profile-img { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; border: 3px solid var(--primary); display: block; margin: 0 auto 15px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-size: 13px; margin-bottom: 5px; opacity: 0.8; }
        input { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.2); background: rgba(0,0,0,0.3); color: white; box-sizing: border-box; }
        .btn { width: 100%; padding: 12px; background: var(--primary); border: none; border-radius: 8px; color: white; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .alert { padding: 10px; border-radius: 5px; margin-bottom: 15px; font-size: 13px; text-align: center; }
        .success { background: #27ae60; } .error { background: #c0392b; }
        .back-btn { display: block; text-align: center; margin-top: 15px; color: #aaa; text-decoration: none; font-size: 13px; }
    </style>
</head>
<body>

<div class="card">
    <h2 style="text-align:center">Account Settings</h2>
    
    <?php if($success_msg): ?> <div class="alert success"><?php echo $success_msg; ?></div> <?php endif; ?>
    <?php if($error_msg): ?> <div class="alert error"><?php echo $error_msg; ?></div> <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div style="text-align:center">
            <img id="prev" src="<?php echo $user['profile_pic']; ?>" class="profile-img">
            <input type="file" name="profile_pic" style="font-size: 11px;" onchange="document.getElementById('prev').src = window.URL.createObjectURL(this.files[0])">
        </div>

        <div class="form-group" style="margin-top:20px;">
            <label>Email Address</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
        </div>

        <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 10px; margin-top: 20px;">
            <p style="font-size: 12px; margin-bottom: 10px; color: #ff9f43;">Leave blank to keep current password</p>
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="new_password" placeholder="••••••••">
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input type="password" name="confirm_password" placeholder="••••••••">
            </div>
        </div>

        <button type="submit" class="btn">Update Account</button>
    </form>
    
    <a href="home.php" class="back-btn">← Back to Dashboard</a>
</div>

</body>
</html>