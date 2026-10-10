<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$passed = 0;
$failed = 0;
function test(string $name, callable $test): void
{
    global $passed, $failed;
    try {
        $test();
        $passed++;
        echo "[OK] $name\n";
    } catch (Throwable $error) {
        $failed++;
        echo "[FALLO] $name: " . $error->getMessage() . "\n";
    }
}
function equal(mixed $actual, mixed $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException('El resultado no coincide con el esperado.');
    }
}
function found(string $text, string $type): array
{
    return array_values(array_filter(TextLab::analyze($text)['results'], fn(array $r): bool => $r['type'] === $type && $r['valid']));
}

echo "RegexLab · pruebas funcionales PHP\n\n";
foreach (['51234567', '5123-4567', '5123 4567', '+502 5123-4567', '+50251234567', '01234567'] as $value) {
    test("Teléfono válido: $value", fn() => equal(Validation::field('telefono', $value)['valid'], true));
}
foreach (['', '5123456', '512345678', '5123/4567', '5123--4567', '+503 51234567', 'abc51234567', '5123  4567', ' 51234567', "51234567\n", '５１２３４５６７'] as $value) {
    test('Teléfono inválido: ' . json_encode($value), fn() => equal(Validation::field('telefono', $value)['valid'], false));
}
foreach (['1234567890101', '1234 56789 0101', '0000000000000'] as $value) {
    test("DPI válido por formato: $value", fn() => equal(Validation::field('dpi', $value)['valid'], true));
}
foreach (['123456789012', '12345678901234', '1234-56789-0101', '1234 5678 90101', "1234\t56789 0101", '123456789010x', "1234567890101\n"] as $value) {
    test('DPI inválido: ' . json_encode($value), fn() => equal(Validation::field('dpi', $value)['valid'], false));
}
foreach (['juan@gmail.com', 'Ana.Perez+lab@ejemplo.com.gt', 'a_b@sub-dominio.org', 'j@EXAMPLE.COM'] as $value) {
    test("Correo válido: $value", fn() => equal(Validation::field('correo', $value)['valid'], true));
}
foreach (['usuario@', '@gmail.com', 'usuario.com', 'u suario@gmail.com', 'a..b@gmail.com', '.a@gmail.com', 'a@gmail..com', 'a@-gmail.com', 'a@gmail-.com', 'a@@gmail.com', "a@gmail.com\n", str_repeat('a', 65) . '@gmail.com'] as $value) {
    test('Correo inválido: ' . json_encode($value), fn() => equal(Validation::field('correo', $value)['valid'], false));
}
test('Normalizar prefijo y guion del teléfono', fn() => equal(Validation::field('telefono', '+502 5123-4567')['normalized'], '51234567'));
test('Normalizar espacios del DPI', fn() => equal(Validation::field('dpi', '1234 56789 0101')['normalized'], '1234567890101'));
test('Normalizar únicamente el dominio del correo', fn() => equal(Validation::field('correo', 'Ana@EXAMPLE.COM')['normalized'], 'Ana@example.com'));
test('No normalizar un teléfono inválido para volverlo válido', fn() => equal(Validation::field('telefono', '51abc234567')['normalized'], null));
test('Nombre vacío rechazado en PHP', fn() => equal(Validation::person(['nombre' => '  ', 'telefono' => '51234567', 'dpi' => '1234567890101', 'correo' => 'j@example.com'])['valid'], false));
test('Tipos de entrada no escalares rechazados', fn() => equal(Validation::person(['nombre' => [], 'telefono' => [], 'dpi' => [], 'correo' => []])['valid'], false));

