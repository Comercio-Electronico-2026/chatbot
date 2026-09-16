<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$telegram = App::telegram();
$router = App::router();
$offset = 0;

echo "Bot escuchando vía getUpdates (long polling). Ctrl+C para salir.\n";

while (true) {
    $updates = $telegram->call('getUpdates', [
        'offset' => $offset,
        'timeout' => 25,
        'allowed_updates' => ['message', 'callback_query'],
    ], 35);
    if ($updates === null) {
        sleep(2);
        continue;
    }
    foreach ($updates as $update) {
        $offset = (int) $update['update_id'] + 1;
        try {
            if (isset($update['callback_query']) && is_array($update['callback_query'])) {
                $router->handleCallback($update['callback_query']);
            } else {
                $router->handle($update);
            }
        } catch (Throwable $e) {
            Log::error('poll', 'excepción no controlada: ' . $e->getMessage());
        }
    }
}
