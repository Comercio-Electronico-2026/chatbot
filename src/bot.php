<?php
$env_path = __DIR__ . '/../.env';
$db_path = __DIR__ . '/../sesiones.db';

if (!file_exists($env_path)) {
    http_response_code(500);
    exit;
}

$env = file_get_contents($env_path);
preg_match('/BOT_TOKEN=(.*)/', $env, $matches);
$token = trim($matches[1] ?? '');
preg_match('/LICHESS_TOKEN=(.*)/', $env, $lichess_matches);
$lichess_token = trim($lichess_matches[1] ?? '');

$apiUrl = "https://api.telegram.org/bot{$token}/";

$update = json_decode(file_get_contents('php://input'), true);

if (!$update || !isset($update['message']['text'])) {
    http_response_code(200);
    exit;
}

$chat_id = $update['message']['chat']['id'];
$texto = trim($update['message']['text']);

try {
    $pdo = new PDO("sqlite:" . $db_path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM sesiones WHERE chat_id = ?");
$stmt->execute([$chat_id]);
$sesion = $stmt->fetch(PDO::FETCH_ASSOC);

$fen_inicial = 'rnbqkbnr/pppppppp/8/8/8/8/PPPPPPPP/RNBQKBNR w KQkq - 0 1';

if (!$sesion) {
    $pdo->prepare("INSERT INTO sesiones (chat_id, fen, current_flow) VALUES (?, ?, ?)")->execute([$chat_id, $fen_inicial, 'blancas']);
    $sesion = ['chat_id' => $chat_id, 'current_flow' => 'blancas', 'fen' => $fen_inicial];
}

function enviarMensaje($chat_id, $texto, $apiUrl) {
    file_get_contents($apiUrl . "sendMessage?chat_id={$chat_id}&text=" . urlencode($texto));
}

function enviarFoto($chat_id, $ruta_imagen, $apiUrl, $caption = "") {
    $ch_foto = curl_init($apiUrl . "sendPhoto");
    curl_setopt($ch_foto, CURLOPT_POST, true);
    $postFields = [
        'chat_id' => $chat_id,
        'photo' => new CURLFile($ruta_imagen)
    ];
    if ($caption !== "") {
        $postFields['caption'] = $caption;
        $postFields['parse_mode'] = 'MarkdownV2';
    }
    curl_setopt($ch_foto, CURLOPT_POSTFIELDS, $postFields);
    curl_setopt($ch_foto, CURLOPT_RETURNTRANSFER, true);
    curl_exec($ch_foto);
    curl_close($ch_foto);
}

// 1. Comandos Críticos[cite: 1]
if (strpos($texto, '/') === 0) {
    if ($texto === '/start') {
        $msg = "Bot operativo. Comandos:\n"
             . "• /jugarBlancas: Inicia partida contra Stockfish (Tú mueves primero).\n"
             . "• /jugarNegras: Inicia partida contra Stockfish (Bot mueve primero).\n"
             . "• /analizar: Analiza movimientos libremente.\n"
             . "• /apertura: Consulta Lichess para la posición actual.\n"
             . "Texto libre interactúa con la IA.";
        enviarMensaje($chat_id, $msg, $apiUrl);
    } elseif ($texto === '/jugarBlancas') {
        $pdo->prepare("UPDATE sesiones SET fen = ?, current_flow = 'blancas' WHERE chat_id = ?")->execute([$fen_inicial, $chat_id]);
        enviarMensaje($chat_id, "Partida iniciada con blancas. Esperando jugada (ej. d4, Cf3).", $apiUrl);
    } elseif ($texto === '/analizar') {
        $pdo->prepare("UPDATE sesiones SET fen = ?, current_flow = 'analizar' WHERE chat_id = ?")->execute([$fen_inicial, $chat_id]);
        enviarMensaje($chat_id, "Modo análisis. Envía una jugada por turno. La evaluación se enviará oculta.", $apiUrl);
    } elseif ($texto === '/jugarNegras') {
        $pdo->prepare("UPDATE sesiones SET fen = ?, current_flow = 'negras' WHERE chat_id = ?")->execute([$fen_inicial, $chat_id]);
        $fen_arg = escapeshellarg($fen_inicial);
        
        $comando = "cd /home/camilo/chatbot && .venv/bin/python chess_cli.py jugar_negras_inicio {$fen_arg} negras";
        $resultado_cli = shell_exec($comando);
        $datos_ajedrez = json_decode($resultado_cli, true);
        
        if (isset($datos_ajedrez['valido']) && $datos_ajedrez['valido']) {
            $pdo->prepare("UPDATE sesiones SET fen = ? WHERE chat_id = ?")->execute([$datos_ajedrez['fen'], $chat_id]);
            $ruta_imagen = realpath(__DIR__ . '/../' . $datos_ajedrez['png']);
            enviarFoto($chat_id, $ruta_imagen, $apiUrl, "Stockfish ha movido\. Esperando tu jugada\.");
        }
    } elseif ($texto === '/apertura') {
        $fen_url = urlencode($sesion['fen']);
        $ch = curl_init("https://explorer.lichess.ovh/masters?fen={$fen_url}");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_USERAGENT, "BotUniversitario-MM22108");
        if ($lichess_token !== '') {
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer {$lichess_token}"]);
        }
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch) || $http_code !== 200) {
            enviarMensaje($chat_id, "El servicio de aperturas falló (HTTP $http_code).", $apiUrl);
        } else {
            $data = json_decode($response, true);
            $resp = "Mejores respuestas según Lichess:\n";
            $limite = min(3, count($data['moves'] ?? []));
            for ($i = 0; $i < $limite; $i++) {
                $resp .= "• " . $data['moves'][$i]['san'] . "\n";
            }
            enviarMensaje($chat_id, empty($data['moves']) ? "No hay datos de apertura para esta posición." : $resp, $apiUrl);
        }
        curl_close($ch);
    } else {
        enviarMensaje($chat_id, "Comando no reconocido.", $apiUrl);
    }
    exit;
}

