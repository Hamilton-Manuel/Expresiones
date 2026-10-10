<?php
declare(strict_types=1);

// Iniciar PHP antes: php -S 127.0.0.1:8080 -t public
// Ejecutar: php tests/http.php http://127.0.0.1:8080
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!extension_loaded('curl')) {
    fwrite(STDERR, "Estas pruebas opcionales requieren la extensión PHP curl.\n");
    exit(2);
}
$base = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$url = parse_url($base);
if (!in_array($url['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)) {
    fwrite(STDERR, "Las pruebas HTTP se limitan al servidor local.\n");
    exit(2);
}
$cookie = tempnam(sys_get_temp_dir(), 'regexlab_http_');
$csrf = '';
$passed = 0;
$failed = 0;
$createdId = null;
$withDatabase = in_array('--db', $argv, true);

function http(string $path, ?string $body = null, ?string $token = null): array
{
    global $base, $cookie;
    $ch = curl_init($base . $path);
    $headers = ['Accept: application/json'];
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'X-CSRF-Token: ' . ($token ?? '');
    }
    $responseHeaders = [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookie,
        CURLOPT_COOKIEFILE => $cookie,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders): int {
            $responseHeaders[] = trim($line);
            return strlen($line);
        },
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $content = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($content === false) {
        throw new RuntimeException('No se pudo contactar con el servidor local: ' . $error);
    }
    return ['status' => $status, 'body' => $content, 'headers' => implode("\n", $responseHeaders), 'json' => json_decode($content, true)];
}
function check(bool $condition, string $name): void
{
    global $passed, $failed;
    if ($condition) { $passed++; echo "[OK] $name\n"; }
    else { $failed++; echo "[FALLO] $name\n"; }
}
function post(string $action, array $data): array
{
    global $csrf;
    return http('/api.php?action=' . $action, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $csrf);
}

try {
    echo "RegexLab · pruebas HTTP de PHP y seguridad\n\n";
    $page = http('/');
    check($page['status'] === 200 && str_contains($page['body'], 'RegexLab'), 'La página principal devuelve HTML');
    check(str_contains($page['body'], 'Los registros son temporales') && str_contains($page['body'], 'Usa únicamente datos ficticios'), 'La interfaz avisa del almacenamiento temporal');
    check(str_contains($page['headers'], 'Content-Type: text/html; charset=UTF-8'), 'HTML declara UTF-8 aunque php.ini configure otra codificación');
    preg_match('/name="csrf-token" content="([a-f0-9]{64})"/', $page['body'], $match);
    $csrf = $match[1] ?? '';
    check(strlen($csrf) === 64, 'Token CSRF generado en la sesión');
    check(str_contains($page['headers'], 'Content-Security-Policy:') && str_contains($page['headers'], "script-src 'self'"), 'CSP limita scripts al mismo origen');
    check(str_contains($page['headers'], 'HttpOnly') && str_contains($page['headers'], 'SameSite=Strict'), 'Cookie con HttpOnly y SameSite=Strict');
    check(str_contains($page['headers'], 'X-Content-Type-Options: nosniff'), 'Protección nosniff activa');
    foreach (['/assets/css/app.css', '/assets/js/app.js', '/assets/vendor/bootstrap/bootstrap.min.css', '/assets/vendor/bootstrap/bootstrap.bundle.min.js', '/assets/favicon.svg'] as $asset) {
        check(http($asset)['status'] === 200, 'Recurso local disponible: ' . $asset);
    }
    check(http('/app/Database.php')['status'] === 404, 'Código de aplicación fuera del directorio público');
    check(http('/config/config.example.php')['status'] === 404, 'Configuración fuera del directorio público');
    check(http('/database.sql')['status'] === 404, 'SQL fuera del directorio público');
    check(http('/storage/regexlab.sqlite')['status'] === 404, 'Archivo SQLite fuera del directorio público');
    check(http('/api.php?action=save', '{}')['status'] === 403, 'Guardar sin CSRF es rechazado');
    check(http('/api.php?action=delete', '{"id":1}', 'incorrecto')['status'] === 403, 'Eliminar con CSRF incorrecto es rechazado');
    check(http('/api.php?action=constraints', '{"case":"check"}')['status'] === 403, 'Pruebas SQL sin CSRF son rechazadas');
    check(http('/api.php?action=analyze')['status'] === 405, 'Análisis requiere POST');
    check(http('/api.php?action=save')['status'] === 405, 'Guardar por GET es rechazado');
    check(http('/api.php?action=analyze', '{', $csrf)['status'] === 400, 'JSON mal formado recibe error claro');
    check(http('/api.php?action=analyze', 'null', $csrf)['status'] === 400, 'JSON null es rechazado');
    check(post('analyze', ['text' => []])['status'] === 422, 'Texto de tipo incorrecto es rechazado');
    check(post('analyze', ['text' => str_repeat('a', 30001)])['status'] === 422, 'Límite de 30 000 bytes aplicado por el servidor');
    check(http('/api.php?action=analyze', str_repeat('a', 65537), $csrf)['status'] === 413, 'Solicitud demasiado grande es rechazada');
    $person = ['nombre' => 'Persona de prueba', 'telefono' => '+502 5123-4567', 'dpi' => '1234 56789 0101', 'correo' => 'Ana@EXAMPLE.COM'];
    $valid = post('validate', $person);
    check($valid['status'] === 200 && $valid['json']['valid'] === true, 'PHP valida el registro completo');
    check($valid['json']['data']['telefono'] === '51234567' && $valid['json']['data']['dpi'] === '1234567890101' && $valid['json']['data']['correo'] === 'Ana@example.com', 'La API normaliza los tres campos');
    $invalid = post('save', array_replace($person, ['telefono' => '5123456']));
    check($invalid['status'] === 422 && !$invalid['json']['validation']['valid'], 'Guardar dato inválido se rechaza antes de acceder a SQLite');
    $empty = post('save', []);
    check($empty['status'] === 422 && count($empty['json']['validation']['errors']) === 4, 'Campos obligatorios incompletos se rechazan');
    $analysis = post('analyze', ['text' => "<script>alert(1)</script>\nTeléfono: +502 5123-4567\nDPI: 1234 56789 0101\nCorreo: juan@gmail.com\nCorreo: usuario@"]);
    check($analysis['status'] === 200 && $analysis['json']['counts']['valid'] === 3 && $analysis['json']['counts']['invalid'] === 1, 'Extracción y errores etiquetados funcionan por HTTP');
    check(!str_contains($analysis['json']['highlighted'], '<script>') && str_contains($analysis['json']['highlighted'], '&lt;script&gt;'), 'Resaltado HTTP escapa HTML peligroso');
    $anon = post('anonymize', ['text' => 'Hola: juan@gmail.com, 51234567, 1234567890101.']);
    check($anon['status'] === 200 && $anon['json']['anonymized'] === 'Hola: j***@gmail.com, ****4567, *********0101.', 'Anonimización HTTP mantiene el texto restante');
    check(post('constraints', ['case' => 'DROP TABLE personas'])['status'] === 422, 'El panel SQL no permite pruebas arbitrarias');
    check(post('unknown', [])['status'] === 404, 'Acciones desconocidas son rechazadas');
    check(http('/api.php?action[]=records')['status'] === 400, 'Acción con tipo incorrecto es rechazada');
    check(http('/api.php?action=records&q[]=test')['status'] === 400, 'Búsqueda con tipo incorrecto se rechaza antes de conectar');
    if ($withDatabase) {
        $health = http('/health.php');
        check($health['status'] === 200 && $health['json']['database'] === 'sqlite', 'Health check comprueba el esquema SQLite sin crear sesiones');
        check(!str_contains($health['headers'], 'Set-Cookie'), 'Health check no genera cookie de sesión');
        $baseline = http('/api.php?action=records');
        check($baseline['status'] === 200, 'Listado de SQLite disponible');
        if ($baseline['status'] !== 200) throw new RuntimeException('SQLite no está inicializado para probar los registros.');
        foreach (['not_null', 'unique', 'check'] as $case) {
            $constraint = post('constraints', ['case' => $case]);
            check($constraint['status'] === 200 && $constraint['json']['passed'] && $constraint['json']['constraint_type'] === $case && $constraint['json']['rollback'], 'SQLite rechaza ' . $case . ' realmente por HTTP y revierte la transacción');
        }
        $after = http('/api.php?action=records');
        check($after['json'] === $baseline['json'], 'Panel SQL conserva el listado original');
        $data = ['nombre' => 'Registro ficticio HTTP %_!', 'telefono' => '+502 5123-4567', 'dpi' => (string)random_int(1000000000000, 9999999999999), 'correo' => 'Demo.HTTP@EXAMPLE.INVALID'];
        $save = post('save', $data);
        check($save['status'] === 201, 'Guardar por HTTP inserta en SQLite');
        if ($save['status'] !== 201) throw new RuntimeException('No se pudo guardar el registro ficticio de prueba.');
        $createdId = $save['json']['id'];
        $search = http('/api.php?action=records&q=' . rawurlencode($data['dpi']));
        check($search['status'] === 200 && $search['json']['total'] === 1 && $search['json']['records'][0]['telefono'] === '51234567' && $search['json']['records'][0]['correo'] === 'Demo.HTTP@example.invalid', 'Búsqueda devuelve el registro normalizado');
        $literal = http('/api.php?action=records&q=' . rawurlencode('%_!'));
        check($literal['status'] === 200 && count(array_filter($literal['json']['records'], fn(array $row): bool => (int)$row['id'] === $createdId)) === 1, 'Búsqueda HTTP escapa comodines literales');
        $duplicate = post('save', $data);
        check($duplicate['status'] === 409 && str_contains($duplicate['json']['error'], 'ese DPI'), 'DPI duplicado devuelve 409 y un mensaje seguro');
        $inject = http('/api.php?action=records&q=' . rawurlencode("' OR 1=1 --"));
        check($inject['status'] === 200 && $inject['json']['total'] === 0, 'Búsqueda HTTP no permite SQL Injection');
        $delete = post('delete', ['id' => $createdId]);
        check($delete['status'] === 200, 'Eliminación por POST borra solo el registro ficticio creado');
        if ($delete['status'] === 200) $createdId = null;
        $final = http('/api.php?action=records');
        check($final['json'] === $baseline['json'], 'El recorrido HTTP termina con las personas originales intactas');
    }
} catch (Throwable $error) {
    $failed++;
    echo '[FALLO] ' . $error->getMessage() . "\n";
} finally {
    if ($createdId !== null) {
        try { post('delete', ['id' => $createdId]); }
        catch (Throwable $error) { echo "[AVISO] No se pudo limpiar el registro ficticio HTTP.\n"; }
    }
    if (is_file($cookie)) unlink($cookie);
}
echo "\nResultado HTTP: $passed aprobadas, $failed fallidas. " . ($withDatabase ? 'Las pruebas modifican solo una persona ficticia propia y la eliminan al terminar.' : 'No se guardaron registros.') . "\n";
exit($failed > 0 ? 1 : 0);
