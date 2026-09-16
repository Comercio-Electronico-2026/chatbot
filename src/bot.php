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

$wcConsumerKey =
    $env['WC_CONSUMER_KEY'] ?? '';

$wcConsumerSecret =
    $env['WC_CONSUMER_SECRET'] ?? '';

$storeApiUrl =
    "https://tienda.hv21011.duckdns.org/wp-json/wc/store/v1/products";

$wooApiUrl =
    "https://tienda.hv21011.duckdns.org/wp-json/wc/v3";


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

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log(
            "Telegram respondió con HTTP {$httpCode}"
        );

        return null;
    }

    $data = json_decode($response, true);

    if (!is_array($data) || !($data['ok'] ?? false)) {
        error_log(
            'Respuesta no válida de Telegram'
        );

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
        "/catalogo Chloe\n" .
        "/pedido 17";
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

    if (stripos($productName, $term) !== false) {
        return true;
    }

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
// WooCommerce Store API - Catálogo
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
// WooCommerce REST API - Pedidos
// ----------------------------------------------------

function getOrderStatusText(string $status): string
{
    $statuses = [
        'pending'        => 'Pendiente de pago',
        'processing'     => 'Procesando',
        'on-hold'        => 'En espera',
        'completed'      => 'Completado',
        'cancelled'      => 'Cancelado',
        'refunded'       => 'Reembolsado',
        'failed'         => 'Fallido',
        'checkout-draft' => 'Borrador',
    ];

    return $statuses[$status]
        ?? ucfirst(str_replace('-', ' ', $status));
}


function fetchOrder(int $orderId): array
{
    global $wooApiUrl;
    global $wcConsumerKey;
    global $wcConsumerSecret;

    if (
        $wcConsumerKey === '' ||
        $wcConsumerSecret === ''
    ) {
        error_log(
            'Las credenciales de WooCommerce no están configuradas'
        );

        return [
            'status' => 'error',
        ];
    }

    $url =
        $wooApiUrl .
        '/orders/' .
        $orderId .
        '?_fields=id,status,date_created';

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD =>
            $wcConsumerKey . ':' . $wcConsumerSecret,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);

    if ($response === false) {
        error_log(
            'Error al consultar pedido: ' .
            curl_error($ch)
        );

        curl_close($ch);

        return [
            'status' => 'error',
        ];
    }

    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

    curl_close($ch);

    if ($httpCode === 404) {
        return [
            'status' => 'not_found',
        ];
    }

    if ($httpCode < 200 || $httpCode >= 300) {
        error_log(
            "WooCommerce Orders respondió HTTP {$httpCode}"
        );

        return [
            'status' => 'error',
        ];
    }

    $order = json_decode(
        $response,
        true
    );

    if (
        !is_array($order) ||
        empty($order['id'])
    ) {
        return [
            'status' => 'error',
        ];
    }

    return [
        'status' => 'success',
        'order' => $order,
    ];
}


