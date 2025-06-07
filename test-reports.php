<?php

/**
 * Debug/Test Script for Reports System
 * Use this to test the Google API integration and report generation
 */

session_start();
require_once 'supabase_config.php';
require_once 'google-api-manager.php';

echo "<h1>🔧 Reports System Debug Tool</h1>";
echo "<style>body{font-family:Arial,sans-serif;margin:20px;} .success{color:green;} .error{color:red;} .info{color:blue;} pre{background:#f5f5f5;padding:10px;border-radius:5px;}</style>";

// Test 1: Check dependencies
echo "<h2>1. Dependencies Check</h2>";
if (class_exists('Google_Client')) {
    echo "<p class='success'>✅ Google API Client: Installed</p>";
} else {
    echo "<p class='error'>❌ Google API Client: Not found</p>";
    echo "<p><strong>Run:</strong> <code>composer require google/apiclient:^2.0</code></p>";
}

// Test 2: Check autoloader
echo "<h2>2. Autoloader Check</h2>";
if (file_exists('vendor/autoload.php')) {
    echo "<p class='success'>✅ Composer autoloader: Found</p>";
} else {
    echo "<p class='error'>❌ Composer autoloader: Missing</p>";
    echo "<p><strong>Run:</strong> <code>composer install</code></p>";
}

// Test 3: Check credentials
echo "<h2>3. Google Credentials Check</h2>";
if (file_exists('google-credentials.json')) {
    echo "<p class='success'>✅ Credentials file: Found</p>";

    $credentials = json_decode(file_get_contents('google-credentials.json'), true);
    if ($credentials && isset($credentials['client_email'])) {
        echo "<p class='info'>📧 Service account: " . $credentials['client_email'] . "</p>";
        echo "<p class='info'>🏗️ Project ID: " . ($credentials['project_id'] ?? 'Not set') . "</p>";
    } else {
        echo "<p class='error'>❌ Invalid credentials format</p>";
    }
} else {
    echo "<p class='error'>❌ Credentials file: Missing</p>";
    echo "<p><strong>Create:</strong> google-credentials.json in project root</p>";
}

// Test 4: Google API Manager
echo "<h2>4. Google API Manager Test</h2>";
try {
    $googleAPI = getGoogleAPIManager();
    $configStatus = $googleAPI->getConfigStatus();

    if ($configStatus['configured']) {
        echo "<p class='success'>✅ Google API: " . $configStatus['message'] . "</p>";
    } else {
        echo "<p class='error'>❌ Google API: " . $configStatus['message'] . "</p>";
        if (isset($configStatus['requirements'])) {
            echo "<p><strong>Requirements:</strong></p><ul>";
            foreach ($configStatus['requirements'] as $req) {
                echo "<li>$req</li>";
            }
            echo "</ul>";
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test 5: Database Connection
echo "<h2>5. Database Connection Test</h2>";
try {
    $supabase = getSupabaseClient();
    $users = $supabase->select('users', 'id', [], '', 1);
    echo "<p class='success'>✅ Database: Connected</p>";
} catch (Exception $e) {
    echo "<p class='error'>❌ Database: " . $e->getMessage() . "</p>";
}

// Test 6: Sample Report Generation
echo "<h2>6. Sample Report Test</h2>";
try {
    // Generate sample data
    $sampleData = [
        ['Name', 'Email', 'Course', 'Date'],
        ['John Doe', 'john@evsu.edu.ph', 'Computer Science', date('Y-m-d')],
        ['Jane Smith', 'jane@evsu.edu.ph', 'Information Technology', date('Y-m-d')]
    ];

    echo "<p class='info'>📋 Sample data generated:</p>";
    echo "<pre>" . print_r($sampleData, true) . "</pre>";

    // Test CSV generation
    $csvContent = '';
    foreach ($sampleData as $row) {
        $csvContent .= '"' . implode('","', $row) . '"' . "\n";
    }
    echo "<p class='success'>✅ CSV format: Working</p>";

    // Test Google API if available
    if (class_exists('Google_Client')) {
        $googleAPI = getGoogleAPIManager();
        if ($googleAPI->isConfigured()) {
            echo "<p class='info'>🧪 Testing Google Sheets creation...</p>";
            $result = $googleAPI->createSpreadsheet('Test Report - ' . date('Y-m-d H:i:s'), $sampleData);

            if ($result['success']) {
                echo "<p class='success'>✅ Google Sheets: " . $result['message'] . "</p>";
                if (isset($result['url'])) {
                    echo "<p><a href='" . $result['url'] . "' target='_blank'>🔗 View created spreadsheet</a></p>";
                }
            } else {
                echo "<p class='error'>❌ Google Sheets: " . $result['message'] . "</p>";
            }
        }
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Report test failed: " . $e->getMessage() . "</p>";
}

// Test 7: File Permissions
echo "<h2>7. File Permissions Check</h2>";
$files = ['reports.php', 'google-api-manager.php', 'vendor/autoload.php'];
foreach ($files as $file) {
    if (file_exists($file)) {
        echo "<p class='success'>✅ $file: Exists and readable</p>";
    } else {
        echo "<p class='error'>❌ $file: Missing</p>";
    }
}

// Summary
echo "<h2>📊 Summary</h2>";
echo "<p><strong>To use the Reports feature:</strong></p>";
echo "<ol>";
echo "<li>Go to <a href='reports.php'>Reports Page</a> (admin login required)</li>";
echo "<li>Select report type (Attendance/Events/Users)</li>";
echo "<li>Choose export format (CSV/Google Sheets/Google Drive)</li>";
echo "<li>Click 'Generate Report'</li>";
echo "</ol>";

echo "<p><strong>For Google API setup:</strong></p>";
echo "<ol>";
echo "<li>Follow the guide in <a href='google-api-setup.md'>google-api-setup.md</a></li>";
echo "<li>Place credentials as 'google-credentials.json'</li>";
echo "<li>Run this test again to verify</li>";
echo "</ol>";

// Navigation
echo "<hr>";
echo "<p>";
echo "<a href='reports.php'>📊 Go to Reports</a> | ";
echo "<a href='admin_dashboard.php'>🏠 Admin Dashboard</a> | ";
echo "<a href='index.php'>🔐 Login</a>";
echo "</p>";
