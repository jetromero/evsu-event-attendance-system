<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    echo "Access denied. Admin privileges required.";
    exit();
}

echo "<h1>Reports Debug Tool</h1>";
echo "<p>This tool helps debug CSV download issues</p>";

// Display POST data if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h2>Form Data Received:</h2>";
    echo "<pre>";
    var_dump($_POST);
    echo "</pre>";

    if (isset($_POST['generate_report'])) {
        $reportType = trim($_POST['report_type']);
        $exportFormat = trim($_POST['export_format']);

        echo "<h2>Processing Report:</h2>";
        echo "<p>Report Type: " . htmlspecialchars($reportType) . "</p>";
        echo "<p>Export Format: " . htmlspecialchars($exportFormat) . "</p>";

        // Test data generation
        if ($reportType === 'attendance') {
            echo "<h3>Testing Attendance Report Generation:</h3>";

            try {
                $supabase = getSupabaseClient();
                $attendance = $supabase->select('attendance', '*');

                if ($attendance) {
                    echo "<p>✅ Found " . count($attendance) . " attendance records</p>";

                    // Show first few records
                    echo "<h4>Sample Records:</h4>";
                    echo "<pre>";
                    print_r(array_slice($attendance, 0, 3));
                    echo "</pre>";

                    // Test CSV generation
                    if ($exportFormat === 'csv') {
                        echo "<h3>Testing CSV Generation:</h3>";

                        $testData = [
                            ['Date', 'Student ID', 'Name', 'Event', 'Type'],
                            ['2024-01-15', '123', 'John Doe', 'Test Event', 'check_in'],
                            ['2024-01-15', '124', 'Jane Smith', 'Test Event', 'check_in']
                        ];

                        echo "<p>Test data prepared. Click button below to download test CSV:</p>";
                        echo '<form method="POST">';
                        echo '<input type="hidden" name="download_test_csv" value="1">';
                        echo '<button type="submit">Download Test CSV</button>';
                        echo '</form>';
                    }
                } else {
                    echo "<p>❌ No attendance records found</p>";
                }
            } catch (Exception $e) {
                echo "<p>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
            }
        }
    }
}

// Handle test CSV download
if (isset($_POST['download_test_csv'])) {
    // Clear output buffer
    if (ob_get_level()) {
        ob_end_clean();
    }

    $testData = [
        ['Date', 'Student ID', 'Name', 'Event', 'Type'],
        ['2024-01-15', '123', 'John Doe', 'Test Event', 'check_in'],
        ['2024-01-15', '124', 'Jane Smith', 'Test Event', 'check_in']
    ];

    $filename = 'debug_test_' . date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    foreach ($testData as $row) {
        fputcsv($output, $row);
    }

    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Reports Debug</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .form-group {
            margin: 15px 0;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        select,
        input {
            padding: 8px;
            width: 200px;
        }

        button {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }

        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
    </style>
</head>

<body>
    <h2>Test Report Generation</h2>

    <form method="POST">
        <div class="form-group">
            <label for="report_type">Report Type:</label>
            <select name="report_type" id="report_type" required>
                <option value="">Choose...</option>
                <option value="attendance">Attendance Report</option>
                <option value="events">Events Report</option>
                <option value="users">Users Report</option>
            </select>
        </div>

        <div class="form-group">
            <label for="export_format">Export Format:</label>
            <select name="export_format" id="export_format" required>
                <option value="">Choose...</option>
                <option value="csv">CSV Download</option>
                <option value="google_sheets">Google Sheets</option>
                <option value="google_drive">Google Drive</option>
            </select>
        </div>

        <button type="submit" name="generate_report">Test Report Generation</button>
    </form>

    <div class="debug-info">
        <h3>Debug Information:</h3>
        <p><strong>Current User:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
        <p><strong>User Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>
        <p><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></p>
        <p><strong>Output Buffering:</strong> <?php echo ob_get_level() > 0 ? 'Active' : 'Inactive'; ?></p>
    </div>

    <p><a href="reports.php">← Back to Reports</a> | <a href="test-csv-download.php">Test Basic CSV Download</a></p>
</body>

</html>