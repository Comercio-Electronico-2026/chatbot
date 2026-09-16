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

// Funciones basicas
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


// Integrando groq a la conversacion limitandolo a responder solo en casos especificos

function consultarGroq(string $mensaje): string
{
    $apiKey = getenv('GROQ_API_KEY') ?: ($_ENV['GROQ_API_KEY'] ?? '');

    if (!$apiKey) {
        return "Lo siento, el servicio de asistencia no está disponible en este momento.";
    }

    $datos = [
        'model' => 'openai/gpt-oss-20b',
        'messages' => [
            [
                'role' => 'system',
                'content' =>
                    'Eres el asistente virtual de TechShift, una tienda de comercio electrónico. '
                    . 'Responde siempre en español, de forma breve, clara y amable. '
                    . 'Puedes responder preguntas generales sobre la tienda y orientar al usuario. '
                    . 'No inventes precios, existencias, pedidos ni estados de pedidos. '
                    . 'Para consultar pedidos, pagos o información de inventario, indica al usuario '
                    . 'que debe utilizar las opciones correspondientes del bot.'
            ],
            [
                'role' => 'user',
                'content' => $mensaje
            ]
        ],
        'temperature' => 0.3,
        'max_completion_tokens' => 300
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode($datos),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_TIMEOUT => 30
    ]);

    $respuesta = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($respuesta === false || $error) {
        logAccion('ERROR', "Error al conectar con Groq: {$error}");
        return "Lo siento, no pude procesar tu consulta en este momento.";
    }

    $resultado = json_decode($respuesta, true);

    if (!isset($resultado['choices'][0]['message']['content'])) {
        logAccion('ERROR', 'Groq devolvió una respuesta inesperada: ' . $respuesta);
        return "Lo siento, no pude generar una respuesta.";
    }

    return trim($resultado['choices'][0]['message']['content']);
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

                logAccion(
                     'INFO',
                     "Enviando consulta abierta de '{$nombre}' a Groq: {$texto}"
                );

                $respuesta = consultarGroq($texto);

                enviarMensaje($chatId, $respuesta);

                logAccion(
                     'INFO',
                     "Respuesta de Groq enviada a '{$nombre}'"
                );
            }
        }
    }
}


//Quedo pendiente por completar, la api a pedidos, catalogo y demas funciones, tambien falta por completar la funcionalidad
//de maximo 3 intentos fallidos y pulir las respuestas de la ia de groq para limitarlo y no gastar tokens innecesariamente
