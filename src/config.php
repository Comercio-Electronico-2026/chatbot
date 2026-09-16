<?php

function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value, " \t\n\r\0\x0B\"'");
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
    }
}

loadEnv(__DIR__ . '/../.env');

$botToken = getenv('BOT_TOKEN');
if (!$botToken) {
    error_log("ERROR: BOT_TOKEN no está configurado en .env");
    exit("BOT_TOKEN missing");
}

define('TELEGRAM_TOKEN', $botToken);
define('TELEGRAM_API', "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/");
