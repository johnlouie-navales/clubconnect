<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clubconnect");
// Fetch the latest global announcement
$ann_res = $conn->query("SELECT * FROM announcements 
                         WHERE created_at >= NOW() - INTERVAL 1 DAY 
                         ORDER BY created_at DESC LIMIT 1");

$latest_announcement = ($ann_res && $ann_res->num_rows > 0) ? $ann_res->fetch_assoc() : null;

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = strtolower($_SESSION['role']);
$fullname = $_SESSION['fullname'];

/* ===============================
    GET REAL COUNTS (UNREAD ONLY)
================================ */

// Count only UNREAD notifications
$notif_count = 0;
$notif_result = $conn->query("SELECT COUNT(*) as total FROM notifications WHERE user_id = '$user_id' AND is_read = 0");
if($notif_result){
    $notif_count = $notif_result->fetch_assoc()['total'];
}

// Count only UNREAD messages
$msg_count = 0;
$msg_result = $conn->query("SELECT COUNT(*) as total FROM messages WHERE receiver_id = '$user_id' AND is_read = 0");
if($msg_result){
    $msg_count = $msg_result->fetch_assoc()['total'];
}

// Get latest notifications for initial load
$notifications = $conn->query("SELECT message, created_at, is_read FROM notifications WHERE user_id = '$user_id' ORDER BY created_at DESC LIMIT 5");

// FIXED QUERY: Join users table to get sender_name for the messages
$messages = $conn->query("
    SELECT u.fullname AS sender_name, m.message_text AS message, m.created_at, m.is_read 
    FROM messages m 
    JOIN users u ON m.sender_id = u.id 
    WHERE m.receiver_id = '$user_id' 
    ORDER BY m.created_at DESC 
    LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ClubConnect - Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --bg-gradient: linear-gradient(-45deg, #0f02ff, #1b4ba5, #9b2f9f, #b31217, #e52d27);
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

        *{ margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI', sans-serif; transition: background 0.3s, color 0.3s; }

        body{
            background: var(--bg-gradient);
            background-size:400% 400%;
            animation: gradientMove 12s ease infinite;
            color: var(--text-color);
            overflow-x:hidden;
            padding-top:100px;
            min-height: 100vh;
        }

        @keyframes gradientMove{
            0%{background-position:0% 50%;}
            50%{background-position:100% 50%;}
            100%{background-position:0% 50%;}
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 30px;
            background: var(--topbar-bg);
            backdrop-filter: blur(12px);
            position: fixed;
            top: 0;
            width: 100%;
            z-index: 1000;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .topbar-right { display: flex; align-items: center; gap: 20px; }

        .user-profile-img {
            width: 40px; height: 40px; border-radius: 50%;
            object-fit: cover; border: 2px solid var(--accent);
            box-shadow: 0 0 10px rgba(179, 18, 23, 0.3);
        }

        .mod-indicators { display: flex; gap: 15px; align-items: center; }
        .icon-wrapper { position: relative; cursor: pointer; color: var(--text-color); opacity: 0.8; transition: 0.3s; }
        .icon-wrapper:hover { opacity: 1; transform: translateY(-2px); }
        .badge {
            position: absolute; top: -5px; right: -8px;
            background: var(--accent); color: white;
            border-radius: 50%; padding: 2px 6px; font-size: 10px; font-weight: bold;
            display: inline-block;
        }

        .search-container { position: relative; margin: 20px auto; max-width: 500px; width: 90%; }
        .search-bar {
            width: 100%; padding: 12px 20px 12px 45px; border-radius: 30px;
            border: 1px solid rgba(255,255,255,0.2); background: var(--input-bg);
            color: var(--text-color); outline: none; backdrop-filter: blur(5px);
        }
        .search-icon { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); opacity: 0.6; }

        .dropdown { position: relative; }
        .dropdown-content {
            display: none; position: absolute; right: 0; top: 45px;
            background: var(--card-bg); min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2); border-radius: 12px;
            overflow: hidden; z-index: 1001;
        }
        .dropdown-content a, .dropdown-content div {
            color: var(--text-color); padding: 12px 16px; text-decoration: none;
            display: flex; align-items: center; gap: 10px; cursor: pointer; font-size: 14px;
        }
        .dropdown-content a:hover, .dropdown-content div:hover { background: rgba(179, 18, 23, 0.1); }
        .show { display: block; }

        .carousel-container { position:relative; padding:20px 0; max-width: 1400px; margin: 0 auto; }
        .carousel { display:flex; gap:25px; overflow-x:hidden; scroll-behavior:smooth; padding:40px 100px; }
        .card {
            width:280px; height:380px; border-radius:25px; overflow:hidden;
            position:relative; flex-shrink:0; transition:0.4s ease; background: #222; cursor: pointer;
        }
        .card.hidden { display: none; }
        .card img { width:100%; height:100%; object-fit:cover; transition: 0.5s; }
        .card:hover img { transform: scale(1.1); }
        .card.active { transform: scale(1.08); border: 4px solid var(--accent); box-shadow: 0 15px 35px rgba(179, 18, 23, 0.3); }
        .card .overlay { position:absolute; bottom:0; width:100%; padding:20px; background:linear-gradient(to top, rgba(0,0,0,0.9), transparent); color: white; }

        .scroll-btn {
            position:absolute; top:50%; transform:translateY(-50%);
            width:55px; height:55px; border-radius:50%; border:none;
            cursor:pointer; z-index:100; background: var(--accent); color:white;
            display: flex; align-items: center; justify-content: center; font-size: 20px;
        }
        .left-btn { left: 20px; }
        .right-btn { right: 20px; }

        .icon-btn {
            background: var(--accent); color: white; padding: 8px 15px;
            border: none; border-radius: 10px; font-size: 14px;
            display: flex; align-items: center; gap: 5px; cursor: pointer;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0; top: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            justify-content: center;
            align-items: center;
        }

        .modal-content {
            background: var(--card-bg);
            width: 400px;
            max-height: 500px;
            overflow-y: auto;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
            border: 1px solid rgba(255,255,255,0.1);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-weight: bold;
        }

        .close-btn { cursor: pointer; font-size: 18px; color: var(--accent); }

        .modal-item {
            padding: 12px;
            border-bottom: 1px solid rgba(128,128,128,0.2);
            font-size: 14px;
        }

        .modal-item small { opacity: 0.6; display: block; margin-top: 5px; }

        .pulse { animation: pulseAnim 0.6s ease; }
        @keyframes pulseAnim {
            0%{transform:scale(1);}
            50%{transform:scale(1.3);}
            100%{transform:scale(1);}
        }

        .unread-item {
            border-left: 4px solid var(--accent);
            background: rgba(179,18,23,0.08);
        }
        .announcement-banner {
    max-width: 1200px;
    margin: -20px auto 30px auto; /* Pulls it up slightly toward the topbar */
    background: linear-gradient(135deg, #b31217, #e52d27);
    color: white;
    padding: 20px 30px;
    border-radius: 16px;
    box-shadow: 0 10px 30px rgba(179, 18, 23, 0.4);
    display: flex;
    align-items: center;
    gap: 20px;
    animation: slideDown 0.5s ease-out;
}

@keyframes slideDown {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.announcement-content h4 {
    font-size: 1.1rem;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.announcement-content p {
    font-size: 0.95rem;
    opacity: 0.9;
    line-height: 1.4;
}
    </style>
</head>
<body id="body">

<div class="topbar">
    <div class="logo" style="display:flex; align-items:center; gap:10px; font-weight:600;">
        <img src="/clubconnect/assetimages/cc.png" alt="Logo" style="height:35px;">
        <span>ClubConnect</span>
    </div>

    <div class="topbar-right">
        <?php if($user_role === 'moderator'): ?>
            <div class="mod-indicators">
                <div class="icon-wrapper" title="Notifications" onclick="openModal('notifModal')">
                    <i data-lucide="bell" size="22"></i>
                    <?php if($notif_count > 0): ?>
                        <span class="badge" id="notifBadge"><?php echo $notif_count; ?></span>
                    <?php else: ?>
                        <span class="badge" id="notifBadge" style="display:none;">0</span>
                    <?php endif; ?>
                </div>

                <div class="icon-wrapper" title="Messages" onclick="openModal('msgModal')">
                    <i data-lucide="mail" size="22"></i>
                    <?php if($msg_count > 0): ?>
                        <span class="badge" id="msgBadge"><?php echo $msg_count; ?></span>
                    <?php else: ?>
                        <span class="badge" id="msgBadge" style="display:none;">0</span>
                    <?php endif; ?>
                </div>
            </div>
            <div style="width:1px;height:25px;background:rgba(255,255,255,0.2);"></div>
        <?php endif; ?>

        <div class="role-badge" style="background:rgba(255,255,255,0.1); padding: 5px 12px; border-radius: 15px; font-size: 11px; text-transform: uppercase; font-weight: bold; letter-spacing: 0.5px;">
            <?php echo ucfirst($user_role); ?>
        </div>
        
        <img src="<?php echo $_SESSION['profile_pic'] ?? 'assetimages/default-user.png'; ?>" class="user-profile-img">

        <div class="dropdown">
            <button onclick="toggleDropdown()" class="icon-btn">
                <i data-lucide="settings" size="18"></i>
            </button>
            <div id="settingsDropdown" class="dropdown-content">
    <?php if ($user_role === 'admin'): ?>
        <a href="admin_dashboard.php" style="background: rgba(179, 18, 23, 0.1); font-weight: bold; color: var(--accent);">
            <i data-lucide="layout-dashboard" size="16"></i> Admin Dashboard
        </a>
        <hr style="border: 0.5px solid rgba(128,128,128,0.2);">
    <?php endif; ?>

    <a href="edit_profile.php"><i data-lucide="user-cog" size="16"></i> Account Settings</a>
    <a href="calendar.php"><i data-lucide="calendar" size="16"></i> Event Calendar</a>
    <div onclick="toggleDarkMode()"><i data-lucide="moon" size="16"></i> Toggle Theme</div>
    <hr style="border: 0.5px solid rgba(128,128,128,0.2);">
    <a href="logout.php" style="color: #ff4d4d;"><i data-lucide="log-out" size="16"></i> Logout</a>
</div>
            </div>
        </div>
    </div>
</div>
<?php if ($latest_announcement): ?>
    <div class="announcement-banner">
        <div style="background: rgba(255,255,255,0.2); padding: 12px; border-radius: 12px;">
            <i data-lucide="megaphone" size="28"></i>
        </div>
        <div class="announcement-content">
            <h4>Admin Update: <?php echo htmlspecialchars($latest_announcement['title']); ?></h4>
            <p><?php echo nl2br(htmlspecialchars($latest_announcement['message'])); ?></p>
            <small style="font-size: 10px; opacity: 0.7; display: block; margin-top: 8px;">
                Posted on <?php echo date('F j, Y - g:i A', strtotime($latest_announcement['created_at'])); ?>
            </small>
        </div>
    </div>
<?php endif; ?>

<div class="hero">
    <h1 style="text-align:center; font-size: 2.8rem; margin-top: 20px; font-weight: 800;">Explore School Clubs</h1>
    <div class="search-container">
        <i data-lucide="search" class="search-icon" size="20"></i>
        <input type="text" id="searchInput" class="search-bar" placeholder="Search clubs by name..." onkeyup="filterClubs()">
    </div>
</div>

<div class="carousel-container">
    <button class="scroll-btn left-btn" onclick="scrollCarousel(-1)">❮</button>
    <div class="carousel" id="clubCarousel">
        <div class="card active" data-name="ADT Dancing" onclick="location.href='club_home.php?id=1'">
            <img src="/clubconnect/assetimages/ADT.jpg" alt="ADT">
            <div class="overlay"><h3>ADT</h3><p>Art of dancing</p></div>
        </div>
        <div class="card" data-name="SCO Leaders" onclick="location.href='club_home.php?id=2'">
            <img src="/clubconnect/assetimages/SCO.jpg" alt="SCO">
            <div class="overlay"><h3>SCO</h3><p>Future leaders</p></div>
        </div>
        <div class="card" data-name="Rover Scouts" onclick="location.href='club_home.php?id=3'">
            <img src="/clubconnect/assetimages/RoverLogo.png" alt="Rovers">
            <div class="overlay"><h3>ACLC Rover Circle 16</h3><p>Building Character</p></div>
        </div>
        <div class="card" data-name="SAMAFIL Culture" onclick="location.href='club_home.php?id=4'">
            <img src="/clubconnect/assetimages/SAMAFIL.jpg" alt="SAMAFIL">
            <div class="overlay"><h3>SAMAFIL</h3><p>Cultural Heritage</p></div>
        </div>
        <div class="card" data-name="Red Cross Volunteer" onclick="location.href='club_home.php?id=5'">
            <img src="/clubconnect/assetimages/ACLCRC.jpg" alt="Red Cross">
            <div class="overlay"><h3>Red Cross Volunteers</h3><p>Saving lives</p></div>
        </div>
        <div class="card" data-name="Hawks Sports" onclick="location.href='club_home.php?id=6'">
            <img src="/clubconnect/assetimages/ACLCHawks.jpg" alt="Hawks">
            <div class="overlay"><h3>ACLC Hawks</h3><p>Athletic enthusiasts</p></div>
        </div>
    </div>
    <button class="scroll-btn right-btn" onclick="scrollCarousel(1)">❯</button>
</div>

<div id="notifModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Notifications</span>
            <span class="close-btn" onclick="closeModal('notifModal')">✖</span>
        </div>
        <div id="notifContainer">
            <?php if($notifications && $notifications->num_rows > 0): ?>
                <?php while($row = $notifications->fetch_assoc()): ?>
                    <div class="modal-item <?php echo $row['is_read']==0 ? 'unread-item' : ''; ?>">
                        <?php echo htmlspecialchars($row['message']); ?>
                        <small><?php echo $row['created_at']; ?></small>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="modal-item">No notifications yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div id="msgModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Inbox</span>
            <span class="close-btn" onclick="closeModal('msgModal')">✖</span>
        </div>
        <div id="msgContainer">
            <?php if($messages && $messages->num_rows > 0): ?>
                <?php while($row = $messages->fetch_assoc()): ?>
                    <div class="modal-item <?php echo $row['is_read']==0 ? 'unread-item' : ''; ?>">
                        <strong><?php echo htmlspecialchars($row['sender_name']); ?></strong><br>
                        <?php echo htmlspecialchars($row['message']); ?>
                        <small><?php echo $row['created_at']; ?></small>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="modal-item">No messages yet.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    lucide.createIcons();

    // Carousel Logic
    const carousel = document.getElementById("clubCarousel");
    let cards = document.querySelectorAll(".card");
    let currentIndex = 0;

    function scrollCarousel(direction) {
        const visibleCards = Array.from(cards).filter(c => !c.classList.contains('hidden'));
        if (visibleCards.length === 0) return;
        currentIndex += direction;
        if (currentIndex < 0) currentIndex = 0;
        if (currentIndex >= visibleCards.length) currentIndex = visibleCards.length - 1;
        const cardWidth = visibleCards[0].offsetWidth + 25;
        carousel.scrollTo({ left: cardWidth * currentIndex, behavior: "smooth" });
        updateActiveCard(visibleCards);
    }

    function updateActiveCard(visibleList) {
        cards.forEach(c => c.classList.remove("active"));
        if (visibleList[currentIndex]) visibleList[currentIndex].classList.add("active");
    }

    function filterClubs() {
        const query = document.getElementById("searchInput").value.toLowerCase();
        cards.forEach(card => {
            const name = card.getAttribute("data-name").toLowerCase();
            card.classList.toggle("hidden", !name.includes(query));
        });
        currentIndex = 0;
        carousel.scrollTo({ left: 0 });
        const visibleCards = Array.from(cards).filter(c => !c.classList.contains('hidden'));
        updateActiveCard(visibleCards);
    }

    // Dropdown & Theme Logic
    function toggleDropdown() { document.getElementById("settingsDropdown").classList.toggle("show"); }

    window.onclick = function(event) {
        if (!event.target.closest('.dropdown')) {
            const dropdown = document.getElementById("settingsDropdown");
            if(dropdown) dropdown.classList.remove("show");
        }
        const modals = document.querySelectorAll(".modal");
        modals.forEach(modal => {
            if(event.target === modal) modal.style.display = "none";
        });
    }

    function toggleDarkMode() {
        const body = document.getElementById("body");
        body.classList.toggle("light-mode");
        localStorage.setItem("theme", body.classList.contains("light-mode") ? "light" : "dark");
    }

    window.onload = () => {
        if (localStorage.getItem("theme") === "light") document.getElementById("body").classList.add("light-mode");
        fetchNotifications();
    };

    // Modal & Real-time Logic
    let lastNotifCount = <?php echo $notif_count; ?>;
    let lastMsgCount = <?php echo $msg_count; ?>;

    function openModal(id){
        document.getElementById(id).style.display = "flex";
        if(id === "notifModal" || id === "msgModal") {
            // Tell server to mark all as read
            fetch("mark_read.php?type=" + (id === "notifModal" ? "notif" : "msg"))
            .then(() => {
                setTimeout(fetchNotifications, 500);
            });
        }
    }

    function closeModal(id){
        document.getElementById(id).style.display = "none";
    }

    function fetchNotifications(){
        fetch("fetch_notifications.php")
        .then(res => res.json())
        .then(data => {
            // Update Notification Badge
            const nBadge = document.getElementById("notifBadge");
            if(data.unreadNotifs > 0){
                nBadge.style.display = "inline-block";
                nBadge.innerText = data.unreadNotifs;
                if(data.unreadNotifs > lastNotifCount) {
                    nBadge.classList.add("pulse");
                    setTimeout(() => nBadge.classList.remove("pulse"), 600);
                }
            } else { nBadge.style.display = "none"; }
            lastNotifCount = data.unreadNotifs;

            // Update Message Badge
            const mBadge = document.getElementById("msgBadge");
            if(data.unreadMsgs > 0){
                mBadge.style.display = "inline-block";
                mBadge.innerText = data.unreadMsgs;
                if(data.unreadMsgs > lastMsgCount) {
                    mBadge.classList.add("pulse");
                    setTimeout(() => mBadge.classList.remove("pulse"), 600);
                }
            } else { mBadge.style.display = "none"; }
            lastMsgCount = data.unreadMsgs;

            // Update Notification Modal Content
            const nContainer = document.getElementById("notifContainer");
            if(nContainer) {
                nContainer.innerHTML = data.notifications.length === 0 ? "<div class='modal-item'>No notifications yet.</div>" : "";
                data.notifications.forEach(n => {
                    nContainer.innerHTML += `<div class="modal-item ${n.is_read==0?'unread-item':''}">
                        ${n.message}<small>${n.created_at}</small></div>`;
                });
            }

            // Update Message Modal Content
            const mContainer = document.getElementById("msgContainer");
            if(mContainer) {
                mContainer.innerHTML = data.messages.length === 0 ? "<div class='modal-item'>No messages yet.</div>" : "";
                data.messages.forEach(m => {
                    mContainer.innerHTML += `<div class="modal-item ${m.is_read==0?'unread-item':''}">
                        <strong>${m.sender_name}</strong><br>${m.message}<small>${m.created_at}</small></div>`;
                });
            }
        });
    }

    setInterval(fetchNotifications, 5000);
</script>
</body>
</html>