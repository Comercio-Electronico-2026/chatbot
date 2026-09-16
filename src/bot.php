<?php

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';

// Leer entrada de Telegram (Webhook)
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    exit('OK');
}

// Extraer chat_id, callback_data o texto
$chatId = null;
$text = null;
$callbackData = null;
$callbackId = null;

if (isset($update['message'])) {
    $chatId = $update['message']['chat']['id'];
    $text = trim($update['message']['text'] ?? '');
    logMessage('IN_MSG', $chatId, $text);
} elseif (isset($update['callback_query'])) {
    $chatId = $update['callback_query']['message']['chat']['id'];
    $callbackData = $update['callback_query']['data'];
    $callbackId = $update['callback_query']['id'];
    logMessage('IN_CALLBACK', $chatId, $callbackData);

    sendTelegramRequest('answerCallbackQuery', ['callback_query_id' => $callbackId]);
}

if (!$chatId) exit('OK');

// Helper para llamadas a la API de Telegram
function sendTelegramRequest($method, $data) {
    $url = TELEGRAM_API . $method;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $res = curl_exec($ch);
    curl_close($ch);
    return json_decode($res, true);
}

function sendMessage($chatId, $text, $keyboard = null) {
    logMessage('OUT_MSG', $chatId, $text);
    $payload = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    if ($keyboard) {
        $payload['reply_markup'] = $keyboard;
    }
    return sendTelegramRequest('sendMessage', $payload);
}

function getMainMenuKeyboard() {
    return [
        'inline_keyboard' => [
            [['text' => 'Agendar cita', 'callback_data' => 'menu_agendar']],
            [['text' => 'Cancelar o reprogramar', 'callback_data' => 'menu_cancelar_repro']],
            [['text' => 'Ver servicios y precios', 'callback_data' => 'menu_precios']],
            [['text' => 'Hablar con alguien', 'callback_data' => 'menu_humano']]
        ]
    ];
}

function getNavButtons() {
    return [
        ['text' => '⬅️ Atrás', 'callback_data' => 'nav_atras'],
        ['text' => '❌ Cancelar', 'callback_data' => 'nav_cancelar']
    ];
}

// Generador dinámico de los próximos 3 días a partir de mañana
function getNext3DaysButtons($prefix) {
    $diasEspanol = [
        'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miércoles',
        'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sábado', 'Sunday' => 'Domingo'
    ];
    $buttons = [];
    for ($i = 1; $i <= 3; $i++) {
        $timestamp = strtotime("+$i day");
        $dayName = $diasEspanol[date('l', $timestamp)];
        $dayNum = date('j', $timestamp);
        $dateText = "$dayName $dayNum";
        $buttons[] = [['text' => $dateText, 'callback_data' => $prefix . $dateText]];
    }
    return $buttons;
}

function cleanText($text) {
    $map = [
        'Á'=>'a', 'É'=>'e', 'Í'=>'i', 'Ó'=>'o', 'Ú'=>'u', 'Ü'=>'u', 'Ñ'=>'n',
        'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u', 'ü'=>'u', 'ñ'=>'n'
    ];
    return strtolower(strtr($text, $map));
}

