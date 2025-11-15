<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['message' => 'Sadece POST isteklerine izin verilir.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = null;

$constants = require __DIR__ . '/config/constants.php';
$workshopCatalog = $constants['workshops'] ?? [];
$discountCatalog = $constants['discounts'] ?? [];
$priceConfig = $constants['pricing'] ?? [];

function json_response(int $status, string $message, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge(['message' => $message], $extra), JSON_UNESCAPED_UNICODE);
    exit;
}

function normalize_string(string $value): string
{
    return trim(strip_tags($value));
}

function calculate_payable_total(
    string $format,
    array $selectedWorkshopCodes,
    array $selectedDiscounts,
    array $workshopCatalog,
    array $priceConfig
): array {
    if (!isset($priceConfig['format'][$format])) {
        throw new RuntimeException('Geçersiz kongre formatı seçildi.', 422);
    }

    $formatTotal = $priceConfig['format'][$format];
    $workshopTotal = 0;

    foreach ($selectedWorkshopCodes as $code) {
        $type = $workshopCatalog[$code]['type'] ?? null;
        if (!$type || !isset($priceConfig['workshop_types'][$type])) {
            throw new RuntimeException('Atölye ücreti hesaplanamadı. Lütfen seçimlerinizi kontrol ediniz.', 422);
        }
        $workshopTotal += $priceConfig['workshop_types'][$type];
    }

    $subtotal = $formatTotal + $workshopTotal;
    $discountCount = count($selectedDiscounts);
    $discountMultiplier = max(0, 1 - ($discountCount * $priceConfig['discount_step']));
    $payable = round($subtotal * $discountMultiplier, 2);
    $discountAmount = max(0, $subtotal - $payable);

    return [
        'format_total' => $formatTotal,
        'workshop_total' => $workshopTotal,
        'discount_amount' => $discountAmount,
        'payable' => $payable,
    ];
}

function handle_upload(string $field, string $directory, int $maxSize): ?string
{
    if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES[$field];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Dosya yüklenirken bir hata meydana geldi.', 422);
    }

    if ($file['size'] > $maxSize) {
        throw new RuntimeException('Yüklediğiniz dosya 2MB sınırını aşıyor.', 422);
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if ($mime !== 'application/pdf') {
        throw new RuntimeException('Lütfen yalnızca PDF formatında dosya yükleyin.', 422);
    }

    if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
        throw new RuntimeException('Dosya dizini oluşturulamadı.', 500);
    }

    $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
    $targetName = sprintf('%s-%s.pdf', $field, uniqid($safeName ?: 'belge', true));
    $targetPath = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $targetName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Dosya kaydedilemedi.', 500);
    }

    return str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $targetPath);
}

function load_database_config(): array
{
    $path = __DIR__ . '/config/database.php';
    if (!file_exists($path)) {
        throw new RuntimeException('Veritabanı yapılandırma dosyası bulunamadı.', 500);
    }
    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('Veritabanı yapılandırması geçersiz.', 500);
    }
    return $config;
}

function load_mail_config(): array
{
    $path = __DIR__ . '/config/mail.php';
    if (!file_exists($path)) {
        throw new RuntimeException('SMTP yapılandırma dosyası bulunamadı.', 500);
    }

    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('SMTP yapılandırma dosyası geçersiz.', 500);
    }

    return $config;
}

function send_notification_email(
    array $config,
    string $recipient,
    string $subject,
    string $body,
    string $replyEmail,
    string $replyName = ''
): void {
    $requiredKeys = ['host', 'port', 'username', 'password', 'from_email'];
    foreach ($requiredKeys as $key) {
        if (empty($config[$key])) {
            throw new RuntimeException(sprintf('SMTP yapılandırması eksik: %s', $key), 500);
        }
    }

    $mailer = new PHPMailer(true);
    try {
        $mailer->CharSet = 'UTF-8';
        $mailer->isSMTP();
        $mailer->Host = (string) $config['host'];
        $mailer->Port = (int) ($config['port'] ?? 587);
        $mailer->SMTPAuth = (bool) ($config['auth'] ?? true);
        $mailer->Username = (string) $config['username'];
        $mailer->Password = (string) $config['password'];

        $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
        if ($encryption === 'ssl') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mailer->SMTPSecure = '';
        }

        $fromName = $config['from_name'] ?? 'Kongre Kayıt';
        $mailer->setFrom((string) $config['from_email'], $fromName);
        $mailer->addAddress($recipient);

        if (!empty($replyEmail)) {
            $mailer->addReplyTo($replyEmail, $replyName ?: $replyEmail);
        } elseif (!empty($config['reply_to'])) {
            $mailer->addReplyTo((string) $config['reply_to'], $fromName);
        }

        $mailer->Subject = $subject;
        $mailer->Body = $body;
        $mailer->AltBody = $body;

        $mailer->send();
    } catch (PHPMailerException $exception) {
        throw new RuntimeException('E-posta gönderimi başarısız: ' . $exception->getMessage(), 500, $exception);
    }
}

