<?php
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

function owwa_h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function owwa_render_error_page(string $title, string $message, ?Throwable $error = null): void
{
    http_response_code(500);
    $debug = $error ? '<pre style="margin-top:16px;white-space:pre-wrap;word-break:break-word;background:#0c1b2d;color:#dce7f4;border-radius:14px;padding:14px 16px;">' . owwa_h($error->getMessage()) . '</pre>' : '';
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>' . owwa_h($title) . '</title><style>body{margin:0;font-family:Arial,Helvetica,sans-serif;background:linear-gradient(180deg,#f8fafc 0%,#eef3f7 100%);color:#17324d}main{max-width:900px;margin:48px auto;padding:24px;background:#fff;border:1px solid #d9e1ea;border-radius:18px;box-shadow:0 10px 24px rgba(16,38,63,.04)}h1{margin:0 0 12px;color:#0a2540}p{margin:0;line-height:1.6;color:#637489}a{color:#0a2540}</style></head><body><main><h1>' . owwa_h($title) . '</h1><p>' . owwa_h($message) . '</p>' . $debug . '<p style="margin-top:16px"><a href="main.html">Return to dashboard</a></p></main></body></html>';
    exit;
}

set_exception_handler(function (Throwable $error): void {
    owwa_render_error_page('Application error', 'The application could not complete the request. Check the database connection and try again.', $error);
});

function owwa_connection(): PDO
{
    $host = 'localhost';
    $database = 'owwa_scholarship_ledger';
    $username = 'root';
    $password = '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};charset={$charset}";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `{$database}`");

    return $pdo;
}

function owwa_ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS scholars_crud (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            last_name VARCHAR(80) NOT NULL,
            first_name VARCHAR(80) NOT NULL,
            middle_initial VARCHAR(5) DEFAULT '',
            birthdate DATE NULL,
            province VARCHAR(120) NOT NULL,
            program VARCHAR(30) NOT NULL,
            year_applied YEAR NOT NULL,
            life_stage VARCHAR(40) NOT NULL DEFAULT 'Student',
            phone VARCHAR(40) NULL,
            work_mode VARCHAR(3) NOT NULL DEFAULT 'SB',
            noa_date DATE NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_name (last_name, first_name),
            INDEX idx_program (program),
            INDEX idx_province (province),
            INDEX idx_year (year_applied),
            INDEX idx_life_stage (life_stage)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    try {
        // Ensure `id` is AUTO_INCREMENT when the table was created from external SQL
        // (some seed SQL creates the table without AUTO_INCREMENT). Fix it safely.
        $maxId = (int)$pdo->query("SELECT COALESCE(MAX(id), 0) FROM scholars_crud")->fetchColumn();
        $nextAuto = $maxId > 0 ? $maxId + 1 : 1;
        $pdo->exec("ALTER TABLE scholars_crud MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY");
        $pdo->exec("ALTER TABLE scholars_crud AUTO_INCREMENT = " . (int)$nextAuto);

        $hasYear = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='scholars_crud' AND column_name='year_applied'")->fetchColumn();
        if (! $hasYear) {
            $pdo->exec("ALTER TABLE scholars_crud ADD COLUMN year_applied YEAR NULL AFTER program");
        }
        $pdo->exec("UPDATE scholars_crud SET year_applied = YEAR(created_at) WHERE year_applied IS NULL OR year_applied = ''");
        $pdo->exec("ALTER TABLE scholars_crud MODIFY year_applied YEAR NOT NULL");

        $hasBirthdate = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='scholars_crud' AND column_name='birthdate'")->fetchColumn();
        if (! $hasBirthdate) {
            $pdo->exec("ALTER TABLE scholars_crud ADD COLUMN birthdate DATE NULL AFTER middle_initial");
        }

        $hasNoa = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='scholars_crud' AND column_name='noa_date'")->fetchColumn();
        if (! $hasNoa) {
            $pdo->exec("ALTER TABLE scholars_crud ADD COLUMN noa_date DATE NULL AFTER year_applied");
        }

        $hasPhone = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='scholars_crud' AND column_name='phone'")->fetchColumn();
        if (! $hasPhone) {
            $pdo->exec("ALTER TABLE scholars_crud ADD COLUMN phone VARCHAR(40) NULL AFTER province");
        }

        $hasWork = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='scholars_crud' AND column_name='work_mode'")->fetchColumn();
        if (! $hasWork) {
            $pdo->exec("ALTER TABLE scholars_crud ADD COLUMN work_mode VARCHAR(3) NOT NULL DEFAULT 'SB' AFTER phone");
        }

        $hasLife = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='scholars_crud' AND column_name='life_stage'")->fetchColumn();
        if (! $hasLife) {
            $pdo->exec("ALTER TABLE scholars_crud ADD COLUMN life_stage VARCHAR(40) NOT NULL DEFAULT 'Student' AFTER year_applied");
        }
                     // Normalize existing life_stage values to the simplified set: Student, Working, Graduated, Terminated
                $pdo->exec(
                    "UPDATE scholars_crud
                     SET life_stage = CASE
                        WHEN life_stage IN ('Withdrawn', 'Inactive', 'Dropped', 'Dropped Out') THEN 'Terminated'
                                WHEN life_stage IN ('Graduated', 'Graduate') THEN 'Graduated'
                                WHEN life_stage IN ('Employed', 'Postgraduate', 'Working', 'Alumni', 'Entrepreneur') THEN 'Working'
                                WHEN life_stage IN ('Board Review', 'On Probation', 'Pending', 'Under Review', 'Academic Recovery', 'Active Student', 'Active') THEN 'Student'
                        ELSE 'Student'
                     END
                            WHERE life_stage IS NULL OR life_stage = '' OR life_stage NOT IN ('Student','Working','Graduated','Terminated')"
                );
                     // Normalize work_mode values to canonical 'SB' or 'LB'
                     $pdo->exec(
                          "UPDATE scholars_crud
                            SET work_mode = CASE
                                WHEN LOWER(work_mode) IN ('sea','sea-based','sb','s','sea_based') THEN 'SB'
                                WHEN LOWER(work_mode) IN ('land','land-based','lb','l','land_based') THEN 'LB'
                                ELSE 'SB'
                            END
                            WHERE work_mode IS NULL OR work_mode = '' OR work_mode NOT IN ('SB','LB')"
                     );
    } catch (Throwable $error) {
        // Ignore migration syntax differences on older servers; the table still works with the existing columns.
    }
}

function owwa_bootstrap(): PDO
{
    $pdo = owwa_connection();
    owwa_ensure_schema($pdo);
    return $pdo;
}