function askOllama($userPrompt) {
    $url = 'http://10.0.2.2:11434/api/generate';

    $payload = [
        'model' => 'llama3.2:1b',
        'prompt' => "Eres la recepcionista de Clínica Dental Sonrisa. Responde brevemente (máximo 15 palabras) en español a: " . $userPrompt,
        'stream' => false,
        'keep_alive' => '15m',
        'options' => [
            'num_predict' => 25,
            'temperature' => 0.3,
            'repeat_penalty' => 1.2
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $json = json_decode($response, true);
        return $json['response'] ?? null;
    }
    return null;
}

// --- RECUPERAR ESTADO DE LA BASE DE DATOS ---
$stateData = Database::getState($chatId);
$currentState = $stateData['state'] ?? 'MAIN_MENU';
$sessionData = $stateData['data'] ?? [];
$attempts = $stateData['attempts'] ?? 0;

// --- INTERCEPCIÓN GLOBAL DE NAVEGACIÓN Y COMANDOS ---
if ($text === '/start' || $callbackData === 'nav_cancelar' || $text === 'menu_principal') {
    Database::resetState($chatId);
    sendMessage($chatId, "¡Hola! Bienvenido a <b>Clínica Dental Sonrisa</b>. ¿En qué puedo ayudarte hoy?", getMainMenuKeyboard());
    exit('OK');
}

if ($callbackData === 'menu_agendar') {
    Database::setState($chatId, 'BOOK_SERVICE');
    $keyboard = [
        'inline_keyboard' => [
            [['text' => 'Limpieza dental', 'callback_data' => 'srv_Limpieza dental']],
            [['text' => 'Extracción', 'callback_data' => 'srv_Extracción']],
            [['text' => 'Ortodoncia', 'callback_data' => 'srv_Ortodoncia']],
            [['text' => 'Revisión general', 'callback_data' => 'srv_Revisión general']],
            [getNavButtons()[0]]
        ]
    ];
    sendMessage($chatId, "Perfecto. ¿Qué servicio necesitas?", $keyboard);
    exit('OK');
}

if ($callbackData === 'menu_cancelar_repro') {
    Database::setState($chatId, 'SEARCH_PHONE');
    $keyboard = ['inline_keyboard' => [[getNavButtons()[0]]]];
    sendMessage($chatId, "Claro. ¿Con qué número de teléfono agendaste la cita? (8 dígitos)", $keyboard);
    exit('OK');
}

if ($callbackData === 'menu_precios') {
    $msg = "<b>Catálogo de Servicios y Precios:</b>\n\n" .
           "• <b>Limpieza dental:</b> $25.00\n" .
           "• <b>Extracción:</b> $35.00\n" .
           "• <b>Ortodoncia (Evaluación):</b> $15.00\n" .
           "• <b>Revisión general:</b> $10.00\n\n" .
           "Horarios de atención: Lunes a Viernes de 8:00 am a 5:00 pm.";
    sendMessage($chatId, $msg, getMainMenuKeyboard());
    exit('OK');
}

if ($callbackData === 'menu_humano') {
    Database::setState($chatId, 'HUMAN_ESC');
    sendMessage($chatId, "Un agente de nuestro personal te atenderá en breve por este medio. Por favor, déjanos tu consulta o aguarda un momento.");
    exit('OK');
}

// Selección de cita individual cuando hay múltiples
if (strpos($callbackData, 'select_cita_') === 0) {
    $citaId = (int)str_replace('select_cita_', '', $callbackData);
    $citas = $sessionData['citas'] ?? [];
    $selectedCita = null;
    foreach ($citas as $c) {
        if ($c['id'] == $citaId) {
            $selectedCita = $c;
            break;
        }
    }
    if ($selectedCita) {
        $sessionData['active_cita'] = $selectedCita;
        Database::setState($chatId, 'VIEW_APPOINTMENT', $sessionData);
        $msg = "Detalles de la cita seleccionada:\n\n" .
               "• <b>Servicio:</b> {$selectedCita['service']}\n" .
               "• <b>Fecha:</b> {$selectedCita['date']}, {$selectedCita['time']}";
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Reprogramarla', 'callback_data' => 'act_reprogramar']],
                [['text' => 'Cancelarla', 'callback_data' => 'act_cancelar']],
                [['text' => 'Menú principal', 'callback_data' => 'nav_cancelar']]
            ]
        ];
        sendMessage($chatId, $msg, $keyboard);
    }
    exit('OK');
}

