<?php
// Leemos el token de forma segura desde tu carpeta privada
$env = parse_ini_file('/home/sp21013/chatbot/.env');
$token = $env['BOT_TOKEN'];
$apiURL = "https://api.telegram.org/bot$token/";

// Recibimos los datos que Telegram envía automáticamente (Webhook)
$update = json_decode(file_get_contents("php://input"), true);

// Verificamos que el mensaje exista para evitar errores
if (isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];
    $message = strtolower($update["message"]["text"]);

    if ($message == "/start") {
        $reply = "¡Hola! Soy tu bot automático de Postres SP21013. ¡Bienvenido!";
        file_get_contents($apiURL . "sendMessage?chat_id=$chat_id&text=" . urlencode($reply));
    }
}
?>
