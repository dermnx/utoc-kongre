<?php
session_start();

$constants = require __DIR__ . '/config/constants.php';
$dbConfig = require __DIR__ . '/config/database.php';

$workshopCatalog = $constants['workshops'] ?? [];
$discountCatalog = $constants['discounts'] ?? [];

const ADMIN_PASSWORD_SALT = 'whr-admin-2026-salt';

function hash_admin_password(string $password): string
{
    return hash('sha256', ADMIN_PASSWORD_SALT . $password);
}

function load_admin_user(PDO $pdo, string $username): ?array
{
    try {
        $stmt = $pdo->prepare('SELECT id, username, password_hash FROM kongre_admins WHERE username = :username LIMIT 1');
        $stmt->execute([':username' => $username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    } catch (PDOException $exception) {
        return null;
    }
}

function render_login_view(string $errorMessage = ''): void
{
    ?>
    <!doctype html>
    <html lang="tr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Admin Girişi | UTÖÇ-KO 2026</title>
        <style>
            :root {
                --teal: #2d7c69;
                --dark: #1e3f3a;
            }
            * { box-sizing: border-box; }
            body {
                margin: 0;
                font-family: 'Roboto', 'Helvetica Neue', Arial, sans-serif;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: linear-gradient(135deg, #dfeee8, #f7faf9 45%, #e4eff5);
                padding: 40px 16px;
                color: #1f2d2b;
            }
            .login-card {
                width: 100%;
                max-width: 420px;
                background: #fff;
                border-radius: 20px;
                padding: 36px 34px;
                box-shadow: 0 30px 70px rgba(20, 60, 50, 0.18);
                border: 1px solid rgba(45, 124, 105, 0.18);
            }
            .login-logo {
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 12px;
            }
            .login-logo img {
                max-width: 180px;
                height: auto;
                display: block;
            }
            .login-card p {
                text-align: center;
                color: #60736e;
                font-size: 14px;
                margin: 6px 0 24px;
            }
            .login-card label {
                display: block;
                margin-bottom: 8px;
                font-size: 12px;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                color: #50615e;
            }
            .login-card input[type="text"],
            .login-card input[type="password"] {
                width: 100%;
                padding: 12px 14px;
                border-radius: 12px;
                border: 1px solid #c7d9d3;
                font-size: 15px;
                margin-bottom: 18px;
            }
            .login-card button {
                width: 100%;
                border: none;
                background: var(--teal);
                color: #fff;
                padding: 14px 16px;
                border-radius: 14px;
                font-size: 14px;
                letter-spacing: 0.2em;
                text-transform: uppercase;
                cursor: pointer;
                box-shadow: 0 18px 30px rgba(45, 124, 105, 0.35);
            }
            .login-error {
                background: #fde8e8;
                color: #a32323;
                border: 1px solid #f6c5c5;
                padding: 10px 14px;
                border-radius: 10px;
                margin-bottom: 18px;
                font-size: 14px;
            }
            .login-hint {
                font-size: 12px;
                color: #6c7b77;
                margin-top: 18px;
                text-align: center;
            }
        </style>
    </head>
    <body>
    <div class="login-card">
        <div class="login-logo">
            <img src="img/Logo.png" alt="UTÖÇ-KO 2026">
        </div>
        <p>Kayıt paneline erişmek için giriş yapın.</p>
        <?php if ($errorMessage): ?>
            <div class="login-error"><?= htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <label for="username">Kullanıcı Adı</label>
            <input type="text" id="username" name="username" autocomplete="username" required>
            <label for="password">Şifre</label>
            <input type="password" id="password" name="password" autocomplete="current-password" required>
            <button type="submit">Giriş Yap</button>
        </form>
    </div>
    </body>
    </html>
    <?php
}

$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $dbConfig['host'], $dbConfig['name'], $dbConfig['charset']);
$pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$loginError = '';
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    if ($username === '' || $password === '') {
        $loginError = 'Kullanıcı adı ve şifre zorunludur.';
    } else {
        $adminRow = load_admin_user($pdo, $username);
        $incomingHash = hash_admin_password($password);
        if ($adminRow && hash_equals($adminRow['password_hash'], $incomingHash)) {
            $_SESSION['admin'] = [
                'id' => (int) $adminRow['id'],
                'username' => $adminRow['username'],
            ];
            header('Location: admin.php');
            exit;
        }
        if (!$adminRow && $username === 'admin' && hash_equals(hash_admin_password('2r59043raSfewrV_*'), $incomingHash)) {
            $_SESSION['admin'] = [
                'id' => 0,
                'username' => 'admin',
            ];
            header('Location: admin.php');
            exit;
        }
        $loginError = 'Kullanıcı adı veya şifre hatalı.';
    }
    render_login_view($loginError);
    exit;
}

if (empty($_SESSION['admin'])) {
    render_login_view('');
    exit;
}

$currentAdmin = $_SESSION['admin'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update-payment') {
    $regId = isset($_POST['registration_id']) ? (int) $_POST['registration_id'] : 0;
    $newStatus = ($_POST['confirmed'] ?? '') === '1' ? 1 : 0;
    $redirectStatus = 'payment-error';

    if ($regId > 0) {
        $updateStmt = $pdo->prepare('UPDATE kongre_registrations SET payment_confirmed = :confirmed WHERE id = :id');
        $updateStmt->execute([
            ':confirmed' => $newStatus,
            ':id' => $regId,
        ]);
        if ($updateStmt->rowCount() > 0) {
            $redirectStatus = 'payment-updated';
        }
    }

    $redirectQuery = $_GET;
    $redirectQuery['status'] = $redirectStatus;
    header('Location: admin.php?' . http_build_query(array_filter($redirectQuery, 'admin_filter_query_value')));
    exit;
}

$formatFilter = $_GET['format'] ?? '';
if (!in_array($formatFilter, ['yuz-yuze', 'online'], true)) {
    $formatFilter = '';
}

$workshopFilter = $_GET['workshop'] ?? '';
if ($workshopFilter && !array_key_exists($workshopFilter, $workshopCatalog)) {
    $workshopFilter = '';
}

$searchQuery = trim($_GET['q'] ?? '');
$paymentFilter = $_GET['payment'] ?? '';
if (!in_array($paymentFilter, ['confirmed', 'pending'], true)) {
    $paymentFilter = '';
}

$viewOptions = [
    'overview' => 'Genel Bakış',
    'workshops' => 'Atölye Stoğu',
    'registrations' => 'Kayıtlar',
];
$currentView = $_GET['view'] ?? 'overview';
if (!array_key_exists($currentView, $viewOptions)) {
    $currentView = 'overview';
}

$flashMessage = '';
$flashType = '';
$statusParam = $_GET['status'] ?? '';
if ($statusParam === 'payment-updated') {
    $flashMessage = 'Ödeme onayı güncellendi.';
    $flashType = 'success';
    unset($_GET['status']);
} elseif ($statusParam === 'payment-error') {
    $flashMessage = 'Ödeme onayı güncellenirken bir hata oluştu.';
    $flashType = 'error';
    unset($_GET['status']);
}

$sortMap = [
    'created_at' => 'kr.created_at',
    'full_name' => 'kr.full_name',
    'payment_amount' => 'kr.payment_amount',
    'format' => 'kr.format',
    'payment_confirmed' => 'kr.payment_confirmed',
];
$sortParam = $_GET['sort'] ?? 'created_at';
$sortColumn = $sortMap[$sortParam] ?? $sortMap['created_at'];
$direction = strtolower($_GET['direction'] ?? 'desc');
$direction = $direction === 'asc' ? 'ASC' : 'DESC';

$whereParts = [];
$params = [];
if ($formatFilter) {
    $whereParts[] = 'kr.format = :format';
    $params[':format'] = $formatFilter;
}
if ($searchQuery) {
    $whereParts[] = '(kr.full_name LIKE :search OR kr.email LIKE :search OR kr.phone LIKE :search)';
    $params[':search'] = '%' . $searchQuery . '%';
}
if ($workshopFilter) {
    $whereParts[] = 'EXISTS (SELECT 1 FROM kongre_registration_workshops kw WHERE kw.registration_id = kr.id AND kw.workshop_code = :workshop)';
    $params[':workshop'] = $workshopFilter;
}
if ($paymentFilter === 'confirmed') {
    $whereParts[] = 'kr.payment_confirmed = 1';
} elseif ($paymentFilter === 'pending') {
    $whereParts[] = 'kr.payment_confirmed = 0';
}
$whereSql = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$sql = "SELECT kr.id, kr.full_name, kr.phone, kr.email, kr.profession, kr.format, kr.discounts, kr.payment_amount, kr.payment_confirmed, kr.notes, kr.created_at
        FROM kongre_registrations kr
        $whereSql
        ORDER BY $sortColumn $direction";
$registrationsStmt = $pdo->prepare($sql);
$registrationsStmt->execute($params);
$registrations = $registrationsStmt->fetchAll();

$registrationIds = array_column($registrations, 'id');
$registrationWorkshops = [];
if ($registrationIds) {
    $placeholders = implode(',', array_fill(0, count($registrationIds), '?'));
    $stmt = $pdo->prepare("SELECT registration_id, workshop_title FROM kongre_registration_workshops WHERE registration_id IN ($placeholders)");
    $stmt->execute($registrationIds);
    foreach ($stmt as $row) {
        $registrationWorkshops[(int) $row['registration_id']][] = $row['workshop_title'];
    }
}

$totalStats = $pdo->query('SELECT COUNT(*) AS total_regs, COALESCE(SUM(payment_amount),0) AS total_amount, SUM(payment_confirmed) AS confirmed_payments, COALESCE(SUM(CASE WHEN payment_confirmed = 1 THEN payment_amount ELSE 0 END),0) AS confirmed_amount FROM kongre_registrations')->fetch();
$confirmedPaymentsCount = (int) ($totalStats['confirmed_payments'] ?? 0);
$pendingPaymentsCount = max(0, (int) ($totalStats['total_regs'] ?? 0) - $confirmedPaymentsCount);
$confirmedPaymentsAmount = (float) ($totalStats['confirmed_amount'] ?? 0);
$pendingPaymentsAmount = max(0.0, (float) ($totalStats['total_amount'] ?? 0) - $confirmedPaymentsAmount);
$formatStatsStmt = $pdo->query('SELECT format, COUNT(*) AS total FROM kongre_registrations GROUP BY format');
$formatStats = [];
foreach ($formatStatsStmt as $row) {
    $formatStats[$row['format']] = (int) $row['total'];
}
$formatChartLabels = [];
$formatChartValues = [];
foreach (['yuz-yuze' => 'Yüz Yüze', 'online' => 'Online'] as $formatKey => $label) {
    $formatChartLabels[] = $label;
    $formatChartValues[] = (int) ($formatStats[$formatKey] ?? 0);
}
$workshopCountsStmt = $pdo->query('SELECT workshop_code, COUNT(*) AS total FROM kongre_registration_workshops GROUP BY workshop_code');
$workshopCounts = [];
foreach ($workshopCountsStmt as $row) {
    $workshopCounts[$row['workshop_code']] = (int) $row['total'];
}
$totalWorkshopSelections = array_sum($workshopCounts);
$totalWorkshopCapacity = count($workshopCatalog) * 20;
$workshopOccupancyPercent = $totalWorkshopCapacity > 0 ? round(($totalWorkshopSelections / $totalWorkshopCapacity) * 100) : 0;
$totalRegistrationCount = (int) ($totalStats['total_regs'] ?? 0);
$confirmedPercent = $totalRegistrationCount > 0 ? round(($confirmedPaymentsCount / $totalRegistrationCount) * 100) : 0;
$pendingPercent = max(0, 100 - $confirmedPercent);

$recentRegistrationsStmt = $pdo->query('SELECT id, full_name, format, payment_confirmed, payment_amount, created_at FROM kongre_registrations ORDER BY created_at DESC LIMIT 6');
$recentRegistrations = $recentRegistrationsStmt->fetchAll();

function format_currency(float $value): string
{
    return number_format($value, 2, ',', '.') . ' TL';
}

function translate_format(string $format): string
{
    return $format === 'yuz-yuze' ? 'Yüz Yüze' : 'Online';
}

function render_discounts(?string $json, array $catalog): string
{
    if (!$json) {
        return '—';
    }
    $codes = json_decode($json, true) ?: [];
    if (!$codes) {
        return '—';
    }
    $labels = [];
    foreach ($codes as $code) {
        $labels[] = $catalog[$code] ?? $code;
    }
    return implode(', ', $labels);
}

function format_payment_status(int $value): string
{
    return $value === 1 ? 'Onaylandı' : 'Beklemede';
}

function build_query(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    return http_build_query(array_filter($query, 'admin_filter_query_value'));
}

function admin_filter_query_value($value)
{
    return $value !== '' && $value !== null;
}

$export = $_GET['export'] ?? '';
if ($export === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="kayitlar-' . date('Ymd-His') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Ad Soyad', 'E-posta', 'Telefon', 'Format', 'Atölyeler', 'İndirimler', 'Tutar', 'Ödeme Durumu', 'Tarih']);
    foreach ($registrations as $registration) {
        $regId = (int) $registration['id'];
        $workshops = $registrationWorkshops[$regId] ?? [];
        fputcsv($output, [
            $registration['full_name'],
            $registration['email'],
            $registration['phone'],
            translate_format($registration['format']),
            implode(' | ', $workshops),
            render_discounts($registration['discounts'], $discountCatalog),
            number_format((float) $registration['payment_amount'], 2, ',', '.'),
            format_payment_status((int) $registration['payment_confirmed']),
            date('d.m.Y H:i', strtotime($registration['created_at'])),
        ]);
    }
    fclose($output);
    exit;
}

if ($export === 'pdf') {
    require_once __DIR__ . '/vendor/fpdf.php';
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'UTÖÇ-KO 2026 Kayıt Listesi', 0, 1, 'C');
    $pdf->Ln(4);
    $pdf->SetFont('Arial', '', 11);
    foreach ($registrations as $registration) {
        $regId = (int) $registration['id'];
        $workshops = $registrationWorkshops[$regId] ?? [];
        $line = sprintf(
            "%s | %s | %s | %s | %s | %s",
            $registration['full_name'],
            $registration['email'],
            $registration['phone'],
            translate_format($registration['format']),
            implode(' / ', $workshops),
            format_currency((float) $registration['payment_amount'])
        );
        $pdf->MultiCell(0, 8, $line);
        $pdf->SetFont('Arial', 'I', 10);
        $pdf->MultiCell(
            0,
            6,
            'İndirimler: ' . render_discounts($registration['discounts'], $discountCatalog) .
            ' | Ödeme: ' . format_payment_status((int) $registration['payment_confirmed']) .
            ' | Tarih: ' . date('d.m.Y H:i', strtotime($registration['created_at']))
        );
        $pdf->Ln(2);
        $pdf->SetFont('Arial', '', 11);
    }
    $pdf->Output('D', 'kayitlar-' . date('Ymd-His') . '.pdf');
    exit;
}
?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Kayıt Paneli | UTÖÇ-KO 2026</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --teal: #2d7c69;
            --teal-light: #d5f0e7;
            --grey: #3b4a48;
            --border: #d9e4df;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            background: #f3f7f6;
            color: #1f2d2b;
        }
        header {
            background: linear-gradient(135deg, #2d7c69, #1f5244);
            color: #fff;
            padding: 28px;
            box-shadow: 0 12px 32px rgba(11, 32, 27, 0.25);
        }
        header h1 { margin: 0; font-size: 26px; letter-spacing: 0.05em; }
        .header-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
        }
        .admin-user-meta {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }
        .logout-link {
            padding: 6px 14px;
            border-radius: 999px;
            border: 1px solid rgba(255,255,255,0.7);
            color: #fff;
            text-decoration: none;
            font-size: 12px;
            letter-spacing: 0.1em;
        }
