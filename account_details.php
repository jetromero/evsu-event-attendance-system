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

$notification = '';
$notification_type = '';

// Handle form submission - Only allow profile updates for non-admin users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $user['role'] !== 'admin') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $course = trim($_POST['course']);
    $year_level = trim($_POST['year_level']);
    $section = trim($_POST['section']);

    // Validation
    if (empty($first_name) || empty($last_name) || empty($course) || empty($year_level) || empty($section)) {
        $notification = 'Please fill in all fields.';
        $notification_type = 'error';
    } elseif (!preg_match('/^[A-Z]$/', $section)) {
        $notification = 'Section must be a single letter (A-Z).';
        $notification_type = 'error';
    } else {
        try {
            $supabase = getSupabaseClient();

            // Update user profile
            $updateData = [
                'first_name' => $first_name,
                'last_name' => $last_name,
                'course' => $course,
                'year_level' => $year_level,
                'section' => $section
            ];

            $result = $supabase->update('users', $updateData, ['id' => $user['id']]);

            if ($result) {
                // Primary database update successful, now sync to secondary database
                $syncResult = syncUserUpdateToSecondary($user['id'], $updateData);

                if ($syncResult['success']) {
                    $notification = 'Profile updated successfully!';
                    $notification_type = 'success';
                    error_log("Profile update with dual-sync successful for user ID: " . $user['id']);
                } else {
                    // Primary succeeded but secondary failed - still show success but log the issue
                    $notification = 'Profile updated successfully! (Note: Secondary sync had issues - see logs)';
                    $notification_type = 'success';
                    error_log("Profile update: Primary DB success, Secondary DB sync failed for user ID: " . $user['id'] . " - " . $syncResult['message']);
                }

                // Refresh user data
                $user = getCurrentUser();
            } else {
                $notification = 'Failed to update profile. Please try again.';
                $notification_type = 'error';
            }
        } catch (Exception $e) {
            error_log("Profile update error: " . $e->getMessage());
            $notification = 'An error occurred while updating your profile.';
            $notification_type = 'error';
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile']) && $user['role'] === 'admin') {
    $notification = 'Admin accounts cannot modify personal information. Contact system administrator if changes are needed.';
    $notification_type = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Details - EVSU Event Attendance System</title>
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

        /* Account Form */
        .account__card {
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

        .readonly-info {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            color: var(--text-color);
            cursor: not-allowed;
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
                    <p>Account Details</p>
                </div>
            </div>
            <nav class="header__nav">
                <ul>
                    <?php if ($user['role'] === 'admin'): ?>
                        <li><a href="admin_dashboard.php">Dashboard</a></li>
                        <li><a href="events.php">Events</a></li>
                        <li><a href="qr_scanner.php">QR Scanner</a></li>
                        <li><a href="account_details.php">Account</a></li>
                        <li><a href="admin_dashboard.php?logout=1" class="logout-btn">
                                <i class="ri-logout-box-line"></i> Logout
                            </a></li>
                    <?php else: ?>
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <li><a href="dashboard.php#attendance">Attendance</a></li>
                        <li><a href="dashboard.php#events">Events</a></li>
                        <li><a href="account_details.php">Account</a></li>
                        <li><a href="dashboard.php?logout=1" class="logout-btn">
                                <i class="ri-logout-box-line"></i> Logout
                            </a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main">
        <div class="main__container">
            <!-- Page Header -->
            <div class="page__header">
                <h1 class="page__title">Account Details</h1>
                <p class="page__subtitle">Manage your personal information and account settings</p>
            </div>

            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <div class="breadcrumb__list">
                    <a href="dashboard.php" class="breadcrumb__link">Dashboard</a>
                    <span class="breadcrumb__separator">/</span>
                    <span class="breadcrumb__current">Account Details</span>
                </div>
            </nav>

            <!-- Notification -->
            <?php if (!empty($notification)): ?>
                <div class="notification notification--<?php echo $notification_type; ?>">
                    <i class="ri-<?php echo $notification_type === 'success' ? 'check' : 'error-warning'; ?>-line"></i>
                    <?php echo htmlspecialchars($notification); ?>
                </div>
            <?php endif; ?>

            <!-- Account Form -->
            <div class="account__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-user-settings-line"></i>
                    </div>
                    <h2 class="card__title">Personal Information</h2>
                    <?php if ($user['role'] === 'admin'): ?>
                        <p style="color: var(--text-color); font-size: 0.9rem; margin-left: auto; font-style: italic;">
                            Admin accounts are read-only
                        </p>
                    <?php endif; ?>
                </div>

                <form method="POST" action="">
                    <div class="form__grid">
                        <div class="form__group">
                            <label for="first_name" class="form__label">First Name</label>
                            <input type="text" id="first_name" name="first_name"
                                class="form__input <?php echo $user['role'] === 'admin' ? 'readonly-info' : ''; ?>"
                                value="<?php echo htmlspecialchars($user['first_name']); ?>"
                                <?php echo $user['role'] === 'admin' ? 'readonly' : 'required'; ?>>
                        </div>

                        <div class="form__group">
                            <label for="last_name" class="form__label">Last Name</label>
                            <input type="text" id="last_name" name="last_name"
                                class="form__input <?php echo $user['role'] === 'admin' ? 'readonly-info' : ''; ?>"
                                value="<?php echo htmlspecialchars($user['last_name']); ?>"
                                <?php echo $user['role'] === 'admin' ? 'readonly' : 'required'; ?>>
                        </div>

                        <div class="form__group">
                            <label for="email" class="form__label">Email Address</label>
                            <input type="email" id="email" name="email" class="form__input readonly-info"
                                value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                            <small style="color: var(--text-color); font-size: 0.8rem; margin-top: 0.25rem; display: block;">
                                Email cannot be changed for security reasons
                            </small>
                        </div>

                        <div class="form__group">
                            <label for="student_id" class="form__label">Student ID</label>
                            <input type="text" id="student_id" name="student_id" class="form__input readonly-info"
                                value="<?php echo htmlspecialchars($user['id']); ?>" readonly>
                            <small style="color: var(--text-color); font-size: 0.8rem; margin-top: 0.25rem; display: block;">
                                Student ID is automatically generated
                            </small>
                        </div>

                        <div class="form__group">
                            <label for="course" class="form__label">Course</label>
                            <?php if ($user['role'] === 'admin'): ?>
                                <input type="text" id="course" name="course" class="form__input readonly-info"
                                    value="<?php echo htmlspecialchars($user['course']); ?>" readonly>
                            <?php else: ?>
                                <select id="course" name="course" class="form__select" required>
                                    <option value="">Select your course</option>

                                    <!-- College of Engineering and Technology -->
                                    <optgroup label="College of Engineering and Technology">
                                        <option value="BS Computer Engineering" <?php echo $user['course'] === 'BS Computer Engineering' ? 'selected' : ''; ?>>BS Computer Engineering</option>
                                        <option value="BS Computer Science" <?php echo $user['course'] === 'BS Computer Science' ? 'selected' : ''; ?>>BS Computer Science</option>
                                        <option value="BS Information Technology" <?php echo $user['course'] === 'BS Information Technology' ? 'selected' : ''; ?>>BS Information Technology</option>
                                        <option value="BS Civil Engineering" <?php echo $user['course'] === 'BS Civil Engineering' ? 'selected' : ''; ?>>BS Civil Engineering</option>
                                        <option value="BS Electrical Engineering" <?php echo $user['course'] === 'BS Electrical Engineering' ? 'selected' : ''; ?>>BS Electrical Engineering</option>
                                        <option value="BS Electronics Engineering" <?php echo $user['course'] === 'BS Electronics Engineering' ? 'selected' : ''; ?>>BS Electronics Engineering</option>
                                        <option value="BS Mechanical Engineering" <?php echo $user['course'] === 'BS Mechanical Engineering' ? 'selected' : ''; ?>>BS Mechanical Engineering</option>
                                        <option value="BS Industrial Engineering" <?php echo $user['course'] === 'BS Industrial Engineering' ? 'selected' : ''; ?>>BS Industrial Engineering</option>
                                        <option value="BS Architecture" <?php echo $user['course'] === 'BS Architecture' ? 'selected' : ''; ?>>BS Architecture</option>
                                    </optgroup>

                                    <!-- College of Business and Management -->
                                    <optgroup label="College of Business and Management">
                                        <option value="BS Business Administration" <?php echo $user['course'] === 'BS Business Administration' ? 'selected' : ''; ?>>BS Business Administration</option>
                                        <option value="BS Accountancy" <?php echo $user['course'] === 'BS Accountancy' ? 'selected' : ''; ?>>BS Accountancy</option>
                                        <option value="BS Entrepreneurship" <?php echo $user['course'] === 'BS Entrepreneurship' ? 'selected' : ''; ?>>BS Entrepreneurship</option>
                                        <option value="BS Economics" <?php echo $user['course'] === 'BS Economics' ? 'selected' : ''; ?>>BS Economics</option>
                                        <option value="BS Marketing Management" <?php echo $user['course'] === 'BS Marketing Management' ? 'selected' : ''; ?>>BS Marketing Management</option>
                                        <option value="BS Financial Management" <?php echo $user['course'] === 'BS Financial Management' ? 'selected' : ''; ?>>BS Financial Management</option>
                                        <option value="BS Human Resource Development Management" <?php echo $user['course'] === 'BS Human Resource Development Management' ? 'selected' : ''; ?>>BS Human Resource Development Management</option>
                                    </optgroup>

                                    <!-- College of Arts and Sciences -->
                                    <optgroup label="College of Arts and Sciences">
                                        <option value="AB Communication" <?php echo $user['course'] === 'AB Communication' ? 'selected' : ''; ?>>AB Communication</option>
                                        <option value="AB English" <?php echo $user['course'] === 'AB English' ? 'selected' : ''; ?>>AB English</option>
                                        <option value="AB History" <?php echo $user['course'] === 'AB History' ? 'selected' : ''; ?>>AB History</option>
                                        <option value="AB Philosophy" <?php echo $user['course'] === 'AB Philosophy' ? 'selected' : ''; ?>>AB Philosophy</option>
                                        <option value="AB Political Science" <?php echo $user['course'] === 'AB Political Science' ? 'selected' : ''; ?>>AB Political Science</option>
                                        <option value="BS Biology" <?php echo $user['course'] === 'BS Biology' ? 'selected' : ''; ?>>BS Biology</option>
                                        <option value="BS Chemistry" <?php echo $user['course'] === 'BS Chemistry' ? 'selected' : ''; ?>>BS Chemistry</option>
                                        <option value="BS Mathematics" <?php echo $user['course'] === 'BS Mathematics' ? 'selected' : ''; ?>>BS Mathematics</option>
                                        <option value="BS Physics" <?php echo $user['course'] === 'BS Physics' ? 'selected' : ''; ?>>BS Physics</option>
                                        <option value="BS Psychology" <?php echo $user['course'] === 'BS Psychology' ? 'selected' : ''; ?>>BS Psychology</option>
                                        <option value="BS Statistics" <?php echo $user['course'] === 'BS Statistics' ? 'selected' : ''; ?>>BS Statistics</option>
                                    </optgroup>

                                    <!-- College of Education -->
                                    <optgroup label="College of Education">
                                        <option value="Bachelor of Elementary Education" <?php echo $user['course'] === 'Bachelor of Elementary Education' ? 'selected' : ''; ?>>Bachelor of Elementary Education</option>
                                        <option value="Bachelor of Secondary Education - English" <?php echo $user['course'] === 'Bachelor of Secondary Education - English' ? 'selected' : ''; ?>>Bachelor of Secondary Education - English</option>
                                        <option value="Bachelor of Secondary Education - Mathematics" <?php echo $user['course'] === 'Bachelor of Secondary Education - Mathematics' ? 'selected' : ''; ?>>Bachelor of Secondary Education - Mathematics</option>
                                        <option value="Bachelor of Secondary Education - Science" <?php echo $user['course'] === 'Bachelor of Secondary Education - Science' ? 'selected' : ''; ?>>Bachelor of Secondary Education - Science</option>
                                        <option value="Bachelor of Secondary Education - Social Studies" <?php echo $user['course'] === 'Bachelor of Secondary Education - Social Studies' ? 'selected' : ''; ?>>Bachelor of Secondary Education - Social Studies</option>
                                        <option value="Bachelor of Physical Education" <?php echo $user['course'] === 'Bachelor of Physical Education' ? 'selected' : ''; ?>>Bachelor of Physical Education</option>
                                        <option value="Bachelor of Technology and Livelihood Education" <?php echo $user['course'] === 'Bachelor of Technology and Livelihood Education' ? 'selected' : ''; ?>>Bachelor of Technology and Livelihood Education</option>
                                    </optgroup>

                                    <!-- College of Nursing and Health Sciences -->
                                    <optgroup label="College of Nursing and Health Sciences">
                                        <option value="BS Nursing" <?php echo $user['course'] === 'BS Nursing' ? 'selected' : ''; ?>>BS Nursing</option>
                                        <option value="BS Midwifery" <?php echo $user['course'] === 'BS Midwifery' ? 'selected' : ''; ?>>BS Midwifery</option>
                                        <option value="BS Medical Technology" <?php echo $user['course'] === 'BS Medical Technology' ? 'selected' : ''; ?>>BS Medical Technology</option>
                                        <option value="BS Pharmacy" <?php echo $user['course'] === 'BS Pharmacy' ? 'selected' : ''; ?>>BS Pharmacy</option>
                                        <option value="BS Physical Therapy" <?php echo $user['course'] === 'BS Physical Therapy' ? 'selected' : ''; ?>>BS Physical Therapy</option>
                                        <option value="BS Occupational Therapy" <?php echo $user['course'] === 'BS Occupational Therapy' ? 'selected' : ''; ?>>BS Occupational Therapy</option>
                                    </optgroup>

                                    <!-- College of Agriculture and Food Science -->
                                    <optgroup label="College of Agriculture and Food Science">
                                        <option value="BS Agriculture" <?php echo $user['course'] === 'BS Agriculture' ? 'selected' : ''; ?>>BS Agriculture</option>
                                        <option value="BS Agricultural Engineering" <?php echo $user['course'] === 'BS Agricultural Engineering' ? 'selected' : ''; ?>>BS Agricultural Engineering</option>
                                        <option value="BS Food Technology" <?php echo $user['course'] === 'BS Food Technology' ? 'selected' : ''; ?>>BS Food Technology</option>
                                        <option value="BS Fisheries" <?php echo $user['course'] === 'BS Fisheries' ? 'selected' : ''; ?>>BS Fisheries</option>
                                        <option value="BS Forestry" <?php echo $user['course'] === 'BS Forestry' ? 'selected' : ''; ?>>BS Forestry</option>
                                        <option value="BS Veterinary Medicine" <?php echo $user['course'] === 'BS Veterinary Medicine' ? 'selected' : ''; ?>>BS Veterinary Medicine</option>
                                    </optgroup>

                                    <!-- College of Law -->
                                    <optgroup label="College of Law">
                                        <option value="Bachelor of Laws (LLB)" <?php echo $user['course'] === 'Bachelor of Laws (LLB)' ? 'selected' : ''; ?>>Bachelor of Laws (LLB)</option>
                                        <option value="Juris Doctor (JD)" <?php echo $user['course'] === 'Juris Doctor (JD)' ? 'selected' : ''; ?>>Juris Doctor (JD)</option>
                                    </optgroup>

                                    <!-- Graduate Programs -->
                                    <optgroup label="Graduate Programs">
                                        <option value="Master of Arts in Education" <?php echo $user['course'] === 'Master of Arts in Education' ? 'selected' : ''; ?>>Master of Arts in Education</option>
                                        <option value="Master of Science in Engineering" <?php echo $user['course'] === 'Master of Science in Engineering' ? 'selected' : ''; ?>>Master of Science in Engineering</option>
                                        <option value="Master of Business Administration" <?php echo $user['course'] === 'Master of Business Administration' ? 'selected' : ''; ?>>Master of Business Administration</option>
                                        <option value="Doctor of Philosophy" <?php echo $user['course'] === 'Doctor of Philosophy' ? 'selected' : ''; ?>>Doctor of Philosophy</option>
                                    </optgroup>

                                    <!-- Other/Unspecified -->
                                    <optgroup label="Other">
                                        <option value="Other" <?php echo $user['course'] === 'Other' ? 'selected' : ''; ?>>Other/Not Listed</option>
                                    </optgroup>

                                    <!-- Legacy options for backward compatibility -->
                                    <?php if (!in_array($user['course'], [
                                        'BS Computer Engineering',
                                        'BS Computer Science',
                                        'BS Information Technology',
                                        'BS Civil Engineering',
                                        'BS Electrical Engineering',
                                        'BS Electronics Engineering',
                                        'BS Mechanical Engineering',
                                        'BS Industrial Engineering',
                                        'BS Architecture',
                                        'BS Business Administration',
                                        'BS Accountancy',
                                        'BS Entrepreneurship',
                                        'BS Economics',
                                        'BS Marketing Management',
                                        'BS Financial Management',
                                        'BS Human Resource Development Management',
                                        'AB Communication',
                                        'AB English',
                                        'AB History',
                                        'AB Philosophy',
                                        'AB Political Science',
                                        'BS Biology',
                                        'BS Chemistry',
                                        'BS Mathematics',
                                        'BS Physics',
                                        'BS Psychology',
                                        'BS Statistics',
                                        'Bachelor of Elementary Education',
                                        'Bachelor of Secondary Education - English',
                                        'Bachelor of Secondary Education - Mathematics',
                                        'Bachelor of Secondary Education - Science',
                                        'Bachelor of Secondary Education - Social Studies',
                                        'Bachelor of Physical Education',
                                        'Bachelor of Technology and Livelihood Education',
                                        'BS Nursing',
                                        'BS Midwifery',
                                        'BS Medical Technology',
                                        'BS Pharmacy',
                                        'BS Physical Therapy',
                                        'BS Occupational Therapy',
                                        'BS Agriculture',
                                        'BS Agricultural Engineering',
                                        'BS Food Technology',
                                        'BS Fisheries',
                                        'BS Forestry',
                                        'BS Veterinary Medicine',
                                        'Bachelor of Laws (LLB)',
                                        'Juris Doctor (JD)',
                                        'Master of Arts in Education',
                                        'Master of Science in Engineering',
                                        'Master of Business Administration',
                                        'Doctor of Philosophy',
                                        'Other'
                                    ]) && !empty($user['course'])): ?>
                                        <option value="<?php echo htmlspecialchars($user['course']); ?>" selected><?php echo htmlspecialchars($user['course']); ?> (Legacy)</option>
                                    <?php endif; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="form__group">
                            <label for="year_level" class="form__label">Year Level</label>
                            <?php if ($user['role'] === 'admin'): ?>
                                <input type="text" id="year_level" name="year_level" class="form__input readonly-info"
                                    value="<?php echo htmlspecialchars($user['year_level']); ?>" readonly>
                            <?php else: ?>
                                <select id="year_level" name="year_level" class="form__select" required>
                                    <option value="">Select year level</option>
                                    <option value="1st Year" <?php echo $user['year_level'] === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo $user['year_level'] === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo $user['year_level'] === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo $user['year_level'] === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                                    <option value="5th Year" <?php echo $user['year_level'] === '5th Year' ? 'selected' : ''; ?>>5th Year</option>
                                    <option value="Graduate" <?php echo $user['year_level'] === 'Graduate' ? 'selected' : ''; ?>>Graduate</option>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="form__group">
                            <label for="section" class="form__label">Section</label>
                            <?php if ($user['role'] === 'admin'): ?>
                                <input type="text" id="section" name="section" class="form__input readonly-info"
                                    value="<?php echo htmlspecialchars($user['section']); ?>" readonly>
                            <?php else: ?>
                                <select id="section" name="section" class="form__select" required>
                                    <option value="">Select section</option>
                                    <?php for ($i = ord('A'); $i <= ord('Z'); $i++): ?>
                                        <option value="<?php echo chr($i); ?>" <?php echo $user['section'] === chr($i) ? 'selected' : ''; ?>><?php echo chr($i); ?></option>
                                    <?php endfor; ?>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="form__group">
                            <label for="role" class="form__label">Role</label>
                            <input type="text" id="role" name="role" class="form__input readonly-info"
                                value="<?php echo htmlspecialchars(ucfirst($user['role'])); ?>" readonly>
                            <small style="color: var(--text-color); font-size: 0.8rem; margin-top: 0.25rem; display: block;">
                                Role is assigned by administrators
                            </small>
                        </div>
                    </div>

                    <?php if ($user['role'] !== 'admin'): ?>
                        <div class="form__buttons">
                            <button type="submit" name="update_profile" class="btn btn--primary">
                                <i class="ri-save-line"></i>
                                Update Profile
                            </button>
                            <a href="dashboard.php" class="btn btn--secondary">
                                <i class="ri-arrow-left-line"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="form__buttons">
                            <a href="dashboard.php" class="btn btn--secondary">
                                <i class="ri-arrow-left-line"></i>
                                Back to Dashboard
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Additional Actions -->
            <div class="account__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-settings-line"></i>
                    </div>
                    <h2 class="card__title">Account Actions</h2>
                </div>

                <div class="form__buttons">
                    <a href="change_password.php" class="btn btn--primary">
                        <i class="ri-lock-password-line"></i>
                        Change Password
                    </a>
                    <a href="dashboard.php" class="btn btn--secondary">
                        <i class="ri-qr-code-line"></i>
                        View QR Code
                    </a>
                </div>
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
        // Form validation - only for non-admin users
        <?php if ($user['role'] !== 'admin'): ?>
            document.querySelector('form').addEventListener('submit', function(e) {
                const firstName = document.getElementById('first_name').value.trim();
                const lastName = document.getElementById('last_name').value.trim();
                const course = document.getElementById('course').value;
                const yearLevel = document.getElementById('year_level').value;
                const section = document.getElementById('section').value;

                if (!firstName || !lastName || !course || !yearLevel || !section) {
                    e.preventDefault();
                    alert('Please fill in all required fields.');
                    return;
                }

                // Confirm update
                if (!confirm('Are you sure you want to update your profile? Your QR code will be updated automatically.')) {
                    e.preventDefault();
                }
            });
        <?php endif; ?>
    </script>
</body>

</html>