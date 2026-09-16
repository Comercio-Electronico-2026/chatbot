<?php

function logMessage($direction, $chatId, $content) {
    $logFile = __DIR__ . '/bot.log';
    $date = date('Y-m-d H:i:s');
    $text = is_array($content) ? json_encode($content, JSON_UNESCAPED_UNICODE) : $content;
    $logEntry = "[$date] [$direction] [Chat: $chatId] $text\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}
