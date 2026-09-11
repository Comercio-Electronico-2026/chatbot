<?php

declare(strict_types=1);

const TELEGRAM_API_BASE = 'https://api.telegram.org';
const POLLING_TIMEOUT_SECONDS = 25;
const POLLING_HTTP_TIMEOUT_SECONDS = POLLING_TIMEOUT_SECONDS + 25;
const STANDARD_HTTP_TIMEOUT_SECONDS = 15;

/**
 * Carga variables sencillas desde .env sin requerir dependencias externas.
 */
function loadEnvironment(string $path): void
{
    if (!is_file($path)) {
        throw new RuntimeException(
            'No se encontró .env. Copia .env.example como .env y agrega BOT_TOKEN.'
        );
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        throw new RuntimeException('No fue posible leer el archivo .env.');
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (!preg_match('/^[A-Z_][A-Z0-9_]*$/', $name)) {
            continue;
        }

        if (getenv($name) === false) {
            putenv("{$name}={$value}");
        }
    }
}

/**
 * Ejecuta una solicitud a Telegram y devuelve solamente el campo result.
 *
 * @return mixed
 */
function telegramRequest(string $token, string $method, array $parameters = []): mixed
{
    $url = TELEGRAM_API_BASE . "/bot{$token}/{$method}";
    $handle = curl_init($url);
    $httpTimeout = $method === 'getUpdates'
        ? POLLING_HTTP_TIMEOUT_SECONDS
        : STANDARD_HTTP_TIMEOUT_SECONDS;

    if ($handle === false) {
        throw new RuntimeException('No fue posible iniciar la conexión con Telegram.');
    }

    curl_setopt_array($handle, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $parameters,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $httpTimeout,
    ]);

    $response = curl_exec($handle);

    if ($response === false) {
        $error = curl_error($handle);
        curl_close($handle);
        throw new RuntimeException("Error de conexión con Telegram: {$error}");
    }

    $statusCode = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    try {
        $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new RuntimeException('Telegram devolvió una respuesta que no es JSON válido.');
    }

    if ($statusCode !== 200 || !($data['ok'] ?? false)) {
        $description = $data['description'] ?? 'Error desconocido de Telegram.';
        throw new RuntimeException("Telegram rechazó la solicitud: {$description}");
    }

    return $data['result'];
}

function isStartCommand(string $text): bool
{
    return preg_match('/^\/start(?:@[A-Za-z0-9_]+)?(?:\s|$)/i', trim($text)) === 1;
}

function compactLogText(string $text, int $maximumLength = 200): string
{
    $singleLineText = preg_replace('/\s+/u', ' ', trim($text));

    if ($singleLineText === null) {
        return '[texto no válido]';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        return mb_strlen($singleLineText) > $maximumLength
            ? mb_substr($singleLineText, 0, $maximumLength) . '…'
            : $singleLineText;
    }

    return strlen($singleLineText) > $maximumLength
        ? substr($singleLineText, 0, $maximumLength) . '...'
        : $singleLineText;
}

function detectMessageType(array $message): string
{
    foreach (['text', 'photo', 'voice', 'audio', 'video', 'document', 'sticker', 'location'] as $type) {
        if (array_key_exists($type, $message)) {
            return $type;
        }
    }

    return 'otro';
}

function logEvent(string $event, array $details = []): void
{
    $encodedDetails = json_encode(
        $details,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    $suffix = $encodedDetails !== false && $details !== []
        ? " {$encodedDetails}"
        : '';

    fwrite(STDOUT, sprintf("[%s] %s%s\n", date('Y-m-d H:i:s'), $event, $suffix));
}

function sendWelcome(string $token, int|string $chatId): void
{
    $message = implode("\n", [
        '¡Hola! Bienvenido a Tienda Electrónica.',
        '',
        'Aquí encontrarás información sobre los cuatro productos de la tienda, sus categorías y precios.',
    ]);

    telegramRequest($token, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => $message,
    ]);
}

try {
    loadEnvironment(dirname(__DIR__) . '/.env');
    $token = getenv('BOT_TOKEN');

    if ($token === false || trim($token) === '') {
        throw new RuntimeException('La variable BOT_TOKEN no tiene un valor.');
    }

    $bot = telegramRequest($token, 'getMe');
    $username = $bot['username'] ?? 'sin_usuario';
    logEvent('Bot conectado', ['usuario' => "@{$username}"]);
    logEvent('Esperando mensajes. Presiona Ctrl+C para detenerlo.');
} catch (RuntimeException $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    exit(1);
}

$offset = 0;

while (true) {
    try {
        $updates = telegramRequest($token, 'getUpdates', [
            'offset' => $offset,
            'timeout' => POLLING_TIMEOUT_SECONDS,
            'allowed_updates' => json_encode(['message'], JSON_THROW_ON_ERROR),
        ]);

        foreach ($updates as $update) {
            $offset = ((int) $update['update_id']) + 1;
            $message = $update['message'] ?? null;

            if (!is_array($message) || !isset($message['chat']['id'])) {
                continue;
            }

            $text = (string) ($message['text'] ?? '');
            $messageType = detectMessageType($message);
            $logDetails = [
                'actualizacion' => (int) $update['update_id'],
                'tipo' => $messageType,
            ];

            if ($messageType === 'text') {
                $logDetails['texto'] = compactLogText($text);
            }

            logEvent('Mensaje recibido', $logDetails);

            if (isStartCommand($text)) {
                sendWelcome($token, $message['chat']['id']);
                logEvent('Respuesta enviada', [
                    'actualizacion' => (int) $update['update_id'],
                    'accion' => 'bienvenida',
                ]);
            } else {
                logEvent('Mensaje sin respuesta', [
                    'actualizacion' => (int) $update['update_id'],
                    'motivo' => 'Solo /start está implementado',
                ]);
            }
        }
    } catch (RuntimeException | JsonException $exception) {
        fwrite(
            STDERR,
            sprintf(
                "[%s] %s Reintentando en 3 segundos.\n",
                date('Y-m-d H:i:s'),
                $exception->getMessage()
            )
        );
        sleep(3);
    }
}
