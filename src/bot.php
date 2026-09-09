<?php
// Leer el token del archivo .env
$env = file_get_contents(__DIR__ . '/../.env');
preg_match('/BOT_TOKEN=(.*)/', $env, $matches);
if (empty($matches[1])) {
    die("Error: BOT_TOKEN no encontrado en .env\n");
}
$token = trim($matches[1]);
$apiUrl = "https://api.telegram.org/bot{$token}/";

$offset = 0;
echo "Bot ejecutándose con Long Polling. Esperando comando /start...\n";

while (true) {
    $url = $apiUrl . "getUpdates?offset=$offset&timeout=10";
    $response = file_get_contents($url);
    $updates = json_decode($response, true);

    if (isset($updates['ok']) && $updates['ok'] && !empty($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $offset = $update['update_id'] + 1; // Actualizar el offset para no procesar el mensaje dos veces
            
            if (isset($update['message']['text'])) {
                $chatId = $update['message']['chat']['id'];
                $text = $update['message']['text'];

                if ($text === '/start') {
                    $reply = urlencode("Hola. Puedo evaluar posiciones si me envías una cadena FEN, o darte líneas de apertura si me indicas el movimiento inicial. ¿Qué necesitas?");
                    file_get_contents($apiUrl . "sendMessage?chat_id={$chatId}&text={$reply}");
                    echo "Se respondió al comando /start en el chat {$chatId}\n";
                }
            }
        }
    }
}
