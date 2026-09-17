<?php

declare(strict_types=1);

class ChatStore
{
    public const TTL = 1800;

    public function __construct(private readonly string $directory)
    {
        foreach ([$directory, $directory . '/chats', $directory . '/logs'] as $path) {
            if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) {
                throw new RuntimeException('No fue posible crear el directorio de datos del bot.');
            }
        }
    }

    public function process(string $chatId, int $updateId, callable $handler): bool
    {
        $cleanupLock = fopen($this->directory . '/cleanup.lock', 'c+');
        if ($cleanupLock === false || !flock($cleanupLock, LOCK_SH)) {
            throw new RuntimeException('No fue posible bloquear el directorio de conversaciones.');
        }
        $path = $this->directory . '/chats/' . hash('sha256', $chatId) . '.json';
        $file = fopen($path, 'c+');
        if ($file === false || !flock($file, LOCK_EX)) {
            flock($cleanupLock, LOCK_UN);
            fclose($cleanupLock);
            throw new RuntimeException('No fue posible bloquear el estado de la conversación.');
        }
        try {
            $raw = stream_get_contents($file);
            $record = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $recent = $record['updates'] ?? [];
            if (in_array($updateId, $recent, true)) {
                return false;
            }
            $state = ($record['expires'] ?? 0) > time() ? ($record['state'] ?? []) : [];
            $handler($state);
            $recent[] = $updateId;
            $record = ['state' => $state, 'updates' => array_slice($recent, -50), 'expires' => time() + self::TTL];
            $encoded = json_encode($record, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            rewind($file);
            if (!ftruncate($file, 0) || fwrite($file, $encoded) !== strlen($encoded) || !fflush($file)) {
                throw new RuntimeException('No fue posible guardar el estado de la conversación.');
            }
            return true;
        } finally {
            flock($file, LOCK_UN);
            fclose($file);
            flock($cleanupLock, LOCK_UN);
            fclose($cleanupLock);
        }
    }

    public function cleanup(): void
    {
        $cleanupLock = fopen($this->directory . '/cleanup.lock', 'c+');
        if ($cleanupLock === false) {
            throw new RuntimeException('No fue posible abrir el bloqueo de limpieza.');
        }
        if (!flock($cleanupLock, LOCK_EX | LOCK_NB)) {
            fclose($cleanupLock);
            return;
        }
        try {
            foreach (glob($this->directory . '/chats/*.json') ?: [] as $path) {
                $file = fopen($path, 'r+');
                if ($file === false) {
                    continue;
                }
                if (flock($file, LOCK_EX | LOCK_NB)) {
                    // No se elimina un fichero bloqueado por una conversación activa.
                    if (filemtime($path) < time() - self::TTL) {
                        unlink($path);
                    }
                    flock($file, LOCK_UN);
                }
                fclose($file);
            }
            foreach (glob($this->directory . '/logs/bot-*.log') ?: [] as $path) {
                if (filemtime($path) < time() - 7 * 86400) {
                    unlink($path);
                }
            }
        } finally {
            flock($cleanupLock, LOCK_UN);
            fclose($cleanupLock);
        }
    }
}
