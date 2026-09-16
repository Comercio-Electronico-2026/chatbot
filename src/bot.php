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

$storeApiUrl =
    "https://tienda.hv21011.duckdns.org/wp-json/wc/store/v1/products";


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
        error_log("Telegram respondió con HTTP {$httpCode}");
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
        "¡Hola, {$firstName}! Qué gusto verte por MusicHub Bot 🎵.\n\n" .
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
        "👤 /soporte - Consultar atención humana\n" .
        "🚪 /salir - Terminar la interacción\n\n" .
        "También puedes buscar directamente, por ejemplo:\n" .
        "/catalogo Madonna";
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
// Estado temporal de la conversación
// ----------------------------------------------------

function getSessionFile(string|int $chatId): string
{
    $safeChatId = preg_replace(
        '/[^0-9-]/',
        '',
        (string) $chatId
    );

    return sys_get_temp_dir() .
        '/musichub_session_' .
        $safeChatId .
        '.json';
}


function loadSession(string|int $chatId): array
{
    $file = getSessionFile($chatId);

    if (!file_exists($file)) {
        return [
            'state' => 'menu',
            'attempts' => 0,
        ];
    }

    $data = json_decode(
        file_get_contents($file),
        true
    );

    if (!is_array($data)) {
        return [
            'state' => 'menu',
            'attempts' => 0,
        ];
    }

    return array_merge(
        [
            'state' => 'menu',
            'attempts' => 0,
        ],
        $data
    );
}


function saveSession(
    string|int $chatId,
    array $session
): void {
    file_put_contents(
        getSessionFile($chatId),
        json_encode($session),
        LOCK_EX
    );
}


function clearSession(string|int $chatId): void
{
    $file = getSessionFile($chatId);

    if (file_exists($file)) {
        unlink($file);
    }
}


// ----------------------------------------------------
// Utilidades de productos
// ----------------------------------------------------

function decodeWooText(string $text): string
{
    return html_entity_decode(
        $text,
        ENT_QUOTES | ENT_HTML5,
        'UTF-8'
    );
}


