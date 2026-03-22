<?php
session_start();
require_once '../includes/google_oauth.php';

$state = bin2hex(random_bytes(16));
$_SESSION['google_oauth_state'] = $state;

header('Location: ' . google_auth_url($state));
exit();
