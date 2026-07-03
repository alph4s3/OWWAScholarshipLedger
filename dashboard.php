<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

$pdo = owwa_bootstrap();

$programOptions = ['SUP', 'SESP', 'EDSP', 'ODSP', 'CMWSP', 'ELAP-EA'];
$provinceOptions = [
  'Zamboanga City','Zamboanga Sibugay','Isabela, Basilan',
  'Pagadian','Zamboanga Del Sur','Zamboanga Del Norte','Liloy','Buug'
];
$lifeStageOptions = ['Student', 'Working', 'Graduated', 'Terminated'];
$academicStatusOptions = ['Maintained', 'Probation', 'Terminated'];

$program        = trim($_GET['program'] ?? '');
$province       = trim($_GET['province'] ?? '');
$last_name      = trim($_GET['last_name'] ?? '');
$first_name     = trim($_GET['first_name'] ?? '');
$middle_initial = trim($_GET['middle_initial'] ?? '');
$lifeStage      = trim($_GET['life_stage'] ?? '');

$notice = '';
if (($_GET['msg'] ?? '') === 'recorded_successfully') {
  $notice = 'Recorded successfully.';
}

$sql = "SELECT id, last_name, first_name, middle_initial, birthdate, province, program, phone, YEAR(created_at) AS year_applied, life_stage, academic_status, created_at FROM scholars_crud WHERE 1=1";
$params = [];
if (in_array($program, $programOptions, true)) {
  $sql .= " AND program = :program";
  $params[':program'] = $program;
}
if (in_array($province, $provinceOptions, true)) {
  $sql .= " AND province = :province";
  $params[':province'] = $province;
}
if ($last_name !== '') {
  $sql .= " AND last_name LIKE :last_name";
  $params[':last_name'] = '%' . $last_name . '%';
}
if ($first_name !== '') {
  $sql .= " AND first_name LIKE :first_name";
  $params[':first_name'] = '%' . $first_name . '%';
}
if ($middle_initial !== '') {
  $sql .= " AND middle_initial LIKE :middle_initial";
  $params[':middle_initial'] = '%' . $middle_initial . '%';
}
if (in_array($lifeStage, $lifeStageOptions, true)) {
  $sql .= " AND life_stage = :life_stage";
  $params[':life_stage'] = $lifeStage;
}
$sql .= " ORDER BY created_at DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$filtered = count($rows);

$total = (int)$pdo->query("SELECT COUNT(*) FROM scholars_crud")->fetchColumn();

$activeFilters = array_filter([
  'Province'        => $province !== '' ? $province : null,
  'Program'         => $program !== '' ? $program : null,
  'Last Name'       => $last_name !== '' ? $last_name : null,
  'First Name'      => $first_name !== '' ? $first_name : null,
  'Middle Initial'  => $middle_initial !== '' ? $middle_initial : null,
  'Life Stage'      => $lifeStage !== '' ? $lifeStage : null,
]);

// ----- MODE: search (initial) vs results (after applying any filter) -----
// ----- MODE: search (initial) vs results (after applying) -----
// Enter results mode when the user clicks Search/Apply, even if no filters are set.
$submitted = isset($_GET['submitted']);
$hasFilters = !empty($activeFilters);
$hasResults = $submitted || $hasFilters;
$bodyMode = $hasResults ? 'results-mode' : 'search-mode';

