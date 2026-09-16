<?php
$envFile = dirname(__DIR__) . '/.env';
if (!file_exists($envFile)) {
    die("Error: No se encontró el archivo .env\n");
}
$env = parse_ini_file($envFile);
$botToken = $env['BOT_TOKEN'] ?? null;

if (!$botToken) {
    die("Error: BOT_TOKEN no definido en .env\n");
}

$apiUrl       = "https://api.telegram.org/bot{$botToken}/";
$storeApiBase = "https://tiendahb21009.duckdns.org/wp-json/wc/store/v1/products";
$soporteEmail = "soporte@tiendahb21009.duckdns.org";

$userState = [];

echo "🤖 Michu Bot iniciado con Long Polling. Esperando mensajes...\n";
$lastUpdateId = 0;

while (true) {
    $url = $apiUrl . "getUpdates?offset=" . ($lastUpdateId + 1) . "&timeout=30";
    $response = @file_get_contents($url);

    if ($response === false) {
        sleep(2);
        continue;
    }

    $data = json_decode($response, true);
    if (empty($data['result'])) {
        continue;
    }

    foreach ($data['result'] as $update) {
        $lastUpdateId = $update['update_id'];

        if (!isset($update['message']['text'])) {
            continue;
        }

        $chatId = $update['message']['chat']['id'];
        $text   = trim($update['message']['text']);
        $sender = $update['message']['from']['first_name'] ?? 'Amigo';

        echo "Mensaje de {$sender} [{$chatId}]: {$text}\n";

        if (!isset($userState[$chatId])) {
            $userState[$chatId] = [
                'step'            => 'IDLE',
                'search_attempts' => 0,
                'id_attempts'     => 0,
                'last_products'   => []
            ];
        }

        // 1. INTERCEPCIÓN GLOBAL (/cancelar y /ayuda)
        if ($text === '/cancelar') {
            $userState[$chatId] = [
                'step'            => 'IDLE',
                'search_attempts' => 0,
                'id_attempts'     => 0,
                'last_products'   => []
            ];
            enviarMensaje($apiUrl, $chatId, "Operación cancelada. Regresando al menú principal. 🐾\n\nEscribe /start para ver las opciones.");
            continue;
        }

        if ($text === '/ayuda') {
            $msgAyuda = "❓ *Centro de Ayuda - Michu Bot*\n\n"
                      . "Comandos disponibles:\n"
                      . "• /catalogo - Consultar accesorios, alimentos y juguetes.\n"
                      . "• /envios - Zonas de entrega y tarifas.\n"
                      . "• /cancelar - Cancelar cualquier consulta en curso.\n"
                      . "• /start - Menú principal.\n\n"
                      . "Contacto de soporte: " . $soporteEmail;
            enviarMensaje($apiUrl, $chatId, $msgAyuda);
            continue;
        }

        // 2. COMANDOS ESTÁTICOS Y MENÚ
        if (str_starts_with($text, '/start')) {
            $userState[$chatId]['step'] = 'IDLE';
            $msgStart = "¡Hola, {$sender}! 🐱 Soy *Michu*, tu asistente virtual de *Tienda Michuno Gatuno*.\n\n"
                      . "Te ayudo a consultar accesorios, alimentos y juguetes para tu mascota (no gestionamos consultas veterinarias).\n\n"
                      . "📦 Escribe /catalogo para consultar opciones.\n"
                      . "🚚 Escribe /envios para zonas de entrega.\n"
                      . "❓ Escribe /ayuda para más información.";
            enviarMensaje($apiUrl, $chatId, $msgStart);
            continue;
        }

        if ($text === '/envios') {
            $msgEnvios = "🚚 *Cobertura y Envíos - Tienda Michuno Gatuno*\n\n"
                       . "• Entregas en San Salvador y La Libertad: 24 a 48 horas.\n"
                       . "• Envíos departamentales: 48 a 72 horas hábiles vía encomienda.\n"
                       . "• Envío gratis en compras mayores a $25.00 USD.\n\n"
                       . "Escribe /catalogo para explorar la tienda o /start para volver al menú.";
            enviarMensaje($apiUrl, $chatId, $msgEnvios);
            continue;
        }

        if ($text === '/catalogo') {
            $userState[$chatId]['step'] = 'AWAITING_SEARCH';
            $userState[$chatId]['search_attempts'] = 0;
            $msgCat = "📦 *Catálogo de productos:*\n\n"
                    . "¿Qué artículo o categoría deseas consultar? (Ejemplos: *Torre*, *Pelotas*, *Cepillo*, *Plato*, o escribe *todos*).\n\n"
                    . "Puedes escribir una palabra clave o /cancelar para volver.";
            enviarMensaje($apiUrl, $chatId, $msgCat);
            continue;
        }

        // 3. MÁQUINA DE ESTADOS (FLUJO CATÁLOGO)
        $step = $userState[$chatId]['step'];

        // Paso A: Búsqueda
        if ($step === 'AWAITING_SEARCH') {
            $isGeneral = in_array(mb_strtolower($text), ['todos', 'todo', 'catalogo', 'juguetes', 'juguete', 'accesorios']);
            $endpoint = $isGeneral ? $storeApiBase . "?per_page=10" : $storeApiBase . "?search=" . urlencode($text);
            
            $resApi = consultarApiTienda($endpoint);

            if ($resApi['status'] !== 200) {
                enviarMensaje($apiUrl, $chatId, "⚠️ El catálogo se encuentra en mantenimiento temporal. Intenta más tarde o consulta /envios.");
                $userState[$chatId]['step'] = 'IDLE';
                continue;
            }

            $productos = $resApi['body'];

            // Filtro de contingencia por contenido
            if (empty($productos) && !$isGeneral) {
                $resAll = consultarApiTienda($storeApiBase . "?per_page=20");
                if (!empty($resAll['body'])) {
                    $term = mb_strtolower($text);
                    $productos = array_filter($resAll['body'], function($item) use ($term) {
                        $nombre = mb_strtolower($item['name'] ?? '');
                        $desc = mb_strtolower($item['description'] ?? '');
                        $cats = implode(' ', array_column($item['categories'] ?? [], 'name'));
                        return str_contains($nombre, $term) || str_contains($desc, $term) || str_contains(mb_strtolower($cats), $term);
                    });
                }
            }

            if (empty($productos)) {
                $userState[$chatId]['search_attempts']++;
                if ($userState[$chatId]['search_attempts'] < 3) {
                    enviarMensaje($apiUrl, $chatId, "🔍 No encontramos productos coincidentes con \"{$text}\". Intenta con palabras como *Torre*, *Cepillo*, *Plato* o *todos*, o escribe /cancelar.");
                } else {
                    enviarMensaje($apiUrl, $chatId, "Has superado el límite de intentos de búsqueda. 😿\n\nPuedes escribir a atención humana: {$soporteEmail}\n\nEscribe /start para reiniciar.");
                    $userState[$chatId]['step'] = 'IDLE';
                }
                continue;
            }

            $listaTexto = "🔎 *Encontré estas opciones disponibles:*\n\n";
            $productosGuardados = [];

            foreach ($productos as $p) {
                $precio = number_format(((float)$p['prices']['price']) / 100, 2);
                $listaTexto .= "• *{$p['name']}* — \${$precio} USD (ID: `{$p['id']}`)\n";
                $productosGuardados[$p['id']] = $p;
            }

            $listaTexto .= "\n¿Deseas ver detalles de alguno? Ingresa el ID numérico (ej. `" . array_key_first($productosGuardados) . "`), o escribe /cancelar.";

            $userState[$chatId]['last_products'] = $productosGuardados;
            $userState[$chatId]['step'] = 'AWAITING_ID';
            $userState[$chatId]['id_attempts'] = 0;

            enviarMensaje($apiUrl, $chatId, $listaTexto);
            continue;
        }

        // Paso B: Detalle por ID
        if ($step === 'AWAITING_ID') {
            if (!ctype_digit($text)) {
                $userState[$chatId]['id_attempts']++;
                evaluarErrorId($apiUrl, $chatId, $userState, $soporteEmail);
                continue;
            }

            $prodId = (int)$text;
            if (!isset($userState[$chatId]['last_products'][$prodId])) {
                $userState[$chatId]['id_attempts']++;
                evaluarErrorId($apiUrl, $chatId, $userState, $soporteEmail);
                continue;
            }

            $producto = $userState[$chatId]['last_products'][$prodId];
            $precio = number_format(((float)$producto['prices']['price']) / 100, 2);
            $stock  = $producto['stock_availability']['text'] ?? 'Disponible';
            $desc   = trim(strip_tags($producto['description'] ?: $producto['short_description']));

            $ficha = "🐱 *{$producto['name']} (ID #{$producto['id']})*\n\n"
                   . "• *Descripción:* {$desc}\n"
                   . "• *Precio:* \${$precio} USD\n"
                   . "• *Disponibilidad:* {$stock}\n"
                   . "• *Comprar en tienda:* {$producto['permalink']}\n\n"
                   . "¿Deseas consultar otro producto? Responde *Sí* o *No*.";

            $userState[$chatId]['step'] = 'AWAITING_CONTINUE';
            enviarMensaje($apiUrl, $chatId, $ficha);
            continue;
        }

        // Paso C: Continuación Sí/No
        if ($step === 'AWAITING_CONTINUE') {
            $resp = mb_strtolower($text);
            if (in_array($resp, ['si', 'sí', 's'])) {
                $userState[$chatId]['step'] = 'AWAITING_SEARCH';
                $userState[$chatId]['search_attempts'] = 0;
                enviarMensaje($apiUrl, $chatId, "📦 ¿Qué otro producto o categoría deseas buscar?");
            } else {
                $userState[$chatId]['step'] = 'IDLE';
                enviarMensaje($apiUrl, $chatId, "¡De acuerdo! Escribe /catalogo cuando quieras consultar nuevamente o /start para el menú principal. ¡Que tengas un excelente día! 🐾");
            }
            continue;
        }

        // 4. FALLBACK GENERAL DE REGLAS
        enviarMensaje($apiUrl, $chatId, "🤖 No entendí esa opción. Escribe /catalogo para ver productos o /ayuda.");
    }
}

function enviarMensaje($apiUrl, $chatId, $text) {
    $postData = [
        'chat_id'    => $chatId,
        'text'       => $text,
        'parse_mode' => 'Markdown'
    ];
    $opts = [
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => http_build_query($postData),
            'timeout' => 10
        ]
    ];
    @file_get_contents($apiUrl . "sendMessage", false, stream_context_create($opts));
}

function consultarApiTienda($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'status' => $httpCode,
        'body'   => json_decode($response, true) ?: []
    ];
}

function evaluarErrorId($apiUrl, $chatId, &$userState, $soporteEmail) {
    if ($userState[$chatId]['id_attempts'] < 3) {
        enviarMensaje($apiUrl, $chatId, "⚠️ Código no reconocido. Por favor ingresa el número de ID que aparece entre las opciones mostradas o escribe /cancelar.");
    } else {
        enviarMensaje($apiUrl, $chatId, "No pudimos validar el ID seleccionado tras múltiples intentos. 😿\n\nPonte en contacto con nuestro equipo: {$soporteEmail}\n\nEscribe /start para reiniciar.");
        $userState[$chatId]['step'] = 'IDLE';
    }
}