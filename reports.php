<?php
session_start();
require_once 'supabase_config.php';
require_once 'vendor/autoload.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Access denied. Admin privileges required.'];
    header('Location: dashboard.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    logoutUser();
    header('Location: index.php');
    exit();
}

// Handle CSV download FIRST - before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_report']) && $_POST['export_format'] === 'csv') {
    error_log("CSV download initiated - POST data: " . json_encode($_POST));

    $reportType = trim($_POST['report_type']);
    $startDate = !empty($_POST['start_date']) ? trim($_POST['start_date']) : null;
    $endDate = !empty($_POST['end_date']) ? trim($_POST['end_date']) : null;

    error_log("CSV download - Report type: $reportType, Start: $startDate, End: $endDate");

    // Generate report data
    if ($reportType === 'attendance') {
        $reportResult = generateAttendanceReportForCSV($startDate, $endDate);
    } elseif ($reportType === 'events') {
        $reportResult = generateEventReportForCSV();
    } else {
        $reportResult = ['success' => false, 'message' => 'Invalid report type'];
    }

    error_log("CSV download - Report result: " . json_encode(['success' => $reportResult['success'], 'data_count' => isset($reportResult['data']) ? count($reportResult['data']) : 0]));

    if ($reportResult['success']) {
        $filename = $reportType . '_report_' . date('Y-m-d_H-i-s') . '.csv';

        // Clear any previous output
        if (ob_get_level()) {
            ob_end_clean();
        }

        error_log("CSV download - Setting headers for file: $filename");

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

        // Create output stream
        $output = fopen('php://output', 'w');

        // Add UTF-8 BOM for proper Excel encoding
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write CSV data
        foreach ($reportResult['data'] as $row) {
            fputcsv($output, $row);
        }

        fclose($output);
        error_log("CSV download - File output completed");
        exit();
    } else {
        error_log("CSV download failed - " . ($reportResult['message'] ?? 'Unknown error'));
    }
}

$notification = '';
$notification_type = '';

// Check for session notifications first
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification']['message'];
    $notification_type = $_SESSION['notification']['type'];
    unset($_SESSION['notification']); // Clear after use
}

// Include Google API Manager and Configuration
require_once 'google-api-manager.php';
require_once 'google-config.php';

// Initialize Google API Manager
function getGoogleAPIManager()
{
    return new GoogleAPIManager();
}

