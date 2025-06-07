<?php
session_start();
require_once 'supabase_config.php';

echo "<h2>QR Scanner Debug Tool</h2>";
echo "<p>Use this to debug QR scanner issues</p>";

try {
    $supabase = getSupabaseClient();
    echo "<p>✅ Supabase connection: OK</p>";

    // Test 1: Check attendance table structure
    echo "<h3>1. Attendance Table Structure Check</h3>";
    $result = $supabase->select('attendance', '*', [], '', 1);
    if ($result !== false) {
        echo "<p>✅ Attendance table accessible</p>";

        // Check column structure
        echo "<h4>Column Information:</h4>";
        echo "<pre>";
        // Get first record to see what columns exist
        if (!empty($result)) {
            echo "Available columns: " . implode(', ', array_keys($result[0])) . "\n";
        }
        echo "</pre>";

        // Check if user_id_new column exists by trying to query it
        try {
            $test = $supabase->select('attendance', 'user_id_new', [], '', 1);
            echo "<p>✅ user_id_new column exists</p>";
        } catch (Exception $e) {
            echo "<p>❌ user_id_new column missing: " . $e->getMessage() . "</p>";
            echo "<p><strong>Solution:</strong> Run the migration script in Supabase SQL Editor</p>";
        }

        // Check if old user_id column still exists
        try {
            $test = $supabase->select('attendance', 'user_id', [], '', 1);
            echo "<p>⚠️ Old user_id column still exists</p>";
        } catch (Exception $e) {
            echo "<p>✅ Old user_id column properly removed</p>";
        }
    } else {
        echo "<p>❌ Attendance table not accessible</p>";
    }

    // Test 2: Check users table
    echo "<h3>2. Users Table Check</h3>";
    $users = $supabase->select('users', 'id, first_name, last_name, email', [], '', 3);
    if ($users && count($users) > 0) {
        echo "<p>✅ Users table accessible with " . count($users) . " users</p>";
        echo "<p>Sample user ID: " . $users[0]['id'] . "</p>";
    } else {
        echo "<p>❌ No users found or table not accessible</p>";
    }

    // Test 3: Check events table
    echo "<h3>3. Events Table Check</h3>";
    $today = date('Y-m-d');
    $events = $supabase->select('events', 'id, title, event_date, status', [], '', 5);
    if ($events && count($events) > 0) {
        echo "<p>✅ Events table accessible with " . count($events) . " events</p>";
        $todayEvents = array_filter($events, function ($event) use ($today) {
            return $event['event_date'] === $today;
        });
        echo "<p>Events for today (" . $today . "): " . count($todayEvents) . "</p>";

        if (count($todayEvents) > 0) {
            $firstTodayEvent = array_values($todayEvents)[0]; // Get first element safely
            echo "<p>Sample event ID for today: " . $firstTodayEvent['id'] . "</p>";
        }
    } else {
        echo "<p>❌ No events found or table not accessible</p>";
    }

    // Test 4: Sample QR Data Test
    echo "<h3>4. Sample QR Data Test</h3>";
    if ($users && count($users) > 0) {
        $sampleUser = $users[0];
        $sampleQR = [
            'student_id' => $sampleUser['id'],
            'first_name' => $sampleUser['first_name'],
            'last_name' => $sampleUser['last_name'],
            'email' => $sampleUser['email'],
            'course' => 'Test Course',
            'year_level' => '1st Year',
            'section' => 'A',
            'timestamp' => time()
        ];

        echo "<p>✅ Sample QR data generated:</p>";
        echo "<pre>" . json_encode($sampleQR, JSON_PRETTY_PRINT) . "</pre>";

        // Test JSON parsing
        $qrString = json_encode($sampleQR);
        $parsed = json_decode($qrString, true);
        if ($parsed && isset($parsed['student_id'])) {
            echo "<p>✅ JSON encoding/decoding works</p>";
        } else {
            echo "<p>❌ JSON encoding/decoding failed</p>";
        }
    }

    // Test 5: Database Insertion Test
    echo "<h3>5. Database Insertion Test</h3>";
    if ($users && count($users) > 0 && $events && count($events) > 0) {
        $testUser = $users[0];
        $testEvent = $events[0];

        // Check if test record already exists
        $existing = $supabase->select('attendance', '*', [
            'user_id_new' => $testUser['id'],
            'event_id' => $testEvent['id'],
            'attendance_type' => 'check_in'
        ]);

        if (!$existing) {
            echo "<p>Testing database insertion...</p>";
            $testData = [
                'user_id_new' => $testUser['id'],
                'event_id' => $testEvent['id'],
                'attendance_type' => 'check_in',
                'check_in_time' => date('Y-m-d H:i:s'),
                'attendance_date' => date('Y-m-d H:i:s'),
                'check_in_method' => 'debug_test',
                'notes' => 'Debug test record - can be deleted'
            ];

            try {
                $result = $supabase->insert('attendance', $testData);
                if ($result) {
                    echo "<p>✅ Database insertion works</p>";

                    // Clean up test record
                    $supabase->delete('attendance', [
                        'user_id_new' => $testUser['id'],
                        'event_id' => $testEvent['id'],
                        'attendance_type' => 'check_in'
                    ]);
                    echo "<p>Test record cleaned up</p>";
                } else {
                    echo "<p>❌ Database insertion failed</p>";
                }
            } catch (Exception $e) {
                echo "<p>❌ Database insertion error: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p>Test record already exists</p>";
        }
    }
} catch (Exception $e) {
    echo "<p>❌ Critical error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='qr_scanner.php'>← Back to QR Scanner</a></p>";
echo "<p><strong>Note:</strong> Delete this debug file after troubleshooting is complete.</p>";
