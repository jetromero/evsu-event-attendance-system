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

// Handle password change form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $notification = 'Please fill in all password fields.';
        $notification_type = 'error';
    } elseif ($new_password !== $confirm_password) {
        $notification = 'New password and confirmation do not match.';
        $notification_type = 'error';
    } elseif (strlen($new_password) < 8) {
        $notification = 'New password must be at least 8 characters long.';
        $notification_type = 'error';
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $new_password)) {
        $notification = 'New password must contain at least one uppercase letter, one lowercase letter, and one number.';
        $notification_type = 'error';
    } elseif ($current_password === $new_password) {
        $notification = 'New password must be different from your current password.';
        $notification_type = 'error';
    } else {
        try {
            // First verify current password
            if (!password_verify($current_password, $user['password'])) {
                $notification = 'Current password is incorrect.';
                $notification_type = 'error';
            } else {
                // Hash the new password
                $hashedNewPassword = password_hash($new_password, PASSWORD_DEFAULT);

                // Update password in primary database
                $supabase = getSupabaseClient();
                $updateData = ['password' => $hashedNewPassword];
                $result = $supabase->update('users', $updateData, ['id' => $user['id']]);

                if ($result) {
                    // Primary database update successful, now sync to secondary database
                    $syncResult = syncUserUpdateToSecondary($user['id'], $updateData);

                    if ($syncResult['success']) {
                        $notification = 'Password changed successfully! You will be logged out shortly for security.';
                        $notification_type = 'success';
                        error_log("Password change with dual-sync successful for user ID: " . $user['id']);
                    } else {
                        // Primary succeeded but secondary failed - still show success but log the issue
                        $notification = 'Password changed successfully! (Note: Secondary sync had issues - see logs)';
                        $notification_type = 'success';
                        error_log("Password change: Primary DB success, Secondary DB sync failed for user ID: " . $user['id'] . " - " . $syncResult['message']);
                    }

                    // Log the user out after successful password change for security
                    $_SESSION['password_changed'] = true;
                } else {
                    $notification = 'Failed to change password. Please try again.';
                    $notification_type = 'error';
                }
            }
        } catch (Exception $e) {
            error_log("Password change error: " . $e->getMessage());
            $notification = 'An error occurred while changing your password. Please try again.';
            $notification_type = 'error';
        }
    }
}

