<?php
session_start();
require_once '../includes/db_connect.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

if (!isset($_POST['search']) || empty(trim($_POST['search']))) {
    echo json_encode(['exists' => false]);
    exit();
}

$search = trim($_POST['search']);

// Check students table
$stmt = $conn->prepare("SELECT firstName, lastName, studentID, status FROM students WHERE
    firstName LIKE ? OR lastName LIKE ? OR studentID LIKE ? OR CONCAT(firstName, ' ', lastName) LIKE ?");
$searchParam = "%$search%";
$stmt->bind_param("ssss", $searchParam, $searchParam, $searchParam, $searchParam);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'exists' => true,
        'name' => $row['firstName'] . ' ' . $row['lastName'],
        'type' => 'student',
        'status' => $row['status']
    ]);
    exit();
}

// Check employees table
$stmt = $conn->prepare("SELECT firstName, lastName, emplID, status FROM employees WHERE
    firstName LIKE ? OR lastName LIKE ? OR emplID LIKE ? OR CONCAT(firstName, ' ', lastName) LIKE ?");
$stmt->bind_param("ssss", $searchParam, $searchParam, $searchParam, $searchParam);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    echo json_encode([
        'exists' => true,
        'name' => $row['firstName'] . ' ' . $row['lastName'],
        'type' => 'employee',
        'status' => $row['status']
    ]);
    exit();
}

// User doesn't exist
echo json_encode(['exists' => false]);
?>