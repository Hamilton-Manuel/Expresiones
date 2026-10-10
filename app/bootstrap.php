<?php
declare(strict_types=1);

require_once __DIR__ . '/Regex.php';
require_once __DIR__ . '/Validation.php';
require_once __DIR__ . '/TextLab.php';
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/DatabaseInitializer.php';
require_once __DIR__ . '/PersonRepository.php';
require_once __DIR__ . '/ConstraintLab.php';

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function startWebSession(): void
{
    ini_set('display_errors', '0');
    ini_set('default_charset', 'UTF-8');
    ini_set('session.use_strict_mode', '1');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (getenv('REGEXLAB_TRUST_PROXY') === '1' && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'samesite' => 'Strict',
        'path' => '/',
    ]);
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: DENY');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    header('Cache-Control: no-store');
}

/** Nunca registra credenciales, SQL, texto de análisis ni datos personales. */
function logTechnicalError(Throwable $error, string $context): void
{
    $driver = $error instanceof PDOException ? ($error->errorInfo[1] ?? '-') : '-';
    error_log(sprintf('[RegexLab] %s: %s code=%s driver=%s', $context, get_class($error), $error->getCode(), $driver));
}
