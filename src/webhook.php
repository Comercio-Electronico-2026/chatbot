<?php

declare(strict_types=1);

require_once __DIR__ . '/bot.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$config = loadConfig(__DIR__);
$providedSecret = (string) ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if ($config['webhook_secret'] === '' || !hash_equals($config['webhook_secret'], $providedSecret)) {
    http_response_code(403);
    exit;
}

$rawUpdate = file_get_contents('php://input');
$update = json_decode((string) $rawUpdate, true);
if (!is_array($update)) {
    http_response_code(400);
    exit;
}

$state = loadState();
processTelegramUpdate($update, $config, $state);

http_response_code(200);
header('Content-Type: application/json');
echo '{"ok":true}';
