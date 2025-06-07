<?php
session_start();
require_once 'supabase_config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Timezone Test - Philippine Time Verification</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .header { background: #1976d2; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .section { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #1976d2; }
        .status { padding: 15px; margin: 10px 0; border-radius: 5px; }
        .status.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.info { background: #d1ecf1; color: #0c5460; border: 1px solid #bee5eb; }
        .btn { background: #1976d2; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>";

echo "<div class='header'>";
echo "<h1>🌏 Timezone Test - Philippine Time</h1>";
echo "<p>Verifying that the system is using Philippine Standard Time (PST/PHT) correctly</p>";
echo "</div>";

// Display current timezone settings
echo "<div class='section'>";
echo "<h2>⚙️ System Configuration</h2>";

echo "<div class='status info'>";
echo "<h3>Current Timezone Settings:</h3>";
echo "<p><strong>PHP Default Timezone:</strong> " . date_default_timezone_get() . "</p>";
echo "<p><strong>Expected Timezone:</strong> Asia/Manila (UTC+8)</p>";

if (date_default_timezone_get() === 'Asia/Manila') {
    echo "<p style='color: #28a745;'>✅ <strong>Timezone is correctly set to Philippine time!</strong></p>";
} else {
    echo "<p style='color: #dc3545;'>❌ <strong>Warning: Timezone is not set to Philippine time!</strong></p>";
}
echo "</div>";
echo "</div>";

// Display various time formats
echo "<div class='section'>";
echo "<h2>🕒 Current Time Display</h2>";

$now = time();
echo "<table>";
echo "<tr><th>Format</th><th>Time</th><th>Description</th></tr>";
echo "<tr><td>Full Date/Time</td><td>" . date('F j, Y g:i:s A', $now) . "</td><td>Display format used in system</td></tr>";
echo "<tr><td>Database Format</td><td>" . date('Y-m-d H:i:s', $now) . "</td><td>Format stored in database</td></tr>";
echo "<tr><td>12-Hour Format</td><td>" . date('g:i A', $now) . "</td><td>User-friendly time display</td></tr>";
echo "<tr><td>24-Hour Format</td><td>" . date('H:i:s', $now) . "</td><td>24-hour time format</td></tr>";
echo "<tr><td>Timezone</td><td>" . date('T', $now) . "</td><td>Timezone abbreviation</td></tr>";
echo "<tr><td>UTC Offset</td><td>" . date('P', $now) . "</td><td>UTC offset (should be +08:00)</td></tr>";
echo "<tr><td>Unix Timestamp</td><td>" . $now . "</td><td>Raw timestamp</td></tr>";
echo "</table>";
echo "</div>";

// Test database time operations
echo "<div class='section'>";
echo "<h2>💾 Database Time Test</h2>";

try {
    $supabase = getSupabaseClient();

    echo "<div class='status success'>";
    echo "<h3>✅ Database Connection Successful</h3>";
    echo "<p>Testing time operations with Philippine timezone...</p>";
    echo "</div>";

    // Show what time would be stored in database
    $db_time = date('Y-m-d H:i:s', time());
    echo "<p><strong>Time that would be stored in database:</strong> $db_time</p>";
    echo "<p><strong>This represents:</strong> " . date('F j, Y g:i A T', time()) . "</p>";
} catch (Exception $e) {
    echo "<div class='status error'>";
    echo "<h3>❌ Database Error</h3>";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
echo "</div>";

// Time comparison
echo "<div class='section'>";
echo "<h2>🌍 Time Comparison</h2>";
echo "<table>";
echo "<tr><th>Location</th><th>Time</th><th>UTC Offset</th></tr>";

// Philippine Time (current setting)
$ph_time = new DateTime('now', new DateTimeZone('Asia/Manila'));
echo "<tr><td>🇵🇭 Philippines (Manila)</td><td>" . $ph_time->format('Y-m-d g:i A') . "</td><td>+08:00</td></tr>";

// UTC Time
$utc_time = new DateTime('now', new DateTimeZone('UTC'));
echo "<tr><td>🌐 UTC</td><td>" . $utc_time->format('Y-m-d g:i A') . "</td><td>+00:00</td></tr>";

// US Eastern Time for comparison
$us_time = new DateTime('now', new DateTimeZone('America/New_York'));
echo "<tr><td>🇺🇸 New York</td><td>" . $us_time->format('Y-m-d g:i A') . "</td><td>" . $us_time->format('P') . "</td></tr>";

echo "</table>";
echo "</div>";

// Quick actions
echo "<div class='section'>";
echo "<h2>🚀 Quick Actions</h2>";
echo "<a href='dashboard.php' class='btn'>📊 Go to Dashboard</a>";
echo "<a href='qr_scanner.php' class='btn'>📱 QR Scanner</a>";
echo "<a href='test_time_in_out.php' class='btn'>🕒 Time In/Out Test</a>";
echo "</div>";

// Verification status
echo "<div class='section'>";
echo "<h2>✅ Verification Status</h2>";

$checks = [
    'PHP Timezone' => date_default_timezone_get() === 'Asia/Manila',
    'UTC Offset' => date('P') === '+08:00',
    'Timezone Abbreviation' => in_array(date('T'), ['PHT', 'PST', '+08'])
];

foreach ($checks as $check => $passed) {
    $status = $passed ? '✅' : '❌';
    $color = $passed ? '#28a745' : '#dc3545';
    echo "<p style='color: $color;'>$status <strong>$check:</strong> " . ($passed ? 'PASSED' : 'FAILED') . "</p>";
}

if (array_product($checks)) {
    echo "<div class='status success'>";
    echo "<h3>🎉 All Timezone Checks Passed!</h3>";
    echo "<p>Your system is correctly configured to use Philippine time.</p>";
    echo "</div>";
} else {
    echo "<div class='status error'>";
    echo "<h3>⚠️ Timezone Configuration Issues Detected</h3>";
    echo "<p>Some timezone settings may need adjustment.</p>";
    echo "</div>";
}

echo "</div>";

echo "<script>";
echo "// Auto-refresh every 10 seconds to show live time";
echo "setTimeout(() => location.reload(), 10000);";
echo "</script>";

echo "</body></html>";
