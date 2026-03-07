<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clubconnect");

if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }

$club_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];
$user_role = strtolower($_SESSION['role'] ?? '');

$club = $conn->query("SELECT * FROM clubs WHERE id = $club_id")->fetch_assoc();
if (!$club) { echo "Club not found."; exit(); }

$is_assigned_moderator = ($user_role === 'moderator' && isset($_SESSION['managed_club_id']) && $_SESSION['managed_club_id'] == $club_id);

// Moderator floating bubble data
$notif_count = 0;
$msg_count = 0;

if($is_assigned_moderator){
   // Only count UNREAD notifications
    $notif_res = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = $user_id AND is_read = 0");
    if($notif_res){
        $notif_count = $notif_res->fetch_assoc()['total'];
    }

    // Only count UNREAD messages
    $msg_res = $conn->query("SELECT COUNT(*) as total FROM messages WHERE receiver_id = $user_id AND is_read = 0");
    if($msg_res){
        $msg_count = $msg_res->fetch_assoc()['total'];
    }
}

// --- RSVP HANDLING LOGIC ---
if (isset($_POST['rsvp_action'])) {
    $post_id = (int)$_POST['post_id'];
    $status = $_POST['rsvp_status']; // 'joining' or 'not_joining'
    
    // Check if user already responded
    $check = $conn->query("SELECT id FROM event_responses WHERE user_id = $user_id AND post_id = $post_id");
    
    if ($check->num_rows > 0) {
        $conn->query("UPDATE event_responses SET status = '$status' WHERE user_id = $user_id AND post_id = $post_id");
    } else {
        $conn->query("INSERT INTO event_responses (user_id, post_id, status) VALUES ($user_id, $post_id, '$status')");
    }
    header("Location: club_home.php?id=$club_id"); exit();
}
function containsProfanity($text) {
    $badWords = [
        'puta','gago','tanga','bobo','pakshet','kantut','hayop',
        'fuck','shit','asshole','bitch','dick','pussy','bastard',
        'pendejo','mierda','verga', 'nigga', 'negro', 'dumbass', 'ass'
        ,'kupal', 'tangina' , 'pota' , 'powtah', 'gagu', 'iyot', 'pisot'
        ,'tite', 'kado'
    ];

    $normalized = strtolower($text);

    // Remove extra spaces & symbols to detect hidden profanity
    $normalized = preg_replace('/[^a-z0-9]/i', '', $normalized);

    foreach ($badWords as $word) {
        $cleanWord = preg_replace('/[^a-z0-9]/i', '', strtolower($word));

        if (strpos($normalized, $cleanWord) !== false) {
            return true;
        }
    }

    return false;
}
if (isset($_POST['add_comment'])) {

    $post_id = (int)$_POST['post_id'];
    $raw_text = trim($_POST['comment_text']);

    if (empty($raw_text)) {
        $_SESSION['comment_error'] = "Comment cannot be empty.";
        header("Location: club_home.php?id=$club_id");
        exit();
    }

    // 🚫 PROFANITY CHECK
    if (containsProfanity($raw_text)) {

        // Optional: Notify moderator
        $u_info = $conn->query("SELECT fullname FROM users WHERE id = $user_id")->fetch_assoc();
        $sender = $conn->real_escape_string($u_info['fullname'] ?? 'A user');
        $cleanText = $conn->real_escape_string($raw_text);

        $msg = "Blocked comment from $sender: \"$cleanText\"";

        $mod_query = "SELECT id FROM users 
                      WHERE LOWER(role) = 'moderator' 
                      AND managed_club_id = $club_id 
                      LIMIT 1";

        $mod_res = $conn->query($mod_query);

        if ($mod_res && $mod_res->num_rows > 0) {
            $mod_id = $mod_res->fetch_assoc()['id'];
            $conn->query("INSERT INTO notifications (user_id, message, type) 
                          VALUES ($mod_id, '$msg', 'flagged_comment')");
        }

        // 🚫 BLOCK COMMENT
        $_SESSION['comment_error'] = "⚠ Your comment violates our community guidelines and was not posted.";
        header("Location: club_home.php?id=$club_id");
        exit();
    }

    // ✅ SAFE COMMENT — SAVE IT
    $safe_text = $conn->real_escape_string($raw_text);
    $conn->query("INSERT INTO post_comments (post_id, user_id, comment_text) 
                  VALUES ($post_id, $user_id, '$safe_text')");

    header("Location: club_home.php?id=$club_id");
    exit();
}
    
// --- MEMBERSHIP REQUEST HANDLING ---
if (isset($_POST['join_club'])) {
    // 1. Send Join Request
    $conn->query("INSERT INTO membership_requests (user_id, club_id, status) VALUES ($user_id, $club_id, 'pending')");
    
    // 2. Notify the Moderator
    $u_info = $conn->query("SELECT fullname FROM users WHERE id = $user_id")->fetch_assoc();
    $sender = $conn->real_escape_string($u_info['fullname']);
    $msg = "$sender has requested to join " . $club['club_name'];

    $mod_res = $conn->query("SELECT id FROM users WHERE LOWER(role) = 'moderator' AND managed_club_id = $club_id LIMIT 1");
    if ($mod_res->num_rows > 0) {
        $mod_id = $mod_res->fetch_assoc()['id'];
        $conn->query("INSERT INTO notifications (user_id, message, type) VALUES ($mod_id, '$msg', 'join_request')");
    }
    header("Location: club_home.php?id=$club_id"); exit();
}

if (isset($_POST['manage_request'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['manage_request']; // This gets the 'value' of the button clicked
    
    // Fetch request details
    $req_res = $conn->query("SELECT * FROM membership_requests WHERE id = $request_id");
    if (!$req_res) {
        die("Error fetching request: " . $conn->error);
    }
    $req_data = $req_res->fetch_assoc();
    if (!$req_data) {
        die("Request not found");
    }
    $applicant_id = $req_data['user_id'];

    if ($action === 'approve') {
        // 1. Mark request as approved
        if (!$conn->query("UPDATE membership_requests SET status = 'approved' WHERE id = $request_id")) {
            die("Error updating request: " . $conn->error);
        }
        
        // 2. Insert into official club_memberships (This makes them appear in the Member List)
        if (!$conn->query("INSERT IGNORE INTO club_memberships (user_id, club_id) VALUES ($applicant_id, $club_id)")) {
            die("Error adding member: " . $conn->error);
        }
        
        $notif_msg = "Your request to join " . $club['club_name'] . " has been approved! Welcome!";
    } else {
        if (!$conn->query("UPDATE membership_requests SET status = 'rejected' WHERE id = $request_id")) {
            die("Error updating request: " . $conn->error);
        }
        $notif_msg = "Your request to join " . $club['club_name'] . " was declined.";
    }
    
    // Notify the student (optional - table may not exist)
        $conn->query("INSERT INTO notifications (user_id, message, type) VALUES ($applicant_id, '$notif_msg', 'status')");
    header("Location: club_home.php?id=$club_id"); exit();
}

// --- REMOVE MEMBER LOGIC ---
if (isset($_POST['remove_member'])) {
    $m_id = (int)$_POST['target_user_id'];
    // Remove from membership table
    $conn->query("DELETE FROM club_memberships WHERE user_id = $m_id AND club_id = $club_id");
    // Also clear their pending request so they can try to join again later if they want
    $conn->query("DELETE FROM membership_requests WHERE user_id = $m_id AND club_id = $club_id");
    
    header("Location: club_home.php?id=$club_id"); exit();
}
$is_member = $conn->query("SELECT id FROM club_memberships WHERE user_id = $user_id AND club_id = $club_id")->num_rows > 0;
$has_pending = $conn->query("SELECT id FROM membership_requests WHERE user_id = $user_id AND club_id = $club_id AND status = 'pending'")->num_rows > 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($club['club_name']); ?></title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root { --accent: #b31217; --bg: #0f172a; --card: #032f76; --text: #f8fafc; }
        * { box-sizing: border-box; } 
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding-top: 65px; }

        .topbar { 
            position: fixed; top: 0; left: 0; right: 0; height: 65px; 
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            display: flex; align-items: center; justify-content: space-between;
            padding: 0 30px; z-index: 2000; border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .topbar-left { display: flex; align-items: center; gap: 10px; text-decoration: none; color: white; font-weight: 600; }

        .dropdown { position: relative; }
        .drop-btn { background: var(--accent); color: white; border: none; padding: 10px 18px; border-radius: 10px; cursor: pointer; display: flex; align-items: center; gap: 8px; font-weight: 600; box-shadow: 0 4px 15px rgba(0,0,0,0.3); }
        
        .drop-content {
            display: none; position: absolute; right: 0; top: 50px; min-width: 220px;
            background: var(--card); border-radius: 12px; border: 1px solid rgba(255,255,255,0.1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.5); overflow: hidden; z-index: 2001;
            animation: fadeIn 0.2s ease;
        }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .drop-content button {
            width: 100%; padding: 14px 20px; text-align: left; background: none; border: none;
            color: #cbd5e1; cursor: pointer; display: flex; align-items: center; gap: 12px; font-size: 14px;
        }
        .drop-content button:hover { background: rgba(255,255,255,0.08); color: white; }
        .divider { height: 1px; background: rgba(255,255,255,0.1); margin: 5px 0; }

        .hero-banner {
            height: 350px; background: linear-gradient(to bottom, rgba(15, 23, 42, 0.2), var(--bg)), url('<?php echo $club['logo']; ?>') no-repeat center/cover;
            display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
        }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); backdrop-filter: blur(8px); z-index: 3000; justify-content: center; align-items: center; }
        .modal-content { background: var(--card); width: 95%; max-width: 500px; border-radius: 20px; padding: 30px; border: 1px solid rgba(255,255,255,0.1); position: relative; }

        .main-feed { max-width: 580px; margin: -50px auto 40px auto; padding: 0 20px; position: relative; z-index: 10; }
        .post-card { background: var(--card); border-radius: 16px; padding: 20px; margin-bottom: 25px; border: 1px solid rgba(255,255,255,0.08); box-shadow: 0 4px 20px rgba(0,0,0,0.3); overflow: hidden; }
        .post-img { width: 100%; max-height: 300px; object-fit: cover; border-radius: 12px; margin-bottom: 15px; }
        .event-badge { background: rgba(179, 18, 23, 0.15); color: #ff4d4d; padding: 6px 12px; border-radius: 8px; font-size: 12px; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 12px; border: 1px solid rgba(179, 18, 23, 0.3); }

        /* RSVP & Action Styling */
        .post-footer { border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; margin-top: 15px; display: flex; justify-content: space-between; align-items: center; }
        .analytics { display: flex; gap: 15px; font-size: 12px; color: #94a3b8; }
        .analytics b { color: white; }
        .post-actions { display: flex; gap: 8px; }
        .btn-rsvp { border: none; padding: 8px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 5px; color: white; transition: 0.2s; }
        .btn-join { background: #16a34a; } .btn-join:hover { background: #15803d; }
        .btn-skip { background: #475569; } .btn-skip:hover { background: #334155; }
        .btn-map { background: #2563eb; text-decoration: none; } .btn-map:hover { background: #1d4ed8; }
        
        input, textarea, select { width: 100%; background: #0f172a; border: 1px solid #334155; color: white; padding: 12px; border-radius: 8px; margin-bottom: 12px; }
    /* COMMENT SECTION */
.comment-section {
    margin-top: 15px;
    border-top: 1px solid rgba(255,255,255,0.05);
    padding-top: 15px;
}

.comment-item {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
}

.comment-avatar {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    object-fit: cover;
}

.comment-content {
    background: #0f172a;
    padding: 8px 12px;
    border-radius: 10px;
    flex: 1;
}

.comment-header {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    margin-bottom: 4px;
}

.comment-name {
    font-weight: 600;
    color: white;
}

.comment-time {
    color: #94a3b8;
    font-size: 11px;
}

.comment-form {
    display: flex;
    gap: 8px;
    margin-top: 10px;
}

.comment-form input {
    flex: 1;
    margin: 0;
}
/* FLOATING MODERATOR BUBBLE */
.mod-bubble {
    position: fixed;
    bottom: 25px;
    right: 25px;
    width: 65px;
    height: 65px;
    border-radius: 50%;
    background: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 10px 25px rgba(0,0,0,0.4);
    z-index: 5000;
    transition: 0.3s;
}

.mod-bubble:hover {
    transform: scale(1.1);
}

.bubble-badge {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #ef4444;
    color: white;
    font-size: 11px;
    padding: 3px 6px;
    border-radius: 50%;
}

.mod-panel {
    position: fixed;
    bottom: 100px;
    right: 25px;
    width: 350px;
    max-height: 450px;
    background: var(--card);
    border-radius: 18px;
    box-shadow: 0 15px 40px rgba(0,0,0,0.6);
    display: none;
    flex-direction: column;
    overflow: hidden;
    z-index: 5001;
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateY(20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.mod-header {
    display: flex;
    justify-content: space-between;
    padding: 15px;
    background: rgba(255,255,255,0.05);
    font-weight: bold;
}

.mod-tabs {
    display: flex;
}

.mod-tabs button {
    flex: 1;
    padding: 10px;
    background: none;
    border: none;
    color: #cbd5e1;
    cursor: pointer;
}

.mod-tabs button.active {
    background: rgba(179,18,23,0.2);
    color: white;
}

.mod-content {
    flex: 1;
    overflow-y: auto;
    padding: 12px;
}

.mod-item {
    padding: 15px;
    border-bottom: 1px solid rgba(255,255,255,0.08);
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* The actual button styling */
.btn-mark-read {
    align-self: flex-start;
    background: rgba(255, 255, 255, 0.1);
    color: #f8fafc;
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 4px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn-mark-read:hover {
    background: var(--accent); /* Uses your red accent color */
    border-color: var(--accent);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(179, 18, 23, 0.3);
}

.btn-mark-read:active {
    transform: translateY(0);
}
.mod-bubble.pulse {
    animation: pulse 1.2s infinite;
}

@keyframes pulse {
    0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.6); }
    70% { box-shadow: 0 0 0 15px rgba(239,68,68,0); }
    100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); }
}
.student-msg{
background:#1e293b;
padding:6px;
margin:5px 0;
border-radius:6px;
}

.mod-msg{
background:#b31217;
padding:6px;
margin:5px 0;
border-radius:6px;
text-align:right;
}
    </style>
</head>
<body>

<nav class="topbar">
    <a href="home.php" class="topbar-left">
        <i data-lucide="chevron-left" size="20"></i> <span>Home</span>
    </a>

    <div class="topbar-right">
        <?php if ($is_assigned_moderator): ?>
            <div class="dropdown">
                <button class="drop-btn" id="dropBtn">
                    <i data-lucide="shield-check" size="18"></i> Manage <i data-lucide="chevron-down" size="14"></i>
                </button>
                <div class="drop-content" id="dropMenu">
                    <button onclick="openModal('post')"><i data-lucide="plus-circle" size="16"></i> Create Post</button>
                    <button onclick="openModal('members')"><i data-lucide="users" size="16"></i> Member Requests</button>
                    <button onclick="openModal('list')"><i data-lucide="users" size="16"></i> View Members</button>
                </div>
            </div>
        <?php elseif ($user_role === 'student'): ?>
    <?php if (!$is_member && !$has_pending): ?>
        <form method="POST"> <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
            <button type="submit" name="join_club" class="drop-btn">Join Club</button>
        </form>
    <?php elseif ($has_pending): ?>
        <button class="drop-btn" style="background:#eab308; cursor:default;">Request Pending</button>
    <?php endif; ?>
<?php endif; ?>
    </div>
</nav>

<div class="hero-banner">
    <h1 style="font-size: 3rem; margin: 0;"><?php echo htmlspecialchars($club['club_name']); ?></h1>
    <p style="color: #94a3b8; max-width: 500px;"><?php echo htmlspecialchars($club['description']); ?></p>
</div>

<div class="main-feed">
    <?php
    // Optimized query: Get posts with RSVP counts in a single query
    $posts_sql = "SELECT p.*, 
                    COALESCE(SUM(CASE WHEN er.status = 'joining' THEN 1 ELSE 0 END), 0) as join_count,
                    COALESCE(SUM(CASE WHEN er.status = 'not_joining' THEN 1 ELSE 0 END), 0) as skip_count
                 FROM club_posts p
                 LEFT JOIN event_responses er ON p.id = er.post_id
                 WHERE p.club_id = $club_id
                 GROUP BY p.id
                 ORDER BY p.created_at DESC";
    
    $posts = $conn->query($posts_sql);
    while($p = $posts->fetch_assoc()): 
        $pid = $p['id'];
        $join_count = $p['join_count'];
        $skip_count = $p['skip_count'];
    ?>
        <div class="post-card">
            <?php if(!empty($p['image_url'])): ?>
                <img src="<?php echo $p['image_url']; ?>" class="post-img">
            <?php endif; ?>

            <?php if(!empty($p['event_date'])): ?>
                <div class="event-badge">
                    <i data-lucide="calendar" size="14"></i>
                    <?php echo date('M d, g:i A', strtotime($p['event_date'])); ?>
                </div>
            <?php endif; ?>
            
            <h2 style="margin:0; font-size: 1.25rem;"><?php echo htmlspecialchars($p['title']); ?></h2>
            <p style="color:#cbd5e1; font-size: 14px; line-height:1.6; margin: 12px 0;"><?php echo nl2br(htmlspecialchars($p['content'])); ?></p>
            
            <div class="post-footer">
                <div class="analytics">
                    <span><b><?php echo $join_count; ?></b> Joining</span>
                    <span><b><?php echo $skip_count; ?></b> Skipping</span>
                </div>

                <div class="post-actions">
                    <form method="POST" style="display:flex; gap:5px;">
    <input type="hidden" name="post_id" value="<?php echo $pid; ?>">

    <button type="submit"
            name="rsvp_status"
            value="joining"
            class="btn-rsvp btn-join">
        Join
    </button>

    <button type="submit"
            name="rsvp_status"
            value="not_joining"
            class="btn-rsvp btn-skip">
        Skip
    </button>

    <input type="hidden" name="rsvp_action" value="1">
</form>

                    <?php if(!empty($p['location_address'])): ?>
                        <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($p['location_address']); ?>" target="_blank" class="btn-rsvp btn-map">
                            <i data-lucide="map" size="14"></i> Map
                        </a>
                    <?php endif; ?>
                </div>
            </div>

<!-- COMMENT SECTION -->
<div class="comment-section">

<?php
$comments = $conn->query("
    SELECT pc.*, u.fullname, u.profile_pic 
    FROM post_comments pc
    JOIN users u ON pc.user_id = u.id
    WHERE pc.post_id = $pid
    ORDER BY pc.created_at ASC
");

while($c = $comments->fetch_assoc()):
?>
    <div class="comment-item">
        <img src="<?php echo !empty($c['profile_pic']) ? $c['profile_pic'] : 'default-avatar.png'; ?>" class="comment-avatar">
        
        <div class="comment-content">
            <div class="comment-header">
                <span class="comment-name"><?php echo htmlspecialchars($c['fullname']); ?></span>
                <span class="comment-time">
                    <?php echo date('M d, g:i A', strtotime($c['created_at'])); ?>
                </span>
            </div>
            <div>
                <?php echo nl2br(htmlspecialchars($c['comment_text'])); ?>
            </div>
        </div>
    </div>
<?php endwhile; ?>

<?php if(isset($_SESSION['comment_error'])): ?>
    <div style="
        background: rgba(220,38,38,0.15);
        border: 1px solid #dc2626;
        color: #fecaca;
        padding: 10px;
        border-radius: 8px;
        margin-bottom: 10px;
        font-size: 13px;">
        <?php 
            echo $_SESSION['comment_error']; 
            unset($_SESSION['comment_error']); 
        ?>
    </div>
<?php endif; ?>
<!-- ADD COMMENT FORM -->
<form method="POST" class="comment-form" style="display: flex; gap: 8px; margin-top: 15px; align-items: center;">
    <input type="hidden" name="post_id" value="<?php echo $pid; ?>">
    <input type="text" name="comment_text" placeholder="Write a comment..." required 
           style="flex: 1; margin: 0; background: #0f172a; border: 1px solid #334155; color: white; padding: 10px; border-radius: 8px;">
    
    <button type="submit" name="add_comment" 
            style="background: var(--accent); color: white; border: none; width: 42px; height: 42px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;">
        <i data-lucide="send" size="18"></i>
    </button>
</form>

</div>
        </div>
    <?php endwhile; ?>
</div>

<div id="modal-post" class="modal-overlay">
    <div class="modal-content">
        <button onclick="closeModals()" style="position:absolute; top:20px; right:20px; background:none; border:none; color:white; cursor:pointer;"><i data-lucide="x"></i></button>
        <h3>New Event/Announcement</h3>
        <form action="post_handler.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
            <input type="text" name="title" placeholder="Event Title" required>
            <label style="font-size: 12px; color: #94a3b8;">Event Date (Optional)</label>
            <input type="datetime-local" name="event_date">
            <input type="text" name="location_address" placeholder="Event Location">
            <textarea name="content" rows="3" placeholder="Description..." required></textarea>
            <input type="file" name="post_image">
            <button type="submit" name="submit_post" style="width:100%; padding:15px; background:var(--accent); color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer; margin-top:10px;">Publish</button>
        </form>
    </div>
</div>
<div id="modal-list" class="modal-overlay">
    <div class="modal-content">
        <button onclick="closeModals()" style="position:absolute; top:20px; right:20px; background:none; border:none; color:white; cursor:pointer;"><i data-lucide="x"></i></button>
        <h3>Official Members</h3>
        <div style="max-height: 400px; overflow-y: auto; margin-top:15px;">
            <?php
            $members = $conn->query("
                SELECT u.id, u.fullname, u.profile_pic, cm.joined_at 
                FROM club_memberships cm 
                JOIN users u ON cm.user_id = u.id 
                WHERE cm.club_id = $club_id 
                ORDER BY u.fullname ASC
            ");

            if ($members->num_rows > 0):
                while($m = $members->fetch_assoc()): ?>
                    <div style="display: flex; align-items: center; gap: 12px; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);">
                        <img src="<?php echo !empty($m['profile_pic']) ? $m['profile_pic'] : 'default-avatar.png'; ?>" 
                             style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent);">
                        
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($m['fullname']); ?></div>
                            <div style="font-size: 11px; color: #64748b;">Joined <?php echo date('M d, Y', strtotime($m['joined_at'])); ?></div>
                        </div>

                        <form method="POST" onsubmit="return confirm('Are you sure you want to remove this member?');" style="margin:0;">
                            <input type="hidden" name="target_user_id" value="<?php echo $m['id']; ?>">
                            <button type="submit" name="remove_member" style="background:none; border:none; color:#f87171; cursor:pointer; padding:5px;">
                                <i data-lucide="user-minus" size="18"></i>
                            </button>
                        </form>
                    </div>
                <?php endwhile;
            else: ?>
                <div style="text-align:center; padding:30px; color:#64748b;">No official members yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="modal-members" class="modal-overlay">
    <div class="modal-content">
        <button onclick="closeModals()" style="position:absolute; top:20px; right:20px; background:none; border:none; color:white; cursor:pointer;"><i data-lucide="x"></i></button>
        <h3>Pending Requests</h3>
        <div style="max-height: 400px; overflow-y: auto;">
            <?php
            $requests = $conn->query("SELECT mr.*, u.fullname FROM membership_requests mr JOIN users u ON mr.user_id = u.id WHERE mr.club_id = $club_id AND mr.status = 'pending'");
            if ($requests->num_rows > 0):
                while($r = $requests->fetch_assoc()): ?>
                    <div style="background: rgba(255,255,255,0.05); padding: 15px; border-radius: 12px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                        <span><?php echo htmlspecialchars($r['fullname']); ?></span>
                        <form method="POST" style="display: flex; gap: 5px; margin: 0;">
                            <input type="hidden" name="request_id" value="<?php echo $r['id']; ?>">
                            <button type="submit" name="manage_request" value="approve" style="background: #16a34a; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">Approve</button>
                            <button type="submit" name="manage_request" value="reject" style="background: #dc2626; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">Reject</button>
                        </form>
                    </div>
                <?php endwhile;
            else: ?>
                <p style="color: #94a3b8; text-align: center;">No pending requests.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    lucide.createIcons();
    const dropBtn = document.getElementById('dropBtn');
    const dropMenu = document.getElementById('dropMenu');
    if(dropBtn) {
        dropBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            dropMenu.style.display = dropMenu.style.display === 'block' ? 'none' : 'block';
        });
    }
    function openModal(id) {
        closeModals();
        if(dropMenu) dropMenu.style.display = 'none';
        document.getElementById('modal-' + id).style.display = 'flex';
    }
    function closeModals() {
        document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
    }
    window.onclick = (e) => {
        if (e.target.className === 'modal-overlay') closeModals();
        if (dropMenu && !dropBtn.contains(e.target)) dropMenu.style.display = 'none';
    }
</script>
<script>
function toggleModPanel(){
    const panel = document.getElementById("modPanel");
    panel.style.display = panel.style.display === "flex" ? "none" : "flex";
    panel.style.flexDirection = "column";
    loadModeratorData();
}

function switchTab(tab){
    document.getElementById("notifTab").style.display = tab === "notif" ? "block" : "none";
    document.getElementById("msgTab").style.display = tab === "msg" ? "block" : "none";

    document.querySelectorAll(".mod-tabs button").forEach(btn => btn.classList.remove("active"));
    event.target.classList.add("active");
}

let lastCount = 0;

function loadModeratorData(){

    fetch("moderator_fetch.php?type=count")
    .then(res=>res.json())
    .then(data=>{
        const total = data.total;

        if(total > lastCount){
            document.querySelector(".mod-bubble").classList.add("pulse");
            document.getElementById("notifSound").play();
        }

        lastCount = total;
    });

    fetch("moderator_fetch.php?type=notif")
    .then(res=>res.text())
    .then(data=>{
        document.getElementById("notifTab").innerHTML = data;
    });

    fetch("moderator_fetch.php?type=msg")
    .then(res=>res.text())
    .then(data=>{
        document.getElementById("msgTab").innerHTML = data;
    });
}
// AUTO REFRESH
setInterval(loadModeratorData, 4000);

function markNotif(id) {
    // 1. Visually hide the item immediately for a snappy feel
    const item = event.target.closest('.mod-item');
    if(item) item.style.opacity = '0.5';

    fetch("moderator_fetch.php?type=mark_notif&id=" + id)
        .then(() => {
            updateBadgeCount(); // Update the bubble number immediately
            loadModeratorData(); // Reload the lists
        });
}

function markMsg(id) {
    const item = event.target.closest('.mod-item');
    if(item) item.style.opacity = '0.5';

    fetch("moderator_fetch.php?type=msg")
    .then(res=>res.text())
    .then(data=>{
    document.getElementById("inboxList").innerHTML=data;
    });
            }

// Helper function to specifically refresh the badge number
function updateBadgeCount() {
    fetch("moderator_fetch.php?type=count")
        .then(res => res.json())
        .then(data => {
            const badge = document.querySelector(".bubble-badge");
            const bubble = document.querySelector(".mod-bubble");
            
            if (data.total > 0) {
                if (badge) {
                    badge.innerText = data.total;
                } else {
                    // If badge didn't exist (count was 0), create it
                    const newBadge = document.createElement('span');
                    newBadge.className = 'bubble-badge';
                    newBadge.innerText = data.total;
                    bubble.appendChild(newBadge);
                }
            } else {
                // If count is now 0, remove the badge
                if (badge) badge.remove();
                bubble.classList.remove("pulse");
            }
        });
}
let currentStudent = 0;

function openChat(studentId){

currentStudent = studentId;

document.getElementById("inboxList").style.display="none";
document.getElementById("chatBox").style.display="block";

loadChat();

}

function backToInbox(){

document.getElementById("chatBox").style.display="none";
document.getElementById("inboxList").style.display="block";

}

function loadChat(){

fetch("fetch_chat.php?student_id="+currentStudent)
.then(res=>res.text())
.then(data=>{
document.getElementById("chatMessages").innerHTML=data;
});

}

function sendReply(){

let msg = document.getElementById("replyText").value;

fetch("send_reply.php",{
method:"POST",
headers:{"Content-Type":"application/x-www-form-urlencoded"},
body:"student_id="+currentStudent+"&message="+encodeURIComponent(msg)
})
.then(()=>{
document.getElementById("replyText").value="";
loadChat();
});

}
</script>
<?php if($is_assigned_moderator): ?>
<!-- FLOATING BUBBLE -->
<div class="mod-bubble" onclick="toggleModPanel()">
    <i data-lucide="bell"></i>
    <?php if($notif_count + $msg_count > 0): ?>
        <span class="bubble-badge">
            <?php echo $notif_count + $msg_count; ?>
        </span>
    <?php endif; ?>
</div>

<!-- FLOATING PANEL -->
<div class="mod-panel" id="modPanel">
    <div class="mod-header">
        Moderator Panel
        <span style="cursor:pointer;" onclick="toggleModPanel()">✕</span>
    </div>

    <div class="mod-tabs">
        <button class="active" onclick="switchTab('notif')">Notifications</button>
        <button onclick="switchTab('msg')">Inbox</button>
    </div>

    <div class="mod-content" id="notifTab">
        Loading...
    </div>
    <div class="mod-content" id="msgTab" style="display:none;">

    <div id="inboxList">Loading...</div>

    <div id="chatBox" style="display:none;">

    <div style="margin-bottom:8px;">
    <button onclick="backToInbox()">← Back</button>
    </div>

    <div id="chatMessages" style="height:220px;overflow-y:auto;border:1px solid #333;padding:8px;border-radius:8px;"></div>

<div style="display:flex;margin-top:6px;">
<input id="replyText" placeholder="Type reply..." style="flex:1;padding:6px;">
<button onclick="sendReply()">Send</button>
</div>
</div>
<?php endif; ?>
<audio id="notifSound" src="notification.mp3" preload="auto"></audio>
</body>
</html>