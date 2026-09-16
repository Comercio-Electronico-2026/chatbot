<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// Desactivar despliegue de errores HTML para no romper la respuesta HTTP 200 de Telegram


// Buscar el archivo .env en varias rutas posibles
$possibleEnvPaths = [
    __DIR__ . '/../.env',
    '/var/www/html/.env',
    '/home/arce/html_public/.env'
];

$botToken = getenv('BOT_TOKEN');

if (!$botToken) {
    foreach ($possibleEnvPaths as $envPath) {
        if (file_exists($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    if (trim($name) === 'BOT_TOKEN') {
                        $botToken = trim($value);
                        break 2;
                    }
                }
            }
        }
    }
}

// Token de respaldo por si falla la lectura de archivo
if (!$botToken) {
    // Si sigue nulo, pon de forma temporal tu token directo entre comillas para probar:
    // $botToken = "TU_BOT_TOKEN_AQUI";
}

$apiUrl = "https://api.telegram.org/bot{$botToken}/";

// Leer la actualización
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if ($content) {
    file_put_contents(__DIR__ . '/../bot.log', date('[Y-m-d H:i:s] ') . $content . PHP_EOL, FILE_APPEND);
}

if (!$update) {
    http_response_code(200);
    exit;
}

$chatId = null;
$text = null;

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = trim($update['message']['text'] ?? '');
} elseif (isset($update['callback_query'])) {
    $chatId = $update['callback_query']['message']['chat']['id'];
    $text = trim($update['callback_query']['data'] ?? '');
}

if (!$chatId || !$text) {
    http_response_code(200);
    exit;
}

$lowerText = mb_strtolower($text);

// Responder a /start o menú
if (in_array($lowerText, ['/start', 'cancelar', 'ayuda', 'menú', 'menu'])) {
    $mensaje = "¡Hola! Soy *TecnoBot* 💻📱\nPuedo ayudarte a consultar disponibilidad de productos o revisar el estado de tu pedido.\n\n¿Qué deseas hacer?";
    
    $keyboard = [
        'inline_keyboard' => [
            [['text' => '💻 Ver Laptops', 'callback_data' => 'op_laptops'], ['text' => '📱 Ver Smartphones', 'callback_data' => 'op_smartphones']],
            [['text' => '🎧 Ver Accesorios', 'callback_data' => 'op_accesorios'], ['text' => '📦 Estado de Pedido', 'callback_data' => 'op_pedido']]
        ]
    ];

    responderTelegram($apiUrl, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => $mensaje,
        'parse_mode' => 'Markdown',
        'reply_markup' => json_encode($keyboard)
    ]);
    
    http_response_code(200);
    exit;
}

// Respuesta a Opciones de Botón / Catálogo
if ($lowerText === 'op_laptops' || strpos($lowerText, 'laptop') !== false) {
    $res = consultarAPI("http://localhost/src/api.php?action=catalogo&categoria=laptops");
    $txt = "💻 *Laptops Disponibles:*\n\n";
    if (!empty($res['data'])) {
        foreach ($res['data'] as $p) {
            $e = $p['stock'] > 0 ? "✅ En stock ({$p['stock']})" : "❌ Agotado";
            $txt .= "• *{$p['marca']} {$p['modelo']}* - \${$p['precio']} [$e]\n";
        }
    } else {
        $txt .= "No se pudo consultar el catálogo.";
    }
    $txt .= "\n¿Necesitas algo más? Escribe 'menú' para volver al inicio.";
    enviarMensaje($apiUrl, $chatId, $txt);
    http_response_code(200);
    exit;
}

