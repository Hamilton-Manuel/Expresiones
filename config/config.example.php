<?php
declare(strict_types=1);

// Opcional: copiar a config.local.php. No se necesitan credenciales ni servidor.
return [
    'path' => dirname(__DIR__) . '/storage/regexlab.sqlite',
    'busy_timeout_ms' => 5000,
];
