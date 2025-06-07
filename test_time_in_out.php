<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in
requireLogin();

// Get current user data
$user = getCurrentUser();
if (!$user) {
    header('Location: index.php');
    exit();
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Time In/Out Test - EVSU Event Attendance</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; }
        .header { background: #1976d2; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; }
        .section { background: #f5f5f5; padding: 20px; margin: 20px 0; border-radius: 8px; border-left: 4px solid #1976d2; }
        .attendance-record { background: white; padding: 15px; margin: 10px 0; border-radius: 5px; border-left: 4px solid #28a745; }
        .check-out { border-left-color: #dc3545; }
        .btn { background: #1976d2; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; text-decoration: none; display: inline-block; }
        .btn-success { background: #28a745; }
        .btn-danger { background: #dc3545; }
        .status { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .status.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .status.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .status.warning { background: #fff3cd; color: #856404; border: 1px solid #ffeaa7; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #f2f2f2; }
        .time-in { color: #28a745; font-weight: bold; }
        .time-out { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>";

echo "<div class='header'>";
echo "<h1>🕒 Time In/Out System Test</h1>";
echo "<p>Testing the enhanced attendance tracking with Time In and Time Out functionality</p>";
echo "<p><strong>System Time:</strong> " . date('F j, Y g:i:s A T') . " (Philippine Time)</p>";
echo "</div>";

// Get user's recent attendance with new time tracking
try {
    $supabase = getSupabaseClient();

    // Get attendance sessions (combined time in/out data)
    $attendance_sessions = $supabase->select('attendance_sessions', '*', ['user_id_new' => $user['id']]);

    echo "<div class='section'>";
    echo "<h2>📊 Your Attendance Sessions</h2>";

    if (!empty($attendance_sessions)) {
        echo "<table>";
        echo "<tr>";
        echo "<th>Event</th>";
        echo "<th>Date</th>";
        echo "<th>Time In</th>";
        echo "<th>Time Out</th>";
        echo "<th>Duration</th>";
        echo "<th>Status</th>";
        echo "</tr>";

        foreach ($attendance_sessions as $session) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($session['event_title']) . "</td>";
            echo "<td>" . date('M j, Y', strtotime($session['event_date'])) . "</td>";
            echo "<td class='time-in'>";
            if ($session['check_in_time']) {
                echo "✅ " . date('g:i A', strtotime($session['check_in_time']));
            } else {
                echo "❌ Not checked in";
            }
            echo "</td>";
            echo "<td class='time-out'>";
            if ($session['check_out_time']) {
                echo "🚪 " . date('g:i A', strtotime($session['check_out_time']));
            } else {
                echo "⏳ Still present";
            }
            echo "</td>";
            echo "<td>";
            if ($session['duration_hours']) {
                echo round($session['duration_hours'], 1) . " hours";
            } else {
                echo "In progress...";
            }
            echo "</td>";
            echo "<td>";
            if ($session['session_status'] === 'completed') {
                echo "<span style='color: #28a745;'>✅ Completed</span>";
            } else {
                echo "<span style='color: #ffc107;'>⏳ Active</span>";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No attendance sessions found. Your attendance records will appear here after using the Time In/Out system.</p>";
    }
    echo "</div>";

    // Get all attendance records (raw data)
    $all_attendance = $supabase->select('user_attendance_history', '*', ['user_id_new' => $user['id']]);

    echo "<div class='section'>";
    echo "<h2>📋 Raw Attendance Records</h2>";

    if (!empty($all_attendance)) {
        foreach ($all_attendance as $record) {
            $isCheckOut = ($record['attendance_type'] === 'check_out');
            $class = $isCheckOut ? 'attendance-record check-out' : 'attendance-record';

            echo "<div class='$class'>";
            echo "<h4>" . htmlspecialchars($record['event_title']) . "</h4>";

            if ($isCheckOut) {
                echo "<p><strong>🔴 TIME OUT:</strong> " . date('F j, Y g:i A', strtotime($record['check_out_time'] ?? $record['attendance_date'])) . "</p>";
            } else {
                echo "<p><strong>🟢 TIME IN:</strong> " . date('F j, Y g:i A', strtotime($record['check_in_time'] ?? $record['attendance_date'])) . "</p>";
            }

            echo "<p><strong>Location:</strong> " . htmlspecialchars($record['location']) . "</p>";
            echo "<p><strong>Method:</strong> " . ucfirst(str_replace('_', ' ', $record['check_in_method'])) . "</p>";

            if (!empty($record['notes'])) {
                echo "<p><strong>Notes:</strong> " . htmlspecialchars($record['notes']) . "</p>";
            }
            echo "</div>";
        }
    } else {
        echo "<p>No attendance records found yet.</p>";
    }
    echo "</div>";
} catch (Exception $e) {
    echo "<div class='status error'>";
    echo "<h3>❌ Error</h3>";
    echo "<p>Could not retrieve attendance data: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<div class='section'>";
echo "<h2>🔧 System Features</h2>";
echo "<div class='status success'>";
echo "<h3>✅ New Time In/Out Features:</h3>";
echo "<ul>";
echo "<li><strong>🟢 Time In:</strong> Records when students arrive at events</li>";
echo "<li><strong>🔴 Time Out:</strong> Records when students leave events</li>";
echo "<li><strong>⏱️ Duration Tracking:</strong> Automatically calculates time spent at events</li>";
echo "<li><strong>📊 Session Status:</strong> Shows if attendance is active or completed</li>";
echo "<li><strong>🔍 Smart Validation:</strong> Prevents duplicate check-ins/outs and invalid sequences</li>";
echo "<li><strong>📱 Mobile Friendly:</strong> Works on phones and tablets</li>";
echo "</ul>";
echo "</div>";
echo "</div>";

echo "<div class='section'>";
echo "<h2>🚀 Quick Actions</h2>";
echo "<a href='dashboard.php' class='btn'>📊 Go to Dashboard</a>";
if ($user['role'] === 'admin') {
    echo "<a href='qr_scanner.php' class='btn btn-success'>📱 QR Scanner (Time In/Out)</a>";
    echo "<a href='events.php' class='btn'>📅 Manage Events</a>";
}
echo "</div>";

echo "<div class='section'>";
echo "<h2>📖 How to Use</h2>";
echo "<ol>";
echo "<li><strong>For Admins:</strong> Go to QR Scanner → Select Event → Choose 'Time In' or 'Time Out' → Scan student QR codes</li>";
echo "<li><strong>For Students:</strong> Show your QR code from the dashboard when asked</li>";
echo "<li><strong>Time In:</strong> Scan when students arrive at the event</li>";
echo "<li><strong>Time Out:</strong> Scan when students leave the event</li>";
echo "<li><strong>View Reports:</strong> Check attendance sessions to see complete timing data</li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
