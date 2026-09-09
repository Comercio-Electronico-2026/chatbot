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
echo " Bot de Telegram iniciado correctamente\n";
echo " Escuchando consultas de la Tienda Gaming...\n";
echo "======================================\n";

// Bucle infinito para consultar mensajes en tiempo real
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
                $textLower = strtolower($text);
                $nombreUsuario = $update['message']['from']['first_name'] ?? 'Usuario';

                echo "Mensaje recibido de {$nombreUsuario}: '{$text}'\n";

                // Evaluador de Intenciones
                if ($textLower === '/start') {
                    $respuesta = "¡Hola, {$nombreUsuario}! 👋 Bienvenido a nuestra tienda de accesorios de tecnología y gaming. 🎮\n\n"
                        . "Puedo ayudarte a consultar precios, catálogo y disponibilidad de nuestros productos.\n\n"
                        . "Por ejemplo, puedes preguntar:\n"
                        . "- ¿Tienen audífonos bluetooth?\n"
                        . "- ¿Cuánto cuesta el teclado mecánico?\n"
                        . "- Escribe /catalogo para ver todos los productos\n"
                        . "- Escribe /help para ver las opciones de ayuda";
                } 
                elseif ($textLower === '/catalogo' || strpos($textLower, 'catalogo') !== false) {
                    $respuesta = "🛍️ *Catálogo de Productos Disponibles:*\n\n"
                        . "1. *Audífonos Inalámbricos Bluetooth Pro* - $40.00 (Disponible)\n"
                        . "2. *Hub USB-C Multiport 7 en 1* - $38.00 (Disponible)\n"
                        . "3. *Mouse Gamer Óptico 7200 DPI* - $28.00 (Disponible)\n"
                        . "4. *Teclado Mecánico Gaming RGB* - $65.00 (Disponible)\n\n"
                        . "¿Deseas información de algún producto en específico?";
                } 
                elseif (strpos($textLower, 'audifono') !== false || strpos($textLower, 'bluetooth') !== false) {
                    $respuesta = "🎧 *Audífonos Inalámbricos Bluetooth Pro*\n• Precio: *$40.00*\n• Estado: *Disponible en stock*";
                } 
                elseif (strpos($textLower, 'hub') !== false || strpos($textLower, 'usb') !== false) {
                    $respuesta = "🔌 *Hub USB-C Multiport 7 en 1*\n• Precio: *$38.00*\n• Estado: *Disponible en stock*";
                } 
                elseif (strpos($textLower, 'mouse') !== false || strpos($textLower, 'raton') !== false) {
                    $respuesta = "🖱️ *Mouse Gamer Óptico 7200 DPI*\n• Precio: *$28.00*\n• Estado: *Disponible en stock*";
                } 
                elseif (strpos($textLower, 'teclado') !== false) {
                    $respuesta = "⌨️ *Teclado Mecánico Gaming RGB*\n• Precio: *$65.00*\n• Estado: *Disponible en stock*";
                } 
                elseif ($textLower === '/help' || strpos($textLower, 'ayuda') !== false) {
                    $respuesta = "📌 *Guía de Comandos y Ayuda:*\n\n"
                        . "• `/catalogo` - Lista completa de productos\n"
                        . "• Escribe el nombre de un periférico (ej: *audifonos*, *teclado*, *mouse*, *hub*)\n"
                        . "• `/start` - Menú de bienvenida";
                } 
                elseif (strpos($textLower, 'hola') !== false) {
                    $respuesta = "¡Hola, {$nombreUsuario}! ¿En qué te puedo ayudar hoy? Escribe `/catalogo` para ver nuestros periféricos.";
                } 
                else {
                    $respuesta = "Lo siento, no entendí tu consulta. 🤔\n\nPrueba escribiendo `/catalogo` para ver nuestros productos o `/help` para recibir ayuda.";
                }

                // Enviar respuesta a Telegram
                $parametros = http_build_query([
                    'chat_id' => $chatId,
                    'text' => $respuesta,
                    'parse_mode' => 'Markdown'
                ]);
                
                @file_get_contents($apiUrl . "sendMessage?{$parametros}");
                echo "-> Respuesta enviada a {$nombreUsuario}\n";
            }
        }
    }
    sleep(1);
}
?>
EOF
