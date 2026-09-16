<?php

declare(strict_types=1);

final class Session
{
    private const DEFAULT = [
        'state' => 'MENU',
        'attempts' => 0,
        'context' => null,
        'pending' => null,
        'return_state' => null,
    ];

    public static function load(int|string $chatId): array
    {
        $file = self::file($chatId);
        if (is_file($file)) {
            $data = json_decode((string) file_get_contents($file), true);
            if (is_array($data)) {
                return $data + self::DEFAULT;
            }
        }
        return self::DEFAULT;
    }

    public static function save(int|string $chatId, array $session): void
    {
        file_put_contents(
            self::file($chatId),
            json_encode($session, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    public static function reset(int|string $chatId): array
    {
        self::save($chatId, self::DEFAULT);
        return self::DEFAULT;
    }

    private static function file(int|string $chatId): string
    {
        $dir = sys_get_temp_dir() . '/tg-shop-bot-sessions';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $id = preg_replace('/[^0-9-]/', '', (string) $chatId) ?? '0';
        return $dir . '/chat-' . $id . '.json';
    }
}
