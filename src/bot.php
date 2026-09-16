<?php
// src/bot.php - Chatbot híbrido para ElForaneo (CET 115 - Guía 5b)

declare(strict_types=1);

// 1. Carga segura del entorno (.env)
$envPath = __DIR__ . '/../.env';
if (!file_exists($envPath)) {
    http_response_code(500);
    exit("Error: No se encontró el archivo .env.");
}

$env = parse_ini_file($envPath);
$botToken   = $env['BOT_TOKEN'] ?? null;
$groqApiKey = $env['GROQ_API_KEY'] ?? null;

if (!$botToken) {
    http_response_code(500);
    exit("Error: BOT_TOKEN no definido.");
}

// 2. Constantes del sistema
define('TELEGRAM_API', "https://api.telegram.org/bot{$botToken}/");
define('LOG_FILE', __DIR__ . '/../bot.log');
define('SESSION_DIR', sys_get_temp_dir() . '/elforaneo_sessions_gd21011');
define('API_BASE_URL', 'https://api.elforaneo.com');
define('MAX_RETRIES', 3);

if (!is_dir(SESSION_DIR)) {
    mkdir(SESSION_DIR, 0755, true);
}

// 3. Logger local de auditoría
function logBot(string $level, string $message): void {
    $timestamp = date('Y-m-d H:i:s');
    $entry = sprintf("[%s] [%s] %s\n", $timestamp, strtoupper($level), $message);
    file_put_contents(LOG_FILE, $entry, FILE_APPEND | LOCK_EX);
}

// 4. Gestión de sesiones JSON
function getSession(int $chatId): array {
    $filePath = SESSION_DIR . "/{$chatId}.json";
    if (file_exists($filePath)) {
        $data = json_decode((string)file_get_contents($filePath), true);
        if (is_array($data)) {
            return $data;
        }
    }
    return [
        'step'    => 'START',
        'retries' => 0,
        'slots'   => [
            'university_id'   => null,
            'university_name' => null,
            'gender'          => null,
            'max_price'       => null
        ],
        'results_cache' => []
    ];
}

function saveSession(int $chatId, array $session): void {
    $filePath = SESSION_DIR . "/{$chatId}.json";
    file_put_contents($filePath, json_encode($session, JSON_PRETTY_PRINT), LOCK_EX);
}

function clearSession(int $chatId): void {
    $filePath = SESSION_DIR . "/{$chatId}.json";
    if (file_exists($filePath)) {
        unlink($filePath);
    }
}

// 5. Wrapper cURL para la API de Telegram
function sendTelegramMessage(int $chatId, string $text, ?array $keyboard = null): bool {
    $payload = [
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'parse_mode'               => 'Markdown',
        'disable_web_page_preview' => false
    ];

    if ($keyboard !== null) {
        $payload['reply_markup'] = $keyboard;
    }

    $ch = curl_init(TELEGRAM_API . 'sendMessage');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload)
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200) {
        logBot('ERROR', "Fallo al enviar mensaje a Chat {$chatId} (HTTP {$httpCode}): {$curlError}");
        return false;
    }

    logBot('OUT', "Chat {$chatId}: " . str_replace("\n", " ", mb_substr($text, 0, 100)) . "...");
    return true;
}

// 6. Consumo REST de la API de ElForaneo

function fetchUniversities(): array {
    $url = API_BASE_URL . '/universities/';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'User-Agent: ElForaneo-TelegramBot/1.0'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200 || !$response) {
        logBot('ERROR', "Fallo al consultar /universities/ (HTTP {$httpCode}): {$error}");
        return ['status' => 'error', 'data' => []];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        logBot('ERROR', "Respuesta inválida JSON en /universities/");
        return ['status' => 'error', 'data' => []];
    }

    return ['status' => 'success', 'data' => $data];
}

