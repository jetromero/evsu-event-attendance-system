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

$notification = '';
$notification_type = '';
$event = null;

// Get event ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Event ID is required.'];
    header('Location: events.php');
    exit();
}

$event_id = $_GET['id'];

// Get event data
try {
    $supabase = getSupabaseClient();
    $events = $supabase->select('events', '*', ['id' => $event_id]);

    if (empty($events)) {
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'Event not found.'];
        header('Location: events.php');
        exit();
    }

    $event = $events[0];
} catch (Exception $e) {
    error_log("Error fetching event: " . $e->getMessage());
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Error loading event data.'];
    header('Location: events.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_event'])) {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = trim($_POST['event_date']);
    $start_time = trim($_POST['start_time']);
    $end_time = trim($_POST['end_time']);
    $location = trim($_POST['location']);
    $max_attendees = !empty($_POST['max_attendees']) ? intval($_POST['max_attendees']) : null;
    $status = trim($_POST['status']);

    // Validation
    if (empty($title) || empty($description) || empty($event_date) || empty($start_time) || empty($end_time) || empty($location) || empty($status)) {
        $notification = 'Please fill in all required fields.';
        $notification_type = 'error';
    } elseif ($max_attendees !== null && $max_attendees <= 0) {
        $notification = 'Maximum attendees must be greater than 0 or left empty for unlimited.';
        $notification_type = 'error';
    } elseif (strtotime($start_time) >= strtotime($end_time)) {
        $notification = 'End time must be after start time.';
        $notification_type = 'error';
    } else {
        try {
            // Update event
            $updateData = [
                'title' => $title,
                'description' => $description,
                'event_date' => $event_date,
                'start_time' => $start_time,
                'end_time' => $end_time,
                'location' => $location,
                'max_attendees' => $max_attendees,
                'status' => $status,
                'updated_at' => date('c') // ISO 8601 format
            ];

            $result = $supabase->update('events', $updateData, ['id' => $event_id]);

            if ($result) {
                $_SESSION['notification'] = ['type' => 'success', 'message' => 'Event updated successfully!'];
                header('Location: events.php');
                exit();
            } else {
                $notification = 'Failed to update event. Please try again.';
                $notification_type = 'error';
            }
        } catch (Exception $e) {
            error_log("Error updating event: " . $e->getMessage());
            $notification = 'An error occurred while updating the event.';
            $notification_type = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Event - EVSU Event Attendance System</title>
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

        /* Form Card */
        .form__card {
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
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
        .form__select,
        .form__textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            font-family: var(--body-font);
            font-size: var(--normal-font-size);
            transition: border-color 0.3s;
        }

        .form__input:focus,
        .form__select:focus,
        .form__textarea:focus {
            outline: none;
            border-color: var(--first-color);
        }

        .form__textarea {
            resize: vertical;
            min-height: 120px;
        }

        .form__buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
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
                    <p>Edit Event</p>
                </div>
            </div>
            <nav class="header__nav">
                <ul>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="events.php">Events</a></li>
                    <li><a href="qr_scanner.php">QR Scanner</a></li>
                    <li><a href="admin_dashboard.php?logout=1" class="logout-btn">
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
                <h1 class="page__title">Edit Event</h1>
                <p class="page__subtitle">Update event information and settings</p>
            </div>

            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <div class="breadcrumb__list">
                    <a href="admin_dashboard.php" class="breadcrumb__link">Dashboard</a>
                    <span class="breadcrumb__separator">/</span>
                    <a href="events.php" class="breadcrumb__link">Events</a>
                    <span class="breadcrumb__separator">/</span>
                    <span class="breadcrumb__current">Edit Event</span>
                </div>
            </nav>

            <!-- Notification -->
            <?php if (!empty($notification)): ?>
                <div class="notification notification--<?php echo $notification_type; ?>">
                    <i class="ri-<?php echo $notification_type === 'success' ? 'check' : 'error-warning'; ?>-line"></i>
                    <?php echo htmlspecialchars($notification); ?>
                </div>
            <?php endif; ?>

            <!-- Event Form -->
            <div class="form__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-edit-line"></i>
                    </div>
                    <h2 class="card__title">Edit Event: <?php echo htmlspecialchars($event['title']); ?></h2>
                </div>

                <form method="POST" action="">
                    <div class="form__grid">
                        <div class="form__group">
                            <label for="title" class="form__label">Event Title</label>
                            <input type="text" id="title" name="title" class="form__input"
                                value="<?php echo htmlspecialchars($event['title']); ?>" required>
                        </div>

                        <div class="form__group">
                            <label for="status" class="form__label">Status</label>
                            <select id="status" name="status" class="form__select" required>
                                <option value="active" <?php echo $event['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $event['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="completed" <?php echo $event['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $event['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="form__group">
                            <label for="event_date" class="form__label">Event Date</label>
                            <input type="date" id="event_date" name="event_date" class="form__input"
                                value="<?php echo $event['event_date']; ?>" required>
                        </div>

                        <div class="form__group">
                            <label for="max_attendees" class="form__label">Maximum Attendees</label>
                            <input type="number" id="max_attendees" name="max_attendees" class="form__input"
                                value="<?php echo $event['max_attendees']; ?>" min="1"
                                placeholder="Leave empty for unlimited">
                        </div>

                        <div class="form__group">
                            <label for="start_time" class="form__label">Start Time</label>
                            <input type="time" id="start_time" name="start_time" class="form__input"
                                value="<?php echo $event['start_time']; ?>" required>
                        </div>

                        <div class="form__group">
                            <label for="end_time" class="form__label">End Time</label>
                            <input type="time" id="end_time" name="end_time" class="form__input"
                                value="<?php echo $event['end_time']; ?>" required>
                        </div>
                    </div>

                    <div class="form__group">
                        <label for="location" class="form__label">Location</label>
                        <input type="text" id="location" name="location" class="form__input"
                            value="<?php echo htmlspecialchars($event['location']); ?>" required
                            placeholder="e.g., IT Laboratory, Main Auditorium, etc.">
                    </div>

                    <div class="form__group">
                        <label for="description" class="form__label">Description</label>
                        <textarea id="description" name="description" class="form__textarea" required
                            placeholder="Provide a detailed description of the event..."><?php echo htmlspecialchars($event['description']); ?></textarea>
                    </div>

                    <div class="form__buttons">
                        <button type="submit" name="update_event" class="btn btn--primary">
                            <i class="ri-save-line"></i>
                            Update Event
                        </button>
                        <a href="events.php" class="btn btn--secondary">
                            <i class="ri-arrow-left-line"></i>
                            Cancel
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
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const description = document.getElementById('description').value.trim();
            const eventDate = document.getElementById('event_date').value;
            const startTime = document.getElementById('start_time').value;
            const endTime = document.getElementById('end_time').value;
            const location = document.getElementById('location').value.trim();
            const status = document.getElementById('status').value;

            if (!title || !description || !eventDate || !startTime || !endTime || !location || !status) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }

            // Validate time
            if (startTime >= endTime) {
                e.preventDefault();
                alert('End time must be after start time.');
                return;
            }

            // Validate max attendees
            const maxAttendees = document.getElementById('max_attendees').value;
            if (maxAttendees && parseInt(maxAttendees) <= 0) {
                e.preventDefault();
                alert('Maximum attendees must be greater than 0 or left empty for unlimited.');
                return;
            }

            // Confirm update
            if (!confirm('Are you sure you want to update this event?')) {
                e.preventDefault();
            }
        });

        // Auto-resize textarea
        const textarea = document.getElementById('description');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });

        // Initialize textarea height
        textarea.style.height = textarea.scrollHeight + 'px';
    </script>
</body>

</html>