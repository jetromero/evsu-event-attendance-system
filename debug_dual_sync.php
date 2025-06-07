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

$debug_output = [];
$test_results = [];

function addDebugOutput($message, $type = 'info')
{
    global $debug_output;
    $timestamp = date('Y-m-d H:i:s');
    $debug_output[] = [
        'timestamp' => $timestamp,
        'type' => $type,
        'message' => $message
    ];
    error_log("DEBUG_DUAL_SYNC [$timestamp] [$type]: $message");
}

// Test dual-sync configuration
function testDualSyncConfiguration()
{
    global $supabase_url, $supabase_key, $supabase_secondary_url, $supabase_secondary_key, $enable_dual_sync;

    addDebugOutput("=== DUAL-SYNC CONFIGURATION TEST ===", 'header');

    addDebugOutput("Primary URL: " . $supabase_url);
    addDebugOutput("Primary Key: " . substr($supabase_key, 0, 20) . "...");
    addDebugOutput("Primary Key Type: " . (strpos($supabase_key, 'service_role') !== false ? 'SERVICE_ROLE' : 'ANON'));
    addDebugOutput("Secondary URL: " . $supabase_secondary_url);
    addDebugOutput("Secondary Key: " . substr($supabase_secondary_key, 0, 20) . "...");
    addDebugOutput("Secondary Key Type: " . (strpos($supabase_secondary_key, 'service_role') !== false ? 'SERVICE_ROLE' : 'ANON'));
    addDebugOutput("Dual-Sync Enabled: " . ($enable_dual_sync ? 'YES' : 'NO'));

    return [
        'primary_configured' => !empty($supabase_url) && !empty($supabase_key),
        'secondary_configured' => !empty($supabase_secondary_url) && !empty($supabase_secondary_key),
        'dual_sync_enabled' => $enable_dual_sync
    ];
}

// Test database connections
function testDatabaseConnections()
{
    addDebugOutput("=== DATABASE CONNECTION TEST ===", 'header');

    $results = ['primary' => false, 'secondary' => false];

    // Test primary connection
    try {
        addDebugOutput("Testing primary database connection...");
        $supabase = getSupabaseClient();

        // First, test basic connection
        addDebugOutput("Testing basic connection to primary database...");
        $primaryTest = $supabase->select('users', 'id, email', [], 'id DESC', 1);

        if (is_array($primaryTest)) {
            addDebugOutput("Primary database: CONNECTION SUCCESS", 'success');

            // Get total count
            $allUsers = $supabase->select('users', 'id');
            $userCount = is_array($allUsers) ? count($allUsers) : 0;
            addDebugOutput("Primary users count: " . $userCount);

            // Test table structure
            addDebugOutput("Checking primary table structure...");
            if (!empty($primaryTest)) {
                $firstUser = $primaryTest[0];
                addDebugOutput("Primary table fields: " . implode(', ', array_keys($firstUser)));
                addDebugOutput("Sample user ID type: " . gettype($firstUser['id']) . " (value: " . $firstUser['id'] . ")");
            } else {
                addDebugOutput("Primary table is empty - this is normal for a fresh setup");
            }

            $results['primary'] = true;
        } else {
            addDebugOutput("Primary database: CONNECTION FAILED - No data returned", 'error');
            addDebugOutput("Response type: " . gettype($primaryTest) . ", Value: " . json_encode($primaryTest));
        }
    } catch (Exception $e) {
        addDebugOutput("Primary database: CONNECTION ERROR - " . $e->getMessage(), 'error');
        addDebugOutput("Primary database: Error type - " . get_class($e), 'error');
        addDebugOutput("Primary database: Full error trace - " . $e->getTraceAsString(), 'error');
    }

    // Test secondary connection
    try {
        addDebugOutput("Testing secondary database connection...");
        $secondarySupabase = getSecondarySupabaseClient();

        addDebugOutput("Testing basic connection to secondary database...");
        $secondaryTest = $secondarySupabase->select('users', 'id, email', [], 'id DESC', 1);

        if (is_array($secondaryTest)) {
            addDebugOutput("Secondary database: CONNECTION SUCCESS", 'success');

            // Get total count
            $allSecondaryUsers = $secondarySupabase->select('users', 'id');
            $secondaryUserCount = is_array($allSecondaryUsers) ? count($allSecondaryUsers) : 0;
            addDebugOutput("Secondary users count: " . $secondaryUserCount);

            // Test table structure
            addDebugOutput("Checking secondary table structure...");
            if (!empty($secondaryTest)) {
                $firstSecondaryUser = $secondaryTest[0];
                addDebugOutput("Secondary table fields: " . implode(', ', array_keys($firstSecondaryUser)));
                addDebugOutput("Sample user ID type: " . gettype($firstSecondaryUser['id']) . " (value: " . $firstSecondaryUser['id'] . ")");
            } else {
                addDebugOutput("Secondary table is empty");
            }

            $results['secondary'] = true;
        } else {
            addDebugOutput("Secondary database: CONNECTION FAILED - No data returned", 'error');
            addDebugOutput("Secondary response type: " . gettype($secondaryTest) . ", Value: " . json_encode($secondaryTest));
        }
    } catch (Exception $e) {
        addDebugOutput("Secondary database: CONNECTION ERROR - " . $e->getMessage(), 'error');
        addDebugOutput("Secondary database: Error type - " . get_class($e), 'error');
        addDebugOutput("Secondary database: Full error trace - " . $e->getTraceAsString(), 'error');
    }

    return $results;
}

