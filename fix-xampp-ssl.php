<?php

/**
 * XAMPP SSL Certificate Fix for Google API
 * This script helps fix SSL certificate issues in XAMPP environments
 */

echo "<h1>🔧 XAMPP SSL Certificate Fix</h1>";

// Check current XAMPP setup
echo "<h2>1. Current System Information</h2>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "OpenSSL: " . (extension_loaded('openssl') ? "✅ Loaded" : "❌ Not loaded") . "<br>";
echo "cURL: " . (extension_loaded('curl') ? "✅ Loaded" : "❌ Not loaded") . "<br>";

// Check current SSL configuration
$phpIniPath = php_ini_loaded_file();
echo "PHP INI file: " . $phpIniPath . "<br>";

// Test current SSL connectivity
echo "<h2>2. Current SSL Connectivity Test</h2>";
echo "Testing Google APIs connectivity...<br>";

$context = stream_context_create([
    "http" => [
        "timeout" => 10,
        "method" => "GET"
    ]
]);

$testUrls = [
    'https://www.googleapis.com' => 'Google APIs',
    'https://sheets.googleapis.com' => 'Google Sheets API',
    'https://www.google.com' => 'Google.com'
];

foreach ($testUrls as $url => $name) {
    echo "Testing $name: ";
    $result = @file_get_contents($url, false, $context);
    echo $result ? "✅ Success" : "❌ Failed";
    echo "<br>";
}

// Download and configure certificate fix
echo "<h2>3. SSL Certificate Fix</h2>";

// First, let's try a different approach - test with cURL directly
echo "Testing with cURL...<br>";
if (extension_loaded('curl')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://www.googleapis.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $result = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "cURL result: ";
    if ($result && $httpCode == 200) {
        echo "✅ Success (HTTP $httpCode)<br>";
    } else {
        echo "❌ Failed<br>";
        if ($error) {
            echo "cURL Error: " . htmlspecialchars($error) . "<br>";
        }
        echo "HTTP Code: $httpCode<br>";
    }
} else {
    echo "❌ cURL not available<br>";
}

// Check if we can write to PHP directory (for certificate fix)
$xamppPath = dirname($phpIniPath);
$certPath = $xamppPath . DIRECTORY_SEPARATOR . 'cacert.pem';

echo "<h2>4. Certificate File Management</h2>";
echo "XAMPP Path: " . $xamppPath . "<br>";
echo "Certificate target path: " . $certPath . "<br>";

if (is_writable($xamppPath)) {
    echo "Directory writable: ✅ Yes<br>";

    // Download fresh certificate
    echo "Downloading fresh CA certificate bundle...<br>";
    $certUrl = 'https://curl.se/ca/cacert.pem';

    // Try to download using cURL with less strict SSL
    if (extension_loaded('curl')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $certUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Temporarily disable for download
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');

        $certData = curl_exec($ch);
        $downloadError = curl_error($ch);
        $downloadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($certData && $downloadHttpCode == 200 && strlen($certData) > 1000) {
            echo "Certificate download: ✅ Success (" . number_format(strlen($certData)) . " bytes)<br>";

            // Save the certificate
            if (file_put_contents($certPath, $certData)) {
                echo "Certificate saved: ✅ Success<br>";

                // Now update php.ini
                echo "<h2>5. PHP Configuration Update</h2>";
                echo "Certificate saved to: " . $certPath . "<br>";
                echo "<strong>⚠️ Manual Steps Required:</strong><br>";
                echo "<ol>";
                echo "<li>Open your php.ini file: <code>" . $phpIniPath . "</code></li>";
                echo "<li>Find the line: <code>;curl.cainfo =</code></li>";
                echo "<li>Replace it with: <code>curl.cainfo = \"" . str_replace('\\', '\\\\', $certPath) . "\"</code></li>";
                echo "<li>Find the line: <code>;openssl.cafile =</code></li>";
                echo "<li>Replace it with: <code>openssl.cafile = \"" . str_replace('\\', '\\\\', $certPath) . "\"</code></li>";
                echo "<li>Save the file and restart XAMPP</li>";
                echo "</ol>";

                echo "<h3>Alternative: Quick Configuration File</h3>";
                echo "Or copy this configuration to add to your php.ini:<br>";
                echo "<textarea style='width: 100%; height: 100px; font-family: monospace;'>";
                echo "; SSL Certificate Configuration\n";
                echo "curl.cainfo = \"" . str_replace('\\', '\\\\', $certPath) . "\"\n";
                echo "openssl.cafile = \"" . str_replace('\\', '\\\\', $certPath) . "\"\n";
                echo "</textarea>";
            } else {
                echo "Certificate save: ❌ Failed (permission denied)<br>";
            }
        } else {
            echo "Certificate download: ❌ Failed<br>";
            if ($downloadError) {
                echo "Download error: " . htmlspecialchars($downloadError) . "<br>";
            }
            echo "HTTP Code: $downloadHttpCode<br>";
        }
    } else {
        echo "❌ Cannot download - cURL not available<br>";
    }
} else {
    echo "Directory writable: ❌ No<br>";
    echo "<strong>⚠️ Run as Administrator:</strong> You need to run XAMPP Control Panel as Administrator to modify files.<br>";
}

