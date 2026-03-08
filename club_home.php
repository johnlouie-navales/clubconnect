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

// moderator floating bubble data
$notif_count = 0;
$msg_count = 0;

if($is_assigned_moderator){
	$notif_res = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = $user_id AND is_read = 0");
	if($notif_res) $notif_count = $notif_res->fetch_assoc()['total'];

	$msg_res = $conn->query("SELECT COUNT(*) as total FROM messages WHERE receiver_id = $user_id AND is_read = 0");
	if($msg_res) $msg_count = $msg_res->fetch_assoc()['total'];
}

// --- RSVP HANDLING LOGIC ---
if (isset($_POST['rsvp_action'])) {
	$post_id = (int)$_POST['post_id'];
	$status = $_POST['rsvp_status'];
	$check = $conn->query("SELECT id FROM event_responses WHERE user_id = $user_id AND post_id = $post_id");

	if ($check->num_rows > 0) {
		$conn->query("UPDATE event_responses SET status = '$status' WHERE user_id = $user_id AND post_id = $post_id");
	} else {
		$conn->query("INSERT INTO event_responses (user_id, post_id, status) VALUES ($user_id, $post_id, '$status')");
	}

	// if submitted via AJAX, return the new counts instantly!
	if(isset($_POST['ajax'])) {
		$counts = $conn->query("SELECT 
            COALESCE(SUM(CASE WHEN status = 'joining' THEN 1 ELSE 0 END), 0) as join_count,
            COALESCE(SUM(CASE WHEN status = 'not_joining' THEN 1 ELSE 0 END), 0) as skip_count
            FROM event_responses WHERE post_id = $post_id")->fetch_assoc();
		echo json_encode($counts);
		exit();
	}

	header("Location: club_home.php?id=$club_id"); exit();
}

function containsProfanity($text) {
	$badWords = [
			'puta','gago','tanga','bobo','pakshet','kantut','hayop',
			'fuck','shit','asshole','bitch','dick','pussy','bastard',
			'pendejo','mierda','verga', 'nigga', 'negro', 'dumbass', 'ass',
			'kupal', 'tangina' , 'pota' , 'powtah', 'gagu', 'iyot', 'pisot',
			'tite', 'kado'
	];
	$normalized = strtolower($text);
	$normalized = preg_replace('/[^a-z0-9]/i', '', $normalized);
	foreach ($badWords as $word) {
		$cleanWord = preg_replace('/[^a-z0-9]/i', '', strtolower($word));
		if (strpos($normalized, $cleanWord) !== false) return true;
	}
	return false;
}

if (isset($_POST['add_comment'])) {
	$post_id = (int)$_POST['post_id'];
	$raw_text = trim($_POST['comment_text']);

	if (empty($raw_text)) {
		$_SESSION['comment_error'] = "Comment cannot be empty.";
		header("Location: club_home.php?id=$club_id"); exit();
	}

	if (containsProfanity($raw_text)) {
		$u_info = $conn->query("SELECT fullname FROM users WHERE id = $user_id")->fetch_assoc();
		$sender = $conn->real_escape_string($u_info['fullname'] ?? 'A user');
		$cleanText = $conn->real_escape_string($raw_text);
		$msg = "Blocked comment from $sender: \"$cleanText\"";

		$mod_res = $conn->query("SELECT id FROM users WHERE LOWER(role) = 'moderator' AND managed_club_id = $club_id LIMIT 1");
		if ($mod_res && $mod_res->num_rows > 0) {
			$mod_id = $mod_res->fetch_assoc()['id'];
			$conn->query("INSERT INTO notifications (user_id, message, type) VALUES ($mod_id, '$msg', 'flagged_comment')");
		}

		// return an error flag if it's an AJAX request
		if(isset($_POST['ajax'])) { echo "profanity_error"; exit(); }

		$_SESSION['comment_error'] = "⚠ Your comment violates our community guidelines and was not posted.";
		header("Location: club_home.php?id=$club_id"); exit();
	}

	$safe_text = $conn->real_escape_string($raw_text);
	$conn->query("INSERT INTO post_comments (post_id, user_id, comment_text) VALUES ($post_id, $user_id, '$safe_text')");

	// if submitted via AJAX, echo the new DOM element and stop processing
	if(isset($_POST['ajax'])) {
		$u_info = $conn->query("SELECT fullname, profile_pic FROM users WHERE id = $user_id")->fetch_assoc();
		$avatar = !empty($u_info['profile_pic']) ? $u_info['profile_pic'] : 'default-avatar.png';
		$name = htmlspecialchars($u_info['fullname']);
		$time = date('M d, g:i A');
		$clean_comment = nl2br(htmlspecialchars($raw_text));

		echo "<div class='comment-item'>
                <img src='$avatar' class='comment-avatar'>
                <div class='comment-content'>
                    <div class='comment-header'>
                        <span class='comment-name'>$name</span>
                        <span class='comment-time'>$time</span>
                    </div>
                    <div>$clean_comment</div>
                </div>
              </div>";
		exit();
	}

	// fallback for non-AJAX
	header("Location: club_home.php?id=$club_id"); exit();
}

// --- MEMBERSHIP REQUEST HANDLING ---
if (isset($_POST['join_club'])) {
	$conn->query("INSERT INTO membership_requests (user_id, club_id, status) VALUES ($user_id, $club_id, 'pending')");
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
	$action = $_POST['manage_request'];

	$req_res = $conn->query("SELECT * FROM membership_requests WHERE id = $request_id");
	if (!$req_res) die("Error fetching request: " . $conn->error);
	$req_data = $req_res->fetch_assoc();
	if (!$req_data) die("Request not found");
	$applicant_id = $req_data['user_id'];

	if ($action === 'approve') {
		$conn->query("UPDATE membership_requests SET status = 'approved' WHERE id = $request_id");
		$conn->query("INSERT IGNORE INTO club_memberships (user_id, club_id) VALUES ($applicant_id, $club_id)");
		$notif_msg = "Your request to join " . $club['club_name'] . " has been approved! Welcome!";
	} else {
		$conn->query("UPDATE membership_requests SET status = 'rejected' WHERE id = $request_id");
		$notif_msg = "Your request to join " . $club['club_name'] . " was declined.";
	}
	$conn->query("INSERT INTO notifications (user_id, message, type) VALUES ($applicant_id, '$notif_msg', 'status')");
	header("Location: club_home.php?id=$club_id"); exit();
}

if (isset($_POST['remove_member'])) {
	$m_id = (int)$_POST['target_user_id'];
	$conn->query("DELETE FROM club_memberships WHERE user_id = $m_id AND club_id = $club_id");
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
        .comment-section { margin-top: 15px; border-top: 1px solid rgba(255,255,255,0.05); padding-top: 15px; }
        .comment-item { display: flex; gap: 10px; margin-bottom: 12px; }
        .comment-avatar { width: 35px; height: 35px; border-radius: 50%; object-fit: cover; }
        .comment-content { background: #0f172a; padding: 8px 12px; border-radius: 10px; flex: 1; }
        .comment-header { display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 4px; }
        .comment-name { font-weight: 600; color: white; }
        .comment-time { color: #94a3b8; font-size: 11px; }

        /* MODERATOR BUBBLE & PANEL */
        .mod-bubble { position: fixed; bottom: 25px; right: 25px; width: 65px; height: 65px; border-radius: 50%; background: var(--accent); display: flex; align-items: center; justify-content: center; cursor: pointer; box-shadow: 0 10px 25px rgba(0,0,0,0.4); z-index: 5000; transition: 0.3s; }
        .mod-bubble:hover { transform: scale(1.1); }
        .mod-bubble.pulse { animation: pulse 1.2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(239,68,68,0.6); } 70% { box-shadow: 0 0 0 15px rgba(239,68,68,0); } 100% { box-shadow: 0 0 0 0 rgba(239,68,68,0); } }

        .bubble-badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 11px; padding: 3px 6px; border-radius: 50%; }
        .mod-panel { position: fixed; bottom: 100px; right: 25px; width: 350px; max-height: 450px; background: var(--card); border-radius: 18px; box-shadow: 0 15px 40px rgba(0,0,0,0.6); display: none; flex-direction: column; overflow: hidden; z-index: 5001; animation: slideUp 0.3s ease; }
        @keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }

        .mod-header { display: flex; justify-content: space-between; padding: 15px; background: rgba(255,255,255,0.05); font-weight: bold; }
        .mod-tabs { display: flex; }
        .mod-tabs button { flex: 1; padding: 10px; background: none; border: none; color: #cbd5e1; cursor: pointer; }
        .mod-tabs button.active { background: rgba(179,18,23,0.2); color: white; }
        .mod-content { flex: 1; overflow-y: auto; padding: 12px; }
        .mod-item { padding: 15px; border-bottom: 1px solid rgba(255,255,255,0.08); display: flex; flex-direction: column; gap: 8px; }

        .student-msg { background:#1e293b; padding:6px; margin:5px 0; border-radius:6px; }
        .mod-msg { background:#b31217; padding:6px; margin:5px 0; border-radius:6px; text-align:right; }

        /* STUDENT NOTIFICATION STYLES */
        .student-badge { position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; font-size: 11px; padding: 3px 6px; border-radius: 50%; display: none; box-shadow: 0 0 8px #ef4444; }
        .toast-notification { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(100px); background: #2563eb; color: white; padding: 12px 24px; border-radius: 50px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: flex; align-items: center; gap: 10px; z-index: 6000; opacity: 0; transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55); cursor: pointer; }
        .toast-notification.show { transform: translateX(-50%) translateY(0); opacity: 1; }

        .comment-error-box {
            background: rgba(220,38,38,0.15);
            border: 1px solid #dc2626;
            color: #fecaca;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 13px;
            display: none;
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
			<div style="display: flex; gap: 10px;">
				<button onclick="openStudentChat()" class="drop-btn" style="background: #2563eb; position: relative;">
					<i data-lucide="message-circle" size="18"></i> Ask Moderator
					<span id="studentUnreadBadge" class="student-badge">0</span>
				</button>

				<?php if (!$is_member && !$has_pending): ?>
					<form method="POST" style="margin: 0;">
						<input type="hidden" name="club_id" value="<?php echo $club_id; ?>">
						<button type="submit" name="join_club" class="drop-btn">Join Club</button>
					</form>
				<?php elseif ($has_pending): ?>
					<button class="drop-btn" style="background:#eab308; cursor:default;">Request Pending</button>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</div>
</nav>

<div class="hero-banner">
	<h1 style="font-size: 3rem; margin: 0;"><?php echo htmlspecialchars($club['club_name']); ?></h1>
	<p style="color: #94a3b8; max-width: 500px;"><?php echo htmlspecialchars($club['description']); ?></p>
</div>

<div class="main-feed">
	<?php
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
					<form method="POST" onsubmit="submitRsvp(event, <?php echo $pid; ?>)" style="display:flex; gap:5px;">
						<input type="hidden" name="post_id" value="<?php echo $pid; ?>">
						<button type="submit" name="rsvp_status" value="joining" class="btn-rsvp btn-join">Join</button>
						<button type="submit" name="rsvp_status" value="not_joining" class="btn-rsvp btn-skip">Skip</button>
						<input type="hidden" name="rsvp_action" value="1">
					</form>

					<?php if(!empty($p['location_address'])): ?>
						<a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($p['location_address']); ?>" target="_blank" class="btn-rsvp btn-map">
							<i data-lucide="map" size="14"></i> Map
						</a>
					<?php endif; ?>
				</div>
			</div>

			<div class="comment-section">
				<?php
				$comments = $conn->query("SELECT pc.*, u.fullname, u.profile_pic FROM post_comments pc JOIN users u ON pc.user_id = u.id WHERE pc.post_id = $pid ORDER BY pc.created_at ASC");
				while($c = $comments->fetch_assoc()):
					?>
					<div class="comment-item">
						<img src="<?php echo !empty($c['profile_pic']) ? $c['profile_pic'] : 'default-avatar.png'; ?>" class="comment-avatar">
						<div class="comment-content">
							<div class="comment-header">
								<span class="comment-name"><?php echo htmlspecialchars($c['fullname']); ?></span>
								<span class="comment-time"><?php echo date('M d, g:i A', strtotime($c['created_at'])); ?></span>
							</div>
							<div><?php echo nl2br(htmlspecialchars($c['comment_text'])); ?></div>
						</div>
					</div>
				<?php endwhile; ?>

				<?php if(isset($_SESSION['comment_error'])): ?>
					<div style="background: rgba(220,38,38,0.15); border: 1px solid #dc2626; color: #fecaca; padding: 10px; border-radius: 8px; margin-bottom: 10px; font-size: 13px;">
						<?php echo $_SESSION['comment_error']; unset($_SESSION['comment_error']); ?>
					</div>
				<?php endif; ?>

				<div id="comment-error-<?php echo $pid; ?>" class="comment-error-box">
					⚠ Your comment violates our community guidelines and was not posted.
				</div>

				<form method="POST" class="comment-form" onsubmit="submitComment(event, <?php echo $pid; ?>)" style="display: flex; gap: 8px; margin-top: 15px; align-items: center;">
					<input type="hidden" name="post_id" value="<?php echo $pid; ?>">
					<input type="text" name="comment_text" placeholder="Write a comment..." required style="flex: 1; margin: 0; background: #0f172a; border: 1px solid #334155; color: white; padding: 10px; border-radius: 8px;">
					<button type="submit" name="add_comment" style="background: var(--accent); color: white; border: none; width: 42px; height: 42px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.2s;">
						<i data-lucide="send" size="18"></i>
					</button>
				</form>
			</div>
		</div>
	<?php endwhile; ?>
</div>

<div id="modal-student-chat" class="modal-overlay">
	<div class="modal-content" style="display: flex; flex-direction: column; height: 500px; width: 400px; padding: 20px;">
		<button onclick="closeStudentChat()" style="position:absolute; top:15px; right:20px; background:none; border:none; color:white; cursor:pointer;"><i data-lucide="x"></i></button>
		<h3 style="margin-top: 0; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">
			<i data-lucide="message-square" size="18"></i> Chat with Moderator
		</h3>
		<div id="studentTypingIndicator" style="display: none; color: #94a3b8; font-size: 11px; margin-bottom: 8px; font-style: italic;">Moderator is typing...</div>
		<div id="studentChatHistory" style="flex: 1; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; padding: 10px 0; margin-bottom: 15px;"></div>
		<div style="display: flex; gap: 8px;">
			<input type="text" id="studentChatInput" placeholder="Type a message..." style="flex: 1; margin: 0; background: #0f172a; border: 1px solid #334155; color: white; padding: 10px; border-radius: 8px;" onkeypress="if(event.key === 'Enter') sendStudentMsg()">
			<button onclick="sendStudentMsg()" style="background: #2563eb; color: white; border: none; width: 42px; height: 42px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center;"><i data-lucide="send" size="18"></i></button>
		</div>
	</div>
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
			$members = $conn->query("SELECT u.id, u.fullname, u.profile_pic, cm.joined_at FROM club_memberships cm JOIN users u ON cm.user_id = u.id WHERE cm.club_id = $club_id ORDER BY u.fullname ASC");
			if ($members->num_rows > 0):
				while($m = $members->fetch_assoc()): ?>
					<div style="display: flex; align-items: center; gap: 12px; padding: 12px; border-bottom: 1px solid rgba(255,255,255,0.05);">
						<img src="<?php echo !empty($m['profile_pic']) ? $m['profile_pic'] : 'default-avatar.png'; ?>" style="width: 38px; height: 38px; border-radius: 50%; object-fit: cover; border: 2px solid var(--accent);">
						<div style="flex: 1;">
							<div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($m['fullname']); ?></div>
							<div style="font-size: 11px; color: #64748b;">Joined <?php echo date('M d, Y', strtotime($m['joined_at'])); ?></div>
						</div>
						<form method="POST" onsubmit="return confirm('Are you sure you want to remove this member?');" style="margin:0;">
							<input type="hidden" name="target_user_id" value="<?php echo $m['id']; ?>">
							<button type="submit" name="remove_member" style="background:none; border:none; color:#f87171; cursor:pointer; padding:5px;"><i data-lucide="user-minus" size="18"></i></button>
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

<div id="studentToast" class="toast-notification" onclick="openStudentChat()">
	<i data-lucide="bell" size="18"></i>
	<span>New message from Moderator!</span>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        lucide.createIcons();
    });
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

    // --- MODERATOR LOGIC ---
    let lastCount = 0;
    let modChatTimer = null;
    let currentStudent = 0;
    let typingTimeout = null;

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

    function loadModeratorData(){
        fetch("moderator_fetch.php?type=count")
            .then(res=>res.json())
            .then(data=>{
                if(data.total > lastCount){
                    document.querySelector(".mod-bubble").classList.add("pulse");
                    const snd = document.getElementById("notifSound");
                    if(snd) { let p = snd.play(); if(p!==undefined) p.catch(e=>{}); }
                }
                lastCount = data.total;
            });

        fetch("moderator_fetch.php?type=notif").then(res=>res.text()).then(data=> { document.getElementById("notifTab").innerHTML = data; });
        fetch("moderator_fetch.php?type=msg").then(res=>res.text()).then(data=> {
            const inboxList = document.getElementById("inboxList");
            if (inboxList) inboxList.innerHTML = data;
        });
    }
    setInterval(loadModeratorData, 4000);

    function markNotif(id) {
        const item = event.target.closest('.mod-item');
        if(item) item.style.opacity = '0.5';
        fetch("moderator_fetch.php?type=mark_notif&id=" + id).then(() => { updateBadgeCount(); loadModeratorData(); });
    }

    function updateBadgeCount() {
        fetch("moderator_fetch.php?type=count").then(res => res.json()).then(data => {
            const badge = document.querySelector(".bubble-badge");
            const bubble = document.querySelector(".mod-bubble");
            if (data.total > 0) {
                if (badge) badge.innerText = data.total;
                else {
                    const newBadge = document.createElement('span');
                    newBadge.className = 'bubble-badge';
                    newBadge.innerText = data.total;
                    bubble.appendChild(newBadge);
                }
            } else {
                if (badge) badge.remove();
                bubble.classList.remove("pulse");
            }
        });
    }

    function openChat(studentId){
        currentStudent = studentId;
        document.getElementById("inboxList").style.display = "none";
        document.getElementById("chatBox").style.display = "flex";
        loadChat();
        clearInterval(modChatTimer);
        modChatTimer = setInterval(loadChat, 3000);
    }

    function backToInbox(){
        document.getElementById("chatBox").style.display = "none";
        document.getElementById("inboxList").style.display = "block";
        clearInterval(modChatTimer);
        loadModeratorData();
    }

    function loadChat(){
        fetch("fetch_chat.php?student_id="+currentStudent).then(res=>res.text()).then(data=>{
            const chatBox = document.getElementById("chatMessages");
            const isScrolledToBottom = chatBox.scrollHeight - chatBox.clientHeight <= chatBox.scrollTop + 50;
            chatBox.innerHTML = data;
            if (isScrolledToBottom) chatBox.scrollTop = chatBox.scrollHeight;
        });
    }

    function sendReply(){
        let input = document.getElementById("replyText");
        let msg = input.value.trim();
        if (!msg) return;
        fetch("send_reply.php",{
            method:"POST", headers:{"Content-Type":"application/x-www-form-urlencoded"},
            body:"student_id="+currentStudent+"&message="+encodeURIComponent(msg)
        }).then(()=>{ input.value = ""; loadChat(); });
    }

    function emitModeratorTyping() {
        if (!currentStudent) return;
        const formData = new URLSearchParams();
        formData.append('receiver_id', currentStudent);
        formData.append('is_typing', 1);
        fetch(`message_handler.php?action=set_typing`, { method: 'POST', body: formData });

        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            const stopData = new URLSearchParams();
            stopData.append('receiver_id', currentStudent);
            stopData.append('is_typing', 0);
            fetch(`message_handler.php?action=set_typing`, { method: 'POST', body: stopData });
        }, 2000);
    }

    // --- STUDENT LOGIC ---
    let studentChatTimer = null;
    let checkTypingTimer = null;
    let lastStudentUnread = 0;

    function openStudentChat() {
        document.getElementById('modal-student-chat').style.display = 'flex';
        const badge = document.getElementById('studentUnreadBadge');
        if (badge) badge.style.display = 'none';
        lastStudentUnread = 0;

        fetchStudentChat();
        clearInterval(studentChatTimer);
        studentChatTimer = setInterval(fetchStudentChat, 3000);

        clearInterval(checkTypingTimer);
        checkTypingTimer = setInterval(() => {
            fetch(`message_handler.php?action=check_typing&club_id=<?php echo $club_id; ?>&t=${Date.now()}`)
                .then(res => res.json())
                .then(data => {
                    const indicator = document.getElementById('studentTypingIndicator');
                    if (indicator) indicator.style.display = data.typing ? 'block' : 'none';
                }).catch(e => {});
        }, 1500);
    }

    function closeStudentChat() {
        document.getElementById('modal-student-chat').style.display = 'none';
        clearInterval(studentChatTimer);
        clearInterval(checkTypingTimer);
        const indicator = document.getElementById('studentTypingIndicator');
        if (indicator) indicator.style.display = 'none';
    }

    function fetchStudentChat() {
        fetch(`message_handler.php?action=fetch&club_id=<?php echo $club_id; ?>`).then(res => res.text()).then(html => {
            const chatBox = document.getElementById('studentChatHistory');
            const isScrolledToBottom = chatBox.scrollHeight - chatBox.clientHeight <= chatBox.scrollTop + 50;
            chatBox.innerHTML = html;
            if (isScrolledToBottom) chatBox.scrollTop = chatBox.scrollHeight;
        });
    }

    function sendStudentMsg() {
        const input = document.getElementById('studentChatInput');
        const msg = input.value.trim();
        if (!msg) return;
        const formData = new URLSearchParams();
        formData.append('club_id', <?php echo $club_id; ?>);
        formData.append('message', msg);
        fetch('message_handler.php?action=send', {
            method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        }).then(() => { input.value = ''; fetchStudentChat(); });
    }

    function checkStudentUnread() {
        if (document.getElementById('modal-student-chat').style.display === 'flex') return;
        fetch(`message_handler.php?action=check_unread&club_id=<?php echo $club_id; ?>&t=${Date.now()}`)
            .then(res => res.text())
            .then(text => {
                try {
                    const data = JSON.parse(text.trim());
                    const badge = document.getElementById('studentUnreadBadge');
                    if (data.unread > 0) {
                        badge.innerText = data.unread;
                        badge.style.display = 'block';
                        if (data.unread > lastStudentUnread) {
                            showStudentToast();
                            const snd = document.getElementById("notifSound");
                            if (snd) { let p = snd.play(); if (p !== undefined) p.catch(e => {}); }
                        }
                    } else {
                        badge.style.display = 'none';
                    }
                    lastStudentUnread = data.unread;
                } catch (e) {}
            });
    }

    function showStudentToast() {
        const toast = document.getElementById('studentToast');
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 4000);
    }

	<?php if($user_role === 'student'): ?>
    setInterval(checkStudentUnread, 4000);
	<?php endif; ?>

    // --- AJAX COMMENT SUBMISSION ---
    function submitComment(e, postId) {
        e.preventDefault(); // stop the page from refreshing!

        const form = e.target;
        const input = form.querySelector('input[name="comment_text"]');
        const text = input.value.trim();
        if(!text) return;

        // build the data payload
        const formData = new URLSearchParams();
        formData.append('add_comment', '1');
        formData.append('post_id', postId);
        formData.append('comment_text', text);
        formData.append('ajax', '1'); // PHP handles profanity blocking and returns "profanity_error"

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
            .then(res => res.text())
            .then(html => {
                // find the placeholder error box ADJACENT to THIS specific form
                const errorBox = document.getElementById(`comment-error-${postId}`);

                if(html === "profanity_error") {
                    if (errorBox) errorBox.style.display = 'block';
                } else {
                    // success path
                    if (errorBox) errorBox.style.display = 'none'; // strictly clear any old error message
                    form.insertAdjacentHTML('beforebegin', html); // snap the new comment element into place
                    input.value = ''; // clear input for next use
                }
            });
    }

    // --- AJAX RSVP SUBMISSION ---
    function submitRsvp(e, postId) {
        e.preventDefault(); // stop the refresh!

        // e.submitter gets the exact button the user clicked (Join or Skip)
        const btn = e.submitter;
        if (!btn) return;

        const formData = new URLSearchParams();
        formData.append('rsvp_action', '1');
        formData.append('post_id', postId);
        formData.append('rsvp_status', btn.value);
        formData.append('ajax', '1');

        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
            .then(res => res.json())
            .then(data => {
                // find the analytics div for THIS specific post and update the numbers instantly
                const analyticsDiv = e.target.closest('.post-footer').querySelector('.analytics');
                analyticsDiv.innerHTML = `<span><b>${data.join_count}</b> Joining</span><span><b>${data.skip_count}</b> Skipping</span>`;
            });
    }

    // --- ESCAPE KEY ACCESSIBILITY ---
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            // close standard modals (Create Post, Member List)
            closeModals();

            // close student chat (if it exists and is open)
            if (typeof closeStudentChat === 'function') {
                closeStudentChat();
            }

            // take moderator back to inbox (if they are currently in a chat)
            if (typeof backToInbox === 'function' && document.getElementById('chatBox')?.style.display === 'flex') {
                backToInbox();
            }

            // close the top navigation dropdown
            const dropMenu = document.getElementById('dropMenu');
            if (dropMenu) dropMenu.style.display = 'none';
        }
    });
