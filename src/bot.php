<?php

declare(strict_types=1);

/**
 * CitasBot - Clinica Sonrisa Sana.
 *
 * Regla: las acciones de citas se resuelven con codigo determinista.
 * Ollama solo atiende mensajes abiertos que no son acciones criticas.
 */

const TELEGRAM_BASE = 'https://api.telegram.org';
const OFFSET_FILE = __DIR__ . '/../.offset';
const RUNTIME_DIR = __DIR__ . '/../.runtime';
const STATE_FILE = RUNTIME_DIR . '/state.json';
const LOG_FILE = RUNTIME_DIR . '/bot.log';

function loadConfig(string $dir): array
{
    $envFile = $dir . '/../.env';
    if (!is_file($envFile)) {
        $envFile = $dir . '/.env';
    }
    $values = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if ($values === false || empty($values['BOT_TOKEN'])) {
        fwrite(STDERR, "No se encontro BOT_TOKEN en el archivo .env\n");
        exit(1);
    }

    return [
        'token' => (string) $values['BOT_TOKEN'],
        'ollama_url' => rtrim((string) ($values['OLLAMA_URL'] ?? 'http://127.0.0.1:11434'), '/'),
        'ollama_model' => (string) ($values['OLLAMA_MODEL'] ?? 'qwen2.5:0.5b'),
    ];
}

function ensureRuntime(): void
{
    if (!is_dir(RUNTIME_DIR)) {
        mkdir(RUNTIME_DIR, 0700, true);
    }
}

function logLine(string $direction, string $chatId, string $text): void
{
    ensureRuntime();
    $safeText = str_replace(["\r", "\n"], ' ', $text);
    file_put_contents(
        LOG_FILE,
        date('[Y-m-d H:i:s] ') . $direction . ' chat=' . $chatId . ' ' . $safeText . PHP_EOL,
        FILE_APPEND | LOCK_EX
    );
}

function loadOffset(): int
{
    if (!is_file(OFFSET_FILE)) {
        return 0;
    }

    return (int) trim((string) file_get_contents(OFFSET_FILE));
}

function saveOffset(int $offset): void
{
    file_put_contents(OFFSET_FILE, (string) $offset, LOCK_EX);
}

function emptyState(): array
{
    return [
        'chats' => [],
        'appointments' => [],
        'next_appointment_id' => 1001,
    ];
}

function loadState(): array
{
    ensureRuntime();
    if (!is_file(STATE_FILE)) {
        return emptyState();
    }

    $data = json_decode((string) file_get_contents(STATE_FILE), true);
    return is_array($data) ? array_replace_recursive(emptyState(), $data) : emptyState();
}

function saveState(array $state): void
{
    ensureRuntime();
    file_put_contents(
        STATE_FILE,
        json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        LOCK_EX
    );
}

function httpJson(string $url, ?array $payload = null, int $timeout = 30): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    if ($payload !== null) {
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json'],
        ]);
    }

    $response = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $error !== '') {
        return ['ok' => false, 'error' => $error ?: 'HTTP request failed', 'status' => $status];
    }

    $data = json_decode((string) $response, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Invalid JSON response', 'status' => $status];
    }

    $data['_http_status'] = $status;
    return $data;
}

function telegramApi(string $token, string $method, ?array $payload = null): array
{
    return httpJson(TELEGRAM_BASE . '/bot' . $token . '/' . $method, $payload, 60);
}

function getUpdates(string $token, int $offset): array
{
    $query = http_build_query([
        'timeout' => 30,
        'offset' => $offset + 1,
        'allowed_updates' => json_encode(['message']),
    ]);
    return httpJson(TELEGRAM_BASE . '/bot' . $token . '/getUpdates?' . $query, null, 40);
}

function keyboard(array $rows, bool $resize = true): array
{
    return [
        'keyboard' => $rows,
        'resize_keyboard' => $resize,
        'one_time_keyboard' => false,
    ];
}

