<?php
session_start();
require_once 'supabase_config.php';
require_once 'google-api-manager.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

echo "<h1>Google API Debug Test</h1>";

// Test Google API Manager initialization
echo "<h2>1. Google API Manager Initialization</h2>";
try {
    $googleAPI = new GoogleAPIManager();
    echo "✅ GoogleAPIManager created successfully<br>";

    $isConfigured = $googleAPI->isConfigured();
    echo "Configuration status: " . ($isConfigured ? "✅ Configured" : "❌ Not configured") . "<br>";

    if (!$isConfigured) {
        echo "<strong>❌ Google API is not configured!</strong><br>";
        echo "Please check:<br>";
        echo "- google-credentials.json file exists<br>";
        echo "- Credentials file is valid JSON<br>";
        echo "- Required scopes are enabled<br>";
    }
} catch (Exception $e) {
    echo "❌ Error initializing GoogleAPIManager: " . $e->getMessage() . "<br>";
}

// Test configuration status
echo "<h2>2. Detailed Configuration Status</h2>";
try {
    if (isset($googleAPI)) {
        $configStatus = $googleAPI->getConfigStatus();
        echo "<pre>" . print_r($configStatus, true) . "</pre>";

        // Also test basic connectivity without making API calls
        echo "<h3>2.1 Connection Test (No API Calls)</h3>";
        $connectionTest = $googleAPI->testConnection();
        echo "<pre>" . print_r($connectionTest, true) . "</pre>";
    }
} catch (Exception $e) {
    echo "❌ Error getting config status: " . $e->getMessage() . "<br>";
    echo "<p>Full error details:</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

// Test simple spreadsheet creation
echo "<h2>3. Test Spreadsheet Creation</h2>";
if (isset($_GET['test_sheets']) && isset($googleAPI) && $googleAPI->isConfigured()) {
    try {
        $testData = [
            ['Name', 'Email', 'Course'],
            ['Test Student 1', 'test1@evsu.edu.ph', 'BS IT'],
            ['Test Student 2', 'test2@evsu.edu.ph', 'BS CS']
        ];

        echo "Creating test spreadsheet...<br>";
        $result = $googleAPI->createSpreadsheet('Debug Test Spreadsheet - ' . date('Y-m-d H:i:s'), $testData);

        echo "<strong>Result:</strong><br>";
        echo "<pre>" . print_r($result, true) . "</pre>";

        if ($result['success'] && isset($result['url'])) {
            echo "<a href='" . $result['url'] . "' target='_blank'>View Test Spreadsheet</a><br>";
        } else {
            echo "<h3>Troubleshooting Information:</h3>";
            if (isset($result['suggestion'])) {
                echo "<p><strong>Suggestion:</strong> " . $result['suggestion'] . "</p>";
            }
            if (isset($result['error_type'])) {
                echo "<p><strong>Error Type:</strong> " . $result['error_type'] . "</p>";
            }

            echo "<h4>Common Solutions for XAMPP/Windows:</h4>";
            echo "<ul>";
            echo "<li><strong>Update XAMPP:</strong> Download the latest version from <a href='https://www.apachefriends.org/' target='_blank'>apachefriends.org</a></li>";
            echo "<li><strong>Update CA Certificates:</strong> Download latest cacert.pem from <a href='https://curl.se/ca/cacert.pem' target='_blank'>curl.se</a></li>";
            echo "<li><strong>PHP Configuration:</strong> Check that openssl and curl extensions are enabled in php.ini</li>";
            echo "<li><strong>Firewall:</strong> Ensure Apache/PHP can make outbound HTTPS connections</li>";
            echo "<li><strong>Alternative:</strong> Consider using CSV export only until Google API issues are resolved</li>";
            echo "</ul>";
        }
    } catch (Exception $e) {
        echo "❌ Error creating test spreadsheet: " . $e->getMessage() . "<br>";
        echo "<p>This error typically indicates SSL/TLS or network connectivity issues on Windows/XAMPP environments.</p>";
    }
} else {
    echo "<p>Click 'Test Google Sheets' button below to test spreadsheet creation.</p>";
}

// Test Google Drive upload
echo "<h2>4. Test Google Drive Upload</h2>";
if (isset($_GET['test_drive']) && isset($googleAPI) && $googleAPI->isConfigured()) {
    try {
        $testCsvContent = "Name,Email,Course\nTest Student 1,test1@evsu.edu.ph,BS IT\nTest Student 2,test2@evsu.edu.ph,BS CS";
        $filename = 'debug_test_' . date('Y-m-d_H-i-s') . '.csv';

        echo "Uploading test CSV to Drive...<br>";
        $result = $googleAPI->uploadToDrive($filename, $testCsvContent, 'text/csv');

        echo "<strong>Result:</strong><br>";
        echo "<pre>" . print_r($result, true) . "</pre>";

        if ($result['success'] && isset($result['viewUrl'])) {
            echo "<a href='" . $result['viewUrl'] . "' target='_blank'>View Test File</a><br>";
        }
    } catch (Exception $e) {
        echo "❌ Error uploading test file: " . $e->getMessage() . "<br>";
    }
}

// Test attendance report generation
echo "<h2>5. Test Report Generation</h2>";
if (isset($_GET['test_report'])) {
    try {
        echo "Testing attendance report generation...<br>";

        // Load the function from reports.php
        require_once 'reports.php';

        $reportResult = generateAttendanceReport();
        echo "Report generation result: " . ($reportResult['success'] ? "✅ Success" : "❌ Failed") . "<br>";

        if ($reportResult['success']) {
            echo "Data rows: " . count($reportResult['data']) . "<br>";
            echo "Sample data (first 3 rows):<br>";
            echo "<pre>";
            for ($i = 0; $i < min(3, count($reportResult['data'])); $i++) {
                print_r($reportResult['data'][$i]);
            }
            echo "</pre>";
        } else {
            echo "Error: " . $reportResult['message'] . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Error testing report generation: " . $e->getMessage() . "<br>";
    }
}

// Check PHP error logs
echo "<h2>6. Recent PHP Errors</h2>";
if (function_exists('error_get_last')) {
    $lastError = error_get_last();
    if ($lastError) {
        echo "<strong>Last PHP Error:</strong><br>";
        echo "<pre>" . print_r($lastError, true) . "</pre>";
    } else {
        echo "No recent PHP errors found.<br>";
    }
}

// Check file permissions
echo "<h2>7. File Permissions & Credentials</h2>";
$credentialsFile = 'google-credentials.json';
if (file_exists($credentialsFile)) {
    echo "google-credentials.json: ✅ Exists<br>";
    echo "Readable: " . (is_readable($credentialsFile) ? "✅ Yes" : "❌ No") . "<br>";
    echo "File size: " . filesize($credentialsFile) . " bytes<br>";

    // Check credentials content
    try {
        $credentialsContent = file_get_contents($credentialsFile);
        $credentials = json_decode($credentialsContent, true);

        if ($credentials) {
            echo "JSON valid: ✅ Yes<br>";
            echo "Client email: " . (isset($credentials['client_email']) ? "✅ Present" : "❌ Missing") . "<br>";
            echo "Private key: " . (isset($credentials['private_key']) ? "✅ Present" : "❌ Missing") . "<br>";
            echo "Project ID: " . (isset($credentials['project_id']) ? "✅ Present (" . $credentials['project_id'] . ")" : "❌ Missing") . "<br>";
            echo "Type: " . (isset($credentials['type']) ? $credentials['type'] : "Unknown") . "<br>";
        } else {
            echo "JSON valid: ❌ Invalid JSON format<br>";
        }
    } catch (Exception $e) {
        echo "Error reading credentials: " . $e->getMessage() . "<br>";
    }
} else {
    echo "google-credentials.json: ❌ Not found<br>";
}

// Check network connectivity
echo "<h2>8. Network & System Info</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "OpenSSL: " . (extension_loaded('openssl') ? "✅ Loaded" : "❌ Not loaded") . "<br>";
echo "cURL: " . (extension_loaded('curl') ? "✅ Loaded" : "❌ Not loaded") . "<br>";
echo "User Agent: " . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Not set') . "<br>";

// Test internet connectivity
echo "Internet connectivity test: ";
try {
    $context = stream_context_create([
        'http' => [
            'timeout' => 5
        ]
    ]);
    $response = @file_get_contents('https://www.google.com', false, $context);
    echo $response ? "✅ Working" : "❌ Failed";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
echo "<br>";

?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }

    h1,
    h2 {
        color: #333;
    }

    .test-btn {
        display: inline-block;
        padding: 10px 15px;
        margin: 5px;
        background: #007bff;
        color: white;
        text-decoration: none;
        border-radius: 3px;
    }

    .test-btn:hover {
        background: #0056b3;
    }

    pre {
        background: #f5f5f5;
        padding: 10px;
        border-radius: 3px;
        overflow-x: auto;
    }
</style>

<h2>Test Actions</h2>
<a href="?test_sheets=1" class="test-btn">Test Google Sheets</a>
<a href="?test_drive=1" class="test-btn">Test Google Drive</a>
<a href="?test_report=1" class="test-btn">Test Report Generation</a>
<a href="debug-google-api.php" class="test-btn">Refresh</a>

<p><a href="reports.php">← Back to Reports</a></p>