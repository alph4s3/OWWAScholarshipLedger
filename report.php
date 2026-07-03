<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);
require __DIR__ . '/db.php';
require __DIR__ . '/auth.php';

$pdo = owwa_bootstrap();

$programOptions = ['SUP', 'SESP', 'EDSP', 'ODSP', 'CMWSP', 'ELAP-EA'];
$academicStatusOptions = ['Maintained', 'Probation', 'Terminated'];
$provinceOptions = [
    'Zamboanga City','Zamboanga Sibugay','Isabela, Basilan',
    'Pagadian','Zamboanga Del Sur','Zamboanga Del Norte','Liloy','Buug'
];

$program        = trim($_GET['program'] ?? '');
$province       = trim($_GET['province'] ?? '');
$dateMode       = trim($_GET['date_mode'] ?? '');
$todayOnly      = isset($_GET['today']);
$academicStatus = trim($_GET['academic_status'] ?? '');

$sql = "SELECT id, last_name, first_name, middle_initial, birthdate, province, program, phone, YEAR(created_at) AS year_applied, life_stage, academic_status, noa_date, created_at FROM scholars_crud WHERE 1=1";
$params = [];
if (in_array($program, $programOptions, true)) {
    $sql .= " AND program = :program";
    $params[':program'] = $program;
}
if (in_array($province, $provinceOptions, true)) {
    $sql .= " AND province = :province";
    $params[':province'] = $province;
}
if ($todayOnly) {
  $sql .= " AND COALESCE(life_stage,'') != 'Terminated'";
}
if (in_array($academicStatus, $academicStatusOptions, true)) {
    $sql .= " AND academic_status = :academic_status";
    $params[':academic_status'] = $academicStatus;
}

if ($dateMode === 'noa') {
    $sql .= " ORDER BY noa_date DESC, created_at DESC, id DESC";
} elseif ($dateMode === 'created') {
    $sql .= " ORDER BY created_at DESC, id DESC";
} else {
    $sql .= " ORDER BY id DESC";
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();
$total = count($rows);

$titleParts = [];
$titleParts[] = $program !== '' ? $program . ' Program' : 'All Programs';
if ($province !== '')       $titleParts[] = $province;
if ($todayOnly)             $titleParts[] = 'Active Only';
if ($academicStatus !== '') $titleParts[] = $academicStatus;
$reportTitle = implode(' · ', $titleParts);

// CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $filename = preg_replace('/[^a-z0-9\-_.]/i', '_', ($reportTitle ?: 'owwa_report')) . '_' . date('Ymd');
  header('Content-Type: text/csv; charset=UTF-8');
  header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
  echo "\xEF\xBB\xBF";
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Last Name','First Name','Middle','Program','Province','Year','NOA Date','Created','Phone','Life Stage','Academic Status','Birthdate']);
  foreach ($rows as $r) {
    fputcsv($out, [
      $r['last_name'] ?? '',
      $r['first_name'] ?? '',
      $r['middle_initial'] ?? '',
      $r['program'] ?? '',
      $r['province'] ?? '',
      $r['year_applied'] ?? '',
      ($r['noa_date'] ? date('M d, Y', strtotime((string)$r['noa_date'])) : ''),
      ($r['created_at'] ? date('M d, Y', strtotime((string)$r['created_at'])) : ''),
      $r['phone'] ?? '',
      $r['life_stage'] ?? '',
      $r['academic_status'] ?? '',
      ($r['birthdate'] ? date('F j, Y', strtotime((string)$r['birthdate'])) : ''),
    ]);
  }
  fclose($out);
  exit;
}

$exportParams = $_GET;
unset($exportParams['export']);
$exportParams['export'] = 'csv';
$exportUrl = 'report.php?' . http_build_query($exportParams);

$dateModeLabel = $dateMode === 'noa' ? 'By NOA Date'
              : ($dateMode === 'created' ? 'By Created Date' : 'By Record ID');

$activeFiltersSummary = [];
if ($program !== '')        $activeFiltersSummary[] = 'Program: ' . $program;
if ($province !== '')       $activeFiltersSummary[] = 'Province: ' . $province;
if ($todayOnly)             $activeFiltersSummary[] = 'Enrolled Only';
if ($academicStatus !== '') $activeFiltersSummary[] = 'Academic: ' . $academicStatus;
$activeFiltersText = $activeFiltersSummary ? implode(' · ', $activeFiltersSummary) : 'No filters applied';

