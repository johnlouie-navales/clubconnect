<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clubconnect");

if (!isset($_SESSION['user_id']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: home.php");
    exit();
}

$fullname = $_SESSION['fullname'];

// Fetch Stats
$total_users = $conn->query("SELECT COUNT(*) as count FROM users")->fetch_assoc()['count'];
$total_clubs = $conn->query("SELECT COUNT(*) as count FROM clubs")->fetch_assoc()['count'];
$total_posts = $conn->query("SELECT COUNT(*) as count FROM club_posts")->fetch_assoc()['count'];

$clubs_res = $conn->query("SELECT * FROM clubs");
// Fetch only the 20 most recent posts to keep the dashboard snappy
$posts_res = $conn->query("SELECT p.*, c.club_name, c.hex_color FROM club_posts p JOIN clubs c ON p.club_id = c.id ORDER BY p.created_at DESC LIMIT 20");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - ClubConnect</title>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <style>
        :root {
            --bg-gradient: linear-gradient(-45deg, #1700c4, #3b3ff5, #642d76, #b31217, #e52d27);
            --card-bg: rgba(0, 0, 0, 0.8);
            --text-color: #fff;
            --accent: #b31217;
            --input-bg: rgba(255,255,255,0.1);
        }

        body {
            background: var(--bg-gradient); background-size: 400% 400%; animation: gradientMove 12s ease infinite;
            color: var(--text-color); font-family: 'Segoe UI', sans-serif; margin: 0; padding: 20px;
        }
        @keyframes gradientMove { 0%{background-position:0% 50%;} 50%{background-position:100% 50%;} 100%{background-position:0% 50%;} }

        .admin-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 25px; margin-top: 80px; }
        .admin-card { background: var(--card-bg); backdrop-filter: blur(15px); border: 1px solid rgba(255,255,255,0.1); border-radius: 20px; padding: 25px; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 12px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.05); }

        .modal { 
            display: none; position: fixed; z-index: 3000; left: 0; top: 0; width: 100%; height: 100%; 
            background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); justify-content: center; align-items: center; 
        }
        .modal-content { 
            background: #1a1a1a; width: 450px; padding: 30px; border-radius: 20px; border: 1px solid var(--accent);
        }
        
        #calendar { background: rgba(0,0,0,0.3); border-radius: 15px; padding: 10px; color: white; }
        
        /* Calendar Markers */
        .fc-daygrid-event { border: none !important; background: transparent !important; }
        .fc-event-main { display: flex; align-items: center; gap: 5px; color: white !important; font-size: 11px; }
        .event-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

        .btn-delete-small { color: #ff4d4d; background: none; border: none; cursor: pointer; transition: 0.2s; }
        .btn-delete-small:hover { color: #ff0000; transform: scale(1.2); }
        .admin-top-btn {
    transition: all 0.2s ease;
    border: none;
    color: white;
    padding: 8px 14px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
}

.announce-btn { background: #16a34a; }
.site-btn { background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2) !important; }
.logout-btn { background: #ef4444; }

.admin-top-btn:hover {
    transform: translateY(-2px);
    filter: brightness(1.2);
}

/* Responsive Fix: Hide text on small screens to keep buttons fitting */
@media (max-width: 850px) {
    .admin-top-btn span {
        display: none; /* Only show icons on smaller tablets/screens */
    }
    .admin-top-btn {
        padding: 10px;
    }
    .topbar {
        padding: 0 15px;
    }
}
    </style>
</head>
<body>

<div class="topbar" style="display:flex; justify-content: space-between; align-items: center; position:fixed; top:0; left:0; width:100%; height: 70px; padding: 0 4%; background: rgba(0,0,0,0.6); backdrop-filter:blur(15px); z-index:1000; box-sizing: border-box; border-bottom: 1px solid rgba(255,255,255,0.1);">
    
    <div style="display:flex; align-items:center; gap:12px; min-width: max-content;">
        <img src="/clubconnect/assetimages/cc.png" height="32" style="filter: drop-shadow(0 0 5px rgba(255,255,255,0.2));">
        <span style="font-weight:800; letter-spacing: 1px; font-size: 14px; text-transform: uppercase; white-space: nowrap;">Admin Dashboard</span>
    </div>
    
    <div style="display:flex; gap:10px; align-items:center; flex-wrap: nowrap;">
        
        <button onclick="openAnnounceModal()" class="admin-top-btn announce-btn">
            <i data-lucide="megaphone" size="16"></i> <span>Post Announcement</span>
        </button>

        <button onclick="window.location.href='home.php'" class="admin-top-btn site-btn">
            <i data-lucide="external-link" size="16"></i> <span>View Site</span>
        </button>

        <button onclick="if(confirm('Are you sure?')) window.location.href='logout.php'" class="admin-top-btn logout-btn">
            <i data-lucide="log-out" size="16"></i> <span>Logout</span>
        </button>

    </div>
</div>
    </div>
</div>
</div>

<div class="admin-grid">
    <div class="admin-card">
        <div class="section-header">
            <h3>Manage Clubs</h3>
            <button onclick="openClubModal()" style="background:var(--accent); color:white; border:none; padding:8px 15px; border-radius:8px; cursor:pointer;">+ Add Club</button>
        </div>
        <table>
            <?php while($club = $clubs_res->fetch_assoc()): ?>
            <tr>
                <td><div style="width:12px; height:12px; border-radius:50%; background:<?php echo $club['hex_color']; ?>"></div></td>
                <td><?php echo htmlspecialchars($club['club_name']); ?></td>
                <td style="text-align:right;">
                    <button onclick='editClub(<?php echo json_encode($club); ?>)' style="background:none; border:none; color:cyan; cursor:pointer;"><i data-lucide="edit-3" size="18"></i></button>
                    <button onclick="deleteClub(<?php echo $club['id']; ?>)" class="btn-delete-small"><i data-lucide="trash-2" size="18"></i></button>
                </td>
            </tr>
            <?php endwhile; ?>
        </table>
    </div>

    <div class="admin-card">
        <div class="section-header"><h3>Event Schedule</h3></div>
        <div id="calendar"></div>
    </div>

    <div class="admin-card">
        <div class="section-header"><h3>Recent Activity</h3></div>
        <div style="max-height: 450px; overflow-y: auto; padding-right: 5px;">
            <?php while($post = $posts_res->fetch_assoc()): ?>
            <div style="background:<?php echo $post['hex_color']; ?>15; border-left:4px solid <?php echo $post['hex_color']; ?>; padding:12px; margin-bottom:12px; border-radius:8px; position:relative;">
                <button onclick="deletePost(<?php echo $post['id']; ?>)" style="position:absolute; top:8px; right:8px; color:rgba(255,255,255,0.4); background:none; border:none; cursor:pointer;">✕</button>
                <div style="font-size:11px; font-weight:bold; color:<?php echo $post['hex_color']; ?>; margin-bottom:4px;"><?php echo strtoupper($post['club_name']); ?></div>
                <div style="font-size:14px;"><?php echo nl2br(htmlspecialchars($post['content'])); ?></div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>
</div>

<div id="eventDetailModal" class="modal">
    <div class="modal-content" style="text-align: center;">
        <div id="popupClubColor" style="height:5px; width:100%; border-radius:10px; margin-bottom:15px;"></div>
        <h2 id="popupTitle" style="margin-top:0;"></h2>
        <p id="popupClubName" style="color:var(--accent); font-weight:bold;"></p>
        <p id="popupDescription" style="opacity:0.8; font-size:14px;"></p>
        <div style="display:flex; justify-content:center; gap:10px; margin-top:20px;">
            <button onclick="closeEventModal()" style="background:#475569; border:none; color:white; padding:10px 20px; border-radius:8px; cursor:pointer;">Close</button>
            <button id="deleteEventFromPopup" class="btn-delete-large" style="background:#ef4444; border:none; color:white; padding:10px 20px; border-radius:8px; cursor:pointer;">Delete Post</button>
        </div>
    </div>
</div>

<div id="clubModal" class="modal">
    <div class="modal-content">
        <div class="section-header">
            <h3 id="modalTitle">Add New Club</h3>
            <span onclick="closeModal()" style="cursor:pointer;">&times;</span>
        </div>
        <form action="admin_actions.php?action=save_club" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="club_id" id="club_id">
            
            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; margin-bottom:5px;">Club Name</label>
                <input type="text" name="club_name" id="club_name" style="width:100%; padding:10px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.2); color:white; border-radius:8px;" required>
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; margin-bottom:5px;">Description</label>
                <textarea name="description" id="club_description" rows="3" style="width:100%; padding:10px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.2); color:white; border-radius:8px; resize: none;"></textarea>
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; margin-bottom:5px;">Theme Color</label>
                <input type="color" name="hex_color" id="hex_color" style="width:100%; height:40px; border:none; background:none; cursor:pointer;">
            </div>

            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; margin-bottom:5px;">Club Logo / Banner</label>
                <input type="file" name="banner_image" accept="image/*" style="width:100%; color: #94a3b8; font-size: 13px;">
                <small style="color: #64748b; display: block; margin-top: 4px;">Leave empty to keep current image when editing.</small>
            </div>

            <button type="submit" class="btn-save" style="width:100%; padding:12px; background:var(--accent); border:none; color:white; border-radius:8px; cursor:pointer; font-weight: bold;">Save Club Details</button>
        </form>
    </div>
</div>
<script>
    lucide.createIcons();

    // CALENDAR INITIALIZATION
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            events: 'fetch_admin_events.php',
            height: 550,
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: ''
            },
            // INTERACTIVE MARKERS (Like calendar.php)
            eventDidMount: function(info) {
                const color = info.event.extendedProps.color || '#b31217';
                const dot = `<span class="event-dot" style="background:${color}"></span>`;
                const title = info.event.title;
                info.el.querySelector('.fc-event-main').innerHTML = `${dot} ${title}`;
            },
            // CUSTOM POPUP ON CLICK
            eventClick: function(info) {
                const event = info.event;
                const props = event.extendedProps;

                document.getElementById('popupTitle').innerText = event.title;
                document.getElementById('popupClubName').innerText = props.club;
                document.getElementById('popupDescription').innerText = props.description || "No details provided.";
                document.getElementById('popupClubColor').style.backgroundColor = props.color;
                
                // Hook up the delete button in the popup
                document.getElementById('deleteEventFromPopup').onclick = function() {
                    deletePost(props.post_id);
                };

                document.getElementById('eventDetailModal').style.display = 'flex';
            }
        });
        calendar.render();
    });

    function closeEventModal() { document.getElementById('eventDetailModal').style.display = 'none'; }
    function deletePost(id) { if(confirm('Delete this post and event?')) window.location.href='admin_actions.php?action=delete_post&id='+id; }
    
    // Existing Club Modal Functions
   // UPDATED MODAL FUNCTIONS
    function openClubModal() {
        document.getElementById('modalTitle').innerText = "Add New Club";
        document.getElementById('club_id').value = "";
        document.getElementById('club_name').value = "";
        document.getElementById('club_description').value = ""; // Reset description
        document.getElementById('hex_color').value = "#b31217";
        document.getElementById('clubModal').style.display = "flex";
    }

    function editClub(club) {
        document.getElementById('modalTitle').innerText = "Edit Club";
        document.getElementById('club_id').value = club.id;
        document.getElementById('club_name').value = club.club_name;
        // Make sure your clubs table has a 'description' column being fetched
        document.getElementById('club_description').value = club.description || ""; 
        document.getElementById('hex_color').value = club.hex_color;
        document.getElementById('clubModal').style.display = "flex";
    }

    function closeModal() { document.getElementById('clubModal').style.display = "none"; }
    function deleteClub(id) { if(confirm('Delete club and ALL its data?')) window.location.href='admin_actions.php?action=delete_club&id='+id; }
    
    window.onclick = function(e) {
        if (e.target.className === 'modal') {
            closeModal();
            closeEventModal();
        }
    }
    // Announcement Modal Controls
