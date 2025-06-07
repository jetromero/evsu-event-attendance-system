<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in
requireLogin();

// Get current user data
$user = getCurrentUser();
if (!$user) {
    echo "❌ No user logged in\n";
    exit();
}

// Generate QR code data for testing
$qrData = json_encode([
    'student_id' => $user['id'],
    'first_name' => $user['first_name'],
    'last_name' => $user['last_name'],
    'email' => $user['email'],
    'course' => $user['course'],
    'year_level' => $user['year_level'],
    'section' => $user['section'],
    'timestamp' => time()
]);

echo "✅ QR Data Generated:\n";
echo $qrData . "\n\n";

// Parse and validate
$parsed = json_decode($qrData, true);
echo "📋 Parsed Fields:\n";
foreach ($parsed as $key => $value) {
    echo "  - $key: $value\n";
}

echo "\n🔍 Required Field Check:\n";
$required = ['student_id', 'first_name', 'last_name'];
foreach ($required as $field) {
    if (isset($parsed[$field]) && !empty($parsed[$field])) {
        echo "  ✅ $field: " . $parsed[$field] . "\n";
    } else {
        echo "  ❌ Missing: $field\n";
    }
}
