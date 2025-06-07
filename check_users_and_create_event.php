<?php
session_start();
require_once 'supabase_config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Check Users & Create Events</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        .warning { color: orange; }
        .container { background: #f9f9f9; padding: 20px; border-radius: 10px; margin: 10px 0; }
    </style>
</head>
<body>";

echo "<h2>👥 Users & Events Diagnostic</h2>";

try {
    $supabase = getSupabaseClient();
    echo "<p class='success'>✓ Supabase connection working</p>";

    // Check users
    echo "<div class='container'>";
    echo "<h3>👤 Checking Users</h3>";

    $users = $supabase->select('users', 'id, first_name, last_name, email, role');

    if (empty($users)) {
        echo "<p class='warning'>⚠️ No users found in database!</p>";
        echo "<p>You need to register at least one user first:</p>";
        echo "<ol>";
        echo "<li><a href='index.php'>Go to Login Page</a></li>";
        echo "<li>Click 'Create Account'</li>";
        echo "<li>Register with any email/password</li>";
        echo "<li>Come back here</li>";
        echo "</ol>";
    } else {
        echo "<p class='success'>✓ Found " . count($users) . " users:</p>";
        echo "<ul>";
        foreach ($users as $user) {
            $roleIcon = $user['role'] === 'admin' ? '👑' : '👤';
            echo "<li>{$roleIcon} {$user['first_name']} {$user['last_name']} ({$user['email']}) - {$user['role']}</li>";
        }
        echo "</ul>";

        // Use the first user to create events
        $firstUser = $users[0];
        echo "</div>";

        echo "<div class='container'>";
        echo "<h3>📅 Creating Sample Events</h3>";
        echo "<p class='info'>Using user: {$firstUser['first_name']} {$firstUser['last_name']}</p>";

        $sampleEvents = [
            [
                'title' => 'Computer Science Orientation',
                'description' => 'Welcome orientation for new Computer Science students',
                'event_date' => date('Y-m-d', strtotime('+3 days')),
                'start_time' => '09:00',
                'end_time' => '12:00',
                'location' => 'Main Auditorium',
                'max_attendees' => 200,
                'status' => 'active',
                'created_by' => $firstUser['id']
            ],
            [
                'title' => 'Engineering Career Fair',
                'description' => 'Career opportunities for engineering students',
                'event_date' => date('Y-m-d', strtotime('+7 days')),
                'start_time' => '10:00',
                'end_time' => '16:00',
                'location' => 'Engineering Building',
                'max_attendees' => 150,
                'status' => 'active',
                'created_by' => $firstUser['id']
            ],
            [
                'title' => 'IT Workshop: Web Development',
                'description' => 'Hands-on workshop on modern web development techniques',
                'event_date' => date('Y-m-d', strtotime('+10 days')),
                'start_time' => '13:00',
                'end_time' => '17:00',
                'location' => 'IT Laboratory',
                'max_attendees' => 50,
                'status' => 'active',
                'created_by' => $firstUser['id']
            ]
        ];

        $successCount = 0;
        foreach ($sampleEvents as $event) {
            try {
                $result = $supabase->insert('events', $event);
                if ($result) {
                    echo "<p class='success'>✓ Created: {$event['title']}</p>";
                    $successCount++;
                } else {
                    echo "<p class='error'>✗ Failed: {$event['title']}</p>";
                }
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'duplicate') !== false) {
                    echo "<p class='info'>ℹ️ Already exists: {$event['title']}</p>";
                } else {
                    echo "<p class='error'>✗ Error creating {$event['title']}: " . $e->getMessage() . "</p>";
                }
            }
        }

        echo "<p class='success'><strong>Created {$successCount} new events!</strong></p>";
        echo "</div>";

        // Check final events
        echo "<div class='container'>";
        echo "<h3>📋 Final Event List</h3>";
        $allEvents = $supabase->select('events', 'title, event_date, status, location');

        if (!empty($allEvents)) {
            echo "<p class='success'>✓ Total events in database: " . count($allEvents) . "</p>";
            echo "<ul>";
            foreach ($allEvents as $event) {
                $statusIcon = $event['status'] === 'active' ? '🟢' : '🔴';
                echo "<li>{$statusIcon} <strong>{$event['title']}</strong> - {$event['event_date']} at {$event['location']}</li>";
            }
            echo "</ul>";
        }
        echo "</div>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>🔗 Next Steps</h3>";
echo "<p><a href='qr_scanner.php' style='background: #007bff; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; margin: 5px;'>📱 Go to QR Scanner</a></p>";
echo "<p><a href='admin_dashboard.php' style='background: #28a745; color: white; padding: 10px 20px; border-radius: 5px; text-decoration: none; margin: 5px;'>🎛️ Admin Dashboard</a></p>";

echo "</body></html>";
