<?php
require_once 'supabase_config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <title>URL Debug Test</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }
        .info { color: blue; }
        .error { color: red; }
        .success { color: green; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>";

echo "<h2>🔍 URL Debug Test</h2>";

// Get the Supabase configuration
global $supabase_url, $supabase_key;

echo "<div class='info'>";
echo "<h3>Configuration Check:</h3>";
echo "<p><strong>Supabase URL:</strong> <code>" . htmlspecialchars($supabase_url) . "</code></p>";
echo "<p><strong>URL Length:</strong> " . strlen($supabase_url) . " characters</p>";
echo "<p><strong>Key Length:</strong> " . strlen($supabase_key) . " characters</p>";
echo "<p><strong>Key Starts With:</strong> " . substr($supabase_key, 0, 20) . "...</p>";
echo "</div>";

// Test URL construction
echo "<h3>URL Construction Test:</h3>";

try {
    // Manual URL construction test
    $base_url = rtrim($supabase_url, '/');
    $test_endpoint = '/rest/v1/users';
    $full_url = $base_url . $test_endpoint;

    echo "<p class='info'>Base URL: <code>" . htmlspecialchars($base_url) . "</code></p>";
    echo "<p class='info'>Test Endpoint: <code>" . htmlspecialchars($test_endpoint) . "</code></p>";
    echo "<p class='info'>Full URL: <code>" . htmlspecialchars($full_url) . "</code></p>";

    // Test if URL is valid
    if (filter_var($full_url, FILTER_VALIDATE_URL)) {
        echo "<p class='success'>✓ URL appears to be valid</p>";
    } else {
        echo "<p class='error'>❌ URL validation failed</p>";
    }

    // Test cURL initialization
    echo "<h3>cURL Test:</h3>";
    $ch = curl_init();

    if ($ch === false) {
        echo "<p class='error'>❌ Failed to initialize cURL</p>";
    } else {
        echo "<p class='success'>✓ cURL initialized successfully</p>";

        // Test setting URL
        $url_set = curl_setopt($ch, CURLOPT_URL, $full_url);
        if ($url_set) {
            echo "<p class='success'>✓ URL set successfully</p>";
        } else {
            echo "<p class='error'>❌ Failed to set URL</p>";
        }

        // Set other options
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'apikey: ' . $supabase_key,
            'Authorization: Bearer ' . $supabase_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        // Try the request
        echo "<p class='info'>Attempting test request...</p>";
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($error) {
            echo "<p class='error'>❌ cURL Error: " . htmlspecialchars($error) . "</p>";
        } else {
            echo "<p class='success'>✓ Request completed</p>";
            echo "<p class='info'>HTTP Code: " . $http_code . "</p>";
            echo "<p class='info'>Response: " . htmlspecialchars(substr($response, 0, 200)) . "...</p>";
        }

        curl_close($ch);
    }
} catch (Exception $e) {
    echo "<p class='error'>❌ Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Check for common URL issues
echo "<h3>URL Analysis:</h3>";
echo "<ul>";

if (strpos($supabase_url, 'localhost') !== false) {
    echo "<li class='error'>⚠️ Using localhost URL - should use your actual Supabase project URL</li>";
}

if (!preg_match('/^https:\/\/[a-z0-9]+\.supabase\.co$/', $supabase_url)) {
    echo "<li class='error'>⚠️ URL doesn't match expected Supabase format</li>";
}

if (strlen($supabase_key) < 100) {
    echo "<li class='error'>⚠️ API key seems too short</li>";
}

echo "</ul>";

echo "<hr>";
echo "<p><a href='qr_scanner.php'>← Back to QR Scanner</a></p>";
echo "</body></html>";
