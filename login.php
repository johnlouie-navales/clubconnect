<?php
session_start();

// Database connection
$conn = new mysqli("localhost", "root", "", "clubconnect");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$error = "";

// Only run login logic if form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usn = trim($_POST['usn']);
    $password = $_POST['password'];

    if (!empty($usn) && !empty($password)) {

        $stmt = $conn->prepare("SELECT * FROM users WHERE usn = ?");
        $stmt->bind_param("s", $usn);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {

            $user = $result->fetch_assoc();

            if (password_verify($password, $user['password'])) {

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['fullname'] = $user['fullname'];
                $_SESSION['role'] = strtolower($user['role']); // Convert to lowercase for consistent checking
                $_SESSION['managed_club_id'] = $user['managed_club_id'];

                $_SESSION['profile_pic'] = !empty($user['profile_pic'])
                    ? $user['profile_pic']
                    : 'assetimages/default-user.png';

                // --- REDIRECT LOGIC START ---
                if ($_SESSION['role'] === 'admin') {
                    header("Location: admin_dashboard.php");
                } else {
                    header("Location: home.php");
                }
                // --- REDIRECT LOGIC END ---
                
                exit();

            } else {
                $error = "Invalid USN or password!";
            }

        } else {
            $error = "Invalid USN or password!";
        }

        $stmt->close();
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>ClubConnect - Login</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<style>
* { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', sans-serif; }

body {
    height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    background: linear-gradient(-45deg, #000844, #0036be, #583053, #b31217, #e52d27);
    background-size:400% 400%;
    animation: gradientMove 12s ease infinite;
}

@keyframes gradientMove {
    0% {background-position:0% 50%;}
    50% {background-position:100% 50%;}
    100% {background-position:0% 50%;}
}

.container {
    width:900px;
    height:500px;
    display:flex;
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 20px 50px rgba(0,0,0,0.4);
    backdrop-filter:blur(10px);
}

.left-panel {
    width:40%;
    background:rgba(255,255,255,0.1);
    color:white;
    display:flex;
    flex-direction:column;
    justify-content:center;
    padding:60px;
}

.login-logo img {
    height:120px;
    width:auto;
    filter:drop-shadow(0 4px 10px rgba(0,0,0,0.3));
    margin-bottom:20px;
}

.left-panel h1 { font-size:28px; margin-bottom:15px; }
.left-panel p { font-size:15px; opacity:0.9; line-height:1.4; }

.right-panel {
    width:60%;
    background:white;
    display:flex;
    flex-direction:column;
    justify-content:center;
    align-items:center;
    padding:40px;
}

.right-panel h2 { margin-bottom:20px; color:#203a43; }

form { width:100%; max-width:320px; }

input {
    width:100%;
    padding:12px 20px;
    margin:10px 0;
    border-radius:25px;
    border:1px solid #ccc;
    outline:none;
    transition:0.3s;
}

input:focus {
    border-color:#b31217;
    box-shadow:0 0 8px rgba(179,18,23,0.2);
}

button {
    width:100%;
    padding:12px;
    border:none;
    border-radius:25px;
    background:linear-gradient(to right,#203a43,#b31217);
    color:white;
    font-weight:bold;
    cursor:pointer;
    transition:0.3s;
    margin-top:10px;
}

button:hover {
    transform:scale(1.02);
    box-shadow:0 5px 15px rgba(0,0,0,0.2);
}

.error {
    color:#e74c3c;
    font-size:14px;
    margin-top:15px;
    text-align:center;
    font-weight:500;
}

@media(max-width:768px){
    .container { flex-direction:column; height:auto; width:90%; }
    .left-panel, .right-panel { width:100%; padding:30px; text-align:center; }
}
</style>
</head>

<body>

<div class="container">

    <div class="left-panel">
        <div class="login-logo">
            <img src="/clubconnect/assetimages/cc.png" alt="ClubConnect Logo">
        </div>
        <h1>Welcome Back!</h1>
        <p>Access your student portal, discover communities, and stay updated with school activities.</p>
    </div>

    <div class="right-panel">
        <h2>Sign In</h2>

        <form method="POST" action="">
            <input type="text" name="usn" placeholder="Enter USN" required>
            <input type="password" name="password" placeholder="Enter Password" required>
            <button type="submit">Login</button>

            <?php if (!empty($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>

        </form>
    </div>

</div>

</body>
</html>