<?php
// src/bot_webhook.php

$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) die("Error: .env no encontrado.");
$env = parse_ini_file($envPath);
$token = $env['BOT_TOKEN'] ?? null;
if (!$token) die("Error: BOT_TOKEN no definido.");

$apiUrl = "https://api.telegram.org/bot{$token}/";

$update = json_decode(file_get_contents("php://input"), true);
if (!$update) exit;

if (isset($update['callback_query'])) {
    $chatId = $update['callback_query']['message']['chat']['id'];
    $text = trim($update['callback_query']['data']);
    $firstName = $update['callback_query']['from']['first_name'] ?? 'Usuario';
    file_get_contents($apiUrl . "answerCallbackQuery?callback_query_id=" . $update['callback_query']['id']);
} elseif (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = trim($update['message']['text'] ?? '');
    $firstName = $update['message']['chat']['first_name'] ?? 'Usuario';
} else {
    exit;
}

$stateFile = __DIR__ . "/state_{$chatId}.json";
$state = file_exists($stateFile) ? json_decode(file_get_contents($stateFile), true) : ['step' => 'menu', 'intentos' => 0];

function sendMessage($chatId, $text, $inlineKeyboard = null) {
    global $apiUrl;
    $data = ['chat_id' => $chatId, 'text' => $text, 'parse_mode' => 'Markdown'];
    if ($inlineKeyboard) {
        $data['reply_markup'] = json_encode(['inline_keyboard' => $inlineKeyboard]);
    } else {
        $data['reply_markup'] = json_encode(['remove_keyboard' => true]);
    }
    file_get_contents($apiUrl . "sendMessage?" . http_build_query($data));
}

// --- NUEVA FUNCIÓN: Conexión con IA Local (Ollama) ---
function askOllama($prompt) {
    $ch = curl_init('http://localhost:11434/api/generate');
    $payload = json_encode([
        'model' => 'tinyllama',
        'prompt' => "Eres un asistente de tecnología experto. Responde en español brevemente. Pregunta: " . $prompt,
        'stream' => false
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 45); // 45 segundos de margen para procesar
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    if ($response) {
        $data = json_decode($response, true);
        return $data['response'] ?? 'Tuve un error al procesar la respuesta.';
    }
    return 'Mi cerebro IA está apagado o tardó mucho en responder.';
}

if (str_starts_with($text, '/start') || str_starts_with($text, '/cancel') || $text === 'cmd_menu') {
    $state = ['step' => 'menu', 'intentos' => 0];
    file_put_contents($stateFile, json_encode($state));
    
    $botones = [
        [['text' => '📱 Recomendar teléfono', 'callback_data' => 'cmd_recomendar']],
        [['text' => '📄 Ficha técnica', 'callback_data' => 'cmd_ficha']],
        [['text' => '⚖️ Comparar teléfonos', 'callback_data' => 'cmd_comparar']]
    ];
    sendMessage($chatId, "¡Hola $firstName! 👋 Soy *WhatPhone*.\nTe ayudo a recomendar, consultar y comparar smartphones. ¿Qué deseas hacer?", $botones);
    http_response_code(200);
    exit;
}

if (str_starts_with($text, '/help')) {
    sendMessage($chatId, "🆘 *Ayuda de WhatPhone*\n\nUsa /start para ver el menú interactivo. Si escribes cualquier otra cosa, conversarás con mi IA.");
    http_response_code(200);
    exit;
}

switch ($state['step']) {
    case 'menu':
        if ($text === 'cmd_recomendar') {
            $state['step'] = 'ask_budget';
            $state['intentos'] = 0;
            file_put_contents($stateFile, json_encode($state));
            sendMessage($chatId, "¡Excelente! Dime cuál es tu presupuesto máximo en dólares (USD).\n*(Escribe solo el número, por ejemplo: 350)*");
        } elseif ($text === 'cmd_ficha' || $text === 'cmd_comparar') {
            sendMessage($chatId, "🛠️ Función en desarrollo para el laboratorio. Usa /start para regresar.");
        } else {
            // --- PATRÓN HÍBRIDO: Redirección a IA ---
            sendMessage($chatId, "🧠 *IA procesando tu mensaje...* (Esto puede tardar unos segundos)");
            $respuestaIA = askOllama($text);
            sendMessage($chatId, "🤖: " . $respuestaIA . "\n\n*(Escribe /start para volver a las opciones de la tienda)*");
        }
        break;

    case 'ask_budget':
        if (is_numeric($text) && $text > 0) {
            $state['budget'] = (int)$text;
            $state['step'] = 'ask_usage';
            $state['intentos'] = 0;
            file_put_contents($stateFile, json_encode($state));
            
            $botonesUso = [
                [['text' => '📸 Mejores Cámaras', 'callback_data' => 'uso_camara']],
                [['text' => '🎮 Rendimiento Gaming', 'callback_data' => 'uso_gaming']],
                [['text' => '🔋 Batería / Autonomía', 'callback_data' => 'uso_bateria']],
                [['text' => '⚖️ Calidad-Precio', 'callback_data' => 'uso_calidad']]
            ];
            sendMessage($chatId, "Perfecto, buscando opciones hasta *$". $state['budget'] ." USD*. ¿Cuál es tu prioridad principal?", $botonesUso);
        } else {
            $state['intentos']++;
            if ($state['intentos'] >= 3) {
                unlink($stateFile);
                sendMessage($chatId, "❌ Límite de errores alcanzado. Usa /start.");
            } else {
                file_put_contents($stateFile, json_encode($state));
                sendMessage($chatId, "⚠️ Escribe un monto válido usando **solo números** (ej: 300).\nIntento {$state['intentos']}/3.");
            }
        }
        break;

    case 'ask_usage':
        $usosValidos = ['uso_camara', 'uso_gaming', 'uso_bateria', 'uso_calidad'];
        if (in_array($text, $usosValidos)) {
            sendMessage($chatId, "Consultando catálogo... ⏳");
            $apiData = @file_get_contents("https://dummyjson.com/products/category/smartphones");
            
            if ($apiData === false) {
                sendMessage($chatId, "❌ Problema de conexión temporal con la API externa.");
            } else {
                $json = json_decode($apiData, true);
                $telefono = $json['products'][0] ?? null; 
                
                if ($telefono) {
                    $nombre = $telefono['title'];
                    $precio = $telefono['price'];
                    $botonesFinales = [[['text' => '🔙 Volver al menú principal', 'callback_data' => 'cmd_menu']]];
                    sendMessage($chatId, "🎯 *Recomendación encontrada:*\n\n📱 *$nombre*\n• *Precio estimado:* $$precio USD", $botonesFinales);
                } else {
                    sendMessage($chatId, "No existen coincidencias exactas. Usa /start.");
                }
            }
            unlink($stateFile); 
        } else {
            $state['intentos']++;
            if ($state['intentos'] >= 3) {
                unlink($stateFile);
                sendMessage($chatId, "❌ Límite de errores alcanzado. Usa /start.");
            } else {
                file_put_contents($stateFile, json_encode($state));
                sendMessage($chatId, "⚠️ Usa los botones para seleccionar. Intento {$state['intentos']}/3.");
            }
        }
        break;
}
http_response_code(200);
?>
