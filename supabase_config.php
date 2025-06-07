<?php
// Set Philippine timezone for all operations
date_default_timezone_set('Asia/Manila');

// Primary Supabase configuration
// Replace these with your actual Supabase project credentials

// Your primary Supabase project URL (found in your Supabase dashboard)
$supabase_url = 'https://tlpllfglbtjxjwdvqxmc.supabase.co';

// Your primary Supabase service role key (needed for custom auth without Supabase Auth)
// NOTE: You need to get the service role key from your primary Supabase project under Settings > API
$supabase_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InRscGxsZmdsYnRqeGp3ZHZxeG1jIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc0ODUzNzQxNywiZXhwIjoyMDY0MTEzNDE3fQ.C0aXhl7u8dfTJPXvtu7i9KGJpfJKBWxfvAqnMYmBH2Q';

// Secondary Supabase configuration for dual-sync
// This is used for syncing user registration data to a backup/secondary project

// Your secondary Supabase project URL
$supabase_secondary_url = 'https://zegomgvvlgdijepeyzjp.supabase.co';

// Your secondary Supabase service role key (needed for auth bypass on secondary project)
$supabase_secondary_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InplZ29tZ3Z2bGdkaWplcGV5empwIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc0ODQ1Njc0MywiZXhwIjoyMDY0MDMyNzQzfQ.nhQRHOrZra-0JBLe-AmgGURY89AWrGVKSnkhI0GTnrY';

// Flag to enable/disable dual-sync (set to false to disable secondary sync)
$enable_dual_sync = true;

// Include the autoloader if using composer
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// Include our custom Supabase client
require_once __DIR__ . '/src/SupabaseClient.php';

use App\SupabaseClient;

// Create primary Supabase client instance
function getSupabaseClient()
{
    global $supabase_url, $supabase_key;
    return new SupabaseClient($supabase_url, $supabase_key);
}

// Create secondary Supabase client instance for dual-sync
function getSecondarySupabaseClient()
{
    global $supabase_secondary_url, $supabase_secondary_key;
    return new SupabaseClient($supabase_secondary_url, $supabase_secondary_key);
}

// Function to check if user is logged in (using session)
function isLoggedIn()
{
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && isset($_SESSION['user_id']);
}

// Function to require login
function requireLogin()
{
    if (!isLoggedIn()) {
        header('Location: index.php');
        exit();
    }
}

// Function to get current user data
function getCurrentUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    try {
        $supabase = getSupabaseClient();

        // Get user data from users table by ID
        $userData = $supabase->select('users', '*', ['id' => $_SESSION['user_id']]);

        if (empty($userData)) {
            error_log("User not found for ID: " . $_SESSION['user_id']);
            return null;
        }

        return $userData[0];
    } catch (Exception $e) {
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

// Function to create user profile directly in users table (no separate auth)
function createUserProfile($userData)
{
    global $enable_dual_sync;

    try {
        $supabase = getSupabaseClient();

        // Hash the password
        $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);

        $profileData = [
            'email' => $userData['email'],
            'password' => $hashedPassword,
            'first_name' => $userData['first_name'],
            'last_name' => $userData['last_name'],
            'course' => $userData['course'],
            'year_level' => $userData['year_level'],
            'section' => $userData['section'],
            'role' => $userData['role'] ?? 'student'
        ];

        // Insert into primary database
        $primaryResult = $supabase->insert('users', $profileData);

        if (!$primaryResult) {
            throw new Exception("Failed to create user profile in primary database");
        }

        // Get the inserted user's ID for dual-sync
        $insertedUser = $supabase->select('users', '*', ['email' => $userData['email']]);
        $primaryUserId = $insertedUser[0]['id'] ?? null;

        // Sync to secondary database if enabled
        if ($enable_dual_sync && $primaryUserId) {
            try {
                error_log("DUAL_SYNC: Starting secondary database sync for user: " . $userData['email']);
                error_log("DUAL_SYNC: Secondary URL: " . $GLOBALS['supabase_secondary_url']);

                $secondarySupabase = getSecondarySupabaseClient();
                error_log("DUAL_SYNC: Secondary client created successfully");

                // Test connection first
                $testQuery = $secondarySupabase->select('users', 'id', [], 'id DESC', 1);
                error_log("DUAL_SYNC: Connection test result: " . (is_array($testQuery) ? 'SUCCESS' : 'FAILED'));

                // For secondary sync, we need to match the structure exactly
                $secondaryResult = $secondarySupabase->insert('users', $profileData);
                error_log("DUAL_SYNC: Insert result: " . json_encode($secondaryResult));

                if ($secondaryResult) {
                    error_log("DUAL_SYNC: SUCCESS - User profile synced successfully to secondary database for user: " . $userData['email']);
                } else {
                    error_log("DUAL_SYNC: WARNING - Failed to sync user profile to secondary database for user: " . $userData['email'] . " - No result returned");
                    // Don't throw exception here - primary registration was successful
                }
            } catch (Exception $syncError) {
                error_log("DUAL_SYNC: ERROR - Exception syncing user profile to secondary database: " . $syncError->getMessage());
                error_log("DUAL_SYNC: ERROR - Full stack trace: " . $syncError->getTraceAsString());
                // Log the error but don't fail the entire registration
            }
        } else {
            if (!$enable_dual_sync) {
                error_log("DUAL_SYNC: DISABLED - Skipping secondary database sync");
            } else {
                error_log("DUAL_SYNC: ERROR - Could not get primary user ID for sync");
            }
        }

        return $primaryResult;
    } catch (Exception $e) {
        error_log("Error creating user profile: " . $e->getMessage());
        throw $e;
    }
}

