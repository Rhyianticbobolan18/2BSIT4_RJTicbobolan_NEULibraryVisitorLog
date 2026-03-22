<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo "Method Not Allowed";
    exit();
}

csrf_require();

$emplId = isset($_POST['empl_id']) ? trim($_POST['empl_id']) : '';
$approve = isset($_POST['approve']) ? (int)$_POST['approve'] : null;

if ($emplId === '' || ($approve !== 0 && $approve !== 1)) {
    http_response_code(400);
    echo "Invalid request";
    exit();
}

$stmt = $conn->prepare("UPDATE employees SET is_admin_approved = ? WHERE emplID = ?");
if (!$stmt) {
    http_response_code(500);
    echo "Prepare failed";
    exit();
}
$emplIdInt = (int)$emplId;
$stmt->bind_param("ii", $approve, $emplIdInt);

if ($stmt->execute()) {
    echo "success";
} else {
    http_response_code(500);
    echo "Update failed";
}

$stmt->close();
?>