function getProductAttributeValues(
    array $product,
    string $attributeName
): array {
    foreach (($product['attributes'] ?? []) as $attribute) {

        $name = decodeWooText(
            $attribute['name'] ?? ''
        );

        if (strcasecmp($name, $attributeName) !== 0) {
            continue;
        }

        $values = [];

        foreach (($attribute['terms'] ?? []) as $term) {
            $value = decodeWooText(
                $term['name'] ?? ''
            );

            if ($value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    return [];
}


function getProductAttributeText(
    array $product,
    string $attributeName
): string {
    $values = getProductAttributeValues(
        $product,
        $attributeName
    );

    if (empty($values)) {
        return 'No disponible';
    }

    return implode(', ', $values);
}


function productMatchesSearch(
    array $product,
    string $term
): bool {
    $productName = decodeWooText(
        $product['name'] ?? ''
    );

    // Buscar por nombre de álbum/producto
    if (stripos($productName, $term) !== false) {
        return true;
    }

    // Buscar por el atributo Artista
    $artists = getProductAttributeValues(
        $product,
        'Artista'
    );

    foreach ($artists as $artist) {
        if (stripos($artist, $term) !== false) {
            return true;
        }
    }

    return false;
}


// ----------------------------------------------------
// WooCommerce REST API
// ----------------------------------------------------

function fetchCatalogProducts(): array
{
    global $storeApiUrl;

    $url = $storeApiUrl . '?' . http_build_query([
        'per_page' => 100,
    ]);

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log(
            'Error al consultar WooCommerce: ' .
            curl_error($ch)
        );

        curl_close($ch);

        return [
            'ok' => false,
            'products' => [],
        ];
    }

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log(
            "WooCommerce respondió con HTTP {$httpCode}"
        );

        return [
            'ok' => false,
            'products' => [],
        ];
    }

    $products = json_decode(
        $response,
        true
    );

    if (!is_array($products)) {
        error_log(
            'WooCommerce devolvió JSON inválido'
        );

        return [
            'ok' => false,
            'products' => [],
        ];
    }

    return [
        'ok' => true,
        'products' => $products,
    ];
}


function searchProducts(string $term): array
{
    $catalog = fetchCatalogProducts();

    if (!$catalog['ok']) {
        return [
            'ok' => false,
            'products' => [],
        ];
    }

    $results = [];

    foreach ($catalog['products'] as $product) {

        if (productMatchesSearch($product, $term)) {
            $results[] = $product;
        }
    }

    return [
        'ok' => true,
        'products' => $results,
    ];
}


// ----------------------------------------------------
// Formatear información de productos
// ----------------------------------------------------

function formatProductPrice(array $product): string
{
    $prices = $product['prices'] ?? [];

    $rawPrice = $prices['price'] ?? null;

    if ($rawPrice === null) {
        return 'No disponible';
    }

    $decimals =
        (int) ($prices['currency_minor_unit'] ?? 2);

    $currency =
        $prices['currency_code'] ?? 'USD';

    $price =
        ((float) $rawPrice) /
        (10 ** $decimals);

    if ($currency === 'USD') {
        return '$' . number_format(
            $price,
            $decimals,
            '.',
            ','
        );
    }

    return
        $currency . ' ' .
        number_format(
            $price,
            $decimals,
            '.',
            ','
        );
}


function formatCatalogResults(array $products): string
{
    // Evitamos enviar mensajes demasiado largos.
    $products = array_slice($products, 0, 5);

    $reply = "🎵 Encontré estos productos:\n";

    foreach ($products as $index => $product) {

        $name = decodeWooText(
            $product['name'] ?? 'Sin nombre'
        );

        $artist = getProductAttributeText(
            $product,
            'Artista'
        );

        $year = getProductAttributeText(
            $product,
            'Año de lanzamiento'
        );

        $format = getProductAttributeText(
            $product,
            'Formato'
        );

        $genre = getProductAttributeText(
            $product,
            'Género'
        );

        $language = getProductAttributeText(
            $product,
            'Idioma'
        );

        $price = formatProductPrice($product);

        $inStock =
            $product['is_in_stock'] ?? false;

        $stock =
            $inStock
                ? 'Disponible'
                : 'No disponible';

        $number = $index + 1;

        $reply .=
            "\n{$number}. {$name}\n" .
            "🎤 Artista: {$artist}\n" .
            "📅 Año: {$year}\n" .
            "💿 Formato: {$format}\n" .
            "🎼 Género: {$genre}\n" .
            "🌐 Idioma: {$language}\n" .
            "💵 Precio: {$price}\n" .
            "📦 Stock: {$stock}\n";
    }

    $reply .=
        "\n¿Deseas hacer otra consulta?\n\n" .
        "🛒 /catalogo - Buscar otro producto\n" .
        "📦 /pedido - Consultar un pedido\n" .
        "🏠 /menu - Volver al menú\n" .
        "🚪 /salir - Terminar";

    return $reply;
}


function performCatalogSearch(string $term): array
{
    $result = searchProducts($term);

    if (!$result['ok']) {
        return [
            'status' => 'error',
            'text' =>
                "En este momento no puedo consultar " .
                "el catálogo de la tienda.\n\n" .
                "Puedes intentarlo nuevamente o " .
                "escribir /menu.",
        ];
    }

    if (empty($result['products'])) {
        return [
            'status' => 'empty',
            'text' =>
                "No encontré productos relacionados " .
                "con \"{$term}\".\n\n" .
                "Puedes intentar con el nombre del álbum " .
                "o del artista.",
        ];
    }

    return [
        'status' => 'success',
        'text' => formatCatalogResults(
            $result['products']
        ),
    ];
}


// ----------------------------------------------------
// Inicio mediante long polling
// ----------------------------------------------------

echo "MusicHub Bot iniciado. Esperando mensajes...\n";

$updateId = 0;

while (true) {

    $response = telegramRequest('getUpdates', [
        'offset' => $updateId + 1,
        'timeout' => 30,
    ]);

    if ($response === null) {
        echo
            "No se pudo consultar Telegram. " .
            "Reintentando...\n";

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
        $from =
            $update['message']['from'] ?? [];

        $firstName =
            trim($from['first_name'] ?? '');

        if ($firstName === '') {
            $firstName = 'Usuario';
        }

        $username =
            $from['username'] ?? null;

        $logUser = $username
            ? $firstName . ' (@' . $username . ')'
            : $firstName;

        echo
            "Recibido de {$logUser}: " .
            "{$message}\n";


        // ------------------------------------------------
        // Separar comando y argumento
        // ------------------------------------------------

        $parts = preg_split(
            '/\s+/',
            $message,
            2
        );

        $command =
            strtolower($parts[0] ?? '');

        $argument =
            trim($parts[1] ?? '');


        // ------------------------------------------------
        // Comandos globales
        // ------------------------------------------------

        if ($command === '/start') {

            clearSession($chatId);

            sendMessage(
                $chatId,
                getMenuText($firstName)
            );

            echo
                "Enviado menú principal a {$logUser}\n";

            continue;
        }


        if ($command === '/menu') {

            clearSession($chatId);

            sendMessage(
                $chatId,
                getMenuText($firstName)
            );

            echo
                "Enviado menú principal a {$logUser}\n";

            continue;
        }


        if ($command === '/ayuda') {

            sendMessage(
                $chatId,
                getHelpText()
            );

            echo
                "Enviada ayuda a {$logUser}\n";

            continue;
        }


        if ($command === '/soporte') {

            clearSession($chatId);

            sendMessage(
                $chatId,
                getSupportText()
            );

            echo
                "Enviada información de soporte a {$logUser}\n";

            continue;
        }


        if ($command === '/salir') {

            clearSession($chatId);

            $reply =
                "¡Hasta luego, {$firstName}! 🎵\n\n" .
                "Gracias por usar MusicHub Bot.\n" .
                "Puedes escribir /start cuando quieras volver.";

            sendMessage(
                $chatId,
                $reply
            );

            echo
                "Finalizada interacción con {$logUser}\n";

            continue;
        }


        // ------------------------------------------------
        // /catalogo
        // ------------------------------------------------

        if ($command === '/catalogo') {

            clearSession($chatId);

            // Ejemplo:
            // /catalogo Chloe
            //
            // Como el dato ya viene incluido,
            // no volvemos a solicitarlo.

            if ($argument !== '') {

                echo
                    "Buscando en catálogo: {$argument}\n";

                $result =
                    performCatalogSearch($argument);

                sendMessage(
                    $chatId,
                    $result['text']
                );

                if ($result['status'] !== 'success') {
                    saveSession(
                        $chatId,
                        [
                            'state' => 'awaiting_catalog',
                            'attempts' => 1,
                        ]
                    );
                }

                continue;
            }

            saveSession(
                $chatId,
                [
                    'state' => 'awaiting_catalog',
                    'attempts' => 0,
                ]
            );

            sendMessage(
                $chatId,
                "Escribe el nombre del artista " .
                "o álbum que deseas buscar.\n\n" .
                "Por ejemplo: Chloe"
            );

            echo
                "Esperando búsqueda de catálogo de {$logUser}\n";

            continue;
        }


        // ------------------------------------------------
        // /pedido - lo implementaremos después
        // ------------------------------------------------

        if ($command === '/pedido') {

            clearSession($chatId);

            sendMessage(
                $chatId,
                "La consulta de pedidos será " .
                "implementada en el siguiente bloque."
            );

            echo
                "Solicitado pedido por {$logUser}\n";

            continue;
        }


        // ------------------------------------------------
        // Estado actual de conversación
        // ------------------------------------------------

        $session = loadSession($chatId);


        // ------------------------------------------------
        // Esperando búsqueda de catálogo
        // ------------------------------------------------

        if ($session['state'] === 'awaiting_catalog') {

            // Un mensaje vacío o un comando desconocido
            // no debe usarse como término de búsqueda.

            if (
                $message === '' ||
                str_starts_with($message, '/')
            ) {

                $session['attempts']++;

                if ($session['attempts'] >= 3) {

                    clearSession($chatId);

                    sendMessage(
                        $chatId,
                        "Parece que estamos teniendo problemas " .
                        "con la búsqueda.\n\n" .
                        "Puedes escribir /menu para volver " .
                        "al menú o /soporte para recibir ayuda."
                    );

                    echo
                        "Máximo de intentos en catálogo " .
                        "para {$logUser}\n";

                    continue;
                }

                saveSession(
                    $chatId,
                    $session
                );

                sendMessage(
                    $chatId,
                    "Necesito el nombre de un artista " .
                    "o álbum.\n\n" .
                    "Por ejemplo: Chloe x Halle"
                );

                continue;
            }


            echo
                "Buscando en catálogo: {$message}\n";

            $result =
                performCatalogSearch($message);


            // Producto encontrado
            if ($result['status'] === 'success') {

                clearSession($chatId);

                sendMessage(
                    $chatId,
                    $result['text']
                );

                echo
                    "Consulta de catálogo completada " .
                    "para {$logUser}\n";

                continue;
            }


            // Fallo del servicio REST
            if ($result['status'] === 'error') {

                sendMessage(
                    $chatId,
                    $result['text']
                );

                echo
                    "Fallo de API durante consulta " .
                    "de {$logUser}\n";

                continue;
            }


            // No hubo resultados
            $session['attempts']++;

            if ($session['attempts'] >= 3) {

                clearSession($chatId);

                sendMessage(
                    $chatId,
                    $result['text'] .
                    "\n\nYa realizaste varios intentos " .
                    "sin resultados.\n\n" .
                    "Puedes escribir /menu o /soporte."
                );

                echo
                    "Máximo de búsquedas sin resultados " .
                    "para {$logUser}\n";

                continue;
            }

            saveSession(
                $chatId,
                $session
            );

            sendMessage(
                $chatId,
                $result['text'] .
                "\n\nEscribe otro término " .
                "para volver a intentar."
            );

            echo
                "Búsqueda sin resultados de {$logUser}\n";

            continue;
        }


        // ------------------------------------------------
        // Entrada no reconocida
        // ------------------------------------------------

        $session['attempts']++;

        if ($session['attempts'] >= 3) {

            clearSession($chatId);

            sendMessage(
                $chatId,
                "No pude reconocer tus últimos mensajes.\n\n" .
                "Escribe /menu para volver al menú " .
                "o /soporte si necesitas ayuda."
            );

            echo
                "Máximo de entradas no reconocidas " .
                "de {$logUser}\n";

            continue;
        }

        saveSession(
            $chatId,
            $session
        );

        sendMessage(
            $chatId,
            "No pude reconocer esa opción.\n\n" .
            "Escribe /ayuda para ver lo que puedo hacer."
        );

        echo
            "Entrada no reconocida de {$logUser}\n";
    }

    sleep(1);
}
