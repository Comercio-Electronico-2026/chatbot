<?php
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) die("Error: Falta el archivo .env\n");

$env = parse_ini_file($envPath);
$token = $env['BOT_TOKEN'] ?? null;
if (!$token) die("Error: BOT_TOKEN vacío\n");

$apiUrl = "https://api.telegram.org/bot{$token}/";
$offset = 0;

echo "WhatPhone Bot en línea. Esperando mensajes (Presiona Ctrl+C para salir)...\n";

while (true) {
    $updates = @file_get_contents("{$apiUrl}getUpdates?offset={$offset}&timeout=20");
    if ($updates !== false) {
        $data = json_decode($updates, true);
        if (!empty($data['result'])) {
            foreach ($data['result'] as $update) {
                $offset = $update['update_id'] + 1;
                if (isset($update['message']['text'])) {
                    $chatId = $update['message']['chat']['id'];
                    $text = trim($update['message']['text']);

                    if (str_starts_with($text, '/start')) {
                        $reply = "¡Hola! 👋 Soy *WhatPhone*.\n\nEscribe `/ayuda` para ver cómo puedo asesorarte con las especificaciones de tu próximo teléfono.";
                        @file_get_contents("{$apiUrl}sendMessage?" . http_build_query([
                            'chat_id' => $chatId,
                            'text' => $reply,
                            'parse_mode' => 'Markdown'
                        ]));
                        echo "-> /start respondido al chat: {$chatId}\n";
                    }
                }
            }
        }
    }
    usleep(500000);
}
