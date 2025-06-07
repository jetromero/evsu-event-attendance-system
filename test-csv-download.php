<?php
// Simple CSV download test
session_start();
require_once 'supabase_config.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>CSV Download Test</h1>";

// Test 1: Simple CSV download
if (isset($_GET['test']) && $_GET['test'] === 'simple') {
    echo "<h2>Starting Simple CSV Download...</h2>";
    
    // Clean output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Sample data
    $data = [
        ['Name', 'Email', 'Course'],
        ['John Doe', 'john@example.com', 'Computer Science'],
        ['Jane Smith', 'jane@example.com', 'Information Technology']
    ];
    
    $filename = 'simple_test_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Create file handle
    $output = fopen('php://output', 'w');
    
    if ($output === false) {
        die('Error: Cannot create output stream');
    }
    
    // Add BOM for UTF-8
    fwrite($output, "\xEF\xBB\xBF");
    
    // Write data
    foreach ($data as $row) {
        if (fputcsv($output, $row) === false) {
            die('Error: Cannot write CSV row');
        }
    }
    
    fclose($output);
    exit();
}

// Test 2: CSV with actual data
if (isset($_GET['test']) && $_GET['test'] === 'real_data') {
    echo "<h2>Starting Real Data CSV Download...</h2>";
    
    try {
        $supabase = getSupabaseClient();
        
        // Get some real data
        $users = $supabase->select('users', 'id, first_name, last_name, email, course', [], '', 10);
        
        if (!$users || !is_array($users)) {
            die('Error: No user data found or database error');
        }
        
        // Clean output buffer
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        $filename = 'real_data_test_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Set headers
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, must-revalidate');
        header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
        
        // Create file handle
        $output = fopen('php://output', 'w');
        
        // Add BOM
        fwrite($output, "\xEF\xBB\xBF");
        
        // Header row
        fputcsv($output, ['ID', 'First Name', 'Last Name', 'Email', 'Course']);
        
        // Data rows
        foreach ($users as $user) {
            fputcsv($output, [
                $user['id'],
                $user['first_name'],
                $user['last_name'],
                $user['email'],
                $user['course']
            ]);
        }
        
        fclose($output);
        exit();
        
    } catch (Exception $e) {
        die('Error: ' . $e->getMessage());
    }
}

// Test 3: Debug headers
if (isset($_GET['test']) && $_GET['test'] === 'headers') {
    echo "<h2>Testing Headers Only...</h2>";
    
    // Clean output buffer
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    $filename = 'header_test_' . date('Y-m-d_H-i-s') . '.csv';
    
    // Set headers
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    echo "Test,CSV,File\n";
    echo "Header,Test,Only\n";
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>CSV Download Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .test-button {
            display: inline-block;
            background: #007bff;
            color: white;
            padding: 15px 30px;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px;
            font-size: 16px;
        }
        .test-button:hover {
            background: #0056b3;
        }
        .info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <h1>CSV Download Test Suite</h1>
    
    <div class="info">
        <strong>Instructions:</strong> Click each test button below to verify CSV download functionality.
        If any test fails, check your browser's developer console and network tab for errors.
    </div>
    
    <h2>Test Options</h2>
    
    <a href="?test=simple" class="test-button">Test 1: Simple CSV</a>
    <p>Downloads a basic CSV file with hardcoded data to test basic functionality.</p>
    
    <a href="?test=real_data" class="test-button">Test 2: Real Data CSV</a>
    <p>Downloads CSV with actual user data from the database.</p>
    
    <a href="?test=headers" class="test-button">Test 3: Headers Only</a>
    <p>Tests just the HTTP headers for CSV download.</p>
    
    <hr>
    
    <h2>System Information</h2>
    <div class="info">
        <strong>PHP Version:</strong> <?php echo PHP_VERSION; ?><br>
        <strong>Output Buffering:</strong> <?php echo ob_get_level() > 0 ? 'Active (Level: ' . ob_get_level() . ')' : 'Inactive'; ?><br>
        <strong>Memory Limit:</strong> <?php echo ini_get('memory_limit'); ?><br>
        <strong>Max Execution Time:</strong> <?php echo ini_get('max_execution_time'); ?><br>
        <strong>Error Display:</strong> <?php echo ini_get('display_errors') ? 'On' : 'Off'; ?><br>
        <strong>Log Errors:</strong> <?php echo ini_get('log_errors') ? 'On' : 'Off'; ?><br>
    </div>
    
    <h2>Database Test</h2>
    <?php
    try {
        $supabase = getSupabaseClient();
        $testUsers = $supabase->select('users', 'id, first_name, last_name', [], '', 3);
        
        if (is_array($testUsers) && !empty($testUsers)) {
            echo '<div class="success">✅ Database connection working - found ' . count($testUsers) . ' users</div>';
        } else {
            echo '<div class="error">❌ Database query failed or no users found</div>';
        }
    } catch (Exception $e) {
        echo '<div class="error">❌ Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    }
    ?>
    
    <hr>
    <p><a href="reports.php" style="color: #007bff;">← Back to Reports</a></p>
    
</body>
</html>