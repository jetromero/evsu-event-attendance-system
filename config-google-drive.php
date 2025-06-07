<?php

/**
 * Google Drive Folder Configuration
 * Set up custom folders for report organization
 */

require_once 'google-api-manager.php';

// Configuration
$DRIVE_CONFIG = [
    'main_folder' => 'EVSU Attendance Reports',
    'subfolders' => [
        'attendance' => 'Attendance Reports',
        'events' => 'Event Reports',
        'users' => 'User Reports',
        'archive' => 'Archive'
    ]
];

function setupGoogleDriveFolders()
{
    global $DRIVE_CONFIG;

    $googleAPI = getGoogleAPIManager();
    if (!$googleAPI->isConfigured()) {
        return ['success' => false, 'message' => 'Google API not configured'];
    }

    $result = ['success' => true, 'folders' => []];

    try {
        // Create main folder
        echo "Creating main folder: " . $DRIVE_CONFIG['main_folder'] . "\n";
        $mainFolder = $googleAPI->createFolder($DRIVE_CONFIG['main_folder']);

        if ($mainFolder['success']) {
            $mainFolderId = $mainFolder['folderId'];
            $result['folders']['main'] = $mainFolder;

            // Create subfolders
            foreach ($DRIVE_CONFIG['subfolders'] as $key => $name) {
                echo "Creating subfolder: $name\n";
                $subfolder = $googleAPI->createFolder($name, $mainFolderId);

                if ($subfolder['success']) {
                    $result['folders'][$key] = $subfolder;
                    echo "✅ Created: $name\n";
                } else {
                    echo "❌ Failed: $name - " . $subfolder['message'] . "\n";
                }
            }
        } else {
            return ['success' => false, 'message' => $mainFolder['message']];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }

    return $result;
}

// If run directly, execute setup
if (php_sapi_name() === 'cli' || basename($_SERVER['PHP_SELF']) === 'config-google-drive.php') {
    echo "🗂️ Setting up Google Drive folders...\n\n";

    $result = setupGoogleDriveFolders();

    if ($result['success']) {
        echo "\n✅ All folders created successfully!\n";
        echo "\nFolder structure:\n";
        foreach ($result['folders'] as $type => $folder) {
            echo "- $type: " . $folder['url'] . "\n";
        }
    } else {
        echo "\n❌ Error: " . $result['message'] . "\n";
    }
}

/**
 * Get folder ID for specific report type
 */
function getReportFolderId($reportType)
{
    // Return null to use default Drive location
    // Implement folder mapping logic here if needed
    return null;
}
