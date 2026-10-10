<?php
declare(strict_types=1);

final class ConstraintLab
{
    public const CASES = [
        'not_null' => ['name' => 'NOT NULL · nombre', 'operation' => 'INSERT con nombre = NULL', 'expected' => 'not_null', 'explanation' => 'nombre es obligatorio; SQL NULL está prohibido por NOT NULL.'],
        'unique' => ['name' => 'UNIQUE · uq_personas_dpi', 'operation' => 'Dos INSERT con el mismo DPI de prueba', 'expected' => 'unique', 'explanation' => 'Un registro de prueba ya utiliza ese DPI; UNIQUE rechaza el segundo.'],
        'check' => ['name' => 'CHECK · chk_personas_telefono', 'operation' => "INSERT directo con telefono = '5123456'", 'expected' => 'check', 'explanation' => 'Siete dígitos incumplen el patrón y la longitud de ocho dígitos del CHECK.'],
    ];

    public static function run(PDO $pdo, string $case): array
    {
        if (!isset(self::CASES[$case])) {
            throw new InvalidArgumentException('Prueba desconocida.');
        }
        if ($pdo->inTransaction()) {
            throw new RuntimeException('La prueba requiere una transacción independiente.');
        }
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'sqlite') {
            throw new RuntimeException('Las pruebas requieren una conexión SQLite.');
        }
        $definition = self::CASES[$case];
        $pdo->beginTransaction();
        try {
            $find = $pdo->prepare('SELECT COUNT(*) FROM personas WHERE dpi = :dpi');
            do {
                $dpi = (string)random_int(1000000000000, 9999999999999);
                $find->execute(['dpi' => $dpi]);
            } while ((int)$find->fetchColumn() !== 0);
            // SQL fijo: estas pruebas eluden deliberadamente Validation y el repositorio.
            $insert = $pdo->prepare('INSERT INTO personas (nombre, telefono, dpi, correo) VALUES (:nombre, :telefono, :dpi, :correo)');
            $data = ['nombre' => 'Prueba temporal RegexLab', 'telefono' => '51234567', 'dpi' => $dpi, 'correo' => 'prueba@example.com'];
            if ($case === 'unique') {
                $insert->execute($data);
            } elseif ($case === 'not_null') {
                $data['nombre'] = null;
            } else {
                $data['telefono'] = '5123456';
            }
            $code = null;
            $type = null;
            try {
                $insert->execute($data);
                $passed = false;
            } catch (PDOException $error) {
                $code = (int)($error->errorInfo[1] ?? 0);
                $type = Database::constraintType($error);
                $passed = $type === $definition['expected'];
                if (!$passed) {
                    logTechnicalError($error, 'prueba de restricción inesperada');
                }
            }
            return [
                'case' => $case, 'name' => $definition['name'], 'operation' => $definition['operation'],
                'passed' => $passed, 'driver_code' => $code, 'constraint_type' => $type,
                'result' => $passed ? 'SQLite rechazó la operación como se esperaba.' : 'La prueba no obtuvo el rechazo esperado. Revisa el esquema y la configuración.',
                'explanation' => $passed ? $definition['explanation'] : 'Una aceptación o un error distinto no demuestra esta restricción.',
                'rollback' => true,
            ];
        } finally {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }
    }
}
