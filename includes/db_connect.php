<?php
function is_private_ip($ip) {
    if (!$ip || $ip === '::1') {
        return true;
    }
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        if (strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0) {
            return true;
        }
        if (strpos($ip, '172.') === 0) {
            $parts = explode('.', $ip);
            $second = isset($parts[1]) ? (int)$parts[1] : 0;
            return $second >= 16 && $second <= 31;
        }
    }
    return false;
}

$server_name = $_SERVER['SERVER_NAME'] ?? '';
$remote_addr = $_SERVER['REMOTE_ADDR'] ?? '';
$server_addr = $_SERVER['SERVER_ADDR'] ?? '';

$is_local = in_array($server_name, ['localhost', '127.0.0.1'], true)
    || in_array($remote_addr, ['127.0.0.1', '::1'], true)
    || is_private_ip($remote_addr)
    || is_private_ip($server_addr);

if ($is_local) {
    $servername = "localhost";
    $username = "root";
    $password = ""; // Default Laragon password is empty
    $dbname = "librarylogs";
} else {
    $servername = "sql210.infinityfree.com";
    $username = "if0_41384394";
    $password = "neulibrary";
    $dbname = "if0_41384394_librarylogs";
}

// Use a consistent app timezone (PH)
date_default_timezone_set('Asia/Manila');

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Align MySQL session time zone with PH time
$conn->query("SET time_zone = '+08:00'");
?>
