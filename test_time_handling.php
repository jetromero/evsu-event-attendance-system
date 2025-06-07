<?php
// Test script to verify time handling improvements
session_start();
require_once 'supabase_config.php';

echo "<h2>Time Handling Test</h2>";
echo "<p><strong>PHP Timezone:</strong> " . date_default_timezone_get() . "</p>";
echo "<p><strong>Current Server Time:</strong> " . date('Y-m-d H:i:s T') . "</p>";
echo "<p><strong>Formatted Time:</strong> " . date('F j, Y g:i:s A') . "</p>";

// Test strtotime and date functions
$testTime = '2024-12-31 14:30:00';
echo "<h3>Time Conversion Test</h3>";
echo "<p><strong>Test time string:</strong> $testTime</p>";
echo "<p><strong>strtotime result:</strong> " . strtotime($testTime) . "</p>";
echo "<p><strong>Formatted result:</strong> " . date('g:i A', strtotime($testTime)) . "</p>";

// Test with different time formats
$dbFormats = [
    '2024-12-31 14:30:00+08',
    '2024-12-31T14:30:00+08:00',
    '2024-12-31 14:30:00.123456+08',
    '2024-12-31 14:30:00'
];

echo "<h3>Database Time Format Tests</h3>";
foreach ($dbFormats as $format) {
    $timestamp = strtotime($format);
    $formatted = ($timestamp !== false) ? date('g:i A', $timestamp) : 'FAILED';
    echo "<p><strong>$format</strong> → $formatted</p>";
}

// Test current time functions
echo "<h3>Current Time Functions</h3>";
echo "<p><strong>time():</strong> " . time() . " → " . date('g:i A', time()) . "</p>";
echo "<p><strong>date('Y-m-d H:i:s'):</strong> " . date('Y-m-d H:i:s') . " → " . date('g:i A', strtotime(date('Y-m-d H:i:s'))) . "</p>";

// Test database connection and recent times
try {
    $supabase = getSupabaseClient();
    echo "<h3>Database Time Test</h3>";

    // Get recent attendance records
    $recentAttendance = $supabase->select('attendance', 'check_in_time, check_out_time, attendance_type, created_at', [], 'id DESC', 5);

    if ($recentAttendance) {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>Type</th><th>Raw Time</th><th>Formatted Time</th><th>strtotime Result</th></tr>";

        foreach ($recentAttendance as $att) {
            $timeField = $att['attendance_type'] === 'check_in' ? 'check_in_time' : 'check_out_time';
            $rawTime = $att[$timeField];

            if ($rawTime) {
                $timestamp = strtotime($rawTime);
                $formatted = ($timestamp !== false) ? date('g:i A', $timestamp) : 'PARSE_ERROR';
                $timestampDisplay = ($timestamp !== false) ? $timestamp : 'FAILED';

                echo "<tr>";
                echo "<td>{$att['attendance_type']}</td>";
                echo "<td>$rawTime</td>";
                echo "<td>$formatted</td>";
                echo "<td>$timestampDisplay</td>";
                echo "</tr>";
            }
        }
        echo "</table>";
    } else {
        echo "<p>No attendance records found in database.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'><strong>Database Error:</strong> " . $e->getMessage() . "</p>";
}

// Test UTC to Philippine time conversion (the main issue)
echo "<h3>UTC to Philippine Time Conversion Test</h3>";
$utcTimes = [
    '2025-06-05 08:06:24+00',  // Your actual database time
    '2025-06-05 00:06:24+00',  // What it should be for 8:06 AM Philippine
    '2024-12-31 16:30:00+00'   // Another test time
];

foreach ($utcTimes as $utcTime) {
    echo "<p><strong>UTC Time:</strong> $utcTime</p>";

    // Method 1: Using DateTime objects (recommended)
    try {
        $datetime = new DateTime($utcTime, new DateTimeZone('UTC'));
        $datetime->setTimezone(new DateTimeZone('Asia/Manila'));
        $phpTime = $datetime->format('g:i A');
        echo "<p><strong>DateTime conversion:</strong> $phpTime</p>";
    } catch (Exception $e) {
        echo "<p><strong>DateTime conversion:</strong> ERROR - " . $e->getMessage() . "</p>";
    }

    // Method 2: Using strtotime (old method)
    $timestamp = strtotime($utcTime);
    $strtotimeResult = ($timestamp !== false) ? date('g:i A', $timestamp) : 'FAILED';
    echo "<p><strong>strtotime conversion:</strong> $strtotimeResult</p>";

    echo "<hr>";
}

echo "<h3>Expected Results for Your Check-in</h3>";
echo "<p>✅ <strong>Database:</strong> 2025-06-05 08:06:24+00 (UTC)</p>";
echo "<p>✅ <strong>Should display:</strong> 8:06 AM (Philippine Time)</p>";
echo "<p>❌ <strong>Was displaying:</strong> 4:06 PM (incorrect)</p>";

echo "<h3>Test Summary</h3>";
echo "<p>✅ This test helps verify that time handling is working correctly.</p>";
echo "<p>🕒 All times should display in Philippine Time (Asia/Manila).</p>";
echo "<p>📊 Check that database times are being parsed and formatted properly.</p>";
