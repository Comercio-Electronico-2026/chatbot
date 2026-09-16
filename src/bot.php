```php
<?php

$env = parse_ini_file('/home/pr21064/chatbot/.env');

$token = $env['BOT_TOKEN'];
$wcKey = $env['WC_KEY'];
$wcSecret = $env['WC_SECRET'];
$groqKey = $env['GROQ_API_KEY'];

$apiURL = "https://api.telegram.org/bot$token/";
$storeUrl = "https://tiendapr21064.duckdns.org/wp-json/wc/v3";

$update = json_decode(file_get_contents("php://input"), true);

if (!isset($update["message"]["text"])) {
    exit;
}

$chatId = $update["message"]["chat"]["id"];
$texto = trim($update["message"]["text"]);


/*
|--------------------------------------------------------------------------
| SESIONES
|--------------------------------------------------------------------------
|
| Se conserva la sesión de cada usuario en sesiones.json.
| Ahora también almacenamos un historial corto de conversación
| para darle contexto a Groq.
|
*/

$sessionFile = 'sesiones.json';

$sesiones = file_exists($sessionFile)
    ? json_decode(file_get_contents($sessionFile), true)
    : [];

if (!is_array($sesiones)) {
    $sesiones = [];
}

$estado = $sesiones[$chatId]['estado'] ?? 'INICIO';
$intentos = $sesiones[$chatId]['intentos'] ?? 0;
$tempData = $sesiones[$chatId]['tempData'] ?? '';
$historial = $sesiones[$chatId]['historial'] ?? [];

if (!is_array($historial)) {
    $historial = [];
}


/*
|--------------------------------------------------------------------------
| FUNCIÓN: ENVIAR MENSAJE A TELEGRAM
|--------------------------------------------------------------------------
*/

function enviarMensaje($chatId, $texto, $apiURL)
{
    file_get_contents(
        $apiURL .
        "sendMessage?chat_id=" . urlencode($chatId) .
        "&parse_mode=HTML&text=" . urlencode($texto)
    );
}


/*
|--------------------------------------------------------------------------
| FUNCIÓN: CONSULTAR WOOCOMMERCE
|--------------------------------------------------------------------------
*/

function consultarWooCommerce($endpoint, $wcKey, $wcSecret)
{
    $url = $endpoint .
        (strpos($endpoint, '?') !== false ? '&' : '?') .
        "consumer_key=" . urlencode($wcKey) .
        "&consumer_secret=" . urlencode($wcSecret);

    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'code' => $httpCode,
        'data' => $response ? json_decode($response, true) : null
    ];
}


/*
|--------------------------------------------------------------------------
| FUNCIÓN: CONSULTAR GROQ
|--------------------------------------------------------------------------
|
| FASE 1:
|   System prompt con información y reglas de CETBOT.
|
| FASE 2:
|   Historial de conversación.
|
| FASE 3:
|   Contexto real de productos obtenido desde WooCommerce.
|
*/

function consultarGroq($mensaje, $groqKey, $historial = [], $contextoWooCommerce = '')
{
    $url = "https://api.groq.com/openai/v1/chat/completions";


    /*
    |--------------------------------------------------------------------------
    | FASE 1 - CONTEXTO GENERAL DE CETBOT
    |--------------------------------------------------------------------------
    */

    $systemPrompt = <<<PROMPT
Eres CETBOT, el asistente virtual oficial de CET STORE.

IDENTIDAD:
- Eres un asistente virtual de una tienda de tecnología y periféricos.
- CET STORE comercializa productos tecnológicos y accesorios.
- Entre las categorías pueden encontrarse teclados, mouse, audífonos, bocinas, componentes de PC, memorias RAM, almacenamiento, cables, adaptadores, accesorios gaming y productos similares.
- Tu objetivo es ayudar al cliente de forma clara, breve, amable y natural.

PERSONALIDAD:
- Responde siempre en español.
- Sé amable y profesional, pero no demasiado formal.
- Habla de forma natural, como un asistente de atención al cliente.
- Puedes utilizar emojis ocasionalmente.
- Evita respuestas excesivamente largas.
- Responde normalmente en 1-3 párrafos cortos.

FUNCIONES DEL BOT:
1. Ayudar a consultar productos.
2. Informar sobre disponibilidad cuando se proporcionen datos reales de WooCommerce.
3. Responder preguntas generales sobre tecnología y periféricos.
4. Orientar al usuario hacia las opciones del menú.
5. Ayudar al usuario a entender las características de los productos cuando exista información disponible.

MENÚ PRINCIPAL:
1 - Consultar el catálogo
2 - Consultar estado de mi pedido
3 - Métodos de pago

REGLAS IMPORTANTES:
- Nunca inventes productos.
- Nunca inventes precios.
- Nunca inventes disponibilidad o stock.
- Nunca inventes características específicas de un producto.
- Nunca inventes números de pedido.
- Nunca inventes estados de pedidos.
- Nunca inventes enlaces.
- Si se proporciona información de WooCommerce, utiliza únicamente esa información para hablar de los productos.
- Si no tienes información suficiente para responder una pregunta específica sobre un producto, dilo claramente.
- No afirmes que puedes realizar compras directamente.
- No puedes modificar pedidos.
- No puedes cancelar pedidos.
- No puedes consultar información privada de pedidos por tu cuenta.
- El sistema PHP se encarga de validar los pedidos.
- Nunca solicites contraseñas.
- Nunca solicites números completos de tarjetas bancarias ni información sensible de pago.

OPERACIONES DEL SISTEMA:
- La consulta de catálogo se realiza mediante WooCommerce.
- La consulta de pedidos se realiza mediante el sistema PHP y requiere validación.
- Los métodos de pago son gestionados por el sistema.
- No debes intentar sustituir estos procesos.

SOBRE EL MENÚ:
Si el usuario quiere consultar el catálogo, consultar un pedido o conocer métodos de pago, puedes indicarle que utilice la opción correspondiente del menú.

IMPORTANTE:
Tu función principal es conversar y orientar al usuario.
El código PHP controla las operaciones reales de la tienda.
No debes inventar acciones que el sistema no haya realizado.
PROMPT;


    /*
    |--------------------------------------------------------------------------
    | FASE 3 - CONTEXTO DE WOOCOMMERCE
    |--------------------------------------------------------------------------
    */

    $messages = [
        [
            "role" => "system",
            "content" => $systemPrompt
        ]
    ];


    /*
    |--------------------------------------------------------------------------
    | Agregar contexto real de WooCommerce
    |--------------------------------------------------------------------------
    */

    if (!empty($contextoWooCommerce)) {

        $messages[] = [
            "role" => "system",
            "content" =>
                "INFORMACIÓN REAL OBTENIDA DESDE WOOCOMMERCE:\n\n" .
                $contextoWooCommerce .
                "\n\nIMPORTANTE: utiliza estos datos como fuente de verdad para productos, precios y disponibilidad. No inventes información que no aparezca aquí."
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | FASE 2 - HISTORIAL
    |--------------------------------------------------------------------------
    |
    | Solo enviamos el historial que ya está almacenado.
    | Esto evita enviar una conversación infinita a la API.
    |
    */

    foreach ($historial as $mensajeHistorial) {

        if (
            isset($mensajeHistorial['role']) &&
            isset($mensajeHistorial['content'])
        ) {
            $messages[] = [
                "role" => $mensajeHistorial['role'],
                "content" => $mensajeHistorial['content']
            ];
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Mensaje actual del usuario
    |--------------------------------------------------------------------------
    */

    $messages[] = [
        "role" => "user",
        "content" => $mensaje
    ];


    /*
    |--------------------------------------------------------------------------
    | Petición a Groq
    |--------------------------------------------------------------------------
    */

    $data = [
        "model" => "openai/gpt-oss-20b",
        "messages" => $messages,
        "max_tokens" => 200
    ];


    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        json_encode($data, JSON_UNESCAPED_UNICODE)
    );

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        [
            "Authorization: Bearer $groqKey",
            "Content-Type: application/json"
        ]
    );

    $response = curl_exec($ch);

    if ($response === false) {
        curl_close($ch);

        return "Estoy teniendo problemas para conectarme con el servicio de IA. Intenta nuevamente en unos momentos.";
    }

    curl_close($ch);


    /*
    |--------------------------------------------------------------------------
    | Procesar respuesta
    |--------------------------------------------------------------------------
    */

    $resObj = json_decode($response, true);

    if (
        isset($resObj['choices'][0]['message']['content']) &&
        !empty($resObj['choices'][0]['message']['content'])
    ) {

        return trim(
            $resObj['choices'][0]['message']['content']
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Fallback
    |--------------------------------------------------------------------------
    */

    return "Estoy teniendo problemas para pensar en este momento. Intenta usar las opciones del menú.";
}


/*
|--------------------------------------------------------------------------
| MENÚ PRINCIPAL
|--------------------------------------------------------------------------
*/

$menuPrincipal =
    "\n\n🔄 <b>Menú Principal:</b>\n" .
    "1️⃣ Consultar el catálogo\n" .
    "2️⃣ Consultar estado de mi pedido\n" .
    "3️⃣ Métodos de pago";


/*
|--------------------------------------------------------------------------
| COMANDOS
|--------------------------------------------------------------------------
*/

if (
    strtolower($texto) === '/cancelar' ||
    strtolower($texto) === '/start'
) {

    $estado = 'INICIO';

    /*
    |--------------------------------------------------------------------------
    | Reiniciamos también el historial
    |--------------------------------------------------------------------------
    |
    | /start comienza una nueva conversación.
    |
    */

    $historial = [];

    $sesiones[$chatId] = [
        'estado' => 'INICIO',
        'intentos' => 0,
        'tempData' => '',
        'historial' => []
    ];

    file_put_contents(
        $sessionFile,
        json_encode(
            $sesiones,
            JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        )
    );

    $reply =
        "¡Hola! 👋 Soy CETBOT. " .
        "Te ayudo a consultar disponibilidad, estado de pedidos " .
        "e información de pago.\n\n" .
        "Escribe el número de tu opción (1-3) o pregúntame lo que quieras:" .
        $menuPrincipal;

    enviarMensaje($chatId, $reply, $apiURL);

    exit;
}


if (strtolower($texto) === '/ayuda') {

    $reply =
        "🆘 <b>Ayuda de CETBOT</b>\n" .
        "Comandos disponibles:\n" .
        "/start - Menú principal\n" .
        "/cancelar - Detener acción actual\n\n" .
        "Si necesitas asistencia humana, escribe a soporte@cetstore.sv";

    enviarMensaje($chatId, $reply, $apiURL);

    exit;
}


/*
|--------------------------------------------------------------------------
| VARIABLE PARA CONTEXTO DE WOOCOMMERCE
|--------------------------------------------------------------------------
|
| Por defecto está vacía.
| Solamente se llena cuando consultamos productos.
|
*/

$contextoWooCommerce = '';


/*
|--------------------------------------------------------------------------
| FLUJO PRINCIPAL
|--------------------------------------------------------------------------
*/

switch ($estado) {

    /*
    |--------------------------------------------------------------------------
    | INICIO
    |--------------------------------------------------------------------------
    */

    case 'INICIO':

        if ($texto == '1') {

            $estado = 'ESPERANDO_PRODUCTO';

            $reply =
                "¡Genial! 🔍 Escribe el <b>tipo de producto</b>, " .
                "marca o modelo (ej. 'Teclado', 'Bocina').";

        } elseif ($texto == '2') {

            $estado = 'ESPERANDO_PEDIDO_ID';

            $reply =
                "Por favor ingresa tu número de ID de pedido (solo los números):";

        } elseif ($texto == '3') {

            $estado = 'ESPERANDO_CIERRE';

            $reply =
                "💳 <b>Métodos de pago:</b>\n" .
                "Aceptamos transferencias bancarias, tarjetas de crédito/débito y Bitcoin.\n\n" .
                "¿Puedo ayudarte con algo más? (Responde Sí / No)";

        } elseif (
            preg_match(
                '/estado.*pedido.* (\d+)/i',
                $texto,
                $matches
            )
        ) {

            $estado = 'ESPERANDO_PEDIDO_CORREO';

            $tempData = $matches[1];

            $reply =
                "Claro, te ayudaré a consultar tu pedido #$tempData. " .
                "Por seguridad, por favor escribe el correo electrónico " .
                "con el que realizaste la compra.";

        } else {

            /*
            |--------------------------------------------------------------------------
            | IA
            |--------------------------------------------------------------------------
            |
            | Preguntas generales utilizan Groq.
            |
            */

            $reply = consultarGroq(
                $texto,
                $groqKey,
                $historial,
                $contextoWooCommerce
            );
        }

        break;


    /*
    |--------------------------------------------------------------------------
    | ESPERANDO PRODUCTO
    |--------------------------------------------------------------------------
    */

    case 'ESPERANDO_PRODUCTO':

        $res = consultarWooCommerce(
            $storeUrl .
            "/products?search=" .
            urlencode($texto),
            $wcKey,
            $wcSecret
        );


        if ($res['code'] !== 200) {

            $reply =
                "⚠️ Problemas técnicos con la tienda. " .
                "Por favor intenta más tarde." .
                $menuPrincipal;

            $estado = 'INICIO';

        } elseif (empty($res['data'])) {

            $intentos++;

            if ($intentos >= 3) {

                $reply =
                    "🚫 <b>Límite de intentos alcanzado.</b> " .
                    "Derivando a soporte humano: por favor escribe a " .
                    "soporte@cetstore.sv." .
                    $menuPrincipal;

                $estado = 'INICIO';

            } else {

                $reply =
                    "No logré encontrar ningún producto que coincida con " .
                    "'<b>$texto</b>' 😔.\n\n" .
                    "Prueba con otra palabra (ej. 'Mouse' o 'RAM') " .
                    "(Intento $intentos/3).";
            }

        } else {

            /*
            |--------------------------------------------------------------------------
            | FASE 3
            |--------------------------------------------------------------------------
            |
            | Construimos contexto únicamente con los productos encontrados.
            |
            */

            $contextoProductos = [];

            $contador = 0;

            foreach ($res['data'] as $prod) {

                if ($contador >= 5) {
                    break;
                }

                $stock =
                    isset($prod['stock_status']) &&
                    $prod['stock_status'] === 'instock'
                        ? 'Disponible'
                        : 'Agotado';

                $descripcion =
                    isset($prod['short_description'])
                        ? trim(
                            preg_replace(
                                '/\s+/',
                                ' ',
                                strip_tags($prod['short_description'])
                            )
                        )
                        : 'Sin descripción.';

                $contextoProductos[] = [
                    'nombre' => $prod['name'] ?? 'Sin nombre',
                    'precio' => $prod['price'] ?? 'No disponible',
                    'stock' => $stock,
                    'descripcion' => $descripcion,
                    'url' => $prod['permalink'] ?? ''
                ];

                $contador++;
            }


            /*
            |--------------------------------------------------------------------------
            | Convertimos los productos a texto para Groq
            |--------------------------------------------------------------------------
            */

            foreach ($contextoProductos as $index => $producto) {

                $numero = $index + 1;

                $contextoWooCommerce .=
                    "PRODUCTO $numero\n" .
                    "Nombre: " . $producto['nombre'] . "\n" .
                    "Precio: $" . $producto['precio'] . "\n" .
                    "Stock: " . $producto['stock'] . "\n" .
                    "Descripción: " . $producto['descripcion'] . "\n" .
                    "Enlace: " . $producto['url'] . "\n\n";
            }


            /*
            |--------------------------------------------------------------------------
            | Respuesta visible para el usuario
            |--------------------------------------------------------------------------
            |
            | Conservamos tu respuesta original de productos.
            |
            */

            $reply =
                "¡Mira lo que encontré para ti! 🎉\n\n";

            foreach ($contextoProductos as $producto) {

                $reply .=
                    "📦 <b>" .
                    htmlspecialchars(
                        $producto['nombre'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    "</b>\n";

                $reply .=
                    "📝 <i>" .
                    htmlspecialchars(
                        $producto['descripcion'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    "</i>\n";

                $reply .=
                    "💰 $" .
                    htmlspecialchars(
                        $producto['precio'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                    " | Stock: " .
                    $producto['stock'] .
                    "\n";

                if (!empty($producto['url'])) {

                    $reply .=
                        "🔗 <a href='" .
                        htmlspecialchars(
                            $producto['url'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) .
                        "'>Ver producto</a>\n";
                }

                $reply .= "\n";
            }

            $reply .=
                "¿Puedo ayudarte con algo más? (Responde Sí / No)";

            $estado = 'ESPERANDO_CIERRE';
        }

        break;


    /*
    |--------------------------------------------------------------------------
    | ESPERANDO PEDIDO ID
    |--------------------------------------------------------------------------
    */

    case 'ESPERANDO_PEDIDO_ID':

        if (!is_numeric($texto)) {

            $reply =
                "Formato inválido. Ingresa solo números. " .
                "O usa /cancelar.";

        } else {

            $tempData = $texto;

            $estado = 'ESPERANDO_PEDIDO_CORREO';

            $reply =
                "¡Gracias! Ahora, por favor ingresa el correo " .
                "electrónico de la compra #$tempData:";
        }

        break;


    /*
    |--------------------------------------------------------------------------
    | ESPERANDO PEDIDO CORREO
    |--------------------------------------------------------------------------
    */

    case 'ESPERANDO_PEDIDO_CORREO':

        $res = consultarWooCommerce(
            $storeUrl . "/orders/" . $tempData,
            $wcKey,
            $wcSecret
        );


        if ($res['code'] !== 200) {

            $intentos++;

            if ($intentos >= 3) {

                $reply =
                    "🚫 <b>Límite de intentos alcanzado.</b> " .
                    "No pudimos validar tu pedido." .
                    $menuPrincipal;

                $estado = 'INICIO';

            } else {

                $reply =
                    "Datos no coinciden o pedido no encontrado. " .
                    "Intenta de nuevo (Intento $intentos/3):";
            }

        } else {

            $orden = $res['data'];

            if (
                isset($orden['billing']['email']) &&
                strtolower(
                    trim($orden['billing']['email'])
                ) === strtolower($texto)
            ) {

                $reply =
                    "¡Validación exitosa! ✅\n\n" .
                    "Tu pedido <b>#$tempData</b> se encuentra: " .
                    "<b>" . $orden['status'] . "</b>.\n" .
                    "Total de compra: $" . $orden['total'] . "\n\n" .
                    "¿Puedo ayudarte con algo más? (Responde Sí / No)";

                $estado = 'ESPERANDO_CIERRE';

            } else {

                $intentos++;

                if ($intentos >= 3) {

                    $reply =
                        "🚫 <b>Límite de intentos alcanzado.</b> " .
                        "Datos incorrectos." .
                        $menuPrincipal;

                    $estado = 'INICIO';

                } else {

                    $reply =
                        "El correo no coincide con el pedido. " .
                        "Intenta de nuevo (Intento $intentos/3):";
                }
            }
        }

        break;


    /*
    |--------------------------------------------------------------------------
    | ESPERANDO CIERRE
    |--------------------------------------------------------------------------
    */

    case 'ESPERANDO_CIERRE':

        if (
            strtolower($texto) == 'si' ||
            strtolower($texto) == 'sí'
        ) {

            $estado = 'INICIO';

            $reply =
                "¡Excelente! Por favor, selecciona una opción:" .
                $menuPrincipal;

        } elseif (strtolower($texto) == 'no') {

            $estado = 'INICIO';

            $reply =
                "¡Gracias por contactar a CET STORE! " .
                "Si necesitas algo más, presiona /start. 👋";

        } else {

            $reply =
                "Por favor, responde 'Sí' o 'No'. " .
                "(O usa /cancelar para salir).";
        }

        break;
}


/*
|--------------------------------------------------------------------------
| FASE 2 - ACTUALIZAR HISTORIAL
|--------------------------------------------------------------------------
|
| Guardamos la interacción actual después de obtener la respuesta.
|
| Esto permite que una siguiente pregunta tenga contexto.
|
*/

$historial[] = [
    'role' => 'user',
    'content' => $texto
];

$historial[] = [
    'role' => 'assistant',
    'content' => $reply
];


/*
|--------------------------------------------------------------------------
| Limitar historial
|--------------------------------------------------------------------------
|
| Conservamos únicamente los últimos 10 mensajes.
| Eso equivale aproximadamente a 5 intercambios.
|
*/

$historial = array_slice($historial, -10);


/*
|--------------------------------------------------------------------------
| GUARDAR SESIÓN
|--------------------------------------------------------------------------
*/

$sesiones[$chatId] = [
    'estado' => $estado,
    'intentos' => $estado === 'INICIO' ? 0 : $intentos,
    'tempData' => $tempData,
    'historial' => $historial
];


file_put_contents(
    $sessionFile,
    json_encode(
        $sesiones,
        JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
    )
);


/*
|--------------------------------------------------------------------------
| ENVIAR RESPUESTA
|--------------------------------------------------------------------------
*/

enviarMensaje(
    $chatId,
    $reply,
    $apiURL
);

?>
```