function matchUniversity(string $userInput, array $universities): ?array {
    $clean = trim($userInput);
    $clean = ltrim($clean, '/');
    $clean = mb_strtoupper($clean, 'UTF-8');

    $normalized = strtr($clean, ['Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U']);

    $aliases = [
        'NACIONAL'        => 'UES',
        'LA NACIONAL'     => 'UES',
        'CENTROAMERICANA' => 'UCA',
        'DON BOSCO'       => 'UDB',
        'GAVIDIA'         => 'UFG',
        'TECNOLOGICA'     => 'UTEC',
        'TECNOLOGICO'     => 'UTEC',
        'MATIAS'          => 'UJMD',
        'JOSE MATIAS'     => 'UJMD',
        'EVANGELICA'      => 'UEES',
        'MASFERRER'       => 'USAM'
    ];

    if (isset($aliases[$normalized])) {
        $normalized = $aliases[$normalized];
    }

    foreach ($universities as $uni) {
        $acronym = mb_strtoupper($uni['acronym'] ?? '', 'UTF-8');
        $name    = mb_strtoupper($uni['name'] ?? '', 'UTF-8');
        $nameNorm = strtr($name, ['Á'=>'A', 'É'=>'E', 'Í'=>'I', 'Ó'=>'O', 'Ú'=>'U']);

        if ($normalized === $acronym) {
            return $uni;
        }

        if (mb_stripos($nameNorm, $normalized) !== false || mb_stripos($normalized, $acronym) !== false) {
            return $uni;
        }
    }

    return null;
}

function matchGender(string $userInput): ?string {
    $clean = trim($userInput);
    $clean = ltrim($clean, '/');
    $clean = mb_strtolower($clean, 'UTF-8');

    $clean = strtr($clean, [
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u',
        'ñ'=>'n'
    ]);

    if (preg_match('/(senorita|mujer|chica|nina|dama|femenin)/u', $clean)) {
        return 'Señoritas';
    }

    if (preg_match('/(varon|hombre|chico|nino|caballero|masculin|chero|bicho)/u', $clean)) {
        return 'Varones';
    }

    if (preg_match('/(mixt|todo|cualquier|ambos|da igual|sin preferencia|lo que sea)/u', $clean)) {
        return 'Mixto';
    }

    return null;
}

function fetchListings(int $universityId, string $genderText, float $maxPrice): array {
    $genderMap = [
        'Señoritas' => 'female_only',
        'Varones'   => 'male_only',
        'Mixto'     => 'mixed'
    ];
    $genderParam = $genderMap[$genderText] ?? 'mixed';

    $params = http_build_query([
        'university'        => $universityId,
        'gender_preference' => $genderParam,
        'price__lte'        => $maxPrice
    ]);

    $url = API_BASE_URL . '/listings/?' . $params;
    logBot('INFO', "Consultando catálogo ElForaneo: {$url}");

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        CURLOPT_HTTPHEADER     => [
            'Accept: application/json',
            'User-Agent: ElForaneo-TelegramBot/1.0'
        ]
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200 || !$response) {
        logBot('ERROR', "Fallo al consultar /listings/ (HTTP {$httpCode}): {$error}");
        return [
            'status' => 'error',
            'count'  => 0,
            'items'  => []
        ];
    }

    $data = json_decode($response, true);
    if (!isset($data['results']) || !is_array($data['results'])) {
        logBot('ERROR', "Formato inesperado devuelto por /listings/");
        return [
            'status' => 'error',
            'count'  => 0,
            'items'  => []
        ];
    }

    return [
        'status' => 'success',
        'count'  => (int)($data['count'] ?? count($data['results'])),
        'items'  => $data['results']
    ];
}

// 7. Integración con LLM (Groq) para conversación libre

function consultarGroq(string $mensajeUsuario, ?string $apiKey): string {
    if (!$apiKey) {
        logBot('WARNING', 'Consulta LLM omitida: GROQ_API_KEY no configurada.');
        return "Disculpa, el módulo de asistencia conversacional no está disponible en este momento. Usa /buscar para ver habitaciones.";
    }

    $systemPrompt = "Eres el asistente virtual oficial de ElForaneo (plataforma de alojamiento para estudiantes universitarios en El Salvador). "
                  . "Responde siempre en español salvadoreño neutro, de forma muy concisa (máximo 2 a 3 oraciones), cordial y empática. "
                  . "Si el usuario pregunta por cuartos, zonas, precios o disponibilidad, oriéntalo amablemente a utilizar el comando /buscar. "
                  . "Delimitación estricta de alcance: No procesas pagos, no reservas en firme, no redactas contratos legales ni inventes números de teléfono.";

    $payload = [
        'model'       => 'groq/compound-mini',
        'messages'    => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $mensajeUsuario]
        ],
        'max_tokens'  => 180,
        'temperature' => 0.6
    ];

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim($apiKey)
        ],
        CURLOPT_POSTFIELDS     => json_encode($payload),
        CURLOPT_TIMEOUT        => 8
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $httpCode !== 200 || !$response) {
        logBot('ERROR', "Fallo en llamada a Groq (HTTP {$httpCode}): {$curlError}");
        return "Disculpa, en este momento no puedo procesar tu mensaje. Puedes escribir /buscar para explorar opciones de pupilajes.";
    }

    $data = json_decode($response, true);
    $reply = $data['choices'][0]['message']['content'] ?? '';
    $reply = preg_replace('/<think>[\s\S]*?<\/think>/i', '', $reply);

    return trim($reply) ?: "¡Hola! Escribe /buscar para filtrar habitaciones disponibles según tu universidad y presupuesto.";
}