.container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 30px 20px 60px;
}
.admin-shell {
    display: flex;
    flex-direction: column;
    gap: 24px;
}
.admin-tabs {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 10px;
    background: #fff;
    padding: 12px;
    border-radius: 999px;
    border: 1px solid var(--border);
    box-shadow: 0 12px 32px rgba(16, 50, 46, 0.08);
}
.admin-tabs a {
    text-decoration: none;
    font-size: 13px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 10px 20px;
    border-radius: 999px;
    border: 1px solid transparent;
    color: var(--grey);
    transition: all 0.2s ease;
}
.admin-tabs a:hover {
    border-color: var(--teal);
    color: var(--teal);
}
.admin-tabs a.is-active {
    background: var(--teal);
    color: #fff;
    border-color: var(--teal);
    box-shadow: 0 10px 20px rgba(45, 124, 105, 0.35);
}
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 10px 25px rgba(22, 60, 52, 0.08);
        }
        .stat-card h3 {
            margin: 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.15em;
            color: #627470;
        }
.stat-card strong {
    display: block;
    margin-top: 10px;
    font-size: 28px;
    color: var(--teal);
}
.stat-card small {
    display: block;
    margin-top: 4px;
    font-size: 12px;
    color: #7a8c88;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.section-block {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 24px;
    margin-bottom: 30px;
    box-shadow: 0 10px 25px rgba(22, 60, 52, 0.06);
}
.chart-block {
    text-align: center;
}
.chart-block canvas {
    max-width: 420px;
    margin: 0 auto;
}
.dual-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 20px;
}
.insights-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 18px;
}
.insight-card {
    background: #f9fbfb;
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 18px 20px;
}
.insight-card h3 {
    margin: 0;
    font-size: 12px;
    letter-spacing: 0.18em;
    text-transform: uppercase;
    color: #627470;
}
.insight-metric {
    font-size: 32px;
    font-weight: 700;
    color: var(--grey);
    margin-top: 10px;
}
.insight-meta {
    font-size: 13px;
    color: #70837f;
    margin-top: 6px;
}
.flash {
    padding: 14px 16px;
    border-radius: 8px;
    margin-bottom: 18px;
    font-weight: 500;
        }
        .flash.success {
            background: #def5ec;
            color: #1f6b4f;
            border: 1px solid #b9e5d2;
        }
        .flash.error {
            background: #fde8e8;
            color: #a32323;
            border: 1px solid #f6c5c5;
        }
