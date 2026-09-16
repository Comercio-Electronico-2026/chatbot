<?php

// ----------------------------------------------------
// Configuración
// ----------------------------------------------------

$envPath = dirname(__DIR__) . '/.env';

if (!file_exists($envPath)) {
    exit("Error: no se encontró el archivo .env\n");
}

$env = parse_ini_file($envPath);

if (!$env || empty($env['BOT_TOKEN'])) {
    exit("Error: BOT_TOKEN no está configurado\n");
}

$token = $env['BOT_TOKEN'];
$apiUrl = "https://api.telegram.org/bot" . $token;


// ----------------------------------------------------
// Funciones de Telegram
// ----------------------------------------------------

function telegramRequest(string $method, array $params = []): ?array
{
    global $apiUrl;

    $ch = curl_init($apiUrl . '/' . $method);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 40,
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log(
            'Error de conexión con Telegram: ' .
            curl_error($ch)
        );

        curl_close($ch);
        return null;
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log(
            "Telegram respondió con HTTP $httpCode"
        );

        return null;
    }

    $data = json_decode($response, true);

    if (!is_array($data) || !($data['ok'] ?? false)) {
        error_log('Respuesta no válida de Telegram');
        return null;
    }

    return $data;
}


function sendMessage(string|int $chatId, string $text): bool
{
    $response = telegramRequest('sendMessage', [
        'chat_id' => $chatId,
        'text' => $text,
    ]);

    return $response !== null;
}


// ----------------------------------------------------
// Textos reutilizables
// ----------------------------------------------------

function getMenuText(string $firstName): string
{
    return
        "¡Hola, {$firstName}! Es un gusto tener hoy en MusicHub Bot 🎵.\n\n" .
        "Puedo ayudarte a buscar discos y vinilos " .
        "o consultar el estado de un pedido.\n\n" .
        "🛒 /catalogo - Buscar discos y vinilos\n" .
        "📦 /pedido - Consultar un pedido\n" .
        "❓ /ayuda - Ver las opciones disponibles\n" .
        "👤 /soporte - Atención humana\n" .
        "🚪 /salir - Terminar";
}


function getHelpText(): string
{
    return
        "Estas son las opciones disponibles:\n\n" .
        "🛒 /catalogo - Buscar discos y vinilos\n" .
        "📦 /pedido - Consultar el estado de un pedido\n" .
        "🏠 /menu - Volver al menú principal\n" .
        "👤 /soporte - Consultar las opciones de atención humana\n" .
        "🚪 /salir - Terminar la interacción";
}


function getSupportText(): string
{
    return
        "👤 Atención humana\n\n" .
        "Si tu consulta no puede ser resuelta por el bot, " .
        "puedes recurrir al canal de atención de MusicHub.\n\n" .
        "El medio de contacto será configurado para la tienda.\n\n" .
        "También puedes escribir /menu para volver al menú principal.";
}


// ----------------------------------------------------
// Inicio del bot mediante long polling
// ----------------------------------------------------

echo "MusicHub Bot iniciado. Esperando mensajes...\n";

$updateId = 0;

while (true) {

    $response = telegramRequest('getUpdates', [
        'offset' => $updateId + 1,
        'timeout' => 30,
    ]);

    if ($response === null) {
        echo "No se pudo consultar Telegram. Reintentando...\n";
        sleep(3);
        continue;
    }

    foreach ($response['result'] as $update) {

        $updateId = $update['update_id'];

        $message = trim(
            $update['message']['text'] ?? ''
        );

        $chatId =
            $update['message']['chat']['id'] ?? null;

        if ($chatId === null) {
            continue;
        }

        // Datos básicos del usuario
        $from = $update['message']['from'] ?? [];

        $firstName = trim($from['first_name'] ?? '');

        if ($firstName === '') {
            $firstName = 'Usuario';
        }

        $username = $from['username'] ?? null;

        $logUser = $username
            ? $firstName . ' (@' . $username . ')'
            : $firstName;

        echo "Recibido de {$logUser}: {$message}\n";


        // ------------------------------------------------
        // /start
        // ------------------------------------------------

        if ($message === '/start') {

            $reply = getMenuText($firstName);

            sendMessage($chatId, $reply);

            echo "Enviado menú principal a {$logUser}\n";
            continue;
        }


        // ------------------------------------------------
        // /menu
        // ------------------------------------------------

        if ($message === '/menu') {

            $reply = getMenuText($firstName);

            sendMessage($chatId, $reply);

            echo "Enviado menú principal a {$logUser}\n";
            continue;
        }


        // ------------------------------------------------
        // /ayuda
        // ------------------------------------------------

        if ($message === '/ayuda') {

            $reply = getHelpText();

            sendMessage($chatId, $reply);

            echo "Enviada ayuda a {$logUser}\n";
            continue;
        }


        // ------------------------------------------------
        // /soporte
        // ------------------------------------------------

        if ($message === '/soporte') {

            $reply = getSupportText();

            sendMessage($chatId, $reply);

            echo "Enviada información de soporte a {$logUser}\n";
            continue;
        }


        // ------------------------------------------------
        // /salir
        // ------------------------------------------------

        if ($message === '/salir') {

            $reply =
                "¡Hasta luego, {$firstName}! 🎵\n\n" .
                "Gracias por usar MusicHub Bot.\n" .
                "Puedes escribir /start cuando quieras volver.";

            sendMessage($chatId, $reply);

            echo "Finalizada interacción con {$logUser}\n";
            continue;
        }


        // ------------------------------------------------
        // Comandos que implementaremos después
        // ------------------------------------------------

        if ($message === '/catalogo') {

            sendMessage(
                $chatId,
                "La consulta de catálogo será implementada en el siguiente paso."
            );

            echo "Solicitado catálogo por {$logUser}\n";
            continue;
        }


        if ($message === '/pedido') {

            sendMessage(
                $chatId,
                "La consulta de pedidos será implementada próximamente."
            );

            echo "Solicitado pedido por {$logUser}\n";
            continue;
        }


        // ------------------------------------------------
        // Entrada todavía no reconocida
        // ------------------------------------------------

        $reply =
            "No pude reconocer esa opción.\n\n" .
            "Escribe /ayuda para ver lo que puedo hacer.";

        sendMessage($chatId, $reply);

        echo "Entrada no reconocida de {$logUser}\n";
    }

    sleep(1);
}
