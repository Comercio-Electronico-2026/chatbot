<?php
// bot.php - Long Polling para responder a /start

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$botToken = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if (!$botToken) {
    die("❌ Error: No se encontró la variable BOT_TOKEN en el archivo .env\n");
}

$apiUrl = "https://api.telegram.org/bot{$botToken}/";

// FUNCIONES DEL BOT

function telegram(string $method, array $data = []): ?array
{
    global $apiUrl;

    $ch = curl_init($apiUrl . $method);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => !empty($data),
        CURLOPT_POSTFIELDS => !empty($data) ? http_build_query($data) : null,
        CURLOPT_TIMEOUT => 40
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response ? json_decode($response, true) : null;
}

function enviarMensaje(int $chatId, string $texto): void
{
    telegram('sendMessage', [
        'chat_id' => $chatId,
        'text' => $texto
    ]);
}

function logAccion(string $nivel, string $mensaje): void
{
    $iconos = [
        'INFO' => '📩',
        'WARN' => '⚠️',
        'ERROR' => '❌',
        'SUCCESS' => '✅'
    ];

    $icono = $iconos[$nivel] ?? 'ℹ️';

    echo "{$icono} [{$nivel}] {$mensaje}" . PHP_EOL;
}

function mostrarMenu(int $chatId, string $nombre): void
{
    $mensaje = "¡Hola {$nombre}! 👋\n\n"
             . "Bienvenido al asistente virtual de TechShift.\n\n"
             . "Opciones disponibles:\n"
             . "📦 /pedido - Consultar pedido\n"
             . "🛍️ /catalogo - Ver catálogo\n"
             . "❓ /help - Ayuda\n"
             . "👤 /soporte - Hablar con soporte\n"
             . "❌ /cancel - Cancelar\n\n"
             . "¿En qué te puedo ayudar?";

    enviarMensaje($chatId, $mensaje);
}