try {
    $fullName = normalize_string($_POST['full_name'] ?? '');
    $phone = normalize_string($_POST['phone'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL) ? strtolower(trim($_POST['email'])) : '';
    $profession = normalize_string($_POST['profession'] ?? '');
    $format = $_POST['format'] ?? '';
    $notes = normalize_string($_POST['notes'] ?? '');
    $kvkk = isset($_POST['kvkk']);

    if ($fullName === '' || $phone === '' || $email === '') {
        throw new RuntimeException('Lütfen tüm zorunlu alanları doldurun.', 422);
    }

    if ($format !== 'yuz-yuze' && $format !== 'online') {
        throw new RuntimeException('Lütfen kongre formatını seçin.', 422);
    }

    if (!$kvkk) {
        throw new RuntimeException('KVKK onayı olmadan kayıt tamamlanamaz.', 422);
    }

    $selectedDiscounts = $_POST['discounts'] ?? [];
    if (!is_array($selectedDiscounts)) {
        $selectedDiscounts = [];
    }
    $selectedDiscounts = array_values(array_intersect($selectedDiscounts, array_keys($discountCatalog)));

    $studentRequiresProof = in_array('student', $selectedDiscounts, true);
    $whrRequiresProof = in_array('whr', $selectedDiscounts, true);

    $studentProofPath = handle_upload('student_proof', __DIR__ . '/uploads/student_docs', 2 * 1024 * 1024);
    $whrProofPath = handle_upload('whr_proof', __DIR__ . '/uploads/whr_docs', 2 * 1024 * 1024);

    if ($studentRequiresProof && $studentProofPath === null) {
        throw new RuntimeException('Öğrenci indirimi için öğrenci belgesi yüklenmelidir.', 422);
    }
    if ($whrRequiresProof && $whrProofPath === null) {
        throw new RuntimeException('WHR sertifika indirimi için belge yüklenmelidir.', 422);
    }

    $selectedWorkshopCodes = $_POST['workshops'] ?? [];
    if (!is_array($selectedWorkshopCodes)) {
        $selectedWorkshopCodes = [];
    }
    $selectedWorkshopCodes = array_values(array_unique(array_filter($selectedWorkshopCodes, fn ($code) => isset($workshopCatalog[$code]))));

    $slotUsage = [];
    foreach ($selectedWorkshopCodes as $code) {
        $slotKey = $workshopCatalog[$code]['slot'];
        if (isset($slotUsage[$slotKey])) {
            throw new RuntimeException('Aynı saat diliminde yalnızca bir atölye seçebilirsiniz.', 422);
        }
        $slotUsage[$slotKey] = $code;
    }

    $priceDetails = calculate_payable_total($format, $selectedWorkshopCodes, $selectedDiscounts, $workshopCatalog, $priceConfig);
    $calculatedTotal = $priceDetails['payable'];

    $dbConfig = load_database_config();

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $dbConfig['host'], $dbConfig['name'], $dbConfig['charset']);
    $pdo = new PDO($dsn, $dbConfig['user'], $dbConfig['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pdo->beginTransaction();

    if ($selectedWorkshopCodes) {
        $placeholders = implode(',', array_fill(0, count($selectedWorkshopCodes), '?'));
        $capacityStmt = $pdo->prepare(
            "SELECT workshop_code, COUNT(*) AS total
             FROM kongre_registration_workshops
             WHERE workshop_code IN ($placeholders)
             GROUP BY workshop_code
             FOR UPDATE"
        );
        $capacityStmt->execute($selectedWorkshopCodes);
        $capacity = [];
        foreach ($capacityStmt as $row) {
            $capacity[$row['workshop_code']] = (int) $row['total'];
        }
        foreach ($selectedWorkshopCodes as $code) {
            $current = $capacity[$code] ?? 0;
            if ($current >= 20) {
                throw new RuntimeException(sprintf('%s atölyesinin kontenjanı dolmuştur.', $workshopCatalog[$code]['title']), 409);
            }
        }
    }

    $insertRegistration = $pdo->prepare('INSERT INTO kongre_registrations
        (full_name, phone, email, profession, format, discounts, student_proof, whr_proof, payment_amount, payment_confirmed, notes)
        VALUES (:full_name, :phone, :email, :profession, :format, :discounts, :student_proof, :whr_proof, :payment_amount, :payment_confirmed, :notes)');

    $insertRegistration->execute([
        ':full_name' => $fullName,
        ':phone' => $phone,
        ':email' => $email,
        ':profession' => $profession ?: null,
        ':format' => $format,
        ':discounts' => $selectedDiscounts ? json_encode($selectedDiscounts, JSON_UNESCAPED_UNICODE) : null,
        ':student_proof' => $studentProofPath,
        ':whr_proof' => $whrProofPath,
        ':payment_amount' => $calculatedTotal,
        ':payment_confirmed' => 0,
        ':notes' => $notes ?: null,
    ]);

    $registrationId = (int) $pdo->lastInsertId();

    if ($selectedWorkshopCodes) {
        $insertWorkshop = $pdo->prepare('INSERT INTO kongre_registration_workshops (registration_id, workshop_code, workshop_title, slot_key, workshop_type) VALUES (:registration_id, :workshop_code, :workshop_title, :slot_key, :workshop_type)');
        foreach ($selectedWorkshopCodes as $code) {
            $insertWorkshop->execute([
                ':registration_id' => $registrationId,
                ':workshop_code' => $code,
                ':workshop_title' => $workshopCatalog[$code]['title'],
                ':slot_key' => $workshopCatalog[$code]['slot'],
                ':workshop_type' => $workshopCatalog[$code]['type'],
            ]);
        }
    }

    $pdo->commit();

    $recipient = 'utoc-ko@worldhumanrelief.org';
    $subject = sprintf('Yeni Kongre Kaydı: %s', $fullName);
    $workshopLines = [];
    foreach ($selectedWorkshopCodes as $code) {
        $workshopLines[] = sprintf('- %s (%s)', $workshopCatalog[$code]['title'], $workshopCatalog[$code]['slot']);
    }
    $emailBody = "Ad Soyad: $fullName\n" .
        "Telefon: $phone\n" .
        "E-posta: $email\n" .
        "Meslek: $profession\n" .
        "Format: $format\n" .
        "İndirimler: " . ($selectedDiscounts ? implode(', ', array_map(fn($code) => $discountCatalog[$code] ?? $code, $selectedDiscounts)) : 'Yok') . "\n" .
        "Format Ücreti: " . number_format($priceDetails['format_total'], 2, ',', '.') . " TL\n" .
        "Atölye Ücretleri: " . number_format($priceDetails['workshop_total'], 2, ',', '.') . " TL\n" .
        "İndirim Toplamı: " . number_format($priceDetails['discount_amount'], 2, ',', '.') . " TL\n" .
        "Ödenecek Tutar: " . number_format($calculatedTotal, 2, ',', '.') . " TL\n" .
        "Atölyeler:\n" . ($workshopLines ? implode("\n", $workshopLines) : '- Seçilmedi -') . "\n\n" .
        "Notlar:\n" . ($notes ?: '-');

    $mailConfig = load_mail_config();
    send_notification_email($mailConfig, $recipient, $subject, $emailBody, $email, $fullName);

    json_response(200, 'Kaydınız başarıyla alınmıştır.', ['total_amount' => $calculatedTotal]);
} catch (RuntimeException $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $code = $exception->getCode();
    if ($code < 400 || $code > 599) {
        $code = 400;
    }
    json_response($code, $exception->getMessage());
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    json_response(500, 'Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyiniz.');
}
