<?php

declare(strict_types=1);

require_once __DIR__ . '/services.php';
require_once __DIR__ . '/state.php';
require_once __DIR__ . '/conversation.php';

umask(0077);
ini_set('display_errors', '0');

function handleUpdate(array $update, ChatStore $store, string $dataDirectory): void
{
    $callback = $update['callback_query'] ?? null;
    $message = $callback['message'] ?? $update['message'] ?? null;
    if (!is_array($message) || !isset($message['chat']['id'], $update['update_id'])) {
        return;
    }
    $chatId = (string) $message['chat']['id'];
    $updateId = (int) $update['update_id'];
    $text = $callback === null ? (string) ($message['text'] ?? '') : '';
    $callbackData = $callback === null ? null : (string) ($callback['data'] ?? '');
    if ($callback !== null && isset($callback['id'])) {
        try {
            telegramRequest('answerCallbackQuery', ['callback_query_id' => $callback['id']]);
        } catch (ServiceFailure $failure) {
            logEvent($dataDirectory . '/logs', 'Error al confirmar botón', ['servicio' => $failure->service, 'http' => $failure->status]);
        }
    }
    $processed = $store->process($chatId, $updateId, static function (array &$state) use ($text, $callbackData, $message, $chatId, $updateId, $dataDirectory): void {
        $catalog = new CatalogApi(setting('STORE_API_URL', 'https://mt23014.duckdns.org/index.php?rest_route=/wc/store/v1'), setting('STORE_RESOLVE_IP', '127.0.0.1'));
        $openAi = new OpenAiApi(setting('OPENAI_API_KEY'), setting('OPENAI_MODEL'));
        $conversation = new Conversation($catalog->products(...), $openAi->answer(...), setting('SUPPORT_URL', 'https://mt23014.duckdns.org/'));
        logEvent($dataDirectory . '/logs', 'Entrada recibida', ['actualizacion' => $updateId,
            'tipo' => $callbackData !== null ? 'boton' : (isset($message['text']) ? 'texto' : 'multimedia'), 'estado' => $state['step'] ?? 'menu']);
        $lastSentAt = $state['_sent_at'] ?? 0;
        $reply = $conversation->handle($text, $callbackData, isset($message['text']) || $callbackData !== null, $state);
        if (isset($state['last_error'])) {
            logEvent($dataDirectory . '/logs', 'Fallo de servicio', $state['last_error']);
            unset($state['last_error']);
        }
        $delay = 1.1 - (microtime(true) - $lastSentAt);
        if ($delay > 0) {
            usleep((int) ($delay * 1000000));
        }
        telegramRequest('sendMessage', ['chat_id' => $chatId, 'text' => mb_substr($reply['text'], 0, 4000), 'reply_markup' => $reply['reply_markup']]);
        $state['_sent_at'] = microtime(true);
        logEvent($dataDirectory . '/logs', 'Salida enviada', ['actualizacion' => $updateId, 'estado' => $state['step'] ?? 'menu', 'caracteres' => mb_strlen($reply['text'])]);
    });
    if (!$processed) {
        logEvent($dataDirectory . '/logs', 'Actualización duplicada omitida', ['actualizacion' => $updateId]);
    }
    $store->cleanup();
}