// Function to authenticate user for password verification (removed since we use direct verification)
function authenticateUserForPasswordChange($email, $password)
{
    try {
        $supabase = getSupabaseClient();
        $userData = $supabase->select('users', '*', ['email' => $email]);

        if (empty($userData)) {
            return false;
        }

        $user = $userData[0];
        return password_verify($password, $user['password']);
    } catch (Exception $e) {
        error_log("Password verification error: " . $e->getMessage());
        return false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - EVSU Event Attendance System</title>
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
            max-width: 600px;
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

        /* Password Form */
        .password__card {
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
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form__label {
            display: block;
            font-weight: var(--font-medium);
            color: var(--title-color);
            margin-bottom: 0.5rem;
        }

        .password__input-container {
            position: relative;
        }

        .form__input {
            width: 100%;
            padding: 1rem 3rem 1rem 1rem;
            border: 2px solid #e0e0e0;
            border-radius: 0.5rem;
            font-family: var(--body-font);
            font-size: var(--normal-font-size);
            transition: border-color 0.3s;
        }

        .form__input:focus {
            outline: none;
            border-color: var(--first-color);
        }

        .password__toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-color);
            cursor: pointer;
            font-size: 1.25rem;
            transition: color 0.3s;
        }

        .password__toggle:hover {
            color: var(--first-color);
        }

        .form__help {
            font-size: 0.8rem;
            color: var(--text-color);
            margin-top: 0.5rem;
        }

        .password__requirements {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-top: 1rem;
        }

        .password__requirements h4 {
            color: var(--title-color);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .requirements__list {
            list-style: none;
            font-size: 0.8rem;
        }

        .requirements__item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
            color: var(--text-color);
        }

        .requirements__item.valid {
            color: #28a745;
        }

        .requirements__item.invalid {
            color: #dc3545;
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

        /* Security Info */
        .security__info {
            background: #e3f2fd;
            border: 1px solid #42a5f5;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 2rem;
        }

        .security__info h3 {
            color: #1976d2;
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }

        .security__info p {
            color: #1976d2;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
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
                    <p>Change Password</p>
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
                <h1 class="page__title">Change Password</h1>
                <p class="page__subtitle">Update your account password for better security</p>
            </div>

            <!-- Breadcrumb -->
            <nav class="breadcrumb">
                <div class="breadcrumb__list">
                    <a href="dashboard.php" class="breadcrumb__link">Dashboard</a>
                    <span class="breadcrumb__separator">/</span>
                    <a href="account_details.php" class="breadcrumb__link">Account Details</a>
                    <span class="breadcrumb__separator">/</span>
                    <span class="breadcrumb__current">Change Password</span>
                </div>
            </nav>

            <!-- Security Info -->
            <div class="security__info">
                <h3><i class="ri-shield-check-line"></i> Security Notice</h3>
                <p>For your security, you will be automatically logged out after changing your password.</p>
                <p>You will need to log in again with your new password.</p>
            </div>

            <!-- Notification -->
            <?php if (!empty($notification)): ?>
                <div class="notification notification--<?php echo $notification_type; ?>">
                    <i class="ri-<?php echo $notification_type === 'success' ? 'check' : 'error-warning'; ?>-line"></i>
                    <?php echo htmlspecialchars($notification); ?>
                    <?php if ($notification_type === 'success'): ?>
                        <script>
                            setTimeout(function() {
                                window.location.href = 'dashboard.php?logout=1';
                            }, 3000);
                        </script>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <!-- Password Form -->
            <div class="password__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-lock-password-line"></i>
                    </div>
                    <h2 class="card__title">Change Password</h2>
                </div>

                <form method="POST" action="" id="passwordForm">
                    <div class="form__group">
                        <label for="current_password" class="form__label">Current Password</label>
                        <div class="password__input-container">
                            <input type="password" id="current_password" name="current_password" class="form__input" required>
                            <button type="button" class="password__toggle" onclick="togglePassword('current_password')">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                        <div class="form__help">Enter your current password to verify your identity</div>
                    </div>

                    <div class="form__group">
                        <label for="new_password" class="form__label">New Password</label>
                        <div class="password__input-container">
                            <input type="password" id="new_password" name="new_password" class="form__input" required>
                            <button type="button" class="password__toggle" onclick="togglePassword('new_password')">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                        <div class="form__help">Choose a strong password for better security</div>
                    </div>

                    <div class="form__group">
                        <label for="confirm_password" class="form__label">Confirm New Password</label>
                        <div class="password__input-container">
                            <input type="password" id="confirm_password" name="confirm_password" class="form__input" required>
                            <button type="button" class="password__toggle" onclick="togglePassword('confirm_password')">
                                <i class="ri-eye-line"></i>
                            </button>
                        </div>
                        <div class="form__help">Re-enter your new password to confirm</div>
                    </div>

                    <!-- Password Requirements -->
                    <div class="password__requirements">
                        <h4>Password Requirements:</h4>
                        <ul class="requirements__list">
                            <li class="requirements__item" id="length">
                                <i class="ri-close-circle-line"></i>
                                At least 8 characters long
                            </li>
                            <li class="requirements__item" id="uppercase">
                                <i class="ri-close-circle-line"></i>
                                Contains at least one uppercase letter
                            </li>
                            <li class="requirements__item" id="lowercase">
                                <i class="ri-close-circle-line"></i>
                                Contains at least one lowercase letter
                            </li>
                            <li class="requirements__item" id="number">
                                <i class="ri-close-circle-line"></i>
                                Contains at least one number
                            </li>
                            <li class="requirements__item" id="match">
                                <i class="ri-close-circle-line"></i>
                                Passwords match
                            </li>
                        </ul>
                    </div>

                    <div class="form__buttons">
                        <button type="submit" name="change_password" class="btn btn--primary" id="submitBtn" disabled>
                            <i class="ri-save-line"></i>
                            Change Password
                        </button>
                        <a href="account_details.php" class="btn btn--secondary">
                            <i class="ri-arrow-left-line"></i>
                            Back to Account
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
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('i');

            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'ri-eye-off-line';
            } else {
                field.type = 'password';
                icon.className = 'ri-eye-line';
            }
        }

        // Password validation
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const submitBtn = document.getElementById('submitBtn');

        function updateRequirement(id, isValid) {
            const element = document.getElementById(id);
            const icon = element.querySelector('i');

            if (isValid) {
                element.classList.add('valid');
                element.classList.remove('invalid');
                icon.className = 'ri-check-circle-line';
            } else {
                element.classList.add('invalid');
                element.classList.remove('valid');
                icon.className = 'ri-close-circle-line';
            }
        }

        function validatePassword() {
            const password = newPassword.value;
            const confirm = confirmPassword.value;

            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);
            const passwordsMatch = password === confirm && password !== '';

            // Update UI
            updateRequirement('length', hasLength);
            updateRequirement('uppercase', hasUppercase);
            updateRequirement('lowercase', hasLowercase);
            updateRequirement('number', hasNumber);
            updateRequirement('match', passwordsMatch);

            // Enable/disable submit button
            const allValid = hasLength && hasUppercase && hasLowercase && hasNumber && passwordsMatch;
            submitBtn.disabled = !allValid;
        }

        // Add event listeners
        newPassword.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);

        // Form submission confirmation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to change your password? You will be logged out after the change.')) {
                e.preventDefault();
            }
        });
    </script>
</body>

</html>