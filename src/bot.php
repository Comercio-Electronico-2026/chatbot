<?php
$env_path = __DIR__ . '/../.env';
if (!file_exists($env_path)) {
    http_response_code(500);
    exit;
}

$env = file_get_contents($env_path);
preg_match('/BOT_TOKEN=(.*)/', $env, $matches);
$token = trim($matches[1]);
$apiUrl = "https://api.telegram.org/bot{$token}/";

// Recepción vía Webhook
$update = json_decode(file_get_contents('php://input'), true);

if (!$update || !isset($update['message']['text'])) {
    http_response_code(200); // Siempre responder 200 a Telegram
    exit;
}

$chat_id = $update['message']['chat']['id'];
$texto = $update['message']['text'];

// Lógica temporal para probar el Webhook
if ($texto === '/start') {
    $reply = urlencode("Hola. Puedo evaluar posiciones si me envías una cadena FEN, o darte líneas de apertura si me indicas el movimiento inicial. ¿Qué necesitas?");
    file_get_contents($apiUrl . "sendMessage?chat_id={$chat_id}&text={$reply}");
}
