<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clubconnect");

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'moderator') {
    header("Location: home.php"); exit();
}

$club_id = $_SESSION['managed_club_id'];
$view = $_GET['view'] ?? 'members';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Moderator Panel</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --accent: #b31217; --bg: #0f172a; --card: #1e293b; --text: #f8fafc; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); padding: 40px; }
        .container { max-width: 900px; margin: 0 auto; }
        .tab-nav { display: flex; gap: 20px; margin-bottom: 30px; }
        .tab { text-decoration: none; color: #94a3b8; padding: 10px 20px; border-radius: 10px; background: var(--card); }
        .tab.active { background: var(--accent); color: white; }
        .request-row { background: var(--card); padding: 20px; border-radius: 15px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-left: 5px solid #eab308; }
        .btn { border: none; padding: 8px 15px; border-radius: 8px; cursor: pointer; font-weight: bold; color: white; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h1>Moderator Dashboard</h1>
        <a href="home.php" style="color:white;">Back to Home</a>
    </div>

    <div class="tab-nav">
        <a href="?view=post" class="tab <?php echo $view=='post'?'active':''; ?>">Posts</a>
        <a href="?view=members" class="tab <?php echo $view=='members'?'active':''; ?>">Requests & Members</a>
        <a href="?view=messages" class="tab <?php echo $view=='messages'?'active':''; ?>">Inbox</a>
    </div>

    <?php if ($view == 'members'): ?>
        <h2>Pending Membership Requests</h2>
        <?php
        $reqs = $conn->query("SELECT r.*, u.fullname, u.usn FROM membership_requests r JOIN users u ON r.user_id = u.id WHERE r.club_id = $club_id AND r.status = 'pending'");
        if ($reqs->num_rows > 0):
            while($r = $reqs->fetch_assoc()): ?>
                <div class="request-row">
                    <div>
                        <strong><?php echo $r['fullname']; ?></strong><br>
                        <small>USN: <?php echo $r['usn']; ?> • Requested on: <?php echo date('M d', strtotime($r['request_date'])); ?></small>
                    </div>
                    <form action="request_handler.php" method="POST" style="display:flex; gap:10px;">
                        <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                        <input type="hidden" name="applicant_id" value="<?php echo $r['user_id']; ?>">
                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                        <button type="submit" name="approve" class="btn" style="background:#22c55e;">Approve</button>
                        <button type="submit" name="decline" class="btn" style="background:#ef4444;">Decline</button>
                    </form>
                </div>
            <?php endwhile;
        else: ?>
            <p style="color:#64748b;">No pending requests at the moment.</p>
        <?php endif; ?>

        <h2 style="margin-top:50px;">Approved Members</h2>
        <?php
        $members = $conn->query("SELECT u.*, cm.joined_at FROM club_memberships cm LEFT JOIN users u ON cm.user_id = u.id WHERE cm.club_id = $club_id ORDER BY u.fullname ASC");
        
        if ($members && $members->num_rows > 0):
            while($m = $members->fetch_assoc()): ?>
                <div class="request-row" style="border-left-color: #22c55e;">
                    <div>
                        <strong><?php echo htmlspecialchars($m['fullname']); ?></strong><br>
                        <small>USN: <?php echo htmlspecialchars($m['usn']); ?> • Member since: <?php echo $m['joined_at'] ? date('M d, Y', strtotime($m['joined_at'])) : 'N/A'; ?></small>
                    </div>
                    <form action="request_handler.php" method="POST" style="display:flex; gap:10px;" onsubmit="return confirm('Remove this member from the club?');">
                        <input type="hidden" name="request_id" value="">
                        <input type="hidden" name="applicant_id" value="<?php echo $m['id']; ?>">
                        <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
                        <button type="submit" name="remove_member" class="btn" style="background:#ef4444;">Remove</button>
                    </form>
                </div>
            <?php endwhile;
        else: ?>
            <p style="color:#64748b;">No approved members yet.</p>
        <?php endif; ?>
        <?php endif; ?>
</div>

<script>lucide.createIcons();</script>
</body>
</html>