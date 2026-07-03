<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

function h($value) { return owwa_h($value); }

$pdo = owwa_bootstrap();

// ---------- OPTIONS ----------
$programOptions = ['SUP', 'SESP', 'EDSP', 'ODSP', 'CMWSP', 'ELAP-EA'];
$provinceOptions = [
    'Zamboanga City','Zamboanga Sibugay','Isabela, Basilan',
    'Pagadian','Zamboanga Del Sur','Zamboanga Del Norte','Liloy','Buug'
];
$lifeStageOptions = ['Student', 'Working', 'Graduated', 'Terminated'];
$academicStatusOptions = ['Maintained', 'Probation', 'Terminated'];

$countryCodes = [
  '+63'  => 'Philippines (+63)',
  '+971' => 'UAE (+971)',
  '+1'   => 'United States (+1)',
  '+44'  => 'United Kingdom (+44)',
  '+60'  => 'Malaysia (+60)',
];

// ---------- SCHEMA DETECTION ----------
function column_exists(PDO $pdo, string $table, string $column): bool {
  $stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c"
  );
  $stmt->execute([':t' => $table, ':c' => $column]);
  return (bool) $stmt->fetchColumn();
}

$cols = [
  'year_applied'    => column_exists($pdo, 'scholars_crud', 'year_applied'),
  'birthdate'       => column_exists($pdo, 'scholars_crud', 'birthdate'),
  'scholar_id'      => column_exists($pdo, 'scholars_crud', 'scholar_id'),
  'institution'     => column_exists($pdo, 'scholars_crud', 'institution'),
  'year_level'      => column_exists($pdo, 'scholars_crud', 'year_level'),
  'gwa'             => column_exists($pdo, 'scholars_crud', 'gwa'),
  'phone'           => column_exists($pdo, 'scholars_crud', 'phone'),
  'academic_status' => column_exists($pdo, 'scholars_crud', 'academic_status'),
  'noa_date'        => column_exists($pdo, 'scholars_crud', 'noa_date'),

];

// ---------- PHONE HELPERS ----------
function format_local_number(string $country, string $local): string {
  $digits = preg_replace('/\D/', '', $local);
  if ($country === '+63') {
    if (strlen($digits) === 11 && $digits[0] === '0') $digits = substr($digits, 1);
    if (strlen($digits) === 10) {
      return substr($digits, 0, 3) . ' ' . substr($digits, 3, 3) . ' ' . substr($digits, 6);
    }
  }
  return trim(preg_replace('/(.{3})/', '$1 ', $digits));
}

function build_stored_phone(string $country, string $local): ?string {
  $local = trim($local);
  if ($local === '') return null;
  $cleaned = preg_replace('/^0+/', '', $local);
  $formatted = format_local_number($country, $cleaned);
  if ($country === '') return $formatted;
  $prefix = str_starts_with($country, '+') ? $country : '+' . $country;
  return $prefix . ' ' . $formatted;
}

// ---------- UNIQUE SCHOLAR ID ----------
function generate_scholar_id(PDO $pdo, int $nextId): string {
  if ($nextId > 0) {
    $candidate = 'SCH-IX-' . str_pad((string)$nextId, 5, '0', STR_PAD_LEFT);
    $check = $pdo->prepare("SELECT 1 FROM scholars_crud WHERE scholar_id = :s LIMIT 1");
    $check->execute([':s' => $candidate]);
    if (!$check->fetchColumn()) return $candidate;
  }
  for ($i = 0; $i < 5; $i++) {
    $candidate = 'SCH-IX-' . str_pad((string)random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
    $check = $pdo->prepare("SELECT 1 FROM scholars_crud WHERE scholar_id = :s LIMIT 1");
    $check->execute([':s' => $candidate]);
    if (!$check->fetchColumn()) return $candidate;
  }
  throw new RuntimeException('Could not generate a unique scholar ID.');
}

// ---------- STATE ----------
$flash = '';
$flashIsError = false;
$editRow = null;
$formData = null;

// ---------- DELETE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
  $deleteId = (int) $_POST['delete_id'];
  if ($deleteId > 0) {
    $stmt = $pdo->prepare("DELETE FROM scholars_crud WHERE id = :id");
    $stmt->execute([':id' => $deleteId]);
  }
  header('Location: crud.php?msg=deleted#scholarForm');
  exit;
}

