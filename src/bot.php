<?php

// Subir un nivel para leer el .env desde la raíz del proyecto
$envFile = dirname(__DIR__) . '/.env';
if (!file_exists($envFile)) {
    die("Error: No se encontró el archivo .env\n");
}

$env = parse_ini_file($envFile);
$botToken = $env['BOT_TOKEN'] ?? null;

if (!$botToken) {
    die("Error: La variable BOT_TOKEN no está configurada en .env\n");
}

$apiUrl = "https://api.telegram.org/bot{$botToken}/";
$lastUpdateId = 0;

echo "Bot en ejecución mediante Long Polling. Esperando mensajes...\n";

while (true) {
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

                if ($text === '/start') {
                    $mensajeBienvenida = "¡Hola! 👋 Soy tu asistente en la tienda deportiva.\nEscribe el nombre de un producto, o usa /ofertas para descuentos y /catalogo para ver categorías.";
                    file_get_contents($apiUrl . "sendMessage?chat_id={$chatId}&text=" . urlencode($mensajeBienvenida));
                }
            }
        }
    }

    usleep(500000);
}
