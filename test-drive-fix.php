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

echo "<h1>🔧 Google Drive Export Fix Test</h1>";

// Test 1: Basic Google API Manager Setup
echo "<h2>1. Google API Manager Setup</h2>";
try {
    $googleAPI = new GoogleAPIManager();

    if ($googleAPI->isConfigured()) {
        echo "✅ Google API Manager is configured<br>";

        $config = $googleAPI->getConfigStatus();
        if ($config['configured']) {
            echo "✅ Configuration status: " . $config['message'] . "<br>";
            echo "📧 Service account: " . $config['client_email'] . "<br>";
        }
    } else {
        echo "❌ Google API Manager not configured<br>";
        exit();
    }
} catch (Exception $e) {
    echo "❌ Error initializing Google API Manager: " . $e->getMessage() . "<br>";
    exit();
}

// Test 2: Test Google Drive Upload (Root folder)
echo "<h2>2. Test Google Drive Upload (Root Folder)</h2>";
try {
    $result = $googleAPI->testDriveUpload();

    if ($result['success']) {
        echo "🎉 <strong>SUCCESS!</strong> " . $result['message'] . "<br>";
        if (isset($result['viewUrl'])) {
            echo "🔗 <a href='" . $result['viewUrl'] . "' target='_blank'>View Test File</a><br>";
        }
    } else {
        echo "❌ <strong>FAILED:</strong> " . $result['message'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Drive upload test error: " . $e->getMessage() . "<br>";
}

// Test 3: Test Google Drive Upload to Specific Folder
echo "<h2>3. Test Google Drive Upload to Specific Folder</h2>";
global $GOOGLE_CONFIG;
if (isset($GOOGLE_CONFIG['drive_folder_id']) && !empty($GOOGLE_CONFIG['drive_folder_id'])) {
    $folderId = $GOOGLE_CONFIG['drive_folder_id'];
    echo "📁 Using folder ID: " . $folderId . "<br>";

    try {
        $result = $googleAPI->testDriveUpload($folderId);

        if ($result['success']) {
            echo "🎉 <strong>SUCCESS!</strong> " . $result['message'] . "<br>";
            if (isset($result['viewUrl'])) {
                echo "🔗 <a href='" . $result['viewUrl'] . "' target='_blank'>View Test File in Folder</a><br>";
            }
        } else {
            echo "❌ <strong>FAILED:</strong> " . $result['message'] . "<br>";
        }
    } catch (Exception $e) {
        echo "❌ Folder upload test error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "⚠️ No specific folder ID configured in google-config.php<br>";
}

// Test 4: Test Full Report Upload to Drive
echo "<h2>4. Test Full Report Upload to Drive</h2>";

// Generate sample report data
$sampleReportData = [
    ['Date', 'Student ID', 'Student Name', 'Email', 'Course', 'Event Title', 'Attendance Type', 'Check In Time', 'Check Out Time'],
    ['2024-01-15', '2021-00001', 'John Doe', 'johndoe@evsu.edu.ph', 'BS Computer Science', 'Sample Event', 'check_in', '2024-01-15 08:00:00', '2024-01-15 17:00:00'],
    ['2024-01-15', '2021-00002', 'Jane Smith', 'janesmith@evsu.edu.ph', 'BS Information Technology', 'Sample Event', 'check_in', '2024-01-15 08:15:00', '2024-01-15 17:15:00'],
    ['2024-01-15', '2021-00003', 'Bob Johnson', 'bobjohnson@evsu.edu.ph', 'BS Computer Engineering', 'Sample Event', 'check_in', '2024-01-15 08:30:00', '2024-01-15 17:30:00']
];

// Convert to CSV
$csvContent = '';
foreach ($sampleReportData as $row) {
    $csvContent .= '"' . implode('","', str_replace('"', '""', $row)) . '"' . "\n";
}

echo "📊 Sample report data generated (" . count($sampleReportData) . " rows)<br>";

try {
    $filename = 'test_attendance_report_' . date('Y-m-d_H-i-s') . '.csv';

    if (isset($GOOGLE_CONFIG['drive_folder_id']) && !empty($GOOGLE_CONFIG['drive_folder_id'])) {
        $result = $googleAPI->uploadToExistingFolder($GOOGLE_CONFIG['drive_folder_id'], $filename, $csvContent, 'text/csv');
    } else {
        $result = $googleAPI->uploadToDrive($filename, $csvContent, 'text/csv');
    }

    if ($result['success']) {
        echo "🎉 <strong>SUCCESS!</strong> " . $result['message'] . "<br>";
        if (isset($result['viewUrl'])) {
            echo "🔗 <a href='" . $result['viewUrl'] . "' target='_blank'>View Report File</a><br>";
        }
    } else {
        echo "❌ <strong>FAILED:</strong> " . $result['message'] . "<br>";
    }
} catch (Exception $e) {
    echo "❌ Report upload test error: " . $e->getMessage() . "<br>";
}

// Test 5: Test the actual reports.php Google Drive export logic
echo "<h2>5. Test Reports.php Google Drive Export Logic</h2>";

// Simulate the reports.php POST request
$_POST['report_type'] = 'attendance';
$_POST['export_format'] = 'google_drive';
$_POST['generate_report'] = '';

echo "🧪 Simulating POST request with:<br>";
echo "- Report Type: attendance<br>";
echo "- Export Format: google_drive<br>";

// Include report generation functions
require_once 'reports.php';

// Since we included reports.php, the logic should have executed
// Check if any output was generated

echo "<hr>";
echo "<h2>💡 Debugging Information</h2>";
echo "<ul>";
echo "<li>📋 If all tests above passed, Google Drive export should work</li>";
echo "<li>🔧 The fixed Google API Manager uses proper uploadType parameter</li>";
echo "<li>📁 Files are uploaded with correct MIME types</li>";
echo "<li>🔗 View links are generated properly</li>";
echo "</ul>";

if (isset($result) && $result['success']) {
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3 style='color: #155724; margin: 0 0 10px 0;'>✅ Google Drive Export is Working!</h3>";
    echo "<p style='color: #155724; margin: 0;'>The Google Drive export functionality has been fixed and is now working correctly.</p>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3 style='color: #721c24; margin: 0 0 10px 0;'>❌ Google Drive Export Still Has Issues</h3>";
    echo "<p style='color: #721c24; margin: 0;'>Please check the error messages above and verify your Google credentials and folder permissions.</p>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='reports.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Back to Reports</a></p>";
echo "<p><a href='test-guzzle-fix.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin-left: 10px;'>View Previous Test</a></p>";
