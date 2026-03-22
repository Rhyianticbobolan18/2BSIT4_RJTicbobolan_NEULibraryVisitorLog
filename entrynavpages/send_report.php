<?php
session_start();
require_once '../includes/db_connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    if (!$conn) {
        header("Location: help.php?status=db_error");
        exit();
    }

    // 1. Capture the data from the help.php form
    $userID    = $_POST['userID']     ?? '';
    $issueType = $_POST['issue_type'] ?? '';
    $message   = $_POST['message']    ?? '';

    // 2. Prepare the SQL query to insert into your problem_reports table using a prepared statement
    $stmt = $conn->prepare(
        "INSERT INTO problem_reports (user_identifier, issue_type, description, status) 
         VALUES (?, ?, ?, 'Pending')"
    );

    if ($stmt && $stmt->bind_param("sss", $userID, $issueType, $message) && $stmt->execute()) {
        // success redirect
        header("Location: help.php?status=success");
        exit();
    } else {
        // error redirect
        header("Location: help.php?status=error");
        exit();
    }
} else {
    // If someone tries to visit send_report.php directly in the browser, send them back
    header("Location: help.php");
    exit();
}
?>