function removeKeyboard(): array
{
    return ['remove_keyboard' => true];
}

function sendMessage(string $token, string|int $chatId, string $text, ?array $markup = null): void
{
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
    ];
    if ($markup !== null) {
        $payload['reply_markup'] = $markup;
    }

    $response = telegramApi($token, 'sendMessage', $payload);
    if (empty($response['ok'])) {
        logLine('ERROR', (string) $chatId, 'sendMessage fallo');
        return;
    }
    logLine('OUT', (string) $chatId, $text);
}

function lower(string $text): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
}

function normalize(string $text): string
{
    return trim(lower($text));
}

function mainMenu(): array
{
    return keyboard([
        ['Agendar cita', 'Consultar mis citas'],
        ['Cancelar cita', 'Reprogramar cita'],
        ['Servicios y precios', 'Ubicacion y horario'],
        ['Hablar con recepcion', '/clima San Salvador'],
    ]);
}

function welcome(): string
{
    return "Hola, soy CitasBot de Clinica Sonrisa Sana. Puedo ayudarte a agendar, "
        . "consultar, cancelar o reprogramar una cita, ver precios y ubicacion, "
        . "o comunicarte con recepcion.\n\n"
        . "Usa los botones o escribe /ayuda en cualquier momento.";
}

function help(): string
{
    return "Opciones disponibles:\n"
        . "- Agendar, consultar, cancelar o reprogramar una cita\n"
        . "- Consultar servicios, precios y ubicacion\n"
        . "- Consultar el clima con /clima <ciudad>\n"
        . "- /cancelar: abandonar el flujo actual\n"
        . "- /volver: regresar un paso\n"
        . "- Escribir otra pregunta: recibiras ayuda conversacional.";
}

function defaultChat(): array
{
    return ['step' => 'idle', 'attempts' => 0, 'data' => []];
}

function &chatState(array &$state, string $chatId): array
{
    if (!isset($state['chats'][$chatId]) || !is_array($state['chats'][$chatId])) {
        $state['chats'][$chatId] = defaultChat();
    }
    return $state['chats'][$chatId];
}

function resetChat(array &$chat): void
{
    $chat = defaultChat();
}

function askForStep(string $step): array
{
    return match ($step) {
        'appointment_service' => [
            'Que servicio necesitas?',
            keyboard([['Limpieza dental', 'Consulta general'], ['Urgencia', 'Ver precios']]),
        ],
        'appointment_date' => [
            'Que fecha deseas? Puedes escribir, por ejemplo, "jueves 11 de septiembre".',
            null,
        ],
        'appointment_time' => [
            'Elige uno de estos horarios disponibles:',
            keyboard([['09:00', '11:00'], ['15:00', '17:00']]),
        ],
        'appointment_name' => ['Es tu primera vez. Escribe tu nombre completo.', null],
        'appointment_phone' => ['Escribe un numero de telefono para registrar la cita.', null],
        'cancel_id' => ['Escribe el numero de la cita que deseas cancelar.', null],
        'cancel_confirm' => ['Confirma la cancelacion con "Si, cancelar cita" o "No, conservar cita".', keyboard([['Si, cancelar cita'], ['No, conservar cita']])],
        'reschedule_id' => ['Escribe el numero de la cita que deseas reprogramar.', null],
        'reschedule_date' => ['Que nueva fecha deseas para tu cita?', null],
        'consult_phone' => ['Escribe el telefono registrado para consultar tus citas.', null],
        default => ['Escribe tu solicitud o usa /ayuda.', mainMenu()],
    };
}

function sendStepQuestion(string $token, string $chatId, string $step): void
{
    [$text, $markup] = askForStep($step);
    sendMessage($token, $chatId, $text, $markup);
}

function retryOrHuman(string $token, string $chatId, array &$chat, string $message): void
{
    $chat['attempts']++;
    if ($chat['attempts'] >= 3) {
        sendMessage($token, $chatId, $message . "\n\nYa usamos tres intentos. Te comunicaremos con recepcion para ayudarte.", mainMenu());
        resetChat($chat);
        return;
    }

    sendMessage($token, $chatId, $message . "\n\nIntento " . $chat['attempts'] . " de 3.");
    sendStepQuestion($token, $chatId, $chat['step']);
}

