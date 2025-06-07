<?php
session_start();
require_once 'supabase_config.php';

// Check if user is logged in and is admin
requireLogin();
$user = getCurrentUser();
if (!$user || $user['role'] !== 'admin') {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Access denied. Admin privileges required.'];
    header('Location: dashboard.php');
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Invalid request method.'];
    header('Location: events.php');
    exit();
}

// Get event ID from POST data
if (!isset($_POST['event_id']) || empty($_POST['event_id'])) {
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'Event ID is required.'];
    header('Location: events.php');
    exit();
}

$event_id = $_POST['event_id'];

try {
    $supabase = getSupabaseClient();

    // First, get the event to verify it exists and get its title for confirmation
    $events = $supabase->select('events', 'title', ['id' => $event_id]);

    if (empty($events)) {
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'Event not found.'];
        header('Location: events.php');
        exit();
    }

    $eventTitle = $events[0]['title'];

    // Delete associated attendance records first (due to foreign key constraints)
    $supabase->delete('attendance', ['event_id' => $event_id]);

    // Now delete the event
    $result = $supabase->delete('events', ['id' => $event_id]);

    if ($result) {
        $_SESSION['notification'] = ['type' => 'success', 'message' => "Event '{$eventTitle}' has been deleted successfully."];
    } else {
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'Failed to delete the event. Please try again.'];
    }
} catch (Exception $e) {
    error_log("Error deleting event: " . $e->getMessage());
    $_SESSION['notification'] = ['type' => 'error', 'message' => 'An error occurred while deleting the event.'];
}

// Redirect back to events page
header('Location: events.php');
exit();
