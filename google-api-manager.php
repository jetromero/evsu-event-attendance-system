<?php

/**
 * Google API Manager for EVSU Event Attendance System
 * Handles Google Sheets and Google Drive API integration
 * 
 * @phpstan-ignore-next-line
 * @psalm-suppress UndefinedClass
 */

// Suppress autoload warnings for Google API classes
if (file_exists('vendor/autoload.php')) {
    require_once 'vendor/autoload.php';
}

class GoogleAPIManager
{
    private $client;
    private $sheetsService;
    private $driveService;
    private $isConfigured;

    public function __construct()
    {
        $this->isConfigured = false;
        $this->initializeClient();
    }

    private function initializeClient()
    {
        try {
            // Check if Google API classes are available
            if (!class_exists('Google_Client')) {
                error_log("Google API client not installed. Run: composer install");
                return;
            }

            $this->client = new Google_Client();
            $this->client->setApplicationName('EVSU Event Attendance System');

            // Note: Modern Google API client handles logging automatically
            // No need to disable logging in newer versions

            // Enhanced HTTP client configuration for XAMPP environments
            if (class_exists('GuzzleHttp\Client')) {
                try {
                    // Create a more conservative HTTP client configuration
                    $config = [
                        'verify' => false,  // Disable SSL verification for local development
                        'timeout' => 60,    // Increased timeout
                        'connect_timeout' => 30,
                        'http_errors' => false,  // Don't throw exceptions on HTTP errors
                        'allow_redirects' => [
                            'max' => 3,
                            'strict' => false,
                            'referer' => false,
                            'protocols' => ['http', 'https']
                        ]
                    ];

                    // Only add curl options if curl is available
                    if (extension_loaded('curl') && function_exists('curl_version')) {
                        $curlVersion = curl_version();
                        error_log("cURL version: " . $curlVersion['version']);

                        $config['curl'] = [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false,
                            CURLOPT_FOLLOWLOCATION => true,
                            CURLOPT_MAXREDIRS => 3,
                            CURLOPT_USERAGENT => 'EVSU-Attendance-System/1.0',
                            CURLOPT_TIMEOUT => 60,
                            CURLOPT_CONNECTTIMEOUT => 30
                        ];

                        // Only set SSL version if supported
                        if (defined('CURL_SSLVERSION_TLSv1_2')) {
                            $config['curl'][CURLOPT_SSLVERSION] = CURL_SSLVERSION_TLSv1_2;
                        }
                    }

                    $httpClient = new GuzzleHttp\Client($config);
                    $this->client->setHttpClient($httpClient);
                    error_log("Applied enhanced HTTP client configuration for XAMPP environment");

                    // Additional compatibility measures - logging handled automatically in newer versions
                } catch (Exception $e) {
                    error_log("Failed to configure HTTP client: " . $e->getMessage());
                    // Continue without custom HTTP client
                }
            }

            // Check for credentials file
            $credentialsPath = 'google-credentials.json';
            if (file_exists($credentialsPath)) {
                $this->client->setAuthConfig($credentialsPath);
                $this->client->setScopes([
                    Google_Service_Sheets::SPREADSHEETS,
                    Google_Service_Drive::DRIVE
                ]);

                $this->sheetsService = new Google_Service_Sheets($this->client);
                $this->driveService = new Google_Service_Drive($this->client);
                $this->isConfigured = true;

                error_log("Google API initialized successfully");
            } else {
                error_log("Google credentials file not found: $credentialsPath");
            }
        } catch (Exception $e) {
            error_log("Failed to initialize Google API: " . $e->getMessage());
        }
    }

    public function isConfigured()
    {
        return $this->isConfigured;
    }

