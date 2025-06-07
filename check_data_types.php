<?php
session_start();
require_once 'supabase_config.php';

echo "<h2>Database Data Type Checker</h2>";

try {
    $supabase = getSupabaseClient();

    // Check users table - what format are the IDs?
    echo "<h3>1. Users Table ID Format Check</h3>";
    $users = $supabase->select('users', 'id, first_name, last_name', [], '', 3);
    if ($users && count($users) > 0) {
        echo "<p>✅ Found " . count($users) . " users</p>";
        foreach ($users as $user) {
            echo "<p>User ID: " . $user['id'] . " (Name: " . $user['first_name'] . " " . $user['last_name'] . ")</p>";

            // Check if ID is numeric or UUID format
            if (is_numeric($user['id'])) {
                echo "<p>  → This is a numeric ID (integer)</p>";
            } elseif (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $user['id'])) {
                echo "<p>  → This is a UUID format</p>";
            } else {
                echo "<p>  → Unknown ID format</p>";
            }
        }
    }

    // Check attendance table structure
    echo "<h3>2. Attendance Table Sample Data</h3>";
    $attendance = $supabase->select('attendance', '*', [], '', 3);
    if ($attendance && count($attendance) > 0) {
        echo "<p>✅ Found " . count($attendance) . " attendance records</p>";
        foreach ($attendance as $record) {
            echo "<pre>";
            print_r($record);
            echo "</pre>";
            break; // Just show first record structure
        }
    } else {
        echo "<p>No attendance records found</p>";
    }

    // Try to insert a test record to see what format is expected
    echo "<h3>3. Database Column Type Test</h3>";

    // Test with the actual user ID from the error
    $testUserId = "2"; // From the QR code error
    $events = $supabase->select('events', 'id', [], '', 1);

    if ($events && count($events) > 0) {
        $testEventId = $events[0]['id'];

        echo "<p>Testing insertion with:</p>";
        echo "<p>  user_id_new: " . $testUserId . " (type: " . gettype($testUserId) . ")</p>";
        echo "<p>  event_id: " . $testEventId . "</p>";

        // Try insertion
        try {
            $testData = [
                'user_id_new' => $testUserId,
                'event_id' => $testEventId,
                'attendance_type' => 'check_in',
                'check_in_time' => date('Y-m-d H:i:s'),
                'attendance_date' => date('Y-m-d H:i:s'),
                'check_in_method' => 'qr_code',
                'notes' => 'Debug test - safe to delete'
            ];

            $result = $supabase->insert('attendance', $testData);

            if ($result) {
                echo "<p>✅ Test insertion successful - data types are compatible</p>";

                // Clean up
                $supabase->delete('attendance', [
                    'user_id_new' => $testUserId,
                    'event_id' => $testEventId,
                    'attendance_type' => 'check_in'
                ]);
                echo "<p>Test record cleaned up</p>";
            } else {
                echo "<p>❌ Test insertion failed</p>";
            }
        } catch (Exception $e) {
            echo "<p>❌ Database error: " . $e->getMessage() . "</p>";

            if (strpos($e->getMessage(), 'uuid') !== false) {
                echo "<p><strong>Issue identified:</strong> The user_id_new column expects UUID format, but you're providing integer ID.</p>";
                echo "<p><strong>Solution options:</strong></p>";
                echo "<ul>";
                echo "<li>1. Change user_id_new column type to INTEGER</li>";
                echo "<li>2. Convert integer IDs to UUID format in the database</li>";
                echo "<li>3. Cast integer to text in the queries</li>";
                echo "</ul>";
            }
        }
    }
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='qr_scanner.php'>← Back to QR Scanner</a></p>";
