<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
$logFile = __DIR__ . '/bot.log';

// Carga de variables de entorno desde .env
$envFile = dirname(__DIR__) . '/.env';
if (!file_exists($envFile)) {
    error_log("Archivo .env no encontrado\n", 3, $logFile);
    exit;
}
$env = parse_ini_file($envFile);
$botToken    = $env['BOT_TOKEN'] ?? null;
$ollamaUrl   = $env['OLLAMA_URL'] ?? 'http://localhost:11434/api/generate';
$ollamaModel = $env['OLLAMA_MODEL'] ?? 'qwen2.5:0.5b';
$apiKey = $env['GEMINI_API_KEY'] ?? null;

// Credenciales WooCommerce REST API
$wcUrl    = $env['WC_URL'] ?? 'https://gt22004.duckdns.org/wp-json/wc/v3';
$wcKey    = $env['WC_KEY'] ?? '';
$wcSecret = $env['WC_SECRET'] ?? '';

if (!$botToken) {
    exit;
}

$apiUrl = "https://api.telegram.org/bot{$botToken}/";

function sendTelegram($method, $data) {
    global $apiUrl, $logFile;
    $ch = curl_init($apiUrl . $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $res = curl_exec($ch);
    curl_close($ch);
    file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "OUT: $method - " . json_encode($data) . "\n", FILE_APPEND);
    return $res;
}

function sendText($chatId, $text, $keyboard = null) {
    $payload = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    if ($keyboard) {
        $payload['reply_markup'] = $keyboard;
    }
    return sendTelegram('sendMessage', $payload);
}

function sendTyping($chatId) {
    return sendTelegram('sendChatAction', [
        'chat_id' => $chatId,
        'action'  => 'typing'
    ]);
}

// 1. Cliente REST WooCommerce
function consultarWooCommerceREST($endpoint, $params = []) {
    global $wcUrl, $wcKey, $wcSecret, $logFile;

    $url = rtrim($wcUrl, '/') . '/' . ltrim($endpoint, '/') . '?' . http_build_query($params);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if (!empty($wcKey) && !empty($wcSecret)) {
        curl_setopt($ch, CURLOPT_USERPWD, $wcKey . ":" . $wcSecret);
    }
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || $httpCode !== 200) {
        file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "ERROR WC REST: HTTP $httpCode - $url\n", FILE_APPEND);
        return null;
    }

    return json_decode($res, true);
}