function performOrderSearch(string $value): array
{
    $value = trim($value);

    if (
        $value === '' ||
        !ctype_digit($value) ||
        (int) $value <= 0
    ) {
        return [
            'status' => 'invalid',
            'text' =>
                "El número de pedido debe contener " .
                "solamente números.\n\n" .
                "Por ejemplo: 17",
        ];
    }

    $orderId = (int) $value;

    $result = fetchOrder($orderId);

    if ($result['status'] === 'not_found') {
        return [
            'status' => 'not_found',
            'text' =>
                "No encontré un pedido con el número " .
                "#{$orderId}.\n\n" .
                "Revisa el número e inténtalo nuevamente.",
        ];
    }

    if ($result['status'] === 'error') {
        return [
            'status' => 'error',
            'text' =>
                "En este momento no puedo consultar " .
                "el estado del pedido.\n\n" .
                "Puedes intentarlo nuevamente más tarde " .
                "o escribir /menu.",
        ];
    }

    $order = $result['order'];

    $status = getOrderStatusText(
        $order['status'] ?? 'desconocido'
    );

    return [
        'status' => 'success',
        'text' =>
            "📦 Pedido #{$orderId}\n\n" .
            "Estado: {$status}\n\n" .
            "Puedes consultar otro pedido con /pedido " .
            "o volver al menú con /menu.",
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

            continue;
        }


        if ($command === '/menu') {

            clearSession($chatId);

            sendMessage(
                $chatId,
                getMenuText($firstName)
            );

            continue;
        }


        if ($command === '/ayuda') {

            sendMessage(
                $chatId,
                getHelpText()
            );

            continue;
        }


        if ($command === '/soporte') {

            clearSession($chatId);

            sendMessage(
                $chatId,
                getSupportText()
            );

            continue;
        }


        if ($command === '/salir') {

            clearSession($chatId);

            sendMessage(
                $chatId,
                "¡Hasta luego, {$firstName}! 🎵\n\n" .
                "Gracias por usar MusicHub Bot.\n" .
                "Puedes escribir /start cuando quieras volver."
            );

            continue;
        }


        // ------------------------------------------------
        // Catálogo
        // ------------------------------------------------

        if ($command === '/catalogo') {

            clearSession($chatId);

            if ($argument !== '') {

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

            continue;
        }


        // ------------------------------------------------
        // Pedido
        // ------------------------------------------------

        if ($command === '/pedido') {

            clearSession($chatId);

            if ($argument !== '') {

                $result =
                    performOrderSearch($argument);

                sendMessage(
                    $chatId,
                    $result['text']
                );

                if (
                    $result['status'] === 'invalid' ||
                    $result['status'] === 'not_found'
                ) {
                    saveSession(
                        $chatId,
                        [
                            'state' => 'awaiting_order',
                            'attempts' => 1,
                        ]
                    );
                }

                continue;
            }

            saveSession(
                $chatId,
                [
                    'state' => 'awaiting_order',
                    'attempts' => 0,
                ]
            );

            sendMessage(
                $chatId,
                "¿Cuál es el número de tu pedido?\n\n" .
                "Por ejemplo: 17"
            );

            continue;
        }


        // ------------------------------------------------
        // Estado actual
        // ------------------------------------------------

        $session = loadSession($chatId);


        // ------------------------------------------------
        // Esperando catálogo
        // ------------------------------------------------

        if ($session['state'] === 'awaiting_catalog') {

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

            $result =
                performCatalogSearch($message);

            if ($result['status'] === 'success') {

                clearSession($chatId);

                sendMessage(
                    $chatId,
                    $result['text']
                );

                continue;
            }

            if ($result['status'] === 'error') {

                sendMessage(
                    $chatId,
                    $result['text']
                );

                continue;
            }

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

            continue;
        }


        // ------------------------------------------------
        // Esperando pedido
        // ------------------------------------------------

        if ($session['state'] === 'awaiting_order') {

            $result =
                performOrderSearch($message);

            if ($result['status'] === 'success') {

                clearSession($chatId);

                sendMessage(
                    $chatId,
                    $result['text']
                );

                continue;
            }

            if ($result['status'] === 'error') {

                sendMessage(
                    $chatId,
                    $result['text']
                );

                continue;
            }

            $session['attempts']++;

            if ($session['attempts'] >= 3) {

                clearSession($chatId);

                sendMessage(
                    $chatId,
                    $result['text'] .
                    "\n\nYa realizaste varios intentos " .
                    "sin éxito.\n\n" .
                    "Puedes escribir /menu para volver " .
                    "al menú o /soporte para recibir ayuda."
                );

                continue;
            }

            saveSession(
                $chatId,
                $session
            );

            sendMessage(
                $chatId,
                $result['text'] .
                "\n\nEscribe otro número para intentarlo de nuevo."
            );

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
    }

    sleep(1);
}
