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
echo "🤖 CETBOT iniciado con Long Polling. Esperando mensajes...\n";

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
                
                // Aquí capturamos el nombre real del usuario que escribe
                $sender = $update['message']['from']['first_name'] ?? 'Usuario';

                echo "Mensaje recibido de {$sender} [{$chatId}]: {$text}\n";

                // Respuesta al comando /start
                if (str_starts_with($text, '/start')) {
                    
                    // Mensaje estructurado usando la variable $sender
                    $reply = "¡Hola, {$sender}! 👋 Bienvenido a CET STORE, tu tienda tecnológica de confianza. 🖥️⚡\n\n"
                           . "Soy CETBOT, tu asistente virtual. Estoy aquí para ayudarte a encontrar los mejores componentes para tu setup, verificar tus compras o aclarar cualquier duda que tengas.\n\n"
                           . "¿En qué te puedo colaborar el día de hoy? Por favor, envíame el número de la opción que necesites:\n\n"
                           . "1️⃣ Consultar nuestro catálogo (Laptops, SSDs, Periféricos y más).\n"
                           . "2️⃣ Consultar el estado de tu pedido.\n"
                           . "3️⃣ Conocer nuestros métodos de pago y envíos.";

                    $sendUrl = $apiUrl . "sendMessage";
                    $postData = [
                        'chat_id' => $chatId,
                        'text' => $reply
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
