<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
ini_set('display_errors', '0');
try {
    $result = DatabaseInitializer::run();
    echo $result['created']
        ? "[RegexLab] SQLite inicializado: {$result['seeded']} registros ficticios de demostración.\n"
        : "[RegexLab] SQLite existente: registros conservados, sin repetir ejemplos.\n";
} catch (Throwable $error) {
    logTechnicalError($error, 'inicialización SQLite');
    fwrite(STDERR, "[RegexLab] No se pudo inicializar SQLite. Verifica pdo_sqlite y los permisos de storage/.\n");
    exit(1);
}
