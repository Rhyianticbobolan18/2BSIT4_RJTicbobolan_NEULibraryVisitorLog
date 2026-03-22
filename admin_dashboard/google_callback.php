<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/google_oauth.php';

if (!isset($_GET['code']) || !isset($_GET['state'])) {
    header('Location: login.php?google_error=Missing%20code');
    exit();
}

if (!isset($_SESSION['google_oauth_state']) || $_GET['state'] !== $_SESSION['google_oauth_state']) {
    header('Location: login.php?google_error=Invalid%20state');
    exit();
}
unset($_SESSION['google_oauth_state']);

$token = google_exchange_code($_GET['code']);
if (!$token['ok']) {
    $err = $token['error'] ?? 'token_exchange_failed';
    $desc = $token['error_description'] ?? 'Token exchange failed';
    $msg = rawurlencode($err . ': ' . $desc);
    header('Location: login.php?google_error=' . $msg);
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
    if (!$userInfo['ok']) {
        $err = $userInfo['error'] ?? 'userinfo_failed';
        $desc = $userInfo['error_description'] ?? 'Unable to fetch profile';
        $msg = rawurlencode($err . ': ' . $desc);
        header('Location: login.php?google_error=' . $msg);
        exit();
    }
    $profile = $userInfo['data'] ?? [];
}

$email = strtolower(trim($profile['email'] ?? ''));
$emailVerified = !empty($profile['email_verified']);

if ($email === '') {
    header('Location: login.php?google_error=Missing%20email');
    exit();
}

if (!$emailVerified || !str_ends_with($email, '@neu.edu.ph')) {
    header('Location: login.php?google_error=Unauthorized%20email');
    exit();
}

$sql = "SELECT emplID, firstName, lastName, role, status, is_admin_approved FROM employees WHERE institutionalEmail = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if (strcasecmp($user['status'], 'Blocked') === 0) {
        header('Location: login.php?google_error=Account%20blocked');
        exit();
    }

    if (strcasecmp($user['role'], 'Faculty/Admin') !== 0) {
        header('Location: login.php?google_error=Admins%20only');
        exit();
    }
    if (empty($user['is_admin_approved'])) {
        header('Location: login.php?google_error=Admin%20access%20pending%20approval');
        exit();
    }

    $_SESSION['admin_logged_in'] = true;
    $_SESSION['emplID'] = $user['emplID'];
    $_SESSION['admin_name'] = $user['firstName'] . ' ' . $user['lastName'];
    $_SESSION['show_welcome'] = true;

    header('Location: index.php');
    exit();
}

header('Location: login.php?google_error=Email%20not%20registered');
exit();
