<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

echo "<h1>🔧 GuzzleHttp Compatibility Fix Test</h1>";

// Test 1: Check GuzzleHttp version
echo "<h2>1. GuzzleHttp Version Check</h2>";
if (class_exists('GuzzleHttp\Client')) {
    if (defined('GuzzleHttp\ClientInterface::VERSION')) {
        echo "✅ GuzzleHttp version: " . GuzzleHttp\ClientInterface::VERSION . "<br>";
    } else {
        echo "⚠️ GuzzleHttp version unknown<br>";
    }
} else {
    echo "❌ GuzzleHttp not found<br>";
}

// Test 2: Check Composer dependencies
echo "<h2>2. Composer Dependencies</h2>";
if (file_exists('vendor/autoload.php')) {
    echo "✅ Vendor autoload found<br>";

    if (file_exists('vendor/composer/installed.json')) {
        $installed = json_decode(file_get_contents('vendor/composer/installed.json'), true);
        echo "📦 Packages installed: " . count($installed['packages'] ?? $installed) . "<br>";

        // Find GuzzleHttp version
        $packages = $installed['packages'] ?? $installed;
        foreach ($packages as $package) {
            if ($package['name'] === 'guzzlehttp/guzzle') {
                echo "🔍 GuzzleHttp installed: " . $package['version'] . "<br>";
                break;
            }
        }
    }
} else {
    echo "❌ Vendor directory not found<br>";
}

