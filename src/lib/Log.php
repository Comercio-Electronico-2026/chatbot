<?php

declare(strict_types=1);

final class Log
{
    public static function incoming(int|string $chatId, string $text): void
    {
        self::write('IN ', $chatId, $text);
    }

    public static function outgoing(int|string $chatId, string $text): void
    {
        self::write('OUT', $chatId, $text);
    }

    public static function error(string $source, string $detail): void
    {
        $line = sprintf(
            "[%s] ERR %s: %s\n",
            date('Y-m-d H:i:s'),
            $source,
            str_replace("\n", ' ', $detail)
        );
        @file_put_contents(self::file(), $line, FILE_APPEND | LOCK_EX);
    }

    public static function event(string $text): void
    {
        $line = sprintf(
            "[%s] EVT %s\n",
            date('Y-m-d H:i:s'),
            str_replace("\n", ' ', $text)
        );
        @file_put_contents(self::file(), $line, FILE_APPEND | LOCK_EX);
    }

    private static function write(string $direction, int|string $chatId, string $text): void
    {
        $line = sprintf(
            "[%s] %s chat=%s %s\n",
            date('Y-m-d H:i:s'),
            $direction,
            $chatId,
            str_replace("\n", ' ', $text)
        );
        @file_put_contents(self::file(), $line, FILE_APPEND | LOCK_EX);
    }

    private static function file(): string
    {
        return Env::get('LOG_FILE', sys_get_temp_dir() . '/tg-shop-bot.log');
    }
}
