<?php
session_start();
require_once 'supabase_config.php';

echo "<h1>User Lookup Debug</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} .warning{color:orange;}</style>";

try {
    $supabase = getSupabaseClient();

    // Get attendance records
    echo "<h2>1. Attendance Records Analysis</h2>";
    $attendance = $supabase->select('attendance', '*', [], '', 5);

    if ($attendance) {
        echo "<p class='success'>✅ Found " . count($attendance) . " attendance records</p>";

        $reportData = [];
        $reportData[] = ['Date', 'Student ID', 'Student Name', 'Email', 'Course', 'Event Title', 'Attendance Type', 'Check In Time', 'Check Out Time', 'Duration (Hours)'];

        foreach ($attendance as $i => $record) {
            echo "<h3>Record " . ($i + 1) . ":</h3>";
            echo "<p><strong>user_id_new:</strong> " . $record['user_id_new'] . " (type: " . gettype($record['user_id_new']) . ")</p>";
            echo "<p><strong>event_id:</strong> " . $record['event_id'] . "</p>";

            // Try to find user
            $userId = $record['user_id_new'];
            $user = $supabase->select('users', '*', ['id' => $userId]);
            $userInfo = is_array($user) && !empty($user) ? $user[0] : null;

            if ($userInfo) {
                echo "<p class='success'>✅ User found: " . $userInfo['first_name'] . " " . $userInfo['last_name'] . "</p>";
            } else {
                echo "<p class='error'>❌ User not found with ID: $userId</p>";
            }

            // Try to find event
            $eventId = $record['event_id'];
            echo "<p><strong>Looking up event with ID:</strong> $eventId</p>";
            $event = $supabase->select('events', '*', ['id' => $eventId]);
            $eventInfo = is_array($event) && !empty($event) ? $event[0] : null;

            if ($eventInfo) {
                echo "<p class='success'>✅ Event found: " . $eventInfo['title'] . "</p>";
            } else {
                echo "<p class='error'>❌ Event not found with ID: $eventId</p>";
            }

            // Check if this record would be included in the report
            if ($userInfo && $eventInfo) {
                echo "<p class='success'>✅ This record WILL be included in CSV report</p>";

                $checkInTime = $record['check_in_time'] ?? $record['attendance_date'];
                $checkOutTime = $record['check_out_time'] ?? '';

                // Calculate duration if both times available
                $duration = '';
                if ($checkInTime && $checkOutTime) {
                    $start = strtotime($checkInTime);
                    $end = strtotime($checkOutTime);
                    if ($start && $end) {
                        $duration = round(($end - $start) / 3600, 2);
                    }
                }

                $reportData[] = [
                    date('Y-m-d', strtotime($checkInTime)),
                    $userInfo['id'],
                    $userInfo['first_name'] . ' ' . $userInfo['last_name'],
                    $userInfo['email'],
                    $userInfo['course'],
                    $eventInfo['title'],
                    $record['attendance_type'] ?? 'check_in',
                    $checkInTime ? date('Y-m-d H:i:s', strtotime($checkInTime)) : '',
                    $checkOutTime ? date('Y-m-d H:i:s', strtotime($checkOutTime)) : '',
                    $duration
                ];
            } else {
                echo "<p class='error'>❌ This record will be SKIPPED (missing user or event data)</p>";
            }
            echo "<hr>";
        }

        echo "<h3>CSV Report Preview:</h3>";
        echo "<p><strong>Total rows that would be generated:</strong> " . count($reportData) . " (including header)</p>";

        if (count($reportData) > 1) {
            echo "<p class='success'>✅ Report would contain data</p>";
            echo "<h4>Sample data:</h4>";
            echo "<table border='1' style='border-collapse:collapse;'>";
            foreach (array_slice($reportData, 0, 3) as $row) {
                echo "<tr>";
                foreach ($row as $cell) {
                    echo "<td style='padding:5px;'>" . htmlspecialchars($cell) . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";

            // Generate a test CSV download
            echo "<h4>Test CSV Download:</h4>";
            echo '<form method="POST">';
            echo '<input type="hidden" name="download_debug_csv" value="1">';
            echo '<button type="submit" style="padding:10px 20px; background:#28a745; color:white; border:none; border-radius:5px;">Download Debug CSV</button>';
            echo '</form>';
        } else {
            echo "<p class='warning'>⚠️ Report would be empty (only header row)</p>";
        }
    } else {
        echo "<p class='error'>❌ No attendance records found</p>";
    }

    // List all events to see what exists
    echo "<h2>2. All Events</h2>";
    $events = $supabase->select('events', 'id, title, status', [], '', 10);

    if ($events) {
        echo "<p class='info'>Available event IDs:</p>";
        echo "<ul>";
        foreach ($events as $event) {
            echo "<li>ID: " . $event['id'] . " - " . $event['title'] . " (" . $event['status'] . ")</li>";
        }
        echo "</ul>";
    } else {
        echo "<p class='error'>❌ No events found</p>";
    }

    // List all users to see what IDs exist
    echo "<h2>3. All Users</h2>";
    $users = $supabase->select('users', 'id, first_name, last_name', [], '', 10);

    if ($users) {
        echo "<p class='info'>Available user IDs:</p>";
        echo "<ul>";
        foreach ($users as $user) {
            echo "<li>ID: " . $user['id'] . " (type: " . gettype($user['id']) . ") - " . $user['first_name'] . " " . $user['last_name'] . "</li>";
        }
        echo "</ul>";
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// Handle debug CSV download
if (isset($_POST['download_debug_csv'])) {
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    // Re-generate the same data
    $supabase = getSupabaseClient();
    $attendance = $supabase->select('attendance', '*', [], '', 5);

    $reportData = [];
    $reportData[] = ['Date', 'Student ID', 'Student Name', 'Email', 'Course', 'Event Title', 'Attendance Type', 'Check In Time', 'Check Out Time', 'Duration (Hours)'];

    foreach ($attendance as $record) {
        $userId = $record['user_id_new'];
        $user = $supabase->select('users', '*', ['id' => $userId]);
        $userInfo = is_array($user) && !empty($user) ? $user[0] : null;

        $event = $supabase->select('events', '*', ['id' => $record['event_id']]);
        $eventInfo = is_array($event) && !empty($event) ? $event[0] : null;

        if ($userInfo && $eventInfo) {
            $checkInTime = $record['check_in_time'] ?? $record['attendance_date'];
            $checkOutTime = $record['check_out_time'] ?? '';

            $duration = '';
            if ($checkInTime && $checkOutTime) {
                $start = strtotime($checkInTime);
                $end = strtotime($checkOutTime);
                if ($start && $end) {
                    $duration = round(($end - $start) / 3600, 2);
                }
            }

            $reportData[] = [
                date('Y-m-d', strtotime($checkInTime)),
                $userInfo['id'],
                $userInfo['first_name'] . ' ' . $userInfo['last_name'],
                $userInfo['email'],
                $userInfo['course'],
                $eventInfo['title'],
                $record['attendance_type'] ?? 'check_in',
                $checkInTime ? date('Y-m-d H:i:s', strtotime($checkInTime)) : '',
                $checkOutTime ? date('Y-m-d H:i:s', strtotime($checkOutTime)) : '',
                $duration
            ];
        }
    }

    $filename = 'debug_attendance_report_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    foreach ($reportData as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}

echo "<p><a href='reports.php'>← Back to Reports</a> | <a href='debug-reports.php'>Debug Reports</a></p>";
