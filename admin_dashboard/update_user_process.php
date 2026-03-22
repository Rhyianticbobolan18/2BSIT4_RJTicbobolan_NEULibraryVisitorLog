<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';

// Only logged-in admins can update user records
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    exit('Unauthorized access');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') exit('Direct access denied');
csrf_require();

$id = trim($_POST['id']);
$type = $_POST['type']; // 'Student' or 'Employee'
$firstName = trim($_POST['firstName']);
$lastName = trim($_POST['lastName']);
$email = trim($_POST['email']);
$deptID = trim($_POST['departmentID']);

// --- NEW DOMAIN VALIDATION ---
$allowedDomain = "@neu.edu.ph";
if (!str_ends_with(strtolower($email), $allowedDomain)) {
    exit("Invalid Email: Only $allowedDomain addresses are permitted.");
}

$allowed = [
    'Student' => ['table' => 'students', 'idCol' => 'studentID', 'folder' => 'student'],
    'Employee' => ['table' => 'employees', 'idCol' => 'emplID', 'folder' => 'admin'],
];
if (!isset($allowed[$type])) {
    exit('Invalid Request');
}
$table = $allowed[$type]['table'];
$idCol = $allowed[$type]['idCol'];
$folder = $allowed[$type]['folder'];

// 1. File Upload Logic
$newImageFile = null;
if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === 0) {
    $maxSize = 2 * 1024 * 1024; // 2MB
    if (($_FILES['profile_image']['size'] ?? 0) > $maxSize) {
        exit("Image too large. Max size is 2MB.");
    }

    $uploadTmpPath = $_FILES['profile_image']['tmp_name'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($uploadTmpPath);
    $allowedMimes = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/gif" => "gif",
        "image/webp" => "webp"
    ];
    if (!isset($allowedMimes[$mime])) {
        exit("Invalid image format. Only JPG, JPEG, PNG, WEBP, and GIF are allowed.");
    }
    $extension = $allowedMimes[$mime];
    $targetDir = __DIR__ . "/../profilepictures/" . $folder . "/";
    
    // Ensure directory exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $newFileName = $id . "." . $extension;
    $uploadPath = $targetDir . $newFileName;

    if (move_uploaded_file($uploadTmpPath, $uploadPath)) {
        $newImageFile = $newFileName;
    }
}

// 2. Password Logic (PLAIN TEXT as requested)
$newPassword = null;
if ($type === 'Employee' && !empty($_POST['new_password'])) {
    $newPassword = $_POST['new_password'];
}

// 3. Database Update
// Note: We use the prepared statement for the 4 standard fields and the ID.
// The $imgUpdateSql and $passUpdateSql are appended as strings safely because 
// the internal variables were already escaped above.
$fields = [
    "firstName = ?",
    "lastName = ?",
    "institutionalEmail = ?",
    "departmentID = ?"
];
$types = "ssss";
$params = [$firstName, $lastName, $email, $deptID];

if (!empty($newImageFile)) {
    $fields[] = "profile_image = ?";
    $types .= "s";
    $params[] = $newImageFile;
}
if (!empty($newPassword)) {
    $fields[] = "password = ?";
    $types .= "s";
    $params[] = $newPassword;
}

$types .= "s";
$params[] = $id;

$sql = "UPDATE $table SET " . implode(", ", $fields) . " WHERE $idCol = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo "success"; 
} else {
    echo "Error: Update failed.";
}
?>
