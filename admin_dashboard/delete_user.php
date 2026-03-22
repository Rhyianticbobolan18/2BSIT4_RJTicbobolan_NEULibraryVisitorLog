<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$id = $_POST['id'] ?? '';
$type = strtolower($_POST['type'] ?? '');
$adminID = $_SESSION['emplID']; 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

csrf_require();

if (!empty($id) && !empty($type)) {
    $allowed = [
        'student' => ['table' => 'students', 'idColumn' => 'studentID', 'folder' => 'student'],
        'employee' => ['table' => 'employees', 'idColumn' => 'emplID', 'folder' => 'admin'],
    ];
    if (!isset($allowed[$type])) {
        header("Location: user_management.php");
        exit();
    }
    $table = $allowed[$type]['table'];
    $idColumn = $allowed[$type]['idColumn'];

    $userQuery = $conn->prepare("SELECT firstName, lastName, profile_image FROM $table WHERE $idColumn = ?");
    $userQuery->bind_param("i", $id); 
    $userQuery->execute();
    $userData = $userQuery->get_result()->fetch_assoc();

    if ($userData) {
        $fullName = $userData['firstName'] . ' ' . $userData['lastName'];

        // Image Cleanup
        if (!empty($userData['profile_image']) && $userData['profile_image'] !== 'default.png') {
            $folder = $allowed[$type]['folder'];
            $filePath = "../profilepictures/$folder/" . $userData['profile_image'];
            if (file_exists($filePath)) unlink($filePath);
        }
        
        // DELETE ALL HISTORY LOGS BELONGING TO THIS USER
        $uTypeFormatted = ucfirst($type); // 'Student' or 'Employee'
        $deleteLogs = $conn->prepare("DELETE FROM history_logs WHERE user_identifier = ? AND user_type = ?");
        $deleteLogs->bind_param("is", $id, $uTypeFormatted);
        $deleteLogs->execute();

        // DELETE THE RECORD
        $deleteSql = "DELETE FROM $table WHERE $idColumn = ?";
        $stmt = $conn->prepare($deleteSql);
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            header("Location: user_management.php?msg=UserDeleted");
        } else {
            header("Location: user_management.php?msg=DependencyError");
        }
    } else {
        header("Location: user_management.php?msg=UserNotFound");
    }
} else {
    header("Location: user_management.php");
}
exit();
