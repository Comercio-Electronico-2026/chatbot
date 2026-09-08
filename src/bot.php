<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Cargar variables desde .env
|--------------------------------------------------------------------------
*/

$envPath = dirname(__DIR__) . '/.env';

if (!file_exists($envPath)) {
    fwrite(STDERR, "Error: no existe el archivo .env\n");
    exit(1);
}

$lineas = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lineas as $linea) {

    $linea = trim($linea);

    if ($linea === '' || str_starts_with($linea, '#')) {
        continue;
    }

    [$clave, $valor] = array_map(
        'trim',
        explode('=', $linea, 2)
    );

    putenv("$clave=$valor");
}


/*
|--------------------------------------------------------------------------
| Configuración
|--------------------------------------------------------------------------
*/

$token = getenv('BOT_TOKEN');

if (!$token) {
    fwrite(STDERR, "Error: BOT_TOKEN no está definido.\n");
    exit(1);
}

$api = "https://api.telegram.org/bot{$token}";

$offset = 0;


/*
|--------------------------------------------------------------------------
| Función para enviar mensajes
|--------------------------------------------------------------------------
*/

function enviarMensaje(
    string $api,
    int|string $chatId,
    string $mensaje
): void {

    $curl = curl_init("{$api}/sendMessage");

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'chat_id' => $chatId,
            'text' => $mensaje
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15
    ]);

    $respuesta = curl_exec($curl);

    if ($respuesta === false) {
        fwrite(
            STDERR,
            "Error enviando mensaje: " . curl_error($curl) . PHP_EOL
        );
    }

    curl_close($curl);
}


/*
|--------------------------------------------------------------------------
| Long polling
|--------------------------------------------------------------------------
*/

echo "FerreBot iniciado..." . PHP_EOL;
echo "Presiona Ctrl+C para detenerlo." . PHP_EOL;

while (true) {

    $url = "{$api}/getUpdates?timeout=30&offset={$offset}";

    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35
    ]);

    $respuesta = curl_exec($curl);

    if ($respuesta === false) {

        fwrite(
            STDERR,
            "Error consultando Telegram: " . curl_error($curl) . PHP_EOL
        );

        curl_close($curl);
        sleep(2);
        continue;
    }

    curl_close($curl);

    $datos = json_decode($respuesta, true);

    if (
        !is_array($datos) ||
        !isset($datos['ok']) ||
        $datos['ok'] !== true
    ) {
        fwrite(STDERR, "Respuesta inválida de Telegram.\n");
        sleep(2);
        continue;
    }

    foreach ($datos['result'] as $actualizacion) {

        $updateId = $actualizacion['update_id'];

        // Evita procesar nuevamente la misma actualización.
        $offset = $updateId + 1;

        if (!isset($actualizacion['message'])) {
            continue;
        }

        $mensaje = $actualizacion['message'];

        $chatId = $mensaje['chat']['id'] ?? null;
        $texto = trim($mensaje['text'] ?? '');

        if ($chatId === null) {
            continue;
        }

        /*
        |--------------------------------------------------------------------------
        | Comando /start
        |--------------------------------------------------------------------------
        */

        if ($texto === '/start') {

            $bienvenida =
                "¡Hola! Soy FerreBot 🔧\n\n" .
                "Puedo ayudarte a buscar productos de la ferretería " .
                "y consultar sus precios.\n\n" .
                "Por el momento estoy en mi primera versión.";

            enviarMensaje(
                $api,
                $chatId,
                $bienvenida
            );

            echo "Respondí /start al chat {$chatId}" . PHP_EOL;
        }
    }
}
