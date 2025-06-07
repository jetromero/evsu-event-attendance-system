<?php
/**
 * Migration script to transfer data from MySQL to Supabase
 * 
 * This script helps migrate existing user data from your MySQL database
 * to your new Supabase setup.
 * 
 * IMPORTANT: Run this script only once and in a controlled environment.
 * Make sure to backup your data before running this migration.
 */

// Include both configurations
require_once 'supabase_config.php';

// Uncomment the MySQL configuration in config.php before running this script
$mysql_host = 'localhost';
$mysql_dbname = 'joyces_db';
$mysql_username = 'root';
$mysql_password = '';

// Function to get MySQL connection
function getMySQLConnection() {
    global $mysql_host, $mysql_dbname, $mysql_username, $mysql_password;
    try {
        $pdo = new PDO("mysql:host=$mysql_host;dbname=$mysql_dbname;charset=utf8mb4", 
                      $mysql_username, $mysql_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("MySQL connection failed: " . $e->getMessage());
    }
}

// Function to migrate users
function migrateUsers() {
    try {
        // Get MySQL connection
        $mysql = getMySQLConnection();
        $supabase = getSupabaseClient();
        
        // Fetch all users from MySQL
        $stmt = $mysql->prepare("SELECT * FROM users ORDER BY created_at");
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $migrated = 0;
        $errors = 0;
        
        echo "Starting user migration...\n";
        echo "Found " . count($users) . " users to migrate.\n\n";
        
        foreach ($users as $user) {
            try {
                echo "Migrating user: " . $user['email'] . "... ";
                
                // First, create the user in Supabase Auth
                // Note: We can't migrate the original password hash, so we'll set a temporary password
                $tempPassword = 'TempPass123!'; // Users will need to reset their password
                
                $authResponse = $supabase->signUp($user['email'], $tempPassword, [
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name']
                ]);
                
                if (isset($authResponse['user']['id'])) {
                    // Create user profile in users table
                    $profileData = [
                        'id' => $authResponse['user']['id'],
                        'email' => $user['email'],
                        'first_name' => $user['first_name'],
                        'last_name' => $user['last_name'],
                        'course' => $user['course'] ?? 'Not Specified', // Default value for migrated users
                        'year_level' => $user['year_level'],
                        'section' => $user['section'],
                        'role' => $user['role']
                    ];
                    
                    $supabase->insert('users', $profileData);
                    
                    echo "SUCCESS\n";
                    $migrated++;
                } else {
                    echo "FAILED - Auth creation failed\n";
                    $errors++;
                }
                
            } catch (Exception $e) {
                echo "ERROR - " . $e->getMessage() . "\n";
                $errors++;
            }
            
            // Small delay to avoid rate limiting
            usleep(500000); // 0.5 seconds
        }
        
        echo "\n=== Migration Summary ===\n";
        echo "Total users: " . count($users) . "\n";
        echo "Successfully migrated: $migrated\n";
        echo "Errors: $errors\n";
        
        if ($errors > 0) {
            echo "\nNote: Users with errors will need to register manually.\n";
        }
        
        echo "\nIMPORTANT: All migrated users have been assigned the temporary password: '$tempPassword'\n";
        echo "Users should be instructed to reset their passwords immediately.\n";
        
    } catch (Exception $e) {
        echo "Migration failed: " . $e->getMessage() . "\n";
    }
}

// Function to verify migration
function verifyMigration() {
    try {
        $mysql = getMySQLConnection();
        $supabase = getSupabaseClient();
        
        // Count users in MySQL
        $stmt = $mysql->prepare("SELECT COUNT(*) as count FROM users");
        $stmt->execute();
        $mysqlCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Count users in Supabase
        $supabaseUsers = $supabase->select('users', 'id');
        $supabaseCount = count($supabaseUsers);
        
        echo "=== Migration Verification ===\n";
        echo "MySQL users: $mysqlCount\n";
        echo "Supabase users: $supabaseCount\n";
        
        if ($mysqlCount == $supabaseCount) {
            echo "✓ User counts match!\n";
        } else {
            echo "⚠ User counts don't match. Some users may not have been migrated.\n";
        }
        
    } catch (Exception $e) {
        echo "Verification failed: " . $e->getMessage() . "\n";
    }
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line for security reasons.\n");
}

echo "=== EVSU Student Portal - MySQL to Supabase Migration ===\n\n";

// Check if Supabase is configured
if (empty($GLOBALS['supabase_url']) || $GLOBALS['supabase_url'] === 'https://your-project-id.supabase.co') {
    die("ERROR: Please configure your Supabase credentials in supabase_config.php first.\n");
}

// Confirm migration
echo "This script will migrate all users from MySQL to Supabase.\n";
echo "Make sure you have:\n";
echo "1. Configured Supabase credentials in supabase_config.php\n";
echo "2. Run the supabase_setup.sql script in your Supabase dashboard\n";
echo "3. Backed up your MySQL database\n\n";

echo "Do you want to proceed? (yes/no): ";
$handle = fopen("php://stdin", "r");
$line = fgets($handle);
fclose($handle);

if (trim(strtolower($line)) !== 'yes') {
    echo "Migration cancelled.\n";
    exit(0);
}

// Run migration
migrateUsers();

// Verify migration
echo "\nRunning verification...\n";
verifyMigration();

echo "\n=== Migration Complete ===\n";
echo "Next steps:\n";
echo "1. Test the login/registration functionality\n";
echo "2. Inform users about the temporary password if applicable\n";
echo "3. Set up password reset functionality\n";
echo "4. Update your production configuration\n";
echo "5. Consider removing this migration script for security\n";
?>
