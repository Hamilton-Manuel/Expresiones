<?php
declare(strict_types=1);

final class DatabaseInitializer
{
    public const DEMO = [
        ['nombre' => 'Persona Demo Alfa', 'telefono' => '50000001', 'dpi' => '0000000000001', 'correo' => 'demo.alfa@example.invalid'],
        ['nombre' => 'Persona Demo Beta', 'telefono' => '50000002', 'dpi' => '0000000000002', 'correo' => 'demo.beta@example.invalid'],
        ['nombre' => 'Persona Demo Gamma', 'telefono' => '50000003', 'dpi' => '0000000000003', 'correo' => 'demo.gamma@example.invalid'],
    ];

    public static function run(?string $path = null): array
    {
        $path ??= Database::config()['path'];
        if (!is_string($path) || $path === '' || $path === ':memory:') {
            throw new InvalidArgumentException('La inicialización requiere un archivo SQLite.');
        }
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException('No se pudo crear storage/. Revisa los permisos.');
        }
        // Serializa inicializaciones simultáneas; no se invoca en solicitudes HTTP.
        $lock = @fopen($path . '.init.lock', 'c');
        if ($lock === false) throw new RuntimeException('No se pudo abrir el bloqueo de inicialización.');
        try {
            if (!flock($lock, LOCK_EX)) throw new RuntimeException('No se pudo bloquear la inicialización.');
            $pdo = Database::connect($path, true);
            $pdo->exec('PRAGMA journal_mode = WAL');
            $pdo->beginTransaction();
            try {
                $exists = (bool)$pdo->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'personas'")->fetchColumn();
                $schema = file_get_contents(dirname(__DIR__) . '/database.sql');
                if ($schema === false) throw new RuntimeException('No se pudo leer database.sql.');
                $pdo->exec($schema);
                $seeded = 0;
                if (!$exists) {
                    $repository = new PersonRepository($pdo);
                    foreach (self::DEMO as $person) {
                        $repository->create($person);
                        $seeded++;
                    }
                }
                $pdo->commit();
                return ['created' => !$exists, 'seeded' => $seeded];
            } catch (Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
