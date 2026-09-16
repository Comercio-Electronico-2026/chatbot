<?php
header('Content-Type: application/json; charset=utf-8');

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

$ollamaUrl = $_ENV['OLLAMA_URL'] ?? 'http://10.0.2.2:11434/api/generate';
$ollamaModel = $_ENV['OLLAMA_MODEL'] ?? 'qwen2.5:1.5b';

$query = $_REQUEST['query'] ?? '';

if (empty(trim($query))) {
    echo json_encode(['status' => 'error', 'message' => 'Parámetro query vacío.']);
    exit();
}

function queryCatalogAPI($searchTerm) {
    $catalog = [
        'audifonos' => ['name' => 'Audífonos Inalámbricos Bluetooth Pro', 'price' => '$40.00 USD', 'stock' => 'En Stock'],
        'teclado'   => ['name' => 'Teclado Mecánico Gaming RGB', 'price' => '$65.00 USD', 'stock' => 'En Stock'],
        'mouse'     => ['name' => 'Mouse Gamer Óptico 7200 DPI', 'price' => '$25.00 USD', 'stock' => 'Agotado'],
        'hub'       => ['name' => 'Adaptador Hub USB-C Multiport 7 en 1', 'price' => '$30.00 USD', 'stock' => 'En Stock']
    ];

    $term = strtolower(trim($searchTerm));
    foreach ($catalog as $key => $product) {
        if (strpos($term, $key) !== false) {
            return $product;
        }
    }
    return null;
}

function queryOllama($prompt, $ollamaUrl, $model) {
    $data = [
        'model' => $model,
        'prompt' => "Eres un asistente de una tienda de tecnología y gaming. Responde de forma breve y amigable: " . $prompt,
        'stream' => false
    ];

    $ch = curl_init($ollamaUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        return $result['response'] ?? null;
    }
    return null;
}

// 1. Consulta al Catálogo
$productInfo = queryCatalogAPI($query);
if ($productInfo) {
    echo json_encode([
        'status' => 'success',
        'source' => 'catalog',
        'data'   => $productInfo,
        'text'   => "📦 *{$productInfo['name']}*\n💰 *Precio:* {$productInfo['price']}\n📊 *Estado:* {$productInfo['stock']}\n\n¿Deseas consultar otro producto?"
    ]);
    exit();
}

// 2. Consulta a Ollama
$ollamaResponse = queryOllama($query, $ollamaUrl, $ollamaModel);
if ($ollamaResponse) {
    echo json_encode([
        'status' => 'success',
        'source' => 'ollama',
        'text'   => "🤖 " . $ollamaResponse
    ]);
    exit();
}

// 3. Sin coincidencia
echo json_encode([
    'status' => 'notFound',
    'message' => 'No se encontró el producto ni respuesta de IA.'
]);
