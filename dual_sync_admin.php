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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'sync_user':
                $userId = trim($_POST['user_id']);
                if (!empty($userId)) {
                    $result = syncUserToSecondary($userId);
                    if ($result['success']) {
                        $notification = $result['message'];
                        $notification_type = 'success';
                    } else {
                        $notification = $result['message'];
                        $notification_type = 'error';
                    }
                } else {
                    $notification = 'Please enter a valid user ID.';
                    $notification_type = 'error';
                }
                break;

            case 'sync_all_users':
                $syncResults = syncAllUsersToSecondary();
                $notification = "Sync completed. Success: {$syncResults['success']}, Failed: {$syncResults['failed']}, Total: {$syncResults['total']}";
                $notification_type = $syncResults['failed'] > 0 ? 'warning' : 'success';
                break;

            case 'test_connections':
                $testResults = testDualSyncConnections();
                $notification = $testResults['message'];
                $notification_type = $testResults['success'] ? 'success' : 'error';
                break;
        }
    }
}

// Function to sync all users to secondary database
function syncAllUsersToSecondary()
{
    global $enable_dual_sync;

    if (!$enable_dual_sync) {
        return ['success' => 0, 'failed' => 0, 'total' => 0, 'message' => 'Dual-sync is disabled'];
    }

    try {
        $supabase = getSupabaseClient();
        $allUsers = $supabase->select('users', '*');

        if (empty($allUsers)) {
            return ['success' => 0, 'failed' => 0, 'total' => 0, 'message' => 'No users found'];
        }

        $success = 0;
        $failed = 0;
        $total = count($allUsers);

        foreach ($allUsers as $user) {
            $result = syncUserToSecondary($user['id']);
            if ($result['success']) {
                $success++;
            } else {
                $failed++;
                error_log("Failed to sync user {$user['id']}: " . $result['message']);
            }
        }

        return ['success' => $success, 'failed' => $failed, 'total' => $total];
    } catch (Exception $e) {
        error_log("Error in syncAllUsersToSecondary: " . $e->getMessage());
        return ['success' => 0, 'failed' => 0, 'total' => 0, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Function to test both database connections
function testDualSyncConnections()
{
    try {
        // Test primary connection
        $supabase = getSupabaseClient();
        $primaryTest = $supabase->select('users', 'id', [], 'id DESC', 1);
        $primaryStatus = is_array($primaryTest) ? 'Connected' : 'Failed';

        // Test secondary connection
        $secondarySupabase = getSecondarySupabaseClient();
        $secondaryTest = $secondarySupabase->select('users', 'id', [], 'id DESC', 1);
        $secondaryStatus = is_array($secondaryTest) ? 'Connected' : 'Failed';

        $success = ($primaryStatus === 'Connected' && $secondaryStatus === 'Connected');
        $message = "Primary DB: $primaryStatus, Secondary DB: $secondaryStatus";

        return ['success' => $success, 'message' => $message];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Connection test failed: ' . $e->getMessage()];
    }
}

// Get sync statistics
function getSyncStatistics()
{
    global $enable_dual_sync;

    if (!$enable_dual_sync) {
        return ['enabled' => false, 'primary_count' => 0, 'secondary_count' => 0, 'sync_diff' => 0];
    }

    try {
        // Count users in primary database
        $supabase = getSupabaseClient();
        $primaryUsers = $supabase->select('users', 'id');
        $primaryCount = is_array($primaryUsers) ? count($primaryUsers) : 0;

        // Count users in secondary database
        $secondarySupabase = getSecondarySupabaseClient();
        $secondaryUsers = $secondarySupabase->select('users', 'id');
        $secondaryCount = is_array($secondaryUsers) ? count($secondaryUsers) : 0;

        return [
            'enabled' => true,
            'primary_count' => $primaryCount,
            'secondary_count' => $secondaryCount,
            'sync_diff' => abs($primaryCount - $secondaryCount)
        ];
    } catch (Exception $e) {
        return ['enabled' => true, 'primary_count' => 0, 'secondary_count' => 0, 'sync_diff' => 0, 'error' => $e->getMessage()];
    }
}

$syncStats = getSyncStatistics();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dual-Sync Admin - EVSU Event Attendance System</title>
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
            padding: 2rem 1.5rem;
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

        /* Cards */
        .admin__card {
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

        /* Statistics */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .stat-card__number {
            font-size: 2rem;
            font-weight: var(--font-semi-bold);
            color: var(--first-color);
            margin-bottom: 0.5rem;
        }

        .stat-card__label {
            color: var(--text-color);
            font-size: 0.9rem;
        }

        /* Forms */
        .form__group {
            margin-bottom: 1.5rem;
        }

        .form__label {
            display: block;
            font-weight: var(--font-medium);
            color: var(--title-color);
            margin-bottom: 0.5rem;
        }

        .form__input {
            width: 100%;
            padding: 1rem;
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
            margin: 0.25rem;
        }

        .btn--primary {
            background: var(--first-color);
            color: white;
        }

        .btn--primary:hover {
            background: var(--first-color-alt);
        }

        .btn--secondary {
            background: #6c757d;
            color: white;
        }

        .btn--warning {
            background: #f39c12;
            color: white;
        }

        .btn--info {
            background: #17a2b8;
            color: white;
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

        /* Status indicators */
        .status {
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
            font-weight: var(--font-semi-bold);
        }

        .status--enabled {
            background: #d3f9d8;
            color: #2b8a3e;
        }

        .status--disabled {
            background: #ffc9c9;
            color: #c92a2a;
        }

        .status--synced {
            background: #d3f9d8;
            color: #2b8a3e;
        }

        .status--out-of-sync {
            background: #ffeaa7;
            color: #e67e22;
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
                    <p>Dual-Sync Administration</p>
                </div>
            </div>
            <nav class="header__nav">
                <ul>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="events.php">Events</a></li>
                    <li><a href="dual_sync_admin.php">Dual-Sync</a></li>
                    <li><a href="dashboard.php?logout=1" class="logout-btn">
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
                <h1 class="page__title">Dual-Sync Administration</h1>
                <p class="page__subtitle">Manage synchronization between primary and secondary Supabase projects</p>
            </div>

            <!-- Notification -->
            <?php if (!empty($notification)): ?>
                <div class="notification notification--<?php echo $notification_type; ?>">
                    <i class="ri-<?php echo $notification_type === 'success' ? 'check' : ($notification_type === 'warning' ? 'error-warning' : 'error-warning'); ?>-line"></i>
                    <?php echo htmlspecialchars($notification); ?>
                </div>
            <?php endif; ?>

            <!-- Sync Statistics -->
            <div class="admin__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-bar-chart-line"></i>
                    </div>
                    <h2 class="card__title">Synchronization Status</h2>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card__number">
                            <span class="status <?php echo $syncStats['enabled'] ? 'status--enabled' : 'status--disabled'; ?>">
                                <?php echo $syncStats['enabled'] ? 'ENABLED' : 'DISABLED'; ?>
                            </span>
                        </div>
                        <div class="stat-card__label">Dual-Sync Status</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card__number"><?php echo number_format($syncStats['primary_count']); ?></div>
                        <div class="stat-card__label">Primary Database Users</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card__number"><?php echo number_format($syncStats['secondary_count']); ?></div>
                        <div class="stat-card__label">Secondary Database Users</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-card__number">
                            <span class="status <?php echo $syncStats['sync_diff'] === 0 ? 'status--synced' : 'status--out-of-sync'; ?>">
                                <?php echo $syncStats['sync_diff']; ?>
                            </span>
                        </div>
                        <div class="stat-card__label">Sync Difference</div>
                    </div>
                </div>

                <?php if (isset($syncStats['error'])): ?>
                    <div class="notification notification--error">
                        <i class="ri-error-warning-line"></i>
                        Error retrieving sync statistics: <?php echo htmlspecialchars($syncStats['error']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Connection Test -->
            <div class="admin__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-wifi-line"></i>
                    </div>
                    <h2 class="card__title">Connection Test</h2>
                </div>

                <p style="margin-bottom: 1rem; color: var(--text-color);">
                    Test the connection to both primary and secondary Supabase databases.
                </p>

                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="test_connections">
                    <button type="submit" class="btn btn--info">
                        <i class="ri-refresh-line"></i>
                        Test Connections
                    </button>
                </form>

                <a href="debug_dual_sync.php" class="btn btn--warning">
                    <i class="ri-bug-line"></i>
                    Debug Utility
                </a>
            </div>

            <!-- Individual User Sync -->
            <div class="admin__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-user-line"></i>
                    </div>
                    <h2 class="card__title">Sync Individual User</h2>
                </div>

                <p style="margin-bottom: 1rem; color: var(--text-color);">
                    Manually sync a specific user to the secondary database. Enter the user ID (UUID) from the primary database.
                </p>

                <form method="POST">
                    <input type="hidden" name="action" value="sync_user">
                    <div class="form__group">
                        <label for="user_id" class="form__label">User ID (UUID)</label>
                        <input type="text" id="user_id" name="user_id" class="form__input"
                            placeholder="e.g., 123e4567-e89b-12d3-a456-426614174000" required>
                    </div>
                    <button type="submit" class="btn btn--primary">
                        <i class="ri-sync-line"></i>
                        Sync User
                    </button>
                </form>
            </div>

            <!-- Bulk Sync -->
            <div class="admin__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-group-line"></i>
                    </div>
                    <h2 class="card__title">Bulk User Sync</h2>
                </div>

                <p style="margin-bottom: 1rem; color: var(--text-color);">
                    Synchronize all users from the primary database to the secondary database. This operation may take some time depending on the number of users.
                </p>

                <div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem;">
                    <strong>⚠️ Warning:</strong> This will attempt to sync all users. Existing users in the secondary database will be updated.
                </div>

                <form method="POST" onsubmit="return confirm('Are you sure you want to sync all users? This may take several minutes.');">
                    <input type="hidden" name="action" value="sync_all_users">
                    <button type="submit" class="btn btn--warning">
                        <i class="ri-refresh-line"></i>
                        Sync All Users
                    </button>
                </form>
            </div>

            <!-- Configuration Info -->
            <div class="admin__card">
                <div class="card__header">
                    <div class="card__icon">
                        <i class="ri-settings-line"></i>
                    </div>
                    <h2 class="card__title">Configuration Information</h2>
                </div>

                <div style="font-family: monospace; background: #f8f9fa; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem;">
                    <strong>Primary Supabase URL:</strong> <?php echo htmlspecialchars($supabase_url); ?><br>
                    <strong>Secondary Supabase URL:</strong> <?php echo htmlspecialchars($supabase_secondary_url); ?><br>
                    <strong>Dual-Sync Enabled:</strong> <?php echo $enable_dual_sync ? 'Yes' : 'No'; ?>
                </div>

                <p style="color: var(--text-color); font-size: 0.9rem;">
                    <strong>Note:</strong> Dual-sync can be enabled/disabled by modifying the <code>$enable_dual_sync</code>
                    variable in <code>supabase_config.php</code>. When disabled, only the primary database will be used.
                </p>
            </div>

            <!-- Back to Dashboard -->
            <div style="text-align: center; margin-top: 2rem;">
                <a href="admin_dashboard.php" class="btn btn--secondary">
                    <i class="ri-arrow-left-line"></i>
                    Back to Admin Dashboard
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
        // Auto-refresh sync statistics every 30 seconds
        setInterval(function() {
            if (!document.querySelector('form')) {
                // Only refresh if no forms are being used
                location.reload();
            }
        }, 30000);

        // Add confirmation for bulk operations
        document.addEventListener('DOMContentLoaded', function() {
            const bulkSyncForm = document.querySelector('form[onsubmit*="sync all users"]');
            if (bulkSyncForm) {
                bulkSyncForm.addEventListener('submit', function(e) {
                    const confirmMsg = 'Are you sure you want to sync all users?\n\n' +
                        'This operation will:\n' +
                        '• Copy all users from primary to secondary database\n' +
                        '• Update existing users in secondary database\n' +
                        '• May take several minutes to complete\n\n' +
                        'Continue?';

                    if (!confirm(confirmMsg)) {
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>

</html>