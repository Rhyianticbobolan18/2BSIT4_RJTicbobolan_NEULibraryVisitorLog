<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';

// Security: Check if admin is actually logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit("Unauthorized access.");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

csrf_require();

// Check if IDs were sent via POST
if (isset($_POST['report_ids']) && is_array($_POST['report_ids'])) {
    $ids = $_POST['report_ids'];
    
    // Create placeholders (?,?,?) based on the number of IDs
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    
    // Prepare the statement
    $stmt = $conn->prepare("DELETE FROM problem_reports WHERE reportID IN ($placeholders)");
    
    // Dynamically bind the integer values
    $types = str_repeat('i', count($ids));
    $stmt->bind_param($types, ...$ids);
    
    if ($stmt->execute()) {
        header("Location: reports.php?msg=DeletedSuccessfully");
    } else {
        header("Location: reports.php?msg=Error");
    }
} else {
    // If no IDs were selected, just go back
    header("Location: reports.php");
}
exit();
