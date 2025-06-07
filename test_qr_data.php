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

echo "<!DOCTYPE html>
<html>
<head>
    <title>QR Code Data Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .info { background: #e3f2fd; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .qr-data { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; font-family: monospace; word-break: break-all; }
        .test-btn { background: #1976d2; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
        .test-result { padding: 10px; margin: 10px 0; border-radius: 5px; }
        .success { background: #e8f5e9; color: #2e7d32; }
        .error { background: #ffebee; color: #c62828; }
    </style>
</head>
<body>";

echo "<h2>🧪 QR Code Data Testing</h2>";

echo "<div class='info'>";
echo "<h3>Current User Information:</h3>";
echo "<p><strong>ID:</strong> " . htmlspecialchars($user['id']) . "</p>";
echo "<p><strong>Name:</strong> " . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . "</p>";
echo "<p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>";
echo "<p><strong>Course:</strong> " . htmlspecialchars($user['course']) . "</p>";
echo "<p><strong>Year/Section:</strong> " . htmlspecialchars($user['year_level'] . ' - ' . $user['section']) . "</p>";
echo "</div>";

echo "<div class='info'>";
echo "<h3>Generated QR Code Data:</h3>";
echo "<div class='qr-data'>" . htmlspecialchars($qrData) . "</div>";
echo "</div>";

echo "<div class='info'>";
echo "<h3>QR Code Data Validation Test:</h3>";
echo "<button class='test-btn' onclick='testQRData()'>Test QR Data Format</button>";
echo "<div id='testResult'></div>";
echo "</div>";

echo "<div class='info'>";
echo "<h3>Quick Actions:</h3>";
echo "<a href='dashboard.php' class='test-btn'>Go to Dashboard</a>";
echo "<a href='qr_scanner.php' class='test-btn'>Go to QR Scanner</a>";
echo "</div>";

echo "<script>
function testQRData() {
    const qrData = " . json_encode($qrData) . ";
    const resultDiv = document.getElementById('testResult');
    
    try {
        // Parse QR data
        const studentData = JSON.parse(qrData);
        console.log('Parsed student data:', studentData);
        
        let results = [];
        let hasErrors = false;
        
        // Check required fields
        const requiredFields = ['student_id', 'first_name', 'last_name'];
        
        requiredFields.forEach(field => {
            if (studentData[field]) {
                results.push('✅ ' + field + ': ' + studentData[field]);
            } else {
                results.push('❌ Missing: ' + field);
                hasErrors = true;
            }
        });
        
        // Check optional fields
        const optionalFields = ['email', 'course', 'year_level', 'section', 'timestamp'];
        
        optionalFields.forEach(field => {
            if (studentData[field]) {
                results.push('✅ ' + field + ': ' + studentData[field]);
            } else {
                results.push('⚠️ Optional missing: ' + field);
            }
        });
        
        const resultClass = hasErrors ? 'error' : 'success';
        const resultTitle = hasErrors ? '❌ QR Data has errors' : '✅ QR Data is valid';
        
        resultDiv.innerHTML = '<div class=\"test-result ' + resultClass + '\">' +
            '<h4>' + resultTitle + '</h4>' +
            '<ul><li>' + results.join('</li><li>') + '</li></ul>' +
            '</div>';
            
    } catch (error) {
        resultDiv.innerHTML = '<div class=\"test-result error\">' +
            '<h4>❌ JSON Parse Error</h4>' +
            '<p>' + error.message + '</p>' +
            '</div>';
    }
}
</script>";

echo "</body></html>";
