<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

echo "<h1>Reports Debug Tool</h1>";
echo "<h2>Testing Report Generation</h2>";

// Test database connection
echo "<h3>1. Database Connection Test</h3>";
try {
    $supabase = getSupabaseClient();
    echo "✅ Supabase client initialized<br>";

    // Test basic query
    $testUsers = $supabase->select('users', 'id, first_name, last_name', [], '', 3);
    if (is_array($testUsers) && !empty($testUsers)) {
        echo "✅ Database connection working - found " . count($testUsers) . " users<br>";
    } else {
        echo "❌ Database query failed or no users found<br>";
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Test report generation functions
echo "<h3>2. Testing Report Generation Functions</h3>";

// Test attendance report
echo "<h4>Attendance Report Test:</h4>";
try {
    $attendanceResult = generateAttendanceReport();
    if ($attendanceResult['success']) {
        echo "✅ Attendance report generated successfully<br>";
        echo "📊 Data rows: " . count($attendanceResult['data']) . "<br>";
        if (!empty($attendanceResult['data'])) {
            echo "📋 Sample header: " . implode(', ', array_slice($attendanceResult['data'][0], 0, 5)) . "...<br>";
        }
    } else {
        echo "❌ Attendance report failed: " . $attendanceResult['message'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Attendance report error: " . $e->getMessage() . "<br>";
}

// Test events report
echo "<h4>Events Report Test:</h4>";
try {
    $eventsResult = generateEventReport();
    if ($eventsResult['success']) {
        echo "✅ Events report generated successfully<br>";
        echo "📊 Data rows: " . count($eventsResult['data']) . "<br>";
    } else {
        echo "❌ Events report failed: " . $eventsResult['message'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Events report error: " . $e->getMessage() . "<br>";
}

// Test users report
echo "<h4>Users Report Test:</h4>";
try {
    $usersResult = generateUserReport();
    if ($usersResult['success']) {
        echo "✅ Users report generated successfully<br>";
        echo "📊 Data rows: " . count($usersResult['data']) . "<br>";
    } else {
        echo "❌ Users report failed: " . $usersResult['message'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Users report error: " . $e->getMessage() . "<br>";
}

echo "<hr>";

// Test CSV generation
echo "<h3>3. Testing CSV Generation</h3>";

// Get sample data for CSV test
$sampleData = [
    ['Column 1', 'Column 2', 'Column 3'],
    ['Data 1', 'Data 2', 'Data 3'],
    ['Test 1', 'Test 2', 'Test 3']
];

echo "<h4>CSV Test with Sample Data:</h4>";
echo "<a href='?download_test_csv=1' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Download Test CSV</a><br><br>";

if (isset($_GET['download_test_csv'])) {
    // Test CSV download
    try {
        // Clear any existing output
        if (ob_get_level()) {
            ob_end_clean();
        }

        $filename = 'test_csv_' . date('Y-m-d_H-i-s') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Add BOM for UTF-8
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        foreach ($sampleData as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit();
    } catch (Exception $e) {
        echo "❌ CSV generation error: " . $e->getMessage() . "<br>";
    }
}

// Test real report CSV
echo "<h4>Real Report CSV Test:</h4>";
echo "<a href='?download_real_csv=attendance' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Download Attendance CSV</a>";
echo "<a href='?download_real_csv=events' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Download Events CSV</a>";
echo "<a href='?download_real_csv=users' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Download Users CSV</a><br><br>";

if (isset($_GET['download_real_csv'])) {
    $reportType = $_GET['download_real_csv'];

    try {
        // Generate report data
        switch ($reportType) {
            case 'attendance':
                $reportResult = generateAttendanceReport();
                break;
            case 'events':
                $reportResult = generateEventReport();
                break;
            case 'users':
                $reportResult = generateUserReport();
                break;
            default:
                throw new Exception('Invalid report type');
        }

        if ($reportResult['success'] && !empty($reportResult['data'])) {
            // Clear any existing output
            if (ob_get_level()) {
                ob_end_clean();
            }

            $filename = $reportType . '_report_' . date('Y-m-d_H-i-s') . '.csv';

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $output = fopen('php://output', 'w');

            // Add BOM for UTF-8
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($reportResult['data'] as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
            exit();
        } else {
            echo "❌ No data available for " . $reportType . " report<br>";
        }
    } catch (Exception $e) {
        echo "❌ Real CSV generation error: " . $e->getMessage() . "<br>";
    }
}

echo "<hr>";

// Test POST form simulation
echo "<h3>4. Form Submission Test</h3>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h4>POST Data Received:</h4>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";

    if (isset($_POST['test_report'])) {
        $reportType = $_POST['report_type'] ?? '';
        $exportFormat = $_POST['export_format'] ?? '';

        echo "<h4>Processing Test Report:</h4>";
        echo "Report Type: " . htmlspecialchars($reportType) . "<br>";
        echo "Export Format: " . htmlspecialchars($exportFormat) . "<br>";

        if ($reportType && $exportFormat === 'csv') {
            echo "✅ Form data looks correct for CSV generation<br>";
        } else {
            echo "❌ Missing or invalid form data<br>";
        }
    }
}

?>

<h4>Test Form Submission:</h4>
<form method="POST" style="background: #f8f9fa; padding: 20px; border-radius: 10px;">
    <div style="margin-bottom: 15px;">
        <label>Report Type:</label><br>
        <select name="report_type" style="padding: 8px; width: 200px;">
            <option value="">Select...</option>
            <option value="attendance">Attendance</option>
            <option value="events">Events</option>
            <option value="users">Users</option>
        </select>
    </div>

    <div style="margin-bottom: 15px;">
        <label>Export Format:</label><br>
        <select name="export_format" style="padding: 8px; width: 200px;">
            <option value="">Select...</option>
            <option value="csv">CSV</option>
            <option value="google_sheets">Google Sheets</option>
        </select>
    </div>

    <button type="submit" name="test_report" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">Test Form Submission</button>
</form>

<hr>

<h3>5. System Information</h3>
<?php
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Output Buffering: " . (ob_get_level() > 0 ? "Active (Level: " . ob_get_level() . ")" : "Inactive") . "<br>";
echo "Memory Limit: " . ini_get('memory_limit') . "<br>";
echo "Max Execution Time: " . ini_get('max_execution_time') . "<br>";
echo "Error Reporting Level: " . error_reporting() . "<br>";

// Check for common PHP extensions
$extensions = ['curl', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    echo "Extension $ext: " . (extension_loaded($ext) ? "✅ Loaded" : "❌ Not loaded") . "<br>";
}
?>

<hr>
<p><a href="reports.php" style="background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">← Back to Reports</a></p>

<?php

// Report generation functions (copied from reports.php for testing)
function generateAttendanceReport($startDate = null, $endDate = null)
{
    try {
        $supabase = getSupabaseClient();

        // Get attendance data with user and event information
        $attendance = $supabase->select('attendance', '*');

        if (!$attendance) {
            return ['success' => false, 'message' => 'No attendance data found'];
        }

        $reportData = [];
        $reportData[] = ['Date', 'Student ID', 'Student Name', 'Email', 'Course', 'Event Title', 'Attendance Type', 'Check In Time', 'Check Out Time', 'Duration (Hours)'];

        foreach ($attendance as $record) {
            // Get user details - handle both string and integer IDs
            $userId = $record['user_id_new'];
            $user = $supabase->select('users', '*', ['id' => $userId]);
            $userInfo = is_array($user) && !empty($user) ? $user[0] : null;

            // If user not found and ID is numeric, try casting to string
            if (!$userInfo && is_numeric($userId)) {
                $user = $supabase->select('users', '*', ['id' => (string)$userId]);
                $userInfo = is_array($user) && !empty($user) ? $user[0] : null;
            }

            // Get event details
            $event = $supabase->select('events', '*', ['id' => $record['event_id']]);
            $eventInfo = is_array($event) && !empty($event) ? $event[0] : null;

            if ($userInfo && $eventInfo) {
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
            }
        }

        return ['success' => true, 'data' => $reportData];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generating attendance report: ' . $e->getMessage()];
    }
}

function generateEventReport()
{
    try {
        $supabase = getSupabaseClient();
        $events = $supabase->select('events', '*');

        if (!$events) {
            return ['success' => false, 'message' => 'No events found'];
        }

        $reportData = [];
        $reportData[] = ['Event ID', 'Title', 'Description', 'Date', 'Start Time', 'End Time', 'Location', 'Max Attendees', 'Status', 'Total Attendance', 'Created Date'];

        foreach ($events as $event) {
            // Get attendance count for this event
            $attendance = $supabase->select('attendance', 'id', ['event_id' => $event['id']]);
            $attendanceCount = is_array($attendance) ? count($attendance) : 0;

            $reportData[] = [
                $event['id'],
                $event['title'],
                $event['description'],
                $event['event_date'],
                $event['start_time'],
                $event['end_time'],
                $event['location'],
                $event['max_attendees'] ?? 'Unlimited',
                $event['status'],
                $attendanceCount,
                $event['created_at']
            ];
        }

        return ['success' => true, 'data' => $reportData];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generating event report: ' . $e->getMessage()];
    }
}

function generateUserReport()
{
    try {
        $supabase = getSupabaseClient();
        $users = $supabase->select('users', '*');

        if (!$users) {
            return ['success' => false, 'message' => 'No users found'];
        }

        $reportData = [];
        $reportData[] = ['User ID', 'First Name', 'Last Name', 'Email', 'Course', 'Year Level', 'Section', 'Role', 'Total Events Attended', 'Registration Date'];

        foreach ($users as $user) {
            // Get attendance count for this user
            $attendance = $supabase->select('attendance', 'id', ['user_id_new' => $user['id']]);
            $attendanceCount = is_array($attendance) ? count($attendance) : 0;

            $reportData[] = [
                $user['id'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['course'],
                $user['year_level'],
                $user['section'],
                $user['role'],
                $attendanceCount,
                $user['created_at']
            ];
        }

        return ['success' => true, 'data' => $reportData];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error generating user report: ' . $e->getMessage()];
    }
}
?>