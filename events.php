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

// Get all events for admin view (optimized with pagination)
function getAllEvents($limit = 20, $offset = 0)
{
    try {
        // Use cached data if available and recent (3 minutes)
        $cache_key = "events_page_{$offset}_{$limit}";
        if (isset($_SESSION[$cache_key]) && (time() - $_SESSION[$cache_key . '_time']) < 180) {
            return $_SESSION[$cache_key];
        }

        $supabase = getSupabaseClient();

        // Get events with essential fields only for performance
        $events = $supabase->select('events', 'id, title, description, event_date, start_time, end_time, location, status, max_attendees, created_at', [], 'event_date DESC', $limit);

        // Cache the results
        $_SESSION[$cache_key] = $events ?: [];
        $_SESSION[$cache_key . '_time'] = time();

        return $events ?: [];
    } catch (Exception $e) {
        error_log("Error getting all events: " . $e->getMessage());
        return [];
    }
}

// Get event attendance summary (optimized - load only for displayed events)
function getEventAttendanceSummary($eventIds = [])
{
    try {
        // Use cached data if available and recent (3 minutes)
        $cache_key = "attendance_summary_" . md5(implode(',', $eventIds));
        if (isset($_SESSION[$cache_key]) && (time() - $_SESSION[$cache_key . '_time']) < 180) {
            return $_SESSION[$cache_key];
        }

        $supabase = getSupabaseClient();

        // Get only attendance data for specific events to improve performance
        if (empty($eventIds)) {
            return [];
        }

        // Use basic attendance count instead of complex view for performance
        $attendanceData = [];
        foreach ($eventIds as $eventId) {
            try {
                $checkins = $supabase->select('attendance', 'id', ['event_id' => $eventId, 'attendance_type' => 'check_in']);
                $checkouts = $supabase->select('attendance', 'id', ['event_id' => $eventId, 'attendance_type' => 'check_out']);

                $attendanceData[$eventId] = [
                    'event_id' => $eventId,
                    'total_checked_in' => is_array($checkins) ? count($checkins) : 0,
                    'total_checked_out' => is_array($checkouts) ? count($checkouts) : 0,
                    'attendance_percentage' => 0 // Calculate this on frontend if needed
                ];
            } catch (Exception $e) {
                error_log("Error getting attendance for event $eventId: " . $e->getMessage());
            }
        }

        // Cache the results
        $_SESSION[$cache_key] = $attendanceData;
        $_SESSION[$cache_key . '_time'] = time();

        return $attendanceData;
    } catch (Exception $e) {
        error_log("Error getting event attendance summary: " . $e->getMessage());
        return [];
    }
}

// Load initial data (first page only)
$allEvents = getAllEvents(15); // Load only first 15 events
$eventIds = array_column($allEvents, 'id');
$attendanceData = getEventAttendanceSummary($eventIds);

