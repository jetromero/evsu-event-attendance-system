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

// Redirect admin users to admin dashboard
if ($user['role'] === 'admin') {
    header('Location: admin_dashboard.php');
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    logoutUser();
    header('Location: index.php');
    exit();
}

// Check for session notifications (like access denied messages)
$notification = '';
$notification_type = '';
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification']['message'];
    $notification_type = $_SESSION['notification']['type'];
    unset($_SESSION['notification']);
}

// Get user's attendance history
function getUserAttendanceHistory($userId)
{
    try {
        $supabase = getSupabaseClient();

        // Get attendance records from the main attendance table
        $attendance = $supabase->select('attendance', '*', ['user_id_new' => $userId], 'coalesce(check_in_time, attendance_date) DESC');

        if (!$attendance) {
            return [];
        }

        $attendanceHistory = [];
        foreach ($attendance as $record) {
            // Get event details for each attendance record
            $event = $supabase->select('events', '*', ['id' => $record['event_id']]);
            $eventInfo = is_array($event) && !empty($event) ? $event[0] : null;

            if ($eventInfo) {
                // Create a combined record with attendance and event information
                $attendanceHistory[] = [
                    'id' => $record['id'],
                    'event_title' => $eventInfo['title'],
                    'event_date' => $eventInfo['event_date'],
                    'location' => $eventInfo['location'],
                    'attendance_type' => $record['attendance_type'] ?? 'check_in',
                    'check_in_time' => $record['check_in_time'],
                    'check_out_time' => $record['check_out_time'],
                    'attendance_date' => $record['attendance_date'],
                    'check_in_method' => $record['check_in_method'] ?? 'qr_code',
                    'notes' => $record['notes'] ?? ''
                ];
            }
        }

        // Return only the 6 most recent records for compact display
        return array_slice($attendanceHistory, 0, 6);
    } catch (Exception $e) {
        error_log("Error getting attendance history: " . $e->getMessage());
        return [];
    }
}

// Get upcoming events
function getUpcomingEvents()
{
    try {
        $supabase = getSupabaseClient();

        // Get events that are active and in the future
        $today = date('Y-m-d');
        $events = $supabase->select('events', '*', ['status' => 'active']);

        // Filter events that are today or in the future
        $upcomingEvents = array_filter($events, function ($event) use ($today) {
            return $event['event_date'] >= $today;
        });

        // Sort by event date
        usort($upcomingEvents, function ($a, $b) {
            return strtotime($a['event_date']) - strtotime($b['event_date']);
        });

        return array_slice($upcomingEvents, 0, 5); // Return only next 5 events
    } catch (Exception $e) {
        error_log("Error getting upcoming events: " . $e->getMessage());
        return [];
    }
}

$attendanceHistory = getUserAttendanceHistory($user['id']);
$upcomingEvents = getUpcomingEvents();