// Test 3: Create minimal Google client
echo "<h2>3. Minimal Google Client Test</h2>";
try {
    require_once 'vendor/autoload.php';

    if (!class_exists('Google_Client')) {
        echo "❌ Google_Client class not found<br>";
    } else {
        echo "✅ Google_Client class available<br>";

        // Create client without custom HTTP configuration
        $client = new Google_Client();
        $client->setApplicationName('EVSU Test');
        echo "✅ Basic Google Client created<br>";

        // Try with credentials
        if (file_exists('google-credentials.json')) {
            try {
                $client->setAuthConfig('google-credentials.json');
                $client->setScopes([
                    Google_Service_Sheets::SPREADSHEETS,
                    Google_Service_Drive::DRIVE
                ]);
                echo "✅ Credentials and scopes set<br>";

                // Create services
                $sheetsService = new Google_Service_Sheets($client);
                $driveService = new Google_Service_Drive($client);
                echo "✅ Services created successfully<br>";
            } catch (Exception $e) {
                echo "❌ Error with credentials: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "⚠️ google-credentials.json not found<br>";
        }
    }
} catch (Exception $e) {
    echo "❌ Error creating client: " . $e->getMessage() . "<br>";
}

// Test 4: Create custom HTTP client with fix
echo "<h2>4. Custom HTTP Client with Compatibility Fix</h2>";
try {
    if (class_exists('GuzzleHttp\Client')) {
        // Create a minimal configuration
        $config = [
            'verify' => false,
            'timeout' => 30,
            'http_errors' => false,
            'headers' => [
                'User-Agent' => 'EVSU-Test/1.0'
            ]
        ];

        // Only add curl options if absolutely necessary
        if (extension_loaded('curl')) {
            $config['curl'] = [
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_TIMEOUT => 30
            ];
        }

        $httpClient = new GuzzleHttp\Client($config);
        echo "✅ Custom HTTP client created<br>";

        // Test a simple request
        try {
            $response = $httpClient->get('https://www.google.com', ['timeout' => 10]);
            echo "✅ Test HTTP request successful<br>";
        } catch (Exception $e) {
            echo "❌ Test HTTP request failed: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "❌ GuzzleHttp\Client not available<br>";
    }
} catch (Exception $e) {
    echo "❌ Custom HTTP client error: " . $e->getMessage() . "<br>";
}

// Test 5: Create improved Google API Manager
echo "<h2>5. Improved Google API Manager Test</h2>";

class ImprovedGoogleAPIManager
{
    private $client;
    private $sheetsService;
    private $isConfigured = false;

    public function __construct()
    {
        $this->initializeClient();
    }

    private function initializeClient()
    {
        try {
            $this->client = new Google_Client();
            $this->client->setApplicationName('EVSU Event Attendance System');

            // Note: Newer Google API client versions don't need logger disabling
            // The setLogger method now requires a proper LoggerInterface
            // Skipping logger configuration as it's not needed for basic functionality

            // Use a minimal HTTP client configuration
            if (class_exists('GuzzleHttp\Client')) {
                $httpClient = new GuzzleHttp\Client([
                    'verify' => false,
                    'timeout' => 60,
                    'http_errors' => false
                ]);
                $this->client->setHttpClient($httpClient);
            }

            if (file_exists('google-credentials.json')) {
                $this->client->setAuthConfig('google-credentials.json');
                $this->client->setScopes([
                    Google_Service_Sheets::SPREADSHEETS
                ]);

                $this->sheetsService = new Google_Service_Sheets($this->client);
                $this->isConfigured = true;

                echo "✅ Improved Google API Manager initialized<br>";
            } else {
                echo "⚠️ Credentials file not found<br>";
            }
        } catch (Exception $e) {
            echo "❌ Improved manager error: " . $e->getMessage() . "<br>";
        }
    }

    public function isConfigured()
    {
        return $this->isConfigured;
    }

    public function testSpreadsheetUpdate($spreadsheetId)
    {
        if (!$this->isConfigured) {
            return ['success' => false, 'message' => 'Not configured'];
        }

        try {
            $testData = [
                ['Test', 'Data', 'From', 'Fixed', 'Manager'],
                ['Row 1', 'Data 1', 'Value 1', date('Y-m-d'), date('H:i:s')],
                ['Row 2', 'Data 2', 'Value 2', date('Y-m-d'), date('H:i:s')]
            ];

            $body = new Google_Service_Sheets_ValueRange([
                'values' => $testData
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $result = $this->sheetsService->spreadsheets_values->update(
                $spreadsheetId,
                'A1',
                $body,
                $params
            );

            return [
                'success' => true,
                'message' => 'Spreadsheet updated successfully',
                'updatedCells' => $result->getUpdatedCells()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Update failed: ' . $e->getMessage()
            ];
        }
    }
}

// Test the improved manager
try {
    $improvedManager = new ImprovedGoogleAPIManager();

    if ($improvedManager->isConfigured()) {
        echo "✅ Improved manager is configured<br>";

        // Test with your spreadsheet
        $spreadsheetId = '1RCKO6ABpoFMz9fEF1rZVZMFOix7R8WW4l8t56ryRYJ8';
        echo "<h3>Testing Spreadsheet Update</h3>";

        $result = $improvedManager->testSpreadsheetUpdate($spreadsheetId);

        if ($result['success']) {
            echo "🎉 <strong>SUCCESS!</strong> " . $result['message'] . "<br>";
            if (isset($result['updatedCells'])) {
                echo "📊 Updated cells: " . $result['updatedCells'] . "<br>";
            }
            echo "🔗 <a href='https://docs.google.com/spreadsheets/d/{$spreadsheetId}' target='_blank'>View Spreadsheet</a><br>";
        } else {
            echo "❌ <strong>FAILED:</strong> " . $result['message'] . "<br>";
        }
    } else {
        echo "❌ Improved manager not configured<br>";
    }
} catch (Exception $e) {
    echo "❌ Improved manager test error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h2>💡 Recommendations</h2>";
echo "<ul>";
echo "<li>✅ The improved Google API Manager should work better</li>";
echo "<li>🔧 Reduced HTTP client configuration complexity</li>";
echo "<li>⚠️ Disabled logging to prevent compatibility issues</li>";
echo "<li>📈 If the test above works, update your main google-api-manager.php</li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='reports.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>← Back to Reports</a></p>";