    /**
     * Test Google API connectivity with minimal authentication
     */
    public function testConnection()
    {
        if (!$this->isConfigured) {
            return [
                'success' => false,
                'message' => 'Google API not configured'
            ];
        }

        try {
            // Try a simple operation that doesn't require full authentication
            // Just check if we can create a basic request structure
            $testData = [['Test'], ['Data']];

            // This shouldn't make an actual API call, just verify the client is working
            if ($this->client && $this->sheetsService && $this->driveService) {
                return [
                    'success' => true,
                    'message' => 'Google API client appears to be working correctly.',
                    'details' => 'Client, Sheets service, and Drive service are initialized.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Some Google API services failed to initialize.'
                ];
            }
        } catch (Exception $e) {
            error_log("Google API test connection error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error testing Google API connection: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a new Google Spreadsheet with data
     */
    public function createSpreadsheet($title, $data)
    {
        if (!$this->isConfigured) {
            return [
                'success' => false,
                'message' => 'Google API not configured. Please set up google-credentials.json file.'
            ];
        }

        try {
            // Check for common issues before making API calls
            $networkCheck = $this->checkNetworkAndSSL();
            if (!$networkCheck['success']) {
                return [
                    'success' => false,
                    'message' => 'Network/SSL issue detected: ' . $networkCheck['message'],
                    'suggestion' => 'Check SSL certificates, firewall settings, or try updating XAMPP/PHP'
                ];
            }

            // Create spreadsheet
            $spreadsheet = new Google_Service_Sheets_Spreadsheet([
                'properties' => [
                    'title' => $title
                ]
            ]);

            $spreadsheet = $this->sheetsService->spreadsheets->create($spreadsheet);

            $spreadsheetId = $spreadsheet->getSpreadsheetId();
            $spreadsheetUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}";

            // Add data to the spreadsheet
            if (!empty($data)) {
                $body = new Google_Service_Sheets_ValueRange([
                    'values' => $data
                ]);

                $params = [
                    'valueInputOption' => 'RAW'
                ];

                $this->sheetsService->spreadsheets_values->update(
                    $spreadsheetId,
                    'A1',
                    $body,
                    $params
                );

                // Format the header row
                $this->formatHeaderRow($spreadsheetId, count($data[0]));
            }

            // Make the spreadsheet publicly viewable (optional)
            $this->shareFile($spreadsheetId, 'reader', 'anyone');

            return [
                'success' => true,
                'spreadsheetId' => $spreadsheetId,
                'url' => $spreadsheetUrl,
                'message' => 'Spreadsheet created successfully in Google Sheets'
            ];
        } catch (Exception $e) {
            error_log("Error creating spreadsheet: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());

            // Provide more specific error messages based on the exception
            $errorMessage = $this->parseGoogleAPIError($e);

            return [
                'success' => false,
                'message' => $errorMessage,
                'raw_error' => $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Format the header row of a spreadsheet
     */
    private function formatHeaderRow($spreadsheetId, $columnCount)
    {
        try {
            $requests = [
                new Google_Service_Sheets_Request([
                    'repeatCell' => [
                        'range' => [
                            'sheetId' => 0,
                            'startRowIndex' => 0,
                            'endRowIndex' => 1,
                            'startColumnIndex' => 0,
                            'endColumnIndex' => $columnCount
                        ],
                        'cell' => [
                            'userEnteredFormat' => [
                                'backgroundColor' => [
                                    'red' => 0.2,
                                    'green' => 0.4,
                                    'blue' => 0.8
                                ],
                                'textFormat' => [
                                    'foregroundColor' => [
                                        'red' => 1.0,
                                        'green' => 1.0,
                                        'blue' => 1.0
                                    ],
                                    'bold' => true
                                ]
                            ]
                        ],
                        'fields' => 'userEnteredFormat(backgroundColor,textFormat)'
                    ]
                ])
            ];

            $batchUpdateRequest = new Google_Service_Sheets_BatchUpdateSpreadsheetRequest([
                'requests' => $requests
            ]);

            $this->sheetsService->spreadsheets->batchUpdate($spreadsheetId, $batchUpdateRequest);
        } catch (Exception $e) {
            error_log("Error formatting header: " . $e->getMessage());
        }
    }

    /**
     * Upload a file to Google Drive
     */
    public function uploadToDrive($filename, $content, $mimeType, $folderId = null)
    {
        if (!$this->isConfigured) {
            return [
                'success' => false,
                'message' => 'Google API not configured. Please set up google-credentials.json file.'
            ];
        }

        try {
            $fileMetadata = new Google_Service_Drive_DriveFile([
                'name' => $filename
            ]);

            // If folder ID is specified, set parent
            if ($folderId) {
                $fileMetadata->setParents([$folderId]);
            }

            // Use proper Google Drive API v3 upload method with parameters array
            $optParams = [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,webViewLink,webContentLink'
            ];

            $file = $this->driveService->files->create($fileMetadata, $optParams);

            $fileId = $file->getId();
            $viewUrl = $file->getWebViewLink();
            $downloadUrl = $file->getWebContentLink();

            // Make the file publicly accessible (optional)
            $this->shareFile($fileId, 'reader', 'anyone');

            return [
                'success' => true,
                'fileId' => $fileId,
                'viewUrl' => $viewUrl,
                'downloadUrl' => $downloadUrl,
                'message' => 'File uploaded successfully to Google Drive'
            ];
        } catch (Exception $e) {
            error_log("Error uploading to Drive: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error uploading to Drive: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Share a file or folder with specific permissions
     */
    private function shareFile($fileId, $role = 'reader', $type = 'anyone')
    {
        try {
            $permission = new Google_Service_Drive_Permission([
                'type' => $type,
                'role' => $role
            ]);

            $this->driveService->permissions->create($fileId, $permission);
        } catch (Exception $e) {
            error_log("Error sharing file: " . $e->getMessage());
        }
    }

    /**
     * Create or get a folder in Google Drive
     */
    public function createFolder($folderName, $parentFolderId = null)
    {
        if (!$this->isConfigured) {
            return null;
        }

        try {
            $fileMetadata = new Google_Service_Drive_DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder'
            ]);

            if ($parentFolderId) {
                $fileMetadata->setParents([$parentFolderId]);
            }

            $folder = $this->driveService->files->create($fileMetadata);

            return [
                'success' => true,
                'folderId' => $folder->getId(),
                'url' => $folder->getWebViewLink()
            ];
        } catch (Exception $e) {
            error_log("Error creating folder: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error creating folder: ' . $e->getMessage()
            ];
        }
    }

    /**
     * List files in Google Drive
     */
    public function listFiles($folderId = null, $pageSize = 10)
    {
        if (!$this->isConfigured) {
            return ['success' => false, 'message' => 'Google API not configured'];
        }

        try {
            $optParams = [
                'pageSize' => $pageSize,
                'fields' => 'nextPageToken, files(id, name, size, createdTime, modifiedTime, webViewLink)'
            ];

            if ($folderId) {
                $optParams['q'] = "'{$folderId}' in parents";
            }

            $response = $this->driveService->files->listFiles($optParams);
            $files = $response->getFiles();

            $fileList = [];
            foreach ($files as $file) {
                $fileList[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'size' => $file->getSize(),
                    'createdTime' => $file->getCreatedTime(),
                    'modifiedTime' => $file->getModifiedTime(),
                    'url' => $file->getWebViewLink()
                ];
            }

            return [
                'success' => true,
                'files' => $fileList,
                'nextPageToken' => $response->getNextPageToken()
            ];
        } catch (Exception $e) {
            error_log("Error listing files: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error listing files: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Delete a file from Google Drive
     */
    public function deleteFile($fileId)
    {
        if (!$this->isConfigured) {
            return ['success' => false, 'message' => 'Google API not configured'];
        }

        try {
            $this->driveService->files->delete($fileId);

            return [
                'success' => true,
                'message' => 'File deleted successfully'
            ];
        } catch (Exception $e) {
            error_log("Error deleting file: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error deleting file: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update an existing Google Spreadsheet with new data
     */
    public function updateExistingSpreadsheet($spreadsheetId, $data, $range = 'A1')
    {
        if (!$this->isConfigured) {
            return [
                'success' => false,
                'message' => 'Google API not configured. Please set up google-credentials.json file.'
            ];
        }

        try {
            // Skip clearing data to avoid compatibility issues
            // Instead, just overwrite the data
            error_log("Skipping clear operation to avoid GuzzleHttp compatibility issues");

            // Add new data
            $body = new Google_Service_Sheets_ValueRange([
                'values' => $data
            ]);

            $params = [
                'valueInputOption' => 'RAW'
            ];

            $result = $this->sheetsService->spreadsheets_values->update(
                $spreadsheetId,
                $range,
                $body,
                $params
            );

            // Format the header row
            if (!empty($data)) {
                $this->formatHeaderRow($spreadsheetId, count($data[0]));
            }

            $spreadsheetUrl = "https://docs.google.com/spreadsheets/d/{$spreadsheetId}";

            return [
                'success' => true,
                'spreadsheetId' => $spreadsheetId,
                'url' => $spreadsheetUrl,
                'message' => 'Existing spreadsheet updated successfully',
                'updatedCells' => $result->getUpdatedCells()
            ];
        } catch (Exception $e) {
            error_log("Error updating existing spreadsheet: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error updating spreadsheet: ' . $e->getMessage(),
                'error_type' => get_class($e)
            ];
        }
    }

    /**
     * Upload file to a specific existing folder in Google Drive
     */
    public function uploadToExistingFolder($folderId, $filename, $content, $mimeType)
    {
        if (!$this->isConfigured) {
            return [
                'success' => false,
                'message' => 'Google API not configured. Please set up google-credentials.json file.'
            ];
        }

        try {
            $fileMetadata = new Google_Service_Drive_DriveFile([
                'name' => $filename,
                'parents' => [$folderId]
            ]);

            // Use proper Google Drive API v3 upload method with parameters array
            $optParams = [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id,webViewLink,webContentLink'
            ];

            $file = $this->driveService->files->create($fileMetadata, $optParams);

            $fileId = $file->getId();
            $viewUrl = "https://drive.google.com/file/d/{$fileId}/view";

            // Make the file publicly accessible (optional)
            $this->shareFile($fileId, 'reader', 'anyone');

            return [
                'success' => true,
                'fileId' => $fileId,
                'viewUrl' => $viewUrl,
                'folderId' => $folderId,
                'message' => 'File uploaded successfully to existing folder'
            ];
        } catch (Exception $e) {
            error_log("Error uploading to existing folder: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error uploading to folder: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test Google Drive upload functionality
     */
    public function testDriveUpload($folderId = null)
    {
        if (!$this->isConfigured) {
            return [
                'success' => false,
                'message' => 'Google API not configured'
            ];
        }

        try {
            $testContent = "Test file created at " . date('Y-m-d H:i:s') . "\nThis is a test upload to Google Drive.";
            $filename = 'test_drive_upload_' . date('Y-m-d_H-i-s') . '.txt';

            if ($folderId) {
                $result = $this->uploadToExistingFolder($folderId, $filename, $testContent, 'text/plain');
            } else {
                $result = $this->uploadToDrive($filename, $testContent, 'text/plain');
            }

            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Drive test failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Convert array data to CSV format
     */
    public static function arrayToCSV($data)
    {
        $csvContent = '';
        foreach ($data as $row) {
            $csvContent .= '"' . implode('","', array_map('str_replace', ['"'], ['""'], $row)) . '"' . "\n";
        }
        return $csvContent;
    }

    /**
     * Check network connectivity and SSL configuration
     */
    private function checkNetworkAndSSL()
    {
        try {
            // Check if basic internet connectivity works
            $context = stream_context_create([
                'http' => [
                    'timeout' => 10,
                    'method' => 'GET'
                ]
            ]);

            $response = @file_get_contents('https://www.googleapis.com', false, $context);
            if ($response === false) {
                return [
                    'success' => false,
                    'message' => 'Cannot connect to Google APIs. Check internet connection and firewall.'
                ];
            }

            // Check SSL/TLS support
            if (!extension_loaded('openssl')) {
                return [
                    'success' => false,
                    'message' => 'OpenSSL extension not loaded. Required for HTTPS connections.'
                ];
            }

            // Check cURL configuration
            if (!extension_loaded('curl')) {
                return [
                    'success' => false,
                    'message' => 'cURL extension not loaded. Required for Google API requests.'
                ];
            }

            return [
                'success' => true,
                'message' => 'Network and SSL checks passed'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Network check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Parse Google API errors and provide user-friendly messages
     */
    private function parseGoogleAPIError($exception)
    {
        $message = $exception->getMessage();
        $trace = $exception->getTraceAsString();

        // Check for common error patterns
        if (strpos($trace, 'CurlFactory') !== false) {
            return 'Network/SSL connection error. This often occurs on local XAMPP environments. Try: 1) Update XAMPP to latest version, 2) Check firewall settings, 3) Verify SSL certificates are up to date.';
        }

        if (strpos($message, 'authentication') !== false || strpos($message, 'token') !== false) {
            return 'Authentication error. Please verify your google-credentials.json file is correct and the service account has proper permissions.';
        }

        if (strpos($message, 'permission') !== false || strpos($message, 'forbidden') !== false) {
            return 'Permission denied. Ensure the Google Sheets and Drive APIs are enabled in your Google Cloud Console and your service account has the required permissions.';
        }

        if (strpos($message, 'timeout') !== false) {
            return 'Request timeout. Your network connection may be slow or unstable.';
        }

        if (strpos($message, 'SSL') !== false || strpos($message, 'certificate') !== false) {
            return 'SSL certificate error. Your system may have outdated certificates or SSL configuration issues.';
        }

        // Default fallback
        return 'Google API error: ' . $message . '. This may be due to network connectivity, SSL configuration, or authentication issues.';
    }

    /**
     * Get configuration status for display
     */
    public function getConfigStatus()
    {
        if (!$this->isConfigured) {
            return [
                'configured' => false,
                'message' => 'Google API not configured. Please follow the setup guide.',
                'requirements' => [
                    'google-credentials.json file',
                    'Google Sheets API enabled',
                    'Google Drive API enabled',
                    'Composer dependencies installed'
                ]
            ];
        }

        try {
            // First, let's just check if we have the basic client setup
            if (!$this->client || !$this->driveService) {
                return [
                    'configured' => false,
                    'message' => 'Google API client or services not properly initialized.'
                ];
            }

            // Check if credentials file exists and is readable
            $credentialsPath = 'google-credentials.json';
            if (!file_exists($credentialsPath) || !is_readable($credentialsPath)) {
                return [
                    'configured' => false,
                    'message' => 'Google credentials file is missing or not readable.'
                ];
            }

            // Verify credentials file has required fields
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            if (!$credentials || !isset($credentials['client_email']) || !isset($credentials['private_key'])) {
                return [
                    'configured' => false,
                    'message' => 'Google credentials file is invalid or missing required fields.'
                ];
            }

            return [
                'configured' => true,
                'message' => 'Google API appears to be properly configured.',
                'services' => ['Google Sheets API', 'Google Drive API'],
                'client_email' => $credentials['client_email'],
                'note' => 'Basic configuration verified. Live API testing may require additional network/authentication setup.'
            ];
        } catch (Exception $e) {
            // Log the full error for debugging
            error_log("Google API config status error: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());

            return [
                'configured' => false,
                'message' => 'Google API configuration error: ' . $e->getMessage(),
                'error_type' => get_class($e),
                'suggestion' => 'Check network connectivity, firewall settings, and SSL certificates.'
            ];
        }
    }
}

/**
 * Demo/Fallback implementation for when Google API is not configured
 */
class DemoGoogleAPIManager
{
    public function isConfigured()
    {
        return false;
    }

    public function createSpreadsheet($title, $data)
    {
        return [
            'success' => true,
            'spreadsheetId' => 'demo_' . time(),
            'url' => 'https://docs.google.com/spreadsheets/d/demo_' . time(),
            'message' => 'DEMO MODE: Report would be created in Google Sheets. Set up Google API for real functionality.'
        ];
    }

    public function uploadToDrive($filename, $content, $mimeType)
    {
        return [
            'success' => true,
            'fileId' => 'demo_file_' . time(),
            'viewUrl' => 'https://drive.google.com/file/d/demo_file_' . time(),
            'downloadUrl' => 'https://drive.google.com/uc?id=demo_file_' . time(),
            'message' => 'DEMO MODE: File would be uploaded to Google Drive. Set up Google API for real functionality.'
        ];
    }

    public function getConfigStatus()
    {
        return [
            'configured' => false,
            'message' => 'Running in demo mode. Set up Google API credentials for full functionality.',
            'requirements' => [
                'Create Google Cloud Project',
                'Enable Google Sheets & Drive APIs',
                'Create service account credentials',
                'Download google-credentials.json',
                'Install composer dependencies'
            ]
        ];
    }

    public function listFiles($folderId = null, $pageSize = 10)
    {
        return [
            'success' => true,
            'files' => [
                [
                    'id' => 'demo1',
                    'name' => 'Sample Attendance Report.csv',
                    'size' => '1024',
                    'createdTime' => date('Y-m-d\TH:i:s\Z'),
                    'url' => '#'
                ],
                [
                    'id' => 'demo2',
                    'name' => 'Sample Events Report.csv',
                    'size' => '2048',
                    'createdTime' => date('Y-m-d\TH:i:s\Z'),
                    'url' => '#'
                ]
            ],
            'message' => 'Demo data - real files would appear here with proper Google API setup'
        ];
    }
}