// Test user sync functionality
function testUserSync()
{
    addDebugOutput("=== USER SYNC TEST ===", 'header');

    try {
        // Get a test user from primary database
        $supabase = getSupabaseClient();
        $testUsers = $supabase->select('users', '*', [], 'created_at DESC', 1);

        if (empty($testUsers)) {
            addDebugOutput("No test users found in primary database", 'warning');
            return false;
        }

        $testUser = $testUsers[0];
        addDebugOutput("Testing sync for user: " . $testUser['email'] . " (ID: " . $testUser['id'] . ")");

        // Test sync function
        $syncResult = syncUserToSecondary($testUser['id']);

        if ($syncResult['success']) {
            addDebugOutput("Sync test: SUCCESS - " . $syncResult['message'], 'success');
            return true;
        } else {
            addDebugOutput("Sync test: FAILED - " . $syncResult['message'], 'error');
            return false;
        }
    } catch (Exception $e) {
        addDebugOutput("Sync test: ERROR - " . $e->getMessage(), 'error');
        return false;
    }
}

// Test table structure comparison
function testTableStructures()
{
    addDebugOutput("=== TABLE STRUCTURE COMPARISON ===", 'header');

    try {
        // This is a simplified test - in a real scenario you'd check actual table schemas
        $supabase = getSupabaseClient();
        $secondarySupabase = getSecondarySupabaseClient();

        // Get sample records to compare structure
        $primarySample = $supabase->select('users', '*', [], 'id DESC', 1);
        $secondarySample = $secondarySupabase->select('users', '*', [], 'id DESC', 1);

        if (!empty($primarySample) && !empty($secondarySample)) {
            $primaryFields = array_keys($primarySample[0]);
            $secondaryFields = array_keys($secondarySample[0]);

            $missingInSecondary = array_diff($primaryFields, $secondaryFields);
            $extraInSecondary = array_diff($secondaryFields, $primaryFields);

            addDebugOutput("Primary table fields: " . implode(', ', $primaryFields));
            addDebugOutput("Secondary table fields: " . implode(', ', $secondaryFields));

            if (empty($missingInSecondary) && empty($extraInSecondary)) {
                addDebugOutput("Table structures: MATCH", 'success');
                return true;
            } else {
                if (!empty($missingInSecondary)) {
                    addDebugOutput("Missing in secondary: " . implode(', ', $missingInSecondary), 'warning');
                }
                if (!empty($extraInSecondary)) {
                    addDebugOutput("Extra in secondary: " . implode(', ', $extraInSecondary), 'warning');
                }
                return false;
            }
        } else {
            addDebugOutput("Cannot compare structures - missing sample data", 'warning');
            return false;
        }
    } catch (Exception $e) {
        addDebugOutput("Structure comparison: ERROR - " . $e->getMessage(), 'error');
        return false;
    }
}

