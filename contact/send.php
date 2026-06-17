<?php
declare(strict_types=1);

// =======================================================
// Contact form handler - Écuries du Grand Vignoble
// Charge les credentials depuis /.env (jamais commit)
// SMTP STARTTLS natif (port 587), pas de dépendance externe
// =======================================================

const REDIRECT_OK    = '/contact/?status=success';
const REDIRECT_ERR   = '/contact/?status=error';

function redirect(string $url): void {
    header("Location: $url");
    exit;
}

function loadEnv(string $path): void {
    if (!is_readable($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$key, $value] = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);
        if (preg_match('/^(["\'])(.*)\1$/', $value, $m)) {
            $value = $m[2];
        }
        if (getenv($key) === false) {
            putenv("$key=$value");
        }
    }
}

function sanitizeHeader(string $value): string {
    return trim(str_replace(["\r", "\n", "\0"], '', $value));
}

// ---- Méthode ----
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/contact/');
}

// ---- Honeypot ----
if (!empty($_POST['website'] ?? '')) {
    redirect(REDIRECT_OK);
}

// ---- Validation ----
$name    = trim((string)($_POST['name']    ?? ''));
$email   = trim((string)($_POST['email']   ?? ''));
$phone   = trim((string)($_POST['phone']   ?? ''));
$message = trim((string)($_POST['message'] ?? ''));

if ($name === '' || strlen($name) > 100)                     redirect(REDIRECT_ERR);
if (!filter_var($email, FILTER_VALIDATE_EMAIL))              redirect(REDIRECT_ERR);
if (strlen($email) > 200)                                    redirect(REDIRECT_ERR);
if (strlen($phone) > 50)                                     redirect(REDIRECT_ERR);
if ($message === '' || strlen($message) > 5000)              redirect(REDIRECT_ERR);

$email = sanitizeHeader($email);
$name  = sanitizeHeader($name);

// ---- Charger .env ----
loadEnv(__DIR__ . '/../.env');

$host = (string) getenv('SMTP_HOST');
$port = (int)  (getenv('SMTP_PORT') ?: 587);
$user = (string) getenv('SMTP_USER');
$pass = (string) getenv('SMTP_PASS');
$from = (string) getenv('SMTP_FROM');
$recipients = array_values(array_filter([
    (string) getenv('SMTP_TO_PRIMARY'),
    (string) getenv('SMTP_TO_SECONDARY'),
]));

if ($host === '' || $user === '' || $pass === '' || $from === '' || $recipients === []) {
    error_log('SMTP config manquante');
    redirect(REDIRECT_ERR);
}

// ---- SMTP STARTTLS ----
$fp = @stream_socket_client("tcp://$host:$port", $errno, $errstr, 15);
if (!$fp) {
    error_log("SMTP connexion échouée: $errstr ($errno)");
    redirect(REDIRECT_ERR);
}

stream_set_timeout($fp, 15);

$readResponse = function() use ($fp): array {
    $lines = '';
    $code  = 0;
    while (!feof($fp)) {
        $line = fgets($fp, 1024);
        if ($line === false) break;
        $lines .= $line;
        if (strlen($line) >= 4 && $line[3] === ' ') {
            $code = (int) substr($line, 0, 3);
            break;
        }
    }
    return [$code, $lines];
};

$send = function(string $cmd) use ($fp): void {
    fwrite($fp, $cmd . "\r\n");
};

$expect = function(int $expected) use ($readResponse, $fp): bool {
    [$code, $resp] = $readResponse();
    if ($code !== $expected) {
        error_log("SMTP attendu $expected reçu $code: " . trim($resp));
        return false;
    }
    return true;
};

try {
    if (!$expect(220)) throw new RuntimeException('greeting');

    $ehloHost = $_SERVER['HTTP_HOST'] ?? 'ecuriesdugrandvignoble.com';
    $send("EHLO $ehloHost");
    if (!$expect(250)) throw new RuntimeException('ehlo-1');

    $send("STARTTLS");
    if (!$expect(220)) throw new RuntimeException('starttls');

    if (!stream_socket_enable_crypto(
        $fp,
        true,
        STREAM_CRYPTO_METHOD_TLS_CLIENT
        | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
        | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT
    )) {
        throw new RuntimeException('tls-upgrade');
    }

    $send("EHLO $ehloHost");
    if (!$expect(250)) throw new RuntimeException('ehlo-2');

    $send("AUTH LOGIN");
    if (!$expect(334)) throw new RuntimeException('auth-init');

    $send(base64_encode($user));
    if (!$expect(334)) throw new RuntimeException('auth-user');

    $send(base64_encode($pass));
    if (!$expect(235)) throw new RuntimeException('auth-pass');

    $send("MAIL FROM:<$from>");
    if (!$expect(250)) throw new RuntimeException('mail-from');

    foreach ($recipients as $rcpt) {
        $send("RCPT TO:<$rcpt>");
        [$rcptCode, $rcptResp] = $readResponse();
        if ($rcptCode !== 250 && $rcptCode !== 251) {
            error_log("SMTP RCPT refusé pour $rcpt: " . trim($rcptResp));
            throw new RuntimeException('rcpt');
        }
    }

    $send("DATA");
    if (!$expect(354)) throw new RuntimeException('data');

    $subject = '=?UTF-8?B?' . base64_encode("Nouveau message du site, $name") . '?=';
    $fromName = '=?UTF-8?B?' . base64_encode('Écuries du Grand Vignoble') . '?=';
    $date = date('r');

    $body = "Nouveau message reçu depuis le site ecuriesdugrandvignoble.com\r\n"
          . str_repeat('-', 60) . "\r\n\r\n"
          . "Nom         : $name\r\n"
          . "Email       : $email\r\n"
          . "Téléphone   : " . ($phone !== '' ? $phone : 'non renseigné') . "\r\n\r\n"
          . "Message :\r\n"
          . str_repeat('-', 60) . "\r\n"
          . $message . "\r\n";

    $headers  = "From: $fromName <$from>\r\n";
    $headers .= "To: " . implode(', ', $recipients) . "\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Date: $date\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "Content-Transfer-Encoding: 8bit\r\n";
    $headers .= "X-Mailer: ecuriesdugrandvignoble.com\r\n";

    // RFC 5321 : neutraliser une ligne commençant par "." dans le corps
    $bodyEscaped = preg_replace("/^\\./m", '..', $body);

    fwrite($fp, $headers . "\r\n" . $bodyEscaped . "\r\n.\r\n");
    if (!$expect(250)) throw new RuntimeException('end-data');

    $send("QUIT");
    fclose($fp);

    redirect(REDIRECT_OK);
} catch (Throwable $e) {
    error_log('SMTP fail: ' . $e->getMessage());
    if (is_resource($fp)) {
        @fwrite($fp, "QUIT\r\n");
        @fclose($fp);
    }
    redirect(REDIRECT_ERR);
}