</script>

<?php if($is_assigned_moderator): ?>
	<div class="mod-bubble" onclick="toggleModPanel()">
		<i data-lucide="bell"></i>
		<?php if($notif_count + $msg_count > 0): ?>
			<span class="bubble-badge"><?php echo $notif_count + $msg_count; ?></span>
		<?php endif; ?>
	</div>

	<div class="mod-panel" id="modPanel">
		<div class="mod-header">
			Moderator Panel
			<span style="cursor:pointer;" onclick="toggleModPanel()">✕</span>
		</div>

		<div class="mod-tabs">
			<button class="active" onclick="switchTab('notif')">Notifications</button>
			<button onclick="switchTab('msg')">Inbox</button>
		</div>

		<div class="mod-content" id="notifTab">Loading...</div>
		<div class="mod-content" id="msgTab" style="display:none; padding: 0;">
			<div id="inboxList" style="padding: 12px;">Loading...</div>
			<div id="chatBox" style="display:none; flex-direction: column; height: 350px; padding: 12px;">
				<div style="margin-bottom:10px;">
					<button onclick="backToInbox()" style="background: none; border: none; color: #cbd5e1; cursor: pointer; display: flex; align-items: center; gap: 5px; font-weight: 600;">
						<i data-lucide="arrow-left" size="16"></i> Back to Inbox
					</button>
				</div>
				<div id="chatMessages" style="flex: 1; overflow-y:auto; padding:10px; border-radius:8px; background: rgba(0,0,0,0.2); display: flex; flex-direction: column; gap: 8px;"></div>
				<div style="display:flex; gap: 8px; margin-top:10px;">
					<input id="replyText" oninput="emitModeratorTyping()" placeholder="Type a reply..." style="flex:1; margin: 0; background: #0f172a; border: 1px solid #334155; color: white; padding: 10px; border-radius: 8px;" onkeypress="if(event.key === 'Enter') sendReply()">
					<button onclick="sendReply()" style="background: var(--accent); color: white; border: none; width: 42px; height: 42px; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center;">
						<i data-lucide="send" size="18"></i>
					</button>
				</div>
			</div>
		</div>
	</div>
<?php endif; ?>
<audio id="notifSound" src="notification.mp3" preload="auto"></audio>
</body>
</html>