// 2. Cliente REST Open-Meteo
function consultarClimaREST($ciudad) {
    $geoUrl = "https://geocoding-api.open-meteo.com/v1/search?name=" . urlencode($ciudad) . "&count=1&language=es&format=json";
    $ch = curl_init($geoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $geoRes = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($geoRes === false || $httpCode !== 200) {
        return ['error' => 'api_fail'];
    }

    $geoData = json_decode($geoRes, true);
    if (empty($geoData['results'][0])) {
        return ['error' => 'not_found'];
    }

    $lat = $geoData['results'][0]['latitude'];
    $lon = $geoData['results'][0]['longitude'];
    $nombreUbicacion = $geoData['results'][0]['name'] . ", " . ($geoData['results'][0]['country'] ?? '');

    $weatherUrl = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current_weather=true";
    $ch2 = curl_init($weatherUrl);
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
    $weatherRes = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    if ($weatherRes === false || $httpCode2 !== 200) {
        return ['error' => 'api_fail'];
    }

    $weatherData = json_decode($weatherRes, true);
    $current = $weatherData['current_weather'] ?? null;

    if (!$current) {
        return ['error' => 'api_fail'];
    }

    return [
        'ubicacion'   => $nombreUbicacion,
        'temperatura' => $current['temperature'],
        'viento'      => $current['windspeed']
    ];
}

//GEMINI
function consultarGemini($prompt) {
    global $apiKey;

    if (!$apiKey) {
        return "Disculpa, el servicio de IA no se encuentra disponible temporalmente.";
    }

    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.6-flash:generateContent?key=" . urlencode($apiKey);

    $systemInstruction = "Eres el asistente virtual de una tienda deportiva. Responde en español sobre fitness, entrenamiento o nutrición de forma amable y concisa, en un máximo de 2 oraciones completas y con punto final.";

    $payload = [
        'system_instruction' => [
            'parts' => [
                ['text' => $systemInstruction]
            ]
        ],
        'contents' => [
            [
                'parts' => [
                    ['text' => $prompt]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature'     => 0.2,
            'maxOutputTokens' => 120,
            'thinkingConfig'  => [
                'thinkingBudget' => 0
            ]
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 6);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($res === false || $httpCode !== 200) {
        return "Disculpa, en este momento no pude procesar tu consulta. Puedes revisar nuestro /catalogo o ver /ofertas.";
    }

    $json = json_decode($res, true);
    $texto = $json['candidates'][0]['content']['parts'][0]['text'] ?? null;

    return $texto ? trim($texto) : "Disculpa, no fue posible generar una respuesta en este momento.";
}

//ollama
function consultarOllama($prompt) {
    global $ollamaUrl, $ollamaModel;

    $url = rtrim($ollamaUrl ?: 'http://localhost:11434', '/');
    $modelo = $ollamaModel ?: 'qwen2.5:0.5b';

    $payload = [
        'model'  => $modelo,
        'prompt' => "Responde en una sola frase breve: " . $prompt,
        'stream' => false,
        'options' => [
            'num_predict' => 35,
            'temperature' => 0.2
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);

    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($res === false || $httpCode !== 200) {
        return "Disculpa la ia no sirve";
    }

    $json = json_decode($res, true);
    $texto = $json['response'] ?? null;

    return $texto ? trim($texto) : "DEBUG JSON -> " . substr($res, 0, 100);
}
// entrada texto incoherente
function esTextoIncoherente($texto) {
    $palabras = preg_split('/\s+/', trim($texto));
    foreach ($palabras as $p) {
        if (preg_match('/[bcdfghjklmnpqrstvwxyz]{5,}/i', $p)) {
            return true;
        }
        if (strlen($p) > 3 && !preg_match('/[aeiouáéíóú]/i', $p)) {
            return true;
        }
    }
    return false;
}
// 4. Recepción del Webhook
$rawInput = file_get_contents('php://input');
if (!$rawInput) {
    exit;
}
file_put_contents($logFile, date('[Y-m-d H:i:s] ') . "IN: " . $rawInput . "\n", FILE_APPEND);

$update = json_decode($rawInput, true);
$chatId = null;
$text   = null;

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text   = trim($update['message']['text'] ?? '');
} elseif (isset($update['callback_query'])) {
    $chatId = $update['callback_query']['message']['chat']['id'];
    $text   = trim($update['callback_query']['data'] ?? '');
}

if (!$chatId || $text === null || $text === '') {
    exit;
}

// Gestión de sesión persistente
$sessionFile = sys_get_temp_dir() . "/bot_session_{$chatId}.json";
$session = file_exists($sessionFile) ? json_decode(file_get_contents($sessionFile), true) : [
    'retries' => 0, 
    'estado' => 'normal', 
    'retries_clima' => 0,
    'opciones_desambiguacion' => []
];

// Teclados de navegación personalizada
$tecladoInicio = [
    'inline_keyboard' => [
        [
            ['text' => '🔥 Ofertas de hoy', 'callback_data' => '/ofertas'],
            ['text' => '📂 Explorar Catálogo', 'callback_data' => '/catalogo']
        ],
        [
            ['text' => '🌤️ Clima para entrenar', 'callback_data' => '/clima'],
            ['text' => 'ℹ️ Ayuda', 'callback_data' => '/help']
	]
    ]
];

$tecladoTrasCategoria = [
    'inline_keyboard' => [
        [
            ['text' => '📂 Otros Departamentos', 'callback_data' => '/catalogo'],
            ['text' => '🔥 Ver Ofertas', 'callback_data' => '/ofertas']
        ],
        [
            ['text' => '🏠 Menú Principal', 'callback_data' => '/start']
        ]
    ]
];

$tecladoTrasOfertas = [
    'inline_keyboard' => [
        [
            ['text' => '📂 Ver Catálogo General', 'callback_data' => '/catalogo']
        ],
        [
            ['text' => '🏠 Menú Principal', 'callback_data' => '/start']
        ]
    ]
];

$tecladoTrasProducto = [
    'inline_keyboard' => [
        [
            ['text' => '📂 Explorar Departamentos', 'callback_data' => '/catalogo'],
            ['text' => '🔥 Ver Ofertas', 'callback_data' => '/ofertas']
        ],
        [
            ['text' => '🏠 Menú Principal', 'callback_data' => '/start']
        ]
    ]
];

$tecladoTrasClima = [
    'inline_keyboard' => [
        [
            ['text' => '🌤️ Consultar otra ciudad', 'callback_data' => '/clima'],
            ['text' => '🏠 Menú Principal', 'callback_data' => '/start']
        ]
    ]
];

$clean = mb_strtolower(trim($text), 'UTF-8');
$cleanNoAccent = strtr($clean, ['á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ñ'=>'n']);

// INTERRUPCIÓN GLOBAL
if ($text === '/cancelar' || strtolower($text) === 'cancelar' || strtolower($text) === 'salir' || strtolower($text) === 'olvidalo') {
    $session = ['retries' => 0, 'estado' => 'normal', 'retries_clima' => 0, 'opciones_desambiguacion' => []];
    file_put_contents($sessionFile, json_encode($session));
    sendText($chatId, "Operación cancelada. Elige una opción o escribe el producto que buscas:", $tecladoInicio);
    exit;
}

// CIERRE Y DESPEDIDA
if (preg_match('/^(gracias|muchas gracias|no gracias|eso seria todo|adios|nada mas|bye)\b/i', $cleanNoAccent)) {
    $session = ['retries' => 0, 'estado' => 'normal', 'retries_clima' => 0, 'opciones_desambiguacion' => []];
    file_put_contents($sessionFile, json_encode($session));
    $tecladoReactivar = [
        'inline_keyboard' => [
            [['text' => '🔄 Iniciar nueva consulta', 'callback_data' => '/start']]
        ]
    ];
    sendText($chatId, "Con gusto. Si necesitas consultar algo más adelante, puedes volver cuando desees. ¡Mucho éxito en tu entrenamiento! 💪", $tecladoReactivar);
    exit;
}

// FUERA DE ALCANCE (Reclamos, garantías)
if (preg_match('/(queja|reclamo|devolucion|reembolso|garantia|defecto|danado|roto|estafa|demanda|abogado|mayoreo|factura credito|pago rechazado|asesor|humano)/i', $cleanNoAccent)) {
    $session['retries'] = 0;
    $session['estado'] = 'normal';
    file_put_contents($sessionFile, json_encode($session));

    $msg = "⚠️ *Consulta fuera del alcance del asistente:*\n\n"
         . "Esta solicitud requiere gestión directa con atención al cliente:\n\n"
         . "• 📧 Correo: `soporte@tiendadeportiva.com`\n"
         . "• 🕒 Lunes a Viernes de 8:00 AM a 5:00 PM";
    sendText($chatId, $msg, $tecladoInicio);
    exit;
}

// SLOT FILLING DEL CLIMA
if (($session['estado'] ?? '') === 'esperando_ciudad_clima') {
    sendTyping($chatId);
    $resultadoClima = consultarClimaREST($text);

    if (isset($resultadoClima['error'])) {
        $session['retries_clima'] = ($session['retries_clima'] ?? 0) + 1;

        if ($session['retries_clima'] >= 3) {
            $session = ['retries' => 0, 'estado' => 'normal', 'retries_clima' => 0, 'opciones_desambiguacion' => []];
            file_put_contents($sessionFile, json_encode($session));
            sendText($chatId, "⚠️ Límite de 3 intentos alcanzado al buscar la ciudad.", $tecladoInicio);
        } else {
            file_put_contents($sessionFile, json_encode($session));
            $tecladoCancelar = ['inline_keyboard' => [[['text' => '❌ Cancelar', 'callback_data' => '/cancelar']]]];
            sendText($chatId, "No encontré la ciudad \"{$text}\" (intento {$session['retries_clima']} de 3). Escribe una ciudad válida o cancela:", $tecladoCancelar);
        }
    } else {
        $session = ['retries' => 0, 'estado' => 'normal', 'retries_clima' => 0, 'opciones_desambiguacion' => []];
        file_put_contents($sessionFile, json_encode($session));

        $msg = "🌤️ *Condiciones para entrenar en {$resultadoClima['ubicacion']}:*\n\n"
             . "• *Temperatura actual:* {$resultadoClima['temperatura']} °C\n"
             . "• *Velocidad del viento:* {$resultadoClima['viento']} km/h\n\n"
             . "¡Listo para tu entrenamiento!";
        sendText($chatId, $msg, $tecladoTrasClima);
	}    
    exit;
}

// RESOLUCIÓN DE DESAMBIGUACIÓN (Por botón o por número escrito)
$prodIdSeleccionado = null;
if (str_starts_with($text, '/prod_id_')) {
    $prodIdSeleccionado = intval(substr($text, 9));
} elseif (($session['estado'] ?? '') === 'esperando_seleccion_desambiguacion' && is_numeric($cleanNoAccent)) {
    $indice = intval($cleanNoAccent) - 1;
    $opciones = $session['opciones_desambiguacion'] ?? [];
    if (isset($opciones[$indice])) {
        $prodIdSeleccionado = $opciones[$indice];
    }
}

if ($prodIdSeleccionado) {
    $session = ['retries' => 0, 'estado' => 'normal', 'retries_clima' => 0, 'opciones_desambiguacion' => []];
    file_put_contents($sessionFile, json_encode($session));

    sendTyping($chatId);
    $p = consultarWooCommerceREST("products/{$prodIdSeleccionado}");

    if ($p) {
        $precio = $p['sale_price'] ? "{$p['sale_price']} USD *(En oferta)*" : "{$p['price']} USD";
        $categoria = !empty($p['categories'][0]['name']) ? $p['categories'][0]['name'] : 'Deportes';
        $stock = ($p['stock_status'] ?? 'instock') === 'instock' ? 'En existencia' : 'Agotado';
        
        $msg = "Sí, tenemos disponible el artículo:\n\n"
             . "• *Producto:* {$p['name']}\n"
             . "• *Categoría:* {$categoria}\n"
             . "• *Precio:* \${$precio}\n"
             . "• *Disponibilidad:* {$stock}\n"
             . "• *Enlace directo:* {$p['permalink']}";
        sendText($chatId, $msg, $tecladoTrasProducto);
        exit;
    }
}

// Normalización de comandos
$comandoMapeado = $text;

if (preg_match('/^(hola|buenas|buenos dias|buenas tardes|buenas noches|inicio|empezar|start|\/start)\b/', $cleanNoAccent)) {
    $comandoMapeado = '/start';
} elseif (preg_match('/^(\/help|help|ayuda|\/ayuda)\b/', $cleanNoAccent)) {
    $comandoMapeado = '/help';
} elseif (preg_match('/^(\/catalogo|catalogo|ver catalogo|departamentos|categorias)/', $cleanNoAccent)) {
    $comandoMapeado = '/catalogo';
} elseif (preg_match('/^(\/ofertas|ofertas|promociones|descuentos)/', $cleanNoAccent)) {
    $comandoMapeado = '/ofertas';
} elseif (preg_match('/^(\/clima|clima|tiempo|temperatura)\b/', $cleanNoAccent)) {
    if (str_starts_with($text, '/clima')) {
        $comandoMapeado = $text;
    } else {
        $partesClima = preg_split('/(clima|tiempo|temperatura)\s+(en\s+|de\s+)?/i', $cleanNoAccent);
        $ciudadDetectada = trim(end($partesClima));
        $comandoMapeado = (!empty($ciudadDetectada) && $ciudadDetectada !== $cleanNoAccent) ? "/clima {$ciudadDetectada}" : '/clima';
    }
}

// Enrutador Principal
switch (true) {
    case ($comandoMapeado === '/start'):
        $session = ['retries' => 0, 'estado' => 'normal', 'retries_clima' => 0, 'opciones_desambiguacion' => []];
        file_put_contents($sessionFile, json_encode($session));

        $saludo = "¡Hola! 👋 Soy tu asistente en la tienda deportiva. Puedes escribir el nombre de un artículo o elegir una opción:";
        sendText($chatId, $saludo, $tecladoInicio);
        break;
    // AYUDA Y GUÍA DE USO (/help)
    case ($comandoMapeado === '/help'):
        $session = ['retries' => 0, 'estado' => 'normal', 'retries_clima' => 0, 'opciones_desambiguacion' => []];
        file_put_contents($sessionFile, json_encode($session));

        $tecladoHelp = [
            'inline_keyboard' => [
                [
                    ['text' => '🔥 Ver Ofertas', 'callback_data' => '/ofertas'],
                    ['text' => '📂 Ir al Catálogo', 'callback_data' => '/catalogo']
                ],
                [
                    ['text' => '🏠 Menú Principal', 'callback_data' => '/start']
                ]
            ]
        ];

        $ayudaMsg = "ℹ️ *Guía de Comandos y Funciones:*\n\n"
                  . "• `/start` o `hola`: Muestra el saludo y menú principal.\n"
                  . "• `/catalogo`: Explora departamentos y categorías de la tienda.\n"
                  . "• `/ofertas`: Lista los productos con descuento activo.\n"
                  . "• `/clima [ciudad]`: Consulta el estado del tiempo para entrenar.\n"
                  . "• `/cancelar`: Anula cualquier operación o búsqueda en curso.\n"
                  . "• *Búsqueda directa:* Escribe el nombre de un artículo (ej: `proteina`, `calzado`).\n"
                  . "• *Asistente IA:* Pregunta cualquier duda sobre fitness o entrenamiento.";

        sendText($chatId, $ayudaMsg, $tecladoHelp);
        break;
    // CATÁLOGO DINÁMICO
    case ($comandoMapeado === '/catalogo'):
        $session['retries'] = 0;
        file_put_contents($sessionFile, json_encode($session));
        sendTyping($chatId);

        $categorias = consultarWooCommerceREST('products/categories', ['hide_empty' => true, 'per_page' => 6]);

        if ($categorias === null) {
            sendText($chatId, "⚠️ No fue posible conectar con el catálogo en este momento.", $tecladoInicio);
            break;
        }

        $tecladoCat = [];
        $fila = [];
        foreach ($categorias as $cat) {
            if (stripos($cat['slug'], 'uncategorized') !== false || stripos($cat['slug'], 'sin-categorizar') !== false) continue;
            $fila[] = ['text' => "📁 {$cat['name']}", 'callback_data' => "/cat_id_{$cat['id']}"];
            if (count($fila) === 2) {
                $tecladoCat[] = $fila;
                $fila = [];
            }
        }
        if (!empty($fila)) $tecladoCat[] = $fila;
        $tecladoCat[] = [['text' => '🔙 Volver al Inicio', 'callback_data' => '/start']];

        $catalogoMsg = "📂 *Departamentos Disponibles en Tienda:*\n\nSelecciona el departamento que deseas explorar:";
        sendText($chatId, $catalogoMsg, ['inline_keyboard' => $tecladoCat]);
        break;

    // PRODUCTOS DE UNA CATEGORÍA
    case (str_starts_with($text, '/cat_id_')):
        $session['retries'] = 0;
        file_put_contents($sessionFile, json_encode($session));
        sendTyping($chatId);

        $catId = intval(substr($text, 8));
        $productosCat = consultarWooCommerceREST('products', ['category' => $catId, 'per_page' => 5]);

        if (empty($productosCat)) {
            sendText($chatId, "No se encontraron artículos en este departamento por el momento.", $tecladoTrasCategoria);
        } else {
            $msg = "📦 *Artículos en este departamento:*\n\n";
            foreach ($productosCat as $p) {
                $precio = $p['sale_price'] ? "{$p['sale_price']} USD *(Oferta)*" : "{$p['price']} USD";
                $stock = ($p['stock_status'] ?? 'instock') === 'instock' ? 'En existencia' : 'Agotado';
                $msg .= "• *{$p['name']}*\n  Precio: \${$precio} | Disponibilidad: {$stock}\n  [Ver producto]({$p['permalink']})\n\n";
            }
            sendText($chatId, $msg, $tecladoTrasCategoria);
        }
        break;

    // OFERTAS
    case ($comandoMapeado === '/ofertas'):
        $session['retries'] = 0;
        file_put_contents($sessionFile, json_encode($session));
        sendTyping($chatId);

        $productos = consultarWooCommerceREST('products', ['on_sale' => true, 'per_page' => 4]);

        if ($productos === null) {
            sendText($chatId, "⚠️ No fue posible conectar con las ofertas en este momento.", $tecladoInicio);
        } elseif (empty($productos)) {
            sendText($chatId, "Por el momento no tenemos productos en oferta activa.", $tecladoTrasOfertas);
        } else {
            $msg = "🔥 *Promociones Deportivas Actuales:*\n\n";
            $i = 1;
            foreach ($productos as $p) {
                $categoria = !empty($p['categories'][0]['name']) ? $p['categories'][0]['name'] : 'Deportes';
                $precioOferta = $p['sale_price'] ?: $p['price'];
                $precioReg    = $p['regular_price'] ?: $precioOferta;
                $stock        = ($p['stock_status'] ?? 'instock') === 'instock' ? 'En existencia' : 'Agotado';

                $msg .= "{$i}. *{$p['name']}*\n"
                     . "   • *Categoría:* {$categoria}\n"
                     . "   • *Precio de oferta:* \${$precioOferta} USD _(Antes: \${$precioReg} USD)_\n"
                     . "   • *Disponibilidad:* {$stock}\n"
                     . "   • [Comprar en tienda]({$p['permalink']})\n\n";
                $i++;
            }
            sendText($chatId, $msg, $tecladoTrasOfertas);
        }
        break;

    // CLIMA
    case (str_starts_with($comandoMapeado, '/clima')):
        $parametro = trim(substr($comandoMapeado, 6));

        if ($parametro === '') {
            $session['estado'] = 'esperando_ciudad_clima';
            $session['retries_clima'] = 0;
            file_put_contents($sessionFile, json_encode($session));
            $tecladoCancelar = ['inline_keyboard' => [[['text' => '❌ Cancelar', 'callback_data' => '/cancelar']]]];
            sendText($chatId, "¿Para qué ciudad deseas conocer las condiciones del clima? (Ejemplo: San Salvador)", $tecladoCancelar);
        } else {
            sendTyping($chatId);
            $resultadoClima = consultarClimaREST($parametro);

            if (isset($resultadoClima['error'])) {
                $session['estado'] = 'esperando_ciudad_clima';
                $session['retries_clima'] = 1;
                file_put_contents($sessionFile, json_encode($session));
                $tecladoCancelar = ['inline_keyboard' => [[['text' => '❌ Cancelar', 'callback_data' => '/cancelar']]]];
                sendText($chatId, "No encontré la ciudad \"{$parametro}\" (intento 1 de 3). Ingresa otra ciudad:", $tecladoCancelar);
            } else {
                $msg = "🌤️ *Condiciones para entrenar en {$resultadoClima['ubicacion']}:*\n\n"
                     . "• *Temperatura actual:* {$resultadoClima['temperatura']} °C\n"
                     . "• *Velocidad del viento:* {$resultadoClima['viento']} km/h\n\n"
                     . "¡Listo para tu entrenamiento!";
                sendText($chatId, $msg, $tecladoTrasClima);
            }
        }
        break;
    default:
        // 1. Filtro Gibberish estructural rápido
        if (esTextoIncoherente($text) || strlen($text) < 3) {
            $session['retries'] = ($session['retries'] ?? 0) + 1;

            if ($session['retries'] >= 3) {
                $session = ['retries' => 0, 'estado' => 'normal', 'retries_clima' => 0, 'opciones_desambiguacion' => []];
                file_put_contents($sessionFile, json_encode($session));
                sendText($chatId, "⚠️ Se ha superado el límite de 3 intentos no válidos. Regresando al menú principal:", $tecladoInicio);
            } else {
                file_put_contents($sessionFile, json_encode($session));
                $tecladoReintento = ['inline_keyboard' => [[['text' => '❌ Cancelar', 'callback_data' => '/cancelar']]]];
                sendText($chatId, "No reconocí tu solicitud (intento {$session['retries']} de 3). Escribe un término más descriptivo o cancela:", $tecladoReintento);
            }
            break;
        }

        // 2. Búsqueda de artículos en WooCommerce 
        $palabras = count(explode(' ', trim($text)));
        if ($palabras <= 4 && !str_starts_with($text, '/')) {

            $patronMuletillas = '/^(tiene|tienen|tendran|tendra|vende|venden|busco|quiero|hay|precio de|precios de|informacion de|cuanto vale)\s+/i';
            $terminoBusqueda = trim(preg_replace($patronMuletillas, '', $cleanNoAccent));
            $terminoBusqueda = trim($terminoBusqueda, '?¿!¡., ');

            sendTyping($chatId);
            $coincidencias = consultarWooCommerceREST('products', ['search' => $terminoBusqueda, 'per_page' => 5]);

            // Múltiples coincidencias -> Desambiguación con botones
            if (!empty($coincidencias) && count($coincidencias) > 1) {
                $session['retries'] = 0;
                $session['estado'] = 'esperando_seleccion_desambiguacion';
                $session['opciones_desambiguacion'] = array_column($coincidencias, 'id');
                file_put_contents($sessionFile, json_encode($session));

                $msg = "Encontré más de un artículo para tu búsqueda. ¿Cuál de ellos deseas consultar?\n\n";
                $botonesDesambiguacion = [];
                $i = 1;
                foreach ($coincidencias as $item) {
                    $precio = $item['sale_price'] ?: $item['price'];
                    $msg .= "{$i}. *{$item['name']}* (\${$precio} USD)\n";
                    $botonesDesambiguacion[] = [
                        ['text' => "Opción {$i}: {$item['name']}", 'callback_data' => "/prod_id_{$item['id']}"]
                    ];
                    $i++;
                }
                $botonesDesambiguacion[] = [['text' => '❌ Cancelar búsqueda', 'callback_data' => '/cancelar']];

                sendText($chatId, $msg, ['inline_keyboard' => $botonesDesambiguacion]);
                break;
            }

            // Coincidencia única -> Ficha técnica directa
            if (!empty($coincidencias) && count($coincidencias) === 1) {
                $session = ['retries' => 0, 'estado' => 'normal', 'retries_clima' => 0, 'opciones_desambiguacion' => []];
                file_put_contents($sessionFile, json_encode($session));

                $item = $coincidencias[0];
                $precio = $item['sale_price'] ? "{$item['sale_price']} USD *(En oferta)*" : "{$item['price']} USD";
                $categoria = !empty($item['categories'][0]['name']) ? $item['categories'][0]['name'] : 'Deportes';
                $stock = ($item['stock_status'] ?? 'instock') === 'instock' ? 'En existencia' : 'Agotado';

                $msg = "Sí, tenemos disponible el artículo:\n\n"
                     . "• *Producto:* {$item['name']}\n"
                     . "• *Categoría:* {$categoria}\n"
                     . "• *Precio:* \${$precio}\n"
                     . "• *Disponibilidad:* {$stock}\n"
                     . "• *Enlace directo:* {$item['permalink']}";
                sendText($chatId, $msg, $tecladoTrasProducto);
                break;
            }

            // Término corto que NO existe en WooCommerce
            if ($coincidencias !== null && empty($coincidencias) && $palabras <= 2) {
                $session['retries'] = ($session['retries'] ?? 0) + 1;

                if ($session['retries'] >= 3) {
                    $session = ['retries' => 0, 'estado' => 'normal', 'retries_clima' => 0, 'opciones_desambiguacion' => []];
                    file_put_contents($sessionFile, json_encode($session));
                    sendText($chatId, "⚠️ Se ha superado el límite de 3 intentos no válidos. Regresando al menú principal:", $tecladoInicio);
                } else {
                    file_put_contents($sessionFile, json_encode($session));
                    $tecladoReintento = [
                        'inline_keyboard' => [
                            [['text' => '📂 Ver Catálogo', 'callback_data' => '/catalogo']],
                            [['text' => '❌ Cancelar', 'callback_data' => '/cancelar']]
                        ]
                    ];
                    sendText($chatId, "No encontré ningún producto relacionado con \"{$text}\" (intento {$session['retries']} de 3). Revisa el catálogo o escribe otro término:", $tecladoReintento);
                }
                break;
            }
        }
	// 3. Consultas para ia
        $session['retries'] = 0;
        file_put_contents($sessionFile, json_encode($session));

        sendTyping($chatId);

	// Gemini API
        $respuestaIa = consultarGemini($text);

        // Fallback local (Ollama)
        //$respuestaIa = consultarOllama($text);

        sendText($chatId, $respuestaIa, $tecladoInicio);
        break;
}
