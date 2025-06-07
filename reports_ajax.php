<?php
session_start();
require_once 'supabase_config.php';
require_once 'google-api-manager.php';
require_once 'google-config.php';

// Set content type to JSON
header('Content-Type: application/json');

// Check if user is logged in and is admin
try {
    requireLogin();
    $user = getCurrentUser();
    if (!$user || $user['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied. Admin privileges required.']);
        exit();
    }
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required.']);
    exit();
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit();
}

// Check if required data is provided
if (!isset($_POST['report_type']) || !isset($_POST['export_format']) || !isset($_POST['generate_report'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters.']);
    exit();
}

$reportType = trim($_POST['report_type']);
$exportFormat = trim($_POST['export_format']);
$startDate = !empty($_POST['start_date']) ? trim($_POST['start_date']) : null;
$endDate = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;

// Log the request
error_log("AJAX Google export - Type: {$reportType}, Format: {$exportFormat}");

// Validate form data
if (empty($reportType)) {
    echo json_encode(['success' => false, 'message' => 'Error: Report type is required.']);
    exit();
}

if (empty($exportFormat) || !in_array($exportFormat, ['google_sheets', 'google_drive'])) {
    echo json_encode(['success' => false, 'message' => 'Error: Valid export format is required.']);
    exit();
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
        $reportData[] = ['Date', 'Student ID', 'Student Name', 'Email', 'Course', 'Event Title', 'Attendance Type', 'Check In Time', 'Check Out Time'];

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
                $recordDate = $checkInTime ? date('Y-m-d', strtotime($checkInTime)) : null;

                if ($startDate && $recordDate && $recordDate < $startDate) continue;
                if ($endDate && $recordDate && $recordDate > $endDate) continue;

                $checkOutTime = $record['check_out_time'] ?? '';

                $displayCheckInTime = '';
                $displayCheckOutTime = '';

                if ($record['attendance_type'] === 'check_out') {
                    $displayCheckOutTime = $checkOutTime ? date('Y-m-d H:i:s', strtotime($checkOutTime)) : '';
                } else {
                    $displayCheckInTime = $checkInTime ? date('Y-m-d H:i:s', strtotime($checkInTime)) : '';
                }

                $reportData[] = [
                    $recordDate ?: 'Unknown',
                    $userInfo['id'],
                    $userInfo['first_name'] . ' ' . $userInfo['last_name'],
                    $userInfo['email'],
                    $userInfo['course'],
                    $eventInfo['title'],
                    $record['attendance_type'] ?? 'check_in',
                    $displayCheckInTime,
                    $displayCheckOutTime
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
            $attendance = $supabase->select('attendance', 'attendance_type', ['event_id' => $event['id']]);

            $attendanceCount = 0;
            if (is_array($attendance)) {
                foreach ($attendance as $record) {
                    if (($record['attendance_type'] ?? 'check_in') === 'check_in') {
                        $attendanceCount++;
                    }
                }
            }

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

// Generate report data
switch ($reportType) {
    case 'attendance':
        $reportResult = generateAttendanceReport($startDate, $endDate);
        break;
    case 'events':
        $reportResult = generateEventReport();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid report type: ' . $reportType]);
        exit();
}

if (!$reportResult['success']) {
    echo json_encode(['success' => false, 'message' => $reportResult['message']]);
    exit();
}

$reportData = $reportResult['data'];

// Initialize Google API Manager
try {
    $googleAPI = new GoogleAPIManager();

    if (!$googleAPI->isConfigured()) {
        echo json_encode(['success' => false, 'message' => 'Google API is not configured. Please check your google-credentials.json file.']);
        exit();
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error initializing Google API: ' . $e->getMessage()]);
    exit();
}

// Process the export
try {
    global $GOOGLE_CONFIG;

    if ($exportFormat === 'google_sheets') {
        // Export to Google Sheets
        error_log("AJAX: Attempting Google Sheets export...");

        if ($GOOGLE_CONFIG['use_existing_spreadsheet'] && !empty($GOOGLE_CONFIG['attendance_spreadsheet_id'])) {
            $spreadsheetId = $GOOGLE_CONFIG['attendance_spreadsheet_id'];
            $sheetName = ($reportType === 'attendance') ? $GOOGLE_CONFIG['attendance_sheet_name'] : $GOOGLE_CONFIG['events_sheet_name'];
            $range = $sheetName . '!A1';
            $result = $googleAPI->updateExistingSpreadsheet($spreadsheetId, $reportData, $range);
        } else {
            $title = ucfirst($reportType) . ' Report - ' . date('Y-m-d H:i:s');
            $result = $googleAPI->createSpreadsheet($title, $reportData);
        }

        if ($result['success']) {
            $message = $result['message'];
            if (isset($result['url'])) {
                $message .= ' <a href="' . $result['url'] . '" target="_blank" style="color: #007bff; text-decoration: underline;">View Spreadsheet</a>';
            }
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    } elseif ($exportFormat === 'google_drive') {
        // Export to Google Drive as CSV
        error_log("AJAX: Attempting Google Drive export...");

        $filename = $reportType . '_report_' . date('Y-m-d_H-i-s') . '.csv';

        // Convert array to CSV string
        $csvContent = '';
        foreach ($reportData as $row) {
            $csvContent .= '"' . implode('","', str_replace('"', '""', $row)) . '"' . "\n";
        }

        if ($GOOGLE_CONFIG['use_existing_folder'] && !empty($GOOGLE_CONFIG['drive_folder_id'])) {
            $result = $googleAPI->uploadToExistingFolder($GOOGLE_CONFIG['drive_folder_id'], $filename, $csvContent, 'text/csv');
        } else {
            $result = $googleAPI->uploadToDrive($filename, $csvContent, 'text/csv');
        }

        if ($result['success']) {
            $message = $result['message'];
            if (isset($result['viewUrl'])) {
                $message .= ' <a href="' . $result['viewUrl'] . '" target="_blank" style="color: #007bff; text-decoration: underline;">View File</a>';
            }
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => $result['message']]);
        }
    }
} catch (Exception $e) {
    error_log("AJAX export error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error during export: ' . $e->getMessage()]);
}