if ($lowerText === 'op_smartphones' || strpos($lowerText, 'smartphone') !== false) {
    $res = consultarAPI("http://localhost/src/api.php?action=catalogo&categoria=smartphones");
    $txt = "📱 *Smartphones Disponibles:*\n\n";
    if (!empty($res['data'])) {
        foreach ($res['data'] as $p) {
            $e = $p['stock'] > 0 ? "✅ En stock" : "❌ Agotado";
            $cols = implode(', ', $p['colores']);
            $txt .= "• *{$p['marca']} {$p['modelo']}* (\${$p['precio']})\n  Colores: $cols - [$e]\n";
        }
    } else {
        $txt .= "No se pudo consultar el catálogo.";
    }
    $txt .= "\n¿Necesitas algo más? Escribe 'menú' para volver al inicio.";
    enviarMensaje($apiUrl, $chatId, $txt);
    http_response_code(200);
    exit;
}

if ($lowerText === 'op_accesorios' || strpos($lowerText, 'accesorio') !== false) {
    $res = consultarAPI("http://localhost/src/api.php?action=catalogo&categoria=accesorios");
    $txt = "🎧 *Accesorios Disponibles:*\n\n";
    if (!empty($res['data'])) {
        foreach ($res['data'] as $p) {
            $txt .= "• *{$p['producto']}* - \${$p['precio']} (Stock: {$p['stock']})\n";
        }
    } else {
        $txt .= "No se pudo obtener la lista de accesorios.";
    }
    $txt .= "\n¿Necesitas algo más? Escribe 'menú' para volver al inicio.";
    enviarMensaje($apiUrl, $chatId, $txt);
    http_response_code(200);
    exit;
}

if ($lowerText === 'op_pedido') {
    enviarMensaje($apiUrl, $chatId, "📦 Por favor, envía tu *número de pedido* de 4 dígitos (Ejemplo: `5678`).");
    http_response_code(200);
    exit;
}

// Búsqueda de pedido de 4 dígitos
if (preg_match('/^\d{4}$/', $text)) {
    $res = consultarAPI("http://localhost/src/api.php?action=pedido&id={$text}");
    if (isset($res['status']) && $res['status'] === 'success') {
        $p = $res['data'];
        $txt = "📦 *Estado del Pedido #{$text}:*\n\n• Estado: *{$p['estado']}*\n• Entrega estimada: *{$p['fecha_entrega']}*";
    } else {
        $txt = "⚠️ No encontré ningún pedido con el número *{$text}*. Verifica los 4 dígitos o escribe 'menú' para cancelar.";
    }
    enviarMensaje($apiUrl, $chatId, $txt);
    http_response_code(200);
    exit;
}

// Fallback con Ollama
$respuestaLLM = consultarOllama($text);
if ($respuestaLLM) {
    enviarMensaje($apiUrl, $chatId, $respuestaLLM);
} else {
    enviarMensaje($apiUrl, $chatId, "No entendí tu solicitud. Puedes escribir 'menú' para ver las opciones disponibles.");
}

http_response_code(200);

// Funciones
function enviarMensaje($apiUrl, $chatId, $texto) {
    responderTelegram($apiUrl, 'sendMessage', [
        'chat_id' => $chatId,
        'text' => $texto,
        'parse_mode' => 'Markdown'
    ]);
}

function responderTelegram($apiUrl, $method, $params) {
    $ch = curl_init($apiUrl . $method);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

function consultarAPI($url) {
    $url = str_replace('http://localhost', 'https://av21009.duckdns.org', $url);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);    
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true) ?? [];
}

function consultarOllama($prompt) {
    $url = 'http://127.0.0.1:11434/api/generate';
    $data = [
        'model' => 'qwen:0.5b',
        'prompt' => $prompt,
        'system' => 'Eres TecnoBot, un asistente breve y amable de una tienda de tecnología.',
        'stream' => false
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Aumentar timeout por si la inferencia en CPU tarda
    
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        file_put_contents(__DIR__ . '/../bot.log', date('[Y-m-d H:i:s] ') . "Ollama cURL Error: " . $curlError . PHP_EOL, FILE_APPEND);
        return null;
    }

    $json = json_decode($response, true);
    return $json['response'] ?? null;
}
?>

