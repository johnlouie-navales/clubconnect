<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clubconnect");

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

/* ==============================
   GET USER CLUB MEMBERSHIPS
============================== */
$userClubs = [];
$memberQuery = $conn->query("SELECT club_id FROM club_members WHERE user_id = $user_id");
if ($memberQuery) {
    while ($row = $memberQuery->fetch_assoc()) {
        $userClubs[] = (int)$row['club_id'];
    }
}

/* ==============================
   GET POSTS AS EVENTS
============================== */
$currentYear = date("Y");
$startDate = ($currentYear - 1) . "-01-01";
$endDate   = ($currentYear + 1) . "-12-31";

$events = [];
$eventQuery = $conn->query("
    SELECT 
        p.id, 
        p.title, 
        p.content, 
        p.image_url,         -- Ensure this matches your DB column
        DATE(p.created_at) as event_date, 
        p.club_id, 
        c.club_name, 
        c.hex_color
    FROM club_posts p
    JOIN clubs c ON p.club_id = c.id
    WHERE p.created_at BETWEEN '$startDate' AND '$endDate'
");

if ($eventQuery) {
    while ($row = $eventQuery->fetch_assoc()) {
        $events[] = $row;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>ClubConnect - Event Calendar</title>
<style>
    body {
        margin: 0; font-family: 'Segoe UI', sans-serif;
        background: linear-gradient(-45deg, #0f2027, #203a43, #2c5364, #b31217, #e52d27);
        background-size: 400% 400%; animation: gradientBG 12s ease infinite;
        color: white; padding: 120px 20px 40px;
    }
    @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    
    .calendar-container {
        max-width: 1000px; margin: auto; background: rgba(0,0,0,0.6);
        backdrop-filter: blur(20px); padding: 30px; border-radius: 25px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.4);
    }
    .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .calendar-header button { background: #e52d27; border: none; padding: 8px 15px; border-radius: 10px; color: white; cursor: pointer; }
    
    .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 10px; }
    .day-name { text-align: center; font-weight: bold; opacity: 0.8; }
    .day {
        height: 100px; background: rgba(255,255,255,0.05); border-radius: 12px;
        padding: 8px; position: relative; font-size: 14px; overflow-y: auto;
        display: flex; flex-wrap: wrap; align-content: flex-start; gap: 5px;
    }
    .day-number { position: absolute; bottom: 8px; right: 10px; font-size: 12px; opacity: 0.6; pointer-events: none; }
    
    .dot {
        width: 14px; height: 14px; border-radius: 50%; display: inline-block;
        border: 1px solid rgba(255,255,255,0.3); transition: 0.2s ease-in-out; cursor: pointer;
    }
    .dot:hover { transform: scale(1.35); box-shadow: 0 0 10px white; z-index: 10; }

    .upcoming-item { padding: 15px; background: rgba(255,255,255,0.05); margin-bottom: 12px; border-radius: 12px; }

    .topbar {
        position: fixed; top: 0; left: 0; width: 100%; height: 70px;
        background: rgba(0,0,0,0.6); backdrop-filter: blur(20px);
        display: flex; justify-content: space-between; align-items: center;
        padding: 0 40px; z-index: 1000;
    }
    .topbar a { text-decoration: none; color: white; background: #e52d27; padding: 8px 15px; border-radius: 10px; }
</style>
</head>
<body>

<div class="topbar">
    <div style="display:flex; align-items:center; gap:20px;">
        <div style="font-size:20px; font-weight:bold;">ClubConnect</div>
        <a href="home.php">Home</a>
    </div>
</div>

<div class="calendar-container">
    <div class="calendar-header">
        <h2 id="monthTitle"></h2>
        <div>
            <button onclick="changeMonth(-1)">◀</button>
            <button onclick="changeMonth(1)">▶</button>
        </div>
    </div>
    <div class="calendar-grid" id="calendarGrid"></div>

    <div class="upcoming">
        <h3>Upcoming Events</h3>
        <?php
        $today = date("Y-m-d");
        $upcomingQuery = $conn->query("
            SELECT p.content as title, c.club_name, c.hex_color, DATE(p.created_at) as event_date
            FROM club_posts p
            JOIN clubs c ON p.club_id = c.id
            WHERE p.created_at >= '$today'
            ORDER BY p.created_at ASC LIMIT 5
        ");
        if ($upcomingQuery) {
            while ($u = $upcomingQuery->fetch_assoc()) {
                $color = $u['hex_color'] ?: '#e52d27';
                echo "<div class='upcoming-item' style='border-left: 5px solid $color;'>
                        <strong style='color: $color;'>" . htmlspecialchars(substr($u['title'], 0, 50)) . "...</strong><br>
                        <small>{$u['club_name']} • " . date("M d, Y", strtotime($u['event_date'])) . "</small>
                      </div>";
            }
        }
        ?>
    </div>
</div>

<div id="postPreviewModal" style="display:none; position:fixed; z-index:2000; left:0; top:0; width:100%; height:100%; background:rgba(0,0,0,0.8); backdrop-filter:blur(8px); justify-content:center; align-items:center; padding: 20px;">
    <div style="background:#1a1a1a; width:100%; max-width:500px; border-radius:20px; border-top: 10px solid #e52d27; position:relative; color:white; overflow: hidden; box-shadow: 0 25px 50px rgba(0,0,0,0.6);">
        
        <div id="previewImageContainer" style="width:100%; height:200px; background:#000; display:none; overflow:hidden;">
            <img id="previewImage" src="" style="width:100%; height:100%; object-fit:cover;">
        </div>

        <div style="padding:25px;">
            <span onclick="closePreview()" style="position:absolute; top:15px; right:20px; font-size:28px; cursor:pointer; text-shadow: 0 0 10px #000;">&times;</span>
            <small id="previewClub" style="text-transform:uppercase; letter-spacing:1px; font-weight:bold;"></small>
            <h2 id="previewTitle" style="margin:10px 0; font-size: 22px;"></h2>
            <hr style="border:0; border-top:1px solid rgba(255,255,255,0.1); margin:15px 0;">
            <p id="previewContent" style="font-size:15px; line-height:1.6; opacity:0.9; max-height:150px; overflow-y:auto;"></p>
            <button onclick="closePreview()" style="margin-top:20px; background:#e52d27; border:none; color:white; padding:12px; width:100%; border-radius:12px; font-weight:bold; cursor:pointer; transition: 0.3s;">Close</button>
        </div>
    </div>
</div>

<script>
const events = <?php echo json_encode($events); ?>;
const userClubs = <?php echo json_encode($userClubs); ?>;
let currentDate = new Date();

function renderCalendar() {
    const grid = document.getElementById("calendarGrid");
    grid.innerHTML = "";
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();

    document.getElementById("monthTitle").innerText = currentDate.toLocaleString('default', { month: 'long' }) + " " + year;

    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);

    ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"].forEach(d => {
        const div = document.createElement("div");
        div.className = "day-name"; div.innerText = d; grid.appendChild(div);
    });

    for (let i = 0; i < firstDay.getDay(); i++) {
        grid.appendChild(document.createElement("div"));
    }

    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
        const dayDiv = document.createElement("div");
        dayDiv.className = "day";

        const num = document.createElement("div");
        num.className = "day-number"; num.innerText = d;
        dayDiv.appendChild(num);

        events.forEach(ev => {
            if (ev.event_date === dateStr) {
                const dot = document.createElement("span");
                dot.className = "dot";
                dot.style.backgroundColor = ev.hex_color || '#e52d27';
                
                if (userClubs.includes(parseInt(ev.club_id))) {
                    dot.style.boxShadow = `0 0 8px ${ev.hex_color}`;
                }

                dot.onclick = (e) => {
                    e.stopPropagation();
                    showPostPreview(ev);
                };
                dayDiv.appendChild(dot);
            }
        });
        grid.appendChild(dayDiv);
    }
}

function showPostPreview(post) {
    const modal = document.getElementById("postPreviewModal");
    const imgContainer = document.getElementById("previewImageContainer");
    const imgTag = document.getElementById("previewImage");
    const clubEl = document.getElementById("previewClub");
    const titleEl = document.getElementById("previewTitle");
    const contentEl = document.getElementById("previewContent");

    clubEl.innerText = post.club_name;
    clubEl.style.color = post.hex_color;
    modal.querySelector('div').style.borderTopColor = post.hex_color;
    titleEl.innerText = post.title || "Club Update";
    contentEl.innerText = post.content;

    // Use post.image_url to match your SQL query
    if (post.image_url && post.image_url.trim() !== "") {
        // Use the full URL or correct path. If it's just a filename, add your path prefix here
        imgTag.src = post.image_url; 
        imgContainer.style.display = "block";
    } else {
        imgContainer.style.display = "none";
    }

    modal.style.display = "flex";
}

function closePreview() {
    document.getElementById("postPreviewModal").style.display = "none";
}

function changeMonth(step) {
    currentDate.setMonth(currentDate.getMonth() + step);
    renderCalendar();
}

// Close on outside click
window.onclick = function(event) {
    const modal = document.getElementById("postPreviewModal");
    if (event.target == modal) closePreview();
}

renderCalendar();
</script>
</body>
</html>