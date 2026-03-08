<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clubconnect");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = strtolower($_SESSION['role'] ?? '');

// Prevent moderators from accessing this page
if ($user_role === 'moderator') {
    header("Location: home.php");
    exit();
}

// Handle join request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_club_id'])) {
    $club_id = (int)$_POST['join_club_id'];
    
    // Check if already a member
    $is_member = $conn->query("SELECT id FROM club_memberships WHERE user_id = $user_id AND club_id = $club_id")->num_rows > 0;
    
    // Check if already has a pending request
    $has_pending = $conn->query("SELECT id FROM membership_requests WHERE user_id = $user_id AND club_id = $club_id AND status = 'pending'")->num_rows > 0;
    
    if (!$is_member && !$has_pending) {
        // Get club info for notification
        $club_res = $conn->query("SELECT club_name FROM clubs WHERE id = $club_id");
        $club_data = $club_res->fetch_assoc();
        $club_name = $club_data ? $club_data['club_name'] : 'the club';
        
        // Create membership request
        $conn->query("INSERT INTO membership_requests (user_id, club_id, status) VALUES ($user_id, $club_id, 'pending')");
        
        // Notify moderator
        $u_info = $conn->query("SELECT fullname FROM users WHERE id = $user_id")->fetch_assoc();
        $sender = $conn->real_escape_string($u_info['fullname'] ?? 'A user');
        $msg = "$sender has requested to join $club_name";
        
        $mod_res = $conn->query("SELECT id FROM users WHERE LOWER(role) = 'moderator' AND managed_club_id = $club_id LIMIT 1");
        if ($mod_res && $mod_res->num_rows > 0) {
            $mod_id = $mod_res->fetch_assoc()['id'];
            $conn->query("INSERT INTO notifications (user_id, message, type) VALUES ($mod_id, '$msg', 'join_request')");
        }
        
        $_SESSION['join_success'] = "Join request sent successfully! Awaiting moderator approval.";
    } elseif ($is_member) {
        $_SESSION['join_error'] = "You are already a member of this club.";
    } elseif ($has_pending) {
        $_SESSION['join_error'] = "You already have a pending request for this club.";
    }
    
    header("Location: join_club.php");
    exit();
}

// Get all clubs
$clubs_result = $conn->query("SELECT id, club_name, description, logo_url FROM clubs ORDER BY club_name ASC");
$all_clubs = $clubs_result->fetch_all(MYSQLI_ASSOC);

// Get user's club information
$member_query = "SELECT club_id FROM club_memberships WHERE user_id = $user_id";
$member_result = $conn->query($member_query);
$my_clubs = [];
while ($row = $member_result->fetch_assoc()) {
    $my_clubs[] = $row['club_id'];
}

$pending_query = "SELECT club_id FROM membership_requests WHERE user_id = $user_id AND status = 'pending'";
$pending_result = $conn->query($pending_query);
$pending_clubs = [];
while ($row = $pending_result->fetch_assoc()) {
    $pending_clubs[] = $row['club_id'];
}

