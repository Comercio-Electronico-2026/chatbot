<?php

// Leer el token desde .env
$env = parse_ini_file(__DIR__ . '/../.env');

$token = $env['BOT_TOKEN'] ?? null;

if (!$token) {
    exit("Error: no se encontro BOT_TOKEN en el archivo .env\n");
}

$apiUrl = "https://api.telegram.org/bot{$token}/";

// Offset para no procesar el mismo mensaje varias veces
$offset = 0;

echo "NutriGuia iniciado.\n";
echo "Esperando mensajes...\n";

while (true) {

    // Consultar nuevos mensajes
    $url = $apiUrl . "getUpdates?" . http_build_query([
        "offset" => $offset,
        "timeout" => 25
    ]);

    $response = file_get_contents($url);

    if ($response === false) {
        echo "Error consultando Telegram.\n";
        sleep(2);
        continue;
    }

    $data = json_decode($response, true);

    foreach ($data["result"] ?? [] as $update) {

        // Avanzar al siguiente mensaje
        $offset = $update["update_id"] + 1;

        // Ignorar actualizaciones que no sean mensajes
        if (!isset($update["message"])) {
            continue;
        }

        $chatId = $update["message"]["chat"]["id"];
        $texto = trim($update["message"]["text"] ?? "");

        echo "Mensaje recibido: {$texto}\n";

        // Responder al comando /start
        if ($texto === "/start") {

            $mensaje =
                "¡Hola! Bienvenido a NutriGuia.\n\n" .
                "Soy tu Asesor Virtual de Nutricion Natural.\n\n";

            $sendUrl = $apiUrl . "sendMessage?" . http_build_query([
                "chat_id" => $chatId,
                "text" => $mensaje
            ]);

            file_get_contents($sendUrl);
        }
    }
}