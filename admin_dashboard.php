<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Access denied. Admin privileges required.'];
    header('Location: dashboard.php');
    exit();
}

// Handle cache clearing for fresh data
if (isset($_GET['refresh_cache'])) {
    // Clear admin-related cache
    unset($_SESSION['admin_stats'], $_SESSION['admin_stats_time']);
    unset($_SESSION['recent_attendance'], $_SESSION['recent_attendance_time']);
    unset($_SESSION['recent_events'], $_SESSION['recent_events_time']);
    header('Location: admin_dashboard.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    logoutUser();
    header('Location: index.php');
    exit();
}

// Get dashboard statistics (optimized for performance)
function getAdminStats()
{
    try {
        // Use cached stats if available and recent (5 minutes)
        if (isset($_SESSION['admin_stats']) && (time() - $_SESSION['admin_stats_time']) < 300) {
            return $_SESSION['admin_stats'];
        }

        $supabase = getSupabaseClient();

        // Quick count queries instead of fetching all data
        $stats = [
            'total_users' => 0,
            'total_events' => 0,
            'todays_attendance' => 0,
            'active_events' => 0
        ];

        // Use minimal queries - just count records
        try {
            $users = $supabase->select('users', 'id', [], '', 1000);
            $stats['total_users'] = is_array($users) ? count($users) : 0;
        } catch (Exception $e) {
            error_log("Error counting users: " . $e->getMessage());
        }

        try {
            $events = $supabase->select('events', 'id, status', [], '', 500);
            if (is_array($events)) {
                $stats['total_events'] = count($events);
                $stats['active_events'] = count(array_filter($events, function ($e) {
                    return $e['status'] === 'active';
                }));
            }
        } catch (Exception $e) {
            error_log("Error counting events: " . $e->getMessage());
        }

        try {
            // Get today's attendance only
            $today = date('Y-m-d');
            $todayStart = $today . ' 00:00:00';
            $todayEnd = $today . ' 23:59:59';

            // Get attendance records from today
            $attendance = $supabase->select('attendance', 'id, attendance_date, check_in_time', [], '', 1000);

            $todaysAttendance = 0;
            if (is_array($attendance)) {
                foreach ($attendance as $record) {
                    // Check if attendance is from today using either attendance_date or check_in_time
                    $recordDate = null;
                    if (!empty($record['check_in_time'])) {
                        $recordDate = date('Y-m-d', strtotime($record['check_in_time']));
                    } elseif (!empty($record['attendance_date'])) {
                        $recordDate = date('Y-m-d', strtotime($record['attendance_date']));
                    }

                    if ($recordDate === $today) {
                        $todaysAttendance++;
                    }
                }
            }

            $stats['todays_attendance'] = $todaysAttendance;
        } catch (Exception $e) {
            error_log("Error counting today's attendance: " . $e->getMessage());
        }

        // Cache the results
        $_SESSION['admin_stats'] = $stats;
        $_SESSION['admin_stats_time'] = time();

        return $stats;
    } catch (Exception $e) {
        error_log("Error getting admin stats: " . $e->getMessage());
        return [
            'total_users' => 0,
            'total_events' => 0,
            'todays_attendance' => 0,
            'active_events' => 0
        ];
    }
}

function getRecentAttendance($limit = 5)
{
    try {
        // Use cached data if available and recent (2 minutes)
        if (isset($_SESSION['recent_attendance']) && (time() - $_SESSION['recent_attendance_time']) < 120) {
            return $_SESSION['recent_attendance'];
        }

        $supabase = getSupabaseClient();

        // Get recent attendance with user and event details
        $attendance = $supabase->select('attendance', 'id, user_id_new, event_id, attendance_date, check_in_time, attendance_type, check_in_method', [], 'coalesce(check_in_time, attendance_date) DESC', $limit);

        $recentAttendance = [];
        if (is_array($attendance)) {
            foreach ($attendance as $record) {
                try {
                    // Get user details
                    $user = $supabase->select('users', 'first_name, last_name, email, course', ['id' => $record['user_id_new']]);
                    $userInfo = is_array($user) && !empty($user) ? $user[0] : null;

                    // Get event details
                    $event = $supabase->select('events', 'title, event_date, location', ['id' => $record['event_id']]);
                    $eventInfo = is_array($event) && !empty($event) ? $event[0] : null;

                    if ($userInfo && $eventInfo) {
                        $recentAttendance[] = [
                            'id' => $record['id'],
                            'user_name' => $userInfo['first_name'] . ' ' . $userInfo['last_name'],
                            'user_email' => $userInfo['email'],
                            'user_course' => $userInfo['course'],
                            'event_title' => $eventInfo['title'],
                            'event_location' => $eventInfo['location'],
                            'attendance_time' => $record['check_in_time'] ?: $record['attendance_date'],
                            'attendance_type' => $record['attendance_type'] ?: 'check_in',
                            'check_in_method' => $record['check_in_method'] ?: 'qr_code'
                        ];
                    }
                } catch (Exception $e) {
                    error_log("Error processing attendance record: " . $e->getMessage());
                    continue;
                }
            }
        }

        // Cache the results
        $_SESSION['recent_attendance'] = $recentAttendance;
        $_SESSION['recent_attendance_time'] = time();

        return $recentAttendance;
    } catch (Exception $e) {
        error_log("Error getting recent attendance: " . $e->getMessage());
        return [];
    }
}

function getRecentEvents($limit = 5)
{
    try {
        // Use cached data if available and recent (2 minutes)
        if (isset($_SESSION['recent_events']) && (time() - $_SESSION['recent_events_time']) < 120) {
            return $_SESSION['recent_events'];
        }

        $supabase = getSupabaseClient();
        $events = $supabase->select('events', 'title, event_date, location, status, created_at', [], 'created_at DESC', $limit);

        // Cache the results
        $_SESSION['recent_events'] = $events ?: [];
        $_SESSION['recent_events_time'] = time();

        return $events ?: [];
    } catch (Exception $e) {
        error_log("Error getting recent events: " . $e->getMessage());
        return [];
    }
}

$stats = getAdminStats();
$recentAttendance = getRecentAttendance();
$recentEvents = getRecentEvents();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - EVSU Event Attendance System</title>
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

        .admin-badge {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            margin-left: 0.5rem;
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

        /* Main Content */
        .main {
            min-height: calc(100vh - 120px);
            padding: 2rem 0;
        }

        .main__container {
            max-width: 1200px;
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

        /* Dashboard Cards */
        .dashboard__card {
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

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card__icon {
            width: 4rem;
            height: 4rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            color: white;
        }

        .stat-card__icon--users {
            background: #17a2b8;
        }

        .stat-card__icon--events {
            background: #28a745;
        }

        .stat-card__icon--attendance {
            background: #ffc107;
            color: #212529;
        }

        .stat-card__icon--active {
            background: var(--first-color);
        }

        .stat-card__number {
            font-size: 2.5rem;
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
            margin-bottom: 0.5rem;
        }

        .stat-card__label {
            color: var(--text-color);
            font-weight: var(--font-medium);
        }

        /* Quick Actions */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, var(--first-color) 0%, var(--first-color-alt) 100%);
            color: white;
            text-decoration: none;
            border-radius: 0.75rem;
            transition: all 0.3s;
            font-weight: var(--font-medium);
        }

        .action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .action-btn i {
            font-size: 1.5rem;
        }

        /* Recent Activity */
        .recent-activity {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-info {
            flex: 1;
        }

        .activity-title {
            font-weight: var(--font-medium);
            color: var(--title-color);
            margin-bottom: 0.25rem;
        }

        .activity-subtitle {
            font-size: 0.9rem;
            color: var(--text-color);
        }

        .activity-time {
            font-size: 0.8rem;
            color: var(--text-color);
            opacity: 0.8;
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
                    <p>Admin Dashboard</p>
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
                            <a href="?logout=1">
                                <i class="ri-logout-box-line"></i>
                                Logout
                            </a>
                        </div>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Dashboard -->
    <main class="main">
        <div class="main__container">
            <!-- Welcome Section -->
            <div class="page__header">
                <h1 class="page__title">Welcome back, <?php echo htmlspecialchars($user['last_name']); ?>!</h1>
                <p class="page__subtitle">Here's an overview of your EVSU Event Attendance Management System.</p>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--users">
                        <i class="ri-user-line"></i>
                    </div>
                    <div class="stat-card__number"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-card__label">Total Users</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--events">
                        <i class="ri-calendar-event-line"></i>
                    </div>
                    <div class="stat-card__number"><?php echo number_format($stats['total_events']); ?></div>
                    <div class="stat-card__label">Total Events</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--attendance">
                        <i class="ri-qr-code-line"></i>
                    </div>
                    <div class="stat-card__number"><?php echo number_format($stats['todays_attendance']); ?></div>
                    <div class="stat-card__label">Today's Attendance</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card__icon stat-card__icon--active">
                        <i class="ri-live-line"></i>
                    </div>
                    <div class="stat-card__number"><?php echo number_format($stats['active_events']); ?></div>
                    <div class="stat-card__label">Active Events</div>
                </div>
            </div>

            <!-- Debug Information (remove this in production) -->
            <?php if (false): // Set to false to hide debug info 
            ?>
                <div class="dashboard__card" style="background: #f8f9fa; border-left: 4px solid #007bff;">
                    <div class="card__header">
                        <div class="card__icon" style="background: #007bff;">
                            <i class="ri-bug-line"></i>
                        </div>
                        <h2 class="card__title">Debug Information</h2>
                    </div>
                    <div style="font-family: monospace; font-size: 0.9rem;">
                        <?php
                        try {
                            $supabase = getSupabaseClient();

                            // Test individual queries
                            echo "<p><strong>🔍 Database Connection Test:</strong></p>";

                            // Test users table
                            $testUsers = $supabase->select('users', 'id, first_name, last_name', [], 'created_at DESC', 3);
                            echo "<p>👥 <strong>Users query result:</strong> " . (is_array($testUsers) ? count($testUsers) . " records found" : "Failed") . "</p>";
                            if (is_array($testUsers) && !empty($testUsers)) {
                                echo "<ul style='margin-left: 20px;'>";
                                foreach ($testUsers as $user) {
                                    echo "<li>" . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . " (ID: " . $user['id'] . ")</li>";
                                }
                                echo "</ul>";
                            }

                            // Test events table
                            $testEvents = $supabase->select('events', 'id, title, status', [], 'created_at DESC', 3);
                            echo "<p>📅 <strong>Events query result:</strong> " . (is_array($testEvents) ? count($testEvents) . " records found" : "Failed") . "</p>";
                            if (is_array($testEvents) && !empty($testEvents)) {
                                echo "<ul style='margin-left: 20px;'>";
                                foreach ($testEvents as $event) {
                                    echo "<li>" . htmlspecialchars($event['title']) . " - " . $event['status'] . " (ID: " . $event['id'] . ")</li>";
                                }
                                echo "</ul>";
                            }

                            // Test attendance table
                            $testAttendance = $supabase->select('attendance', 'id, attendance_type, check_in_time', [], 'id DESC', 3);
                            echo "<p>✅ <strong>Attendance query result:</strong> " . (is_array($testAttendance) ? count($testAttendance) . " records found" : "Failed") . "</p>";
                            if (is_array($testAttendance) && !empty($testAttendance)) {
                                echo "<ul style='margin-left: 20px;'>";
                                foreach ($testAttendance as $att) {
                                    echo "<li>" . $att['attendance_type'] . " - " . ($att['check_in_time'] ?? 'No time') . " (ID: " . $att['id'] . ")</li>";
                                }
                                echo "</ul>";
                            }

                            echo "<p><strong>📊 Statistics Calculation:</strong></p>";
                            echo "<p>Total Users: " . $stats['total_users'] . "</p>";
                            echo "<p>Total Events: " . $stats['total_events'] . "</p>";
                            echo "<p>Total Attendance: " . $stats['total_attendance'] . "</p>";
                            echo "<p>Active Events: " . $stats['active_events'] . "</p>";
                        } catch (Exception $e) {
                            echo "<p style='color: red;'><strong>❌ Debug Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
                        }
                        ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Quick Actions -->
            <div class="dashboard__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-flashlight-line"></i>
                    </div>
                    <h2 class="card__title">Quick Actions</h2>
                </div>
                <div class="actions-grid">
                    <a href="create_event.php" class="action-btn">
                        <i class="ri-add-circle-line"></i>
                        <div>
                            <div>Create Event</div>
                            <small>Add new event to system</small>
                        </div>
                    </a>
                    <a href="events.php" class="action-btn">
                        <i class="ri-calendar-event-line"></i>
                        <div>
                            <div>Manage Events</div>
                            <small>View, edit, delete events</small>
                        </div>
                    </a>
                    <a href="qr_scanner.php" class="action-btn">
                        <i class="ri-qr-scan-line"></i>
                        <div>
                            <div>QR Code Scanner</div>
                            <small>Scan student attendance</small>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="recent-activity">
                <div class="dashboard__card">
                    <div class="card__header">
                        <div class="card__icon">
                            <i class="ri-qr-scan-line"></i>
                        </div>
                        <h3 class="card__title">Recent Attendance</h3>
                    </div>
                    <ul class="activity-list">
                        <?php if (empty($recentAttendance)): ?>
                            <li class="activity-item">
                                <div class="activity-info">
                                    <div class="activity-title">No recent attendance</div>
                                    <div class="activity-subtitle">Attendance records will appear here as students check in</div>
                                </div>
                            </li>
                        <?php else: ?>
                            <?php foreach ($recentAttendance as $record): ?>
                                <li class="activity-item">
                                    <div class="activity-info">
                                        <div class="activity-title">
                                            <?php echo htmlspecialchars($record['user_name']); ?>
                                            <?php if ($record['attendance_type'] === 'check_out'): ?>
                                                <span style="color: #dc3545; font-size: 0.8rem;">(Check Out)</span>
                                            <?php else: ?>
                                                <span style="color: #28a745; font-size: 0.8rem;">(Check In)</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="activity-subtitle">
                                            <?php echo htmlspecialchars($record['event_title']); ?> •
                                            <?php
                                            $method = ucfirst(str_replace('_', ' ', $record['check_in_method']));
                                            echo htmlspecialchars($method);
                                            ?>
                                        </div>
                                    </div>
                                    <div class="activity-time">
                                        <?php
                                        $attendanceTime = $record['attendance_time'];
                                        if ($attendanceTime) {
                                            echo date('M j, g:i A', strtotime($attendanceTime));
                                        } else {
                                            echo 'Unknown time';
                                        }
                                        ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="dashboard__card">
                    <div class="card__header">
                        <div class="card__icon">
                            <i class="ri-calendar-line"></i>
                        </div>
                        <h3 class="card__title">Recent Events</h3>
                    </div>
                    <ul class="activity-list">
                        <?php if (empty($recentEvents)): ?>
                            <li class="activity-item">
                                <div class="activity-info">
                                    <div class="activity-title">No recent events</div>
                                    <div class="activity-subtitle">Events will appear here when created</div>
                                </div>
                            </li>
                        <?php else: ?>
                            <?php foreach ($recentEvents as $event): ?>
                                <li class="activity-item">
                                    <div class="activity-info">
                                        <div class="activity-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <div class="activity-subtitle">
                                            <?php echo htmlspecialchars($event['location']); ?> •
                                            <span style="color: <?php echo $event['status'] === 'active' ? '#28a745' : '#6c757d'; ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('M j, Y', strtotime($event['event_date'])); ?>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer__text">
            EVSU Event Attendance System
        </div>
        <div class="footer__copy">
            &copy; <?php echo date('Y'); ?> All rights reserved.
        </div>
    </footer>

    <script>
        // Page loading optimization
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth transitions
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';

            // Fade in page
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 50);

            // Preload critical pages for faster navigation
            const criticalPages = ['events.php', 'qr_scanner.php'];
            criticalPages.forEach(page => {
                const link = document.createElement('link');
                link.rel = 'prefetch';
                link.href = page;
                document.head.appendChild(link);
            });
        });

        // Fast navigation with loading states
        function navigateWithLoading(url) {
            // Show loading state
            document.body.style.opacity = '0.7';

            // Navigate after brief delay
            setTimeout(() => {
                window.location.href = url;
            }, 100);
        }

        // Add fast navigation to nav links
        document.addEventListener('DOMContentLoaded', function() {
            const navLinks = document.querySelectorAll('.header__nav a:not([href*="logout"])');
            navLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    if (this.href && !this.href.includes('#')) {
                        e.preventDefault();
                        navigateWithLoading(this.href);
                    }
                });
            });
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