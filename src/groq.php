<?php

declare(strict_types=1);


// Detecta si el mensaje parece ser una consulta relacionada con pedidos.
function looksLikeOrderIntent(string $text): bool
{
    return preg_match(
        '/\b(pedido|orden|estado de mi compra|estado de la compra)\b/ui',
        $text
    ) === 1;
}


// Detecta si el usuario solicita atención humana.
function looksLikeHumanSupportIntent(string $text): bool
{
    return preg_match(
        '/\b(soporte|humano|persona|asesor|atenci[oó]n humana)\b/ui',
        $text
    ) === 1;
}


// Se revisa si el mensaje contiene posibles datos personales.
// Estos mensajes no se envían a Groq.
function containsPotentialPersonalData(string $text): bool
{
    $patterns = [
        '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu',
        '/\b\d{4}[-\s]?\d{4}\b/u',
        '/\b\d{8}-\d\b/u',
        '/\b\d{10,19}\b/u',
    ];

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $text) === 1) {
            return true;
        }
    }

    return false;
}


// Convierte el formato básico generado por Groq
// al formato HTML que puede interpretar Telegram.
function formatGroqForTelegram(
    string $text
): string {
    $text = htmlspecialchars(
        $text,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );

    return preg_replace(
        '/\*\*(.+?)\*\*/s',
        '<b>$1</b>',
        $text
    ) ?? $text;
}


// Envía una consulta abierta a la API de Groq.
function askGroq(
    string $message,
    string $apiKey,
    string $model
): array {
    if ($apiKey === '') {
        error_log(
            'GROQ_API_KEY no está configurada'
        );

        return [
            'status' => 'error',
            'text' => '',
        ];
    }

    // Se define el comportamiento que debe seguir la IA.
    $systemPrompt =
        "Eres MusicHub Bot, asistente conversacional de una tienda " .
        "de música llamada MusicHub. " .
        "Responde siempre en español, de forma breve, amable y clara. " .
        "Puedes responder preguntas generales sobre música, vinilos, " .
        "CD, formatos musicales y cuidado de discos. " .
        "No inventes precios, existencias, información de pedidos " .
        "ni datos específicos de la tienda. " .
        "Si el usuario quiere consultar productos, indícale que use " .
        "/catalogo. " .
        "Si quiere consultar un pedido, indícale que use /pedido. " .
        "Si necesita atención humana, indícale que use /soporte. " .
        "No afirmes que realizaste compras, pagos, cancelaciones " .
        "ni otras acciones. " .
        "No uses tablas Markdown ni caracteres | para organizar información. " .
        "Si necesitas comparar elementos, utiliza listas con viñetas. " .
        "Puedes usar **texto** para resaltar palabras importantes.";

    $payload = [
        'model' => $model,
        'messages' => [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
            [
                'role' => 'user',
                'content' => $message,
            ],
        ],
        'reasoning_effort' => 'low',
        'max_completion_tokens' => 300,
    ];

    $ch = curl_init(
        'https://api.groq.com/openai/v1/chat/completions'
    );

    curl_setopt_array(
        $ch,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
            ),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]
    );

    $response = curl_exec($ch);

    // Si no se obtiene respuesta, se informa el error
    // y el bot puede continuar usando sus funciones normales.
    if ($response === false) {
        error_log(
            'Error de conexión con Groq: ' .
            curl_error($ch)
        );

        curl_close($ch);

        return [
            'status' => 'error',
            'text' => '',
        ];
    }

    $httpCode = curl_getinfo(
        $ch,
        CURLINFO_HTTP_CODE
    );

    curl_close($ch);

    if (
        $httpCode < 200 ||
        $httpCode >= 300
    ) {
        error_log(
            "Groq respondió con HTTP {$httpCode}"
        );

        return [
            'status' => 'error',
            'text' => '',
        ];
    }

    $data = json_decode(
        $response,
        true
    );

    $content = trim(
        $data['choices'][0]['message']['content']
        ?? ''
    );

    if ($content === '') {
        error_log(
            'Groq no devolvió contenido'
        );

        return [
            'status' => 'error',
            'text' => '',
        ];
    }

    return [
        'status' => 'success',
        'text' => $content,
    ];
}