// Function to authenticate user with custom authentication (no Supabase Auth)
function authenticateUser($email, $password)
{
    try {
        $supabase = getSupabaseClient();

        // Get user by email from users table
        $userData = $supabase->select('users', '*', ['email' => $email]);

        if (empty($userData)) {
            error_log("Login failed for $email - user not found");
            return false;
        }

        $user = $userData[0];

        // Verify password using PHP's password_verify
        if (password_verify($password, $user['password'])) {
            // Set session data
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['logged_in'] = true;

            error_log("Login successful for: $email (ID: " . $user['id'] . ")");
            return true;
        } else {
            error_log("Login failed for $email - invalid password");
            return false;
        }
    } catch (Exception $e) {
        error_log("Authentication error for $email: " . $e->getMessage());
        return false;
    }
}

// Function to register user with dual-sync to both databases (no Supabase Auth)
function registerUser($userData)
{
    global $enable_dual_sync;

    try {
        // Log registration attempt
        error_log("Attempting to register user: " . $userData['email']);

        // Check if user already exists
        $supabase = getSupabaseClient();
        $existingUser = $supabase->select('users', '*', ['email' => $userData['email']]);

        if (!empty($existingUser)) {
            error_log("Registration failed: User already exists with email " . $userData['email']);
            throw new Exception("User with this email already exists");
        }

        // Create user profile in primary database (includes secondary sync if enabled)
        error_log("Creating user profile for email: " . $userData['email']);
        $result = createUserProfile($userData);

        if ($result) {
            error_log("User registration completed successfully for: " . $userData['email']);
            return true;
        } else {
            error_log("User registration failed for: " . $userData['email']);
            return false;
        }
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        throw $e;
    }
}

// Function to manually sync existing user to secondary database
function syncUserToSecondary($userId)
{
    global $enable_dual_sync;

    if (!$enable_dual_sync) {
        return ['success' => false, 'message' => 'Dual-sync is disabled'];
    }

    try {
        // Get user data from primary database
        $supabase = getSupabaseClient();
        $userData = $supabase->select('users', '*', ['id' => $userId]);

        if (empty($userData)) {
            return ['success' => false, 'message' => 'User not found in primary database'];
        }

        $user = $userData[0];

        // Insert into secondary database
        $secondarySupabase = getSecondarySupabaseClient();

        // Check if user already exists in secondary database by email
        $existingUser = $secondarySupabase->select('users', '*', ['email' => $user['email']]);

        if (!empty($existingUser)) {
            // Update existing user
            $syncResult = $secondarySupabase->update('users', [
                'email' => $user['email'],
                'password' => $user['password'], // Include password in sync
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'course' => $user['course'],
                'year_level' => $user['year_level'],
                'section' => $user['section'],
                'role' => $user['role']
            ], ['email' => $user['email']]);

            $action = 'updated';
        } else {
            // Insert new user (remove id to let serial auto-increment)
            $userDataForSync = $user;
            unset($userDataForSync['id']); // Remove primary ID, let secondary generate its own

            $syncResult = $secondarySupabase->insert('users', $userDataForSync);
            $action = 'inserted';
        }

        if ($syncResult) {
            error_log("User {$user['email']} (Primary ID: $userId) successfully $action in secondary database");
            return ['success' => true, 'message' => "User {$user['email']} $action in secondary database"];
        } else {
            return ['success' => false, 'message' => "Failed to sync user to secondary database"];
        }
    } catch (Exception $e) {
        error_log("Error syncing user to secondary database: " . $e->getMessage());
        return ['success' => false, 'message' => 'Sync error: ' . $e->getMessage()];
    }
}

// Function to sync user profile updates to secondary database
function syncUserUpdateToSecondary($userId, $updateData)
{
    global $enable_dual_sync;

    if (!$enable_dual_sync) {
        return ['success' => false, 'message' => 'Dual-sync is disabled'];
    }

    try {
        // Get user data from primary database to get the email (needed for secondary lookup)
        $supabase = getSupabaseClient();
        $userData = $supabase->select('users', '*', ['id' => $userId]);

        if (empty($userData)) {
            return ['success' => false, 'message' => 'User not found in primary database'];
        }

        $user = $userData[0];
        $userEmail = $user['email'];

        // Update in secondary database using email as identifier
        $secondarySupabase = getSecondarySupabaseClient();

        // Check if user exists in secondary database
        $existingUser = $secondarySupabase->select('users', '*', ['email' => $userEmail]);

        if (empty($existingUser)) {
            // User doesn't exist in secondary, create full sync
            error_log("DUAL_SYNC: User $userEmail not found in secondary DB, performing full sync");
            return syncUserToSecondary($userId);
        } else {
            // Update existing user in secondary database
            $syncResult = $secondarySupabase->update('users', $updateData, ['email' => $userEmail]);

            if ($syncResult) {
                error_log("DUAL_SYNC: User profile update synced successfully to secondary database for user: $userEmail");
                return ['success' => true, 'message' => "Profile updated in both databases"];
            } else {
                error_log("DUAL_SYNC: Failed to sync user profile update to secondary database for user: $userEmail");
                return ['success' => false, 'message' => "Failed to sync profile update to secondary database"];
            }
        }
    } catch (Exception $e) {
        error_log("DUAL_SYNC: Error syncing user profile update to secondary database: " . $e->getMessage());
        return ['success' => false, 'message' => 'Sync error: ' . $e->getMessage()];
    }
}

// Function to logout user
function logoutUser()
{
    // Clear all session data
    $_SESSION = array();

    // Destroy the session
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}