// --- INTERCEPCIÓN GLOBAL DE TEXTO LIBRE ---
if (!empty($text)) {
    $cleanInput = cleanText($text);

    // Ubicación
    if (strpos($cleanInput, 'ubic') !== false || strpos($cleanInput, 'direc') !== false || strpos($cleanInput, 'donde') !== false || strpos($cleanInput, 'llegar') !== false) {
        Database::resetState($chatId);
        $msgUbicacion = "<b>Clínica Dental Sonrisa</b>\n\n" .
                        "<b>Dirección:</b> Psj. Los Mirtos #123, Colonia Mega 1, La Libertad.\n" .
                        "<b>Referencia:</b> Frente al parque central, junto a Farmacia La Salud.\n\n" .
                        "<b>Horarios de atención:</b> Lunes a Viernes: 8:00 AM - 5:00 PM";
        sendMessage($chatId, $msgUbicacion, getMainMenuKeyboard());
        exit('OK');
    }

    // Intención de agendar por texto
    if (strpos($cleanInput, 'agendar') !== false || strpos($cleanInput, 'reservar') !== false || (strpos($cleanInput, 'quiero') !== false && strpos($cleanInput, 'cita') !== false)) {
        Database::setState($chatId, 'BOOK_SERVICE');
        $keyboard = [
            'inline_keyboard' => [
                [['text' => 'Limpieza dental', 'callback_data' => 'srv_Limpieza dental']],
                [['text' => 'Extracción', 'callback_data' => 'srv_Extracción']],
                [['text' => 'Ortodoncia', 'callback_data' => 'srv_Ortodoncia']],
                [['text' => 'Revisión general', 'callback_data' => 'srv_Revisión general']],
                [getNavButtons()[0]]
            ]
        ];
        sendMessage($chatId, "¡Con gusto te ayudo a agendar! ¿Qué servicio necesitas?", $keyboard);
        exit('OK');
    }

    // Consulta de fecha/estado de cita por texto
    if (strpos($cleanInput, 'cuando') !== false || strpos($cleanInput, 'consult') !== false || strpos($cleanInput, 'revis') !== false || strpos($cleanInput, 'mi cita') !== false) {
        $cleanPhone = null;
        if (preg_match('/\b[0-9]{4}[- ]?[0-9]{4}\b/', $text, $matches)) {
            $cleanPhone = preg_replace('/[^0-9]/', '', $matches[0]);
        }

        if ($cleanPhone && strlen($cleanPhone) === 8) {
            $formattedPhone = substr($cleanPhone, 0, 4) . '-' . substr($cleanPhone, 4, 4);
            $citas = Database::getAppointmentsByPhone($formattedPhone);

            if (empty($citas)) {
                $keyboard = ['inline_keyboard' => [[['text' => 'Reintentar', 'callback_data' => 'menu_cancelar_repro']], [['text' => 'Hablar con humano', 'callback_data' => 'menu_humano']]]];
                sendMessage($chatId, "No encontré ninguna cita activa asociada al teléfono <b>$formattedPhone</b>.", $keyboard);
            } elseif (count($citas) === 1) {
                $cita = $citas[0];
                $sessionData = ['active_cita' => $cita];
                Database::setState($chatId, 'VIEW_APPOINTMENT', $sessionData);
                $msg = "Encontré tu cita:\n\n" .
                       "• <b>Servicio:</b> {$cita['service']}\n" .
                       "• <b>Fecha:</b> {$cita['date']}, {$cita['time']}\n" .
                       "• <b>Nombre:</b> {$cita['name']}";
                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => 'Reprogramarla', 'callback_data' => 'act_reprogramar']],
                        [['text' => 'Cancelarla', 'callback_data' => 'act_cancelar']],
                        [['text' => 'Menú principal', 'callback_data' => 'nav_cancelar']]
                    ]
                ];
                sendMessage($chatId, $msg, $keyboard);
            } else {
                $buttons = [];
                foreach ($citas as $c) {
                     $buttons[] = [['text' => "{$c['service']} - {$c['date']} {$c['time']}", 'callback_data' => "select_cita_{$c['id']}"]];
                }
                Database::setState($chatId, 'SELECT_MULTIPLE', ['citas' => $citas]);
                sendMessage($chatId, "Encontré varias citas registradas. Selecciona cuál deseas gestionar:", ['inline_keyboard' => $buttons]);
            }
            exit('OK');
        } else {
            Database::setState($chatId, 'SEARCH_PHONE');
            $keyboard = ['inline_keyboard' => [[getNavButtons()[0]]]];
            sendMessage($chatId, "Con gusto consulto tu cita. ¿A qué número de teléfono de 8 dígitos la agendaste?", $keyboard);
            exit('OK');
        }
    }
}

