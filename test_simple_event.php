<?php
session_start();
require_once 'supabase_config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Simple Event Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
    </style>
</head>
<body>";

echo "<h2>🧪 Simple Event Creation Test</h2>";

try {
    $supabase = getSupabaseClient();
    echo "<p class='info'>✓ Supabase client created successfully</p>";

    // Try a simple insert without complex queries
    $eventData = [
        'title' => 'Test Event ' . date('Y-m-d H:i:s'),
        'description' => 'Simple test event to check database connection',
        'event_date' => date('Y-m-d', strtotime('+1 day')),
        'start_time' => '10:00',
        'end_time' => '12:00',
        'location' => 'Test Location',
        'status' => 'active',
        'created_by' => '00000000-0000-0000-0000-000000000000' // Use a placeholder UUID
    ];

    echo "<p class='info'>📝 Attempting to create test event...</p>";

    $result = $supabase->insert('events', $eventData);

    if ($result) {
        echo "<p class='success'>✅ SUCCESS! Event created successfully!</p>";
        echo "<p>Event data: " . json_encode($result) . "</p>";

        // Now try to retrieve events
        echo "<p class='info'>📋 Now testing event retrieval...</p>";
        $events = $supabase->select('events', 'title, event_date, status');

        if ($events) {
            echo "<p class='success'>✅ Events retrieved successfully!</p>";
            echo "<p>Found " . count($events) . " events:</p>";
            echo "<ul>";
            foreach ($events as $event) {
                echo "<li>{$event['title']} - {$event['event_date']} ({$event['status']})</li>";
            }
            echo "</ul>";
        } else {
            echo "<p class='error'>❌ Could not retrieve events</p>";
        }
    } else {
        echo "<p class='error'>❌ Failed to create event</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p class='info'>This could be due to:</p>";
    echo "<ul>";
    echo "<li>Database connection issues</li>";
    echo "<li>Missing events table</li>";
    echo "<li>Invalid Supabase credentials</li>";
    echo "<li>Network connectivity problems</li>";
    echo "</ul>";
}

echo "<hr>";
echo "<p><a href='qr_scanner.php'>← Back to QR Scanner</a> | <a href='admin_dashboard.php'>Admin Dashboard</a></p>";
echo "</body></html>";
