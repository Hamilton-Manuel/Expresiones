<?php
declare(strict_types=1);
ini_set('display_errors', '0');
require dirname(__DIR__) . '/app/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try {
    Database::connect()->query('SELECT id FROM personas LIMIT 1');
    echo '{"status":"ok","database":"sqlite"}';
} catch (Throwable $error) {
    logTechnicalError($error, 'health check');
    http_response_code(503);
    echo '{"status":"unavailable"}';
}
