<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

echo "<h1>🐛 Form Submission Debug Test</h1>";

// Display any POST data if submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>✅ POST Data Received</h2>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    echo "POST Data:\n";
    print_r($_POST);
    echo "\nSERVER Data:\n";
    print_r([
        'REQUEST_METHOD' => $_SERVER['REQUEST_METHOD'],
        'CONTENT_TYPE' => $_SERVER['CONTENT_TYPE'] ?? 'Not set',
        'CONTENT_LENGTH' => $_SERVER['CONTENT_LENGTH'] ?? 'Not set'
    ]);
    echo "</pre>";

    // Test report generation
    if (isset($_POST['generate_report'])) {
        echo "<h3>🧪 Report Generation Test</h3>";

        $reportType = $_POST['report_type'] ?? '';
        $exportFormat = $_POST['export_format'] ?? '';

        echo "<p><strong>Report Type:</strong> " . htmlspecialchars($reportType) . "</p>";
        echo "<p><strong>Export Format:</strong> " . htmlspecialchars($exportFormat) . "</p>";

        if ($reportType && $exportFormat) {
            echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
            echo "✅ <strong>Form data is valid!</strong><br>";
            echo "The form submission is working correctly.<br>";
            echo "Issue might be in the PHP processing logic.";
            echo "</div>";

            // Test Google API if it's a Google export
            if ($exportFormat === 'google_sheets' || $exportFormat === 'google_drive') {
                echo "<h4>🔍 Testing Google API Manager</h4>";
                try {
                    require_once 'google-api-manager.php';
                    $googleAPI = new GoogleAPIManager();

                    if ($googleAPI->isConfigured()) {
                        echo "✅ Google API Manager is configured<br>";

                        // Test with a simple data set
                        $testData = [
                            ['Test', 'Debug', 'Data', date('Y-m-d H:i:s')],
                            ['Form', 'Submission', 'Working', '✅']
                        ];

                        if ($exportFormat === 'google_sheets') {
                            echo "<p>Testing Google Sheets export...</p>";
                            $result = $googleAPI->updateExistingSpreadsheet('1RCKO6ABpoFMz9fEF1rZVZMFOix7R8WW4l8t56ryRYJ8', $testData, 'A1');

                            if ($result['success']) {
                                echo "<div style='background: #d4edda; padding: 10px; border-radius: 5px;'>";
                                echo "🎉 <strong>Google Sheets test successful!</strong><br>";
                                echo $result['message'] . "<br>";
                                echo "<a href='" . $result['url'] . "' target='_blank'>View Test Spreadsheet</a>";
                                echo "</div>";
                            } else {
                                echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
                                echo "❌ <strong>Google Sheets test failed:</strong> " . $result['message'];
                                echo "</div>";
                            }
                        }
                    } else {
                        echo "❌ Google API Manager not configured<br>";
                    }
                } catch (Exception $e) {
                    echo "❌ Google API test error: " . $e->getMessage() . "<br>";
                }
            }
        } else {
            echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px; margin: 10px 0;'>";
            echo "❌ <strong>Missing form data!</strong><br>";
            echo "Report Type: " . ($reportType ? 'OK' : 'MISSING') . "<br>";
            echo "Export Format: " . ($exportFormat ? 'OK' : 'MISSING');
            echo "</div>";
        }
    }
} else {
    echo "<h2>📝 Test Form</h2>";
    echo "<p>Use this form to test if the submission is working:</p>";
}
?>

<!-- Test Form -->
<form method="POST" action="" style="max-width: 600px; margin: 20px 0; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
    <h3>Test Report Generation Form</h3>

    <div style="margin: 15px 0;">
        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Report Type:</label>
        <select name="report_type" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            <option value="">Select report type...</option>
            <option value="attendance">Attendance Report</option>
            <option value="events">Events Report</option>
        </select>
    </div>

    <div style="margin: 15px 0;">
        <label style="display: block; font-weight: bold; margin-bottom: 5px;">Export Format:</label>
        <select name="export_format" required style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
            <option value="">Select export format...</option>
            <option value="csv">CSV Download</option>
            <option value="google_sheets">Google Sheets</option>
            <option value="google_drive">Google Drive</option>
        </select>
    </div>

    <div style="margin: 20px 0;">
        <button type="submit" name="generate_report" style="background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
            🧪 Test Submit
        </button>
    </div>
</form>

<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0;">
    <h3>🎯 What This Test Does:</h3>
    <ul>
        <li>✅ Tests if form data is being submitted correctly</li>
        <li>✅ Shows exactly what POST data is received</li>
        <li>✅ Tests Google API integration if Google export is selected</li>
        <li>✅ Helps identify where the issue is occurring</li>
    </ul>
</div>

<p style="margin: 20px 0;">
    <a href="reports.php" style="background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">← Back to Reports Page</a>
</p>

<script>
    console.log('Form debug page loaded');

    // Add form submission logging
    document.querySelector('form').addEventListener('submit', function(e) {
        console.log('Form submitted!');
        console.log('Report Type:', document.querySelector('[name="report_type"]').value);
        console.log('Export Format:', document.querySelector('[name="export_format"]').value);

        // Don't prevent submission - let it go through
        return true;
    });
</script>