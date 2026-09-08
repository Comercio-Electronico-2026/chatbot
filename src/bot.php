<?php
$envFile = dirname(__DIR__) . '/.env';
if (!file_exists($envFile)) {
    die("Error: No se encontró el archivo .env\n");
}
$env = parse_ini_file($envFile);
$token = $env['BOT_TOKEN'] ?? null;
if (!$token) {
    die("Error: BOT_TOKEN no definido en .env\n");
}

$apiUrl = "https://api.telegram.org/bot{$token}/";
echo "🤖 Michu Bot iniciado con Long Polling. Esperando mensajes...\n";

$lastUpdateId = 0;
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
                $sender = $update['message']['from']['first_name'] ?? 'Amigo';

                echo "Mensaje recibido de {$sender} [{$chatId}]: {$text}\n";

                $reply = '';

                if (str_starts_with($text, '/start')) {
                    $reply = "¡Hola {$sender}! 🐱 Soy *Michu*, tu asistente virtual de *Tienda Michuno Gatuno*.\n\n"
                           . "Te ayudo a encontrar los mejores accesorios para tu michi.\n\n"
                           . "📦 Escribe */catalogo* para consultar opciones.\n"
                           . "🚚 Escribe */envios* para zonas de entrega.\n"
                           . "❓ Escribe */ayuda* para más información.";
                } elseif (str_starts_with($text, '/envios')) {
                    $reply = "🚚 *Información de Envíos - Tienda Michuno Gatuno*\n\n"
                           . "• Realizamos entregas en todo El Salvador.\n"
                           . "• Tiempo estimado en el Área Metropolitana: 24 a 48 horas hábiles.\n"
                           . "• Tarifas departamentales varían según cobertura local.\n\n"
                           . "Para consultas directas o pedidos visita:\nhttps://tiendahb21009.duckdns.org";
                } elseif (str_starts_with($text, '/ayuda')) {
                    $reply = "❓ *Comandos disponibles en Michu Bot*\n\n"
                           . "• */start* - Reinicia la conversación y menú principal.\n"
                           . "• */catalogo* - Explorar productos felinos (en construcción para Sesión 2).\n"
                           . "• */envios* - Conocer cobertura y tiempos de despacho.\n"
                           . "• */cancelar* - Detener o reiniciar la interacción actual.";
                } elseif (str_starts_with($text, '/cancelar')) {
                    $reply = "¡Operación cancelada! 🐾 Si deseas volver a comenzar, escribe */start*.";
                }

                if (!empty($reply)) {
                    $sendUrl = $apiUrl . "sendMessage";
                    $postData = [
                        'chat_id' => $chatId,
                        'text' => $reply,
                        'parse_mode' => 'Markdown'
                    ];
                    $opts = [
                        'http' => [
                            'method' => 'POST',
                            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                            'content' => http_build_query($postData)
                        ]
                    ];
                    file_get_contents($sendUrl, false, stream_context_create($opts));
                }
            }
        }
    }
}