// CSV-specific Report Generation Functions (simplified for CSV download)
function generateAttendanceReportForCSV($startDate = null, $endDate = null)
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

                // Calculate duration in minutes and seconds
                $durationFormatted = '';
                if ($checkInTime && $checkOutTime) {
                    $start = strtotime($checkInTime);
                    $end = strtotime($checkOutTime);
                    if ($start && $end && $end > $start) {
                        $totalSeconds = $end - $start;
                        $minutes = floor($totalSeconds / 60);
                        $seconds = $totalSeconds % 60;
                        $durationFormatted = $minutes . ':' . sprintf('%02d', $seconds);
                    }
                }

                // Logic for displaying times based on attendance type
                $displayCheckInTime = '';
                $displayCheckOutTime = '';

                if ($record['attendance_type'] === 'check_out') {
                    // For check-out records, only show check-out time
                    $displayCheckOutTime = $checkOutTime ? date('Y-m-d H:i:s', strtotime($checkOutTime)) : '';
                } else {
                    // For check-in records, only show check-in time
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

function generateEventReportForCSV()
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
            // Get all attendance records for this event
            $attendance = $supabase->select('attendance', 'attendance_type', ['event_id' => $event['id']]);

            // Count only check-in records
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

// Report Generation Functions (for Google exports)
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
        $reportData[] = ['Date', 'Student ID', 'Student Name', 'Email', 'Course', 'Event Title', 'Attendance Type', 'Check In Time', 'Check Out Time'];

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

            // Debug logging for missing data
            if (!$userInfo) {
                error_log("User not found for ID: " . $userId . " (type: " . gettype($userId) . ")");
            }
            if (!$eventInfo) {
                error_log("Event not found for ID: " . $record['event_id']);
            }

            if ($userInfo && $eventInfo) {
                // Apply date filtering if provided
                $checkInTime = $record['check_in_time'] ?? $record['attendance_date'];
                $recordDate = $checkInTime ? date('Y-m-d', strtotime($checkInTime)) : null;

                // Skip if outside date range
                if ($startDate && $recordDate && $recordDate < $startDate) continue;
                if ($endDate && $recordDate && $recordDate > $endDate) continue;

                $checkOutTime = $record['check_out_time'] ?? '';

                // Calculate duration in minutes and seconds
                $durationFormatted = '';
                if ($checkInTime && $checkOutTime) {
                    $start = strtotime($checkInTime);
                    $end = strtotime($checkOutTime);
                    if ($start && $end && $end > $start) {
                        $totalSeconds = $end - $start;
                        $minutes = floor($totalSeconds / 60);
                        $seconds = $totalSeconds % 60;
                        $durationFormatted = $minutes . ':' . sprintf('%02d', $seconds);
                    }
                }

                // Logic for displaying times based on attendance type
                $displayCheckInTime = '';
                $displayCheckOutTime = '';

                if ($record['attendance_type'] === 'check_out') {
                    // For check-out records, only show check-out time
                    $displayCheckOutTime = $checkOutTime ? date('Y-m-d H:i:s', strtotime($checkOutTime)) : '';
                } else {
                    // For check-in records, only show check-in time
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
            } else {
                // Log when data is skipped
                error_log("Skipping attendance record - User found: " . ($userInfo ? 'Yes' : 'No') . ", Event found: " . ($eventInfo ? 'Yes' : 'No'));
            }
        }

        // Debug: Log final report data count
        error_log("Attendance report generated with " . count($reportData) . " rows (including header)");

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
            // Get all attendance records for this event
            $attendance = $supabase->select('attendance', 'attendance_type', ['event_id' => $event['id']]);

            // Count only check-in records
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

// Google exports are now handled by AJAX (reports_ajax.php) to prevent page refresh
// Only handle CSV downloads here since they require direct file output
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - EVSU Event Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--body-font);
            background-color: var(--body-color);
            color: var(--text-color);
            line-height: 1.6;
        }

        /* Header */
        .header {
            background: linear-gradient(135deg, var(--first-color) 0%, var(--first-color-alt) 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .header__container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header__logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header__logo img {
            width: 3rem;
            height: 3rem;
            object-fit: contain;
        }

        .header__title {
            font-size: 1.5rem;
            font-weight: var(--font-semi-bold);
        }

        .header__nav ul {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .header__nav a {
            color: white;
            text-decoration: none;
            font-weight: var(--font-medium);
            transition: opacity 0.3s;
        }

        .header__nav a:hover {
            opacity: 0.8;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        /* Main Content */
        .main {
            min-height: calc(100vh - 120px);
            padding: 2rem 0;
        }

        .main__container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .page__header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .page__title {
            font-size: 2rem;
            color: var(--title-color);
            font-weight: var(--font-semi-bold);
            margin-bottom: 0.5rem;
        }

        .page__subtitle {
            color: var(--text-color);
            font-size: 1rem;
        }

        /* Report Cards */
        .reports__card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .card__header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .card__icon {
            background: var(--first-color);
            color: white;
            width: 3rem;
            height: 3rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .card__title {
            font-size: 1.5rem;
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
        }

        .form__grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form__group {
            position: relative;
        }

        .form__label {
            display: block;
            font-weight: var(--font-medium);
            color: var(--title-color);
            margin-bottom: 0.5rem;
        }

        .form__input,
        .form__select {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            font-family: var(--body-font);
            font-size: var(--normal-font-size);
            transition: border-color 0.3s;
        }

        .form__input:focus,
        .form__select:focus {
            outline: none;
            border-color: var(--first-color);
        }

        .form__select {
            appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23a0a0a0' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6,9 12,15 18,9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 1rem center;
            background-size: 1rem;
            cursor: pointer;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 0.5rem;
            font-family: var(--body-font);
            font-size: var(--normal-font-size);
            font-weight: var(--font-medium);
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn--primary {
            background: var(--first-color);
            color: white;
        }

        .btn--primary:hover {
            background: var(--first-color-alt);
            transform: translateY(-2px);
        }

        .btn--secondary {
            background: #6c757d;
            color: white;
        }

        .btn--secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .form__buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        /* Notification */
        .notification {
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .notification--success {
            background: linear-gradient(135deg, #e6ffed 0%, #d3f9d8 100%);
            color: #2b8a3e;
            border: 1px solid #69db7c;
        }

        .notification--error {
            background: linear-gradient(135deg, #ffe0e0 0%, #ffc9c9 100%);
            color: #c92a2a;
            border: 1px solid #ff6b6b;
        }

        /* Report Options */
        .report-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .report-option {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
        }

        .report-option:hover {
            border-color: var(--first-color);
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .report-option.selected {
            border-color: var(--first-color);
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .report-option__icon {
            font-size: 2.5rem;
            color: var(--first-color);
            margin-bottom: 1rem;
        }

        .report-option__title {
            font-size: 1.25rem;
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
            margin-bottom: 0.5rem;
        }

        .report-option__description {
            color: var(--text-color);
            font-size: 0.9rem;
        }

        /* Google API Info */
        .api-info {
            background: #e3f2fd;
            border: 1px solid #42a5f5;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .api-info__title {
            color: #1976d2;
            font-weight: var(--font-semi-bold);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .api-info__content {
            color: #1976d2;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        /* Footer */
        .footer {
            background: var(--container-color);
            text-align: center;
            padding: 2rem 0;
            border-top: 1px solid #e0e0e0;
        }

        .footer__text {
            color: var(--text-color);
            margin-bottom: 0.5rem;
        }

        .footer__copy {
            font-size: 0.9rem;
            color: var(--text-color);
            opacity: 0.8;
        }

        /* Responsive Design */
        @media screen and (max-width: 768px) {
            .header__container {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .header__nav ul {
                gap: 1rem;
                flex-wrap: wrap;
                justify-content: center;
            }

            .form__grid {
                grid-template-columns: 1fr;
            }

            .form__buttons {
                flex-direction: column;
            }

            .report-options {
                grid-template-columns: 1fr;
            }

            .page__title {
                font-size: 1.5rem;
            }
        }
    </style>
</head>

<body>
    <!-- Header -->
    <header class="header">
        <div class="header__container">
            <div class="header__logo">
                <img src="assets/img/evsu-logo.png" alt="EVSU Logo">
                <div>
                    <h2 class="header__title">EVSU Event Attendance</h2>
                    <p>Admin - Reports</p>
                </div>
            </div>
            <nav class="header__nav">
                <ul>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="events.php">Events</a></li>
                    <li><a href="qr_scanner.php">QR Scanner</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li><a href="?logout=1" class="logout-btn">
                            <i class="ri-logout-box-line"></i> Logout
                        </a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="main__container">
            <!-- Page Header -->
            <div class="page__header">
                <h1 class="page__title">Reports & Analytics</h1>
                <p class="page__subtitle">Generate and export comprehensive reports with Google Sheets & Drive integration</p>
            </div>

            <!-- Notification -->
            <?php if (!empty($notification)): ?>
                <div class="notification notification--<?php echo $notification_type; ?>">
                    <i class="ri-<?php echo $notification_type === 'success' ? 'check' : 'error-warning'; ?>-line"></i>
                    <?php echo $notification; ?>
                </div>
            <?php endif; ?>

            <!-- Report Generator -->
            <div class="reports__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-file-chart-line"></i>
                    </div>
                    <h2 class="card__title">Generate Reports</h2>
                </div>

                <form method="POST" action="">
                    <!-- Report Type Selection -->
                    <div class="form__group">
                        <label class="form__label">Select Report Type</label>
                        <div class="report-options">
                            <div class="report-option" data-type="attendance">
                                <div class="report-option__icon">
                                    <i class="ri-user-check-line"></i>
                                </div>
                                <div class="report-option__title">Attendance Report</div>
                                <div class="report-option__description">Complete attendance data with check-in/out times and duration</div>
                            </div>
                            <div class="report-option" data-type="events">
                                <div class="report-option__icon">
                                    <i class="ri-calendar-event-line"></i>
                                </div>
                                <div class="report-option__title">Events Report</div>
                                <div class="report-option__description">All events with details and attendance statistics</div>
                            </div>
                        </div>
                        <input type="hidden" id="report_type" name="report_type" value="">
                    </div>

                    <div class="form__grid">
                        <!-- Date Range (for attendance reports) -->
                        <div class="form__group" id="date_range_group" style="display: none;">
                            <label for="start_date" class="form__label">Start Date (Optional)</label>
                            <input type="date" id="start_date" name="start_date" class="form__input">
                        </div>

                        <div class="form__group" id="end_date_group" style="display: none;">
                            <label for="end_date" class="form__label">End Date (Optional)</label>
                            <input type="date" id="end_date" name="end_date" class="form__input">
                        </div>

                        <!-- Export Format -->
                        <div class="form__group">
                            <label for="export_format" class="form__label">Export Format</label>
                            <select id="export_format" name="export_format" class="form__select" required>
                                <option value="">Choose export format...</option>
                                <option value="csv">📊 Download CSV</option>
                                <option value="google_sheets">📈 Google Sheets</option>
                                <option value="google_drive">💾 Google Drive (CSV)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form__buttons">
                        <button type="submit" name="generate_report" class="btn btn--primary" id="generate_btn" disabled>
                            <i class="ri-file-download-line"></i>
                            Generate Report
                        </button>
                        <a href="admin_dashboard.php" class="btn btn--secondary">
                            <i class="ri-arrow-left-line"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>


        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer__container">
            <p class="footer__text">Eastern Visayas State University</p>
            <p class="footer__copy">&copy; 2024 EVSU Event Attendance Management System. All rights reserved.</p>
        </div>
    </footer>

    <script>
        // Report type selection
        document.addEventListener('DOMContentLoaded', function() {
            const reportOptions = document.querySelectorAll('.report-option');
            const reportTypeInput = document.getElementById('report_type');
            const generateBtn = document.getElementById('generate_btn');
            const exportFormat = document.getElementById('export_format');
            const dateRangeGroup = document.getElementById('date_range_group');
            const endDateGroup = document.getElementById('end_date_group');

            reportOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    reportOptions.forEach(opt => opt.classList.remove('selected'));

                    // Add selected class to clicked option
                    this.classList.add('selected');

                    // Set report type value
                    const reportType = this.getAttribute('data-type');
                    reportTypeInput.value = reportType;

                    // Show/hide date range for attendance reports
                    if (reportType === 'attendance') {
                        dateRangeGroup.style.display = 'block';
                        endDateGroup.style.display = 'block';
                    } else {
                        dateRangeGroup.style.display = 'none';
                        endDateGroup.style.display = 'none';
                    }

                    // Check if we can enable generate button
                    checkGenerateButton();
                });
            });

            exportFormat.addEventListener('change', checkGenerateButton);

            function checkGenerateButton() {
                const reportType = reportTypeInput.value;
                const exportFormatValue = exportFormat.value;

                if (reportType && exportFormatValue) {
                    generateBtn.disabled = false;
                } else {
                    generateBtn.disabled = true;
                }
            }

            // Update button text based on export format
            exportFormat.addEventListener('change', function() {
                const format = this.value;
                const btn = generateBtn;

                if (format === 'csv') {
                    btn.innerHTML = '<i class="ri-download-line"></i> Download CSV';
                } else if (format === 'google_sheets') {
                    btn.innerHTML = '<i class="ri-google-line"></i> Export to Google Sheets';
                } else if (format === 'google_drive') {
                    btn.innerHTML = '<i class="ri-cloud-line"></i> Save to Google Drive';
                } else {
                    btn.innerHTML = '<i class="ri-file-download-line"></i> Generate Report';
                }
            });
        });

        // Form validation and AJAX submission for Google exports
        document.querySelector('form').addEventListener('submit', function(e) {
            const reportType = document.getElementById('report_type').value;
            const exportFormat = document.getElementById('export_format').value;

            console.log('Form submitted - Report Type:', reportType, 'Export Format:', exportFormat);

            if (!reportType) {
                e.preventDefault();
                alert('Please select a report type.');
                return false;
            }

            if (!exportFormat) {
                e.preventDefault();
                alert('Please select an export format.');
                return false;
            }

            // For CSV downloads, allow immediate submission without interference
            if (exportFormat === 'csv') {
                console.log('CSV download initiated - allowing form submission');
                return true; // Allow form to submit normally
            }

            // For Google exports, prevent form submission and use AJAX
            if (exportFormat === 'google_sheets' || exportFormat === 'google_drive') {
                e.preventDefault();
                console.log('Google export initiated - using AJAX');

                const submitBtn = document.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="ri-loader-4-line"></i> Generating...';
                submitBtn.disabled = true;

                // Prepare form data
                const formData = new FormData();
                formData.append('report_type', reportType);
                formData.append('export_format', exportFormat);
                formData.append('generate_report', '');

                // Get date range if applicable
                const startDate = document.getElementById('start_date').value;
                const endDate = document.getElementById('end_date').value;
                if (startDate) formData.append('start_date', startDate);
                if (endDate) formData.append('end_date', endDate);

                // Send AJAX request
                fetch('reports_ajax.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        // Reset button
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;

                        // Show notification
                        showNotification(data.message, data.success ? 'success' : 'error');
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Reset button
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;

                        showNotification('An error occurred while generating the report.', 'error');
                    });

                return false; // Prevent form submission
            }

            // Allow form to submit for other cases
            return true;
        });

        // Function to show notifications
        function showNotification(message, type) {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(n => n.remove());

            // Create new notification
            const notification = document.createElement('div');
            notification.className = `notification notification--${type}`;
            notification.innerHTML = `
                <i class="ri-${type === 'success' ? 'check' : 'error-warning'}-line"></i>
                ${message}
            `;

            // Insert notification before the reports card
            const reportsCard = document.querySelector('.reports__card');
            reportsCard.parentNode.insertBefore(notification, reportsCard);

            // Auto-hide notification after 10 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 10000);
        }
    </script>
</body>

</html>