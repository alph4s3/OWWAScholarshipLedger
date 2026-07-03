<?php
// =================================================================
// logout.php — Destroy the session and bounce to login.
// =================================================================

if (session_status() === PHP_SESSION_NONE) {
  session_name('OWWA_SID');
  session_start();
}

// Clear all session data
$_SESSION = [];

// Expire the session cookie
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(), '', time() - 42000,
    $params['path'], $params['domain'],
    $params['secure'], $params['httponly']
  );
}

session_destroy();

header('Location: login.php?msg=signed_out');
exit;