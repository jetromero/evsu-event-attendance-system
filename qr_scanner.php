<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in
requireLogin();

// Get current user data
$user = getCurrentUser();
if (!$user) {
    header('Location: index.php');
    exit();
}

// Check if user is admin
if (!isset($user['role']) || $user['role'] !== 'admin') {
    // Redirect non-admin users to dashboard with error message
    $_SESSION['notification'] = [
        'type' => 'error',
        'message' => 'Access denied. Administrator privileges required.'
    ];
    header('Location: dashboard.php');
    exit();
}

$notification = '';
$notification_type = '';

// Set timezone for proper time handling
date_default_timezone_set('Asia/Manila');

// Handle QR code scanning and attendance recording
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['qr_data'])) {
    $qr_data = trim($_POST['qr_data']);
    $event_id = trim($_POST['event_id']);
    $attendance_type = trim($_POST['attendance_type']); // 'check_in' or 'check_out'

    try {
        // Decode QR data
        $student_data = json_decode($qr_data, true);

        if (!$student_data || !isset($student_data['student_id'])) {
            $notification = 'Invalid QR code format.';
            $notification_type = 'error';
        } elseif (empty($event_id)) {
            $notification = 'Please select an event.';
            $notification_type = 'error';
        } elseif (empty($attendance_type)) {
            $notification = 'Please select attendance type (Time In or Time Out).';
            $notification_type = 'error';
        } else {
            $supabase = getSupabaseClient();

            // Check if student exists (student_id is now the serial ID)
            $student = $supabase->select('users', '*', ['id' => $student_data['student_id']]);

            if (!$student) {
                $notification = 'Student not found in system.';
                $notification_type = 'error';
            } else {
                $student_name = $student[0]['first_name'] . ' ' . $student[0]['last_name'];

                if ($attendance_type === 'check_in') {
                    // Check if student already checked in
                    $existing_checkin = $supabase->select('attendance', '*', [
                        'user_id_new' => $student_data['student_id'],
                        'event_id' => $event_id,
                        'attendance_type' => 'check_in'
                    ]);

                    if ($existing_checkin) {
                        // Get the check-in time and convert from UTC to Philippine time for display
                        $checkin_time_raw = $existing_checkin[0]['check_in_time'];

                        // Handle different time formats from database
                        if ($checkin_time_raw) {
                            // Create DateTime object from UTC time and convert to Philippine time
                            try {
                                $checkin_datetime = new DateTime($checkin_time_raw, new DateTimeZone('UTC'));
                                $checkin_datetime->setTimezone(new DateTimeZone('Asia/Manila'));
                                $checkin_time = $checkin_datetime->format('g:i A');
                            } catch (Exception $e) {
                                // Fallback: try simple strtotime conversion
                                $checkin_timestamp = strtotime($checkin_time_raw);
                                if ($checkin_timestamp !== false) {
                                    $checkin_time = date('g:i A', $checkin_timestamp);
                                } else {
                                    $checkin_time = 'Unknown time';
                                }
                            }
                        } else {
                            $checkin_time = 'Unknown time';
                        }

                        $notification = "Student already checked in at $checkin_time. Use Time Out to record departure.";
                        $notification_type = 'warning';
                    } else {
                        // Record check-in with proper timezone - store as UTC
                        $current_datetime = new DateTime('now', new DateTimeZone('Asia/Manila'));
                        $current_datetime->setTimezone(new DateTimeZone('UTC'));
                        $current_time_utc = $current_datetime->format('Y-m-d H:i:s');

                        $attendance_data = [
                            'user_id_new' => $student_data['student_id'],
                            'event_id' => $event_id,
                            'attendance_type' => 'check_in',
                            'check_in_time' => $current_time_utc,
                            'attendance_date' => $current_time_utc,
                            'check_in_method' => 'qr_code',
                            'notes' => 'Checked in via QR scanner by ' . $user['first_name'] . ' ' . $user['last_name']
                        ];

                        $result = $supabase->insert('attendance', $attendance_data);

                        if ($result) {
                            $notification = "TIME IN recorded for: $student_name at " . date('g:i A');
                            $notification_type = 'success';
                        } else {
                            $notification = 'Failed to record check-in. Please try again.';
                            $notification_type = 'error';
                        }
                    }
                } elseif ($attendance_type === 'check_out') {
                    // Check if student has checked in first
                    $existing_checkin = $supabase->select('attendance', '*', [
                        'user_id_new' => $student_data['student_id'],
                        'event_id' => $event_id,
                        'attendance_type' => 'check_in'
                    ]);

                    if (!$existing_checkin) {
                        $notification = "Cannot check out: $student_name has not checked in yet.";
                        $notification_type = 'error';
                    } else {
                        // Check if already checked out
                        $existing_checkout = $supabase->select('attendance', '*', [
                            'user_id_new' => $student_data['student_id'],
                            'event_id' => $event_id,
                            'attendance_type' => 'check_out'
                        ]);

                        if ($existing_checkout) {
                            // Get the check-out time and convert from UTC to Philippine time for display
                            $checkout_time_raw = $existing_checkout[0]['check_out_time'];

                            // Handle different time formats from database
                            if ($checkout_time_raw) {
                                // Create DateTime object from UTC time and convert to Philippine time
                                try {
                                    $checkout_datetime = new DateTime($checkout_time_raw, new DateTimeZone('UTC'));
                                    $checkout_datetime->setTimezone(new DateTimeZone('Asia/Manila'));
                                    $checkout_time = $checkout_datetime->format('g:i A');
                                } catch (Exception $e) {
                                    // Fallback: try simple strtotime conversion
                                    $checkout_timestamp = strtotime($checkout_time_raw);
                                    if ($checkout_timestamp !== false) {
                                        $checkout_time = date('g:i A', $checkout_timestamp);
                                    } else {
                                        $checkout_time = 'Unknown time';
                                    }
                                }
                            } else {
                                $checkout_time = 'Unknown time';
                            }

                            $notification = "Student already checked out at $checkout_time.";
                            $notification_type = 'warning';
                        } else {
                            // Record check-out with proper timezone - store as UTC
                            $checkin_time = $existing_checkin[0]['check_in_time'];
                            $checkout_datetime = new DateTime('now', new DateTimeZone('Asia/Manila'));
                            $checkout_datetime->setTimezone(new DateTimeZone('UTC'));
                            $checkout_time_utc = $checkout_datetime->format('Y-m-d H:i:s');

                            // Calculate duration - handle potential timezone issues
                            $checkin_timestamp = strtotime($checkin_time);
                            $checkout_timestamp = time();

                            if ($checkin_timestamp !== false) {
                                $duration = ($checkout_timestamp - $checkin_timestamp) / 3600; // hours
                            } else {
                                $duration = 0; // Fallback if time parsing fails
                            }

                            $attendance_data = [
                                'user_id_new' => $student_data['student_id'],
                                'event_id' => $event_id,
                                'attendance_type' => 'check_out',
                                'check_out_time' => $checkout_time_utc,
                                'attendance_date' => $checkout_time_utc,
                                'check_in_method' => 'qr_code',
                                'notes' => 'Checked out via QR scanner by ' . $user['first_name'] . ' ' . $user['last_name'] .
                                    ". Duration: " . round($duration, 2) . " hours"
                            ];

                            $result = $supabase->insert('attendance', $attendance_data);

                            if ($result) {
                                // Convert check-in time from UTC to Philippine time for display
                                try {
                                    $checkin_datetime = new DateTime($checkin_time, new DateTimeZone('UTC'));
                                    $checkin_datetime->setTimezone(new DateTimeZone('Asia/Manila'));
                                    $checkin_display = $checkin_datetime->format('g:i A');
                                } catch (Exception $e) {
                                    $checkin_display = ($checkin_timestamp !== false) ? date('g:i A', $checkin_timestamp) : 'Unknown';
                                }

                                $checkout_display = date('g:i A');
                                $duration_display = round($duration, 1);

                                $notification = "TIME OUT recorded for: $student_name at $checkout_display. " .
                                    "Duration: $duration_display hours (In: $checkin_display)";
                                $notification_type = 'success';
                            } else {
                                $notification = 'Failed to record check-out. Please try again.';
                                $notification_type = 'error';
                            }
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("QR Scanner error: " . $e->getMessage());
        error_log("QR Scanner error trace: " . $e->getTraceAsString());

        // Temporary debug information (remove this in production)
        $notification = 'Debug Error: ' . $e->getMessage() . ' | QR Data: ' . substr($qr_data, 0, 100) . '... | Event ID: ' . $event_id . ' | Type: ' . $attendance_type;
        $notification_type = 'error';
    }
}

// Get active events for selection (optimized)
function getActiveEvents()
{
    try {
        // Use cached data if available and recent (5 minutes)
        if (isset($_SESSION['todays_events']) && (time() - $_SESSION['todays_events_time']) < 300) {
            return $_SESSION['todays_events'];
        }

        $supabase = getSupabaseClient();
        $today = date('Y-m-d');

        // Get only essential fields and filter by today's date for better performance
        $events = $supabase->select('events', 'id, title, event_date, start_time, end_time, status', []);

        if (!$events) {
            error_log("No events returned from database");
            return [];
        }

        // Filter events to only show today's events
        $todaysEvents = array_filter($events, function ($event) use ($today) {
            return $event['event_date'] === $today;
        });

        // Sort by start time (earliest first) for today's events
        usort($todaysEvents, function ($a, $b) {
            return strtotime($a['start_time']) - strtotime($b['start_time']);
        });

        // Cache the results
        $_SESSION['todays_events'] = $todaysEvents;
        $_SESSION['todays_events_time'] = time();

        return $todaysEvents;
    } catch (Exception $e) {
        error_log("Error getting events: " . $e->getMessage());
        return [];
    }
}

$activeEvents = getActiveEvents();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Scanner - EVSU Event Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <!-- jsQR Library for QR Code Scanning -->
    <script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
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

        /* Admin Dropdown */
        .admin-dropdown {
            position: relative;
            display: inline-block;
        }

        .admin-dropdown-btn {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: background-color 0.3s;
            color: white;
            text-decoration: none;
            font-weight: var(--font-medium);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
            border: none;
            font-family: inherit;
            font-size: inherit;
        }

        .admin-dropdown-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .admin-dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: white;
            min-width: 200px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
            z-index: 1000;
            border: 1px solid #e0e0e0;
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .admin-dropdown-content a {
            color: var(--text-color);
            padding: 0.75rem 1rem;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: var(--font-medium);
            transition: background-color 0.2s;
        }

        .admin-dropdown-content a:hover {
            background-color: #f8f9fa;
            color: var(--first-color);
        }

        .admin-dropdown-content a i {
            font-size: 1.1rem;
        }

        .admin-dropdown.show .admin-dropdown-content {
            display: block;
            animation: dropdownFadeIn 0.2s ease-out;
        }

        @keyframes dropdownFadeIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Admin Badge */
        .admin-badge {
            background: #ffd700;
            color: #000;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: var(--font-semi-bold);
            margin-left: 0.5rem;
        }

        /* Main Content */
        .main {
            min-height: calc(100vh - 120px);
            padding: 2rem 0;
        }

        .main__container {
            max-width: 800px;
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

        .breadcrumb {
            margin-bottom: 2rem;
        }

        .breadcrumb__list {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .breadcrumb__link {
            color: var(--first-color);
            text-decoration: none;
        }

        .breadcrumb__link:hover {
            text-decoration: underline;
        }

        .breadcrumb__separator {
            color: var(--text-color);
        }

        .breadcrumb__current {
            color: var(--text-color);
        }

        /* Scanner Section */
        .scanner__card {
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

        .form__group {
            margin-bottom: 1.5rem;
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

        .qr__input {
            min-height: 120px;
            resize: vertical;
            font-family: monospace;
            font-size: 0.9rem;
        }

        /* Camera Scanner Styles */
        .camera__container {
            position: relative;
            background: #000;
            border-radius: 0.5rem;
            overflow: hidden;
            margin-bottom: 1rem;
            min-height: 300px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .camera__video {
            width: 100%;
            height: 300px;
            object-fit: cover;
            display: none;
        }

        .camera__video.active {
            display: block;
        }

        .camera__canvas {
            display: none;
        }

        .scan__result {
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.9rem;
            display: none;
        }

        .scan__result.success {
            background: rgba(40, 167, 69, 0.9);
            display: block;
        }

        .scan__result.error {
            background: rgba(220, 53, 69, 0.9);
            display: block;
        }

        .camera__controls {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 1rem;
        }

        .camera__controls .btn {
            padding: 0.75rem 1.5rem;
            font-size: 0.9rem;
        }

        .scan__status {
            text-align: center;
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 0.25rem;
            border-left: 4px solid #007bff;
        }

        .scan__status-text {
            font-size: 0.9rem;
            color: #007bff;
            font-weight: var(--font-medium);
        }

        .scan__status-text.scanning {
            color: #28a745;
        }

        .scan__status-text.error {
            color: #dc3545;
        }

        .scanned__info {
            background: #e6ffed;
            border: 1px solid #69db7c;
            border-radius: 0.5rem;
            padding: 1rem;
        }

        .student__info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.75rem;
        }

        .student__field {
            display: flex;
            flex-direction: column;
        }

        .student__label {
            font-size: 0.8rem;
            color: #2b8a3e;
            font-weight: var(--font-medium);
            margin-bottom: 0.25rem;
        }

        .student__value {
            font-size: 0.9rem;
            color: #155724;
            font-weight: var(--font-semi-bold);
        }

        .camera__placeholder {
            color: #6c757d;
            font-size: 1rem;
            text-align: center;
            padding: 2rem;
        }

        .camera__placeholder i {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
            display: block;
        }

        .instructions__content {
            padding: 0;
        }

        .instructions__list {
            list-style: none;
            padding: 0;
        }

        .instructions__item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(25, 118, 210, 0.1);
        }

        .instructions__item:last-child {
            border-bottom: none;
        }

        .instructions__item i {
            background: var(--first-color);
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: var(--font-semi-bold);
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

        .notification--warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #e67e22;
            border: 1px solid #f39c12;
        }

        /* Instructions */
        .instructions__card {
            background: #e3f2fd;
            border: 1px solid #42a5f5;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .instructions__title {
            color: #1976d2;
            font-weight: var(--font-semi-bold);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .instructions__list {
            color: #1976d2;
            margin-left: 1rem;
        }

        .instructions__item {
            margin-bottom: 0.5rem;
        }

        /* Camera Section */
        .camera__section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            text-align: center;
        }

        .camera__placeholder {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 3rem 1rem;
            color: var(--text-color);
        }

        .camera__icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.5;
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

            .admin-dropdown-content {
                position: fixed;
                right: 1rem;
                left: 1rem;
                min-width: auto;
                max-width: none;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }

            .recent-activity {
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
                    <p>Admin - QR Scanner</p>
                </div>
            </div>
            <nav class="header__nav">
                <ul>
                    <li><a href="admin_dashboard.php"> Dashboard</a></li>
                    <li><a href="events.php"> Events</a></li>
                    <li><a href="qr_scanner.php"> QR Scanner</a></li>
                    <li><a href="reports.php">Reports</a></li>
                    <li class="admin-dropdown">
                        <button class="admin-dropdown-btn" onclick="toggleAdminDropdown()">
                            <i class="ri-user-settings-line"></i> Admin <i class="ri-arrow-down-s-line"></i>
                        </button>
                        <div class="admin-dropdown-content">
                            <a href="change_password.php">
                                <i class="ri-lock-password-line"></i>
                                Change Password
                            </a>
                            <a href="dashboard.php?logout=1">
                                <i class="ri-logout-box-line"></i>
                                Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="main__container">
            <!-- Page Header -->
            <div class="page__header">
                <h1 class="page__title">QR Code Scanner</h1>
                <p class="page__subtitle">Scan student QR codes for event attendance tracking (Time In/Out)</p>
                <p style="font-size: 0.9rem; color: #666; margin-top: 0.5rem;">
                    📅 Current Time: <?php echo date('F j, Y g:i:s A T'); ?> (Philippine Time - <?php echo date_default_timezone_get(); ?>)
                </p>
            </div>

            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <div class="breadcrumb__list">
                    <a href="dashboard.php" class="breadcrumb__link">Dashboard</a>
                    <span class="breadcrumb__separator">/</span>
                    <span class="breadcrumb__current">QR Scanner</span>
                </div>
            </nav>

            <!-- Notification -->
            <?php if (!empty($notification)): ?>
                <div class="notification notification--<?php echo $notification_type; ?>">
                    <i class="ri-<?php echo $notification_type === 'success' ? 'check' : ($notification_type === 'warning' ? 'error-warning' : 'error-warning'); ?>-line"></i>
                    <?php echo htmlspecialchars($notification); ?>
                </div>
            <?php endif; ?>

            <!-- QR Scanner Form -->
            <div class="scanner__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-qr-scan-line"></i>
                    </div>
                    <h2 class="card__title">QR Code Scanner</h2>
                </div>

                <form method="POST" action="">

                    <div class="form__group">
                        <label for="event_id" class="form__label">Select Event</label>
                        <select id="event_id" name="event_id" class="form__select" required>
                            <option value="">Choose an event...</option>
                            <?php if (empty($activeEvents)): ?>
                                <option value="" disabled>No events scheduled for today (<?php echo date('M j, Y'); ?>)</option>
                            <?php else: ?>
                                <?php foreach ($activeEvents as $event): ?>
                                    <option value="<?php echo htmlspecialchars($event['id']); ?>"
                                        <?php echo (isset($_POST['event_id']) && $_POST['event_id'] === $event['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($event['title']); ?> -
                                        <?php echo date('g:i A', strtotime($event['start_time'])); ?> to <?php echo date('g:i A', strtotime($event['end_time'])); ?> -
                                        <?php echo ucfirst($event['status']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <small style="color: var(--text-color); font-size: 0.9rem; margin-top: 0.5rem; display: block;">
                            📅 Showing events for today: <?php echo date('F j, Y'); ?>
                        </small>
                    </div>

                    <div class="form__group">
                        <label for="attendance_type" class="form__label">Attendance Type</label>
                        <select id="attendance_type" name="attendance_type" class="form__select" required>
                            <option value="">Choose type...</option>
                            <option value="check_in" <?php echo (isset($_POST['attendance_type']) && $_POST['attendance_type'] === 'check_in') ? 'selected' : ''; ?>>
                                🟢 Time In (Arrival)
                            </option>
                            <option value="check_out" <?php echo (isset($_POST['attendance_type']) && $_POST['attendance_type'] === 'check_out') ? 'selected' : ''; ?>>
                                🔴 Time Out (Departure)
                            </option>
                        </select>
                        <small style="color: var(--text-color); font-size: 0.9rem; margin-top: 0.5rem; display: block;">
                            📝 <strong>Time In:</strong> Record when student arrives at the event<br>
                            📝 <strong>Time Out:</strong> Record when student leaves the event
                        </small>
                    </div>

                    <!-- Camera Scanner Section -->
                    <div class="form__group">
                        <label class="form__label">QR Code Camera Scanner</label>
                        <div class="camera__container">
                            <video id="cameraFeed" class="camera__video" autoplay muted playsinline></video>
                            <canvas id="cameraCanvas" class="camera__canvas"></canvas>
                            <div id="scanResult" class="scan__result"></div>
                        </div>
                        <div class="camera__controls">
                            <button type="button" id="startCamera" class="btn btn--secondary">
                                <i class="ri-camera-line"></i>
                                Start Camera
                            </button>
                            <button type="button" id="stopCamera" class="btn btn--secondary" disabled>
                                <i class="ri-camera-off-line"></i>
                                Stop Camera
                            </button>
                            <button type="button" id="switchCamera" class="btn btn--secondary" disabled>
                                <i class="ri-camera-switch-line"></i>
                                Switch Camera
                            </button>
                        </div>
                        <div class="scan__status">
                            <div id="scanStatus" class="scan__status-text">Ready to scan QR codes</div>
                        </div>
                    </div>

                    <!-- Hidden field to store scanned QR data -->
                    <input type="hidden" id="qr_data" name="qr_data" value="">

                    <!-- Scanned Data Display -->
                    <div class="form__group" id="scannedDataGroup" style="display: none;">
                        <label class="form__label">Scanned Student Information</label>
                        <div class="scanned__info">
                            <div id="studentInfo" class="student__info"></div>
                        </div>
                    </div>

                    <div class="form__buttons">
                        <button type="submit" id="recordBtn" class="btn btn--primary" disabled>
                            <i class="ri-check-line"></i>
                            Record Attendance
                        </button>
                        <button type="button" class="btn btn--secondary" onclick="clearForm()">
                            <i class="ri-refresh-line"></i>
                            Clear Form
                        </button>
                    </div>
                </form>
            </div>

            <!-- Camera Scanner Instructions -->
            <div class="scanner__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-information-line"></i>
                    </div>
                    <h2 class="card__title">Camera Scanner Instructions</h2>
                </div>
                <div class="instructions__content">
                    <ul class="instructions__list">
                        <li class="instructions__item">
                            <i class="ri-number-1"></i>
                            Select the event from the dropdown above
                        </li>
                        <li class="instructions__item">
                            <i class="ri-number-2"></i>
                            Click "Start Camera" to begin scanning
                        </li>
                        <li class="instructions__item">
                            <i class="ri-number-3"></i>
                            Ask students to display their QR code from their dashboard
                        </li>
                        <li class="instructions__item">
                            <i class="ri-number-4"></i>
                            Hold the QR code in front of the camera until it's detected
                        </li>
                        <li class="instructions__item">
                            <i class="ri-number-5"></i>
                            Verify the student information and click "Record Attendance"
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Back to Dashboard -->
            <div style="text-align: center; margin-top: 2rem;">
                <a href="dashboard.php" class="btn btn--secondary">
                    <i class="ri-arrow-left-line"></i>
                    Back to Dashboard
                </a>
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
        // Camera Scanner Variables
        let stream = null;
        let scanning = false;
        let cameras = [];
        let currentCameraIndex = 0;
        let scanInterval = null;
        let isProcessing = false;

        // DOM Elements
        const video = document.getElementById('cameraFeed');
        const canvas = document.getElementById('cameraCanvas');
        const scanResult = document.getElementById('scanResult');
        const scanStatus = document.getElementById('scanStatus');
        const startBtn = document.getElementById('startCamera');
        const stopBtn = document.getElementById('stopCamera');
        const switchBtn = document.getElementById('switchCamera');
        const recordBtn = document.getElementById('recordBtn');
        const qrDataInput = document.getElementById('qr_data');
        const scannedDataGroup = document.getElementById('scannedDataGroup');
        const studentInfo = document.getElementById('studentInfo');

        // Check if we're on HTTPS or localhost (required for camera access)
        function checkSecureContext() {
            const isSecure = location.protocol === 'https:' ||
                location.hostname === 'localhost' ||
                location.hostname === '127.0.0.1';

            if (!isSecure) {
                // Check if we're accessing from mobile/network
                const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
                const isNetworkAccess = !location.hostname.includes('localhost') && !location.hostname.includes('127.0.0.1');

                if (isMobile && isNetworkAccess) {
                    updateStatus('📱 Mobile Access Detected', 'warning');
                    showMobileAccessInfo();
                } else {
                    updateStatus('⚠️ Camera requires HTTPS or localhost. Current: ' + location.protocol, 'error');
                }
                startBtn.disabled = true;
                return false;
            }
            return true;
        }

        // Show mobile access information
        function showMobileAccessInfo() {
            const mobileInfoDiv = document.createElement('div');
            mobileInfoDiv.innerHTML = `
                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 10px 0;">
                    <h4 style="color: #856404; margin: 0 0 10px 0;">📱 Mobile Camera Access Setup</h4>
                    <p style="color: #856404; margin: 5px 0;">To use the camera on your mobile device, you need HTTPS access.</p>
                    
                    <div style="background: white; padding: 10px; border-radius: 5px; margin: 10px 0;">
                        <strong>Option 1: Enable HTTPS on XAMPP</strong>
                        <ol style="margin: 5px 0; padding-left: 20px;">
                            <li>Open XAMPP Control Panel</li>
                            <li>Stop Apache</li>
                            <li>Click "Config" → "Apache (httpd.conf)"</li>
                            <li>Uncomment: <code>#LoadModule ssl_module modules/mod_ssl.so</code></li>
                            <li>Uncomment: <code>#Include conf/extra/httpd-ssl.conf</code></li>
                            <li>Restart Apache</li>
                            <li>Access: <strong>https://${location.hostname}/joyces/qr_scanner.php</strong></li>
                        </ol>
                    </div>
                    
                    <div style="background: white; padding: 10px; border-radius: 5px; margin: 10px 0;">
                        <strong>Option 2: Manual Entry Alternative</strong>
                        <p>If you can't set up HTTPS, you can manually enter student QR data:</p>
                        <button onclick="showManualEntry()" style="background: #007bff; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer;">
                            📝 Manual QR Entry
                        </button>
                    </div>
                    
                    <div style="background: white; padding: 10px; border-radius: 5px; margin: 10px 0;">
                        <strong>Option 3: Access from Computer</strong>
                        <p>Use the QR scanner directly on the computer where XAMPP is running:</p>
                        <a href="http://localhost/joyces/qr_scanner.php" style="color: #007bff;">http://localhost/joyces/qr_scanner.php</a>
                    </div>
                </div>
            `;

            // Insert after the camera controls
            const cameraContainer = document.querySelector('.scanner__controls');
            if (cameraContainer) {
                cameraContainer.parentNode.insertBefore(mobileInfoDiv, cameraContainer.nextSibling);
            }
        }

        // Show manual QR entry form
        function showManualEntry() {
            const manualDiv = document.createElement('div');
            manualDiv.id = 'manualEntryDiv';
            manualDiv.innerHTML = `
                <div style="background: #e3f2fd; border: 1px solid #bbdefb; border-radius: 8px; padding: 15px; margin: 10px 0;">
                    <h4 style="color: #1976d2; margin: 0 0 10px 0;">📝 Manual QR Data Entry</h4>
                    <p style="color: #1976d2; margin: 5px 0;">Ask the student to show you their QR code from their dashboard, then manually enter their information:</p>
                    
                    <div style="margin: 10px 0;">
                        <label style="display: block; margin: 5px 0; font-weight: bold;">Student ID:</label>
                        <input type="text" id="manual_student_id" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div style="margin: 10px 0;">
                        <label style="display: block; margin: 5px 0; font-weight: bold;">First Name:</label>
                        <input type="text" id="manual_first_name" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div style="margin: 10px 0;">
                        <label style="display: block; margin: 5px 0; font-weight: bold;">Last Name:</label>
                        <input type="text" id="manual_last_name" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div style="margin: 10px 0;">
                        <label style="display: block; margin: 5px 0; font-weight: bold;">Email (optional):</label>
                        <input type="email" id="manual_email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div style="margin: 10px 0;">
                        <label style="display: block; margin: 5px 0; font-weight: bold;">Course (optional):</label>
                        <input type="text" id="manual_course" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <div style="margin: 15px 0;">
                        <button onclick="processManualEntry()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px;">
                            ✅ Process Manual Entry
                        </button>
                        <button onclick="hideManualEntry()" style="background: #6c757d; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">
                            ❌ Cancel
                        </button>
                    </div>
                </div>
            `;

            // Remove existing manual entry if any
            const existing = document.getElementById('manualEntryDiv');
            if (existing) existing.remove();

            // Insert after the mobile info
            const mobileInfo = document.querySelector('[style*="background: #fff3cd"]');
            if (mobileInfo) {
                mobileInfo.parentNode.insertBefore(manualDiv, mobileInfo.nextSibling);
            }
        }

        // Process manual entry
        function processManualEntry() {
            const studentId = document.getElementById('manual_student_id').value.trim();
            const firstName = document.getElementById('manual_first_name').value.trim();
            const lastName = document.getElementById('manual_last_name').value.trim();
            const email = document.getElementById('manual_email').value.trim();
            const course = document.getElementById('manual_course').value.trim();

            // Check if event and attendance type are selected
            const eventId = document.getElementById('event_id').value;
            const attendanceType = document.getElementById('attendance_type').value;

            if (!eventId) {
                alert('Please select an event first.');
                return;
            }

            if (!attendanceType) {
                alert('Please select attendance type (Time In or Time Out) first.');
                return;
            }

            if (!studentId || !firstName || !lastName) {
                alert('Please fill in at least Student ID, First Name, and Last Name');
                return;
            }

            // Create QR data object
            const qrData = {
                student_id: studentId,
                first_name: firstName,
                last_name: lastName,
                email: email,
                course: course,
                year_level: '',
                section: ''
            };

            // Process as if it was scanned
            qrDataInput.value = JSON.stringify(qrData);
            displayStudentInfo(qrData);
            recordBtn.disabled = false;

            const typeText = attendanceType === 'check_in' ? 'Time In' : 'Time Out';
            updateStatus(`✅ Manual entry processed for ${typeText}! Verify information and record attendance.`, 'success');
            hideManualEntry();
        }

        // Hide manual entry
        function hideManualEntry() {
            const manualDiv = document.getElementById('manualEntryDiv');
            if (manualDiv) manualDiv.remove();
        }

        // Initialize camera functionality
        async function initCameraSupport() {
            try {
                // Check secure context first
                if (!checkSecureContext()) {
                    return;
                }

                // Check if getUserMedia is supported
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Camera access not supported by this browser');
                }

                // Check for jsQR library
                if (typeof jsQR === 'undefined') {
                    throw new Error('jsQR library not loaded');
                }

                // Get list of video input devices
                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    cameras = devices.filter(device => device.kind === 'videoinput');

                    console.log('Available cameras:', cameras.length);

                    if (cameras.length > 1) {
                        switchBtn.style.display = 'inline-flex';
                    }
                } catch (error) {
                    console.warn('Could not enumerate devices:', error);
                }

                updateStatus('✅ Camera support detected. Click "Start Camera" to begin scanning.');

            } catch (error) {
                console.error('Camera initialization error:', error);
                updateStatus('❌ Camera not available: ' + error.message, 'error');
                startBtn.disabled = true;
            }
        }

        // Start camera with better error handling
        async function startCamera() {
            try {
                updateStatus('🔄 Starting camera...', 'scanning');
                startBtn.disabled = true;

                // Request camera permission with fallback constraints
                let constraints = {
                    video: {
                        width: {
                            ideal: 640
                        },
                        height: {
                            ideal: 480
                        },
                        facingMode: {
                            ideal: 'environment'
                        } // Prefer back camera
                    }
                };

                // If we have specific cameras, use the selected one
                if (cameras.length > 0 && cameras[currentCameraIndex]) {
                    constraints.video.deviceId = {
                        exact: cameras[currentCameraIndex].deviceId
                    };
                }

                try {
                    stream = await navigator.mediaDevices.getUserMedia(constraints);
                } catch (error) {
                    console.warn('Failed with specific constraints, trying basic:', error);
                    // Fallback to basic video constraints
                    constraints = {
                        video: true
                    };
                    stream = await navigator.mediaDevices.getUserMedia(constraints);
                }

                video.srcObject = stream;

                // Wait for video to be ready
                await new Promise((resolve, reject) => {
                    video.onloadedmetadata = () => {
                        video.play()
                            .then(() => {
                                canvas.width = video.videoWidth || 640;
                                canvas.height = video.videoHeight || 480;
                                resolve();
                            })
                            .catch(reject);
                    };
                    video.onerror = reject;

                    // Timeout after 10 seconds
                    setTimeout(() => reject(new Error('Video load timeout')), 10000);
                });

                video.classList.add('active');

                // Update button states
                stopBtn.disabled = false;
                if (cameras.length > 1) {
                    switchBtn.disabled = false;
                }

                // Start scanning
                startScanning();
                updateStatus('📷 Camera active. Point at QR code to scan.', 'scanning');

            } catch (error) {
                console.error('Camera start error:', error);
                let errorMsg = 'Failed to start camera: ';

                if (error.name === 'NotAllowedError') {
                    errorMsg += 'Permission denied. Please allow camera access.';
                } else if (error.name === 'NotFoundError') {
                    errorMsg += 'No camera found.';
                } else if (error.name === 'NotReadableError') {
                    errorMsg += 'Camera is in use by another application.';
                } else {
                    errorMsg += error.message;
                }

                updateStatus('❌ ' + errorMsg, 'error');
                startBtn.disabled = false;
            }
        }

        // Stop camera
        function stopCamera() {
            try {
                if (stream) {
                    stream.getTracks().forEach(track => {
                        track.stop();
                        console.log('Stopped track:', track.kind);
                    });
                    stream = null;
                }

                video.classList.remove('active');
                video.srcObject = null;

                // Stop scanning
                if (scanInterval) {
                    clearInterval(scanInterval);
                    scanInterval = null;
                }
                scanning = false;
                isProcessing = false;

                // Update button states
                startBtn.disabled = false;
                stopBtn.disabled = true;
                switchBtn.disabled = true;

                // Clear scan result
                hideScanResult();
                updateStatus('⏹️ Camera stopped. Click "Start Camera" to resume scanning.');

            } catch (error) {
                console.error('Error stopping camera:', error);
            }
        }

        // Switch camera
        async function switchCamera() {
            if (cameras.length > 1) {
                currentCameraIndex = (currentCameraIndex + 1) % cameras.length;
                console.log('Switching to camera:', currentCameraIndex);
                stopCamera();
                // Small delay before starting new camera
                setTimeout(() => startCamera(), 1000);
            }
        }

        // Start QR code scanning with improved performance
        function startScanning() {
            scanning = true;
            isProcessing = false;

            // Scan less frequently to improve performance
            scanInterval = setInterval(() => {
                if (scanning && !isProcessing) {
                    scanForQR();
                }
            }, 200); // Scan every 200ms instead of 100ms
        }

        // Scan for QR codes with better error handling
        function scanForQR() {
            try {
                if (!scanning || !video.videoWidth || !video.videoHeight || isProcessing) {
                    return;
                }

                isProcessing = true;

                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);

                // Use jsQR to scan
                const code = jsQR(imageData.data, imageData.width, imageData.height, {
                    inversionAttempts: "dontInvert" // Improve performance
                });

                if (code && code.data) {
                    handleQRDetection(code.data);
                }

            } catch (error) {
                console.error('Scan error:', error);
            } finally {
                isProcessing = false;
            }
        }

        // Handle QR code detection
        function handleQRDetection(qrData) {
            try {
                console.log('QR Data detected:', qrData);

                // Parse QR data
                let studentData;
                try {
                    studentData = JSON.parse(qrData);
                } catch (parseError) {
                    throw new Error('Invalid JSON format in QR code');
                }

                console.log('Parsed student data:', studentData);

                // Validate required fields
                if (!studentData.student_id) {
                    throw new Error('QR code missing student_id field. Found fields: ' + Object.keys(studentData).join(', '));
                }
                if (!studentData.first_name) {
                    throw new Error('QR code missing first_name field. Found fields: ' + Object.keys(studentData).join(', '));
                }
                if (!studentData.last_name) {
                    throw new Error('QR code missing last_name field. Found fields: ' + Object.keys(studentData).join(', '));
                }

                // Stop scanning temporarily to prevent multiple scans
                scanning = false;

                // Update hidden field
                qrDataInput.value = qrData;

                // Display success
                showScanResult('✅ QR code scanned successfully!', 'success');
                updateStatus('✅ Student detected! Verify information below.', 'scanning');

                // Display student information
                displayStudentInfo(studentData);

                // Enable record button
                recordBtn.disabled = false;

                // Resume scanning after 3 seconds
                setTimeout(() => {
                    if (stream) { // Only resume if camera is still active
                        scanning = true;
                        hideScanResult();
                    }
                }, 3000);

            } catch (error) {
                console.error('QR parsing error:', error);
                showScanResult('❌ ' + error.message, 'error');
                setTimeout(() => {
                    hideScanResult();
                }, 2000);
            }
        }

        // Display student information
        function displayStudentInfo(data) {
            const fields = [{
                    label: 'Student ID',
                    value: data.student_id
                },
                {
                    label: 'Name',
                    value: `${data.first_name} ${data.last_name}`
                },
                {
                    label: 'Email',
                    value: data.email || 'Not provided'
                },
                {
                    label: 'Course',
                    value: data.course || 'Not specified'
                },
                {
                    label: 'Year & Section',
                    value: `${data.year_level || 'N/A'} - ${data.section || 'N/A'}`
                }
            ];

            studentInfo.innerHTML = fields.map(field => `
                <div class="student__field">
                    <div class="student__label">${field.label}</div>
                    <div class="student__value">${field.value}</div>
                </div>
            `).join('');

            scannedDataGroup.style.display = 'block';
        }

        // Show scan result overlay
        function showScanResult(message, type) {
            scanResult.textContent = message;
            scanResult.className = `scan__result ${type}`;
            scanResult.style.display = 'block';
        }

        // Hide scan result overlay
        function hideScanResult() {
            scanResult.style.display = 'none';
            scanResult.className = 'scan__result';
        }

        // Update status message
        function updateStatus(message, type = '') {
            scanStatus.textContent = message;
            scanStatus.className = `scan__status-text ${type}`;
        }

        // Clear form
        function clearForm() {
            qrDataInput.value = '';
            document.getElementById('event_id').value = '';
            document.getElementById('attendance_type').value = '';
            scannedDataGroup.style.display = 'none';
            recordBtn.disabled = true;
            updateStatus('Ready to scan QR codes');
        }

        // Event listeners
        startBtn.addEventListener('click', startCamera);
        stopBtn.addEventListener('click', stopCamera);
        switchBtn.addEventListener('click', switchCamera);

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const eventId = document.getElementById('event_id').value;
            const attendanceType = document.getElementById('attendance_type').value;
            const qrData = qrDataInput.value.trim();

            if (!eventId) {
                e.preventDefault();
                alert('Please select an event.');
                return;
            }

            if (!attendanceType) {
                e.preventDefault();
                alert('Please select attendance type (Time In or Time Out).');
                return;
            }

            if (!qrData) {
                e.preventDefault();
                alert('Please scan a QR code first.');
                return;
            }

            try {
                const data = JSON.parse(qrData);
                if (!data.student_id) {
                    e.preventDefault();
                    alert('Invalid QR code format. Missing student ID.');
                    return;
                }
            } catch (error) {
                e.preventDefault();
                alert('Invalid QR code format. Please scan again.');
                return;
            }
        });

        // Auto-clear form after successful submission
        <?php if ($notification_type === 'success'): ?>
            setTimeout(function() {
                clearForm();
                updateStatus('✅ Attendance recorded! Ready for next scan.', 'scanning');
            }, 2000);
        <?php endif; ?>

        // Update record button text based on attendance type
        document.getElementById('attendance_type').addEventListener('change', function() {
            const attendanceType = this.value;
            const recordBtn = document.getElementById('recordBtn');

            if (attendanceType === 'check_in') {
                recordBtn.innerHTML = '<i class="ri-login-box-line"></i> Record Time In';
                recordBtn.style.background = '#28a745'; // Green for check-in
            } else if (attendanceType === 'check_out') {
                recordBtn.innerHTML = '<i class="ri-logout-box-line"></i> Record Time Out';
                recordBtn.style.background = '#dc3545'; // Red for check-out
            } else {
                recordBtn.innerHTML = '<i class="ri-check-line"></i> Record Attendance';
                recordBtn.style.background = ''; // Default color
            }
        });

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing QR Scanner...');
            initCameraSupport();

            // Set initial button state
            const attendanceType = document.getElementById('attendance_type').value;
            if (attendanceType) {
                document.getElementById('attendance_type').dispatchEvent(new Event('change'));
            }
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
            }
        });

        // Handle visibility change (tab switching)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden && scanning) {
                console.log('Page hidden, pausing scan');
                scanning = false;
            } else if (!document.hidden && stream) {
                console.log('Page visible, resuming scan');
                scanning = true;
            }
        });

        // Admin dropdown functionality
        function toggleAdminDropdown() {
            const dropdown = document.querySelector('.admin-dropdown');
            dropdown.classList.toggle('show');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.querySelector('.admin-dropdown');
            const isClickInsideDropdown = dropdown.contains(event.target);

            if (!isClickInsideDropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        });

        // Close dropdown when pressing escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                const dropdown = document.querySelector('.admin-dropdown');
                if (dropdown.classList.contains('show')) {
                    dropdown.classList.remove('show');
                }
            }
        });
    </script>
</body>

</html>