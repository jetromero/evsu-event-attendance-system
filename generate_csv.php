<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    die('Access denied. Admin privileges required.');
}

// Enable error logging but disable display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Clear any existing output
while (ob_get_level()) {
    ob_end_clean();
}

// Validate required parameters
if (!isset($_POST['report_type']) || !isset($_POST['export_format'])) {
    http_response_code(400);
    die('Missing required parameters');
}

$reportType = trim($_POST['report_type']);
$exportFormat = trim($_POST['export_format']);
$startDate = !empty($_POST['start_date']) ? trim($_POST['start_date']) : null;
$endDate = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;

// Log the request
error_log("CSV Generation Request: type={$reportType}, format={$exportFormat}");

// Only handle CSV exports
if ($exportFormat !== 'csv') {
    http_response_code(400);
    die('This endpoint only handles CSV exports');
}

// Report Generation Functions
function generateAttendanceReport($startDate = null, $endDate = null)
{
    try {
        $supabase = getSupabaseClient();
        $attendance = $supabase->select('attendance', '*');

        if (!$attendance) {
            return ['success' => false, 'message' => 'No attendance data found'];
        }

        $reportData = [];
        $reportData[] = ['Date', 'Student ID', 'Student Name', 'Email', 'Course', 'Event Title', 'Attendance Type', 'Check In Time', 'Check Out Time', 'Duration (Hours)'];

        foreach ($attendance as $record) {
            $userId = $record['user_id_new'];
            $user = $supabase->select('users', '*', ['id' => $userId]);
            $userInfo = is_array($user) && !empty($user) ? $user[0] : null;

            if (!$userInfo && is_numeric($userId)) {
                $user = $supabase->select('users', '*', ['id' => (string)$userId]);
                $userInfo = is_array($user) && !empty($user) ? $user[0] : null;
            }

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

        return ['success' => true, 'data' => $reportData];
    } catch (Exception $e) {
        error_log("Attendance report error: " . $e->getMessage());
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
        error_log("Event report error: " . $e->getMessage());
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
        error_log("User report error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error generating user report: ' . $e->getMessage()];
    }
}

// Generate the report data
$reportResult = null;
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
        http_response_code(400);
        die('Invalid report type: ' . $reportType);
}

// Check if report generation was successful
if (!$reportResult['success']) {
    error_log("Report generation failed: " . $reportResult['message']);
    http_response_code(500);
    die('Report generation failed: ' . $reportResult['message']);
}

$reportData = $reportResult['data'];

// Check if we have data
if (empty($reportData)) {
    http_response_code(204);
    die('No data available for the selected report');
}

// Generate CSV
try {
    $filename = $reportType . '_report_' . date('Y-m-d_H-i-s') . '.csv';

    // Set proper headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    // Create file pointer connected to the output stream
    $output = fopen('php://output', 'w');

    if ($output === false) {
        throw new Exception('Unable to create output stream');
    }

    // Add BOM for proper UTF-8 encoding in Excel
    fwrite($output, "\xEF\xBB\xBF");

    // Write CSV data
    foreach ($reportData as $row) {
        if (fputcsv($output, $row) === false) {
            throw new Exception('Error writing CSV row');
        }
    }

    fclose($output);
    error_log("CSV generation successful: {$filename}");
} catch (Exception $e) {
    error_log("CSV generation error: " . $e->getMessage());
    http_response_code(500);
    die('Error generating CSV file: ' . $e->getMessage());
}