function dashboard_fmt_birthdate(?string $value): string {
  if (!$value) return 'Not on file';
  $time = strtotime($value);
  return $time ? date('F j, Y', $time) : owwa_h($value);
}
function dashboard_middle_initial(?string $value): string {
  $value = trim((string)$value);
  if ($value === '') return '';
  // First character + period; if it already looks like a single letter, leave as-is
  $first = mb_substr($value, 0, 1);
  return strtoupper($first) . '.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OWWA Region IX — Scholar Dashboard</title>
  <script>
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual';
    }
    (function() {
      try {
        var loadingKey = 'owwa-loading-' + location.pathname;
        if (sessionStorage.getItem(loadingKey) === '1') {
          sessionStorage.removeItem(loadingKey);
          document.documentElement.dataset.loading = '1';
        }
      } catch (e) { /* ignore */ }
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght,SOFT@0,9..144,300..700,0..100;1,9..144,300..700,0..100&family=JetBrains+Mono:wght@400;500;600&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="theme.css">
  <style>
    /* ========================================================
       Header / nav meta (shared with other pages)
       ======================================================== */
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

    /* ========================================================
       Search hero (centered filter in initial state)
       ======================================================== */
    .search-hero {
      display: flex;
      flex-direction: column;
      align-items: center;
    }
    .search-hero-intro {
      text-align: center;
      max-width: 540px;
      margin: 32px auto 28px;
    }
    .search-hero-intro h2 {
      font-family: var(--font-display);
      font-style: italic;
      font-weight: 500;
      font-size: clamp(28px, 3.4vw, 40px);
      color: var(--ink);
      margin: 0 0 10px;
      letter-spacing: -0.015em;
      line-height: 1.1;
    }
    .search-hero-intro p {
      margin: 0;
      color: var(--muted);
      font-size: 15px;
      line-height: 1.6;
    }
    .search-hero-intro .eyebrow {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: var(--gold);
      margin-bottom: 12px;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }
    .search-hero-intro .eyebrow::before,
    .search-hero-intro .eyebrow::after {
      content: "";
      width: 24px; height: 1px;
      background: currentColor;
      opacity: 0.5;
    }

    /* ========================================================
       Filter panel — same markup, different layout per mode
       ======================================================== */
    .filter-panel {
      transition: max-width .35s ease, padding .35s ease;
      width: 100%;
    }
    .filters {
      gap: 12px;
      align-items: end;
    }
    .filters .field input,
    .filters .field select {
      width: 100%;
      box-sizing: border-box;
    }
    .chip-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
    .chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      background: var(--gold-soft);
      color: #5a4310;
      border-radius: 999px;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: .12em;
      text-transform: uppercase;
    }

    /* ===== SEARCH MODE (no filters yet) ===== */
    body.search-mode .filter-panel {
      max-width: 820px;
      margin: 0 auto;
      padding: 36px 40px;
      box-shadow: 0 22px 64px rgba(18, 29, 47, 0.08);
      border-color: var(--rule-soft);
    }
    body.search-mode .filter-panel > h3 { display: none; }
    body.search-mode .filters {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    body.search-mode .filters .field {
      min-width: 0;
    }
    body.search-mode .filters .actions {
      grid-column: 1 / -1;
      margin-top: 8px;
      padding-top: 18px;
      display: flex;
      justify-content: center;
      border-top: 1px solid var(--rule-hair);
    }
    body.search-mode .filters .actions .btn {
      width: 100%;
      max-width: 280px;
      min-height: 50px;
      font-size: 14px;
    }
    body.search-mode .results-container { display: none; }
    body.search-mode .stat-row { margin-bottom: 36px; }

    /* Search-mode entrance animations */
    body.search-mode .search-hero-intro {
      animation: heroIntroIn .55s ease both;
    }
    body.search-mode .filter-panel {
      animation: heroPanelIn .65s cubic-bezier(.2,.9,.2,1) both .1s;
    }
    @keyframes heroIntroIn {
      from { opacity: 0; transform: translateY(-12px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes heroPanelIn {
      from { opacity: 0; transform: translateY(16px) scale(.985); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* ===== RESULTS MODE (after applying filters) ===== */
    body.results-mode .search-hero-intro { display: none; }
    body.results-mode .filter-panel {
      max-width: 100%;
      padding: 22px 24px;
    }
    body.results-mode .filters {
      display: grid;
      /* Last | First | Middle | Province | Program | Button */
      grid-template-columns:
        minmax(0, 1.2fr)
        minmax(0, 1.2fr)
        minmax(0, 0.5fr)
        minmax(0, 1.4fr)
        minmax(0, 1.4fr)
        auto;
      gap: 14px;
      align-items: end;
    }
    body.results-mode .filters .field {
      min-width: 0;
    }
    body.results-mode .filters .actions {
      margin: 0;
      padding: 0;
      border: 0;
    }
    body.results-mode .filters .actions .btn {
      width: auto;
      max-width: none;
      min-height: 44px;
      font-size: 13px;
      padding: 12px 22px;
      white-space: nowrap;
    }

    /* Filter "drops in" from above to compact position; table rises up from below */
    body.results-mode .filter-panel {
      animation: filterDrop .55s cubic-bezier(.2,.9,.2,1) both;
    }
    body.results-mode .results-container {
      animation: resultsRiseUp .65s cubic-bezier(.2,.9,.2,1) both .15s;
      opacity: 0;
    }
    @keyframes filterDrop {
      from { opacity: 0; transform: translateY(-24px); }
      to { opacity: 1; transform: translateY(0); }
    }
    @keyframes resultsRiseUp {
      from { opacity: 0; transform: translateY(40px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* Stagger rows in the table for a polished entrance */
    body.results-mode tbody tr.scholar-row {
      opacity: 0;
      animation: rowIn .35s ease both;
    }
    body.results-mode tbody tr.scholar-row:nth-child(1)  { animation-delay: .35s; }
    body.results-mode tbody tr.scholar-row:nth-child(2)  { animation-delay: .39s; }
    body.results-mode tbody tr.scholar-row:nth-child(3)  { animation-delay: .43s; }
    body.results-mode tbody tr.scholar-row:nth-child(4)  { animation-delay: .47s; }
    body.results-mode tbody tr.scholar-row:nth-child(5)  { animation-delay: .51s; }
    body.results-mode tbody tr.scholar-row:nth-child(6)  { animation-delay: .55s; }
    body.results-mode tbody tr.scholar-row:nth-child(7)  { animation-delay: .59s; }
    body.results-mode tbody tr.scholar-row:nth-child(8)  { animation-delay: .63s; }
    body.results-mode tbody tr.scholar-row:nth-child(n+9) { animation-delay: .67s; }
    @keyframes rowIn {
      from { opacity: 0; transform: translateY(8px); }
      to { opacity: 1; transform: translateY(0); }
    }

    /* ===== Responsive ===== */
    @media (max-width: 1100px) {
      body.search-mode .filters,
      body.results-mode .filters { grid-template-columns: 1fr 1fr; }
      body.search-mode .filters .actions,
      body.results-mode .filters .actions { grid-column: 1 / -1; }
    }
    @media (max-width: 720px) {
      body.search-mode .filters,
      body.results-mode .filters { grid-template-columns: 1fr; }
      body.search-mode .filter-panel { padding: 24px 22px; }
    }

    /* ========================================================
       Loading overlays
       ======================================================== */
    .table-wrap { position: relative; }
    .table-loading {
      position: absolute;
      inset: 0;
      background: rgba(255, 252, 244, 0.88);
      backdrop-filter: blur(4px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 16px;
      z-index: 10;
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity .25s ease, visibility .25s ease;
    }
    .table-loading.active { opacity: 1; visibility: visible; pointer-events: auto; }
    .table-loading .spinner {
      width: 38px; height: 38px;
      border: 3px solid var(--rule-hair);
      border-top-color: var(--gold);
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
    .table-loading .loading-label {
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--muted);
    }
    html[data-loading="1"] .table-loading {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }

    /* Full-page transition overlay (covers viewport during form submit) */
    .page-transition-overlay {
      position: fixed;
      inset: 0;
      background: rgba(245, 239, 228, 0.92);
      backdrop-filter: blur(6px);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 16px;
      z-index: 100;
      opacity: 0;
      visibility: hidden;
      pointer-events: none;
      transition: opacity .25s ease, visibility .25s ease;
    }
    .page-transition-overlay.active {
      opacity: 1;
      visibility: visible;
      pointer-events: auto;
    }
    .page-transition-overlay .spinner {
      width: 44px; height: 44px;
      border: 3px solid var(--rule-hair);
      border-top-color: var(--gold);
      border-radius: 50%;
      animation: spin .7s linear infinite;
    }
    .page-transition-overlay .loading-label {
      font-family: var(--font-mono);
      font-size: 12px;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: var(--muted);
    }

    /* "New Search" pill */
    .new-search-link {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 12px;
      background: var(--paper-3);
      color: var(--ink);
      border-radius: 999px;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: .12em;
      text-transform: uppercase;
      border: 1px solid var(--rule-hair);
      transition: border-color .15s, background .15s;
      text-decoration: none;
    }
    .new-search-link:hover {
      border-color: var(--ink-2);
      background: var(--paper-2);
    }
  </style>
</head>
<body class="show-backend-navs <?php echo $bodyMode; ?>">
<header class="site-header">
  <div class="header-inner">
    <div class="brand">
      <img src="logo.png" alt="OWWA Region IX seal">
      <div>
        <div class="eyebrow">Republic of the Philippines · Region IX</div>
        <h1>Scholar Dashboard</h1>
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
    <a class="nav-btn" data-static="true" href="main.html#page-student"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M8 2L1 6l7 4 7-4-7-4z"/><path d="M3 8v3c0 1.5 2.5 3 5 3s5-1.5 5-3V8"/></svg>Scholar Dashboard</a>
    <a class="nav-btn" data-static="true" href="main.html#page-report"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 2h7l3 3v9H3z"/><path d="M10 2v3h3M5 8h6M5 11h6"/></svg>Report</a>
    <a class="nav-btn active" data-backend="true" href="dashboard.php"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 3h12v10H2z"/><path d="M2 6h12M6 3v10"/></svg>Scholar Dashboard</a>
    <a class="nav-btn" data-backend="true" href="report.php"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 2h7l3 3v9H3z"/><path d="M10 2v3h3M5 8h6M5 11h6"/></svg>Report</a>
    <a class="nav-btn" data-backend="true" href="crud.php"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3.5h10v9H3z"/><path d="M6 3.5v9M3 7h10"/><path d="M7.5 3.5v9"/></svg>Add Records</a>
  </div>
</nav>

<main>
  <?php if ($notice !== ''): ?>
    <div class="panel" style="margin-bottom:18px;border-left:4px solid var(--ok);background:var(--ok-soft)">
      <strong style="display:block;color:var(--ok);font-size:14px;letter-spacing:.04em;text-transform:uppercase;margin-bottom:4px">Success</strong>
      <div style="color:var(--text)"><?php echo owwa_h($notice); ?></div>
    </div>
  <?php endif; ?>

  <div class="stat-row cols-3">
    <div class="stat">
      <div class="lbl"><?php echo $hasFilters ? 'Matching Scholars' : 'Total Scholars'; ?></div>
      <div class="num"><?php echo $hasFilters ? $filtered : $total; ?></div>
      <?php if ($hasFilters): ?>
        <div class="delta">of <?php echo $total; ?> total</div>
      <?php endif; ?>
    </div>
    <div class="stat">
      <div class="lbl">Province</div>
      <div class="num"><?php echo $province !== '' ? owwa_h($province) : 'All Provinces'; ?></div>
    </div>
    <div class="stat">
      <div class="lbl">Program</div>
      <div class="num"><?php echo $program !== '' ? owwa_h($program) : 'All Programs'; ?></div>
    </div>
  </div>

  <!-- SEARCH HERO: heading only visible in search-mode; filter panel always visible -->
  <section class="search-hero">
    <div class="search-hero-intro">
      <div class="eyebrow">Scholar Lookup</div>
      <h2><em>Find a scholar</em></h2>
    </div>

    <div class="panel filter-panel">
      <h3>Search Filters</h3>
      <form class="filters" method="get" id="dashFilters">
        <input type="hidden" name="submitted" value="1">
        <div class="field">
          <label>Last Name</label>
          <input type="text" name="last_name" value="<?php echo owwa_h($last_name); ?>" placeholder="e.g. Dela Cruz">
        </div>
        <div class="field">
          <label>First Name</label>
          <input type="text" name="first_name" value="<?php echo owwa_h($first_name); ?>" placeholder="e.g. Maria">
        </div>
        <div class="field">
          <label>Middle</label>
          <input type="text" name="middle_initial" value="<?php echo owwa_h($middle_initial); ?>" >
        </div>
        <div class="field">
          <label>Province</label>
          <select name="province">
            <option value="">Select Province</option>
            <?php foreach ($provinceOptions as $item): ?>
              <option value="<?php echo owwa_h($item); ?>" <?php echo $province === $item ? 'selected' : ''; ?>><?php echo owwa_h($item); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Program</label>
          <select name="program">
            <option value="">Select Program</option>
            <?php foreach ($programOptions as $item): ?>
              <option value="<?php echo owwa_h($item); ?>" <?php echo $program === $item ? 'selected' : ''; ?>><?php echo owwa_h($item); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="actions">
          <button class="btn primary" type="submit">
            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="7" cy="7" r="5"/><path d="M11 11l3 3"/></svg>
            <?php echo $hasFilters ? 'Apply Filters' : 'Search Records'; ?>
          </button>
        </div>
      </form>
      <?php if ($activeFilters): ?>
        <div class="chip-row">
          <?php foreach ($activeFilters as $label => $value): ?>
            <span class="chip"><?php echo owwa_h($label); ?>: <?php echo owwa_h($value); ?></span>
          <?php endforeach; ?>
          <a class="new-search-link" href="dashboard.php" id="newSearchLink">
            <svg width="10" height="10" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4l8 8M12 4l-8 8"/></svg>
            New Search
          </a>
        </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- RESULTS (only rendered in results-mode) -->
  <?php if ($hasResults): ?>
  <div class="results-container" style="margin-top: 28px;">
    <div class="table-wrap" id="dashboardTable">
      <div class="table-loading" id="tableLoading">
        <div class="spinner"></div>
        <div class="loading-label">Loading records...</div>
      </div>
      <div class="table-head">
        <h3>Scholar Records</h3>
        <div class="count">
          <?php echo $filtered; ?> record<?php echo $filtered === 1 ? '' : 's'; ?> shown
          <?php echo $hasFilters ? '· filtered' : '· all scholars'; ?>
          · sorted newest first
        </div>
      </div>
      <table>
        <thead>
          <tr>
            <th>Last Name</th>
            <th>First Name</th>
            <th>Middle</th>
            <th>Province</th>
            <th>Program</th>
            <th>Year</th>
            <th>Life Stage</th>
            <th>Academic</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8" style="padding:48px 24px;text-align:center">
              <?php if ($hasFilters): ?>
                <div style="color:var(--muted);font-family:var(--font-display);font-style:italic;font-size:18px;margin-bottom:8px">No records</div>
              <?php else: ?>
                <div style="color:var(--muted);font-family:var(--font-display);font-style:italic;font-size:18px;margin-bottom:8px">No scholars in the system yet</div>
                <div class="small"><a href="crud.php" style="color:var(--ink);text-decoration:underline">Add the first scholar record</a> to get started.</div>
              <?php endif; ?>
            </td></tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <?php
                $detailPayload = [
                  'id'              => $row['id'],
                  'full_name'       => trim($row['last_name'] . ', ' . $row['first_name'] . ' ' . dashboard_middle_initial($row['middle_initial'])),
                  'last_name'       => $row['last_name'] ?? '',
                  'first_name'      => $row['first_name'] ?? '',
                  'middle_initial'  => $row['middle_initial'] ?? '',
                  'birthdate'       => dashboard_fmt_birthdate($row['birthdate'] ?? null),
                  'province'        => $row['province'] ?? '',
                  'program'         => $row['program'] ?? '',
                  'year'            => $row['year_applied'] ?? '',
                  'life_stage'      => $row['life_stage'] ?: 'Student',
                  'created_at'      => $row['created_at'] ? date('M d, Y', strtotime((string)$row['created_at'])) : '—',
                  'phone'           => $row['phone'] ?? '',
                  'academic_status' => $row['academic_status'] ?: 'Maintained',
                ];
              ?>
              <tr
                class="scholar-row"
                tabindex="0"
                role="button"
                aria-label="Open scholar details"
                data-id="<?php echo owwa_h((string)$detailPayload['id']); ?>"
                data-full-name="<?php echo owwa_h($detailPayload['full_name']); ?>"
                data-last-name="<?php echo owwa_h($detailPayload['last_name']); ?>"
                data-first-name="<?php echo owwa_h($detailPayload['first_name']); ?>"
                data-middle-initial="<?php echo owwa_h($detailPayload['middle_initial']); ?>"
                data-birthdate="<?php echo owwa_h($detailPayload['birthdate']); ?>"
                data-province="<?php echo owwa_h($detailPayload['province']); ?>"
                data-program="<?php echo owwa_h($detailPayload['program']); ?>"
                data-phone="<?php echo owwa_h($detailPayload['phone']); ?>"
                data-year="<?php echo owwa_h((string)$detailPayload['year']); ?>"
                data-life-stage="<?php echo owwa_h($detailPayload['life_stage']); ?>"
                data-created-at="<?php echo owwa_h($detailPayload['created_at']); ?>"
                data-academic-status="<?php echo owwa_h($detailPayload['academic_status']); ?>"
              >
                <td><?php echo owwa_h($row['last_name']); ?></td>
                <td><?php echo owwa_h($row['first_name']); ?></td>
                <td><?php echo owwa_h(dashboard_middle_initial($row['middle_initial'])); ?></td>
                <td><?php echo owwa_h($row['province']); ?></td>
                <td><span class="badge gold"><?php echo owwa_h($row['program']); ?></span></td>
                <td style="font-family:var(--font-mono);font-size:13px;letter-spacing:.05em"><?php echo owwa_h($row['year_applied']); ?></td>
                <td><span class="badge info"><?php echo owwa_h($row['life_stage'] ?: 'Student'); ?></span></td>
                <td><?php
                  $stat = $row['academic_status'] ?: 'Maintained';
                  $badgeClass = $stat === 'Maintained' ? 'ok'
                              : ($stat === 'Probation' ? 'warn' : 'danger');
                ?>
                  <span class="badge <?php echo $badgeClass; ?>"><?php echo owwa_h($stat); ?></span>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</main>

<!-- Full-page overlay shown during form submission -->
<div class="page-transition-overlay" id="pageTransitionOverlay">
  <div class="spinner"></div>
  <div class="loading-label">Searching records...</div>
</div>

<div class="scholar-modal-backdrop" id="scholarModalBackdrop" aria-hidden="true">
  <div class="scholar-modal" role="dialog" aria-modal="true" aria-labelledby="scholarModalTitle">
    <div class="scholar-modal-head">
      <h3 class="scholar-modal-title" id="scholarModalTitle">Scholar Details</h3>
      <div style="display:flex;gap:8px;align-items:center">
        <a id="scholarModalEdit" class="btn primary" href="#" style="text-decoration:none;padding:6px 10px;font-size:13px">Edit Profile</a>
        <button class="scholar-modal-close" type="button" data-close-modal aria-label="Close details">&times;</button>
      </div>
    </div>
    <div class="scholar-modal-body">
      <div class="scholar-photo-box">
        <div class="scholar-photo-placeholder" id="scholarModalInitials"></div>
        <div class="small" id="scholarModalPhotoName">Scholar name</div>
      </div>
      <div class="scholar-details">
        <div class="detail-card"><span class="label">Last Name</span><span class="value" id="scholarModalLast">—</span></div>
        <div class="detail-card"><span class="label">First Name</span><span class="value" id="scholarModalFirst">—</span></div>
        <div class="detail-card"><span class="label">Middle Name</span><span class="value" id="scholarModalMiddle">—</span></div>
        <div class="detail-card"><span class="label">Birthdate</span><span class="value" id="scholarModalBirthdate">Not on file</span></div>
        <div class="detail-card"><span class="label">Province</span><span class="value" id="scholarModalProvince">—</span></div>
        <div class="detail-card"><span class="label">Program</span><span class="value" id="scholarModalProgram">—</span></div>
        <div class="detail-card"><span class="label">Year</span><span class="value" id="scholarModalYear">—</span></div>
        <div class="detail-card"><span class="label">Life Stage</span><span class="value" id="scholarModalLifeStage">—</span></div>
        <div class="detail-card"><span class="label">Academic Status</span><span class="value" id="scholarModalAcademic">—</span></div>
        <div class="detail-card"><span class="label">Phone</span><span class="value" id="scholarModalPhone">—</span></div>
        <div class="detail-card" style="grid-column:1/-1"><span class="label">Created</span><span class="value" id="scholarModalCreatedAt">—</span></div>
      </div>
    </div>
  </div>
</div>

<script>
// ----- Form submit: show full-page overlay -----
(function() {
  var form = document.getElementById('dashFilters');
  var pageOverlay = document.getElementById('pageTransitionOverlay');
  if (!form) return;
  form.addEventListener('submit', function() {
    try {
      sessionStorage.setItem('owwa-loading-' + location.pathname, '1');
      if (pageOverlay) pageOverlay.classList.add('active');
    } catch (e) { /* ignore */ }
  });

  var newSearchLink = document.getElementById('newSearchLink');
  if (newSearchLink && pageOverlay) {
    newSearchLink.addEventListener('click', function() {
      pageOverlay.classList.add('active');
    });
  }
})();

// ----- Hide table overlay once new page is fully loaded -----
(function() {
  if (document.documentElement.dataset.loading === '1') {
    var overlay = document.getElementById('tableLoading');
    if (overlay) overlay.classList.add('active');
    window.addEventListener('load', function() {
      setTimeout(function() {
        if (overlay) overlay.classList.remove('active');
        delete document.documentElement.dataset.loading;
      }, 200);
    });
  }
})();

// ----- Scholar modal logic -----
const modalBackdrop = document.getElementById('scholarModalBackdrop');
const modalTitle = document.getElementById('scholarModalTitle');
const modalPhotoName = document.getElementById('scholarModalPhotoName');
const modalInitials = document.getElementById('scholarModalInitials');
const modalLast = document.getElementById('scholarModalLast');
const modalFirst = document.getElementById('scholarModalFirst');
const modalMiddle = document.getElementById('scholarModalMiddle');
const modalBirthdate = document.getElementById('scholarModalBirthdate');
const modalProvince = document.getElementById('scholarModalProvince');
const modalProgram = document.getElementById('scholarModalProgram');
const modalYear = document.getElementById('scholarModalYear');
const modalLifeStage = document.getElementById('scholarModalLifeStage');
const modalAcademic = document.getElementById('scholarModalAcademic');
const modalCreatedAt = document.getElementById('scholarModalCreatedAt');
const modalEditLink = document.getElementById('scholarModalEdit');
const modalPhone = document.getElementById('scholarModalPhone');

function formatPhoneForDisplay(phone) {
  if (!phone) return '—';
  var s = String(phone).replace(/\D/g, '');
  if (s.length === 11) {
    return s.slice(0,4) + ' ' + s.slice(4,7) + ' ' + s.slice(7);
  }
  if (s.length === 10) {
    return s.slice(0,3) + ' ' + s.slice(3,6) + ' ' + s.slice(6);
  }
  return phone;
}

function openScholarModal(data) {
  if (!modalBackdrop || !data) return;
  const lifeStage = data.life_stage || data.lifeStage || '—';
  const createdAt = data.created_at || data.createdAt || '—';

  modalTitle.textContent = 'Scholar Details';
  if (modalInitials) modalInitials.textContent = '';
  if (modalPhotoName) modalPhotoName.textContent = data.full_name || data.fullName || 'Scholar';

  modalLast.textContent      = data.lastName || '—';
  modalFirst.textContent     = data.firstName || '—';
  modalMiddle.textContent    = data.middleInitial || '—';
  modalBirthdate.textContent = data.birthdate || 'Not on file';
  modalProvince.textContent  = data.province || '—';
  modalProgram.textContent   = data.program || '—';
  modalYear.textContent      = data.year || '—';
  modalLifeStage.textContent = lifeStage;
  modalAcademic.textContent  = data.academicStatus || data.academic_status || '—';
  modalPhone.textContent     = data.phone ? formatPhoneForDisplay(data.phone) : '—';
  modalCreatedAt.textContent = createdAt;

  if (modalEditLink) {
    if (data.id) {
      modalEditLink.href = 'crud.php?edit=' + encodeURIComponent(data.id);
      modalEditLink.style.display = '';
    } else {
      modalEditLink.href = '#';
      modalEditLink.style.display = 'none';
    }
  }
  modalBackdrop.classList.add('open');
  modalBackdrop.setAttribute('aria-hidden', 'false');
}

function closeScholarModal() {
  if (!modalBackdrop) return;
  modalBackdrop.classList.remove('open');
  modalBackdrop.setAttribute('aria-hidden', 'true');
}

document.addEventListener('click', (event) => {
  const row = event.target.closest('tr.scholar-row');
  if (!row) return;
  try { row.classList.add('clicked'); window.setTimeout(() => row.classList.remove('clicked'), 450); } catch (e) {}
  openScholarModal(row.dataset);
});

document.addEventListener('keydown', (event) => {
  const row = event.target.closest('tr.scholar-row');
  if (!row) return;
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    try { row.classList.add('clicked'); window.setTimeout(() => row.classList.remove('clicked'), 450); } catch (e) {}
    openScholarModal(row.dataset);
  }
});

if (modalBackdrop) {
  modalBackdrop.addEventListener('click', (event) => {
    if (event.target === modalBackdrop || event.target.hasAttribute('data-close-modal')) {
      closeScholarModal();
    }
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeScholarModal();
  });
}

// ----- Header date/time -----
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
    } catch (e) { /* ignore */ }
  }
  document.addEventListener('DOMContentLoaded', function() {
    updateDateTime();
    setInterval(updateDateTime, 1000);
  });
})();
</script>

<footer class="site-footer">
  <div class="seal">OWWA Region IX · Scholar Monitoring v1.1</div>
  <div>Scholar Dashboard</div>
</footer>
</body>
</html>