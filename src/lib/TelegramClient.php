<?php

declare(strict_types=1);

final class TelegramClient
{
    private string $apiUrl;

    public function __construct(string $token)
    {
        $this->apiUrl = 'https://api.telegram.org/bot' . $token . '/';
    }

    public function call(string $method, array $params = [], ?int $timeout = null): ?array
    {
        $ch = curl_init($this->apiUrl . $method);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($params, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $timeout ?? 15,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        $error = $raw === false ? curl_error($ch) : null;
        curl_close($ch);

        if ($raw === false) {
            Log::error('telegram.' . $method, $error ?: 'fallo de conexion');
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || !($data['ok'] ?? false)) {
            $detail = is_array($data) ? ($data['description'] ?? 'respuesta invalida') : 'respuesta no JSON';
            Log::error('telegram.' . $method, $detail);
            return null;
        }
        return is_array($data['result'] ?? null) ? $data['result'] : null;
    }

    public function sendMessage(int|string $chatId, string $text, ?array $keyboard = null): ?array
    {
        $params = [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ];
        if ($keyboard !== null) {
            $params['reply_markup'] = $keyboard;
        }
        Log::outgoing($chatId, $text);
        $result = $this->call('sendMessage', $params);
        if ($result === null) {
            unset($params['parse_mode']);
            $result = $this->call('sendMessage', $params);
        }
        return $result;
    }

    public function sendMenu(int|string $chatId, string $text): ?array
    {
        return $this->sendMessage($chatId, $text, self::menuKeyboard());
    }

    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        $params = ['callback_query_id' => $callbackQueryId];
        if ($text !== '') {
            $params['text'] = $text;
        }
        $this->call('answerCallbackQuery', $params);
    }

    public static function menuKeyboard(): array
    {
        return [
            'keyboard' => [
                [self::btn('Rastrear pedido'), self::btn('Consultar catálogo')],
                [self::btn('Clima'), self::btn('Hablar con un asesor')],
                [self::btn('Ayuda')],
            ],
            'resize_keyboard' => true,
        ];
    }

    public static function yesNoKeyboard(): array
    {
        return [
            'keyboard' => [[self::btn('Sí'), self::btn('No')]],
            'resize_keyboard' => true,
            'one_time_keyboard' => true,
        ];
    }

    public static function retryKeyboard(): array
    {
        return [
            'keyboard' => [[self::btn('Reintentar'), self::btn('Menú principal')]],
            'resize_keyboard' => true,
        ];
    }

    private static function btn(string $text): array
    {
        return ['text' => $text];
    }
}