try {
    loadEnvironment(setting('BOT_ENV_FILE', dirname(__DIR__) . '/.env'));
    $dataDirectory = setting('BOT_DATA_DIR', dirname(__DIR__) . '/var');
    $store = new ChatStore($dataDirectory);

    if (PHP_SAPI === 'cli') {
        $mode = $argv[1] ?? '--help';
        if ($mode === '--check') {
            foreach (['BOT_TOKEN', 'OPENAI_API_KEY', 'OPENAI_MODEL'] as $name) {
                requiredSetting($name);
                echo "{$name}: configurado\n";
            }
            $catalog = new CatalogApi(setting('STORE_API_URL', 'https://mt23014.duckdns.org/index.php?rest_route=/wc/store/v1'), setting('STORE_RESOLVE_IP', '127.0.0.1'));
            echo 'WooCommerce: ' . count($catalog->products()) . " productos\n";
            $bot = telegramRequest('getMe');
            echo 'Telegram: @' . ($bot['username'] ?? 'sin_usuario') . "\n";
            exit(0);
        }
        if ($mode === '--check-ai') {
            $ai = new OpenAiApi(requiredSetting('OPENAI_API_KEY'), requiredSetting('OPENAI_MODEL'));
            $catalog = new CatalogApi(setting('STORE_API_URL', 'https://mt23014.duckdns.org/index.php?rest_route=/wc/store/v1'), setting('STORE_RESOLVE_IP', '127.0.0.1'));
            $answer = $ai->answer('Compara brevemente los productos del catálogo según su categoría, sin inventar especificaciones.', $catalog->products());
            if (trim($answer) === 'FUERA_DE_ALCANCE') {
                throw new RuntimeException('OpenAI no reconoció la consulta de prueba sobre el catálogo.');
            }
            echo "OpenAI: respuesta recibida usando el catálogo público\n";
            exit(0);
        }
        if ($mode === '--register-webhook') {
            $url = requiredSetting('WEBHOOK_URL');
            $secret = requiredSetting('WEBHOOK_SECRET');
            if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secret)) {
                throw new RuntimeException('WEBHOOK_URL o WEBHOOK_SECRET no son válidos.');
            }
            telegramRequest('setWebhook', ['url' => $url, 'secret_token' => $secret, 'allowed_updates' => ['message', 'callback_query']]);
            echo "Webhook registrado.\n";
            exit(0);
        }
        if ($mode === '--webhook-info') {
            $info = telegramRequest('getWebhookInfo');
            echo json_encode(array_intersect_key($info, array_flip(['url', 'pending_update_count', 'last_error_date', 'last_error_message'])), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
            exit(0);
        }
        if ($mode === '--cleanup') {
            $store->cleanup();
            echo "Limpieza completada.\n";
            exit(0);
        }
        if ($mode !== '--poll') {
            echo "Uso: php src/bot.php --poll | --check | --check-ai | --register-webhook | --webhook-info | --cleanup\n";
            exit($mode === '--help' ? 0 : 1);
        }
        $info = telegramRequest('getWebhookInfo');
        if (($info['url'] ?? '') !== '') {
            throw new RuntimeException('Ya hay un webhook registrado. No se iniciará polling.');
        }
        $pollLock = fopen($dataDirectory . '/poll.lock', 'c+');
        if ($pollLock === false || !flock($pollLock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException('Ya existe un proceso de polling activo.');
        }
        $offsetPath = $dataDirectory . '/offset';
        $offset = is_file($offsetPath) ? (int) file_get_contents($offsetPath) : 0;
        echo "Bot en marcha por long polling. Ctrl+C para detener.\n";
        while (true) {
            try {
                $updates = telegramRequest('getUpdates', ['offset' => $offset, 'timeout' => 25, 'allowed_updates' => ['message', 'callback_query']]);
                foreach ($updates as $update) {
                    handleUpdate($update, $store, $dataDirectory);
                    $offset = (int) $update['update_id'] + 1;
                    file_put_contents($offsetPath, (string) $offset, LOCK_EX);
                }
            } catch (ServiceFailure $failure) {
                logEvent($dataDirectory . '/logs', 'Fallo de polling', ['servicio' => $failure->service, 'http' => $failure->status]);
                sleep(3);
            }
        }
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit;
    }
    $secret = requiredSetting('WEBHOOK_SECRET');
    if (!hash_equals($secret, (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? ''))) {
        http_response_code(403);
        exit;
    }
    $raw = file_get_contents('php://input', false, null, 0, 1048577);
    if ($raw === false || strlen($raw) > 1048576) {
        http_response_code(413);
        exit;
    }
    try {
        $update = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        http_response_code(400);
        exit;
    }
    if (!is_array($update) || !isset($update['update_id'])) {
        http_response_code(400);
        exit;
    }
    // PHP-FPM confirma la recepción antes de esperar la respuesta de OpenAI.
    http_response_code(200);
    header('Content-Type: application/json');
    echo '{"ok":true}';
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    handleUpdate($update, $store, $dataDirectory);
} catch (Throwable $failure) {
    $message = $failure instanceof RuntimeException ? $failure->getMessage() : 'No fue posible iniciar o procesar el bot. Revisa configuración y permisos.';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
    error_log($message);
    if (!headers_sent()) {
        http_response_code(500);
    }
}