// ---------- SAVE / UPDATE ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id = (int) ($_POST['id'] ?? 0);

  $formData = [
    'last_name'      => trim($_POST['last_name'] ?? ''),
    'first_name'     => trim($_POST['first_name'] ?? ''),
    'middle_initial' => trim($_POST['middle_initial'] ?? ''),
    'birthdate'      => trim($_POST['birthdate'] ?? ''),
    'province'       => trim($_POST['province'] ?? ''),
    'program'        => trim($_POST['program'] ?? ''),
    'year_applied'   => trim($_POST['year_applied'] ?? ''),
    'life_stage'     => trim($_POST['life_stage'] ?? 'Student'),
    'phone'          => trim($_POST['phone'] ?? ''),
    'phone_country'  => trim($_POST['phone_country'] ?? ''),
    'academic_status'=> trim($_POST['academic_status'] ?? 'Maintained'),
    'noa_date'       => trim($_POST['noa_date'] ?? ''),
  ];

  $errors = [];
  if ($formData['last_name'] === '' || $formData['first_name'] === '') {
    $errors[] = 'Last name and first name are required.';
  }
  if (!in_array($formData['program'], $programOptions, true)) {
    $errors[] = 'Select a valid scholarship program.';
  }
  if (!in_array($formData['province'], $provinceOptions, true)) {
    $errors[] = 'Select a valid province.';
  }
  // Only validate life_stage when editing — new records always default to 'Student'
  if ($action === 'update' && !in_array($formData['life_stage'], $lifeStageOptions, true)) {
    $errors[] = 'Select a valid life stage.';
  }
  if (!preg_match('/^\d{4}$/', $formData['year_applied'])) {
    $errors[] = 'Enter a valid 4-digit year.';
  }
  if ($formData['birthdate'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['birthdate'])) {
    $errors[] = 'Enter a valid birthdate.';
  }
  // Only validate academic_status when editing — new records always default to 'Maintained'
  if ($action === 'update' && !in_array($formData['academic_status'], $academicStatusOptions, true)) {
    $errors[] = 'Select a valid academic status.';
  }
  if ($action === 'update' && $formData['noa_date'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $formData['noa_date'])) {
    $errors[] = 'Enter a valid NOA date.';
  }
  if ($formData['phone'] !== '' && $formData['phone_country'] === '') {
    $errors[] = 'Select a country code for the phone number.';
  }

  if ($errors) {
    $flash = implode(' ', $errors);
    $flashIsError = true;
    if ($action === 'update' && $id > 0) {
      $stmt = $pdo->prepare("SELECT * FROM scholars_crud WHERE id = :id");
      $stmt->execute([':id' => $id]);
      $editRow = $stmt->fetch() ?: null;
    }
  } elseif ($action === 'save' || $action === 'update') {
    $data = [
      'last_name'      => $formData['last_name'],
      'first_name'     => $formData['first_name'],
      'middle_initial' => $formData['middle_initial'],
      'province'       => $formData['province'],
      'program'        => $formData['program'],
      'life_stage'     => $formData['life_stage'],
    ];

    if ($cols['year_applied'])    $data['year_applied']    = $formData['year_applied'];
    if ($cols['birthdate'])       $data['birthdate']       = $formData['birthdate'] !== '' ? $formData['birthdate'] : null;
    if ($cols['phone'])           $data['phone']           = build_stored_phone($formData['phone_country'], $formData['phone']);
    if ($cols['academic_status']) $data['academic_status'] = $formData['academic_status'];
    if ($cols['noa_date'])        $data['noa_date']        = $formData['noa_date'] !== '' ? $formData['noa_date'] : null;

    try {
      if ($action === 'save') {
        $nextId = (int) $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM scholars_crud")->fetchColumn();
        if ($nextId < 1) $nextId = 1;

        $data['id'] = $nextId;
        if ($cols['scholar_id'])  $data['scholar_id']  = generate_scholar_id($pdo, $nextId);
        if ($cols['institution']) $data['institution'] = 'Not Specified';
        if ($cols['year_level'])  $data['year_level']  = '1st Year';
        if ($cols['gwa'])         $data['gwa']         = '0.00';

        if (!$cols['year_applied']) {
          $currentYear = date('Y');
          // New record: if same year as today, use today's actual date; otherwise Jan 1 of that year
          $data['created_at'] = ($formData['year_applied'] === $currentYear)
            ? date('Y-m-d')
            : $formData['year_applied'] . '-01-01';
        }

        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ':' . $c, $columns);
        $params = [];
        foreach ($data as $k => $v) $params[':' . $k] = $v;

        $sql = "INSERT INTO scholars_crud (" . implode(', ', $columns) . ") "
             . "VALUES (" . implode(', ', $placeholders) . ")";
        $pdo->prepare($sql)->execute($params);

        header('Location: dashboard.php?msg=recorded_successfully');
        exit;
      }

      if ($id <= 0) {
        $flash = 'Invalid record ID for update.';
        $flashIsError = true;
      } else {
        // For UPDATE: only touch created_at if year_applied is a derived value and year actually changed
        if (!$cols['year_applied']) {
          $existingYear = !empty($editRow['created_at'])
            ? date('Y', strtotime((string) $editRow['created_at']))
            : null;
          if ($existingYear !== $formData['year_applied']) {
            $currentYear = date('Y');
            $data['created_at'] = ($formData['year_applied'] === $currentYear)
              ? date('Y-m-d')
              : $formData['year_applied'] . '-01-01';
          }
        }

        $sets = [];
        $params = [':id' => $id];
        foreach ($data as $k => $v) {
          $sets[] = "$k = :$k";
          $params[':' . $k] = $v;
        }
        $sql = "UPDATE scholars_crud SET " . implode(', ', $sets) . " WHERE id = :id";
        $pdo->prepare($sql)->execute($params);
        header('Location: dashboard.php?msg=recorded_successfully');
        exit;
      }
    } catch (PDOException $e) {
      $flash = 'Database error: ' . $e->getMessage();
      $flashIsError = true;
      if ($action === 'update' && $id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM scholars_crud WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $editRow = $stmt->fetch() ?: null;
      }
    }
  }
}

