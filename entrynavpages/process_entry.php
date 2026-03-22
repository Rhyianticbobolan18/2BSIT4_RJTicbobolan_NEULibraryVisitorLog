<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/profile_image.php';

if ((!isset($_SESSION['user_id']) && !isset($_SESSION['pending_google_user'])) || !isset($_POST['reason'])) {
    // FIX: Back out to root index
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'] ?? null;
$reason = $_POST['reason'];
$specific_details = isset($_POST['specific_reason']) ? trim($_POST['specific_reason']) : '';

// If Google user is new, insert into DB first
$pending_user = $_SESSION['pending_google_user'] ?? null;
if ($pending_user && $user_id === null) {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $department_id = trim($_POST['department_id'] ?? '');
    $role_choice = trim($_POST['role_choice'] ?? '');

    if ($first_name === '' || $last_name === '' || $department_id === '' || !in_array($role_choice, ['Student', 'Faculty/Admin'], true)) {
        header("Location: VisitorEntryForm.php?new=1");
        exit();
    }

    // Remove middle initial tokens (e.g., "M." or "M") from first name
    $nameParts = preg_split('/\s+/', $first_name);
    $filteredParts = [];
    foreach ($nameParts as $part) {
        $clean = trim($part);
        if ($clean === '') {
            continue;
        }
        if (preg_match('/^[A-Za-z]\.?$/', $clean)) {
            continue;
        }
        $filteredParts[] = $clean;
    }
    if (!empty($filteredParts)) {
        $first_name = implode(' ', $filteredParts);
    }

    $email = $pending_user['email'] ?? '';
    if ($email === '' || !str_ends_with(strtolower($email), '@neu.edu.ph')) {
        header("Location: ../index.php?error=1");
        exit();
    }

    $picture = trim($pending_user['picture'] ?? '');
    $pictureUrl = $picture;
    $pictureForDb = $picture;
    if ($pictureForDb === '' || is_remote_image_url($pictureForDb)) {
        $pictureForDb = 'default.png';
    }

    // Avoid duplicate insert if another session already created the user
    $checkExisting = $conn->prepare("SELECT studentID AS id, 'Student' AS role, 'student' AS src, profile_image FROM students WHERE institutionalEmail = ?
                                     UNION ALL
                                     SELECT emplID AS id, 'Faculty/Admin' AS role, 'employee' AS src, profile_image FROM employees WHERE institutionalEmail = ?
                                     LIMIT 1");
    $checkExisting->bind_param("ss", $email, $email);
    $checkExisting->execute();
    $existing = $checkExisting->get_result()->fetch_assoc();
    if ($existing) {
        $user_id = $existing['id'];
        $existingProfile = $existing['profile_image'] ?? '';
        $existingSrc = $existing['src'] ?? 'student';
        $folder = $existingSrc === 'student' ? 'student' : 'admin';
        $existingLocalPath = __DIR__ . "/../profilepictures/$folder/" . $existingProfile;
        $needsProfileUpdate = $existingProfile === '' || $existingProfile === 'default.png' || is_remote_image_url($existingProfile) || !is_file($existingLocalPath);
        if ($needsProfileUpdate && is_remote_image_url($pictureUrl)) {
            $savedFilename = save_profile_image_from_url($pictureUrl, $folder, (string)$user_id);
            if ($savedFilename) {
                if ($existingSrc === 'student') {
                    $update = $conn->prepare("UPDATE students SET profile_image = ? WHERE studentID = ?");
                } else {
                    $update = $conn->prepare("UPDATE employees SET profile_image = ? WHERE emplID = ?");
                }
                $update->bind_param("ss", $savedFilename, $user_id);
                $update->execute();
            }
        }
    } else {
        if ($role_choice === 'Student') {
            $insert = $conn->prepare("INSERT INTO students (firstName, lastName, institutionalEmail, departmentID, role, status, profile_image) VALUES (?, ?, ?, ?, 'Student', 'Active', ?)");
            $insert->bind_param("sssss", $first_name, $last_name, $email, $department_id, $pictureForDb);
            $insert->execute();
            $user_id = $conn->insert_id;
            if (is_remote_image_url($pictureUrl)) {
                $savedFilename = save_profile_image_from_url($pictureUrl, 'student', (string)$user_id);
                if ($savedFilename) {
                    $update = $conn->prepare("UPDATE students SET profile_image = ? WHERE studentID = ?");
                    $update->bind_param("ss", $savedFilename, $user_id);
                    $update->execute();
                }
            }
        } else {
            $temp_password = bin2hex(random_bytes(8));
            $insert = $conn->prepare("INSERT INTO employees (firstName, lastName, institutionalEmail, password, departmentID, role, status, profile_image) VALUES (?, ?, ?, ?, ?, 'Faculty/Admin', 'Active', ?)");
            $insert->bind_param("ssssss", $first_name, $last_name, $email, $temp_password, $department_id, $pictureForDb);
            $insert->execute();
            $user_id = $conn->insert_id;
            if (is_remote_image_url($pictureUrl)) {
                $savedFilename = save_profile_image_from_url($pictureUrl, 'admin', (string)$user_id);
                if ($savedFilename) {
                    $update = $conn->prepare("UPDATE employees SET profile_image = ? WHERE emplID = ?");
                    $update->bind_param("ss", $savedFilename, $user_id);
                    $update->execute();
                }
            }
        }
    }

    $_SESSION['user_id'] = $user_id;
    unset($_SESSION['pending_google_user']);
}

// --- SAFETY RE-CHECK: ensure user is still not blocked ---
$blockCheckSql = "
    SELECT 'student' AS src, firstName, lastName, profile_image, departmentID, status, studentID AS id
    FROM students WHERE studentID = ?
    UNION ALL
    SELECT 'employee' AS src, firstName, lastName, profile_image, departmentID, status, emplID AS id
    FROM employees WHERE emplID = ?
    LIMIT 1
";
if ($user_id !== null) {
    $blockStmt = $conn->prepare($blockCheckSql);
    $blockStmt->bind_param("ss", $user_id, $user_id);
    $blockStmt->execute();
    $blockRes = $blockStmt->get_result();
    if ($u = $blockRes->fetch_assoc()) {
        if (strtolower($u['status']) === 'blocked') {
            $_SESSION['blocked_user'] = [
                'firstName'    => $u['firstName'],
                'lastName'     => $u['lastName'],
                'profile_image'=> $u['profile_image'],
                'departmentID' => $u['departmentID'],
                'type'         => $u['src'] === 'student' ? 'student' : 'admin',
                'studentID'    => $u['src'] === 'student' ? $u['id'] : null,
                'emplID'       => $u['src'] === 'employee' ? $u['id'] : null,
            ];
            unset($_SESSION['user_id']);
            header("Location: ../index.php?error=blocked");
            exit();
        }
    }
}

// --- CLEAN DATA LOGIC ---
// If 'Others' is selected, we discard the word 'Others' and use the custom text only.
if ($reason == "Others" && !empty($specific_details)) {
    $reason = $specific_details; 
}
// ------------------------

// 1. DUPLICATE CHECK (Prevent multiple entries per day)
$check_sql = "SELECT logID FROM history_logs WHERE user_identifier = ? AND date = CURDATE()";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $user_id);
$check_stmt->execute();
if ($check_stmt->get_result()->num_rows > 0) {
    unset($_SESSION['user_id']);
    // FIX: Back out to root index
    header("Location: ../index.php?error=already_logged");
    exit();
}

// 2. ROLE DETECTION
$user_type = null;

// Check Students table
$stmt_stu = $conn->prepare("SELECT studentID FROM students WHERE studentID = ?");
$stmt_stu->bind_param("s", $user_id);
$stmt_stu->execute();
if ($stmt_stu->get_result()->num_rows > 0) {
    $user_type = 'Student';
} else {
    // Check Employees table
    $stmt_emp = $conn->prepare("SELECT emplID FROM employees WHERE emplID = ?");
    $stmt_emp->bind_param("s", $user_id);
    $stmt_emp->execute();
    if ($stmt_emp->get_result()->num_rows > 0) {
        $user_type = 'Employee';
    }
}

if (is_null($user_type)) {
    die("Error: User type could not be determined for ID: " . htmlspecialchars($user_id));
}

// 3. INSERT INTO HISTORY_LOGS
$sql = "INSERT INTO history_logs (user_identifier, user_type, date, time, reason) 
        VALUES (?, ?, CURDATE(), CURTIME(), ?)";

$stmt_insert = $conn->prepare($sql);
$stmt_insert->bind_param("sss", $user_id, $user_type, $reason);

if ($stmt_insert->execute()) {
    unset($_SESSION['user_id']);
    // FIX: Back out to root index
    header("Location: ../index.php?status=success");
    exit();
} else {
    echo "Database Error: " . $conn->error;
}
?>