// Handle session notifications
$notification = '';
$notification_type = '';
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification']['message'];
    $notification_type = $_SESSION['notification']['type'];
    unset($_SESSION['notification']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Management - EVSU Event Attendance System</title>
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

        /* Statistics Cards */
        .stats__grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .stats__card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-left: 5px solid var(--first-color);
        }

        .stats__header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stats__icon {
            background: var(--first-color);
            color: white;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .stats__value {
            font-size: 2rem;
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
        }

        .stats__label {
            color: var(--text-color);
            font-size: 0.9rem;
        }

        /* Events Section */
        .events__section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .section__header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
        }

        .section__title {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 1.5rem;
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
            margin: 0;
        }

        .section__icon {
            background: var(--first-color);
            color: white;
            width: 2.5rem;
            height: 2.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        /* Event Cards */
        .events__grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 2rem;
        }

        .event__card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 5px solid var(--first-color);
        }

        .event__card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .event__header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .event__title {
            font-size: 1.25rem;
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
            margin-bottom: 0.5rem;
        }

        .event__status {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: var(--font-medium);
        }

        .status--active {
            background: #e6ffed;
            color: #2b8a3e;
            border: 1px solid #69db7c;
        }

        .status--inactive {
            background: #f8f9fa;
            color: #6c757d;
            border: 1px solid #dee2e6;
        }

        .status--cancelled {
            background: #ffe0e0;
            color: #c92a2a;
            border: 1px solid #ff6b6b;
        }

        .status--completed {
            background: #e3f2fd;
            color: #1976d2;
            border: 1px solid #42a5f5;
        }

        .event__description {
            color: var(--text-color);
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .event__details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .event__detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            color: var(--text-color);
        }

        .event__detail i {
            color: var(--first-color);
        }

        .event__attendance {
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
            border: 1px solid #e0e0e0;
        }

        .attendance__header {
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
            margin-bottom: 0.5rem;
        }

        .attendance__stats {
            display: flex;
            gap: 2rem;
            align-items: center;
        }

        .attendance__stat {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .progress__bar {
            width: 100px;
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress__fill {
            height: 100%;
            background: var(--first-color);
            border-radius: 4px;
            transition: width 0.3s;
        }

        /* Empty State */
        .empty__state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-color);
        }

        .empty__icon {
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

            .stats__grid {
                grid-template-columns: 1fr;
            }

            .event__details {
                grid-template-columns: 1fr;
            }

            .attendance__stats {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .page__title {
                font-size: 1.5rem;
            }
        }

        /* Buttons */
        .btn {
            padding: 0.5rem;
            border: none;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            width: 2.5rem;
            height: 2.5rem;
        }

        .btn--edit {
            background: #007bff;
            color: white;
        }

        .btn--edit:hover {
            background: #0056b3;
            transform: translateY(-2px);
        }

        .btn--delete {
            background: #dc3545;
            color: white;
        }

        .btn--delete:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn--scan {
            background: #28a745;
            color: white;
        }

        .btn--scan:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn--primary {
            background: var(--first-color);
            color: white;
            padding: 0.75rem 1.5rem;
            width: auto;
            height: auto;
        }

        .btn--primary:hover {
            background: var(--first-color-alt);
            transform: translateY(-2px);
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
                    <p>Admin - Event Management</p>
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
                <h1 class="page__title">Event Management Dashboard</h1>
                <p class="page__subtitle">Comprehensive view of all events and attendance statistics</p>
            </div>

            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <div class="breadcrumb__list">
                    <a href="admin_dashboard.php" class="breadcrumb__link">Dashboard</a>
                    <span class="breadcrumb__separator">/</span>
                    <span class="breadcrumb__current">Events</span>
                </div>
            </nav>

            <!-- Notification -->
            <?php if (!empty($notification)): ?>
                <div class="notification notification--<?php echo $notification_type; ?>">
                    <i class="ri-<?php echo $notification_type === 'success' ? 'check' : 'error-warning'; ?>-line"></i>
                    <?php echo htmlspecialchars($notification); ?>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="stats__grid">
                <div class="stats__card">
                    <div class="stats__header">
                        <div class="stats__icon">
                            <i class="ri-calendar-event-line"></i>
                        </div>
                        <div>
                            <div class="stats__value"><?php echo count($allEvents); ?></div>
                            <div class="stats__label">Total Events</div>
                        </div>
                    </div>
                </div>

                <div class="stats__card">
                    <div class="stats__header">
                        <div class="stats__icon">
                            <i class="ri-calendar-check-line"></i>
                        </div>
                        <div>
                            <div class="stats__value">
                                <?php
                                $activeEvents = array_filter($allEvents, function ($event) {
                                    return $event['status'] === 'active';
                                });
                                echo count($activeEvents);
                                ?>
                            </div>
                            <div class="stats__label">Active Events</div>
                        </div>
                    </div>
                </div>

                <div class="stats__card">
                    <div class="stats__header">
                        <div class="stats__icon">
                            <i class="ri-group-line"></i>
                        </div>
                        <div>
                            <div class="stats__value">
                                <?php
                                $totalAttendees = 0;
                                if (!empty($attendanceData)) {
                                    $totalAttendees = array_sum(array_column($attendanceData, 'total_checked_in'));
                                }
                                echo $totalAttendees;
                                ?>
                            </div>
                            <div class="stats__label">Total Attendees</div>
                        </div>
                    </div>
                </div>

                <div class="stats__card">
                    <div class="stats__header">
                        <div class="stats__icon">
                            <i class="ri-calendar-todo-line"></i>
                        </div>
                        <div>
                            <div class="stats__value">
                                <?php
                                $upcomingEvents = array_filter($allEvents, function ($event) {
                                    return $event['status'] === 'active' && $event['event_date'] >= date('Y-m-d');
                                });
                                echo count($upcomingEvents);
                                ?>
                            </div>
                            <div class="stats__label">Upcoming Events</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Events List -->
            <div class="events__section">
                <div class="section__header">
                    <h2 class="section__title">
                        <div class="section__icon">
                            <i class="ri-calendar-line"></i>
                        </div>
                        All Events
                    </h2>
                    <a href="create_event.php" class="btn btn--primary">
                        <i class="ri-add-line"></i>
                        Create Event
                    </a>
                </div>

                <?php if (!empty($allEvents)): ?>
                    <div class="events__grid">
                        <?php foreach ($allEvents as $event): ?>
                            <div class="event__card">
                                <div class="event__header">
                                    <div>
                                        <h3 class="event__title"><?php echo htmlspecialchars($event['title']); ?></h3>
                                        <span class="event__status status--<?php echo $event['status']; ?>">
                                            <?php echo ucfirst($event['status']); ?>
                                        </span>
                                    </div>
                                    <!-- Add Edit and Action Buttons -->
                                    <div class="event__actions">
                                        <a href="edit_event.php?id=<?php echo $event['id']; ?>" class="btn btn--edit" title="Edit Event">
                                            <i class="ri-edit-line"></i>
                                        </a>
                                        <button type="button" class="btn btn--delete" onclick="confirmDelete('<?php echo $event['id']; ?>', '<?php echo htmlspecialchars($event['title'], ENT_QUOTES); ?>')" title="Delete Event">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                        <a href="qr_scanner.php?event_id=<?php echo $event['id']; ?>" class="btn btn--scan" title="Scan Attendance">
                                            <i class="ri-qr-scan-line"></i>
                                        </a>
                                    </div>
                                </div>

                                <p class="event__description">
                                    <?php echo htmlspecialchars($event['description']); ?>
                                </p>

                                <div class="event__details">
                                    <div class="event__detail">
                                        <i class="ri-calendar-line"></i>
                                        <span><?php echo date('F j, Y', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="event__detail">
                                        <i class="ri-time-line"></i>
                                        <span><?php echo date('g:i A', strtotime($event['start_time'])) . ' - ' . date('g:i A', strtotime($event['end_time'])); ?> PHT</span>
                                    </div>
                                    <div class="event__detail">
                                        <i class="ri-map-pin-line"></i>
                                        <span><?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                    <div class="event__detail">
                                        <i class="ri-calendar-check-line"></i>
                                        <span>Created: <?php echo date('M j, Y g:i A', strtotime($event['created_at'])); ?> PHT</span>
                                    </div>
                                </div>

                                <!-- Attendance Information -->
                                <?php if (isset($attendanceData[$event['id']])): ?>
                                    <?php $attendance = $attendanceData[$event['id']]; ?>
                                    <div class="event__attendance">
                                        <div class="attendance__header">Attendance Statistics</div>
                                        <div class="attendance__stats">
                                            <div class="attendance__stat">
                                                <i class="ri-user-line"></i>
                                                <span><?php echo $attendance['total_checked_in']; ?> attendees</span>
                                            </div>
                                            <?php if ($event['max_attendees']): ?>
                                                <div class="attendance__stat">
                                                    <span><?php echo round($attendance['attendance_percentage'], 1); ?>% capacity</span>
                                                    <div class="progress__bar">
                                                        <div class="progress__fill" style="width: <?php echo min($attendance['attendance_percentage'], 100); ?>%"></div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="event__attendance">
                                        <div class="attendance__header">Attendance Statistics</div>
                                        <div class="attendance__stats">
                                            <div class="attendance__stat">
                                                <i class="ri-user-line"></i>
                                                <span>0 attendees</span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty__state">
                        <div class="empty__icon">
                            <i class="ri-calendar-line"></i>
                        </div>
                        <h3>No Events Found</h3>
                        <p>There are currently no events in the system.</p>
                    </div>
                <?php endif; ?>
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
        // Page loading optimization
        document.addEventListener('DOMContentLoaded', function() {
            // Add smooth transitions
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.3s ease-in-out';

            // Fade in page
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 50);

            // Preload admin dashboard for faster navigation
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = 'admin_dashboard.php';
            document.head.appendChild(link);
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

        // Add interactivity for better user experience
        document.addEventListener('DOMContentLoaded', function() {
            // Animate progress bars (delayed for performance)
            setTimeout(() => {
                const progressBars = document.querySelectorAll('.progress__fill');
                progressBars.forEach(bar => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 100);
                });
            }, 200);

            // Add click handler for event cards (future enhancement)
            const eventCards = document.querySelectorAll('.event__card');
            eventCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't trigger if clicking on action buttons
                    if (e.target.closest('.event__actions')) {
                        return;
                    }
                    // Future: Open event details modal or navigate to event details page
                    console.log('Event card clicked');
                });
            });
        });

        // Delete confirmation function
        function confirmDelete(eventId, eventTitle) {
            if (confirm(`Are you sure you want to delete the event "${eventTitle}"?\n\nThis action cannot be undone. All associated attendance data will also be deleted.`)) {
                // Create a form to submit the delete request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_event.php';

                const eventIdInput = document.createElement('input');
                eventIdInput.type = 'hidden';
                eventIdInput.name = 'event_id';
                eventIdInput.value = eventId;

                form.appendChild(eventIdInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Quick status toggle function (future enhancement)
        function toggleEventStatus(eventId, currentStatus) {
            const newStatus = currentStatus === 'active' ? 'inactive' : 'active';

            if (confirm(`Change event status to "${newStatus}"?`)) {
                // Submit status change
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'toggle_event_status.php';

                const eventIdInput = document.createElement('input');
                eventIdInput.type = 'hidden';
                eventIdInput.name = 'event_id';
                eventIdInput.value = eventId;

                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'new_status';
                statusInput.value = newStatus;

                form.appendChild(eventIdInput);
                form.appendChild(statusInput);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Copy event details function (future enhancement)
        function copyEventDetails(eventId) {
            // Future: Copy event details to clipboard or create duplicate
            console.log('Copy event details for:', eventId);
        }

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