// ---------- LOAD ROW FOR EDIT ----------
if ($editRow === null && isset($_GET['edit'])) {
  $stmt = $pdo->prepare("SELECT * FROM scholars_crud WHERE id = :id");
  $stmt->execute([':id' => (int) $_GET['edit']]);
  $editRow = $stmt->fetch() ?: null;
}

// ---------- BUILD DISPLAY VALUES ----------
$d = [
  'last_name' => '', 'first_name' => '', 'middle_initial' => '',
  'birthdate' => '', 'province' => '', 'program' => '',
  'year_applied' => date('Y'), 'life_stage' => 'Student',
  'phone' => '', 'phone_country' => '+63',
  'academic_status' => 'Maintained',
  'noa_date' => '',
];

if ($formData !== null) {
  $d = array_merge($d, $formData);
} elseif ($editRow !== null) {
  $d['last_name']       = $editRow['last_name'] ?? '';
  $d['first_name']      = $editRow['first_name'] ?? '';
  $d['middle_initial']  = $editRow['middle_initial'] ?? '';
  $d['province']        = $editRow['province'] ?? '';
  $d['program']         = $editRow['program'] ?? '';
  $d['life_stage']      = $editRow['life_stage'] ?? 'Student';
  $d['academic_status'] = $editRow['academic_status'] ?? 'Maintained';
  if (!empty($editRow['birthdate'])) {
    $ts = strtotime((string) $editRow['birthdate']);
    $d['birthdate'] = $ts ? date('Y-m-d', $ts) : '';
  }
  if (!empty($editRow['noa_date'])) {
    $ts = strtotime((string) $editRow['noa_date']);
    $d['noa_date'] = $ts ? date('Y-m-d', $ts) : '';
  }
  if (!empty($editRow['year_applied'])) {
    $d['year_applied'] = $editRow['year_applied'];
  } elseif (!empty($editRow['created_at'])) {
    $d['year_applied'] = date('Y', strtotime((string) $editRow['created_at']));
  }
  if (!empty($editRow['phone'])) {
    $p = trim((string) $editRow['phone']);
    if (preg_match('/^(\+\d+)\s+(.*)$/', $p, $m)) {
      $d['phone_country'] = $m[1];
      $d['phone'] = $m[2];
    } else {
      $d['phone'] = $p;
    }
  }
}

