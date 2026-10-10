<?php
declare(strict_types=1);

final class Validation
{
    public static function field(string $type, string $original): array
    {
        $limits = ['telefono' => 20, 'dpi' => 20, 'correo' => 254];
        $messages = [
            'telefono' => 'Usa 8 dígitos, con un espacio o guion opcional entre grupos de 4 y prefijo +502 opcional.',
            'dpi' => 'Usa 13 dígitos o el formato 1234 56789 0101. Solo se comprueba el formato.',
            'correo' => 'Usa usuario@dominio.ext, sin espacios ni puntos consecutivos en el usuario.',
        ];
        if (!isset($limits[$type])) {
            throw new InvalidArgumentException('Tipo de dato desconocido.');
        }
        $format = strlen($original) <= $limits[$type] && preg_match(Regex::full($type), $original) === 1;
        $length = $type !== 'correo' || strlen(explode('@', $original)[0]) <= 64;
        $valid = $format && $length;
        $normalized = null;
        if ($valid) {
            if ($type === 'telefono') {
                $normalized = str_replace(['+502', ' ', '-'], '', $original);
            } elseif ($type === 'dpi') {
                $normalized = str_replace(' ', '', $original);
            } else {
                [$local, $domain] = explode('@', $original);
                $normalized = $local . '@' . strtolower($domain);
            }
        }
        return [
            'type' => $type, 'original' => $original, 'normalized' => $normalized,
            'valid' => $valid,
            'message' => $valid ? 'Formato válido. No acredita identidad ni autenticidad.' : ($original === '' ? 'El campo es obligatorio.' : $messages[$type]),
            'checks' => [
                ['label' => 'Campo presente', 'ok' => $original !== ''],
                ['label' => 'Coincide con la expresión regular', 'ok' => $format],
                ['label' => $type === 'correo' ? 'Longitud permitida (usuario ≤ 64, total ≤ 254)' : 'Formato completo, sin fragmentos', 'ok' => $type === 'correo' ? strlen($original) <= 254 && $length : $format],
            ],
        ];
    }

    public static function person(array $input): array
    {
        $errors = [];
        $fields = [];
        $name = isset($input['nombre']) && is_string($input['nombre']) ? trim($input['nombre']) : '';
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120 || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            $errors['nombre'] = 'Ingresa un nombre de 1 a 120 caracteres, sin caracteres de control.';
        }
        $data = ['nombre' => $name];
        foreach (Regex::patterns() as $type => $pattern) {
            $value = isset($input[$type]) && is_string($input[$type]) ? $input[$type] : '';
            $fields[$type] = self::field($type, $value);
            if (!$fields[$type]['valid']) {
                $errors[$type] = $fields[$type]['message'];
            }
            $data[$type] = $fields[$type]['normalized'];
        }
        return ['valid' => $errors === [], 'errors' => $errors, 'fields' => $fields, 'data' => $data];
    }
}
