<?php

//EXTRAER TOKEN

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

$botToken = getenv('BOT_TOKEN') ?: ($_ENV['BOT_TOKEN'] ?? '');

if (!$botToken) {
    die("❌ Error: No se encontró la variable BOT_TOKEN en el archivo .env\n");
}

$apiUrl = "https://api.telegram.org/bot{$botToken}/";



// FUNCIONES DEL BOT

function telegram(string $method, array $data = []): ?array
{
    global $apiUrl;

    $ch = curl_init($apiUrl . $method);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => !empty($data),
        CURLOPT_POSTFIELDS => !empty($data) ? http_build_query($data) : null,
        CURLOPT_TIMEOUT => 40
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response ? json_decode($response, true) : null;
}

function enviarMensaje(int $chatId, string $texto): void
{
    telegram('sendMessage', [
        'chat_id' => $chatId,
        'text' => $texto
    ]);
}

function logAccion(string $nivel, string $mensaje): void
{
    $iconos = [
        'INFO' => '📩',
        'WARN' => '⚠️',
        'ERROR' => '❌',
        'SUCCESS' => '✅'
    ];

    $icono = $iconos[$nivel] ?? 'ℹ️';

    echo "{$icono} [{$nivel}] {$mensaje}" . PHP_EOL;
}

function mostrarMenu(int $chatId, string $nombre): void
{
    $mensaje = "¡Hola {$nombre}! 👋\n\n"
             . "Bienvenido al asistente virtual de TechShift.\n\n"
             . "Opciones disponibles:\n"
             . "📦 /pedido - Consultar pedido\n"
             . "🛍️ /catalogo - Ver catálogo\n"
             . "❓ /help - Ayuda\n"
             . "👤 /soporte - Hablar con soporte\n"
             . "❌ /cancel - Cancelar\n\n"
             . "¿En qué te puedo ayudar?";

    enviarMensaje($chatId, $mensaje);
}


// CONEXION DEL BOT CON EL TOKEN DE TELEGRAM

$me = telegram('getMe');

if (!$me || !($me['ok'] ?? false)) {
    die("❌ Error: Token inválido o problema al conectar con Telegram.\n");
}

echo "✅ Bot conectado exitosamente: @"
   . $me['result']['username']
   . " (ID: "
   . $me['result']['id']
   . ")\n";

echo "📡 Escuchando mensajes con Long Polling (Presiona Ctrl + C para salir)...\n\n";


// LONG POLLING

$offset = 0;

while (true) {

    $data = telegram('getUpdates', [
        'timeout' => 30,
        'offset' => $offset
    ]);

    if (!$data || !isset($data['result'])) {
        sleep(2);
        continue;
    }

    foreach ($data['result'] as $update) {

        $offset = $update['update_id'] + 1;

        if (!isset($update['message']['text'])) {
            continue;
        }

        $message = $update['message'];

        $chatId = $message['chat']['id'];
        $nombre = $message['from']['first_name'] ?? 'Usuario';
        $texto = trim($message['text']);

        // Registrar entrada
        logAccion(
            'INFO',
            "Usuario '{$nombre}' (Chat ID: {$chatId}): {$texto}"
        );

        // COMANDOS

        if ($texto === '/start') {

            mostrarMenu($chatId, $nombre);

            logAccion(
                'INFO',
                "Respondido /start al usuario '{$nombre}' (Chat ID: {$chatId})"
            );

        } elseif ($texto === '/help') {

            enviarMensaje(
                $chatId,
                "❓ Ayuda\n\n"
                . "/start - Mostrar menú\n"
                . "/pedido - Consultar pedido\n"
                . "/catalogo - Ver catálogo\n"
                . "/soporte - Contactar soporte\n"
                . "/cancel - Cancelar operación"
            );

        } elseif ($texto === '/cancel') {

            enviarMensaje(
                $chatId,
                "❌ Operación cancelada. Usa /start para volver al menú."
            );

        } elseif ($texto === '/soporte') {

            enviarMensaje(
                $chatId,
                "👤 Has solicitado soporte.\n"
                . "Un asesor podrá ayudarte con tu solicitud."
            );

        } elseif ($texto === '/pedido') {

            enviarMensaje(
                $chatId,
                "📦 Escribe tu número de pedido de 4 dígitos."
            );

        } elseif ($texto === '/catalogo') {

            // Aqui el servicio REST del catalogo (lo programare mas adelante).
            enviarMensaje(
                $chatId,
                "🛍️ Consultando catálogo..."
            );

        } else {

            // Validacion basica de número de pedido
            if (preg_match('/^\d{4}$/', $texto)) {

                enviarMensaje(
                    $chatId,
                    "📦 Consultando el pedido {$texto}..."
                );

                logAccion(
                    'INFO',
                    "Consulta de pedido {$texto} realizada por '{$nombre}' (Chat ID: {$chatId})"
                );

            } else {

                enviarMensaje(
                    $chatId,
                    "🤔 No entendí tu solicitud.\n\n"
                    . "Usa /help para ver las opciones disponibles."
                );

                logAccion(
                    'WARN',
                    "Entrada no reconocida de '{$nombre}': {$texto}"
                );
            }
        }
    }
}