// Deterministic report reference number
$filterSignature = implode('|', [$program, $province, $dateMode, $todayOnly ? '1' : '0', $academicStatus]);
$reportRef = 'OWWA-IX-RPT-' . date('Ymd') . '-' . strtoupper(substr(md5($filterSignature), 0, 4));

function fmt_date(?string $value): string {
  if (!$value) return '<span style="color:var(--rule-soft)">—</span>';
  $time = strtotime($value);
  return $time ? date('M d, Y', $time) : owwa_h($value);
}
function fmt_birthdate(?string $value): string {
  if (!$value) return 'Not on file';
  $time = strtotime($value);
  return $time ? date('F j, Y', $time) : owwa_h($value);
}
function fmt_middle_initial(?string $value): string {
  $value = trim((string)$value);
  if ($value === '') return '';
  $first = mb_substr($value, 0, 1);
  return strtoupper($first) . '.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>OWWA Region IX — Program Reports</title>

  <script>
    if ('scrollRestoration' in history) {
      history.scrollRestoration = 'manual';
    }
    (function() {
      try {
        var scrollKey = 'owwa-scroll-' + location.pathname;
        var saved = sessionStorage.getItem(scrollKey);
        if (saved !== null) {
          var y = parseInt(saved, 10) || 0;
          sessionStorage.removeItem(scrollKey);
          var tries = 0;
          var stick = function() {
            window.scrollTo(0, y);
            tries++;
            if (tries < 15 && Math.abs(window.scrollY - y) > 1) {
              requestAnimationFrame(stick);
            }
          };
          document.addEventListener('DOMContentLoaded', function() {
            requestAnimationFrame(stick);
          });
        }
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
    /* ----- header meta alignment ----- */
    .site-header .header-inner { align-items: flex-start; }
    .site-header .meta { line-height: 1.4; }
    .site-header .meta strong { display: inline; margin-right: 6px; }
    .meta-date { display: block; font-size: 14px; margin-top: 6px; color: inherit; }
    .meta-time { display: block; font-size: 13px; color: inherit; margin-top: 2px; opacity: 0.85; }
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

    /* ----- filter strip ----- */
    .filters { grid-template-columns: 1.1fr 1fr 1fr 1fr 1fr auto; }
    .checkbox-wrap {
      display: flex; align-items: center; gap: 10px;
      padding: 12px 14px;
      border: 1px solid var(--rule-hair); border-radius: 3px;
      background: var(--paper-3);
      font-size: 13px; color: var(--text);
      cursor: pointer; user-select: none;
      transition: border-color .15s, background .15s;
    }
    .checkbox-wrap:hover { border-color: var(--ink-2); }
    .checkbox-wrap input {
      width: auto;
      margin: 0;
      accent-color: var(--ink);
    }
    .checkbox-wrap input[type="checkbox"],
    .checkbox-wrap input[type="checkbox"]:hover,
    .checkbox-wrap input[type="checkbox"]:focus,
    .checkbox-wrap input[type="checkbox"]:active {
      padding: 0 !important;
      border: 0 !important;
      background: transparent !important;
      box-shadow: none !important;
      outline: none !important;
      transition: none !important;
    }
    .field label.checkbox-wrap {
      display: flex;
      align-items: center;
      gap: 10px;
      width: 100%;
      box-sizing: border-box;
      margin-bottom: 0;
      text-transform: none;
      font-family: inherit;
      font-size: 13px;
      letter-spacing: normal;
      color: var(--text);
      font-weight: 400;
    }
    @media (max-width: 1100px) {
      .filters { grid-template-columns: 1fr 1fr; }
      .filters .actions { grid-column: 1 / -1; }
    }
    @media (max-width: 720px) {
      .filters { grid-template-columns: 1fr; }
    }

    /* ----- document-style preview ----- */
    .report-document {
      position: relative;
      background: #fff;
      border: 1px solid var(--rule-hair);
      border-radius: 4px;
      overflow: hidden;
      box-shadow: 0 12px 32px rgba(18, 29, 47, 0.08), 0 2px 4px rgba(18, 29, 47, 0.04);
    }
    .report-document::before {
      content: "";
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--red) 0%, #c54040 40%, var(--red) 100%);
    }
    .report-document::after {
      content: "OFFICIAL";
      position: absolute;
      top: 50%; left: 50%;
      transform: translate(-50%, -50%) rotate(-22deg);
      font-family: var(--font-display);
      font-style: italic;
      font-weight: 600;
      font-size: 180px;
      color: rgba(11, 29, 51, 0.025);
      letter-spacing: 0.1em;
      pointer-events: none;
      white-space: nowrap;
      z-index: 0;
    }

    .doc-letterhead {
      position: relative;
      z-index: 1;
      padding: 24px 28px 18px;
      border-bottom: 1px solid var(--rule-hair);
      background: linear-gradient(180deg, #fffdf8 0%, var(--paper-2) 100%);
    }
    .doc-letterhead-row {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 24px;
    }
    .doc-eyebrow {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.28em;
      text-transform: uppercase;
      color: var(--red);
      font-weight: 700;
      margin-bottom: 6px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .doc-eyebrow::before {
      content: "";
      width: 18px; height: 2px;
      background: var(--red);
    }
    .doc-title {
      font-family: var(--font-display);
      font-style: italic;
      font-weight: 500;
      font-size: 26px;
      color: var(--ink);
      margin: 0;
      line-height: 1.15;
      letter-spacing: -0.015em;
    }
    .doc-subtitle {
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--muted);
      margin-top: 4px;
    }
    .doc-reference {
      text-align: right;
      font-family: var(--font-mono);
      font-size: 10px;
      letter-spacing: 0.1em;
      color: var(--muted);
      line-height: 1.7;
      flex-shrink: 0;
    }
    .doc-reference .ref-no {
      display: block;
      color: var(--ink);
      font-weight: 600;
      letter-spacing: 0.06em;
      font-size: 11px;
      padding: 4px 8px;
      background: var(--paper);
      border: 1px solid var(--rule-hair);
      border-radius: 2px;
      margin-bottom: 4px;
    }

    .doc-actions {
      position: relative;
      z-index: 1;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      padding: 14px 24px;
      border-bottom: 1px solid var(--rule-hair);
      background: #fff;
    }
    .doc-actions .doc-count {
      font-family: var(--font-mono);
      font-size: 11px;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--muted);
    }
    .doc-actions .doc-count b {
      color: var(--ink);
      font-weight: 700;
      font-size: 14px;
      font-family: var(--font-display);
      font-style: italic;
      letter-spacing: 0;
      text-transform: none;
      margin-right: 4px;
    }
    .doc-actions .action-group { display: flex; gap: 10px; }
    .doc-actions .btn.secondary {
      min-height: 36px;
      padding: 8px 14px;
      font-size: 12px;
    }

    .report-document table {
      position: relative;
      z-index: 1;
      width: 100%;
      border-collapse: collapse;
      background: #fff;
    }
    .report-document th {
      background: #fbfaf3 !important;
      border-bottom: 2px solid var(--ink) !important;
      padding: 14px 20px;
    }
    .report-document td {
      padding: 14px 20px;
    }

    /* ----- loading overlay ----- */
    .table-loading {
      position: absolute; inset: 0;
      background: rgba(255, 252, 244, 0.88);
      backdrop-filter: blur(4px);
      display: flex; flex-direction: column; align-items: center; justify-content: center;
      gap: 16px; z-index: 20;
      opacity: 0; visibility: hidden; pointer-events: none;
      transition: opacity .25s ease, visibility .25s ease;
    }
    .table-loading.active { opacity: 1; visibility: visible; pointer-events: auto; }
    .table-loading .spinner {
      width: 38px; height: 38px;
      border: 3px solid var(--rule-hair);
      border-top-color: var(--red);
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
      opacity: 1; visibility: visible; pointer-events: auto;
    }


    /* Kill the transient size/glow on form interaction */
    .filters .field select,
    .filters .field input {
      transition: background-color .15s, color .15s;
    }
    .filters .field select:focus,
    .filters .field input:focus,
    .filters .field select:active,
    .filters .field input:active {
      outline: 2px solid var(--gold);
      outline-offset: -1px;
      box-shadow: none !important;
      border-color: var(--rule-hair) !important;
    }
    .checkbox-wrap,
    .checkbox-wrap:hover,
    .checkbox-wrap:focus-within,
    .checkbox-wrap:active {
      border-width: 1px !important;
      transform: none !important;
      transition: background-color .15s;
    }
    .checkbox-wrap:focus-within {
      outline: 2px solid var(--gold);
      outline-offset: -1px;
    }

    @media (max-width: 720px) {
      .doc-letterhead-row { flex-direction: column; }
      .doc-reference { text-align: left; }
      .doc-actions { flex-direction: column; align-items: stretch; }
      .doc-actions .action-group { justify-content: flex-end; }
      .report-document::after { font-size: 120px; }
    }

    /* ----- print-only header ----- */
    .print-header { display: none; }

    @media print {
      @page { size: A4 landscape; margin: 12mm 10mm; }
      html, body {
        background: #fff !important;
        color: #000 !important;
        font-family: 'Manrope', Arial, sans-serif !important;
        font-size: 10pt;
      }
      .site-header,
      nav.site-nav,
      footer.site-footer,
      .page-head,
      .panel,
      .doc-actions,
      .table-loading,
      .scholar-modal-backdrop {
        display: none !important;
      }
      main {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
        animation: none !important;
      }
      .report-document {
        border: 0 !important;
        box-shadow: none !important;
        background: transparent !important;
      }
      .report-document::before,
      .report-document::after { display: none !important; }
      .doc-letterhead { display: none !important; }

      .print-header {
        display: block !important;
        margin-bottom: 12pt;
        padding-bottom: 10pt;
        border-bottom: 2px solid #000;
      }
      .print-header .ph-top {
        display: flex; justify-content: space-between;
        align-items: flex-start; gap: 16pt;
      }
      .print-header .ph-agency {
        font-size: 9pt;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #555;
        margin-bottom: 4pt;
      }
      .print-header .ph-title {
        font-family: 'Fraunces', Georgia, serif !important;
        font-size: 20pt; font-style: italic; font-weight: 500;
        color: #000; margin: 0 0 4pt 0; line-height: 1.15;
      }
      .print-header .ph-subtitle { font-size: 10pt; color: #333; margin: 0; }
      .print-header .ph-meta {
        font-size: 9pt; text-align: right; color: #444;
        line-height: 1.5; white-space: nowrap;
        font-family: 'JetBrains Mono', monospace !important;
      }
      .print-header .ph-meta strong {
        display: block;
        font-family: 'Fraunces', Georgia, serif !important;
        font-style: italic; font-size: 11pt;
        color: #000; margin-bottom: 2pt;
      }
      .doc-reference { display: none !important; }

      table { width: 100% !important; border-collapse: collapse !important; page-break-inside: auto; }
      thead { display: table-header-group; }
      tr { page-break-inside: avoid; page-break-after: auto; }
      th, td {
        border: 1px solid #333 !important;
        padding: 6pt 8pt !important;
        font-size: 9.5pt !important;
        color: #000 !important;
        background: transparent !important;
        vertical-align: middle !important;
        text-align: left !important;
      }
      th {
        background: #f0f0f0 !important;
        font-family: 'JetBrains Mono', monospace !important;
        font-size: 8.5pt !important;
        font-weight: 600 !important;
        letter-spacing: 0.08em !important;
        text-transform: uppercase !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
      }
      .badge {
        background: transparent !important;
        color: #000 !important;
        border: 0 !important;
        padding: 0 !important;
        font-family: 'JetBrains Mono', monospace !important;
        font-size: 9pt !important;
        letter-spacing: 0.04em;
        text-transform: none !important;
      }
      .badge::before { display: none !important; }
      a { color: #000 !important; text-decoration: none !important; }
    }
  </style>
</head>
<body class="show-backend-navs<?php echo !empty($_SERVER['QUERY_STRING']) ? ' no-anim' : ''; ?>">

<header class="site-header">
  <div class="header-inner">
    <div class="brand">
      <img src="logo.png" alt="OWWA Region IX seal">
      <div>
        <div class="eyebrow">Republic of the Philippines · Region IX</div>
        <h1>Program Reports</h1>
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
    <a class="nav-btn" data-backend="true" href="dashboard.php"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M2 3h12v10H2z"/><path d="M2 6h12M6 3v10"/></svg>Scholar Dashboard</a>
    <a class="nav-btn active" data-backend="true" href="report.php"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 2h7l3 3v9H3z"/><path d="M10 2v3h3M5 8h6M5 11h6"/></svg>Report</a>
    <a class="nav-btn" data-backend="true" href="crud.php"><svg class="ic" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 3.5h10v9H3z"/><path d="M6 3.5v9M3 7h10"/><path d="M7.5 3.5v9"/></svg>Add Records</a>
  </div>
</nav>

<main>

  <!-- PRINT-ONLY HEADER -->
  <div class="print-header">
    <div class="ph-top">
      <div>
        <div class="ph-agency">Overseas Workers Welfare Administration · Region IX</div>
        <h1 class="ph-title"><?php echo owwa_h($reportTitle); ?></h1>
        <div class="ph-subtitle">Scholarship Program Report</div>
      </div>
      <div class="ph-meta">
        <strong>OWWA IX</strong>
        Scholar Services
      </div>
    </div>
  </div>

  <!-- FILTER PANEL -->
  <div class="panel">
    <h3>Report controls</h3>
    <form class="filters" method="get" id="reportFilters">
      <div class="field">
        <label>Program</label>
        <select name="program">
          <option value="">All Programs</option>
          <?php foreach ($programOptions as $item): ?>
            <option value="<?php echo owwa_h($item); ?>" <?php echo $program === $item ? 'selected' : ''; ?>><?php echo owwa_h($item); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Province</label>
        <select name="province">
          <option value="">All Provinces</option>
          <?php foreach ($provinceOptions as $item): ?>
            <option value="<?php echo owwa_h($item); ?>" <?php echo $province === $item ? 'selected' : ''; ?>><?php echo owwa_h($item); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Date</label>
        <select name="date_mode">
          <option value="">Default</option>
          <option value="created" <?php echo $dateMode === 'created' ? 'selected' : ''; ?>>Created date</option>
          <option value="noa" <?php echo $dateMode === 'noa' ? 'selected' : ''; ?>>NOA date</option>
        </select>
      </div>
      <div class="field">
        <label>Active Filter</label>
        <label class="checkbox-wrap">
          <input type="checkbox" name="today" value="1" <?php echo $todayOnly ? 'checked' : ''; ?>>
          <span>Enrolled only</span>
        </label>
      </div>
      <div class="field">
        <label>Academic Status</label>
        <select name="academic_status">
          <option value="">All Status</option>
          <?php foreach ($academicStatusOptions as $item): ?>
            <option value="<?php echo owwa_h($item); ?>" <?php echo $academicStatus === $item ? 'selected' : ''; ?>><?php echo owwa_h($item); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="actions">
        <button class="btn primary" type="submit">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 3h12M3 8h10M5 13h8"/></svg>
          Generate
        </button>
      </div>
    </form>
  </div>

  <!-- REPORT DOCUMENT PREVIEW -->
  <div class="report-document" id="reportPreview">

    <div class="table-loading" id="tableLoading">
      <div class="spinner"></div>
      <div class="loading-label">Generating report...</div>
    </div>

    <div class="doc-letterhead">
      <div class="doc-letterhead-row">
        <div>
          <div class="doc-eyebrow">Official Report Document</div>
          <h3 class="doc-title"><?php echo owwa_h($reportTitle); ?></h3>
          <div class="doc-subtitle">Scholarship Monitoring · Region IX</div>
        </div>
        <div class="doc-reference">
          <span class="ref-no"><?php echo owwa_h($reportRef); ?></span>
          Issued: <?php echo date('M d, Y'); ?>
        </div>
      </div>
    </div>

    <div class="doc-actions">
      <div class="doc-count"><b><?php echo $total; ?></b> row<?php echo $total === 1 ? '' : 's'; ?> · sorted <?php echo owwa_h(strtolower($dateModeLabel)); ?></div>
      <div class="action-group">
        <button class="btn secondary" onclick="window.print()" type="button">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M4 6V2h8v4M4 12H2v-4h12v4h-2M4 10h8v4H4z"/></svg>
          Print / Save PDF
        </button>
        <a class="btn secondary" href="<?php echo owwa_h($exportUrl); ?>">
          <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M3 2h10v12H3z"/><path d="M3 6h10"/></svg>
          Export (Excel)
        </a>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Last Name</th>
          <th>First Name</th>
          <th>Middle</th>
          <th>Program</th>
          <th>Province</th>
          <th>Year</th>
          <th>Academic</th>
          <th>NOA Date</th>
          <th>Created</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9" style="padding:48px 24px;text-align:center">
            <div style="color:var(--muted);font-family:var(--font-display);font-style:italic;font-size:18px;margin-bottom:8px">No records matched these filters</div>
            <div class="small">Try removing a filter, or <a href="crud.php" style="color:var(--ink);text-decoration:underline">add a new scholar record</a>.</div>
          </td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $detailPayload = [
                'id'              => $row['id'],
                'full_name'       => trim($row['last_name'] . ', ' . $row['first_name'] . ' ' . fmt_middle_initial($row['middle_initial'])),
                'last_name'       => $row['last_name'] ?? '',
                'first_name'      => $row['first_name'] ?? '',
                'middle_initial'  => $row['middle_initial'] ?? '',
                'birthdate'       => fmt_birthdate($row['birthdate'] ?? null),
                'province'        => $row['province'] ?? '',
                'program'         => $row['program'] ?? '',
                'phone'           => $row['phone'] ?? '',
                'year'            => $row['year_applied'] ?? '',
                'life_stage'      => $row['life_stage'] ?: 'Student',
                'academic_status' => $row['academic_status'] ?: 'Maintained',
                'noa_date'        => $row['noa_date'] ? date('M d, Y', strtotime((string)$row['noa_date'])) : '—',
                'created_at'      => $row['created_at'] ? date('M d, Y', strtotime((string)$row['created_at'])) : '—',
              ];
            ?>
            <tr
              class="scholar-row"
              tabindex="0"
              role="button"
              aria-label="Open scholar details"
              data-id="<?php echo owwa_h($detailPayload['id']); ?>"
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
              data-academic-status="<?php echo owwa_h($detailPayload['academic_status']); ?>"
              data-noa-date="<?php echo owwa_h($detailPayload['noa_date']); ?>"
              data-created-at="<?php echo owwa_h($detailPayload['created_at']); ?>"
            >
              <td><?php echo owwa_h($row['last_name']); ?></td>
              <td><?php echo owwa_h($row['first_name']); ?></td>
              <td><?php echo owwa_h(fmt_middle_initial($row['middle_initial'])); ?></td>
              <td><span class="badge gold"><?php echo owwa_h($row['program']); ?></span></td>
              <td><?php echo owwa_h($row['province']); ?></td>
              <td style="font-family:var(--font-mono);font-size:13px;letter-spacing:.05em"><?php echo owwa_h($row['year_applied']); ?></td>
              <td>
                <?php
                  $stat = $row['academic_status'] ?: 'Maintained';
                  $badgeClass = $stat === 'Maintained' ? 'ok'
                              : ($stat === 'Probation' ? 'warn' : 'danger');
                ?>
                <span class="badge <?php echo $badgeClass; ?>"><?php echo owwa_h($stat); ?></span>
              </td>
              <td style="font-family:var(--font-mono);font-size:12px"><?php echo fmt_date($row['noa_date'] ?? null); ?></td>
              <td style="font-family:var(--font-mono);font-size:12px"><?php echo fmt_date($row['created_at'] ?? null); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</main>

<!-- SCHOLAR MODAL -->
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
        <div class="detail-card"><span class="label">NOA Date</span><span class="value" id="scholarModalNoaDate">—</span></div>
        <div class="detail-card" style="grid-column:1/-1"><span class="label">Created</span><span class="value" id="scholarModalCreatedAt">—</span></div>
      </div>
    </div>
  </div>
</div>

<script>
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

// Form submit: save scroll + show loading overlay
(function() {
  var form = document.getElementById('reportFilters');
  var overlay = document.getElementById('tableLoading');
  if (!form) return;
  form.addEventListener('submit', function() {
    try {
      sessionStorage.setItem('owwa-scroll-' + location.pathname, String(window.scrollY || window.pageYOffset || 0));
      sessionStorage.setItem('owwa-loading-' + location.pathname, '1');
      if (overlay) overlay.classList.add('active');
    } catch (e) { /* ignore */ }
  });
})();

// Hide overlay once new page is fully loaded
(function() {
  var overlay = document.getElementById('tableLoading');
  if (!overlay) return;
  if (document.documentElement.dataset.loading === '1') {
    overlay.classList.add('active');
    window.addEventListener('load', function() {
      setTimeout(function() {
        overlay.classList.remove('active');
        delete document.documentElement.dataset.loading;
      }, 200);
    });
  }
})();

// Scholar modal
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
const modalNoaDate = document.getElementById('scholarModalNoaDate');
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
  const noaDate = data.noa_date || data.noaDate || '—';
  const createdAt = data.created_at || data.createdAt || '—';

  // Generic title; clear initials so the avatar stays blank
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
  modalNoaDate.textContent   = noaDate;
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
  openScholarModal(row.dataset);
});

document.addEventListener('keydown', (event) => {
  const row = event.target.closest('tr.scholar-row');
  if (!row) return;
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
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
</script>

<footer class="site-footer">
  <div class="seal">OWWA Region IX · Scholar Monitoring v1.1</div>
  <div>Scholar Reports</div>
</footer>
</body>
</html>