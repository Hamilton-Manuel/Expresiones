<?php
declare(strict_types=1);

final class Regex
{
    // Cuerpos compartidos con JavaScript; PHP usa \A y \z para el valor completo.
    public const PHONE = '(?:\+502[ ]?)?[0-9]{4}[ -]?[0-9]{4}';
    public const DPI = '(?:[0-9]{13}|[0-9]{4} [0-9]{5} [0-9]{4})';
    public const EMAIL = '[A-Za-z0-9_%+\-]+(?:\.[A-Za-z0-9_%+\-]+)*@(?:[A-Za-z0-9](?:[A-Za-z0-9\-]*[A-Za-z0-9])?\.)+[A-Za-z]{2,63}';

    // Candidatos completos, deliberadamente más amplios que los validadores.
    // Un bloque numérico se consume entero para impedir extraer fragmentos de DPI.
    public const FIND_NUMBERS = '~(?<![\p{L}\p{N}_@.+/\-])\+?[0-9]++(?:[ \t\-]++[0-9]++)*+(?![\p{L}\p{N}_@+/\-]|\.[\p{L}\p{N}_])~u';
    public const FIND_EMAILS = '~[^\s<>()[\]{},;:"\x27]+@[^\s<>()[\]{},;:"\x27]*~u';
    public const FIND_LABELS = '~\b(?<label>Tel[eé]fono|DPI|Correo(?:[ ]+electr[oó]nico)?)[ \t]*[:=][ \t]*(?<value>[^\r\n;,]*?)(?=[ \t]+(?:Nombre(?:[ ]+completo)?|Tel[eé]fono|DPI|Correo(?:[ ]+electr[oó]nico)?)[ \t]*[:=]|;|,|\r?$)~imu';

    public static function patterns(): array
    {
        return ['telefono' => self::PHONE, 'dpi' => self::DPI, 'correo' => self::EMAIL];
    }

    public static function full(string $type): string
    {
        return '~\A(?:' . self::patterns()[$type] . ')\z~D';
    }
}
