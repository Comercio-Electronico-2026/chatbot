<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$raw = (string) file_get_contents('php://input');

$secret = Env::get('WEBHOOK_SECRET');
if ($secret !== null && ($secret !== '' && ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '') !== $secret)) {
    http_response_code(403);
    exit;
}

$update = json_decode($raw, true);
http_response_code(200);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

if (!is_array($update)) {
    exit;
}

$router = App::router();
try {
    if (isset($update['callback_query']) && is_array($update['callback_query'])) {
        $router->handleCallback($update['callback_query']);
    } else {
        $router->handle($update);
    }
} catch (Throwable $e) {
    Log::error('webhook', 'excepción no controlada: ' . $e->getMessage());
}
