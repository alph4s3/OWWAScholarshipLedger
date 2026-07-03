<?php


if (session_status() === PHP_SESSION_NONE) {
  // Harden session cookies
  $cookieParams = [
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
  ];
  session_set_cookie_params($cookieParams);
  session_name('OWWA_SID');
  session_start();
}

// Not logged in? Stash where they wanted to go, then send to login.
if (empty($_SESSION['user_id'])) {
  // Capture the original request so login.php can route them back later if desired
  $_SESSION['intended_url'] = $_SERVER['REQUEST_URI'] ?? '/dashboard.php';
  header('Location: login.php');
  exit;
}

// Convenience globals for the page that included us
$currentUser = [
  'id'           => $_SESSION['user_id'],
  'username'     => $_SESSION['username']     ?? '',
  'display_name' => $_SESSION['display_name'] ?? '',
];