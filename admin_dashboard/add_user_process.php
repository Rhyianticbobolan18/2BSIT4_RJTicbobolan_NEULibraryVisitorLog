<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/csrf.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo "Unauthorized access.";
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $userType = $_POST['user_type'] ?? 'Student';
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['institutionalEmail'] ?? '');
    $departmentID = trim($_POST['departmentID'] ?? '');
    $password = $_POST['password'] ?? ''; // PLAIN TEXT AS REQUESTED
    
    // Domain validation backend check
    if (!str_ends_with(strtolower($email), '@neu.edu.ph')) {
        echo "Only @neu.edu.ph emails are allowed.";
        exit();
    }

    // Default image
    $imageName = 'default.png';
    $uploadTmpPath = '';
    $uploadFolder = ($userType === 'Employee') ? 'admin' : 'student';
    $extension = '';

    // Handle File Upload if provided
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        $maxSize = 2 * 1024 * 1024; // 2MB
        if (($_FILES['profile_image']['size'] ?? 0) > $maxSize) {
            echo "Image too large. Max size is 2MB.";
            exit();
        }

        $uploadTmpPath = $_FILES["profile_image"]["tmp_name"];
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($uploadTmpPath);
        $allowedMimes = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/gif" => "gif",
            "image/webp" => "webp"
        ];
        if (!isset($allowedMimes[$mime])) {
            echo "Invalid image format. Only JPG, JPEG, PNG, WEBP, and GIF are allowed.";
            exit();
        }
        $extension = $allowedMimes[$mime];
    }

    // Insert based on User Type
    if ($userType === 'Student') {
        // Check duplicate email
        $checkStmt = $conn->prepare("SELECT institutionalEmail FROM students WHERE institutionalEmail = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $check = $checkStmt->get_result();
        if ($check->num_rows > 0) {
            echo "Email is already registered as a Student.";
            $checkStmt->close();
            exit();
        }
        $checkStmt->close();

        $stmt = $conn->prepare("INSERT INTO students (firstName, lastName, institutionalEmail, departmentID, profile_image) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $firstName, $lastName, $email, $departmentID, $imageName);
        
        if ($stmt->execute()) {
            $newId = $conn->insert_id;

            // If an image was uploaded earlier, save it using the new user ID
            if (!empty($uploadTmpPath)) {
                $targetDir = __DIR__ . "/../profilepictures/$uploadFolder/";
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                $newFileName = $newId . '.' . $extension;
                $targetFilePath = $targetDir . $newFileName;
                if (move_uploaded_file($uploadTmpPath, $targetFilePath)) {
                    $updateStmt = $conn->prepare("UPDATE students SET profile_image = ? WHERE studentID = ?");
                    $updateStmt->bind_param("si", $newFileName, $newId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }

            echo "success";
        } else {
            echo "Database error. Please try again.";
        }
        $stmt->close();
        
    } else if ($userType === 'Employee') {
        // Check duplicate email
        $checkStmt = $conn->prepare("SELECT institutionalEmail FROM employees WHERE institutionalEmail = ?");
        $checkStmt->bind_param("s", $email);
        $checkStmt->execute();
        $check = $checkStmt->get_result();
        if ($check->num_rows > 0) {
            echo "Email is already registered as an Employee.";
            $checkStmt->close();
            exit();
        }
        $checkStmt->close();

        if(empty($password)) {
            echo "Password is required for Faculty/Admin.";
            exit();
        }

        $stmt = $conn->prepare("INSERT INTO employees (firstName, lastName, institutionalEmail, password, departmentID, profile_image) VALUES (?, ?, ?, ?, ?, ?)");
        // WARNING: Binding plain text password directly to DB as requested
        $stmt->bind_param("ssssss", $firstName, $lastName, $email, $password, $departmentID, $imageName);
        
        if ($stmt->execute()) {
            $newId = $conn->insert_id;

            // If an image was uploaded earlier, save it using the new user ID
            if (!empty($uploadTmpPath)) {
                $targetDir = __DIR__ . "/../profilepictures/$uploadFolder/";
                if (!is_dir($targetDir)) {
                    mkdir($targetDir, 0777, true);
                }

                $newFileName = $newId . '.' . $extension;
                $targetFilePath = $targetDir . $newFileName;
                if (move_uploaded_file($uploadTmpPath, $targetFilePath)) {
                    $updateStmt = $conn->prepare("UPDATE employees SET profile_image = ? WHERE emplID = ?");
                    $updateStmt->bind_param("si", $newFileName, $newId);
                    $updateStmt->execute();
                    $updateStmt->close();
                }
            }

            echo "success";
        } else {
            echo "Database error. Please try again.";
        }
        $stmt->close();
    } else {
        echo "Invalid User Type.";
    }
} else {
    echo "Invalid request method.";
}
?>
