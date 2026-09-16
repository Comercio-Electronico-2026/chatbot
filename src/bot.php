<?php
// bot.php - Long Polling para responder a /start

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$botToken = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if (empty($botToken)) {
    die("❌ Error: No se encontró la variable BOT_TOKEN en el archivo .env\n");
}

$apiUrl = "https://api.telegram.org/bot{$botToken}/";

// Probar conexión y obtener datos del bot (getMe)
$ch = curl_init($apiUrl . "getMe");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
curl_close($ch);

$me = json_decode($res, true);
if (!$me || !($me['ok'] ?? false)) {
    die("❌ Error: Token inválido o problema al conectar con Telegram.\n");
}

echo "✅ Bot conectado exitosamente: @" . $me['result']['username'] . " (ID: " . $me['result']['id'] . ")\n";
echo "📡 Escuchando mensajes con Long Polling (Presiona Ctrl + C para salir)...\n";

$offset = 0;

while (true) {
    // getUpdates con long polling (espera hasta 30s si no hay mensajes nuevos)
    $ch = curl_init($apiUrl . "getUpdates?timeout=30&offset=" . $offset);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 40);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response === false) {
        sleep(2);
        continue;
    }

    $data = json_decode($response, true);

    if (!empty($data['result'])) {
        foreach ($data['result'] as $update) {
            $offset = $update['update_id'] + 1;

            if (isset($update['message']['text'])) {
                $chatId = $update['message']['chat']['id'];
                $text = trim($update['message']['text']);
                $name = $update['message']['chat']['first_name'] ?? 'Usuario';

                if (strpos($text, '/start') === 0) {
                    $reply = "¡Hola {$name}! 👋\n\n"
                           . "Bienvenido al asistente virtual de la tienda TechShift.\n\n"
                           . "Opciones disponibles:\n"
                           . "📦 /pedido - Rastrear estado de pedido\n"
                           . "🛍️ /catalogo - Ver catálogo de productos\n"
                           . "❓ /help - Ayuda\n\n"
                           . "¿En qué te puedo ayudar hoy?";

                    // Enviar respuesta por sendMessage
                    $sendCh = curl_init($apiUrl . "sendMessage");
                    curl_setopt($sendCh, CURLOPT_POST, true);
                    curl_setopt($sendCh, CURLOPT_POSTFIELDS, http_build_query([
                        'chat_id' => $chatId,
                        'text' => $reply
                    ]));
                    curl_setopt($sendCh, CURLOPT_RETURNTRANSFER, true);
                    curl_exec($sendCh);
                    curl_close($sendCh);

                    echo "📩 [INFO] Respondido comando /start al usuario '{$name}' (Chat ID: {$chatId})\n";
                }
            }
        }
    }
}
