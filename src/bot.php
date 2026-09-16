<?php
// 1. Cargar variables desde .env
if (!file_exists(__DIR__ . '/../.env')) {
    die("Error: No se encontró el archivo .env\n");
}
$config = parse_ini_file(__DIR__ . '/../.env');
$token = $config['BOT_TOKEN'] ?? null;
$openAiKey = $config['OPENAI_API_KEY'] ?? null;

if (!$token) {
    die("Error: BOT_TOKEN no configurado en .env\n");
}

$apiUrl = "https://api.telegram.org/bot{$token}/";

// 2. Leer entrada JSON de Telegram (Webhook)
$input = file_get_contents("php://input");
$update = json_decode($input, true);

// Sistema de Logs Local
if ($update) {
    $logMsg = sprintf("[%s] Entrada: %s\n", date('Y-m-d H:i:s'), $input);
    file_put_contents(__DIR__ . '/../logs/bot.log', $logMsg, FILE_APPEND);
}

if (!isset($update["message"]["text"])) {
    exit;
}

$chatId = $update["message"]["chat"]["id"];
$text = trim($update["message"]["text"]);
$firstName = $update["message"]["from"]["first_name"] ?? "Usuario";

// 3. Función para enviar mensajes a Telegram
function sendMessage($chatId, $text) {
    global $apiUrl;
    $url = $apiUrl . "sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text
    ];
    $options = [
        'http' => [
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];
    $context  = stream_context_create($options);
    file_get_contents($url, false, $context);
}

// 4. Función para consultar la API de OpenAI (gpt-4o-mini)
function consultarOpenAI($prompt, $apiKey) {
    if (!$apiKey) {
        return "Error: No se ha configurado la API Key de OpenAI en el archivo .env.";
    }

    $ch = curl_init("https://api.openai.com/v1/chat/completions");
    
    $payload = [
        "model" => "gpt-4o-mini",
        "messages" => [
            ["role" => "system", "content" => "Eres un asistente de ventas de una tienda de computadoras. Responde de forma amable, breve y profesional."],
            ["role" => "user", "content" => $prompt]
        ],
        "max_tokens" => 150
    ];

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/json",
            "Authorization: Bearer " . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);

    return $data['choices'][0]['message']['content'] ?? "En este momento nuestro asistente no está disponible.";
}

// 5. Lógica principal de respuestas
if (strpos($text, '/start') === 0) {
    $msg = "¡Hola, {$firstName}! 👋 Bienvenido a la tienda de computadoras.\n\nPuedo ayudarte con:\n- Consultar catálogo (Laptops, RAM, Mouse)\n- /clima <ciudad> (Verificar condiciones de envío)\n- /humano (Contacto con un agente)\n- /cancelar\n\n¿Qué producto o consulta deseas realizar?";
    sendMessage($chatId, $msg);
} elseif (strpos($text, '/clima') === 0) {
    $parts = explode(' ', $text, 2);
    $ciudad = $parts[1] ?? 'San Salvador';
    
    // Consulta a Open-Meteo API
    $geoUrl = "https://geocoding-api.open-meteo.com/v1/search?name=" . urlencode($ciudad) . "&count=1";
    $geoRes = @file_get_contents($geoUrl);
    
    if ($geoRes) {
        $geoData = json_decode($geoRes, true);
        if (!empty($geoData['results'][0])) {
            $lat = $geoData['results'][0]['latitude'];
            $lon = $geoData['results'][0]['longitude'];
            
            $weatherUrl = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current_weather=true";
            $weatherRes = @file_get_contents($weatherUrl);
            $weatherData = json_decode($weatherRes, true);
            
            $temp = $weatherData['current_weather']['temperature'] ?? 'N/A';
            $msg = "Clima en {$ciudad}: {$temp}°C. Las entregas operan con normalidad.";
        } else {
            $msg = "No encontré información para la ciudad: {$ciudad}.";
        }
    } else {
        $msg = "Error al consultar el servicio del clima.";
    }
    
    sendMessage($chatId, $msg);
} elseif (strpos($text, '/humano') === 0) {
    sendMessage($chatId, "Un agente de soporte humano te contactará pronto. También puedes escribirnos a soporte@tienda.com.");
} else {
    // Consulta a OpenAI para respuestas generales
    $respuestaIA = consultarOpenAI($text, $openAiKey);
    sendMessage($chatId, $respuestaIA);
}
