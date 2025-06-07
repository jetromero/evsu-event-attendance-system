<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

// Enable error reporting and logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>Reports POST Debug Tool</h1>";
echo "<h2>This mimics exactly what reports.php does</h2>";

$notification = '';
$notification_type = '';

// Copy the exact functions from reports.php
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

// Handle report generation and export (EXACTLY like reports.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>🔍 POST Request Received</h3>";
    echo "<pre>POST Data: " . htmlspecialchars(json_encode($_POST, JSON_PRETTY_PRINT)) . "</pre>";

    error_log("DEBUG POST request received: " . json_encode($_POST));

    if (isset($_POST['generate_report'])) {
        echo "<h4>✅ generate_report button found</h4>";

        $reportType = trim($_POST['report_type']);
        $exportFormat = trim($_POST['export_format']);
        $startDate = !empty($_POST['start_date']) ? trim($_POST['start_date']) : null;
        $endDate = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;

        echo "<p><strong>Report Type:</strong> " . htmlspecialchars($reportType) . "</p>";
        echo "<p><strong>Export Format:</strong> " . htmlspecialchars($exportFormat) . "</p>";
        echo "<p><strong>Start Date:</strong> " . htmlspecialchars($startDate ?: 'None') . "</p>";
        echo "<p><strong>End Date:</strong> " . htmlspecialchars($endDate ?: 'None') . "</p>";

        error_log("DEBUG Form data parsed - Type: {$reportType}, Format: {$exportFormat}");

        // Validate form data
        if (empty($reportType)) {
            $notification = 'Error: Report type is required.';
            $notification_type = 'error';
            echo "<div style='color: red;'>❌ Missing report type</div>";
            error_log("DEBUG Report generation failed: Missing report type");
        } elseif (empty($exportFormat)) {
            $notification = 'Error: Export format is required.';
            $notification_type = 'error';
            echo "<div style='color: red;'>❌ Missing export format</div>";
            error_log("DEBUG Report generation failed: Missing export format");
        } else {
            echo "<h4>✅ Form validation passed</h4>";
            error_log("DEBUG Report generation starting: type={$reportType}, format={$exportFormat}");

            $reportData = null;

            echo "<p>🔄 Generating report data...</p>";

            switch ($reportType) {
                case 'attendance':
                    $reportResult = generateAttendanceReport($startDate, $endDate);
                    break;
                case 'events':
                    $reportResult = generateEventReport();
                    break;
                case 'users':
                    $reportResult = generateUserReport();
                    break;
                default:
                    $reportResult = ['success' => false, 'message' => 'Invalid report type: ' . $reportType];
            }

            echo "<p><strong>Report Generation Result:</strong></p>";
            echo "<pre>" . htmlspecialchars(json_encode([
                'success' => $reportResult['success'],
                'data_count' => isset($reportResult['data']) ? count($reportResult['data']) : 0,
                'message' => $reportResult['message'] ?? 'No message'
            ], JSON_PRETTY_PRINT)) . "</pre>";

            error_log("DEBUG Report result: " . json_encode(['success' => $reportResult['success'], 'data_count' => isset($reportResult['data']) ? count($reportResult['data']) : 0]));

            if ($reportResult['success']) {
                $reportData = $reportResult['data'];
                echo "<h4>✅ Report data generated successfully</h4>";

                if ($exportFormat === 'csv') {
                    echo "<h4>🔄 Processing CSV export...</h4>";

                    // Check if we have data first
                    if (empty($reportData)) {
                        $notification = 'No data available for the selected report.';
                        $notification_type = 'warning';
                        echo "<div style='color: orange;'>⚠️ No data available for the selected report</div>";
                    } else {
                        echo "<p>✅ Data available: " . count($reportData) . " rows</p>";
                        echo "<p>🔄 Starting CSV generation...</p>";

                        // Show first few rows for debugging
                        echo "<h5>Sample Data (first 3 rows):</h5>";
                        echo "<pre>";
                        for ($i = 0; $i < min(3, count($reportData)); $i++) {
                            echo "Row $i: " . htmlspecialchars(json_encode($reportData[$i])) . "\n";
                        }
                        echo "</pre>";

                        // Check output buffering before starting
                        echo "<p><strong>Output buffer level before cleaning:</strong> " . ob_get_level() . "</p>";

                        // Generate CSV - Clean output buffer completely
                        while (ob_get_level()) {
                            echo "<p>🧹 Cleaning output buffer level: " . ob_get_level() . "</p>";
                            ob_end_clean();
                        }

                        echo "<p><strong>Output buffer level after cleaning:</strong> " . ob_get_level() . "</p>";

                        $filename = $reportType . '_report_' . date('Y-m-d_H-i-s') . '.csv';
                        echo "<p><strong>Filename:</strong> " . htmlspecialchars($filename) . "</p>";

                        try {
                            echo "<p>🔄 Setting headers...</p>";

                            // Set proper headers for CSV download
                            header('Content-Type: text/csv; charset=utf-8');
                            header('Content-Disposition: attachment; filename="' . $filename . '"');
                            header('Cache-Control: no-cache, must-revalidate');
                            header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

                            echo "<p>✅ Headers set successfully</p>";
                            echo "<p>🔄 Creating output stream...</p>";

                            // Create file pointer connected to the output stream
                            $output = fopen('php://output', 'w');

                            if ($output === false) {
                                throw new Exception('Unable to create output stream');
                            }

                            echo "<p>✅ Output stream created</p>";
                            echo "<p>🔄 Writing BOM...</p>";

                            // Add BOM for proper UTF-8 encoding in Excel
                            fwrite($output, "\xEF\xBB\xBF");

                            echo "<p>✅ BOM written</p>";
                            echo "<p>🔄 Writing CSV data...</p>";

                            // Write CSV data
                            $rowCount = 0;
                            foreach ($reportData as $row) {
                                if (fputcsv($output, $row) === false) {
                                    throw new Exception('Error writing CSV row ' . $rowCount);
                                }
                                $rowCount++;
                            }

                            echo "<p>✅ Data written: $rowCount rows</p>";
                            echo "<p>🔄 Closing output stream...</p>";

                            fclose($output);

                            echo "<p>✅ CSV generation completed successfully!</p>";
                            echo "<p>🚀 File should download now...</p>";

                            exit();
                        } catch (Exception $e) {
                            error_log("DEBUG CSV generation error: " . $e->getMessage());
                            $notification = 'Error generating CSV file: ' . $e->getMessage();
                            $notification_type = 'error';
                            echo "<div style='color: red;'>❌ CSV generation error: " . htmlspecialchars($e->getMessage()) . "</div>";
                        }
                    }
                } else {
                    echo "<h4>ℹ️ Non-CSV export format selected</h4>";
                    $notification = 'Export format "' . $exportFormat . '" selected (not CSV)';
                    $notification_type = 'info';
                }
            } else {
                echo "<div style='color: red;'>❌ Report generation failed: " . htmlspecialchars($reportResult['message']) . "</div>";
                $notification = $reportResult['message'];
                $notification_type = 'error';
            }
        }
    } else {
        echo "<div style='color: red;'>❌ generate_report button not found in POST data</div>";
    }
} else {
    echo "<h3>ℹ️ No POST request received yet</h3>";
}