// Test manual insertion
function testManualInsertion()
{
    addDebugOutput("=== MANUAL INSERTION TEST ===", 'header');

    try {
        $testData = [
            'email' => 'test-' . uniqid() . '@test.com',
            'password' => password_hash('testpassword123', PASSWORD_DEFAULT),
            'first_name' => 'Test',
            'last_name' => 'User',
            'course' => 'Test Course',
            'year_level' => '1st Year',
            'section' => 'A',
            'role' => 'student'
        ];

        addDebugOutput("Testing manual insertion with test data...");
        addDebugOutput("Test email: " . $testData['email']);
        addDebugOutput("Test data structure: " . json_encode($testData));

        // Test primary insertion
        addDebugOutput("Attempting insertion into primary database...");
        $supabase = getSupabaseClient();
        $primaryResult = $supabase->insert('users', $testData);
        addDebugOutput("Primary insert result: " . json_encode($primaryResult));

        if ($primaryResult) {
            addDebugOutput("Primary insertion: SUCCESS", 'success');

            // Test secondary insertion
            addDebugOutput("Attempting insertion into secondary database...");
            $secondarySupabase = getSecondarySupabaseClient();
            $secondaryResult = $secondarySupabase->insert('users', $testData);
            addDebugOutput("Secondary insert result: " . json_encode($secondaryResult));

            if ($secondaryResult) {
                addDebugOutput("Secondary insertion: SUCCESS", 'success');

                // Clean up test data by email
                $supabase->delete('users', ['email' => $testData['email']]);
                $secondarySupabase->delete('users', ['email' => $testData['email']]);
                addDebugOutput("Test data cleaned up");

                return true;
            } else {
                addDebugOutput("Secondary insertion: FAILED", 'error');
                // Clean up primary
                $supabase->delete('users', ['email' => $testData['email']]);
                return false;
            }
        } else {
            addDebugOutput("Primary insertion: FAILED", 'error');
            return false;
        }
    } catch (Exception $e) {
        addDebugOutput("Manual insertion test: ERROR - " . $e->getMessage(), 'error');
        addDebugOutput("Manual insertion test: Stack trace - " . $e->getTraceAsString(), 'error');
        return false;
    }
}

// Run all tests if requested
if (isset($_POST['run_tests'])) {
    addDebugOutput("Starting comprehensive dual-sync diagnostic...", 'header');

    $test_results['configuration'] = testDualSyncConfiguration();
    $test_results['connections'] = testDatabaseConnections();
    $test_results['table_structures'] = testTableStructures();
    $test_results['user_sync'] = testUserSync();
    $test_results['manual_insertion'] = testManualInsertion();

    addDebugOutput("=== DIAGNOSTIC COMPLETE ===", 'header');
}

// Test specific user creation if requested
if (isset($_POST['test_user_creation'])) {
    addDebugOutput("=== TESTING USER CREATION FLOW ===", 'header');

    $testUserData = [
        'email' => 'debug-test-' . uniqid() . '@evsu.edu.ph',
        'password' => 'TestPassword123!',
        'first_name' => 'Debug',
        'last_name' => 'Test',
        'course' => 'BS Computer Science',
        'year_level' => '1st Year',
        'section' => 'A',
        'role' => 'student'
    ];

    addDebugOutput("Creating test user: " . $testUserData['email']);

    try {
        $result = registerUser($testUserData);
        if ($result) {
            addDebugOutput("User creation test: SUCCESS", 'success');
        } else {
            addDebugOutput("User creation test: FAILED", 'error');
        }
    } catch (Exception $e) {
        addDebugOutput("User creation test: ERROR - " . $e->getMessage(), 'error');
    }
}