// --- MÁQUINA DE ESTADOS PRINCIPAL ---
switch ($currentState) {

    // --- FLUJO AGENDAR CITA ---
    case 'BOOK_SERVICE':
        if ($callbackData === 'nav_atras') {
            Database::resetState($chatId);
            sendMessage($chatId, "¿Qué necesitas?", getMainMenuKeyboard());
            break;
        }
        if (strpos($callbackData, 'srv_') === 0) {
            $service = str_replace('srv_', '', $callbackData);
            $sessionData['service'] = $service;
            Database::setState($chatId, 'BOOK_DATE', $sessionData);

            $buttons = getNext3DaysButtons('date_');
            $buttons[] = getNavButtons();

            sendMessage($chatId, "Estas son las fechas disponibles en los próximos 3 días para <b>$service</b>:", ['inline_keyboard' => $buttons]);
        } else {
            sendMessage($chatId, "Por favor, selecciona un servicio usando los botones.");
        }
        break;

    case 'BOOK_DATE':
        if ($callbackData === 'nav_atras') {
            Database::setState($chatId, 'BOOK_SERVICE', $sessionData);
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => 'Limpieza dental', 'callback_data' => 'srv_Limpieza dental']],
                    [['text' => 'Extracción', 'callback_data' => 'srv_Extracción']],
                    [['text' => 'Ortodoncia', 'callback_data' => 'srv_Ortodoncia']],
                    [['text' => 'Revisión general', 'callback_data' => 'srv_Revisión general']],
                    [getNavButtons()[0]]
                ]
            ];
            sendMessage($chatId, "¿Qué servicio necesitas?", $keyboard);
            break;
        }
        if (strpos($callbackData, 'date_') === 0) {
            $date = str_replace('date_', '', $callbackData);
            $sessionData['date'] = $date;

            $availableTimes = Database::getAvailableTimes($date);
            if (empty($availableTimes)) {
                sendMessage($chatId, "Lo sentimos, ya no hay horarios disponibles para el $date. Selecciona otra fecha:");
                break;
            }

            Database::setState($chatId, 'BOOK_TIME', $sessionData);
            $timeButtons = [];
            foreach ($availableTimes as $t) {
                $timeButtons[] = [['text' => $t, 'callback_data' => "time_$t"]];
            }
            $timeButtons[] = getNavButtons();

            sendMessage($chatId, "Horarios disponibles para el <b>$date</b>:", ['inline_keyboard' => $timeButtons]);
        }
        break;

    case 'BOOK_TIME':
        if ($callbackData === 'nav_atras') {
            Database::setState($chatId, 'BOOK_DATE', $sessionData);
            $buttons = getNext3DaysButtons('date_');
            $buttons[] = getNavButtons();
            sendMessage($chatId, "Selecciona una fecha:", ['inline_keyboard' => $buttons]);
            break;
        }
        if (strpos($callbackData, 'time_') === 0) {
            $time = str_replace('time_', '', $callbackData);

            if (Database::isSlotTaken($sessionData['date'], $time)) {
                sendMessage($chatId, "⚠️ El horario $time del {$sessionData['date']} acaba de ser reservado por otro usuario. Elige otro horario:");
                break;
            }

            $sessionData['time'] = $time;
            Database::setState($chatId, 'BOOK_NAME', $sessionData);
            $keyboard = ['inline_keyboard' => [[getNavButtons()[0]]]];
            sendMessage($chatId, "Muy bien. ¿Cuál es tu nombre completo?", $keyboard);
        }
        break;

    case 'BOOK_NAME':
        if ($callbackData === 'nav_atras') {
            Database::setState($chatId, 'BOOK_TIME', $sessionData);
            $availableTimes = Database::getAvailableTimes($sessionData['date']);
            $timeButtons = [];
            foreach ($availableTimes as $t) {
                $timeButtons[] = [['text' => $t, 'callback_data' => "time_$t"]];
            }
            $timeButtons[] = getNavButtons();
            sendMessage($chatId, "Selecciona tu horario:", ['inline_keyboard' => $timeButtons]);
            break;
        }
        if (!empty($text)) {
            $sessionData['name'] = $text;
            Database::setState($chatId, 'BOOK_PHONE', $sessionData, 0);
            $keyboard = ['inline_keyboard' => [[getNavButtons()[0]]]];
            sendMessage($chatId, "Gracias, " . htmlspecialchars($text) . ". ¿A qué teléfono te contactamos? (8 dígitos)", $keyboard);
        }
        break;

    case 'BOOK_PHONE':
        if ($callbackData === 'nav_atras') {
            Database::setState($chatId, 'BOOK_NAME', $sessionData);
            sendMessage($chatId, "¿Cuál es tu nombre completo?");
            break;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $text);
        if (strlen($cleanPhone) === 8) {
            $formattedPhone = substr($cleanPhone, 0, 4) . '-' . substr($cleanPhone, 4, 4);
            $sessionData['phone'] = $formattedPhone;
            Database::setState($chatId, 'BOOK_CONFIRM', $sessionData);

            $summary = "<b>Resumen de tu cita:</b>\n\n" .
                       "• <b>Servicio:</b> {$sessionData['service']}\n" .
                       "• <b>Fecha:</b> {$sessionData['date']}, {$sessionData['time']}\n" .
                       "• <b>Nombre:</b> {$sessionData['name']}\n" .
                       "• <b>Teléfono:</b> $formattedPhone";
            sendMessage($chatId, $summary);

            $confirmKeyboard = [
                'inline_keyboard' => [
                    [['text' => 'Sí, confirmar', 'callback_data' => 'confirm_yes']],
                    [['text' => 'No, cancelar', 'callback_data' => 'confirm_no']]
                ]
            ];
            sendMessage($chatId, "¿Deseas confirmar la cita?", $confirmKeyboard);
        } else {
            $newAttempts = $attempts + 1;
            if ($newAttempts >= 3) {
                Database::setState($chatId, 'HUMAN_ESC');
                sendMessage($chatId, "Has superado el límite de intentos. Te transferiremos con un agente de atención.");
            } else {
                Database::setState($chatId, 'BOOK_PHONE', $sessionData, $newAttempts);
                sendMessage($chatId, "El número debe tener 8 dígitos (ej: 7123-4567). Intento $newAttempts de 3. Ingresa tu teléfono:");
            }
        }
        break;

    case 'BOOK_CONFIRM':
        if ($callbackData === 'confirm_yes') {
            // Verificar disponibilidad antes de insertar
            if (Database::isSlotTaken($sessionData['date'], $sessionData['time'])) {
                Database::setState($chatId, 'BOOK_TIME', $sessionData);
                sendMessage($chatId, "⚠️ El horario seleccionado ya no está disponible. Por favor elige otro horario.");
                break;
            }

            $created = Database::createAppointment(
                $sessionData['phone'],
                $sessionData['name'],
                $sessionData['service'],
                $sessionData['date'],
                $sessionData['time']
            );

            if ($created) {
                Database::resetState($chatId);
                sendMessage($chatId, "¡Listo! Tu cita quedó confirmada para el <b>{$sessionData['date']}</b> a las <b>{$sessionData['time']}</b>.", getMainMenuKeyboard());
            } else {
                Database::setState($chatId, 'BOOK_DATE', $sessionData);
                sendMessage($chatId, "Ocurrió un error al guardar tu cita. Selecciona otra fecha.");
            }
        } elseif ($callbackData === 'confirm_no') {
            Database::resetState($chatId);
            sendMessage($chatId, "Proceso de agendación cancelado.", getMainMenuKeyboard());
        }
        break;

    // --- FLUJO CONSULTAR / CANCELAR / REPROGRAMAR ---
    case 'SEARCH_PHONE':
        if ($callbackData === 'nav_atras') {
            Database::resetState($chatId);
            sendMessage($chatId, "¿Qué necesitas?", getMainMenuKeyboard());
            break;
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', $text);
        if (strlen($cleanPhone) === 8) {
            $formattedPhone = substr($cleanPhone, 0, 4) . '-' . substr($cleanPhone, 4, 4);
            $citas = Database::getAppointmentsByPhone($formattedPhone);

            if (empty($citas)) {
                $keyboard = ['inline_keyboard' => [[['text' => 'Reintentar', 'callback_data' => 'menu_cancelar_repro']], [['text' => 'Hablar con humano', 'callback_data' => 'menu_humano']]]];
                sendMessage($chatId, "No encontré ninguna cita activa con el teléfono $formattedPhone.", $keyboard);
            } elseif (count($citas) === 1) {
                $cita = $citas[0];
                $sessionData['active_cita'] = $cita;
                Database::setState($chatId, 'VIEW_APPOINTMENT', $sessionData);

                $msg = "Encontré esta cita activa:\n\n" .
                       "• <b>Servicio:</b> {$cita['service']}\n" .
                       "• <b>Fecha:</b> {$cita['date']}, {$cita['time']}";

                $keyboard = [
                    'inline_keyboard' => [
                        [['text' => 'Reprogramarla', 'callback_data' => 'act_reprogramar']],
                        [['text' => 'Cancelarla', 'callback_data' => 'act_cancelar']],
                        [['text' => 'Menú principal', 'callback_data' => 'nav_cancelar']]
                    ]
                ];
                sendMessage($chatId, $msg, $keyboard);
            } else {
                $buttons = [];
                foreach ($citas as $c) {
                     $buttons[] = [['text' => "{$c['service']} - {$c['date']} {$c['time']}", 'callback_data' => "select_cita_{$c['id']}"]];
                }
                Database::setState($chatId, 'SELECT_MULTIPLE', ['citas' => $citas]);
                sendMessage($chatId, "Encontré varias citas registradas. Selecciona cuál deseas gestionar:", ['inline_keyboard' => $buttons]);
            }
        } else {
            sendMessage($chatId, "Por favor, ingresa un número de teléfono válido de 8 dígitos.");
        }
        break;

    case 'VIEW_APPOINTMENT':
        $cita = $sessionData['active_cita'];
        if ($callbackData === 'act_cancelar') {
            Database::setState($chatId, 'CANCEL_CONFIRM', $sessionData);
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => 'Sí, cancelar cita', 'callback_data' => 'canc_yes']],
                    [['text' => 'No, mantenerla', 'callback_data' => 'canc_no']]
                ]
            ];
            sendMessage($chatId, "¿Seguro que deseas cancelar tu cita del <b>{$cita['date']}</b> a las <b>{$cita['time']}</b>?", $keyboard);
        } elseif ($callbackData === 'act_reprogramar') {
            Database::setState($chatId, 'RESCHEDULE_DATE', $sessionData);
            
            // Generar dinámicamente los próximos 3 días para reprogramar
            $buttons = getNext3DaysButtons('re_date_');
            $buttons[] = [getNavButtons()[0]];

            sendMessage($chatId, "Fechas disponibles en los próximos 3 días para reprogramar tu cita de <b>{$cita['service']}</b>:", ['inline_keyboard' => $buttons]);
        }
        break;

    case 'CANCEL_CONFIRM':
        $cita = $sessionData['active_cita'];
        if ($callbackData === 'canc_yes') {
            Database::cancelAppointment($cita['id']);
            Database::resetState($chatId);
            sendMessage($chatId, "Tu cita del <b>{$cita['date']}</b> a las <b>{$cita['time']}</b> ha sido cancelada exitosamente.", getMainMenuKeyboard());
        } elseif ($callbackData === 'canc_no') {
            Database::setState($chatId, 'VIEW_APPOINTMENT', $sessionData);
            sendMessage($chatId, "Tu cita se mantiene activa. ¿Deseas hacer algo más?");
        }
        break;

    case 'RESCHEDULE_DATE':
        if ($callbackData === 'nav_atras') {
            Database::setState($chatId, 'VIEW_APPOINTMENT', $sessionData);
            $cita = $sessionData['active_cita'];
            $msg = "Detalles de tu cita:\n\n• <b>Servicio:</b> {$cita['service']}\n• <b>Fecha:</b> {$cita['date']}, {$cita['time']}";
            $keyboard = ['inline_keyboard' => [[['text' => 'Reprogramarla', 'callback_data' => 'act_reprogramar']], [['text' => 'Cancelarla', 'callback_data' => 'act_cancelar']], [['text' => 'Menú principal', 'callback_data' => 'nav_cancelar']]]];
            sendMessage($chatId, $msg, $keyboard);
            break;
        }
        if (strpos($callbackData, 're_date_') === 0) {
            $newDate = str_replace('re_date_', '', $callbackData);
            $sessionData['new_date'] = $newDate;

            $availableTimes = Database::getAvailableTimes($newDate);
            if (empty($availableTimes)) {
                sendMessage($chatId, "No hay horarios disponibles para el $newDate. Selecciona otra fecha.");
                break;
            }

            $buttons = [];
            foreach ($availableTimes as $t) {
                $buttons[] = [['text' => $t, 'callback_data' => "re_time_$t"]];
            }
            $buttons[] = [getNavButtons()[0]];

            Database::setState($chatId, 'RESCHEDULE_TIME', $sessionData);
            sendMessage($chatId, "Horarios disponibles para el <b>$newDate</b>:", ['inline_keyboard' => $buttons]);
        }
        break;

    case 'RESCHEDULE_TIME':
        if ($callbackData === 'nav_atras') {
            Database::setState($chatId, 'RESCHEDULE_DATE', $sessionData);
            $buttons = getNext3DaysButtons('re_date_');
            $buttons[] = [getNavButtons()[0]];
            sendMessage($chatId, "Selecciona la fecha para reprogramar:", ['inline_keyboard' => $buttons]);
            break;
        }
        if (strpos($callbackData, 're_time_') === 0) {
            $newTime = str_replace('re_time_', '', $callbackData);

            // Validar que el espacio no se haya reservado mientras tanto
            if (Database::isSlotTaken($sessionData['new_date'], $newTime)) {
                sendMessage($chatId, "⚠️ El horario $newTime del {$sessionData['new_date']} ya fue ocupado. Por favor elige otro horario.");
                break;
            }

            $sessionData['new_time'] = $newTime;
            Database::setState($chatId, 'RESCHEDULE_CONFIRM', $sessionData);

            $cita = $sessionData['active_cita'];
            $msg = "<b>Confirmación de cambio:</b>\n\n" .
                   "• <b>Actual:</b> {$cita['date']}, {$cita['time']}\n" .
                   "• <b>Nueva:</b> {$sessionData['new_date']}, $newTime";

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => 'Sí, reprogramar', 'callback_data' => 're_conf_yes']],
                    [['text' => 'No, mantener original', 'callback_data' => 're_conf_no']]
                ]
            ];
            sendMessage($chatId, $msg, $keyboard);
        }
        break;

    case 'RESCHEDULE_CONFIRM':
        $cita = $sessionData['active_cita'];
        if ($callbackData === 're_conf_yes') {
            // Verificación final de horario libre
            if (Database::isSlotTaken($sessionData['new_date'], $sessionData['new_time'])) {
                Database::setState($chatId, 'RESCHEDULE_DATE', $sessionData);
                sendMessage($chatId, "⚠️ El horario fue tomado hace un instante. Por favor selecciona otra fecha u hora.");
                break;
            }

            $updated = Database::rescheduleAppointment($cita['id'], $sessionData['new_date'], $sessionData['new_time']);
            if ($updated) {
                Database::resetState($chatId);
                sendMessage($chatId, "¡Listo! Tu cita ha sido reprogramada exitosamente para el <b>{$sessionData['new_date']}</b> a las <b>{$sessionData['new_time']}</b>.", getMainMenuKeyboard());
            } else {
                sendMessage($chatId, "Ocurrió un error al actualizar tu cita. Inténtalo nuevamente.");
            }
        } else {
            Database::resetState($chatId);
            sendMessage($chatId, "Se conservó la fecha de la cita original.", getMainMenuKeyboard());
        }
        break;

    case 'HUMAN_ESC':
        break;

    default:
        if (!empty($text)) {
            sendMessage($chatId, "🤖 <i>Consultando...</i>");
            $aiResponse = askOllama($text);
            if ($aiResponse) {
                sendMessage($chatId, $aiResponse, getMainMenuKeyboard());
            } else {
                sendMessage($chatId, "No pude procesar tu mensaje. Elige una opción:", getMainMenuKeyboard());
            }
        } else {
            sendMessage($chatId, "¡Hola! Bienvenido a Clínica Dental Sonrisa. ¿Qué deseas hacer?", getMainMenuKeyboard());
        }
        break;
}