// 2. Procesar Jugadas según el modo activo
$error_py = "";
if (in_array($sesion['current_flow'], ['blancas', 'negras', 'analizar'])) {
    $accion = ($sesion['current_flow'] === 'analizar') ? 'analizar' : 'play';
    $fen_arg = escapeshellarg($sesion['fen']);
    $flow_arg = escapeshellarg($sesion['current_flow']);
    $jugada_arg = escapeshellarg($texto);
    
    $comando = "cd /home/camilo/chatbot && .venv/bin/python chess_cli.py {$accion} {$fen_arg} {$flow_arg} {$jugada_arg}";
    $resultado_cli = shell_exec($comando);
    $datos_ajedrez = json_decode($resultado_cli, true);

    if (isset($datos_ajedrez['valido']) && $datos_ajedrez['valido']) {
        $pdo->prepare("UPDATE sesiones SET fen = ? WHERE chat_id = ?")->execute([$datos_ajedrez['fen'], $chat_id]);
        $ruta_imagen = realpath(__DIR__ . '/../' . $datos_ajedrez['png']);
        
        $caption = "";
        if (isset($datos_ajedrez['eval'])) {
            $eval_escaped = str_replace(['+', '-', '.'], ['\+', '\-', '\.'], $datos_ajedrez['eval']);
            $caption = "Evaluación: ||" . $eval_escaped . "||";
        }
        
        enviarFoto($chat_id, $ruta_imagen, $apiUrl, $caption);
        exit;
    } else {
        $error_py = $datos_ajedrez['error'] ?? 'Formato SAN inválido.';
    }
}

// 3. Fallback a Ollama[cite: 1, 3]
$prompt_sistema = "Eres un bot de ajedrez. Responde en 10 palabras que la jugada es inválida.";
$ch = curl_init('http://localhost:11434/api/generate');
$payload = json_encode([
    'model' => 'tinyllama',
    'prompt' => $prompt_sistema . " Texto: " . $texto,
    'stream' => false,
    'options' => [
        'num_predict' => 40,
        'num_ctx' => 256
    ]
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Aumento crítico de timeout
$response = curl_exec($ch);

if (curl_errno($ch)) {
    enviarMensaje($chat_id, "Jugada inválida: $error_py. IA local excedió tiempo de espera.", $apiUrl);
} else {
    $data = json_decode($response, true);
    if (isset($data['response'])) {
        enviarMensaje($chat_id, trim($data['response']), $apiUrl);
    } else {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        enviarMensaje($chat_id, "Jugada rechazada ($error_py). Error en Ollama (HTTP $http_code).", $apiUrl);
    }
}
curl_close($ch);
?>