// 8. Máquina de Estados y Despachador Principal

function processMessage(array $message, ?string $groqApiKey): void {
    $chatId = (int)($message['chat']['id'] ?? 0);
    $text   = trim((string)($message['text'] ?? ''));

    if (!$chatId || $text === '') {
        return;
    }

    logBot('IN', "Chat {$chatId}: {$text}");
    $session = getSession($chatId);
    $lowerText = mb_strtolower($text, 'UTF-8');

    // --- MANEJO DE COMANDOS GLOBALES ---

    if ($lowerText === '/cancel' || $lowerText === 'cancelar') {
        clearSession($chatId);
        $keyboard = [
            'keyboard' => [[['text' => '/buscar'], ['text' => '/ayuda']]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        sendTelegramMessage($chatId, "❌ Operación cancelada. Sesión reiniciada.\nEscribe /buscar cuando desees iniciar una nueva consulta.", $keyboard);
        return;
    }

    // Derivación a Asesor Humano (Criterio Rúbrica 23%)
    if (in_array($lowerText, ['/humano', '/soporte', 'humano', 'soporte', 'asesor', 'agente', 'persona'])) {
        clearSession($chatId);
        $soporteMsg = "👨‍💼 *Atención con un Asesor Humano*\n\n"
                    . "Si necesitas ayuda personalizada, resolver dudas de un anuncio o reportar una situación:\n\n"
                    . "• 💬 *WhatsApp Equipo ElForaneo:* https://wa.me/50370000000\n"
                    . "• 📧 *Correo electrónico:* soporte@elforaneo.com\n"
                    . "• 🌐 *Portal web oficial:* https://elforaneo.com\n\n"
                    . "Escribe /buscar para retomar la búsqueda automática o /start para el inicio.";
        sendTelegramMessage($chatId, $soporteMsg);
        return;
    }

    if ($lowerText === '/start') {
        clearSession($chatId);
        $welcome = "¡Hola! 👋 Bienvenido a *ElForaneo Bot*.\n\n"
                 . "Te ayudo a encontrar habitaciones y pupilajes universitarios en El Salvador.\n\n"
                 . "📌 *Comandos principales:*\n"
                 . "• /buscar - Iniciar búsqueda con filtros\n"
                 . "• /ayuda - Conocer el alcance del servicio\n"
                 . "• /humano - Contactar con un asesor del equipo\n"
                 . "• /cancel - Cancelar la operación en cualquier momento\n\n"
                 . "También puedes hacerme cualquier consulta libre sobre zonas o recomendaciones.";
        $keyboard = [
            'keyboard' => [
                [['text' => '/buscar'], ['text' => '/ayuda']],
                [['text' => '/humano']]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => true
        ];
        sendTelegramMessage($chatId, $welcome, $keyboard);
        return;
    }

    if ($lowerText === '/ayuda') {
        $ayuda = "ℹ️ *Acerca de ElForaneo Bot*\n\n"
               . "• *Lo que hago:* Filtro habitaciones disponibles por universidad, ambiente y presupuesto, entregándote fotos y enlaces web verificados.\n"
               . "• *Lo que NO hago:* No gestiono pagos directos, no redacto contratos legales ni reservo habitaciones formalmente.\n"
               . "• *Asesoría humana:* Escribe /humano para hablar con nuestro equipo de soporte.\n\n"
               . "Escribe /buscar para encontrar tu próximo pupilaje o escribe tu duda directamente.";
        sendTelegramMessage($chatId, $ayuda);
        return;
    }

    if ($lowerText === '/buscar') {
        $uniResponse = fetchUniversities();
        if ($uniResponse['status'] !== 'success' || empty($uniResponse['data'])) {
            sendTelegramMessage($chatId, "⚠️ El catálogo de ElForaneo no está disponible temporalmente. Intenta más tarde.");
            return;
        }

        $session['step'] = 'AWAITING_UNIVERSITY';
        $session['retries'] = 0;
        $session['slots'] = [
            'university_id'   => null,
            'university_name' => null,
            'gender'          => null,
            'max_price'       => null
        ];
        saveSession($chatId, $session);

        $buttons = [];
        $row = [];
        $acronymList = [];

        foreach ($uniResponse['data'] as $uni) {
            $acronym = $uni['acronym'] ?? $uni['name'];
            $acronymList[] = $acronym;
            $row[] = ['text' => $acronym];
            if (count($row) === 3) {
                $buttons[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $buttons[] = $row;
        }

        $listaLegible = implode(', ', array_slice($acronymList, 0, 12)) . '...';
        $msg = "🏢 *Paso 1 de 3:* ¿Cerca de qué universidad buscas alojamiento?\n\n"
             . "📌 *Opciones registradas:*\n`" . $listaLegible . "`\n\n"
             . "👇 *Toca un botón abajo o escribe el acrónimo (ej: UES, /ues):*";

        sendTelegramMessage($chatId, $msg, [
            'keyboard'          => $buttons,
            'resize_keyboard'   => true,
            'one_time_keyboard' => false
        ]);
        return;
    }

    // --- MÁQUINA DE ESTADOS DETERMINISTA ---

    switch ($session['step']) {
        case 'AWAITING_UNIVERSITY':
            $uniResponse = fetchUniversities();
            $matched = ($uniResponse['status'] === 'success') ? matchUniversity($text, $uniResponse['data']) : null;

            if (!$matched) {
                $session['retries']++;
                if ($session['retries'] >= MAX_RETRIES) {
                    clearSession($chatId);
                    sendTelegramMessage($chatId, "⚠️ Has alcanzado el límite de 3 intentos fallidos. Operación reiniciada. Escribe /buscar para empezar o /humano para asistencia.");
                    return;
                }
                saveSession($chatId, $session);

                $buttons = [];
                $row = [];
                if ($uniResponse['status'] === 'success') {
                    foreach ($uniResponse['data'] as $uni) {
                        $row[] = ['text' => $uni['acronym'] ?? $uni['name']];
                        if (count($row) === 3) {
                            $buttons[] = $row;
                            $row = [];
                        }
                    }
                    if (!empty($row)) $buttons[] = $row;
                }

                $errorMsg = "⚠️ Universidad no reconocida (intento {$session['retries']}/3).\n\n"
                          . "Por favor toca uno de los botones disponibles o escribe un acrónimo válido (ej: *UES*, *UCA*, *UTEC*, *UJMD*):";

                sendTelegramMessage($chatId, $errorMsg, [
                    'keyboard'          => $buttons,
                    'resize_keyboard'   => true,
                    'one_time_keyboard' => false
                ]);
                return;
            }

            $session['slots']['university_id'] = (int)$matched['id'];
            $session['slots']['university_name'] = $matched['acronym'] ?? $matched['name'];
            $session['step'] = 'AWAITING_GENDER';
            $session['retries'] = 0;
            saveSession($chatId, $session);

            $keyboard = [
                'keyboard' => [
                    [['text' => 'Señoritas'], ['text' => 'Varones']],
                    [['text' => 'Mixto']]
                ],
                'resize_keyboard'   => true,
                'one_time_keyboard' => false
            ];

            $genderPrompt = "👥 *Paso 2 de 3:* ¿Qué ambiente o preferencia de género necesitas?\n\n"
                          . "📌 *Opciones válidas:*\n"
                          . "• *Señoritas* (solo mujeres / alumnas)\n"
                          . "• *Varones* (solo hombres / alumnos)\n"
                          . "• *Mixto* (sin restricción de género)\n\n"
                          . "👇 *Selecciona una opción del teclado o escríbela (ej: Señoritas, /senoritas, para mujeres):*";

            sendTelegramMessage($chatId, $genderPrompt, $keyboard);
            return;

        case 'AWAITING_GENDER':
            $selectedGender = matchGender($text);

            if (!$selectedGender) {
                $session['retries']++;
                if ($session['retries'] >= MAX_RETRIES) {
                    clearSession($chatId);
                    sendTelegramMessage($chatId, "⚠️ Límite de 3 intentos alcanzado. Reiniciando sesión. Escribe /buscar para intentarlo nuevamente o /humano para soporte.");
                    return;
                }
                saveSession($chatId, $session);

                $keyboard = [
                    'keyboard' => [
                        [['text' => 'Señoritas'], ['text' => 'Varones']],
                        [['text' => 'Mixto']]
                    ],
                    'resize_keyboard'   => true,
                    'one_time_keyboard' => false
                ];

                $errorGen = "⚠️ Opción no válida (intento {$session['retries']}/3).\n\n"
                          . "Por favor toca uno de los botones o escribe una de estas alternativas:\n"
                          . "• *Señoritas* (o chicas, mujeres)\n"
                          . "• *Varones* (o chicos, hombres)\n"
                          . "• *Mixto* (o cualquier ambiente)";

                sendTelegramMessage($chatId, $errorGen, $keyboard);
                return;
            }

            $session['slots']['gender'] = $selectedGender;
            $session['step'] = 'AWAITING_PRICE';
            $session['retries'] = 0;
            saveSession($chatId, $session);

            $keyboard = [
                'keyboard' => [
                    [['text' => '100'], ['text' => '150']],
                    [['text' => '200'], ['text' => '250']]
                ],
                'resize_keyboard'   => true,
                'one_time_keyboard' => true
            ];

            $pricePrompt = "💵 *Paso 3 de 3:* ¿Cuál es tu presupuesto mensual máximo en USD?\n\n"
                         . "👇 *Selecciona un monto sugerido o escribe tu presupuesto (ej: 120, 150, $180):*";

            sendTelegramMessage($chatId, $pricePrompt, $keyboard);
            return;

        case 'AWAITING_PRICE':
            $cleanedPrice = preg_replace('/[^0-9.]/', '', $text);
            $price = (float)$cleanedPrice;

            if ($price <= 0) {
                $session['retries']++;
                if ($session['retries'] >= MAX_RETRIES) {
                    clearSession($chatId);
                    sendTelegramMessage($chatId, "⚠️ Presupuesto no válido tras 3 intentos. Reiniciando sesión. Usa /buscar para iniciar.");
                    return;
                }
                saveSession($chatId, $session);
                sendTelegramMessage($chatId, "⚠️ Ingresa un monto numérico mayor a 0 (ej: 150). Intento {$session['retries']}/3:");
                return;
            }

            $session['slots']['max_price'] = $price;
            sendTelegramMessage($chatId, "🔍 Consultando catálogo de ElForaneo con tus criterios...");

            $results = fetchListings(
                $session['slots']['university_id'],
                $session['slots']['gender'],
                $session['slots']['max_price']
            );

            if ($results['status'] !== 'success') {
                clearSession($chatId);
                sendTelegramMessage($chatId, "⚠️ Ocurrió una dificultad al conectar con ElForaneo. Por favor intenta más tarde con /buscar.");
                return;
            }

            if (empty($results['items'])) {
                clearSession($chatId);
                $msg = "❌ No encontré alojamientos para *" . htmlspecialchars($session['slots']['gender']) . "* "
                     . "en *" . htmlspecialchars($session['slots']['university_name']) . "* por un presupuesto de hasta *$" . $price . "* USD.\n\n"
                     . "💡 *Sugerencia:* Puedes probar con /buscar aumentando tu presupuesto o seleccionando ambiente *Mixto*.";
                sendTelegramMessage($chatId, $msg);
                return;
            }

            $responseMsg = "🏠 *Alojamientos encontrados en ElForaneo:* ({$results['count']} disponibles)\n\n";
            $limit = array_slice($results['items'], 0, 5);

            foreach ($limit as $item) {
                $title = $item['title'] ?? 'Habitación universitaria';
                $cost  = $item['price'] ?? 'N/D';
                $slug  = $item['slug'] ?? '';
                $url   = "https://elforaneo.com/alojamiento/{$slug}";

                $responseMsg .= "• *{$title}* - \${$cost}/mes\n";
                $responseMsg .= "  🌐 [Ver fotos, mapa y WhatsApp]({$url})\n\n";
            }

            $responseMsg .= "Escribe /buscar para realizar otra consulta, /humano para asistencia o hazme cualquier pregunta sobre la zona.";
            clearSession($chatId);
            sendTelegramMessage($chatId, $responseMsg);
            return;

        default:
            // --- CONVERSACIÓN LIBRE CON LLM (GROQ) ---
            $aiReply = consultarGroq($text, $groqApiKey);
            sendTelegramMessage($chatId, $aiReply);
            return;
    }
}

// 9. Punto de Entrada del Webhook
$rawPayload = file_get_contents('php://input');

if (!$rawPayload) {
    http_response_code(200);
    exit("Servicio Webhook de ElForaneo activo.\n");
}

$update = json_decode($rawPayload, true);
if (isset($update['message'])) {
    processMessage($update['message'], $groqApiKey);
}

http_response_code(200);
echo "OK";