echo "<h2>6. Alternative Solutions</h2>";
echo "<h3>Option 1: Manual Certificate Download</h3>";
echo "<ol>";
echo "<li>Download: <a href='https://curl.se/ca/cacert.pem' target='_blank'>https://curl.se/ca/cacert.pem</a></li>";
echo "<li>Save as: <code>C:\\xampp\\apache\\bin\\cacert.pem</code></li>";
echo "<li>Update php.ini with the configuration shown above</li>";
echo "<li>Restart XAMPP</li>";
echo "</ol>";

echo "<h3>Option 2: Disable SSL Verification (NOT RECOMMENDED for production)</h3>";
echo "If you need a quick temporary fix for testing:<br>";
echo "<ol>";
echo "<li>Edit your google-api-manager.php</li>";
echo "<li>Add this code in the initializeClient() method:</li>";
echo "</ol>";
echo "<textarea style='width: 100%; height: 150px; font-family: monospace;'>";
echo "// Temporary SSL fix - ONLY for local development\n";
echo "if (class_exists('GuzzleHttp\\Client')) {\n";
echo "    \$httpClient = new GuzzleHttp\\Client([\n";
echo "        'verify' => false,\n";
echo "        'timeout' => 30\n";
echo "    ]);\n";
echo "    \$this->client->setHttpClient(\$httpClient);\n";
echo "}\n";
echo "</textarea>";

echo "<h3>Option 3: Use CSV Export Only</h3>";
echo "If SSL issues persist:<br>";
echo "<ul>";
echo "<li>✅ CSV export will always work</li>";
echo "<li>✅ You can manually upload CSVs to Google Drive</li>";
echo "<li>✅ You can import CSVs into Google Sheets</li>";
echo "</ul>";

echo "<h2>7. Testing After Fix</h2>";
echo "<p>After applying the SSL fix:</p>";
echo "<ol>";
echo "<li>Restart XAMPP completely</li>";
echo "<li>Go to your <a href='debug-google-api.php'>Google API Debug page</a></li>";
echo "<li>Test the Google Sheets and Drive exports</li>";
echo "</ol>";

?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
    }

    h1,
    h2,
    h3 {
        color: #333;
    }

    code {
        background: #f5f5f5;
        padding: 2px 5px;
        border-radius: 3px;
    }

    textarea {
        border: 1px solid #ccc;
        padding: 10px;
        border-radius: 5px;
    }

    ol,
    ul {
        margin: 10px 0;
        padding-left: 30px;
    }
</style>