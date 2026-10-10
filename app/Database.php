<?php
declare(strict_types=1);

final class Database
{
    public static function config(): array
    {
        $path = dirname(__DIR__) . '/config/';
        $config = require $path . 'config.example.php';
        if (is_file($path . 'config.local.php')) {
            $local = require $path . 'config.local.php';
            if (!is_array($local)) {
                throw new RuntimeException('Configuración local inválida.');
            }
            $config = array_replace($config, $local);
        }
        foreach (['path' => 'REGEXLAB_SQLITE_PATH', 'busy_timeout_ms' => 'REGEXLAB_BUSY_TIMEOUT_MS'] as $key => $variable) {
            $env = getenv($variable);
            if ($env !== false) {
                $config[$key] = $env;
            }
        }
        return $config;
    }

    public static function connect(?string $path = null, bool $allowCreate = false): PDO
    {
        $c = self::config();
        $path ??= $c['path'];
        $timeout = filter_var($c['busy_timeout_ms'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 60000]]);
        if (!is_string($path) || $path === '' || str_contains($path, "\0") || $timeout === false) {
            throw new RuntimeException('Configuración SQLite inválida.');
        }
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('Activa pdo_sqlite en PHP.');
        }
        // Las solicitudes web nunca crean ni reinicializan la base de datos.
        if (!$allowCreate && ($path === ':memory:' || !is_file($path))) {
            throw new RuntimeException('Inicializa SQLite con php tools/init-db.php antes de usar los registros.');
        }
        $public = realpath(dirname(__DIR__) . '/public');
        $parent = realpath(dirname($path));
        if ($path !== ':memory:' && $parent !== false && $public !== false && ($parent === $public || str_starts_with($parent, $public . DIRECTORY_SEPARATOR))) {
            throw new RuntimeException('La base de datos debe estar fuera de public/.');
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => (int)ceil($timeout / 1000),
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = ' . $timeout);
        $pdo->exec('PRAGMA ignore_check_constraints = OFF');
        return $pdo;
    }

    /** Clasifica únicamente errores reales emitidos por SQLite, sin exponer su texto. */
    public static function constraintType(PDOException $error): ?string
    {
        if (((int)($error->errorInfo[1] ?? 0) & 255) !== 19) {
            return null;
        }
        $message = (string)($error->errorInfo[2] ?? '');
        if (str_contains($message, 'UNIQUE constraint failed: personas.dpi')) return 'unique';
        if (str_contains($message, 'NOT NULL constraint failed: personas.')) return 'not_null';
        if (str_contains($message, 'CHECK constraint failed: chk_personas_telefono')) return 'check';
        return null;
    }

    public static function userMessage(PDOException $error): string
    {
        return match (self::constraintType($error)) {
            'unique' => 'Ya existe un registro con ese DPI. Cada DPI debe ser único.',
            'not_null' => 'Faltan campos obligatorios. La base de datos rechazó el registro.',
            'check' => 'La restricción CHECK rechazó el teléfono: debe contener exactamente 8 dígitos.',
            default => ((int)($error->errorInfo[1] ?? 0) & 255) === 5
                ? 'La base de datos está ocupada. Intenta la operación de nuevo en unos segundos.'
                : 'No se pudo completar la operación. Revisa la inicialización de SQLite y los permisos de storage/.',
        };
    }
}