$fullname = $_SESSION['fullname'] ?? 'Student';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join Clubs - ClubConnect</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --bg-gradient: linear-gradient(-45deg, #0f2027, #203a43, #2c5364, #b31217, #e52d27);
            --card-bg: #000;
            --text-color: #fff;
            --topbar-bg: rgba(0,0,0,0.35);
            --input-bg: rgba(255,255,255,0.1);
            --accent: #b31217;
        }

        body.light-mode {
            --bg-gradient: linear-gradient(-45deg, #f0f2f5, #e0e0e0, #ffffff);
            --card-bg: #ffffff;
            --text-color: #333;
            --topbar-bg: rgba(255,255,255,0.8);
            --input-bg: rgba(0,0,0,0.05);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', sans-serif;
            transition: background 0.3s, color 0.3s, transform 0.2s;
        }

        body {
            background: var(--bg-gradient);
            background-size: 400% 400%;
            animation: gradientMove 12s ease infinite;
            color: var(--text-color);
            overflow-x: hidden;
            min-height: 100vh;
            padding-top: 100px;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        body#body { background-attachment: fixed; }

        .topbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 80px;
            background: var(--topbar-bg);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 100;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 1px;
        }

        .topbar-left:hover {
            opacity: 0.9;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .role-badge {
            background: rgba(255,255,255,0.1);
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .user-profile-img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.2);
            cursor: pointer;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .icon-btn {
            background: none;
            border: none;
            color: var(--text-color);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            transition: background 0.3s;
        }

        .icon-btn:hover {
            background: rgba(255,255,255,0.1);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background: var(--card-bg);
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.3);
            border-radius: 8px;
            z-index: 1000;
            border: 1px solid rgba(255,255,255,0.1);
            animation: slideDown 0.2s;
        }

        .dropdown-content.show {
            display: block;
        }

        .dropdown-content a,
        .dropdown-content div {
            color: var(--text-color);
            padding: 12px 20px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .dropdown-content a:hover,
        .dropdown-content div:hover {
            background: rgba(255,255,255,0.1);
        }

        .dropdown-content a:first-child {
            border-radius: 8px 8px 0 0;
        }

        .dropdown-content a:last-child {
            border-radius: 0 0 8px 8px;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .container {
            max-width: 1400px;
            margin: 40px auto;
            padding: 0 30px;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #b31217, #e52d27, #ff6347);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.8;
            margin-bottom: 30px;
        }

        .search-container {
            position: relative;
            max-width: 500px;
            margin: 0 auto 40px;
        }

        .search-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.6;
        }

        .search-bar {
            width: 100%;
            padding: 12px 15px 12px 45px;
            background: var(--input-bg);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 25px;
            color: var(--text-color);
            font-size: 1rem;
            outline: none;
            transition: all 0.3s;
        }

        .search-bar:focus {
            border-color: var(--accent);
            box-shadow: 0 0 20px rgba(179, 18, 23, 0.3);
        }

        .alert {
            margin-bottom: 30px;
            padding: 15px 20px;
            border-radius: 8px;
            font-weight: 500;
            animation: slideInDown 0.3s;
        }

        .alert-success {
            background: rgba(76, 175, 80, 0.2);
            border: 1px solid rgba(76, 175, 80, 0.5);
            color: #4CAF50;
        }

        .alert-error {
            background: rgba(244, 67, 54, 0.2);
            border: 1px solid rgba(244, 67, 54, 0.5);
            color: #F44336;
        }

        @keyframes slideInDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .clubs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            margin-top: 40px;
        }

        .club-card {
            background: var(--card-bg);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            height: 100%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .club-card:hover {
            transform: translateY(-8px);
            border-color: var(--accent);
            box-shadow: 0 8px 20px rgba(179, 18, 23, 0.3);
        }

        .club-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, rgba(179,18,23,0.3), rgba(229,45,39,0.3));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            overflow: hidden;
        }

        .club-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .club-content {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .club-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-color);
        }

        .club-description {
            font-size: 0.95rem;
            opacity: 0.8;
            margin-bottom: 15px;
            flex: 1;
        }

        .club-status {
            font-size: 0.85rem;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 20px;
            margin-bottom: 15px;
            text-align: center;
            display: inline-block;
        }

        .status-member {
            background: rgba(76, 175, 80, 0.2);
            color: #4CAF50;
            border: 1px solid rgba(76, 175, 80, 0.5);
        }

        .status-pending {
            background: rgba(255, 152, 0, 0.2);
            color: #FF9800;
            border: 1px solid rgba(255, 152, 0, 0.5);
        }

        .status-available {
            background: rgba(33, 150, 243, 0.2);
            color: #2196F3;
            border: 1px solid rgba(33, 150, 243, 0.5);
        }

        .club-buttons {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }

        .btn {
            flex: 1;
            padding: 10px 15px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-join {
            background: linear-gradient(135deg, var(--accent), #e52d27);
            color: white;
            border: none;
        }

        .btn-join:hover:not(:disabled) {
            transform: scale(1.05);
            box-shadow: 0 4px 15px rgba(179, 18, 23, 0.4);
        }

        .btn-join:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-view {
            background: rgba(255,255,255,0.1);
            color: var(--text-color);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .btn-view:hover {
            background: rgba(255,255,255,0.15);
            border-color: rgba(255,255,255,0.3);
        }

        .no-clubs {
            text-align: center;
            padding: 60px 20px;
            opacity: 0.7;
        }

        .no-clubs i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-clubs p {
            font-size: 1.2rem;
        }

        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
            justify-content: center;
        }

        .filter-btn {
            padding: 8px 16px;
            border: 2px solid rgba(255,255,255,0.2);
            background: transparent;
            color: var(--text-color);
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .filter-btn.active {
            border-color: var(--accent);
            background: rgba(179, 18, 23, 0.2);
        }

        .filter-btn:hover {
            border-color: var(--accent);
        }

        footer {
            text-align: center;
            padding: 30px;
            opacity: 0.6;
            margin-top: 60px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        @media (max-width: 768px) {
            .topbar {
                padding: 0 15px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .clubs-grid {
                grid-template-columns: 1fr;
            }

            .container {
                margin: 20px auto;
                padding: 0 15px;
            }
        }

        body.light-mode .club-card {
            background: #f5f5f5;
        }

        body.light-mode .status-member {
            background: rgba(76, 175, 80, 0.15);
        }

        body.light-mode .status-pending {
            background: rgba(255, 152, 0, 0.15);
        }

        body.light-mode .status-available {
            background: rgba(33, 150, 243, 0.15);
        }
    </style>
</head>
<body id="body">
    <!-- Top Navigation Bar -->
    <div class="topbar">
        <div class="topbar-left" onclick="location.href='home.php'">
            <i data-lucide="users" size="28"></i>
            <span>ClubConnect</span>
        </div>

        <div class="topbar-right">
            <div class="role-badge">
                <?php echo ucfirst($user_role); ?>
            </div>
            
            <img src="<?php echo $_SESSION['profile_pic'] ?? 'assetimages/default-user.png'; ?>" class="user-profile-img">

            <div class="dropdown">
                <button onclick="toggleDropdown()" class="icon-btn">
                    <i data-lucide="settings" size="18"></i>
                </button>
                <div id="settingsDropdown" class="dropdown-content">
                    <a href="edit_profile.php"><i data-lucide="user-cog" size="16"></i> Account Settings</a>
                    <a href="calendar.php"><i data-lucide="calendar" size="16"></i> Event Calendar</a>
                    <a href="home.php"><i data-lucide="home" size="16"></i> Home</a>
                    <div onclick="toggleDarkMode()"><i data-lucide="moon" size="16"></i> Toggle Theme</div>
                    <hr style="border: 0.5px solid rgba(128,128,128,0.2);">
                    <a href="logout.php" style="color: #ff4d4d;"><i data-lucide="log-out" size="16"></i> Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Discover & Join Clubs</h1>
            <p>Find and join the clubs that match your interests</p>
        </div>

        <!-- Success/Error Messages -->
        <?php if (!empty($_SESSION['join_success'])): ?>
            <div class="alert alert-success">
                <strong>✓</strong> <?php echo $_SESSION['join_success']; unset($_SESSION['join_success']); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['join_error'])): ?>
            <div class="alert alert-error">
                <strong>⚠</strong> <?php echo $_SESSION['join_error']; unset($_SESSION['join_error']); ?>
            </div>
        <?php endif; ?>

        <!-- Search Bar -->
        <div class="search-container">
            <i data-lucide="search" class="search-icon" size="20"></i>
            <input 
                type="text" 
                id="searchInput" 
                class="search-bar" 
                placeholder="Search clubs by name..." 
                onkeyup="filterClubs()"
            >
        </div>

        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <button class="filter-btn active" onclick="filterByStatus('all')">All Clubs</button>
            <button class="filter-btn" onclick="filterByStatus('available')">Join Now</button>
            <button class="filter-btn" onclick="filterByStatus('pending')">Pending</button>
            <button class="filter-btn" onclick="filterByStatus('member')">My Clubs</button>
        </div>

        <!-- Clubs Grid -->
        <div class="clubs-grid" id="clubsGrid">
            <?php if (empty($all_clubs)): ?>
                <div class="no-clubs" style="grid-column: 1 / -1;">
                    <i data-lucide="inbox" size="64"></i>
                    <p>No clubs available at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach ($all_clubs as $club): ?>
                    <?php
                    $club_id = $club['id'];
                    $is_member = in_array($club_id, $my_clubs);
                    $is_pending = in_array($club_id, $pending_clubs);
                    $status_class = '';
                    $status_icon = '';
                    $status_text = '';
                    $btn_text = '';
                    
                    if ($is_member) {
                        $status_class = 'status-member';
                        $status_icon = '✓';
                        $status_text = 'Member';
                        $btn_text = 'View Club';
                    } elseif ($is_pending) {
                        $status_class = 'status-pending';
                        $status_icon = '⏳';
                        $status_text = 'Pending Approval';
                        $btn_text = 'Pending';
                    } else {
                        $status_class = 'status-available';
                        $status_icon = '+';
                        $status_text = 'Available';
                        $btn_text = 'Join Club';
                    }
                    ?>
                    <div class="club-card" data-club-id="<?php echo $club_id; ?>" data-club-name="<?php echo htmlspecialchars($club['club_name']); ?>" data-status="<?php echo $is_member ? 'member' : ($is_pending ? 'pending' : 'available'); ?>">
                        <div class="club-image">
                            <?php if (!empty($club['logo_url']) && file_exists($club['logo_url'])): ?>
                                <img src="<?php echo htmlspecialchars($club['logo_url']); ?>" alt="<?php echo htmlspecialchars($club['club_name']); ?>">
                            <?php else: ?>
                                <i data-lucide="users" size="64"></i>
                            <?php endif; ?>
                        </div>

                        <div class="club-content">
                            <h3 class="club-name"><?php echo htmlspecialchars($club['club_name']); ?></h3>
                            <p class="club-description"><?php echo htmlspecialchars($club['description'] ?? 'A vibrant club community'); ?></p>
                            
                            <div class="club-status <?php echo $status_class; ?>">
                                <?php echo $status_icon; ?> <?php echo $status_text; ?>
                            </div>

                            <div class="club-buttons">
                                <form method="POST" style="flex: 1; display: <?php echo $is_member || $is_pending ? 'none' : 'flex'; ?>;">
                                    <input type="hidden" name="join_club_id" value="<?php echo $club_id; ?>">
                                    <button type="submit" class="btn btn-join">
                                        <i data-lucide="plus" size="16"></i>
                                        Join Club
                                    </button>
                                </form>

                                <button class="btn btn-view" onclick="location.href='club_home.php?id=<?php echo $club_id; ?>'">
                                    <i data-lucide="arrow-right" size="16"></i>
                                    View
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <footer>
        <p>&copy; 2026 ClubConnect. All rights reserved.</p>
    </footer>

    <script>
        lucide.createIcons();

        let currentFilter = 'all';

        function filterClubs() {
            const query = document.getElementById('searchInput').value.toLowerCase();
            const cards = document.querySelectorAll('.club-card');

            cards.forEach(card => {
                const clubName = card.getAttribute('data-club-name').toLowerCase();
                const status = card.getAttribute('data-status');
                
                const matchesSearch = clubName.includes(query);
                const matchesFilter = currentFilter === 'all' || status === currentFilter;
                
                card.style.display = (matchesSearch && matchesFilter) ? 'flex' : 'none';
            });

            updateNoClubsMessage();
        }

        function filterByStatus(status) {
            currentFilter = status;
            
            // Update active button
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            filterClubs();
        }

        function updateNoClubsMessage() {
            const cards = document.querySelectorAll('.club-card');
            const visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');

            if (visibleCards.length === 0) {
                const grid = document.getElementById('clubsGrid');
                if (!document.querySelector('.no-results-message')) {
                    const noMessage = document.createElement('div');
                    noMessage.className = 'no-clubs no-results-message';
                    noMessage.style.gridColumn = '1 / -1';
                    noMessage.innerHTML = '<i data-lucide="search" size="64"></i><p>No clubs match your search.</p>';
                    grid.appendChild(noMessage);
                    lucide.createIcons();
                }
            } else {
                const noMessage = document.querySelector('.no-results-message');
                if (noMessage) noMessage.remove();
            }
        }

        function toggleDropdown() {
            document.getElementById('settingsDropdown').classList.toggle('show');
        }

        window.onclick = function(event) {
            if (!event.target.closest('.dropdown')) {
                document.getElementById('settingsDropdown').classList.remove('show');
            }
        }

        function toggleDarkMode() {
            const body = document.getElementById('body');
            body.classList.toggle('light-mode');
            localStorage.setItem('theme', body.classList.contains('light-mode') ? 'light' : 'dark');
            lucide.createIcons();
        }

        window.onload = () => {
            if (localStorage.getItem('theme') === 'light') {
                document.getElementById('body').classList.add('light-mode');
            }
            lucide.createIcons();
        };
    </script>
</body>
</html>
