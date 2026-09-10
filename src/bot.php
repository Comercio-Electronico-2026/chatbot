<?php
//Leer la variable de entorno desde el archivo .env
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
echo " Bot de Telegram iniciado correctamente\n";
echo " Esperando el comando /start...\n";
echo "======================================\n";

// Bucle infinito para consultar mensajes en tiempo real
while (true) {
    // Consultar mensajes nuevos enviando el offset actual
    $response = @file_get_contents($apiUrl . "getUpdates?offset={$offset}&timeout=5");
    
    if ($response === FALSE) {
        sleep(2);
        continue;
    }
    
    $data = json_decode($response, true);

    if (!empty($data['result'])) {
        foreach ($data['result'] as $update) {
            // Actualizar offset para no procesar el mismo mensaje dos veces
            $offset = $update['update_id'] + 1;

            // Verificar si el update contiene un mensaje de texto
            if (isset($update['message']['text'])) {
                $chatId = $update['message']['chat']['id'];
                $text = trim($update['message']['text']);
                $nombreUsuario = $update['message']['from']['first_name'] ?? 'Usuario';

                echo "Mensaje recibido de {$nombreUsuario}: '{$text}'\n";

                // 3. Responder al comando /start
                if ($text === '/start') {
                    $respuesta = "¡Hola, {$nombreUsuario}! 👋 Bienvenido a la tienda de computadoras.\n\n"
                        . "Puedo ayudarte a consultar productos, precios y disponibilidad.\n\n"
                        . "Por ejemplo, puedes preguntar:\n"
                        . "- ¿Tienen laptops?\n"
                        . "- ¿Cuánto cuesta una memoria RAM?\n"
                        . "- Necesito una computadora para estudiar.\n\n"
                        . "En la Sesión 2 agregaré las consultas al catálogo. "
                        . "Escribe /start cuando quieras volver a ver este menú.";
                    
                    $parametros = http_build_query([
                        'chat_id' => $chatId,
                        'text' => $respuesta,
                    ]);
                    file_get_contents($apiUrl . "sendMessage?{$parametros}");
                    echo "-> Respondiendo a /start enviado a {$nombreUsuario}\n";
                }
            }
        }
    }
    
    // Pausa breve de 1 segundo para no sobrecargar CPU
    sleep(1);
}
