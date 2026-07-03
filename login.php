<?php
// =================================================================
// login.php — Authenticates OWWA staff and starts a session.
// =================================================================
require __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
  session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => !empty($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
  session_name('OWWA_SID');
  session_start();
}

// Already signed in? Skip the form.
if (!empty($_SESSION['user_id'])) {
  header('Location: dashboard.php');
  exit;
}

$pdo = owwa_bootstrap();

$error = '';
$notice = '';
$prefillUsername = '';

// Friendly messages from query string (e.g. ?msg=signed_out after logout)
if (isset($_GET['msg'])) {
  $msgMap = [
    'signed_out' => 'You have been signed out.',
    'expired'    => 'Your session expired. Please sign in again.',
  ];
  $notice = $msgMap[$_GET['msg']] ?? '';
}

// ---------- Handle POST ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $username = trim($_POST['username'] ?? '');
  $password = (string) ($_POST['password'] ?? '');
  $prefillUsername = $username;

  if ($username === '' || $password === '') {
    $error = 'Please enter both your username and password.';
  } else {
    // Constant-ish lookup — same code path whether the user exists or not,
    // to avoid leaking which usernames are valid.
    $stmt = $pdo->prepare("SELECT id, username, display_name, password_hash, is_active FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();

    $valid = false;
    if ($row && (int) $row['is_active'] === 1 && password_verify($password, $row['password_hash'])) {
      $valid = true;
    }

    if (!$valid) {
      // Small sleep softens timing-based username enumeration
      usleep(300000); // 0.3s
      $error = 'Invalid username or password.';
    } else {
      // Rotate the session ID to prevent fixation
      session_regenerate_id(true);

      $_SESSION['user_id']      = (int) $row['id'];
      $_SESSION['username']     = $row['username'];
      $_SESSION['display_name'] = $row['display_name'];

      // Update last login (best-effort, ignore failure)
      try {
        $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id")
            ->execute([':id' => (int) $row['id']]);
      } catch (Throwable $e) { /* ignore */ }

      // If the auth guard stashed an intended URL, honor it — otherwise dashboard.
      $target = $_SESSION['intended_url'] ?? 'dashboard.php';
      unset($_SESSION['intended_url']);

      // Only allow same-origin relative paths
      if (!preg_match('#^/?[a-zA-Z0-9_\-./?=&%#]+$#', $target) || str_contains($target, '://')) {
        $target = 'dashboard.php';
      }

      header('Location: ' . $target);
      exit;
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OWWA Region IX — Sign In</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=JetBrains+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="theme.css">
  <style>
    /* ===== Login page layout — full-bleed two-column ===== */
    html, body { height: 100%; }
    body.login-page {
      margin: 0;
      min-height: 100vh;
      display: grid;
      grid-template-columns: 1.1fr 1fr;
      background: var(--paper);
      overflow: hidden;
    }

    /* ----- Left: branded panel ----- */
    .login-brand {
      position: relative;
      min-height: 100vh;
      padding: 64px;
      color: #fff;
      background:
        linear-gradient(180deg, rgba(255,255,255,0.03), rgba(255,255,255,0)),
        linear-gradient(135deg, var(--ink) 0%, var(--ink-2) 60%, #17365d 100%);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      justify-content: center;
      box-sizing: border-box;
    }
    .login-brand::before {
      content: "";
      position: absolute; inset: 0;
      background-image:
        radial-gradient(circle at 85% 15%, rgba(212,167,44,0.2), transparent 40%),
        radial-gradient(circle at 15% 85%, rgba(212,167,44,0.1), transparent 40%);
      pointer-events: none;
    }
    .login-brand::after {
      content: "";
      position: absolute;
      left: 0; right: 0; bottom: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--gold-2) 0%, var(--gold) 40%, #8a5b00 100%);
    }

    /* Centered content stack */
    .login-brand-content {
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 32px;
      max-width: 520px;
      z-index: 1;
    }

    /* Logo + heading row */
    .login-brand-top {
      position: relative;
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 24px;
      align-items: center;
    }
    .login-brand-top img {
      width: 90px; height: 90px;
      object-fit: contain;
      background: rgba(255, 255, 255, 0.08);
      backdrop-filter: blur(8px);
      border-radius: 50%;
      padding: 10px;
      box-shadow: 0 12px 32px rgba(0,0,0,0.3), inset 0 0 0 1px rgba(255,255,255,0.15);
    }
    .login-brand-text {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .login-brand .eyebrow {
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.2em;
      text-transform: uppercase;
      color: var(--gold-2);
      opacity: 0.9;
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .login-brand .eyebrow::before {
      content: "";
      width: 16px; height: 1px;
      background: var(--gold-2);
    }
    .login-brand h1 {
      color: #fff;
      font-family: var(--font-display);
      font-style: italic;
      font-variation-settings: "opsz" 144, "SOFT" 30, "wght" 400;
      font-size: clamp(32px, 3.8vw, 46px);
      line-height: 1.1;
      letter-spacing: -0.01em;
      margin: 0;
    }
    .login-brand p.lede {
      position: relative;
      margin: 0;
      opacity: 0.8;
      font-size: 15.5px;
      line-height: 1.65;
      border-left: 2px solid rgba(212, 167, 44, 0.3);
      padding-left: 20px;
    }

    /* Footnote anchored to bottom-left corner */
    .login-brand .footnote {
      position: absolute;
      bottom: 32px;
      left: 64px;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      opacity: 0.5;
      display: flex;
      gap: 10px;
      align-items: center;
      z-index: 1;
    }
    .login-brand .footnote::before {
      content: "";
      width: 22px; height: 1px;
      background: currentColor;
    }

    /* ----- Right: sign-in card ----- */
    .login-form-side {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 48px;
      background:
        radial-gradient(800px 400px at 100% 0%, rgba(212,167,44,0.12), transparent 60%),
        var(--paper);
    }
    .login-card {
      width: 100%;
      max-width: 420px;
      background: var(--paper-3);
      border: 1px solid var(--rule-hair);
      border-radius: 6px;
      box-shadow: 0 22px 64px rgba(18, 29, 47, 0.10);
      padding: 36px 38px;
      position: relative;
    }
    .login-card::before {
      content: "";
      position: absolute;
      top: -1px; left: 36px; right: 36px;
      height: 3px;
      background: var(--red);
      border-radius: 0 0 2px 2px;
    }
    .login-card .crumb {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 10px;
      display: flex; align-items: center; gap: 10px;
    }
    .login-card .crumb::before {
      content: "";
      width: 14px; height: 1px;
      background: var(--gold);
    }
    .login-card h2 {
      font-family: var(--font-display);
      font-style: italic;
      font-weight: 500;
      font-size: 30px;
      color: var(--ink);
      margin: 0 0 6px 0;
      letter-spacing: -0.015em;
      line-height: 1.1;
    }
    .login-card .sub {
      color: var(--muted);
      font-size: 14px;
      margin-bottom: 26px;
    }

    .login-card .field { margin-bottom: 16px; }
    .login-card label {
      display: block;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--muted);
      margin-bottom: 6px;
      font-weight: 600;
    }
    .input-icon {
      position: relative;
      display: block;
    }
    .input-icon > svg {
      position: absolute;
      left: 13px; top: 50%;
      transform: translateY(-50%);
      width: 14px; height: 14px;
      color: var(--muted);
      pointer-events: none;
    }
    .input-icon > input { padding-left: 38px; }

    .login-card .btn.primary {
      width: 100%;
      margin-top: 6px;
    }

    .login-card .notice {
      margin-bottom: 18px;
      font-size: 13px;
    }

    .login-card .helper {
      margin-top: 22px;
      padding-top: 18px;
      border-top: 1px dashed var(--rule-hair);
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.08em;
      color: var(--muted);
      line-height: 1.7;
    }
    .login-card .helper strong {
      color: var(--ink);
      font-family: var(--font-display);
      font-style: italic;
      font-size: 12px;
      letter-spacing: 0;
    }

    /* Show/hide password toggle */
    .pw-toggle {
      position: absolute;
      right: 10px; top: 50%;
      transform: translateY(-50%);
      background: none; border: 0; cursor: pointer;
      color: var(--muted);
      font-family: var(--font-mono);
      font-size: 9.5px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      padding: 6px 8px;
      border-radius: 3px;
    }
    .pw-toggle:hover { color: var(--ink); background: var(--paper-2); }

    /* ----- Responsive: stack on small screens ----- */
    @media (max-width: 960px) {
      body.login-page {
        grid-template-columns: 1fr;
        overflow: auto;
      }
      .login-brand {
        padding: 48px 32px 72px;
        min-height: auto;
      }
      .login-brand-content {
        gap: 24px;
      }
      .login-brand-top {
        grid-template-columns: auto 1fr;
        gap: 20px;
      }
      .login-brand-top img {
        width: 72px; height: 72px;
      }
      .login-brand h1 { font-size: 32px; }
      .login-brand p.lede {
        display: block;
        font-size: 14.5px;
      }
      .login-brand .footnote {
        bottom: 24px;
        left: 32px;
      }
      .login-form-side { padding: 40px 20px 64px; }
    }
    @media (max-width: 480px) {
      .login-card { padding: 28px 24px; }
      .login-brand-top {
        grid-template-columns: 1fr;
        gap: 16px;
      }
      .login-brand-top img {
        width: 64px; height: 64px;
      }
    }
  </style>
</head>
<body class="login-page">

  <section class="login-brand">
    <div class="login-brand-content">
      <div class="login-brand-top">
        <img src="logo.png" alt="OWWA Region IX seal">
        <div class="login-brand-text">
          <div class="eyebrow">Republic of the Philippines · Region IX</div>
          <h1>Scholar<br>Monitoring System</h1>
        </div>
      </div>
      <p class="lede">
        Internal console for the Overseas Workers Welfare Administration —
        Zamboanga Peninsula. Authorized personnel only.
      </p>
    </div>
    <div class="footnote">OWWA IX · Scholar Services</div>
  </section>

  <section class="login-form-side">
    <div class="login-card">
      <div class="crumb">Authentication</div>
      <h2><em>Sign in</em></h2>
      <div class="sub">Use your assigned OWWA staff credentials.</div>

      <?php if ($notice !== ''): ?>
        <div class="notice"><?php echo owwa_h($notice); ?></div>
      <?php endif; ?>
      <?php if ($error !== ''): ?>
        <div class="notice error"><?php echo owwa_h($error); ?></div>
      <?php endif; ?>

      <form method="post" autocomplete="off" id="loginForm">
        <div class="field">
          <label for="username">Username</label>
          <span class="input-icon">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
              <circle cx="8" cy="5.5" r="2.8"/>
              <path d="M2.5 14c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/>
            </svg>
            <input
              type="text"
              id="username"
              name="username"
              value="<?php echo owwa_h($prefillUsername); ?>"
              placeholder="e.g. j.dela.cruz"
              required
              autofocus>
          </span>
        </div>

        <div class="field">
          <label for="password">Password</label>
          <span class="input-icon">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
              <rect x="3" y="7" width="10" height="7" rx="1"/>
              <path d="M5 7V5a3 3 0 116 0v2"/>
            </svg>
            <input
              type="password"
              id="password"
              name="password"
              placeholder="••••••••"
              required>
            <button type="button" class="pw-toggle" id="pwToggle" aria-label="Show password">Show</button>
          </span>
        </div>

        <button class="btn primary" type="submit">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M2 8h10M9 5l3 3-3 3"/>
            <path d="M12 2h2v12h-2"/>
          </svg>
          Sign In
        </button>
      </form>

      <div class="helper">
        <strong>Forgot your password?</strong><br>
        Contact your Scholar Services administrator to have it reset.
      </div>
    </div>
  </section>

<script>
(function() {
  var btn = document.getElementById('pwToggle');
  var pw  = document.getElementById('password');
  if (!btn || !pw) return;
  btn.addEventListener('click', function() {
    var isPw = pw.type === 'password';
    pw.type = isPw ? 'text' : 'password';
    btn.textContent = isPw ? 'Hide' : 'Show';
    btn.setAttribute('aria-label', isPw ? 'Hide password' : 'Show password');
  });
})();
</script>

</body>
</html>