// Test profile update sync if requested
if (isset($_POST['test_profile_update'])) {
    addDebugOutput("=== TESTING PROFILE UPDATE SYNC ===", 'header');

    try {
        // Get a test user from primary database
        $supabase = getSupabaseClient();
        $testUsers = $supabase->select('users', '*', [], 'created_at DESC', 1);

        if (empty($testUsers)) {
            addDebugOutput("No test users found in primary database for update test", 'warning');
        } else {
            $testUser = $testUsers[0];
            addDebugOutput("Testing profile update sync for user: " . $testUser['email'] . " (ID: " . $testUser['id'] . ")");

            // Create test update data
            $updateData = [
                'first_name' => $testUser['first_name'] . '_Updated',
                'course' => 'BS Information Technology', // Change course
                'year_level' => '2nd Year' // Change year level
            ];

            addDebugOutput("Update data: " . json_encode($updateData));

            // Test the update sync function
            $syncResult = syncUserUpdateToSecondary($testUser['id'], $updateData);

            if ($syncResult['success']) {
                addDebugOutput("Profile update sync test: SUCCESS - " . $syncResult['message'], 'success');

                // Revert the changes for clean testing
                $revertData = [
                    'first_name' => $testUser['first_name'],
                    'course' => $testUser['course'],
                    'year_level' => $testUser['year_level']
                ];

                addDebugOutput("Reverting test changes...");
                $revertResult = syncUserUpdateToSecondary($testUser['id'], $revertData);

                if ($revertResult['success']) {
                    addDebugOutput("Test data reverted successfully", 'success');
                } else {
                    addDebugOutput("Warning: Could not revert test data - " . $revertResult['message'], 'warning');
                }
            } else {
                addDebugOutput("Profile update sync test: FAILED - " . $syncResult['message'], 'error');
            }
        }
    } catch (Exception $e) {
        addDebugOutput("Profile update sync test: ERROR - " . $e->getMessage(), 'error');
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dual-Sync Debug - EVSU Event Attendance System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.css">
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .debug-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        .debug-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
        }

        .debug-output {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            max-height: 500px;
            overflow-y: auto;
            margin: 1rem 0;
        }

        .debug-line {
            margin: 0.25rem 0;
            padding: 0.25rem;
            border-radius: 0.25rem;
        }

        .debug-line.header {
            background: #e3f2fd;
            color: #1976d2;
            font-weight: bold;
        }

        .debug-line.success {
            background: #e8f5e8;
            color: #2e7d32;
        }

        .debug-line.error {
            background: #ffebee;
            color: #c62828;
        }

        .debug-line.warning {
            background: #fff3e0;
            color: #ef6c00;
        }

        .test-buttons {
            display: flex;
            gap: 1rem;
            margin: 1rem 0;
            flex-wrap: wrap;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 500;
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

        .btn--secondary {
            background: #6c757d;
            color: white;
        }

        .btn--warning {
            background: #f39c12;
            color: white;
        }

        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1rem 0;
        }

        .status-item {
            padding: 1rem;
            border-radius: 0.5rem;
            text-align: center;
        }

        .status-item.success {
            background: #d4edda;
            color: #155724;
        }

        .status-item.error {
            background: #f8d7da;
            color: #721c24;
        }

        .status-item.warning {
            background: #fff3cd;
            color: #856404;
        }
    </style>
</head>

<body>
    <header class="header">
        <div class="header__container">
            <div class="header__logo">
                <img src="assets/img/evsu-logo.png" alt="EVSU Logo">
                <div>
                    <h2 class="header__title">EVSU Event Attendance</h2>
                    <p>Dual-Sync Debug Utility</p>
                </div>
            </div>
            <nav class="header__nav">
                <ul>
                    <li><a href="admin_dashboard.php">Dashboard</a></li>
                    <li><a href="dual_sync_admin.php">Dual-Sync</a></li>
                    <li><a href="dashboard.php?logout=1" class="logout-btn">
                            <i class="ri-logout-box-line"></i> Logout
                        </a></li>
                </ul>
            </nav>
        </div>
    </header>

    <main class="debug-container">
        <div class="debug-card">
            <h1><i class="ri-bug-line"></i> Dual-Sync Debug Utility</h1>
            <p>This tool helps diagnose issues with dual-sync user registration functionality.</p>

            <div class="test-buttons">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="run_tests" class="btn btn--primary">
                        <i class="ri-play-line"></i> Run Full Diagnostic
                    </button>
                </form>

                <form method="POST" style="display: inline;">
                    <button type="submit" name="test_user_creation" class="btn btn--warning">
                        <i class="ri-user-add-line"></i> Test User Creation
                    </button>
                </form>

                <form method="POST" style="display: inline;">
                    <button type="submit" name="test_profile_update" class="btn btn--warning">
                        <i class="ri-user-settings-line"></i> Test Profile Update Sync
                    </button>
                </form>

                <a href="dual_sync_admin.php" class="btn btn--secondary">
                    <i class="ri-settings-line"></i> Sync Admin
                </a>
            </div>

            <?php if (!empty($test_results)): ?>
                <div class="status-grid">
                    <div class="status-item <?php echo $test_results['configuration']['dual_sync_enabled'] ? 'success' : 'error'; ?>">
                        <strong>Configuration</strong><br>
                        <?php echo $test_results['configuration']['dual_sync_enabled'] ? 'Enabled' : 'Disabled'; ?>
                    </div>

                    <div class="status-item <?php echo $test_results['connections']['primary'] ? 'success' : 'error'; ?>">
                        <strong>Primary DB</strong><br>
                        <?php echo $test_results['connections']['primary'] ? 'Connected' : 'Failed'; ?>
                    </div>

                    <div class="status-item <?php echo $test_results['connections']['secondary'] ? 'success' : 'error'; ?>">
                        <strong>Secondary DB</strong><br>
                        <?php echo $test_results['connections']['secondary'] ? 'Connected' : 'Failed'; ?>
                    </div>

                    <div class="status-item <?php echo $test_results['table_structures'] ? 'success' : 'warning'; ?>">
                        <strong>Table Structure</strong><br>
                        <?php echo $test_results['table_structures'] ? 'Match' : 'Differences'; ?>
                    </div>

                    <div class="status-item <?php echo $test_results['user_sync'] ? 'success' : 'error'; ?>">
                        <strong>User Sync</strong><br>
                        <?php echo $test_results['user_sync'] ? 'Working' : 'Failed'; ?>
                    </div>

                    <div class="status-item <?php echo $test_results['manual_insertion'] ? 'success' : 'error'; ?>">
                        <strong>Manual Insert</strong><br>
                        <?php echo $test_results['manual_insertion'] ? 'Working' : 'Failed'; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($debug_output)): ?>
                <h3>Debug Output</h3>
                <div class="debug-output">
                    <?php foreach ($debug_output as $line): ?>
                        <div class="debug-line <?php echo $line['type']; ?>">
                            [<?php echo $line['timestamp']; ?>] <?php echo htmlspecialchars($line['message']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div style="margin-top: 2rem;">
                <h3>Quick Configuration Check</h3>
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 0.5rem; font-family: monospace; font-size: 0.9rem;">
                    <strong>Primary URL:</strong> <?php echo htmlspecialchars($supabase_url); ?><br>
                    <strong>Secondary URL:</strong> <?php echo htmlspecialchars($supabase_secondary_url); ?><br>
                    <strong>Dual-Sync:</strong> <?php echo $enable_dual_sync ? 'ENABLED' : 'DISABLED'; ?><br>
                    <strong>Primary Key:</strong> <?php echo substr($supabase_key, 0, 20) . '...'; ?><br>
                    <strong>Secondary Key:</strong> <?php echo substr($supabase_secondary_key, 0, 20) . '...'; ?>
                </div>
            </div>

            <div style="margin-top: 2rem;">
                <h3>Common Issues & Solutions</h3>
                <ul style="margin-left: 2rem;">
                    <li><strong>Connection Failed:</strong> Check Supabase URLs and keys</li>
                    <li><strong>Permission Denied:</strong> Ensure service role key is used for secondary DB</li>
                    <li><strong>Table Not Found:</strong> Create users table in secondary project</li>
                    <li><strong>RLS Blocked:</strong> Add service role policy to secondary DB</li>
                    <li><strong>Sync Disabled:</strong> Set $enable_dual_sync = true in config</li>
                </ul>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="dual_sync_admin.php" class="btn btn--secondary">
                <i class="ri-arrow-left-line"></i> Back to Dual-Sync Admin
            </a>
        </div>
    </main>
</body>

</html>