$messageMap = [
  'created' => 'Record saved successfully.',
  'updated' => 'Record updated successfully.',
  'deleted' => 'Record deleted successfully.',
];
if ($flash === '' && isset($_GET['msg']) && isset($messageMap[$_GET['msg']])) {
  $flash = $messageMap[$_GET['msg']];
  $flashIsError = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OWWA Region IX — Scholar Records</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=JetBrains+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="theme.css">
  <style>
    body.crud-page {
      min-height: 100vh;
      background:
        radial-gradient(circle at top left, rgba(232, 200, 122, 0.18), transparent 28%),
        radial-gradient(circle at bottom right, rgba(10, 37, 64, 0.12), transparent 24%),
        linear-gradient(180deg, #f9f4e8 0%, #f4eddc 100%);
    }
    body.crud-page main {
      max-width: 1400px;
      padding: 28px 32px 48px;
    }
    body.crud-page .page-head { margin-bottom: 20px; padding-bottom: 18px; }
    body.crud-page .notice { margin-bottom: 18px; }

    /* ----- header meta alignment (shared with dashboard / report) ----- */
    .site-header .header-inner { align-items: flex-start; }
    .site-header .meta { line-height: 1.4; }
    .site-header .meta strong { display: inline; margin-right: 6px; }
    .meta-date { display: block; font-size: 14px; margin-top: 6px; color: inherit; }
    .meta-time { display: block; font-size: 13px; color: inherit; margin-top: 2px; }
    .meta-user {
      margin-top: 12px;
      padding-top: 12px;
      border-top: 1px solid rgba(255,255,255,0.12);
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 12px;
    }
    .meta-logout {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.18);
      color: #fff;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      font-weight: 600;
      border-radius: 999px;
      text-decoration: none;
      transition: background .15s, border-color .15s;
    }
    .meta-logout:hover {
      background: rgba(185, 29, 40, 0.25);
      border-color: var(--red);
    }
    .meta-logout svg { opacity: 0.85; }

    /* ----- three-column registration card ----- */
    .registration-card {
      padding: 0;
      overflow: hidden;
      background: rgba(255, 252, 244, 0.92);
      backdrop-filter: blur(16px);
      box-shadow: 0 22px 64px rgba(18, 29, 47, 0.10);
    }
    .reg-cols {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    .reg-col {
      padding: 28px 30px;
      border-right: 1px solid var(--rule-hair);
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .reg-col:last-child { border-right: 0; }

    .section-title {
      display: flex;
      align-items: center;
      gap: 12px;
      font-family: var(--font-body);
      font-size: 12px;
      font-weight: 700;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: var(--ink);
      margin: 0 0 6px 0;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--rule-hair);
    }
    .section-title::before {
      content: "";
      width: 3px;
      height: 18px;
      background: var(--gold);
      border-radius: 2px;
      display: inline-block;
    }

    .reg-col .field { margin: 0; }
    .reg-col .field label {
      font-size: 9.5px;
      letter-spacing: 0.18em;
      margin-bottom: 6px;
    }
    .reg-col .field .req { color: var(--red); margin-left: 2px; }

    .field-pair {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 12px;
    }

    .input-icon { position: relative; display: block; }
    .input-icon > svg {
      position: absolute;
      left: 13px;
      top: 50%;
      transform: translateY(-50%);
      width: 14px;
      height: 14px;
      color: var(--muted);
      pointer-events: none;
      z-index: 1;
    }
    .input-icon > input,
    .input-icon > select {
      padding-left: 38px;
    }
    .input-icon > select {
      background-position: calc(100% - 16px) 50%, calc(100% - 11px) 50%;
    }

    .reg-actions {
      margin-top: auto;
      padding-top: 18px;
      border-top: 1px dashed var(--rule-hair);
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .reg-actions .btn { width: 100%; }
    .reg-actions .btn-row { display: flex; gap: 8px; }
    .reg-actions .btn-row .btn { flex: 1; }

    .privacy-note {
      font-family: var(--font-mono);
      font-size: 10px;
      line-height: 1.6;
      letter-spacing: 0.06em;
      color: var(--muted);
      text-transform: none;
      padding: 12px 14px;
      background: var(--paper-2);
      border: 1px solid var(--rule-hair);
      border-left: 2px solid var(--gold);
      border-radius: 3px;
    }
    .privacy-note strong {
      color: var(--ink);
      font-family: var(--font-display);
      font-style: italic;
      font-size: 12px;
      letter-spacing: 0;
    }

    .academic-note {
      padding: 10px 14px;
      background: var(--paper-2);
      border: 1px solid var(--rule-hair);
      border-radius: 3px;
      font-size: 13px;
      color: var(--muted);
      font-family: var(--font-mono);
    }
    .academic-note strong {
      color: var(--ink);
      font-family: var(--font-display);
      font-style: italic;
      font-weight: 500;
    }

    @media (max-width: 1100px) {
      .reg-cols { grid-template-columns: 1fr; }
      .reg-col { border-right: 0; border-bottom: 1px solid var(--rule-hair); }
      .reg-col:last-child { border-bottom: 0; }
    }
    @media (max-width: 720px) {
      .reg-col { padding: 22px; }
      .field-pair { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body class="show-backend-navs crud-page">
<header class="site-header">
  <div class="header-inner">
    <div class="brand">
      <img src="logo.png" alt="OWWA Region IX seal">
      <div>
        <div class="eyebrow">Republic of the Philippines · Region IX</div>
        <h1><?php echo $editRow ? 'Update Record' : 'Registration Form'; ?></h1>
      </div>
    </div>
    <div class="meta">
      <strong>OWWA IX</strong>
      Scholar Services
      <div class="meta-date" id="metaDate"></div>
      <div class="meta-time" id="metaTime"></div>
      <div class="meta-user">
        <a href="logout.php" class="meta-logout">
          <svg width="11" height="11" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
            <path d="M10 3H4v10h6M14 8H7M11 5l3 3-3 3"/>
          </svg>
          Sign Out
        </a>
      </div>
    </div>
  </div>
</header>

<nav class="site-nav">
  <div class="nav-inner">
    <a class="nav-btn" data-static="true" href="main.html#page-student">
      <svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 2L1 6l7 4 7-4-7-4z"/><path d="M3 8v3c0 1.5 2.5 3 5 3s5-1.5 5-3V8"/></svg>
      Scholar Dashboard
    </a>
    <a class="nav-btn" data-static="true" href="main.html#page-report">
      <svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 2h7l3 3v9H3z"/><path d="M10 2v3h3M5 8h6M5 11h6"/></svg>
      Report
    </a>
    <a class="nav-btn" data-backend="true" href="dashboard.php">
      <svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 3h12v10H2z"/><path d="M2 6h12M6 3v10"/></svg>
      Scholar Dashboard
    </a>
    <a class="nav-btn" data-backend="true" href="report.php">
      <svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 2h7l3 3v9H3z"/><path d="M10 2v3h3M5 8h6M5 11h6"/></svg>
      Report
    </a>
    <a class="nav-btn active" data-backend="true" href="crud.php">
      <svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3.5h10v9H3z"/><path d="M6 3.5v9M3 7h10"/><path d="M7.5 3.5v9"/></svg>
      Add Records
    </a>
  </div>
</nav>

<main>
  <section class="page-head">
    <div>
      <h2 class="serif"><em><?php echo $editRow ? 'Edit scholar record' : 'New scholar'; ?></em></h2>
    </div>
  </section>

  <?php if ($flash !== ''): ?>
    <div class="notice <?php echo $flashIsError ? 'error' : ''; ?>"><?php echo h($flash); ?></div>
  <?php endif; ?>

  <div class="panel registration-card">
    <form method="post" id="scholarForm">
      <input type="hidden" name="id" value="<?php echo h($editRow['id'] ?? ''); ?>">

      <div class="reg-cols">

        <!-- COLUMN 1 · IDENTITY -->
        <section class="reg-col">
          <h3 class="section-title">Scholar</h3>

          <div class="field">
            <label>Year Applied <span class="req">*</span></label>
            <span class="input-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M2 6h12M5.5 1.5v3M10.5 1.5v3"/></svg>
              <input type="number" name="year_applied" min="2000" max="2100" value="<?php echo h($d['year_applied']); ?>" placeholder="2025" required>
            </span>
          </div>

          <div class="field">
            <label>Last Name <span class="req">*</span></label>
            <span class="input-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="5.5" r="2.8"/><path d="M2.5 14c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/></svg>
              <input type="text" name="last_name" value="<?php echo h($d['last_name']); ?>" placeholder="Dela Cruz" required>
            </span>
          </div>

          <div class="field">
            <label>First Name <span class="req">*</span></label>
            <span class="input-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="5.5" r="2.8"/><path d="M2.5 14c0-3 2.5-5 5.5-5s5.5 2 5.5 5"/></svg>
              <input type="text" name="first_name" value="<?php echo h($d['first_name']); ?>" placeholder="Maria" required>
            </span>
          </div>

          <div class="field-pair">
            <div class="field">
              <label>Middle Name</label>
              <span class="input-icon">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 13V3l5 7 5-7v10"/></svg>
                <input type="text" name="middle_initial" maxlength="60" value="<?php echo h($d['middle_initial']); ?>">
              </span>
            </div>
            <div class="field">
              <label>Birthdate</label>
              <span class="input-icon">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 2c-1.5 2-3 3.5-3 5.5a3 3 0 006 0C11 5.5 9.5 4 8 2z"/><path d="M3 13.5h10"/></svg>
                <input type="date" name="birthdate" value="<?php echo h($d['birthdate']); ?>">
              </span>
            </div>
          </div>
        </section>

        <!-- COLUMN 2 · PROGRAM & LOCATION -->
        <section class="reg-col">
          <h3 class="section-title">Program &amp; Location</h3>

          <div class="field">
            <label>Province <span class="req">*</span></label>
            <span class="input-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 1.5c-2.8 0-5 2.1-5 4.8 0 3.4 5 7.7 5 7.7s5-4.3 5-7.7c0-2.7-2.2-4.8-5-4.8z"/><circle cx="8" cy="6.3" r="1.7"/></svg>
              <select name="province" required>
                <option value="">Select Province</option>
                <?php foreach ($provinceOptions as $province): ?>
                  <option value="<?php echo h($province); ?>" <?php echo $d['province'] === $province ? 'selected' : ''; ?>><?php echo h($province); ?></option>
                <?php endforeach; ?>
              </select>
            </span>
          </div>

          <div class="field">
            <label>Scholarship Program <span class="req">*</span></label>
            <span class="input-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 3l6-1.5L14 3v3c0 4-3 6.5-6 7.5C5 12.5 2 10 2 6V3z"/></svg>
              <select name="program" required>
                <option value="">Select Program</option>
                <?php foreach ($programOptions as $program): ?>
                  <option value="<?php echo h($program); ?>" <?php echo $d['program'] === $program ? 'selected' : ''; ?>><?php echo h($program); ?></option>
                <?php endforeach; ?>
              </select>
            </span>
          </div>
          
          <?php if ($editRow): ?>
            <div class="field">
              <label>Life Stage</label>
              <span class="input-icon">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="5" width="12" height="8" rx="1"/><path d="M6 5V3.5h4V5"/></svg>
                <select name="life_stage">
                  <option value="Student"    <?php echo $d['life_stage'] === 'Student'    ? 'selected' : ''; ?>>Student</option>
                  <option value="Graduated"  <?php echo $d['life_stage'] === 'Graduated'  ? 'selected' : ''; ?>>Graduated</option>
                  <option value="Working"    <?php echo $d['life_stage'] === 'Working'    ? 'selected' : ''; ?>>Working</option>
                  <option value="Terminated" <?php echo $d['life_stage'] === 'Terminated' ? 'selected' : ''; ?>>Terminated</option>
                </select>
              </span>
            </div>
          <?php endif; ?>

          <?php if ($editRow): ?>
            <div class="field">
              <label>Academic Status <span class="req">*</span></label>
              <span class="input-icon">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
                  <path d="M8 1l2 4 4 .6-3 3 .8 4.4L8 11l-3.8 2 .8-4.4-3-3 4-.6L8 1z"/>
                </svg>
                <select name="academic_status" required>
                  <option value="Maintained"  <?php echo $d['academic_status'] === 'Maintained'  ? 'selected' : ''; ?>>Maintained</option>
                  <option value="Probation"   <?php echo $d['academic_status'] === 'Probation'   ? 'selected' : ''; ?>>Probation</option>
                  <option value="Terminated"  <?php echo $d['academic_status'] === 'Terminated'  ? 'selected' : ''; ?>>Terminated</option>
                </select>
              </span>
            </div>
          <?php endif; ?>

          <?php if ($editRow): ?>
            <div class="field">
              <label>NOA Date</label>
              <span class="input-icon">
                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="12" height="11" rx="1.5"/><path d="M2 6h12M5.5 1.5v3M10.5 1.5v3"/></svg>
                <input type="date" name="noa_date" value="<?php echo h($d['noa_date']); ?>">
              </span>
            </div>
          <?php endif; ?>
            

        </section>

        <!-- COLUMN 3 · CONTACT -->
        <section class="reg-col">
          <h3 class="section-title">Contact</h3>

          <div class="field">
            <label>Country Code</label>
            <span class="input-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="8" cy="8" r="6"/><path d="M2 8h12M8 2c2 2 3 4 3 6s-1 4-3 6c-2-2-3-4-3-6s1-4 3-6z"/></svg>
              <select name="phone_country">
                <option value="">Country code</option>
                <?php foreach ($countryCodes as $code => $label): ?>
                  <option value="<?php echo h($code); ?>" <?php echo $d['phone_country'] === $code ? 'selected' : ''; ?>><?php echo h($label); ?></option>
                <?php endforeach; ?>
              </select>
            </span>
          </div>

          <div class="field">
            <label>Mobile Number</label>
            <span class="input-icon">
              <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 2.5h3l1.5 3.5L6 7c.8 1.7 2.3 3.2 4 4l1-1.5L14.5 11v3c0 .8-.7 1.5-1.5 1.5C6.5 15.5 1 10 1 4c0-.8.7-1.5 1.5-1.5z"/></svg>
              <input type="tel" name="phone" value="<?php echo h($d['phone']); ?>" placeholder="9XX XXX XXXX">
            </span>
          </div>

          <div class="privacy-note">
            <strong>Data Privacy · </strong><br>
            By submitting this form, you authorize <strong>OWWA Regional Welfare Office IX</strong> to collect and process the scholar's information for official scholarship monitoring in accordance with the <em>Data Privacy Act of 2012</em>.
          </div>

          <div class="reg-actions">
            <button class="btn primary" type="submit" name="action" value="<?php echo $editRow ? 'update' : 'save'; ?>">
              <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 1H3a1 1 0 00-1 1v12a1 1 0 001 1h10a1 1 0 001-1V4l-3-3z"/><path d="M5 1v4h6V1M5 11h6"/></svg>
              <?php echo $editRow ? 'Update Record' : 'Submit'; ?>
            </button>
            <div class="btn-row">
              <?php if ($editRow): ?>
                <a class="btn secondary" href="crud.php">Cancel Edit</a>
              <?php endif; ?>
            </div>
          </div>
        </section>

      </div>
    </form>
  </div>
</main>

<script>
(function() {
  const form = document.getElementById('scholarForm');
  if (!form) return;
  const phoneInput   = form.elements['phone'];
  const countryInput = form.elements['phone_country'];

  function formatLocalPhone(country, val) {
    const digits = (val || '').replace(/\D/g, '');
    if (country === '+63') {
      if (digits.length === 11 && digits.startsWith('0')) {
        const d = digits.slice(1);
        return d.slice(0,3) + ' ' + d.slice(3,6) + ' ' + d.slice(6);
      }
      if (digits.length === 10) {
        return digits.slice(0,3) + ' ' + digits.slice(3,6) + ' ' + digits.slice(6);
      }
    }
    return digits.replace(/(.{3})/g, '$1 ').trim();
  }

  form.addEventListener('submit', function(e) {
    const phone = phoneInput ? (phoneInput.value || '').trim() : '';
    const country = countryInput ? (countryInput.value || '').trim() : '';
    if (phone !== '' && country === '') {
      e.preventDefault();
      alert('Please select a country code when providing a phone number.');
      countryInput && countryInput.focus();
      return false;
    }
    if (phone !== '') {
      const digits = phone.replace(/\D/g, '');
      if (digits.length < 6 || digits.length > 15) {
        e.preventDefault();
        alert('Enter a valid phone number (6–15 digits).');
        phoneInput && phoneInput.focus();
        return false;
      }
    }
  });

  if (phoneInput && countryInput) {
    const reformat = () => {
      let val = (phoneInput.value || '').trim().replace(/^0+/, '');
      const country = (countryInput.value || '').trim();
      phoneInput.value = country ? formatLocalPhone(country, val) : val;
    };
    phoneInput.addEventListener('blur', reformat);
    countryInput.addEventListener('change', reformat);
  }
})();

// Header date/time
(function() {
  function updateDateTime() {
    try {
      const now = new Date();
      const locale = (navigator.languages && navigator.languages.length) ? navigator.languages[0] : (navigator.language || 'en-US');
      const dateOpts = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
      const timeOpts = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
      const dateStr = new Intl.DateTimeFormat(locale, dateOpts).format(now);
      const timeStr = new Intl.DateTimeFormat(locale, timeOpts).format(now);
      const dateEl = document.getElementById('metaDate');
      const timeEl = document.getElementById('metaTime');
      if (dateEl) dateEl.textContent = dateStr.replace(',', ' ·');
      if (timeEl) timeEl.textContent = timeStr;
    } catch (e) {}
  }
  document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
  });
})();
</script>

<footer class="site-footer">
  <div class="seal">OWWA Region IX · Scholar Monitoring v1.1</div>
  <div>Module 04 — Data Maintenance</div>
</footer>
</body>
</html>