function extractService(string $text): ?string
{
    $value = normalize($text);
    return match (true) {
        str_contains($value, 'limpieza') => 'Limpieza dental',
        str_contains($value, 'consulta') => 'Consulta general',
        str_contains($value, 'urgencia') => 'Urgencia',
        default => null,
    };
}

function extractDate(string $text): ?string
{
    if (preg_match('/\b(?:lunes|martes|miercoles|jueves|viernes|sabado|domingo)?\s*\d{1,2}(?:\s+de\s+[a-záéíóú]+)?(?:\s+de\s+\d{4})?\b/iu', $text, $match)) {
        return trim($match[0]);
    }
    return null;
}

function beginAppointment(string $token, string $chatId, array &$chat, string $text): void
{
    $chat = ['step' => 'appointment_service', 'attempts' => 0, 'data' => []];
    $service = extractService($text);
    $date = extractDate($text);
    if ($service !== null) {
        $chat['data']['service'] = $service;
    }
    if ($date !== null) {
        $chat['data']['date'] = $date;
    }

    if (!isset($chat['data']['service'])) {
        sendStepQuestion($token, $chatId, 'appointment_service');
        return;
    }
    if (!isset($chat['data']['date'])) {
        $chat['step'] = 'appointment_date';
        sendStepQuestion($token, $chatId, 'appointment_date');
        return;
    }

    $chat['step'] = 'appointment_time';
    sendStepQuestion($token, $chatId, 'appointment_time');
}

function createAppointment(array &$state, string $chatId, array $data): int
{
    $id = (int) $state['next_appointment_id']++;
    $state['appointments'][(string) $id] = [
        'id' => $id,
        'chat_id' => $chatId,
        'status' => 'active',
        'service' => $data['service'],
        'date' => $data['date'],
        'time' => $data['time'],
        'name' => $data['name'],
        'phone' => $data['phone'],
    ];
    return $id;
}

function findAppointment(array $state, string $chatId, string $id): ?array
{
    $appointment = $state['appointments'][$id] ?? null;
    if (!is_array($appointment) || (string) ($appointment['chat_id'] ?? '') !== $chatId) {
        return null;
    }
    return $appointment;
}

function appointmentSummary(array $appointment): string
{
    return '#' . $appointment['id'] . ': ' . $appointment['service'] . ' el '
        . $appointment['date'] . ' a las ' . $appointment['time'];
}

