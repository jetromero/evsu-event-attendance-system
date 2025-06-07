<?php

/**
 * Google Integration Configuration
 * Add your existing Google Spreadsheet and Drive folder IDs here
 */

// Your existing Google Spreadsheet ID (extract from the URL)
// Example: https://docs.google.com/spreadsheets/d/1ABC123DEF456/edit
// Your ID would be: 1ABC123DEF456
define('GOOGLE_SPREADSHEET_ID', '1RCKO6ABpoFMz9fEF1rZVZMFOix7R8WW4l8t56ryRYJ8');

// Your existing Google Drive folder ID (extract from the URL)
// Example: https://drive.google.com/drive/folders/1XYZ789ABC123
// Your ID would be: 1XYZ789ABC123
define('GOOGLE_DRIVE_FOLDER_ID', '1n6FXkOLicYJhriHXSRLq8Xh_x7mRG0BZ');

// Configuration for different report types
$GOOGLE_CONFIG = [
    'attendance_spreadsheet_id' => GOOGLE_SPREADSHEET_ID, // Use same spreadsheet for all reports
    'events_spreadsheet_id' => GOOGLE_SPREADSHEET_ID,     // Or specify different ones
    'drive_folder_id' => GOOGLE_DRIVE_FOLDER_ID,

    // Sheet names within your spreadsheet (optional)
    'attendance_sheet_name' => 'Attendance',
    'events_sheet_name' => 'Events',

    // Whether to use existing resources or create new ones
    'use_existing_spreadsheet' => true,
    'use_existing_folder' => true
];

/**
 * How to get your IDs:
 * 
 * Spreadsheet ID:
 * 1. Open your Google Spreadsheet
 * 2. Look at the URL: https://docs.google.com/spreadsheets/d/YOUR_SPREADSHEET_ID/edit
 * 3. Copy the long string between /d/ and /edit
 * 
 * Folder ID:
 * 1. Open your Google Drive folder
 * 2. Look at the URL: https://drive.google.com/drive/folders/YOUR_FOLDER_ID
 * 3. Copy the long string after /folders/
 */
