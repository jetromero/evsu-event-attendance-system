<?php
// Include Supabase configuration and functions
require_once 'supabase_config.php';

// Legacy MySQL configuration (kept for reference/migration)
// Uncomment these if you need to migrate data from MySQL to Supabase
/*
$host = 'localhost';
$dbname = 'joyces_db';
$username = 'root';
$password_db = '';

function getDBConnection() {
    global $host, $dbname, $username, $password_db;
    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password_db);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}
*/

// All authentication and user management functions are now in supabase_config.php
// This file now serves as the main configuration entry point