function handleStep(string $token, string $chatId, array &$state, array &$chat, string $text): void
{
    $value = trim($text);
    $normalized = normalize($value);

    switch ($chat['step']) {
        case 'appointment_service':
            $service = extractService($value);
            if ($service === null) {
                retryOrHuman($token, $chatId, $chat, 'No reconoci el servicio. Elige una opcion del menu o escribe limpieza, consulta o urgencia.');
                return;
            }
            $chat['data']['service'] = $service;
            $chat['attempts'] = 0;
            $chat['step'] = 'appointment_date';
            sendStepQuestion($token, $chatId, $chat['step']);
            return;

        case 'appointment_date':
            if (strlen($value) < 3) {
                retryOrHuman($token, $chatId, $chat, 'No pude interpretar la fecha. Incluye el dia y, si es posible, el mes.');
                return;
            }
            $chat['data']['date'] = $value;
            $chat['attempts'] = 0;
            $chat['step'] = 'appointment_time';
            sendStepQuestion($token, $chatId, $chat['step']);
            return;

        case 'appointment_time':
            if (!preg_match('/^(09:00|11:00|15:00|17:00)$/', $value)) {
                retryOrHuman($token, $chatId, $chat, 'Ese horario no esta disponible. Selecciona uno de los horarios mostrados.');
                return;
            }
            $chat['data']['time'] = $value;
            $chat['attempts'] = 0;
            $chat['step'] = 'appointment_name';
            sendStepQuestion($token, $chatId, $chat['step']);
            return;

        case 'appointment_name':
            if (strlen($value) < 3 || preg_match('/\d/', $value)) {
                retryOrHuman($token, $chatId, $chat, 'Escribe un nombre completo valido, sin numeros.');
                return;
            }
            $chat['data']['name'] = $value;
            $chat['attempts'] = 0;
            $chat['step'] = 'appointment_phone';
            sendStepQuestion($token, $chatId, $chat['step']);
            return;

        case 'appointment_phone':
            $digits = preg_replace('/\D+/', '', $value);
            if ($digits === null || strlen($digits) < 7 || strlen($digits) > 15) {
                retryOrHuman($token, $chatId, $chat, 'Escribe un numero de telefono valido.');
                return;
            }
            $chat['data']['phone'] = $digits;
            $id = createAppointment($state, $chatId, $chat['data']);
            $appointment = $state['appointments'][(string) $id];
            sendMessage(
                $token,
                $chatId,
                'Cita confirmada: ' . appointmentSummary($appointment) . ".\nTe enviaremos un recordatorio un dia antes. ¿Necesitas algo mas?",
                mainMenu()
            );
            resetChat($chat);
            return;

        case 'consult_phone':
            $digits = preg_replace('/\D+/', '', $value);
            $found = [];
            foreach ($state['appointments'] as $appointment) {
                if (($appointment['phone'] ?? '') === $digits && ($appointment['status'] ?? '') === 'active') {
                    $found[] = appointmentSummary($appointment);
                }
            }
            if ($found === []) {
                sendMessage($token, $chatId, 'No encontre citas activas con ese telefono. ¿Quieres agendar una?', mainMenu());
            } else {
                sendMessage($token, $chatId, "Tus citas activas:\n- " . implode("\n- ", $found), mainMenu());
            }
            resetChat($chat);
            return;

        case 'cancel_id':
            if (!ctype_digit($value)) {
                retryOrHuman($token, $chatId, $chat, 'El numero de cita debe ser un numero entero, por ejemplo 1001.');
                return;
            }
            $appointment = findAppointment($state, $chatId, $value);
            if ($appointment === null || $appointment['status'] !== 'active') {
                retryOrHuman($token, $chatId, $chat, 'No encontre una cita activa con ese numero.');
                return;
            }
            $chat['data']['appointment_id'] = $value;
            $chat['attempts'] = 0;
            $chat['step'] = 'cancel_confirm';
            sendMessage($token, $chatId, 'Encontré tu cita: ' . appointmentSummary($appointment) . ".\n¿Deseas cancelarla?", keyboard([['Si, cancelar cita'], ['No, conservar cita']]));
            return;

        case 'cancel_confirm':
            if (in_array($normalized, ['si, cancelar cita', 'si', 'sí', 'cancelar'], true)) {
                $id = (string) $chat['data']['appointment_id'];
                $state['appointments'][$id]['status'] = 'canceled';
                sendMessage($token, $chatId, 'La cita fue cancelada. ¿Necesitas algo mas?', mainMenu());
                resetChat($chat);
                return;
            }
            if (in_array($normalized, ['no, conservar cita', 'no', 'conservar'], true)) {
                sendMessage($token, $chatId, 'Conserve tu cita sin cambios. ¿Necesitas algo mas?', mainMenu());
                resetChat($chat);
                return;
            }
            retryOrHuman($token, $chatId, $chat, 'Responde usando una de las opciones de confirmacion.');
            return;

        case 'reschedule_id':
            if (!ctype_digit($value) || findAppointment($state, $chatId, $value) === null) {
                retryOrHuman($token, $chatId, $chat, 'No encontre una cita activa con ese numero.');
                return;
            }
            $chat['data']['appointment_id'] = $value;
            $chat['attempts'] = 0;
            $chat['step'] = 'reschedule_date';
            sendStepQuestion($token, $chatId, $chat['step']);
            return;

        case 'reschedule_date':
            if (strlen($value) < 3) {
                retryOrHuman($token, $chatId, $chat, 'No pude interpretar la nueva fecha.');
                return;
            }
            $id = (string) $chat['data']['appointment_id'];
            $state['appointments'][$id]['date'] = $value;
            sendMessage($token, $chatId, 'Reprogramé tu cita para ' . $value . '. ¿Necesitas algo mas?', mainMenu());
            resetChat($chat);
            return;
    }
}

