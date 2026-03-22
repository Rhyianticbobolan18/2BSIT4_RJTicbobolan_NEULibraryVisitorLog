<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/google_oauth.php';
require_once '../includes/profile_image.php';

if (!isset($_GET['code']) || !isset($_GET['state'])) {
    header('Location: ../index.php?error=1');
    exit();
}

if (!isset($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    header('Location: ../index.php?error=1');
    exit();
}
unset($_SESSION['google_oauth_state']);

$token = google_exchange_code($_GET['code']);
if (!$token['ok']) {
    header('Location: ../index.php?error=1');
    exit();
}

$accessToken = $token['data']['access_token'] ?? '';
$idToken = $token['data']['id_token'] ?? '';

function base64url_decode_local(string $data): string {
    $remainder = strlen($data) % 4;
    if ($remainder) $data .= str_repeat('=', 4 - $remainder);
    return base64_decode(strtr($data, '-_', '+/')) ?: '';
}

$profile = [];
if ($idToken !== '') {
    $parts = explode('.', $idToken);
    if (count($parts) === 3) {
        $payload = json_decode(base64url_decode_local($parts[1]), true);
        if (is_array($payload)) {
            $profile = $payload;
        }
    }
}

if (empty($profile) && $accessToken !== '') {
    $userInfo = google_fetch_userinfo($accessToken);
    if ($userInfo['ok']) {
        $profile = $userInfo['data'] ?? [];
    }
}

$email = strtolower(trim($profile['email'] ?? ''));
$emailVerified = !empty($profile['email_verified']);

if ($email === '' || !$emailVerified || !str_ends_with($email, '@neu.edu.ph')) {
    $_SESSION['error_type'] = 'invalid_domain';
    header('Location: ../index.php?error=1');
    exit();
}

$found_id = null;
$user_data = null;

// Students
$stmt = $conn->prepare("SELECT studentID, firstName, lastName, profile_image, departmentID, status, block_reason FROM students WHERE institutionalEmail = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $found_id = $row['studentID'];
    $user_data = $row;
    $user_data['type'] = 'student';
}

if (!$found_id) {
    // Employees
    $stmt = $conn->prepare("SELECT emplID, firstName, lastName, profile_image, departmentID, status, block_reason FROM employees WHERE institutionalEmail = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $found_id = $row['emplID'];
        $user_data = $row;
        $user_data['type'] = 'admin';
    }
}

if ($found_id) {
    $pictureUrl = trim($profile['picture'] ?? '');
    if (is_remote_image_url($pictureUrl)) {
        $currentProfile = $user_data['profile_image'] ?? '';
        $folder = $user_data['type'] === 'student' ? 'student' : 'admin';
        $currentLocalPath = __DIR__ . "/../profilepictures/$folder/" . $currentProfile;
        $needsUpdate = $currentProfile === '' || $currentProfile === 'default.png' || is_remote_image_url($currentProfile) || !is_file($currentLocalPath);
        if ($needsUpdate) {
            $savedFilename = save_profile_image_from_url($pictureUrl, $folder, (string)$found_id);
            if ($savedFilename) {
                if ($user_data['type'] === 'student') {
                    $update = $conn->prepare("UPDATE students SET profile_image = ? WHERE studentID = ?");
                } else {
                    $update = $conn->prepare("UPDATE employees SET profile_image = ? WHERE emplID = ?");
                }
                $update->bind_param("ss", $savedFilename, $found_id);
                $update->execute();
                $user_data['profile_image'] = $savedFilename;
            }
        }
    }

    if (strtolower($user_data['status']) === 'blocked') {
        $_SESSION['blocked_user'] = $user_data;
        header('Location: ../index.php?error=blocked');
        exit();
    }

    $check_log = $conn->prepare("SELECT logID FROM history_logs WHERE user_identifier = ? AND date = CURDATE()");
    $check_log->bind_param("s", $found_id);
    $check_log->execute();
    if ($check_log->get_result()->num_rows > 0) {
        header('Location: ../index.php?error=already_logged');
        exit();
    }

    $_SESSION['user_id'] = $found_id;
    header('Location: VisitorEntryForm.php');
    exit();
}

// New user: allow entry and collect details
$given = trim($profile['given_name'] ?? '');
$family = trim($profile['family_name'] ?? '');
$full = trim($profile['name'] ?? '');
if ($given === '' || $family === '') {
    if ($full !== '') {
        $parts = preg_split('/\s+/', $full);
        $given = $given ?: ($parts[0] ?? '');
        $family = $family ?: ($parts[count($parts) - 1] ?? '');
    }
}

$_SESSION['pending_google_user'] = [
    'email' => $email,
    'first_name' => $given,
    'last_name' => $family,
    'picture' => $profile['picture'] ?? ''
];

header('Location: VisitorEntryForm.php?new=1');
exit();
