<?php
// Google OAuth config for Admin login.
// NOTE: Keep these values private. Do not commit real secrets to public repos.
const GOOGLE_CLIENT_ID = '467208706110-5cob2l0rbe2esb2jeor37qqn164vqpm0.apps.googleusercontent.com';
const GOOGLE_CLIENT_SECRET = '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';

function google_base_url(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $path = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
    return $scheme . '://' . $host . $path;
}

function google_redirect_uri(): string {
    return google_base_url() . '/google_callback.php';
}

function google_auth_url(string $state): string {
    $params = [
        'client_id' => GOOGLE_CLIENT_ID,
        'redirect_uri' => google_redirect_uri(),
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'prompt' => 'select_account',
        // Hint for institutional domain (not enforcement)
        'hd' => 'neu.edu.ph',
    ];
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

function google_exchange_code(string $code): array {
    $postFields = http_build_query([
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => google_redirect_uri(),
        'grant_type' => 'authorization_code',
    ]);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    $caPath = __DIR__ . '/cacert.pem';
    if (file_exists($caPath)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caPath);
    }
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'ok' => false,
            'error' => 'curl_error',
            'error_description' => $curlError ?: 'Unknown cURL error',
            'http_code' => $httpCode,
        ];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400 || !is_array($data)) {
        $error = is_array($data) && isset($data['error']) ? $data['error'] : 'token_exchange_failed';
        $desc = is_array($data) && isset($data['error_description']) ? $data['error_description'] : 'Token exchange failed';
        return [
            'ok' => false,
            'error' => $error,
            'error_description' => $desc,
            'http_code' => $httpCode,
        ];
    }

    return ['ok' => true, 'data' => $data];
}

function google_fetch_userinfo(string $accessToken): array {
    $ch = curl_init('https://oauth2.googleapis.com/userinfo');
    $caPath = __DIR__ . '/cacert.pem';
    if (file_exists($caPath)) {
        curl_setopt($ch, CURLOPT_CAINFO, $caPath);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $accessToken]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return [
            'ok' => false,
            'error' => 'curl_error',
            'error_description' => $curlError ?: 'Unknown cURL error',
            'http_code' => $httpCode,
        ];
    }

    $data = json_decode($response, true);
    if ($httpCode >= 400 || !is_array($data)) {
        $error = is_array($data) && isset($data['error']) ? $data['error'] : 'userinfo_failed';
        $desc = is_array($data) && isset($data['error_description']) ? $data['error_description'] : 'Unable to fetch profile';
        return [
            'ok' => false,
            'error' => $error,
            'error_description' => $desc,
            'http_code' => $httpCode,
        ];
    }

    return ['ok' => true, 'data' => $data];
}