function weatherCode(int $code): string
{
    return match (true) {
        $code === 0 => 'despejado',
        $code <= 3 => 'parcialmente nublado',
        $code <= 48 => 'con neblina',
        $code <= 67 => 'con lluvia',
        $code <= 77 => 'con nieve',
        $code <= 82 => 'con chubascos',
        default => 'con tormenta',
    };
}

function weather(string $city): ?string
{
    $query = rawurlencode($city);
    $geo = httpJson('https://geocoding-api.open-meteo.com/v1/search?name=' . $query . '&count=1&language=es&format=json');
    if (empty($geo['results'][0])) {
        return null;
    }

    $place = $geo['results'][0];
    $lat = (float) $place['latitude'];
    $lon = (float) $place['longitude'];
    $forecast = httpJson('https://api.open-meteo.com/v1/forecast?latitude=' . $lat . '&longitude=' . $lon . '&current=temperature_2m,weather_code&timezone=auto');
    if (empty($forecast['current'])) {
        return null;
    }

    $temperature = $forecast['current']['temperature_2m'];
    $code = (int) $forecast['current']['weather_code'];
    return 'En ' . $place['name'] . ' hay ' . $temperature . ' °C y el cielo esta ' . weatherCode($code) . '.';
}

function redactForModel(string $text): string
{
    $text = preg_replace('/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/', '[correo omitido]', $text) ?? $text;
    $text = preg_replace('/(?:\+?\d[\d\s-]{6,}\d)/', '[telefono omitido]', $text) ?? $text;
    return trim($text);
}

function ollamaAnswer(string $text, array $config): ?string
{
    $safeText = redactForModel($text);
    if ($safeText === '') {
        return null;
    }

    $payload = [
        'model' => $config['ollama_model'],
        'stream' => false,
        'prompt' => "Eres el asistente informativo de Clinica Sonrisa Sana. "
            . "Responde en español, de forma breve y clara. No inventes citas, precios ni datos medicos. "
            . "Si la persona necesita agendar, cancelar o reprogramar, indicale que use el menu del bot. "
            . "Consulta del usuario: " . $safeText,
    ];
    $response = httpJson($config['ollama_url'] . '/api/generate', $payload, 90);
    if (empty($response['response'])) {
        return null;
    }
    return trim((string) $response['response']);
}

