<?php
// Cargar variable BOT_TOKEN desde el archivo .env
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    die("Error: No se encontró el archivo .env\n");
}

$env = parse_ini_file($envFile);
$token = $env['BOT_TOKEN'] ?? null;

if (!$token) {
    die("Error: BOT_TOKEN no definido en .env\n");
}

$apiUrl = "https://api.telegram.org/bot{$token}/";
echo "🤖 ElForaneo Bot iniciado con Long Polling. Esperando mensajes...\n";

$lastUpdateId = 0;

while (true) {
    // Consultar getUpdates con long polling
    $url = $apiUrl . "getUpdates?offset=" . ($lastUpdateId + 1) . "&timeout=30";
    $response = @file_get_contents($url);

    if ($response === false) {
        sleep(2);
        continue;
    }

    $data = json_decode($response, true);

    if (!empty($data['result'])) {
        foreach ($data['result'] as $update) {
            $lastUpdateId = $update['update_id'];

            if (isset($update['message']['text'])) {
                $chatId = $update['message']['chat']['id'];
                $text = trim($update['message']['text']);
                $sender = $update['message']['from']['first_name'] ?? 'Usuario';

                echo "Mensaje recibido de {$sender} [{$chatId}]: {$text}\n";

                // Respuesta al comando /start
                if (str_starts_with($text, '/start')) {
                    $reply = "¡Hola {$sender}! 👋 Bienvenido a *El Foráneo Bot*.\n\n"
                           . "Te ayudo a encontrar habitación o pupilaje cerca de tu universidad en El Salvador de forma rápida y segura.\n\n"
                           . "¿Te gustaría buscar opciones disponibles ahora? Escribe *buscar* para comenzar.";

                    $sendUrl = $apiUrl . "sendMessage";
                    $postData = [
                        'chat_id' => $chatId,
                        'text' => $reply,
                        'parse_mode' => 'Markdown'
                    ];

                    $opts = [
                        'http' => [
                            'method'  => 'POST',
                            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                            'content' => http_build_query($postData)
                        ]
                    ];
                    file_get_contents($sendUrl, false, stream_context_create($opts));
                }
            }
        }
    }
}