function openAnnounceModal() { 
    document.getElementById('announceModal').style.display = 'flex'; 
}

function closeAnnounceModal() { 
    document.getElementById('announceModal').style.display = 'none'; 
}

// Update your existing window.onclick to include closing this modal
window.onclick = function(e) {
    if (e.target.className === 'modal') {
        closeModal(); // For club modal
        closeEventModal(); // For calendar modal
        closeAnnounceModal(); // For announcement modal
    }
}
</script>
<div id="announceModal" class="modal">
    <div class="modal-content" style="border-color: #16a34a;"> <div class="section-header">
            <div style="display:flex; align-items:center; gap:10px;">
                <i data-lucide="megaphone" style="color:#16a34a;"></i>
                <h3 style="margin:0;">Global Broadcast</h3>
            </div>
            <span onclick="closeAnnounceModal()" style="cursor:pointer; font-size: 24px;">&times;</span>
        </div>
        <p style="font-size: 13px; opacity: 0.6; margin-bottom: 20px;">This message will appear at the top of the feed for all students and moderators.</p>
        
        <form action="admin_actions.php?action=post_announcement" method="POST">
            <div style="margin-bottom:15px;">
                <label style="display:block; font-size:12px; margin-bottom:5px; opacity: 0.8;">Subject / Title</label>
                <input type="text" name="title" placeholder="e.g., Campus Maintenance or Event Update" style="width:100%; padding:12px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.2); color:white; border-radius:8px; box-sizing: border-box;" required>
            </div>
            <div style="margin-bottom:20px;">
                <label style="display:block; font-size:12px; margin-bottom:5px; opacity: 0.8;">Message Details</label>
                <textarea name="message" rows="5" placeholder="Write your announcement here..." style="width:100%; padding:12px; background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.2); color:white; border-radius:8px; resize:none; box-sizing: border-box;" required></textarea>
            </div>
            <button type="submit" style="width:100%; padding:14px; background:#16a34a; border:none; color:white; border-radius:10px; cursor:pointer; font-weight:bold; font-size: 16px;">
                Broadcast Announcement
            </button>
        </form>
    </div>
</div>
</body>
</html>