function processMessage(string $token, array $config, array &$state, string $chatId, string $text): void
{
    $value = trim($text);
    $normalized = normalize($value);
    $chat = &chatState($state, $chatId);
    logLine('IN', $chatId, $value);

    if ($normalized === '/start' || $normalized === 'hola') {
        resetChat($chat);
        sendMessage($token, $chatId, welcome(), mainMenu());
        return;
    }
    if ($normalized === '/ayuda' || $normalized === '/help' || $normalized === 'ayuda') {
        sendMessage($token, $chatId, help(), mainMenu());
        if ($chat['step'] !== 'idle') {
            sendStepQuestion($token, $chatId, $chat['step']);
        }
        return;
    }
    if ($normalized === '/cancelar' || $normalized === 'cancelar') {
        resetChat($chat);
        sendMessage($token, $chatId, 'Cancele el flujo actual. ¿Que deseas hacer?', mainMenu());
        return;
    }
    if ($normalized === '/volver' || $normalized === 'volver') {
        $previous = [
            'appointment_date' => 'appointment_service',
            'appointment_time' => 'appointment_date',
            'appointment_name' => 'appointment_time',
            'appointment_phone' => 'appointment_name',
            'reschedule_date' => 'reschedule_id',
            'cancel_confirm' => 'cancel_id',
        ][$chat['step']] ?? null;
        if ($previous === null) {
            resetChat($chat);
            sendMessage($token, $chatId, 'Regrese al menu principal.', mainMenu());
        } else {
            $chat['step'] = $previous;
            $chat['attempts'] = 0;
            sendStepQuestion($token, $chatId, $previous);
        }
        return;
    }
    if (preg_match('/^\/clima(?:\s+(.+))?$/iu', $value, $match)) {
        $city = trim((string) ($match[1] ?? ''));
        if ($city === '') {
            sendMessage($token, $chatId, 'Escribe una ciudad, por ejemplo: /clima San Salvador');
            return;
        }
        $result = weather($city);
        sendMessage($token, $chatId, $result ?? 'No pude consultar el clima en este momento. Intenta de nuevo mas tarde.');
        return;
    }

    if ($chat['step'] !== 'idle') {
        handleStep($token, $chatId, $state, $chat, $value);
        saveState($state);
        return;
    }

    if (str_contains($normalized, 'consultar') && str_contains($normalized, 'cita')) {
        $chat['step'] = 'consult_phone';
        sendStepQuestion($token, $chatId, $chat['step']);
        return;
    }
    if (str_contains($normalized, 'cancelar')) {
        $chat['step'] = 'cancel_id';
        sendStepQuestion($token, $chatId, $chat['step']);
        return;
    }
    if (str_contains($normalized, 'agendar') || str_contains($normalized, 'cita') || str_contains($normalized, 'reservar')) {
        beginAppointment($token, $chatId, $chat, $value);
        saveState($state);
        return;
    }
    if (str_contains($normalized, 'reprogramar') || str_contains($normalized, 'cambiar la hora')) {
        $chat['step'] = 'reschedule_id';
        sendStepQuestion($token, $chatId, $chat['step']);
        return;
    }
    if (str_contains($normalized, 'precio') || str_contains($normalized, 'servicio')) {
        sendMessage($token, $chatId, "Servicios disponibles:\n- Limpieza dental\n- Consulta general\n- Urgencia\n\nPara precios exactos, recepcion confirmara la tarifa vigente.", mainMenu());
        return;
    }
    if (str_contains($normalized, 'ubicacion') || str_contains($normalized, 'horario')) {
        sendMessage($token, $chatId, 'La Clinica Sonrisa Sana atiende de lunes a viernes. Escribe "hablar con recepcion" para confirmar la ubicacion y horario vigente.', mainMenu());
        return;
    }
    if (str_contains($normalized, 'humano') || str_contains($normalized, 'recepcion')) {
        sendMessage($token, $chatId, 'Registré tu solicitud para recepcion. Una persona del equipo te contactara.', mainMenu());
        return;
    }

    $answer = ollamaAnswer($value, $config);
    sendMessage($token, $chatId, $answer ?? 'No pude responder esa consulta. Escribe /ayuda o elige una opcion del menu.', mainMenu());
}

$config = loadConfig(__DIR__);
$offset = loadOffset();
$state = loadState();

echo "CitasBot Sesion 2 iniciado con long polling.\n";

while (true) {
    $updates = getUpdates($config['token'], $offset);
    if (!empty($updates['ok']) && isset($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $offset = max($offset, (int) ($update['update_id'] ?? 0));
            $message = $update['message'] ?? null;
            if (!is_array($message) || !isset($message['chat']['id'])) {
                saveOffset($offset);
                continue;
            }
            $chatId = (string) $message['chat']['id'];
            $text = (string) ($message['text'] ?? '');
            if ($text !== '') {
                processMessage($config['token'], $config, $state, $chatId, $text);
                saveState($state);
            }
            saveOffset($offset);
        }
    } else {
        usleep(500000);
    }
}
