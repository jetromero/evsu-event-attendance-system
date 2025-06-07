<?php
session_start();
require_once 'supabase_config.php';
require_once 'google-api-manager.php';
require_once 'google-config.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

echo "<h1>🧪 Direct Spreadsheet Test</h1>";

echo "<h2>1. Configuration Check</h2>";
echo "Spreadsheet ID: " . GOOGLE_SPREADSHEET_ID . "<br>";
echo "Drive Folder ID: " . GOOGLE_DRIVE_FOLDER_ID . "<br>";

if (empty(GOOGLE_SPREADSHEET_ID)) {
    die("❌ <strong>ERROR:</strong> GOOGLE_SPREADSHEET_ID is empty in google-config.php");
}

echo "<h2>2. SSL/Certificate Test</h2>";
echo "Testing SSL connectivity...<br>";

// Test SSL with different methods
$sslTests = [
    'file_get_contents' => function () {
        $context = stream_context_create([
            'http' => ['timeout' => 10]
        ]);
        return @file_get_contents('https://www.googleapis.com', false, $context) !== false;
    },
    'curl_with_verify' => function () {
        if (!extension_loaded('curl')) return false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $result = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        return $result !== false && empty($error);
    },
    'curl_no_verify' => function () {
        if (!extension_loaded('curl')) return false;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result !== false;
    }
];

foreach ($sslTests as $testName => $test) {
    echo "$testName: ";
    echo $test() ? "✅ Success" : "❌ Failed";
    echo "<br>";
}

echo "<h2>3. Google API Manager Test</h2>";
try {
    $googleAPI = new GoogleAPIManager();
    echo "Google API Manager: ✅ Created<br>";
    echo "Configured: " . ($googleAPI->isConfigured() ? "✅ Yes" : "❌ No") . "<br>";

    if (!$googleAPI->isConfigured()) {
        echo "❌ <strong>STOP:</strong> Google API is not configured!<br>";
        echo "Check your google-credentials.json file.<br>";
        exit;
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
    exit;
}

echo "<h2>4. Test Data Preparation</h2>";
$testData = [
    ['Name', 'Email', 'Course', 'Event', 'Time'],
    ['Test Student 1', 'test1@evsu.edu.ph', 'BS IT', 'Test Event', date('Y-m-d H:i:s')],
    ['Test Student 2', 'test2@evsu.edu.ph', 'BS CS', 'Test Event', date('Y-m-d H:i:s')],
    ['Test Student 3', 'test3@evsu.edu.ph', 'BS CPE', 'Test Event', date('Y-m-d H:i:s')]
];

echo "Test data prepared with " . count($testData) . " rows.<br>";

echo "<h2>5. Direct Spreadsheet Update Test</h2>";

if (isset($_GET['run_test'])) {
    echo "<strong>🚀 Running live test...</strong><br><br>";

    try {
        // Test 1: Update existing spreadsheet
        echo "<h3>Test 1: Update Existing Spreadsheet</h3>";
        $spreadsheetId = GOOGLE_SPREADSHEET_ID;
        $range = 'Attendance!A1'; // Using the configured sheet name

        echo "Spreadsheet ID: " . $spreadsheetId . "<br>";
        echo "Range: " . $range . "<br>";
        echo "Updating spreadsheet...<br>";

        $result = $googleAPI->updateExistingSpreadsheet($spreadsheetId, $testData, $range);

        echo "<strong>Result:</strong><br>";
        echo "<pre>" . print_r($result, true) . "</pre>";

        if ($result['success']) {
            echo "✅ <strong>SUCCESS!</strong> Data sent to spreadsheet!<br>";
            if (isset($result['url'])) {
                echo "View spreadsheet: <a href='" . $result['url'] . "' target='_blank'>" . $result['url'] . "</a><br>";
            }
        } else {
            echo "❌ <strong>FAILED:</strong> " . $result['message'] . "<br>";

            // Try fallback method
            echo "<h3>Test 2: Fallback - Create New Spreadsheet</h3>";
            echo "Trying to create a new spreadsheet instead...<br>";

            $fallbackResult = $googleAPI->createSpreadsheet('EVSU Test - ' . date('Y-m-d H:i:s'), $testData);
            echo "<strong>Fallback Result:</strong><br>";
            echo "<pre>" . print_r($fallbackResult, true) . "</pre>";

            if ($fallbackResult['success']) {
                echo "✅ <strong>FALLBACK SUCCESS!</strong> New spreadsheet created!<br>";
                if (isset($fallbackResult['url'])) {
                    echo "View new spreadsheet: <a href='" . $fallbackResult['url'] . "' target='_blank'>" . $fallbackResult['url'] . "</a><br>";
                }
            }
        }
    } catch (Exception $e) {
        echo "❌ <strong>EXCEPTION:</strong> " . $e->getMessage() . "<br>";
        echo "<strong>Full error:</strong><br>";
        echo "<pre>" . $e->getTraceAsString() . "</pre>";
    }
} else {
    echo "<p><strong>Click the button below to run the live test:</strong></p>";
    echo "<a href='?run_test=1' style='display: inline-block; padding: 15px 25px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;'>🚀 RUN LIVE TEST</a>";
}

echo "<h2>6. Troubleshooting Tips</h2>";
echo "<h3>If test fails:</h3>";
echo "<ol>";
echo "<li><strong>SSL Issues:</strong> Make sure you updated php.ini and restarted XAMPP</li>";
echo "<li><strong>Spreadsheet Access:</strong> Make sure your Google Spreadsheet exists and has sheets named 'Attendance' and 'Events'</li>";
echo "<li><strong>Permissions:</strong> Your service account needs edit access to the spreadsheet</li>";
echo "</ol>";

echo "<h3>Grant Access to Your Spreadsheet:</h3>";
echo "<ol>";
echo "<li>Open your Google Spreadsheet: <a href='https://docs.google.com/spreadsheets/d/" . GOOGLE_SPREADSHEET_ID . "' target='_blank'>Click here</a></li>";
echo "<li>Click <strong>'Share'</strong> button (top right)</li>";
echo "<li>Add this email: <code>evsu-attendance-reports@evsu-attendance.iam.gserviceaccount.com</code></li>";
echo "<li>Give it <strong>'Editor'</strong> permissions</li>";
echo "<li>Click <strong>'Send'</strong></li>";
echo "</ol>";

?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }

    h1,
    h2,
    h3 {
        color: #333;
    }

    pre {
        background: #f5f5f5;
        padding: 10px;
        border-radius: 3px;
        overflow-x: auto;
    }

    code {
        background: #f5f5f5;
        padding: 2px 5px;
        border-radius: 3px;
    }
</style>

<p><a href="reports.php">← Back to Reports</a></p>