// Generate QR code data for the student
$qrData = json_encode([
    'student_id' => $user['id'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'email' => $user['email'],
    'course' => $user['course'],
    'year_level' => $user['year_level'],
    'section' => $user['section'],
    'timestamp' => time()
]);
// Add cache busting parameter to force fresh QR code generation
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($qrData) . "&nocache=" . time();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVSU Student Dashboard - Event Attendance System</title>
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

        /* Main Dashboard */
        .dashboard {
            min-height: calc(100vh - 120px);
            padding: 2rem 0;
        }

        .dashboard__container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .dashboard__header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .welcome__title {
            font-size: 2.5rem;
            color: var(--title-color);
            font-weight: var(--font-semi-bold);
            margin-bottom: 0.5rem;
        }

        .welcome__subtitle {
            color: var(--text-color);
            font-size: 1.1rem;
        }

        /* Dashboard Grid */
        .dashboard__grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 3rem;
        }

        /* Profile Card */
        .profile__card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-left: 5px solid var(--first-color);
        }

        .profile__header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .profile__icon {
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

        .profile__title {
            font-size: 1.5rem;
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
        }

        .profile__info {
            display: grid;
            gap: 1rem;
        }

        .info__item {
            display: flex;
            justify-content: space-between;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .info__label {
            font-weight: var(--font-medium);
            color: var(--text-color);
        }

        .info__value {
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
        }

        /* QR Code Card */
        .qr__card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            border-left: 5px solid var(--first-color);
            text-align: center;
        }

        .qr__code {
            margin: 1rem 0;
        }

        .qr__code img {
            border-radius: 0.5rem;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }

        .qr__actions {
            margin-top: 1rem;
        }

        .qr__download-btn {
            background: linear-gradient(135deg, var(--first-color) 0%, var(--first-color-alt) 100%);
            color: white;
            padding: 0.75rem 1rem;
            border: none;
            border-radius: 0.5rem;
            font-family: var(--body-font);
            font-size: var(--normal-font-size);
            font-weight: var(--font-medium);
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
        }

        .qr__download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }

        .qr__description {
            color: var(--text-color);
            font-size: 0.9rem;
            margin-top: 1rem;
        }

        /* Section Cards */
        .section__card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .section__header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #f0f0f0;
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

        .section__title {
            font-size: 1.4rem;
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
        }

        /* Event Cards */
        .event__item {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--first-color);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .event__item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        }

        .event__title {
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
            margin-bottom: 0.5rem;
        }

        .event__details {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: var(--text-color);
        }

        .event__detail {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Compact Attendance Cards */
        .attendance__item {
            background: #f8f9fa;
            border-radius: 0.4rem;
            padding: 0.75rem 1rem;
            margin-bottom: 0.5rem;
            border-left: 3px solid var(--first-color);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .attendance__item:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.1);
        }

        .attendance__title {
            font-weight: var(--font-semi-bold);
            color: var(--title-color);
            margin-bottom: 0.3rem;
            font-size: 0.95rem;
        }

        .attendance__details {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            font-size: 0.8rem;
            color: var(--text-color);
        }

        .attendance__detail {
            display: flex;
            align-items: center;
            gap: 0.2rem;
        }

        .attendance__detail i {
            font-size: 0.9rem;
        }

        /* Action Buttons */
        .action__buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .action__btn {
            background: linear-gradient(135deg, var(--first-color) 0%, var(--first-color-alt) 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            text-decoration: none;
            text-align: center;
            font-weight: var(--font-medium);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .action__btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
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

        /* Notification */
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
            .dashboard__grid {
                grid-template-columns: 1fr;
            }

            .header__container {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .header__nav ul {
                gap: 1rem;
            }

            .welcome__title {
                font-size: 2rem;
            }

            .action__buttons {
                grid-template-columns: 1fr;
            }
        }

        /* Download Feedback Animations */
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }

            to {
                transform: translateX(100%);
                opacity: 0;
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
                    <p>Student Dashboard</p>
                </div>
            </div>
            <nav class="header__nav">
                <ul>
                    <li><a href="#profile">Profile</a></li>
                    <li><a href="#attendance">Attendance</a></li>
                    <li><a href="#events">Events</a></li>
                    <li><a href="account_details.php">Account</a></li>
                    <li><a href="?logout=1" class="logout-btn">
                            <i class="ri-logout-box-line"></i> Logout
                        </a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Dashboard -->
    <main class="dashboard">
        <div class="dashboard__container">
            <!-- Welcome Header -->
            <div class="dashboard__header">
                <h1 class="welcome__title">Welcome, <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h1>
                <p class="welcome__subtitle">Your personalized event attendance dashboard</p>
            </div>

            <!-- Notification -->
            <?php if (!empty($notification)): ?>
                <div class="notification notification--<?php echo $notification_type; ?>" style="max-width: 100%; margin: 0 auto 2rem auto; display: flex; align-items: center; gap: 0.5rem; padding: 1rem 1.5rem; border-radius: 0.5rem;">
                    <i class="ri-<?php echo $notification_type === 'success' ? 'check' : 'error-warning'; ?>-line"></i>
                    <span><?php echo htmlspecialchars($notification); ?></span>
                    <button style="margin-left: auto; background: none; border: none; color: currentColor; font-size: 1.25rem; cursor: pointer; opacity: 0.7;" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
            <?php endif; ?>

            <!-- Profile and QR Code Grid -->
            <div class="dashboard__grid">
                <!-- Profile Information -->
                <div class="profile__card" id="profile">
                    <div class="profile__header">
                        <div class="profile__icon">
                            <i class="ri-user-line"></i>
                        </div>
                        <h2 class="profile__title">Student Profile</h2>
                    </div>
                    <div class="profile__info">
                        <div class="info__item">
                            <span class="info__label">Name:</span>
                            <span class="info__value"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></span>
                        </div>
                        <div class="info__item">
                            <span class="info__label">Email:</span>
                            <span class="info__value"><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        <div class="info__item">
                            <span class="info__label">Course:</span>
                            <span class="info__value"><?php echo htmlspecialchars($user['course']); ?></span>
                        </div>
                        <div class="info__item">
                            <span class="info__label">Year & Section:</span>
                            <span class="info__value"><?php echo htmlspecialchars($user['year_level'] . ' - ' . $user['section']); ?></span>
                        </div>
                        <div class="info__item">
                            <span class="info__label">Student ID:</span>
                            <span class="info__value"><?php echo htmlspecialchars($user['id']); ?></span>
                        </div>
                        <div class="info__item">
                            <span class="info__label">Member Since:</span>
                            <span class="info__value"><?php echo date('F j, Y', strtotime($user['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- QR Code -->
                <div class="qr__card">
                    <div class="profile__header">
                        <div class="profile__icon">
                            <i class="ri-qr-code-line"></i>
                        </div>
                        <h2 class="profile__title">Your QR Code</h2>
                    </div>
                    <div class="qr__code">
                        <img src="<?php echo $qrCodeUrl; ?>" alt="Student QR Code" id="studentQRCode" crossorigin="anonymous">
                    </div>
                    <div class="qr__actions">
                        <button type="button" class="qr__download-btn" onclick="downloadQRCode()">
                            <i class="ri-download-line"></i>
                            Download QR Code
                        </button>
                    </div>
                    <p class="qr__description">
                        Show this QR code at events for quick check-in.
                        The code updates automatically when you modify your profile.
                    </p>
                </div>
            </div>

            <!-- Attendance History -->
            <div class="section__card" id="attendance">
                <div class="section__header">
                    <div class="section__icon">
                        <i class="ri-calendar-check-line"></i>
                    </div>
                    <h2 class="section__title">My Event Attendance History</h2>
                    <p style="font-size: 0.85rem; color: var(--text-color); margin: 0;">Showing 6 most recent records</p>
                </div>
                <?php if (!empty($attendanceHistory)): ?>
                    <?php foreach ($attendanceHistory as $attendance): ?>
                        <div class="attendance__item">
                            <h3 class="attendance__title"><?php echo htmlspecialchars($attendance['event_title']); ?></h3>
                            <div class="attendance__details">
                                <div class="attendance__detail">
                                    <i class="ri-calendar-line"></i>
                                    <span><?php echo date('F j, Y', strtotime($attendance['event_date'])); ?></span>
                                </div>
                                <div class="attendance__detail">
                                    <i class="ri-map-pin-line"></i>
                                    <span><?php echo htmlspecialchars($attendance['location']); ?></span>
                                </div>

                                <?php if ($attendance['attendance_type'] === 'check_in'): ?>
                                    <div class="attendance__detail">
                                        <i class="ri-login-box-line" style="color: #28a745;"></i>
                                        <span style="color: #28a745;">Time In: <?php echo date('g:i A', strtotime($attendance['check_in_time'] ?? $attendance['attendance_date'])); ?> PHT</span>
                                    </div>
                                <?php elseif ($attendance['attendance_type'] === 'check_out'): ?>
                                    <div class="attendance__detail">
                                        <i class="ri-logout-box-line" style="color: #dc3545;"></i>
                                        <span style="color: #dc3545;">Time Out: <?php echo date('g:i A', strtotime($attendance['check_out_time'] ?? $attendance['attendance_date'])); ?> PHT</span>
                                    </div>
                                <?php else: ?>
                                    <div class="attendance__detail">
                                        <i class="ri-time-line"></i>
                                        <span>Attended: <?php echo date('g:i A', strtotime($attendance['attendance_date'])); ?> PHT</span>
                                    </div>
                                <?php endif; ?>

                                <div class="attendance__detail">
                                    <i class="ri-check-line"></i>
                                    <span><?php echo ucfirst(str_replace('_', ' ', $attendance['check_in_method'])); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty__state">
                        <div class="empty__icon">
                            <i class="ri-calendar-line"></i>
                        </div>
                        <h3>No Event Attendance Yet</h3>
                        <p>You haven't attended any events yet. Check out upcoming events below!</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Events -->
            <div class="section__card" id="events">
                <div class="section__header">
                    <div class="section__icon">
                        <i class="ri-calendar-event-line"></i>
                    </div>
                    <h2 class="section__title">Upcoming Events</h2>
                </div>
                <?php if (!empty($upcomingEvents)): ?>
                    <?php foreach ($upcomingEvents as $event): ?>
                        <div class="event__item">
                            <h3 class="event__title"><?php echo htmlspecialchars($event['title']); ?></h3>
                            <p style="margin: 0.5rem 0; color: var(--text-color);"><?php echo htmlspecialchars($event['description']); ?></p>
                            <div class="event__details">
                                <div class="event__detail">
                                    <i class="ri-calendar-line"></i>
                                    <span><?php echo date('F j, Y', strtotime($event['event_date'])); ?></span>
                                </div>
                                <div class="event__detail">
                                    <i class="ri-time-line"></i>
                                    <span><?php echo date('g:i A', strtotime($event['start_time'])) . ' - ' . date('g:i A', strtotime($event['end_time'])); ?></span>
                                </div>
                                <div class="event__detail">
                                    <i class="ri-map-pin-line"></i>
                                    <span><?php echo htmlspecialchars($event['location']); ?></span>
                                </div>
                                <?php if ($event['max_attendees']): ?>
                                    <div class="event__detail">
                                        <i class="ri-group-line"></i>
                                        <span>Max: <?php echo $event['max_attendees']; ?> attendees</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty__state">
                        <div class="empty__icon">
                            <i class="ri-calendar-event-line"></i>
                        </div>
                        <h3>No Upcoming Events</h3>
                        <p>There are currently no upcoming events scheduled.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Buttons -->
            <div class="action__buttons">
                <a href="account_details.php" class="action__btn">
                    <i class="ri-settings-line"></i>
                    Account Details
                </a>
                <a href="change_password.php" class="action__btn">
                    <i class="ri-lock-password-line"></i>
                    Change Password
                </a>
                <?php if (isset($user['role']) && $user['role'] === 'admin'): ?>
                    <a href="events.php" class="action__btn">
                        <i class="ri-calendar-event-line"></i>
                        View All Events
                    </a>
                    <a href="qr_scanner.php" class="action__btn">
                        <i class="ri-qr-scan-line"></i>
                        QR Scanner
                    </a>
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
        // Auto-refresh QR code every 5 minutes to ensure it's always current
        setInterval(function() {
            const qrImg = document.getElementById('studentQRCode');
            if (qrImg) {
                // Add timestamp to force refresh
                const currentSrc = qrImg.src;
                const separator = currentSrc.includes('&refresh=') ? '&refresh=' : '&refresh=';
                const baseUrl = currentSrc.split('&refresh=')[0];
                qrImg.src = baseUrl + separator + Date.now();
            }
        }, 300000); // 5 minutes

        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        function downloadQRCode() {
            const qrImg = document.getElementById('studentQRCode');
            if (qrImg) {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const img = new Image();

                // Set crossorigin to handle CORS
                img.crossOrigin = 'anonymous';
                img.src = qrImg.src;

                img.onload = function() {
                    // Create a higher resolution canvas for better quality
                    const scale = 2; // 2x resolution
                    canvas.width = img.width * scale;
                    canvas.height = img.height * scale;

                    // Scale the context to draw at higher resolution
                    ctx.scale(scale, scale);
                    ctx.imageSmoothingEnabled = false; // Keep QR code sharp

                    // Draw the image
                    ctx.drawImage(img, 0, 0);

                    // Create download link
                    try {
                        const dataUrl = canvas.toDataURL('image/png');
                        const link = document.createElement('a');
                        link.href = dataUrl;

                        // Create filename with student name and timestamp
                        const studentName = '<?php echo preg_replace("/[^a-zA-Z0-9]/", "_", $user["first_name"] . "_" . $user["last_name"]); ?>';
                        const timestamp = new Date().toISOString().slice(0, 10); // YYYY-MM-DD format
                        link.download = `${studentName}_QR_Code_${timestamp}.png`;

                        // Trigger download
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);

                        // Show success feedback
                        showDownloadFeedback('QR Code downloaded successfully!', 'success');
                    } catch (error) {
                        console.error('Download failed:', error);
                        showDownloadFeedback('Download failed. Please try again.', 'error');
                    }
                };

                img.onerror = function() {
                    showDownloadFeedback('Failed to load QR code. Please try again.', 'error');
                };
            }
        }

        function showDownloadFeedback(message, type) {
            // Create temporary notification
            const notification = document.createElement('div');
            notification.className = `notification notification--${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 1000;
                padding: 1rem 1.5rem;
                border-radius: 0.5rem;
                display: flex;
                align-items: center;
                gap: 0.5rem;
                animation: slideInRight 0.3s ease-out;
                max-width: 300px;
            `;

            const icon = type === 'success' ? 'ri-check-line' : 'ri-error-warning-line';
            notification.innerHTML = `<i class="${icon}"></i><span>${message}</span>`;

            document.body.appendChild(notification);

            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOutRight 0.3s ease-in forwards';
                setTimeout(() => {
                    if (notification.parentElement) {
                        notification.parentElement.removeChild(notification);
                    }
                }, 300);
            }, 3000);
        }
    </script>
</body>

</html>