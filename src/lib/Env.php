<?php

declare(strict_types=1);

final class Env
{
    private static array $vars = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }
        $rows = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($rows as $row) {
            $row = trim($row);
            if ($row === '' || str_starts_with($row, '#')) {
                continue;
            }
            $pos = strpos($row, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($row, 0, $pos));
            $value = trim(substr($row, $pos + 1));
            $value = trim($value, "\"'");
            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (isset(self::$vars[$key]) && self::$vars[$key] !== '') {
            return self::$vars[$key];
        }
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        return $default;
    }
}
