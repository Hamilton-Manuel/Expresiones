<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (!class_exists(ZipArchive::class)) {
    fwrite(STDERR, "Activa la extensión PHP zip para ejecutar el empaquetador.\n");
    exit(1);
}

$root = dirname(__DIR__);
$archive = $root . '/Practica5_RegexLab.zip';
$files = ['README.md', '.gitignore', '.gitattributes', '.dockerignore', 'Dockerfile', 'render.yaml', 'database.sql', 'config/config.example.php', 'docs/VERIFICACION.md', 'storage/.gitkeep'];
// Nunca recorrer config/ completo, tmp/, .git/ ni directorios de estado local.
foreach (['app', 'views', 'public', 'tests', 'tools', 'docker'] as $directory) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isLink() || !$file->isFile()) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
        // Solo código, recursos de la aplicación y licencia Bootstrap.
        if (in_array(strtolower($file->getExtension()), ['php', 'css', 'js', 'svg', 'sh', 'conf', 'ini'], true) || $relative === 'public/assets/vendor/bootstrap/LICENSE') {
            if (!str_contains($relative, 'config.local') && !str_contains($relative, '/.')) {
                $files[] = $relative;
            }
        }
    }
}
sort($files);
foreach ($files as $file) {
    if (!is_file($root . '/' . $file)) {
        fwrite(STDERR, "Falta un archivo de entrega: $file\n");
        exit(1);
    }
}
$zip = new ZipArchive();
if ($zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "No se pudo crear el ZIP en el directorio del proyecto.\n");
    exit(1);
}
foreach ($files as $file) {
    if (!$zip->addFile($root . '/' . $file, $file)) {
        $zip->close();
        fwrite(STDERR, "No se pudo añadir un archivo al ZIP.\n");
        exit(1);
    }
}
if (!$zip->close()) {
    fwrite(STDERR, "No se pudo finalizar el ZIP.\n");
    exit(1);
}
echo 'ZIP creado: Practica5_RegexLab.zip · ' . count($files) . " archivos\n";
echo 'SHA-256: ' . hash_file('sha256', $archive) . "\n";
