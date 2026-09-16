<?php
function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}
loadEnv(__DIR__ . '/../.env');

$token = $_ENV['BOT_TOKEN'] ?? '';
if (empty($token)) {
    die("Error: BOT_TOKEN no configurado en .env\n");
}

define('API_URL', "https://api.telegram.org/bot{$token}/");
define('LOG_FILE', __DIR__ . '/../logs/bot.log');
define('SESSION_FILE', __DIR__ . '/../logs/sessions.json');
define('INTERNAL_API_URL', 'https://tiendarc22009.duckdns.org/src/api.php');
if (!is_dir(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

function writeLog($message) {
    $date = date('Y-m-d H:i:s');
    file_put_contents(LOG_FILE, "[$date] $message\n", FILE_APPEND);
}

function getSession($chatId) {
    if (!file_exists(SESSION_FILE)) return ['attempts' => 0];
    $sessions = json_decode(file_get_contents(SESSION_FILE), true);
    return $sessions[$chatId] ?? ['attempts' => 0];
}

function saveSession($chatId, $data) {
    $sessions = file_exists(SESSION_FILE) ? json_decode(file_get_contents(SESSION_FILE), true) : [];
    $sessions[$chatId] = $data;
    file_put_contents(SESSION_FILE, json_encode($sessions));
}

function sendMessage($chatId, $text, $keyboard = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'Markdown'
    ];
    if ($keyboard) {
        $data['reply_markup'] = json_encode($keyboard);
    }

    $ch = curl_init(API_URL . 'sendMessage');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    $response = curl_exec($ch);
    curl_close($ch);

    writeLog("OUT -> Chat {$chatId}: {$text}");
    return $response;
}

function callApiEndpoint($query) {
    $url = INTERNAL_API_URL . '?query=' . urlencode($query);
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

$mainKeyboard = [
    'keyboard' => [
        [['text' => '🎧 Audífonos'], ['text' => '⌨️ Teclado']],
        [['text' => '🖱️ Mouse'], ['text' => '🔌 Hub USB-C']],
        [['text' => '/help'], ['text' => '/cancelar'], ['text' => '👤 Hablar con agente']]
    ],
    'resize_keyboard' => true
];

$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update || !isset($update['message'])) {
    exit("Esperando mensajes de Telegram...");
}

$message = $update['message'];
$chatId = $message['chat']['id'];
$text = trim($message['text'] ?? '');

writeLog("IN <- Chat {$chatId}: {$text}");
$session = getSession($chatId);

// Comandos Globales
if (strtolower($text) === '/start') {
    saveSession($chatId, ['attempts' => 0]);
    sendMessage($chatId, "¡Hola! Bienvenido a la *Tienda Gaming*.\nPuedo ayudarte a consultar precios y disponibilidad de accesorios.\n\nEscribe el producto que buscas o selecciona una opción:", $mainKeyboard);
    exit();
}

if (strtolower($text) === '/cancelar' || strtolower($text) === 'salir') {
    saveSession($chatId, ['attempts' => 0]);
    sendMessage($chatId, "Operación cancelada. Escribe /start cuando desees realizar otra consulta.");
    exit();
}

if (strtolower($text) === '/help' || strtolower($text) === 'ayuda') {
    sendMessage($chatId, "📌 *Menú de Ayuda*\n- Escribe el producto que buscas (*teclado*, *audífonos*, *mouse*).\n- Usa /cancelar para detener una consulta.", $mainKeyboard);
    exit();
}

if (strtolower($text) === 'hablar con agente' || strtolower($text) === 'humano') {
    saveSession($chatId, ['attempts' => 0]);
    sendMessage($chatId, "🤝 Te estamos transfiriendo con un agente humano. Por favor espera un momento...");
    exit();
}

// Consulta a la nueva API JSON
$apiResult = callApiEndpoint($text);

if (isset($apiResult['status']) && $apiResult['status'] === 'success') {
    saveSession($chatId, ['attempts' => 0]);
    sendMessage($chatId, $apiResult['text'], $mainKeyboard);
    exit();
}

// Manejo de Errores (3 Intentos)
$session['attempts']++;
saveSession($chatId, $session);

if ($session['attempts'] < 3) {
    $remaining = 3 - $session['attempts'];
    sendMessage($chatId, "⚠️ Opción no reconocida. Intenta buscando *mouse*, *teclado* o *audífonos* (Intentos restantes: {$remaining}).", $mainKeyboard);
} else {
    saveSession($chatId, ['attempts' => 0]);
    sendMessage($chatId, "❌ Has excedido los 3 intentos. ¿Deseas reiniciar o hablar con un agente humano?", [
        'keyboard' => [[['text' => '/start'], ['text' => 'Hablar con agente']]],
        'resize_keyboard' => true
    ]);
}
