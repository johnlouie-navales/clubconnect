<?php
session_start();
$conn = new mysqli("localhost", "root", "", "clubconnect");

// Fetch events with club details for coloring
$query = "SELECT p.id, p.title, p.event_date, p.content as description, c.club_name, c.hex_color 
          FROM club_posts p 
          JOIN clubs c ON p.club_id = c.id 
          WHERE p.event_date IS NOT NULL";

$result = $conn->query($query);
$events = [];

while($row = $result->fetch_assoc()) {
    $events[] = [
        'title' => $row['title'],
        'start' => $row['event_date'],
        'extendedProps' => [
            'club' => $row['club_name'],
            'description' => $row['description'],
            'color' => $row['hex_color'],
            'post_id' => $row['id']
        ]
    ];
}

header('Content-Type: application/json');
echo json_encode($events);