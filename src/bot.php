<?php
// Leer la variable de entorno desde el archivo .env
if (!file_exists('.env')) {
    die("Error: No se encontró el archivo .env. Por favor créalo y añade tu BOT_TOKEN.\n");
}

$config = parse_ini_file('.env');
$token = $config['BOT_TOKEN'] ?? null;

if (!$token) {
    die("Error: BOT_TOKEN no está definido dentro del archivo .env.\n");
}

$apiUrl = "https://api.telegram.org/bot{$token}/";
$offset = 0;

echo "======================================\n";
echo " Bot SM21008 iniciado correctamente\n";
echo " Esperando el comando /start...\n";
echo "======================================\n";

// Bucle infinito para consultar mensajes en tiempo real (Long Polling)
while (true) {
    $response = @file_get_contents($apiUrl . "getUpdates?offset={$offset}&timeout=5");
    
    if ($response === FALSE) {
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
                $nombreUsuario = $update['message']['from']['first_name'] ?? 'Usuario';

                echo "Mensaje recibido de {$nombreUsuario}: '{$text}'\n";

                // Respuesta al comando /start adaptado a Tienda SM21008
                if ($text === '/start') {
                    $respuesta = "¡Hola, {$nombreUsuario}! 👋 Bienvenido a *Tienda SM21008*.\n\n"
                        . "Soy tu asistente de componentes electrónicos. Puedo ayudarte a consultar disponibilidad y precios de memorias RAM, almacenamiento SSD/HDD y más.\n\n"
                        . "Por ejemplo, puedes preguntar:\n"
                        . "• ¿Tienen memorias RAM DDR4 de 16GB?\n"
                        . "• ¿Cuánto cuesta un SSD de 1TB?\n"
                        . "• ¿Tienen almacenamiento para laptop?\n\n"
                        . "Escribe /start cuando quieras volver a ver este menú.";
                    
                    $parametros = http_build_query([
                        'chat_id' => $chatId,
                        'text' => $respuesta,
                        'parse_mode' => 'Markdown'
                    ]);
                    file_get_contents($apiUrl . "sendMessage?{$parametros}");
                    echo "-> Respondiendo a /start enviado a {$nombreUsuario}\n";
                }
            }
        }
    }
    
    sleep(1);
}