$sample = "Nombre: Juan Pérez\nTeléfono: +502 5123-4567\nDPI: 1234 56789 0101\nCorreo: juan.perez@gmail.com\n\nNombre: María López\nTeléfono: 5234 5678\nDPI: 2345 67890 0101\nCorreo: maria@example.com";
test('Extraer dos personas sin agruparlas automáticamente', function () use ($sample): void {
    $result = TextLab::analyze($sample);
    equal(count($result['results']), 6);
    equal($result['counts'], ['telefono' => 2, 'dpi' => 2, 'correo' => 2, 'valid' => 6, 'invalid' => 0]);
    equal(isset($result['person']), false);
});
test('Reconocer etiquetas con líneas CRLF de Windows', function () use ($sample): void {
    equal(TextLab::analyze(str_replace("\n", "\r\n", $sample))['counts']['valid'], 6);
});
test('Reconocer etiquetas en una sola línea', fn() => equal(TextLab::analyze('Teléfono: 51234567 DPI: 1234567890101 Correo: a@example.com')['counts']['valid'], 3));
test('Reconocer etiquetas separadas por coma o punto y coma', fn() => equal(TextLab::analyze('Teléfono: 51234567, DPI: 1234567890101; Correo: a@example.com')['counts']['valid'], 3));
test('Reportar los tres datos etiquetados incorrectos', function (): void {
    $result = TextLab::analyze("Teléfono: 5123456\nDPI: 12345678\nCorreo: usuario@");
    equal($result['counts']['invalid'], 3);
    equal($result['counts']['valid'], 0);
});
test('No rescatar un correo válido dentro de un campo etiquetado inválido', fn() => equal(TextLab::analyze('Correo: texto usuario@gmail.com extra')['counts']['valid'], 0));
test('Reportar etiquetas sin valores', fn() => equal(TextLab::analyze("Teléfono:\nDPI:\nCorreo:")['counts']['invalid'], 3));
test('DPI continuo no produce un teléfono', function (): void { equal(count(found('1234567890101', 'dpi')), 1); equal(found('1234567890101', 'telefono'), []); });
test('DPI agrupado no produce un teléfono', function (): void { equal(count(found('1234 56789 0101', 'dpi')), 1); equal(found('1234 56789 0101', 'telefono'), []); });
test('Números continuos largos no producen fragmentos', fn() => equal(TextLab::analyze('12345678901234567890')['counts']['valid'], 0));
test('Bloque numérico largo agrupado no produce fragmentos', fn() => equal(TextLab::analyze('51234567 9012345')['counts']['valid'], 0));
test('Grupos ambiguos de cuatro dígitos no producen teléfonos', fn() => equal(TextLab::analyze('1234 5678 9012')['counts']['valid'], 0));
test('No recuperar un grupo final incrustado en otro dato', fn() => equal(TextLab::analyze('abc1234 51234567')['counts']['valid'], 0));
test('No recuperar prefijo de un bloque con sufijo incorrecto', fn() => equal(TextLab::analyze('51234567 9012345x')['counts']['valid'], 0));
test('No extraer números incrustados en palabras o decimales', fn() => equal(TextLab::analyze('abc51234567 51234567xyz 51234567.90')['counts']['valid'], 0));
test('No extraer teléfono o DPI del usuario de un correo', function (): void { $r = TextLab::analyze('51234567@example.com 1234567890101@example.com'); equal($r['counts']['correo'], 2); equal($r['counts']['telefono'], 0); equal($r['counts']['dpi'], 0); });
test('Múltiples teléfonos libres separados por coma', fn() => equal(count(found('51234567, 5234-5678; +502 5345 6789', 'telefono')), 3));
test('Valores completos consecutivos separados por espacio', fn() => equal(TextLab::analyze('51234567 52345678 1234567890101')['counts']['valid'], 3));
test('Puntuación final no impide detectar un teléfono', fn() => equal(count(found('Llame al 51234567.', 'telefono')), 1));
test('Múltiples correos válidos y un correo inválido', function (): void { $r = TextLab::analyze('a@example.com, b@example.org; usuario@'); equal($r['counts']['valid'], 2); equal($r['counts']['invalid'], 1); });
test('No extraer fragmentos de un correo mal formado', fn() => equal(TextLab::analyze('abc/juan@gmail.com')['counts']['valid'], 0));
test('Coincidencias repetidas permanecen separadas', fn() => equal(count(found("51234567\n51234567", 'telefono')), 2));
test('Anonimización exacta sin cambiar texto ajeno', function (): void {
    $text = "Hola Juan: juan@gmail.com, 51234567 y 1234567890101. ¡Gracias!";
    equal(TextLab::analyze($text)['anonymized'], "Hola Juan: j***@gmail.com, ****4567 y *********0101. ¡Gracias!");
});
test('Anonimización normaliza formato de las coincidencias', fn() => equal(TextLab::analyze("Teléfono: +502 5123-4567\nDPI: 1234 56789 0101")['anonymized'], "Teléfono: ****4567\nDPI: *********0101"));
test('Anonimización no toca un número inválido con el mismo fragmento', fn() => equal(TextLab::analyze('51234567; 5123456789')['anonymized'], '****4567; 5123456789'));
test('Anonimización conserva dominio original y texto Unicode', fn() => equal(TextLab::analyze('¡José! Ana@EXAMPLE.COM')['anonymized'], '¡José! A***@EXAMPLE.COM'));
test('Anonimización deja los valores etiquetados inválidos intactos', fn() => equal(TextLab::analyze('DPI: 51234567')['anonymized'], 'DPI: 51234567'));
test('Texto sin datos permanece intacto', fn() => equal(TextLab::analyze('Hola, mundo. <b>Saludos</b>')['anonymized'], 'Hola, mundo. <b>Saludos</b>'));
test('Resaltado escapa XSS y preserva caracteres Unicode', function (): void {
    $html = TextLab::analyze('<script>alert(1)</script> José: 51234567')['highlighted'];
    equal(str_contains($html, '<script>'), false);
    equal(str_contains($html, '&lt;script&gt;'), true);
    equal(str_contains($html, '<mark class="match-telefono">51234567</mark>'), true);
    equal(str_contains($html, 'José'), true);
});
test('Resaltado y offsets respetan acentos antes de los datos', fn() => equal(TextLab::analyze('María: 51234567')['highlighted'], 'María: <mark class="match-telefono">51234567</mark>'));
test('Límite de tamaño del texto aplicado en PHP', function (): void {
    try { TextLab::analyze(str_repeat('a', 30001)); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Se aceptó un texto demasiado grande.');
});
test('Texto UTF-8 inválido rechazado', function (): void {
    try { TextLab::analyze("\xFF"); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Se aceptó UTF-8 inválido.');
});

$pending = false;
if (!in_array('--regex-only', $argv, true)) {
    if (in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        require __DIR__ . '/sqlite.php';
    } else {
        echo "\n[PENDIENTE] Activa pdo_sqlite para ejecutar las pruebas de integración.\n";
        $pending = true;
    }
} else {
    echo "\n[PENDIENTE] Solo se ejecutó Regex. Omite --regex-only para probar SQLite.\n";
}
echo "\nResultado: $passed aprobadas, $failed fallidas" . ($pending ? ', integración pendiente' : '') . ".\n";
exit($failed > 0 ? 1 : ($pending ? 2 : 0));