.section-block h2 {
    margin: 0 0 18px;
    font-size: 18px;
    letter-spacing: 0.05em;
    color: var(--grey);
}
.recent-list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.recent-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
    padding: 12px 0;
    border-bottom: 1px solid #edf3f0;
}
.recent-item:last-child {
    border-bottom: none;
}
.recent-item strong {
    font-size: 15px;
    color: var(--grey);
}
.recent-item span {
    font-size: 12px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #7f8e8a;
}
.recent-item small {
    font-size: 12px;
    color: #a0b2ac;
}
.progress-track {
    width: 100%;
    height: 12px;
    border-radius: 999px;
    background: #e4f2ed;
    overflow: hidden;
    margin: 16px 0 8px;
}
.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #2d7c69, #5fb69e);
    border-radius: inherit;
}
.distribution-bars {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.distribution-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.distribution-row span {
    font-size: 14px;
    color: var(--grey);
}
.distribution-row strong {
    font-size: 16px;
    color: var(--teal);
}
.empty-indicator {
    padding: 20px;
    text-align: center;
    color: #6e7d79;
    background: #f7fbfa;
    border-radius: 10px;
    border: 1px dashed #c6dcd4;
}
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        th, td {
            border: 1px solid #e4ece8;
            padding: 10px 12px;
            text-align: left;
        }
        th {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 12px;
            background: #f0f6f4;
            color: #4c5c58;
        }
        tbody tr { background: #fff; }
        tbody tr:nth-child(odd) { background: #fbfdfc; }
        .format-chip {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 14px;
            font-size: 12px;
            letter-spacing: 0.08em;
            background: #eaf2f0;
        }
        .filter-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: flex-end;
        }
        .filter-form label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #4a5a57;
        }
        .filter-form select,
        .filter-form input[type="search"] {
            padding: 10px 12px;
            border: 1px solid #cfdad5;
            border-radius: 6px;
            min-width: 180px;
        }
        .filter-form button,
        .export-actions button,
        .export-actions a {
            background: var(--teal);
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 10px 18px;
            text-decoration: none;
            cursor: pointer;
            letter-spacing: 0.08em;
        }
        .filter-form a.clear-link {
            background: transparent;
            color: var(--teal);
            border: 1px solid var(--teal);
        }
        .table-scroll {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
            box-shadow: inset 0 0 0 1px rgba(45, 124, 105, 0.05);
        }
        .table-scroll table {
            min-width: 760px;
            margin: 0;
        }
        .payment-status {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.05em;
        }
        .payment-status--confirmed {
            background: var(--teal-light);
            color: var(--teal);
        }
        .payment-status--pending {
            background: #fcefe5;
            color: #b45d1d;
        }
        .payment-toggle {
            margin-top: 8px;
        }
        .payment-toggle button {
            padding: 6px 12px;
            font-size: 12px;
        }
        .export-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        @media (min-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }
        @media (max-width: 768px) {
            header { text-align: center; }
            table, th, td { font-size: 13px; }
            .section-block { padding: 18px; }
            .filter-form { flex-direction: column; align-items: stretch; }
            .filter-form select,
            .filter-form input,
            .filter-form button,
            .filter-form .clear-link {
                width: 100%;
            }
            .table-scroll {
                border-radius: 10px;
            }
            .admin-tabs {
                border-radius: 18px;
                padding: 10px;
                justify-content: center;
            }
            .admin-tabs a {
                flex: 1 1 calc(50% - 10px);
                text-align: center;
            }
            .dual-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 520px) {
            .payment-toggle button {
                width: 100%;
            }
            .payment-toggle {
                width: 100%;
            }
            .stat-card strong {
                font-size: 22px;
            }
            .admin-tabs a {
                flex: 1 1 100%;
            }
        }
    </style>
</head>
<body>
<header>
    <div class="header-top">
        <h1>UTÖÇ-KO 2026 Kayıt Paneli</h1>
        <div class="admin-user-meta">
            <span><?= htmlspecialchars($currentAdmin['username']); ?></span>
            <a class="logout-link" href="?logout=1">Çıkış</a>
        </div>
    </div>
</header>
<div class="container admin-shell">
    <nav class="admin-tabs">
        <?php foreach ($viewOptions as $viewKey => $label): ?>
            <a class="<?= $currentView === $viewKey ? 'is-active' : ''; ?>" href="?<?= build_query(['view' => $viewKey]); ?>"><?= htmlspecialchars($label); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($flashMessage): ?>
        <div class="flash <?= $flashType; ?>"><?= htmlspecialchars($flashMessage); ?></div>
    <?php endif; ?>

    <?php if ($currentView === 'overview'): ?>
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Toplam Kayıt</h3>
                <strong><?= $totalRegistrationCount; ?></strong>
                <small>kayıt</small>
            </div>
            <div class="stat-card">
                <h3>Onaylı Ödeme</h3>
                <strong><?= $confirmedPaymentsCount; ?></strong>
                <small><?= $confirmedPercent; ?>% tamamlandı</small>
            </div>
            <div class="stat-card">
                <h3>Bekleyen Ödeme</h3>
                <strong><?= $pendingPaymentsCount; ?></strong>
                <small><?= $pendingPercent; ?>% açık</small>
            </div>
            <div class="stat-card">
                <h3>Toplam Tahsilat</h3>
                <strong><?= format_currency((float) ($totalStats['total_amount'] ?? 0)); ?></strong>
                <small>brüt</small>
            </div>
            <div class="stat-card">
                <h3>Onaylı Tutar</h3>
                <strong><?= format_currency($confirmedPaymentsAmount); ?></strong>
                <small>net</small>
            </div>
        </div>

        <div class="dual-grid">
            <div class="section-block">
                <h2>Ödeme Sağlığı</h2>
                <p><?= $confirmedPaymentsCount; ?> / <?= $totalRegistrationCount; ?> kaydın ödemesi onaylandı.</p>
                <div class="progress-track" aria-hidden="true">
                    <div class="progress-fill" style="width: <?= $confirmedPercent; ?>%;"></div>
                </div>
                <div class="insights-grid">
                    <div class="insight-card">
                        <h3>Onaylı Tutar</h3>
                        <div class="insight-metric"><?= format_currency($confirmedPaymentsAmount); ?></div>
                        <div class="insight-meta"><?= $confirmedPercent; ?>% tahsil edildi</div>
                    </div>
                    <div class="insight-card">
                        <h3>Bekleyen Tutar</h3>
                        <div class="insight-metric"><?= format_currency($pendingPaymentsAmount); ?></div>
                        <div class="insight-meta"><?= $pendingPaymentsCount; ?> kayıttan onay bekleniyor</div>
                    </div>
                </div>
            </div>
            <div class="section-block">
                <h2>Format Dağılımı</h2>
                <div class="distribution-bars">
                    <?php foreach (['yuz-yuze' => 'Yüz Yüze', 'online' => 'Online'] as $formatKey => $label): 
                        $count = $formatStats[$formatKey] ?? 0;
                        $ratio = $totalRegistrationCount > 0 ? round(($count / $totalRegistrationCount) * 100) : 0;
                        ?>
                        <div class="distribution-row">
                            <span><?= $label; ?></span>
                            <div style="text-align:right;">
                                <strong><?= $count; ?> kişi</strong><br>
                                <small><?= $ratio; ?>%</small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="section-block chart-block">
            <h2>Katılımcı Grafiği</h2>
            <canvas id="formatChart" height="220"></canvas>
        </div>

        <div class="section-block">
            <h2>Son Kayıtlar</h2>
            <?php if ($recentRegistrations): ?>
                <ul class="recent-list">
                    <?php foreach ($recentRegistrations as $recent): ?>
                        <li class="recent-item">
                            <div>
                                <strong><?= htmlspecialchars($recent['full_name']); ?></strong><br>
                                <small><?= date('d.m.Y H:i', strtotime($recent['created_at'])); ?></small>
                            </div>
                            <div>
                                <span class="format-chip"><?= translate_format($recent['format']); ?></span><br>
                                <small><?= format_currency((float) $recent['payment_amount']); ?></small>
                            </div>
                            <div>
                                <span class="payment-status <?= $recent['payment_confirmed'] ? 'payment-status--confirmed' : 'payment-status--pending'; ?>">
                                    <?= format_payment_status((int) $recent['payment_confirmed']); ?>
                                </span>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <div class="empty-indicator">Henüz kayıt bulunamadı.</div>
            <?php endif; ?>
        </div>
    <?php elseif ($currentView === 'workshops'): ?>
        <div class="insights-grid">
            <div class="insight-card">
                <h3>Toplam Atölye</h3>
                <div class="insight-metric"><?= count($workshopCatalog); ?></div>
                <!-- <div class="insight-meta">aktif slot</div> -->
            </div>
            <div class="insight-card">
                <h3>Toplam Seçim</h3>
                <div class="insight-metric"><?= $totalWorkshopSelections; ?></div>
                <!-- <div class="insight-meta">katılımcı tercihleri</div> -->
            </div>
            <div class="insight-card">
                <h3>Ortalama Doluluk</h3>
                <div class="insight-metric"><?= $workshopOccupancyPercent; ?>%</div>
                <div class="insight-meta">20 kişilik kontenjana göre</div>
            </div>
        </div>
        <div class="section-block">
            <h2>Atölye Stok Durumu</h2>
            <div class="table-scroll" style="margin-top:20px;">
                <table>
                    <thead>
                    <tr>
                        <th>Atölye Adı</th>
                        <th>Tür</th>
                        <th>Kayıtlı</th>
                        <th>Kalan Kontenjan</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($workshopCatalog as $code => $info):
                        $registered = $workshopCounts[$code] ?? 0;
                        $remaining = max(0, 20 - $registered);
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($info['title']); ?></td>
                            <td><?= $info['type'] === 'clinical' ? 'Klinik' : 'Yaşantısal'; ?></td>
                            <td><?= $registered; ?></td>
                            <td><?= $remaining; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php elseif ($currentView === 'registrations'): ?>
        <div class="section-block">
            <h2>Kayıt Listesi</h2>
            <form method="get" class="filter-form">
                <input type="hidden" name="view" value="registrations">
                <div>
                    <label for="format">Format</label><br>
                    <select name="format" id="format">
                        <option value="">Tümü</option>
                        <option value="yuz-yuze" <?= $formatFilter === 'yuz-yuze' ? 'selected' : ''; ?>>Yüz Yüze</option>
                        <option value="online" <?= $formatFilter === 'online' ? 'selected' : ''; ?>>Online</option>
                    </select>
                </div>
                <div>
                    <label for="workshop">Atölye</label><br>
                    <select name="workshop" id="workshop">
                        <option value="">Tümü</option>
                        <?php foreach ($workshopCatalog as $code => $info): ?>
                            <option value="<?= $code; ?>" <?= $workshopFilter === $code ? 'selected' : ''; ?>><?= htmlspecialchars($info['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="q">Arama</label><br>
                    <input type="search" id="q" name="q" value="<?= htmlspecialchars($searchQuery); ?>" placeholder="Ad, e-posta, telefon">
                </div>
                <div>
                    <label for="payment">Ödeme</label><br>
                    <select name="payment" id="payment">
                        <option value="">Tümü</option>
                        <option value="confirmed" <?= $paymentFilter === 'confirmed' ? 'selected' : ''; ?>>Onaylandı</option>
                        <option value="pending" <?= $paymentFilter === 'pending' ? 'selected' : ''; ?>>Beklemede</option>
                    </select>
                </div>
                <div>
                    <button type="submit">Filtrele</button>
                    <a class="clear-link" href="admin.php?view=registrations">Temizle</a>
                </div>
            </form>
            <div class="export-actions">
                <a href="?<?= build_query(['export' => 'csv', 'view' => 'registrations']); ?>">Excel (CSV) Aktar</a>
                <a href="?<?= build_query(['export' => 'pdf', 'view' => 'registrations']); ?>">PDF Aktar</a>
            </div>
            <div class="table-scroll">
                <table>
                    <thead>
                    <tr>
                        <th><a href="?<?= build_query(['sort' => 'full_name', 'direction' => $sortParam === 'full_name' && $direction === 'ASC' ? 'desc' : 'asc', 'view' => 'registrations']); ?>">Ad Soyad</a></th>
                        <th>İletişim</th>
                        <th><a href="?<?= build_query(['sort' => 'format', 'direction' => $sortParam === 'format' && $direction === 'ASC' ? 'desc' : 'asc', 'view' => 'registrations']); ?>">Format</a></th>
                        <th>Atölyeler</th>
                        <th>İndirimler</th>
                        <th><a href="?<?= build_query(['sort' => 'payment_amount', 'direction' => $sortParam === 'payment_amount' && $direction === 'ASC' ? 'desc' : 'asc', 'view' => 'registrations']); ?>">Tutar</a></th>
                        <th><a href="?<?= build_query(['sort' => 'payment_confirmed', 'direction' => $sortParam === 'payment_confirmed' && $direction === 'ASC' ? 'desc' : 'asc', 'view' => 'registrations']); ?>">Ödeme</a></th>
                        <th><a href="?<?= build_query(['sort' => 'created_at', 'direction' => $sortParam === 'created_at' && $direction === 'ASC' ? 'desc' : 'asc', 'view' => 'registrations']); ?>">Tarih</a></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!$registrations): ?>
                        <tr><td colspan="8">Filtreye uygun kayıt bulunamadı.</td></tr>
                    <?php else: ?>
                        <?php foreach ($registrations as $registration):
                            $regId = (int) $registration['id'];
                            $workshops = $registrationWorkshops[$regId] ?? [];
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($registration['full_name']); ?></strong><br>
                                    <span class="notes">Meslek: <?= htmlspecialchars($registration['profession'] ?? '—'); ?></span>
                                </td>
                                <td>
                                    <?= htmlspecialchars($registration['email']); ?><br>
                                    <span class="notes"><?= htmlspecialchars($registration['phone']); ?></span>
                                </td>
                                <td><span class="format-chip"><?= translate_format($registration['format']); ?></span></td>
                                <td>
                                    <?php if ($workshops): ?>
                                        <ul style="margin:0; padding-left:18px;">
                                            <?php foreach ($workshops as $title): ?>
                                                <li><?= htmlspecialchars($title); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td><?= render_discounts($registration['discounts'], $discountCatalog); ?></td>
                                <td><?= format_currency((float) $registration['payment_amount']); ?></td>
                                <td>
                                    <span class="payment-status <?= $registration['payment_confirmed'] ? 'payment-status--confirmed' : 'payment-status--pending'; ?>">
                                        <?= format_payment_status((int) $registration['payment_confirmed']); ?>
                                    </span>
                                    <form method="post" class="payment-toggle">
                                        <input type="hidden" name="action" value="update-payment">
                                        <input type="hidden" name="registration_id" value="<?= $regId; ?>">
                                        <input type="hidden" name="confirmed" value="<?= $registration['payment_confirmed'] ? '0' : '1'; ?>">
                                        <input type="hidden" name="view" value="registrations">
                                        <button type="submit"><?= $registration['payment_confirmed'] ? 'Beklemeye Al' : 'Ödemeyi Onayla'; ?></button>
                                    </form>
                                </td>
                                <td><?= date('d.m.Y H:i', strtotime($registration['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php if ($currentView === 'overview'): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
(function () {
    if (typeof Chart === 'undefined') {
        return;
    }
    const ctx = document.getElementById('formatChart');
    if (!ctx) {
        return;
    }
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($formatChartLabels, JSON_UNESCAPED_UNICODE); ?>,
            datasets: [{
                label: 'Katılımcı',
                data: <?= json_encode($formatChartValues); ?>,
                backgroundColor: ['#2d7c69', '#63c7b2'],
                borderWidth: 0,
                hoverOffset: 8
            }]
        },
        options: {
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: '#4b5a58',
                        padding: 16
                    }
                }
            },
            cutout: '60%'
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>