// Display notification if any
if (!empty($notification)) {
    echo "<div style='padding: 15px; margin: 20px 0; border-radius: 5px; background: " .
        ($notification_type === 'error' ? '#f8d7da; color: #721c24; border: 1px solid #f5c6cb;' : ($notification_type === 'warning' ? '#fff3cd; color: #856404; border: 1px solid #ffeaa7;' :
                '#d4edda; color: #155724; border: 1px solid #c3e6cb;')) . "'>";
    echo htmlspecialchars($notification);
    echo "</div>";
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Reports POST Debug</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 40px;
            line-height: 1.6;
        }

        .form-container {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 10px;
            margin: 30px 0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }

        select,
        input {
            padding: 10px;
            width: 100%;
            max-width: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }

        button {
            background: #007bff;
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }

        button:hover {
            background: #0056b3;
        }

        .debug-info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
        }

        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
        }
    </style>
</head>

<body>
    <h1>Reports POST Debug Tool</h1>

    <div class="debug-info">
        <strong>Current Status:</strong> This tool exactly mimics the reports.php form submission process.
        Fill out the form below and submit to see detailed debugging information.
    </div>

    <div class="form-container">
        <h2>Test Report Generation</h2>
        <form method="POST" action="">
            <div class="form-group">
                <label for="report_type">Report Type:</label>
                <select name="report_type" id="report_type" required>
                    <option value="">Select report type...</option>
                    <option value="attendance" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] === 'attendance') ? 'selected' : ''; ?>>Attendance Report</option>
                    <option value="events" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] === 'events') ? 'selected' : ''; ?>>Events Report</option>
                    <option value="users" <?php echo (isset($_POST['report_type']) && $_POST['report_type'] === 'users') ? 'selected' : ''; ?>>Users Report</option>
                </select>
            </div>

            <div class="form-group">
                <label for="export_format">Export Format:</label>
                <select name="export_format" id="export_format" required>
                    <option value="">Select export format...</option>
                    <option value="csv" <?php echo (isset($_POST['export_format']) && $_POST['export_format'] === 'csv') ? 'selected' : ''; ?>>CSV Download</option>
                    <option value="google_sheets" <?php echo (isset($_POST['export_format']) && $_POST['export_format'] === 'google_sheets') ? 'selected' : ''; ?>>Google Sheets</option>
                </select>
            </div>

            <div class="form-group">
                <label for="start_date">Start Date (Optional):</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo $_POST['start_date'] ?? ''; ?>">
            </div>

            <div class="form-group">
                <label for="end_date">End Date (Optional):</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo $_POST['end_date'] ?? ''; ?>">
            </div>

            <button type="submit" name="generate_report">Generate Report (Debug)</button>
        </form>
    </div>

    <div class="debug-info">
        <h3>System Information</h3>
        <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?><br>
        <strong>Output Buffering:</strong> <?php echo ob_get_level() > 0 ? 'Active (Level: ' . ob_get_level() . ')' : 'Inactive'; ?><br>
        <strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?><br>
        <strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?><br>
        <strong>Headers Sent:</strong> <?php echo headers_sent() ? 'Yes' : 'No'; ?><br>
    </div>

    <hr>
    <p><a href="reports.php" style="color: #007bff;">← Back to Reports</a></p>

</body>

</html>