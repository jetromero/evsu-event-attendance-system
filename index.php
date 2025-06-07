<?php
// Start session for user management
session_start();

// Include database configuration
require_once 'supabase_config.php';

// Initialize variables for form handling
$login_error = '';
$register_error = '';
$register_success = '';
$notifications = [];

// Check for session notifications
if (isset($_SESSION['notification'])) {
    $notifications[] = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

// Redirect if already logged in
if (isLoggedIn()) {
    $user = getCurrentUser();
    if ($user && $user['role'] === 'admin') {
        header('Location: admin_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
    exit();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_submit'])) {
    $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];

    // Basic validation
    if (empty($email) || empty($password)) {
        $login_error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $login_error = 'Please enter a valid email address.';
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@evsu\.edu\.ph$/', $email)) {
        $login_error = 'Please use a valid EVSU email address (@evsu.edu.ph).';
    } else {
        try {
            if (authenticateUser($email, $password)) {
                // Login successful - check user role and redirect accordingly
                $user = getCurrentUser();
                error_log("Login successful for: $email, User data: " . json_encode($user));

                // Set session variable to show loader and redirect URL
                $_SESSION['show_loader'] = true;
                $_SESSION['redirect_url'] = ($user && $user['role'] === 'admin') ? 'admin_dashboard.php' : 'dashboard.php';
                $_SESSION['loader_message'] = 'Login successful! Redirecting to dashboard...';

                // Don't redirect immediately - let JavaScript handle it with loader
            } else {
                $login_error = 'Invalid email or password.';
            }
        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            $login_error = 'Login failed. Please try again.';
        }
    }
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_submit'])) {
    $first_name = trim($_POST['names']);
    $last_name = trim($_POST['surnames']);
    $email = filter_var($_POST['emailCreate'], FILTER_SANITIZE_EMAIL);
    $password = $_POST['passwordCreate'];

    $course = trim($_POST['course']);
    $year_level = trim($_POST['year_level']);
    $section = trim($_POST['section']);
    $role = 'student'; // Default role

    // Log registration attempt for debugging
    error_log("Registration attempt - Email: $email, Name: $first_name $last_name, Course: $course, Year: $year_level, Section: $section");

    // Enhanced validation
    if (empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($course) || empty($year_level) || empty($section)) {
        $register_error = 'Please fill in all fields.';
        error_log("Registration validation failed - Missing fields. First: '$first_name', Last: '$last_name', Email: '$email', Course: '$course', Year: '$year_level', Section: '$section'");
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $register_error = 'Please enter a valid email address.';
        error_log("Registration validation failed - Invalid email format: $email");
    } elseif (!preg_match('/^[a-zA-Z0-9._%+-]+@evsu\.edu\.ph$/', $email)) {
        $register_error = 'Please use a valid EVSU email address (@evsu.edu.ph).';
        error_log("Registration validation failed - Not EVSU email: $email");
    } elseif (strlen($password) < 8) {
        $register_error = 'Password must be at least 8 characters long.';
        error_log("Registration validation failed - Password too short for: $email");
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $register_error = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
        error_log("Registration validation failed - Password complexity for: $email");
    } elseif (!preg_match('/^[A-Z]$/', $section)) {
        $register_error = 'Section must be a single letter (A-Z).';
        error_log("Registration validation failed - Invalid section '$section' for: $email");
    } else {
        try {
            // Prepare user data for Supabase registration
            $userData = [
                'email' => $email,
                'password' => $password,
                'first_name' => $first_name,
                'last_name' => $last_name,
                'course' => $course,
                'year_level' => $year_level,
                'section' => $section,
                'role' => $role
            ];

            // Register user with Supabase
            if (registerUser($userData)) {
                // Log successful registration for debugging
                error_log("User registered successfully: $email");

                // Set session variable to show loader and redirect URL
                $_SESSION['show_loader'] = true;
                $_SESSION['redirect_url'] = 'index.php';
                $_SESSION['loader_message'] = 'Account created successfully! Redirecting to login...';
                $_SESSION['notification'] = ['type' => 'success', 'message' => 'Account created successfully! Please log in.'];

                // Don't redirect immediately - let JavaScript handle it with loader
            } else {
                $register_error = 'Registration failed. Please try again.';
            }
        } catch (Exception $e) {
            // Log the actual error for debugging
            error_log("Registration error: " . $e->getMessage());
            $register_error = 'Registration failed. Please try again. If the problem persists, contact support.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!--=============== REMIXICONS ===============-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">

    <!--=============== CSS ===============-->
    <link rel="stylesheet" href="assets/css/styles.css?v=<?php echo time(); ?>">

    <title>Responsive login and registration form - Bedimcode</title>

    <!--=============== LOADER STYLES ===============-->
    <style>
        /* Loader overlay */
        .loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--first-color) 0%, var(--first-color-alt) 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .loader-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        /* Loader animation */
        .loader {
            color: var(--white-color);
            font-size: 30px;
            font-weight: var(--font-semi-bold);
            margin-bottom: 2rem;
            animation: animate8345 9s linear infinite;
        }

        @keyframes animate8345 {

            0%,
            100% {
                filter: hue-rotate(0deg);
            }

            50% {
                filter: hue-rotate(360deg);
            }
        }

        /* Loading text animation */
        .loader-text {
            color: var(--white-color);
            font-size: var(--normal-font-size);
            text-align: center;
            margin-top: 1rem;
            opacity: 0.9;
        }

        /* Spinner */
        .loader-spinner {
            width: 60px;
            height: 60px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid var(--white-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-bottom: 1rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }
    </style>

    <!-- Immediate registration error check -->
    <script>
        // This runs as soon as the script tag is parsed
        document.addEventListener('DOMContentLoaded', function() {
            // Check for registration errors and show registration form immediately
            // ONLY if there was actually a registration attempt (check for filled registration form data)
            const hasRegisterData = document.querySelector('input[name="names"]')?.value ||
                document.querySelector('input[name="surnames"]')?.value ||
                document.querySelector('input[name="emailCreate"]')?.value ||
                document.querySelector('input[name="course"]')?.value ||
                document.querySelector('select[name="year_level"]')?.value ||
                document.querySelector('select[name="section"]')?.value;

            const registerNotifications = document.getElementById('registerNotifications');
            if (registerNotifications && hasRegisterData) {
                const errorNotifications = registerNotifications.querySelectorAll('.notification--error');
                if (errorNotifications.length > 0) {
                    console.log('Inline script: Registration errors detected after registration attempt');
                    const loginAccessRegister = document.getElementById('loginAccessRegister');
                    if (loginAccessRegister) {
                        loginAccessRegister.classList.add('active');
                        console.log('Inline script: Switched to registration form');
                    }
                }
            }
        });
    </script>
</head>

<body>
    <!--=============== LOADER ===============-->
    <div class="loader-overlay" id="loaderOverlay">
        <div class="loader-spinner"></div>
        <div class="loader">loading...</div>
        <div class="loader-text" id="loaderText">Please wait...</div>
    </div>

    <!--=============== LOGIN IMAGE ===============-->
    <svg class="login__blob" viewBox="0 0 566 840" xmlns="http://www.w3.org/2000/svg">
        <mask id="mask0" mask-type="alpha">
            <path d="M342.407 73.6315C388.53 56.4007 394.378 17.3643 391.538 
            0H566V840H0C14.5385 834.991 100.266 804.436 77.2046 707.263C49.6393 
            591.11 115.306 518.927 176.468 488.873C363.385 397.026 156.98 302.824 
            167.945 179.32C173.46 117.209 284.755 95.1699 342.407 73.6315Z" />
        </mask>

        <g mask="url(#mask0)">
            <path d="M342.407 73.6315C388.53 56.4007 394.378 17.3643 391.538 
            0H566V840H0C14.5385 834.991 100.266 804.436 77.2046 707.263C49.6393 
            591.11 115.306 518.927 176.468 488.873C363.385 397.026 156.98 302.824 
            167.945 179.32C173.46 117.209 284.755 95.1699 342.407 73.6315Z" />

            <!-- Insert your image (recommended size: 1000 x 1200) -->
            <image class="login__img" href="assets/img/bg-img.jpg" />
        </g>
    </svg>

    <!--=============== LOGIN ===============-->
    <div class="login container grid" id="loginAccessRegister">
        <!--===== LOGIN ACCESS =====-->
        <div class="login__access">
            <div class="login__title-container">
                <img src="assets/img/evsu-logo.png" alt="EVSU Logo" class="login__logo">
                <h1 class="login__title">Log in to your account.</h1>
            </div>

            <!-- Notification System -->
            <div class="notification-container" id="loginNotifications">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification notification--<?php echo $notification['type']; ?>">
                            <span class="notification__message"><?php echo htmlspecialchars($notification['message']); ?></span>
                            <button class="notification__close" onclick="closeNotification(this)">&times;</button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                <?php if (!empty($login_error)): ?>
                    <div class="notification notification--error">
                        <span class="notification__message"><?php echo htmlspecialchars($login_error); ?></span>
                        <button class="notification__close" onclick="closeNotification(this)">&times;</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="login__area">

                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="login__form">
                    <div class="login__content grid">
                        <div class="login__box">
                            <input type="email" id="email" name="email" required placeholder=" " class="login__input"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                            <label for="email" class="login__label">EVSU Email</label>

                            <i class="ri-mail-fill login__icon"></i>
                        </div>

                        <div class="login__box">
                            <input type="password" id="password" name="password" required placeholder=" " class="login__input" autocomplete="current-password">
                            <label for="password" class="login__label">Password</label>

                            <i class="ri-eye-off-fill login__icon login__password" id="loginPassword"></i>
                        </div>
                    </div>

                    <a href="#" class="login__forgot">Forgot your password?</a>

                    <button type="submit" name="login_submit" class="login__button">Login</button>
                </form>

                <p class="login__switch">
                    Don't have an account?
                    <button id="loginButtonRegister">Create Account</button>
                </p>
            </div>
        </div>

        <!--===== LOGIN REGISTER =====-->
        <div class="login__register">
            <div class="login__title-container">
                <img src="assets/img/evsu-logo.png" alt="EVSU Logo" class="login__logo">
                <h1 class="login__title">Create EVSU account.</h1>
            </div>

            <!-- Notification System -->
            <div class="notification-container" id="registerNotifications">
                <?php if (!empty($register_error)): ?>
                    <div class="notification notification--error">
                        <span class="notification__message"><?php echo htmlspecialchars($register_error); ?></span>
                        <button class="notification__close" onclick="closeNotification(this)">&times;</button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($register_success)): ?>
                    <div class="notification notification--success">
                        <span class="notification__message"><?php echo htmlspecialchars($register_success); ?></span>
                        <button class="notification__close" onclick="closeNotification(this)">&times;</button>
                    </div>
                <?php endif; ?>
            </div>

            <div class="login__area">

                <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" class="login__form">
                    <div class="login__content grid">
                        <div class="login__group grid">
                            <div class="login__box">
                                <input type="text" id="names" name="names" required placeholder=" " class="login__input"
                                    value="<?php echo isset($_POST['names']) ? htmlspecialchars($_POST['names']) : ''; ?>">
                                <label for="names" class="login__label">First Name</label>

                                <i class="ri-id-card-fill login__icon"></i>
                            </div>

                            <div class="login__box">
                                <input type="text" id="surnames" name="surnames" required placeholder=" " class="login__input"
                                    value="<?php echo isset($_POST['surnames']) ? htmlspecialchars($_POST['surnames']) : ''; ?>">
                                <label for="surnames" class="login__label">Last Name</label>

                                <i class="ri-id-card-fill login__icon"></i>
                            </div>
                        </div>

                        <div class="login__box">
                            <input type="email" id="emailCreate" name="emailCreate" required placeholder=" " class="login__input"
                                value="<?php echo isset($_POST['emailCreate']) ? htmlspecialchars($_POST['emailCreate']) : ''; ?>">
                            <label for="emailCreate" class="login__label">EVSU Email</label>

                            <i class="ri-mail-fill login__icon"></i>
                        </div>

                        <div class="login__box">
                            <select id="course" name="course" required class="login__input login__select" placeholder=" ">
                                <option value="" disabled <?php echo !isset($_POST['course']) ? 'selected' : ''; ?>></option>

                                <!-- College of Engineering and Technology -->
                                <optgroup label="College of Engineering and Technology">
                                    <option value="BS Computer Engineering" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Computer Engineering') ? 'selected' : ''; ?>>BS Computer Engineering</option>
                                    <option value="BS Computer Science" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Computer Science') ? 'selected' : ''; ?>>BS Computer Science</option>
                                    <option value="BS Information Technology" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Information Technology') ? 'selected' : ''; ?>>BS Information Technology</option>
                                    <option value="BS Civil Engineering" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Civil Engineering') ? 'selected' : ''; ?>>BS Civil Engineering</option>
                                    <option value="BS Electrical Engineering" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Electrical Engineering') ? 'selected' : ''; ?>>BS Electrical Engineering</option>
                                    <option value="BS Electronics Engineering" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Electronics Engineering') ? 'selected' : ''; ?>>BS Electronics Engineering</option>
                                    <option value="BS Mechanical Engineering" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Mechanical Engineering') ? 'selected' : ''; ?>>BS Mechanical Engineering</option>
                                    <option value="BS Industrial Engineering" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Industrial Engineering') ? 'selected' : ''; ?>>BS Industrial Engineering</option>
                                    <option value="BS Architecture" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Architecture') ? 'selected' : ''; ?>>BS Architecture</option>
                                </optgroup>

                                <!-- College of Business and Management -->
                                <optgroup label="College of Business and Management">
                                    <option value="BS Business Administration" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Business Administration') ? 'selected' : ''; ?>>BS Business Administration</option>
                                    <option value="BS Accountancy" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Accountancy') ? 'selected' : ''; ?>>BS Accountancy</option>
                                    <option value="BS Entrepreneurship" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Entrepreneurship') ? 'selected' : ''; ?>>BS Entrepreneurship</option>
                                    <option value="BS Economics" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Economics') ? 'selected' : ''; ?>>BS Economics</option>
                                    <option value="BS Marketing Management" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Marketing Management') ? 'selected' : ''; ?>>BS Marketing Management</option>
                                    <option value="BS Financial Management" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Financial Management') ? 'selected' : ''; ?>>BS Financial Management</option>
                                    <option value="BS Human Resource Development Management" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Human Resource Development Management') ? 'selected' : ''; ?>>BS Human Resource Development Management</option>
                                </optgroup>

                                <!-- College of Arts and Sciences -->
                                <optgroup label="College of Arts and Sciences">
                                    <option value="AB Communication" <?php echo (isset($_POST['course']) && $_POST['course'] == 'AB Communication') ? 'selected' : ''; ?>>AB Communication</option>
                                    <option value="AB English" <?php echo (isset($_POST['course']) && $_POST['course'] == 'AB English') ? 'selected' : ''; ?>>AB English</option>
                                    <option value="AB History" <?php echo (isset($_POST['course']) && $_POST['course'] == 'AB History') ? 'selected' : ''; ?>>AB History</option>
                                    <option value="AB Philosophy" <?php echo (isset($_POST['course']) && $_POST['course'] == 'AB Philosophy') ? 'selected' : ''; ?>>AB Philosophy</option>
                                    <option value="AB Political Science" <?php echo (isset($_POST['course']) && $_POST['course'] == 'AB Political Science') ? 'selected' : ''; ?>>AB Political Science</option>
                                    <option value="BS Biology" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Biology') ? 'selected' : ''; ?>>BS Biology</option>
                                    <option value="BS Chemistry" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Chemistry') ? 'selected' : ''; ?>>BS Chemistry</option>
                                    <option value="BS Mathematics" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Mathematics') ? 'selected' : ''; ?>>BS Mathematics</option>
                                    <option value="BS Physics" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Physics') ? 'selected' : ''; ?>>BS Physics</option>
                                    <option value="BS Psychology" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Psychology') ? 'selected' : ''; ?>>BS Psychology</option>
                                    <option value="BS Statistics" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Statistics') ? 'selected' : ''; ?>>BS Statistics</option>
                                </optgroup>

                                <!-- College of Education -->
                                <optgroup label="College of Education">
                                    <option value="Bachelor of Elementary Education" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Bachelor of Elementary Education') ? 'selected' : ''; ?>>Bachelor of Elementary Education</option>
                                    <option value="Bachelor of Secondary Education - English" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Bachelor of Secondary Education - English') ? 'selected' : ''; ?>>Bachelor of Secondary Education - English</option>
                                    <option value="Bachelor of Secondary Education - Mathematics" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Bachelor of Secondary Education - Mathematics') ? 'selected' : ''; ?>>Bachelor of Secondary Education - Mathematics</option>
                                    <option value="Bachelor of Secondary Education - Science" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Bachelor of Secondary Education - Science') ? 'selected' : ''; ?>>Bachelor of Secondary Education - Science</option>
                                    <option value="Bachelor of Secondary Education - Social Studies" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Bachelor of Secondary Education - Social Studies') ? 'selected' : ''; ?>>Bachelor of Secondary Education - Social Studies</option>
                                    <option value="Bachelor of Physical Education" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Bachelor of Physical Education') ? 'selected' : ''; ?>>Bachelor of Physical Education</option>
                                    <option value="Bachelor of Technology and Livelihood Education" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Bachelor of Technology and Livelihood Education') ? 'selected' : ''; ?>>Bachelor of Technology and Livelihood Education</option>
                                </optgroup>

                                <!-- College of Nursing and Health Sciences -->
                                <optgroup label="College of Nursing and Health Sciences">
                                    <option value="BS Nursing" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Nursing') ? 'selected' : ''; ?>>BS Nursing</option>
                                    <option value="BS Midwifery" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Midwifery') ? 'selected' : ''; ?>>BS Midwifery</option>
                                    <option value="BS Medical Technology" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Medical Technology') ? 'selected' : ''; ?>>BS Medical Technology</option>
                                    <option value="BS Pharmacy" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Pharmacy') ? 'selected' : ''; ?>>BS Pharmacy</option>
                                    <option value="BS Physical Therapy" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Physical Therapy') ? 'selected' : ''; ?>>BS Physical Therapy</option>
                                    <option value="BS Occupational Therapy" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Occupational Therapy') ? 'selected' : ''; ?>>BS Occupational Therapy</option>
                                </optgroup>

                                <!-- College of Agriculture and Food Science -->
                                <optgroup label="College of Agriculture and Food Science">
                                    <option value="BS Agriculture" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Agriculture') ? 'selected' : ''; ?>>BS Agriculture</option>
                                    <option value="BS Agricultural Engineering" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Agricultural Engineering') ? 'selected' : ''; ?>>BS Agricultural Engineering</option>
                                    <option value="BS Food Technology" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Food Technology') ? 'selected' : ''; ?>>BS Food Technology</option>
                                    <option value="BS Fisheries" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Fisheries') ? 'selected' : ''; ?>>BS Fisheries</option>
                                    <option value="BS Forestry" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Forestry') ? 'selected' : ''; ?>>BS Forestry</option>
                                    <option value="BS Veterinary Medicine" <?php echo (isset($_POST['course']) && $_POST['course'] == 'BS Veterinary Medicine') ? 'selected' : ''; ?>>BS Veterinary Medicine</option>
                                </optgroup>

                                <!-- College of Law -->
                                <optgroup label="College of Law">
                                    <option value="Bachelor of Laws (LLB)" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Bachelor of Laws (LLB)') ? 'selected' : ''; ?>>Bachelor of Laws (LLB)</option>
                                    <option value="Juris Doctor (JD)" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Juris Doctor (JD)') ? 'selected' : ''; ?>>Juris Doctor (JD)</option>
                                </optgroup>

                                <!-- Graduate Programs -->
                                <optgroup label="Graduate Programs">
                                    <option value="Master of Arts in Education" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Master of Arts in Education') ? 'selected' : ''; ?>>Master of Arts in Education</option>
                                    <option value="Master of Science in Engineering" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Master of Science in Engineering') ? 'selected' : ''; ?>>Master of Science in Engineering</option>
                                    <option value="Master of Business Administration" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Master of Business Administration') ? 'selected' : ''; ?>>Master of Business Administration</option>
                                    <option value="Doctor of Philosophy" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Doctor of Philosophy') ? 'selected' : ''; ?>>Doctor of Philosophy</option>
                                </optgroup>

                                <!-- Other/Unspecified -->
                                <optgroup label="Other">
                                    <option value="Other" <?php echo (isset($_POST['course']) && $_POST['course'] == 'Other') ? 'selected' : ''; ?>>Other/Not Listed</option>
                                </optgroup>
                            </select>
                            <label for="course" class="login__label">Course</label>

                            <i class="ri-book-line login__icon"></i>
                        </div>

                        <div class="login__group grid">
                            <div class="login__box">
                                <select id="year_level" name="year_level" required class="login__input login__select" placeholder=" ">
                                    <option value="" disabled <?php echo !isset($_POST['year_level']) ? 'selected' : ''; ?>></option>
                                    <option value="1st Year" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '1st Year') ? 'selected' : ''; ?>>1st Year</option>
                                    <option value="2nd Year" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '2nd Year') ? 'selected' : ''; ?>>2nd Year</option>
                                    <option value="3rd Year" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '3rd Year') ? 'selected' : ''; ?>>3rd Year</option>
                                    <option value="4th Year" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '4th Year') ? 'selected' : ''; ?>>4th Year</option>
                                    <option value="5th Year" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == '5th Year') ? 'selected' : ''; ?>>5th Year</option>
                                    <option value="Graduate" <?php echo (isset($_POST['year_level']) && $_POST['year_level'] == 'Graduate') ? 'selected' : ''; ?>>Graduate</option>
                                </select>
                                <label for="year_level" class="login__label">Year Level</label>

                                <i class="ri-graduation-cap-fill login__icon"></i>
                            </div>

                            <div class="login__box">
                                <select id="section" name="section" required class="login__input login__select" placeholder=" ">
                                    <option value="" disabled <?php echo !isset($_POST['section']) ? 'selected' : ''; ?>></option>
                                    <option value="A" <?php echo (isset($_POST['section']) && $_POST['section'] == 'A') ? 'selected' : ''; ?>>A</option>
                                    <option value="B" <?php echo (isset($_POST['section']) && $_POST['section'] == 'B') ? 'selected' : ''; ?>>B</option>
                                    <option value="C" <?php echo (isset($_POST['section']) && $_POST['section'] == 'C') ? 'selected' : ''; ?>>C</option>
                                    <option value="D" <?php echo (isset($_POST['section']) && $_POST['section'] == 'D') ? 'selected' : ''; ?>>D</option>
                                    <option value="E" <?php echo (isset($_POST['section']) && $_POST['section'] == 'E') ? 'selected' : ''; ?>>E</option>
                                </select>
                                <label for="section" class="login__label">Section</label>

                                <i class="ri-team-fill login__icon"></i>
                            </div>
                        </div>

                        <div class="login__box">
                            <input type="password" id="passwordCreate" name="passwordCreate" required placeholder=" " class="login__input" autocomplete="new-password">
                            <label for="passwordCreate" class="login__label">Password</label>

                            <i class="ri-eye-off-fill login__icon login__password" id="loginPasswordCreate"></i>
                        </div>
                    </div>

                    <button type="submit" name="register_submit" class="login__button">Create account</button>
                </form>

                <p class="login__switch">
                    Already have an account?
                    <button id="loginButtonAccess">Log In</button>
                </p>
            </div>
        </div>
    </div>

    <!--=============== MAIN JS ===============-->
    <script src="assets/js/main.js"></script>



    <!--=============== LOADER JS ===============-->
    <script>
        // Check if we need to show loader on page load
        <?php if (isset($_SESSION['show_loader']) && $_SESSION['show_loader']): ?>
            document.addEventListener('DOMContentLoaded', function() {
                const loaderOverlay = document.getElementById('loaderOverlay');
                const loaderText = document.getElementById('loaderText');

                // Set the loader message
                loaderText.textContent = '<?php echo addslashes($_SESSION['loader_message'] ?? 'Please wait...'); ?>';

                // Show the loader
                loaderOverlay.classList.add('show');

                // Redirect after 2 seconds
                setTimeout(function() {
                    window.location.href = '<?php echo $_SESSION['redirect_url']; ?>';
                }, 2000);
            });
        <?php
            // Clear the session variables after use
            unset($_SESSION['show_loader']);
            unset($_SESSION['redirect_url']);
            unset($_SESSION['loader_message']);
        endif;
        ?>

        // Function to show loader on form submission
        function showLoaderOnSubmit(formElement, message) {
            formElement.addEventListener('submit', function(e) {
                const loaderOverlay = document.getElementById('loaderOverlay');
                const loaderText = document.getElementById('loaderText');

                loaderText.textContent = message;
                loaderOverlay.classList.add('show');

                // Allow form to submit normally
                // The PHP will handle the redirect with session variables
            });
        }

        // Add loader to forms when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Only add if we're not already showing the loader
            <?php if (!isset($_SESSION['show_loader']) || !$_SESSION['show_loader']): ?>
                // Find forms by their submit buttons
                const loginButton = document.querySelector('button[name="login_submit"]');
                const registerButton = document.querySelector('button[name="register_submit"]');

                if (loginButton) {
                    const loginForm = loginButton.closest('form');
                    if (loginForm) {
                        showLoaderOnSubmit(loginForm, 'Logging in...');
                    }
                }

                if (registerButton) {
                    const registerForm = registerButton.closest('form');
                    if (registerForm) {
                        showLoaderOnSubmit(registerForm, 'Creating account...');
                    }
                }
            <?php endif; ?>
        });
    </script>

    <!-- Notification Close Function Backup -->
    <script>
        // Backup close function in case main.js doesn't load
        if (typeof closeNotification === 'undefined') {
            console.log('Main closeNotification not found, creating backup');
            window.closeNotification = function(button) {
                console.log('Backup closeNotification called');
                const notification = button.closest('.notification');
                if (notification) {
                    notification.style.transition = 'all 0.3s ease';
                    notification.style.transform = 'translateY(-100%)';
                    notification.style.opacity = '0';
                    notification.style.maxHeight = '0';
                    notification.style.marginBottom = '0';
                    notification.style.paddingTop = '0';
                    notification.style.paddingBottom = '0';

                    setTimeout(() => {
                        if (notification.parentNode) {
                            notification.remove();
                        }
                    }, 300);
                }
            };
        }

        // Test function availability
        console.log('closeNotification function available:', typeof closeNotification === 'function');

        // Additional event listener as backup
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('notification__close')) {
                console.log('Backup event listener triggered');
                if (typeof closeNotification === 'function') {
                    closeNotification(e.target);
                }
            }
        });
    </script>
</body>

</html>