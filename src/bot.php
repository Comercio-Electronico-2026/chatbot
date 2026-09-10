<?php
$env = parse_ini_file('.env');
$token = $env['BOT_TOKEN'];
$apiUrl = "https://api.telegram.org/bot" . $token;

echo "Bot iniciado localmente. Esperando mensajes...\n";

$updateId = 0;

while (true) {
    $url = $apiUrl . "/getUpdates?offset=" . ($updateId + 1) . "&timeout=30";
    $response = @file_get_contents($url);
    if ($response) {
        $updates = json_decode($response, true);
        if ($updates['ok']) {
            foreach ($updates['result'] as $update) {
                $updateId = $update['update_id'];
                $message = $update['message']['text'] ?? '';
                $chatId = $update['message']['chat']['id'] ?? '';

                if ($message === '/start') {
                    $reply = "¡Hola! Bienvenido al bot de MusicHub 🎵. Por favor, selecciona una opción para continuar:\n\n"
                           . "🛒 /catalogo - Buscar en la tienda\n"
                           . "🛍️  /pedido - Revisar el estado de tu compra";
                    file_get_contents($apiUrl . "/sendMessage?chat_id=" . $chatId . "&text=" . urlencode($reply));
                    echo "Respondido a /start en el chat $chatId\n";
                }
            }
        }
    }
    sleep(1);
}
