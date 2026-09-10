<?php

declare(strict_types=1);

/**
 * CitasBot - Clínica Sonrisa Sana
 * Sesión 1: long polling mínimo que responde a /start.
 *
 * Uso:
 *   php bot.php
 *
 * Requiere: extensión curl de PHP y el token en el archivo .env (variable BOT_TOKEN).
 */

const API_BASE = 'https://api.telegram.org';
const OFFSET_FILE = __DIR__ . '/.offset';

function loadOffset(): int
{
    if (is_file(OFFSET_FILE)) {
        return (int) trim((string) file_get_contents(OFFSET_FILE));
    }
    return 0;
}

function saveOffset(int $offset): void
{
    file_put_contents(OFFSET_FILE, (string) $offset);
}

function loadToken(string $dir): string
{
    $dotenv = parse_ini_file($dir . '/.env');
    if ($dotenv === false || empty($dotenv['BOT_TOKEN'])) {
        fwrite(STDERR, "No se encontro BOT_TOKEN en el archivo .env\n");
        exit(1);
    }
    return (string) $dotenv['BOT_TOKEN'];
}

function callApi(string $endpoint, ?array $post = null): array
{
    $url = API_BASE . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode((string) $response, true);
    return is_array($data) ? $data : [];
}

function sendMessage(string $token, int|string $chatId, string $text): void
{
    callApi("/bot{$token}/sendMessage", [
        'chat_id' => $chatId,
        'text' => $text,
    ]);
}

function welcomeMessage(): string
{
    return "Hola, soy CitasBot de Clinica Sonrisa Sana. Puedo ayudarte a agendar, "
        . "consultar o cancelar una cita.\n\n"
        . "Escribe /ayuda para ver las opciones disponibles.";
}

function helpMessage(): string
{
    return "Estas son las opciones:\n"
        . "- Agendar una cita\n"
        . "- Consultar mis citas\n"
        . "- Cancelar una cita\n"
        . "- Consultar servicios y precios\n\n"
        . "Escribe /cancelar para volver al inicio.";
}

$token = loadToken(__DIR__);
$offset = loadOffset();

echo "CitasBot (long polling) iniciado. Presiona Ctrl+C para detener.\n";
echo "Offset inicial: " . $offset . "\n";

while (true) {
    $updates = callApi("/bot{$token}/getUpdates?timeout=30&offset=" . ($offset + 1));
    if (empty($updates['ok']) || empty($updates['result'])) {
        continue;
    }

    foreach ($updates['result'] as $update) {
        $offset = max($offset, (int) $update['update_id']);

        $message = $update['message'] ?? null;
        if ($message === null) {
            saveOffset($offset);
            continue;
        }

        $chatId = $message['chat']['id'];
        $text = trim((string) ($message['text'] ?? ''));

        echo date('[Y-m-d H:i:s] ') . $chatId . ': ' . $text . "\n";

        switch ($text) {
            case '/start':
                sendMessage($token, $chatId, welcomeMessage());
                break;
            case '/ayuda':
            case '/help':
                sendMessage($token, $chatId, helpMessage());
                break;
            default:
                sendMessage($token, $chatId,
                    "No entendi tu mensaje. Escribe /ayuda para ver las opciones disponibles.");
        }

        saveOffset($offset);
    }
}