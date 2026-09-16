<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$domain = (string) (Env::get('WEBHOOK_DOMAIN') ?? '');
$domain = rtrim(str_replace(['https://', 'http://'], ['', ''], $domain), '/');
if ($domain === '') {
    fwrite(STDERR, "Error: define WEBHOOK_DOMAIN en .env (ejemplo: https://bh23004.duckdns.org)\n");
    exit(1);
}

$url = 'https://' . $domain . '/src/index.php';
$params = [
    'url' => $url,
    'drop_pending_updates' => true,
    'allowed_updates' => ['message', 'callback_query'],
];
$secret = Env::get('WEBHOOK_SECRET');
if ($secret !== null && $secret !== '') {
    $params['secret_token'] = $secret;
}

$result = App::telegram()->call('setWebhook', $params);
echo $result === null ? "Fallo al registrar el webhook.\n" : "Webhook registrado en {$url}\n";

$info = App::telegram()->call('getWebhookInfo');
if (is_array($info)) {
    echo "Estado del webhook:\n";
    print_r($info);
}

echo "\nNota: el long polling (poll.php) y el webhook no pueden trabajar a la vez.\n";
echo "Para volver a modo long polling ejecuta: deleteWebhook\n";
