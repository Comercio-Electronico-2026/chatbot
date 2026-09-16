<?php

declare(strict_types=1);

final class LLMClient
{
    private string $baseUrl;
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct(string $baseUrl, string $apiKey, string $model, int $timeout)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = $timeout;
    }

    public static function fromEnv(): self
    {
        return new self(
            Env::get('LLM_BASE_URL', 'https://api.openai.com/v1'),
            (string) (Env::get('LLM_API_KEY') ?? ''),
            Env::get('LLM_MODEL', 'gpt-4o-mini'),
            (int) (Env::get('LLM_TIMEOUT', '20') ?? 20)
        );
    }

    public function chat(string $userText, string $systemPrompt): ?string
    {
        if ($this->apiKey === '') {
            Log::error('llm', 'LLM_API_KEY no configurada en .env');
            return null;
        }
        $payload = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userText],
            ],
            'temperature' => 0.4,
            'max_tokens' => 300,
            'stream' => false,
        ];
        $ch = curl_init($this->baseUrl . '/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        $error = $raw === false ? curl_error($ch) : null;
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            Log::error('llm', $error ?: 'fallo de conexion');
            return null;
        }
        if ($status >= 400) {
            Log::error('llm', 'HTTP ' . $status . ' ' . substr((string) $raw, 0, 300));
            return null;
        }
        $data = json_decode($raw, true);
        $content = $data['choices'][0]['message']['content'] ?? null;
        if (!is_string($content) || trim($content) === '') {
            Log::error('llm', 'respuesta sin contenido');
            return null;
        }
        return trim($content);
    }
}
