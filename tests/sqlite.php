<?php
declare(strict_types=1);

// Módulo del runner: siempre usa archivos nuevos en una carpeta temporal aislada.
if (PHP_SAPI !== 'cli' || !function_exists('test')) {
    exit('Ejecuta php tests/run.php desde consola.');
}

function snapshot(PDO $pdo): array
{
    return $pdo->query('SELECT * FROM personas ORDER BY id')->fetchAll();
}

function assertSqlConstraint(PDO $pdo, array $data, string $type): void
{
    $insert = $pdo->prepare('INSERT INTO personas (nombre, telefono, dpi, correo) VALUES (:nombre, :telefono, :dpi, :correo)');
    try {
        $insert->execute($data);
    } catch (PDOException $error) {
        equal(((int)$error->errorInfo[1] & 255), 19);
        equal(Database::constraintType($error), $type);
        return;
    }
    throw new RuntimeException('SQLite aceptó una operación que debía rechazar.');
}

$directory = sys_get_temp_dir() . '/regexlab-test-' . bin2hex(random_bytes(8));
$path = $directory . '/regexlab.sqlite';
$pdo = null;
try {
    echo "\nIntegración real con SQLite (archivo temporal aislado)\n";
    test('Inicialización crea directorio, esquema y tres ejemplos ficticios', function () use ($path): void {
        equal(DatabaseInitializer::run($path), ['created' => true, 'seeded' => 3]);
        equal(is_file($path), true);
    });
    $pdo = Database::connect($path);
    echo 'Motor: SQLite ' . $pdo->query('SELECT sqlite_version()')->fetchColumn() . "\n";
    test('Foreign keys, busy_timeout y CHECK activos', function () use ($pdo): void {
        equal((int)$pdo->query('PRAGMA foreign_keys')->fetchColumn(), 1);
        equal((int)$pdo->query('PRAGMA busy_timeout')->fetchColumn(), 5000);
        equal((int)$pdo->query('PRAGMA ignore_check_constraints')->fetchColumn(), 0);
        equal($pdo->query('PRAGMA journal_mode')->fetchColumn(), 'wal');
    });
    test('Inicialización repetida no duplica ni modifica registros', function () use ($pdo, $path): void {
        $before = snapshot($pdo);
        equal(DatabaseInitializer::run($path), ['created' => false, 'seeded' => 0]);
        equal(snapshot($pdo), $before);
    });
    test('Registro válido, normalización, fecha UTC y ROLLBACK', function () use ($pdo): void {
        $before = snapshot($pdo);
        $pdo->beginTransaction();
        try {
            $id = (new PersonRepository($pdo))->create(['nombre' => 'Persona ficticia', 'telefono' => '+502 5123-4567', 'dpi' => '0000000000999', 'correo' => 'Test@EXAMPLE.INVALID']);
            $read = $pdo->prepare('SELECT telefono, correo, created_at FROM personas WHERE id = ?');
            $read->execute([$id]);
            $row = $read->fetch();
            equal($row['telefono'], '51234567');
            equal($row['correo'], 'Test@example.invalid');
            equal(preg_match('/\A[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}\z/', $row['created_at']), 1);
        } finally { $pdo->rollBack(); }
        equal(snapshot($pdo), $before);
    });
    foreach (ConstraintLab::CASES as $case => $definition) {
        test('SQLite rechaza ' . $definition['name'] . ' y ROLLBACK conserva filas y secuencia', function () use ($pdo, $case): void {
            $before = snapshot($pdo);
            $sequence = $pdo->query('SELECT * FROM sqlite_sequence')->fetchAll();
            $result = ConstraintLab::run($pdo, $case);
            equal($result['passed'], true);
            equal($result['constraint_type'], $case);
            equal($result['rollback'], true);
            equal($pdo->inTransaction(), false);
            equal(snapshot($pdo), $before);
            equal($pdo->query('SELECT * FROM sqlite_sequence')->fetchAll(), $sequence);
        });
    }
    foreach (['5123456', '5123456789', 'abcd1234', "51234567\n", "51234567\0extra", '５１２３４５６７'] as $phone) {
        test('CHECK rechaza SQL directo: ' . json_encode($phone), function () use ($pdo, $phone): void {
            $pdo->beginTransaction();
            try {
                assertSqlConstraint($pdo, ['nombre' => 'Temporal', 'telefono' => $phone, 'dpi' => '0000000000999', 'correo' => 'test@example.invalid'], 'check');
            } finally { $pdo->rollBack(); }
        });
    }
    test('NOT NULL también rechaza teléfono NULL', function () use ($pdo): void {
        $pdo->beginTransaction();
        try {
            assertSqlConstraint($pdo, ['nombre' => 'Temporal', 'telefono' => null, 'dpi' => '0000000000999', 'correo' => 'test@example.invalid'], 'not_null');
        } finally { $pdo->rollBack(); }
    });
    test('Error de DPI duplicado se traduce sin exponer el SQL', function () use ($pdo): void {
        try {
            (new PersonRepository($pdo))->create(DatabaseInitializer::DEMO[0]);
        } catch (PDOException $error) {
            equal(Database::constraintType($error), 'unique');
            equal(Database::userMessage($error), 'Ya existe un registro con ese DPI. Cada DPI debe ser único.');
            return;
        }
        throw new RuntimeException('UNIQUE no rechazó el DPI de ejemplo duplicado.');
    });
    test('Repositorio impide guardar datos inválidos', function () use ($pdo): void {
        $before = snapshot($pdo);
        try {
            (new PersonRepository($pdo))->create(['nombre' => 'Temporal', 'telefono' => '5123456', 'dpi' => '0000000000999', 'correo' => 'test@example.invalid']);
        } catch (InvalidArgumentException $error) {
            equal(snapshot($pdo), $before);
            return;
        }
        throw new RuntimeException('El repositorio aceptó datos inválidos.');
    });
    test('Búsqueda, comodines literales, SQL Injection y eliminación', function () use ($pdo): void {
        $before = snapshot($pdo);
        $pdo->beginTransaction();
        try {
            $repository = new PersonRepository($pdo);
            $id = $repository->create(['nombre' => 'Demo %_! de prueba', 'telefono' => '51234567', 'dpi' => '0000000000999', 'correo' => 'test@example.invalid']);
            equal($repository->search('0000000000999')['total'], 1);
            equal($repository->search('%_!')['total'], 1);
            equal($repository->search("' OR 1=1 --")['total'], 0);
            equal($repository->delete($id), true);
            equal($repository->delete($id), false);
            equal($repository->search('0000000000999')['total'], 0);
        } finally { $pdo->rollBack(); }
        equal(snapshot($pdo), $before);
    });
    test('Paginar y limitar registros con parámetros enteros', function () use ($pdo): void {
        $pdo->beginTransaction();
        try {
            $repository = new PersonRepository($pdo);
            for ($i = 0; $i < 21; $i++) {
                $repository->create(['nombre' => 'Paginación ficticia', 'telefono' => '51234567', 'dpi' => sprintf('%013d', 1000 + $i), 'correo' => 'test@example.invalid']);
            }
            equal(count($repository->search('Paginación')['records']), 20);
            $second = $repository->search('Paginación', 2);
            equal($second['total'], 21);
            equal(count($second['records']), 1);
            equal($repository->search('Paginación', 999)['page'], 2);
        } finally { $pdo->rollBack(); }
    });
    test('Escrituras concurrentes esperan y se recuperan tras ROLLBACK', function () use ($pdo, $path): void {
        $other = Database::connect($path);
        $other->exec('PRAGMA busy_timeout = 30');
        $pdo->beginTransaction();
        $other->beginTransaction();
        try {
            $data = ['nombre' => 'Temporal', 'telefono' => '51234567', 'dpi' => '0000000000998', 'correo' => 'test@example.invalid'];
            (new PersonRepository($pdo))->create($data);
            $data['dpi'] = '0000000000999';
            $blocked = false;
            try { (new PersonRepository($other))->create($data); }
            catch (PDOException $error) { equal(((int)$error->errorInfo[1] & 255), 5); $blocked = true; }
            equal($blocked, true);
            $pdo->rollBack();
            equal((new PersonRepository($other))->create($data) > 0, true);
        } finally {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ($other->inTransaction()) $other->rollBack();
        }
    });
    test('Conectar a un archivo ausente no lo crea durante una solicitud', function () use ($directory): void {
        $missing = $directory . '/missing.sqlite';
        try { Database::connect($missing); }
        catch (RuntimeException $error) { equal(is_file($missing), false); return; }
        throw new RuntimeException('Se creó una BD fuera de la inicialización.');
    });
    test('Base existente vacía conserva su estado y no repone ejemplos', function () use ($directory): void {
        $empty = $directory . '/empty.sqlite';
        $connection = Database::connect($empty, true);
        $connection->exec(file_get_contents(dirname(__DIR__) . '/database.sql'));
        $connection = null;
        equal(DatabaseInitializer::run($empty), ['created' => false, 'seeded' => 0]);
        equal((int)Database::connect($empty)->query('SELECT COUNT(*) FROM personas')->fetchColumn(), 0);
    });
    test('Un nuevo arranque recrea una base que desapareció', function () use ($directory): void {
        $erased = $directory . '/erased.sqlite';
        DatabaseInitializer::run($erased);
        equal(unlink($erased), true);
        clearstatcache(true, $erased);
        equal(DatabaseInitializer::run($erased), ['created' => true, 'seeded' => 3]);
    });
    test('Las pruebas aisladas conservan los tres registros originales', function () use ($pdo): void {
        equal(count(snapshot($pdo)), 3);
    });
} catch (Throwable $error) {
    $failed++;
    logTechnicalError($error, 'pruebas SQLite');
    echo "[FALLO] No se pudo completar la integración SQLite aislada.\n";
} finally {
    $pdo = null;
    // Solo archivos creados por esta ejecución en un directorio aleatorio privado.
    foreach (glob($directory . '/*') ?: [] as $file) {
        if (is_file($file)) unlink($file);
    }
    if (is_dir($directory)) rmdir($directory);
}
