<?php
declare(strict_types=1);

final class TextLab
{
    public const MAX_BYTES = 30000;

    public static function analyze(string $text): array
    {
        if (strlen($text) > self::MAX_BYTES || !mb_check_encoding($text, 'UTF-8')) {
            throw new InvalidArgumentException('El texto debe ser UTF-8 y ocupar como máximo 30 000 bytes.');
        }
        $results = [];
        $occupied = [];
        preg_match_all(Regex::FIND_LABELS, $text, $labels, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        foreach ($labels as $match) {
            $label = mb_strtolower($match['label'][0], 'UTF-8');
            $type = $label === 'dpi' ? 'dpi' : (str_starts_with($label, 'correo') ? 'correo' : 'telefono');
            $raw = $match['value'][0];
            $value = trim($raw, " \t");
            $offset = $match['value'][1] + strlen($raw) - strlen(ltrim($raw, " \t"));
            $results[] = self::result($type, $value, $offset, 'etiqueta');
            $occupied[] = [$match[0][1], $match[0][1] + strlen($match[0][0])];
        }
        // Reservar correos (incluso inválidos) antes de buscar números dentro de ellos.
        preg_match_all(Regex::FIND_EMAILS, $text, $emails, PREG_OFFSET_CAPTURE);
        foreach ($emails[0] as [$raw, $offset]) {
            if (self::overlaps($occupied, $offset, strlen($raw))) {
                continue;
            }
            // Un punto final de oración es puntuación; los valores etiquetados son exactos.
            $value = preg_replace('/[.!?]$/', '', $raw);
            $results[] = self::result('correo', $value, $offset, 'texto');
            $occupied[] = [$offset, $offset + strlen($raw)];
        }
        preg_match_all(Regex::FIND_NUMBERS, $text, $numbers, PREG_OFFSET_CAPTURE);
        foreach ($numbers[0] as [$value, $offset]) {
            if (self::overlaps($occupied, $offset, strlen($value))) {
                continue;
            }
            // No recuperar un grupo final de un bloque cuyo inicio estaba incrustado
            // en letras, ni un prefijo de un bloque numérico mayor mal formado.
            if (preg_match('/[0-9][ \t-]+\z/', substr($text, 0, $offset)) || preg_match('/\A[ \t-]+[0-9]/', substr($text, $offset + strlen($value)))) {
                continue;
            }
            $type = self::numericType($value);
            if ($type !== null) {
                $results[] = self::result($type, $value, $offset, 'texto');
                continue;
            }
            // Dos valores completos separados por espacios son independientes.
            // Grupos parciales ambiguos (p. ej. 1234 5678 9012) no se fraccionan.
            preg_match_all('/[^ \t]+/', $value, $parts, PREG_OFFSET_CAPTURE);
            if (count($parts[0]) > 1 && array_all_compatible($parts[0], fn(array $part): bool => self::numericType($part[0]) !== null)) {
                foreach ($parts[0] as [$part, $relative]) {
                    $results[] = self::result(self::numericType($part), $part, $offset + $relative, 'texto');
                }
            }
        }
        usort($results, fn(array $a, array $b): int => $a['offset'] <=> $b['offset']);
        $counts = ['telefono' => 0, 'dpi' => 0, 'correo' => 0, 'valid' => 0, 'invalid' => 0];
        foreach ($results as $id => &$result) {
            $result['id'] = $id;
            $counts[$result['type']]++;
            $counts[$result['valid'] ? 'valid' : 'invalid']++;
        }
        unset($result);
        return [
            'results' => $results, 'counts' => $counts,
            'highlighted' => self::highlight($text, $results),
            'anonymized' => self::anonymize($text, $results),
        ];
    }

    private static function numericType(string $value): ?string
    {
        foreach (['dpi', 'telefono'] as $type) {
            if (Validation::field($type, $value)['valid']) {
                return $type;
            }
        }
        return null;
    }

    private static function result(string $type, string $value, int $offset, string $source): array
    {
        return Validation::field($type, $value) + ['offset' => $offset, 'length' => strlen($value), 'source' => $source];
    }

    private static function overlaps(array $ranges, int $offset, int $length): bool
    {
        foreach ($ranges as [$start, $end]) {
            if ($offset < $end && $offset + $length > $start) {
                return true;
            }
        }
        return false;
    }

    public static function highlight(string $text, array $results): string
    {
        $html = '';
        $cursor = 0;
        foreach ($results as $result) {
            if ($result['length'] === 0 || $result['offset'] < $cursor) {
                continue;
            }
            $html .= escape(substr($text, $cursor, $result['offset'] - $cursor));
            $class = $result['valid'] ? $result['type'] : 'invalid';
            $html .= '<mark class="match-' . $class . '">' . escape($result['original']) . '</mark>';
            $cursor = $result['offset'] + $result['length'];
        }
        return $html . escape(substr($text, $cursor));
    }

    public static function anonymize(string $text, array $results): string
    {
        $positions = [];
        $values = [];
        foreach ($results as $result) {
            if (!$result['valid']) {
                continue;
            }
            $positions[$result['offset']] = $result;
            $values[$result['original']] = $result['original'];
        }
        if ($values === []) {
            return $text;
        }
        usort($values, fn(string $a, string $b): int => strlen($b) <=> strlen($a));
        $pattern = '~(?:' . implode('|', array_map(fn(string $v): string => preg_quote($v, '~'), $values)) . ')~u';
        return preg_replace_callback($pattern, function (array $match) use ($positions): string {
            [$original, $offset] = $match[0];
            $result = $positions[$offset] ?? null;
            if ($result === null || $result['original'] !== $original) {
                return $original;
            }
            $value = $result['normalized'];
            return match ($result['type']) {
                'telefono' => '****' . substr($value, -4),
                'dpi' => '*********' . substr($value, -4),
                'correo' => substr($value, 0, 1) . '***@' . explode('@', $original)[1],
            };
        }, $text, -1, $count, PREG_OFFSET_CAPTURE);
    }
}

/** Equivalente simple de array_all, disponible desde PHP 8.1. */
function array_all_compatible(array $items, callable $predicate): bool
{
    foreach ($items as $item) {
        if (!$predicate($item)) {
            return false;
        }
    }
    return true;
}
