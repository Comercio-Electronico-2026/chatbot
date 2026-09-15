<?php
// 1. Cargar entorno
$env = parse_ini_file('/home/pr21064/chatbot/.env');
$token = $env['BOT_TOKEN'];
$wcKey = $env['WC_KEY'];
$wcSecret = $env['WC_SECRET'];
$apiURL = "https://api.telegram.org/bot$token/";
$storeUrl = "https://tiendapr21064.duckdns.org/wp-json/wc/v3";

$update = json_decode(file_get_contents("php://input"), true);
if (!isset($update["message"]["text"])) exit;

$chatId = $update["message"]["chat"]["id"];
$texto = trim($update["message"]["text"]);

// 3. Sistema de Sesiones (Memoria)
$sessionFile = 'sesiones.json';
$sesiones = file_exists($sessionFile) ? json_decode(file_get_contents($sessionFile), true) : [];
$estado = $sesiones[$chatId]['estado'] ?? 'INICIO';
$intentos = $sesiones[$chatId]['intentos'] ?? 0;
$tempData = $sesiones[$chatId]['tempData'] ?? '';

function enviarMensaje($chatId, $texto, $apiURL) {
    file_get_contents($apiURL . "sendMessage?chat_id=$chatId&parse_mode=HTML&text=" . urlencode($texto));
}

function consultarWooCommerce($endpoint, $wcKey, $wcSecret) {
    $url = $endpoint . (strpos($endpoint, '?') !== false ? '&' : '?') . "consumer_key=$wcKey&consumer_secret=$wcSecret";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => $response ? json_decode($response, true) : null];
}

$menuPrincipal = "\n\n🔄 <b>Menú Principal:</b>\n1️⃣ Consultar el catálogo\n2️⃣ Consultar estado de mi pedido\n3️⃣ Métodos de pago";

// 4. Intercepción Global
if (strtolower($texto) === '/cancelar' || strtolower($texto) === '/start') {
    $estado = 'INICIO';
    $sesiones[$chatId] = ['estado' => 'INICIO', 'intentos' => 0];
    file_put_contents($sessionFile, json_encode($sesiones));
    $reply = "¡Hola! 👋 Soy CETBOT, el asistente virtual de CET STORE.\n\nTe ayudo a consultar disponibilidad de productos, estado de tus pedidos e información de pago.\n\nEscribe el número de tu opción (1-3):" . $menuPrincipal;
    enviarMensaje($chatId, $reply, $apiURL);
    exit;
}

if (strtolower($texto) === '/ayuda') {
    $reply = "🆘 <b>Ayuda de CETBOT</b>\nComandos disponibles:\n/start - Menú principal\n/cancelar - Detener acción actual\n\nSi necesitas asistencia humana, escribe a soporte@cetstore.sv";
    enviarMensaje($chatId, $reply, $apiURL);
    exit;
}

// 5. Máquina de Estados
switch ($estado) {
    case 'INICIO':
        if ($texto == '1') {
            $estado = 'ESPERANDO_PRODUCTO';
            $reply = "¡Genial! 🔍 Vamos a buscar en el catálogo.\n\nEscribe el <b>tipo de producto</b>, marca o modelo (ej. 'Teclado', 'Bocina', 'Bluetooth', 'SSD').\n\n¿Qué tienes en mente? (O escribe /cancelar para volver).";
        } elseif ($texto == '2') {
            $estado = 'ESPERANDO_PEDIDO_ID';
            $reply = "Por favor ingresa tu número de ID de pedido (solo los números):";
        } elseif ($texto == '3') {
            $estado = 'ESPERANDO_CIERRE';
            $reply = "💳 <b>Métodos de pago:</b>\nAceptamos transferencias bancarias, tarjetas de crédito/débito y Bitcoin.\n\n¿Puedo ayudarte con algo más? (Responde Sí / No)";
        } elseif (preg_match('/estado.*pedido.* (\d+)/i', $texto, $matches)) {
            $estado = 'ESPERANDO_PEDIDO_CORREO';
            $tempData = $matches[1];
            $reply = "Claro, te ayudaré a consultar tu pedido #$tempData. Por seguridad, por favor escribe el correo electrónico con el que realizaste la compra.";
        } else {
            $reply = "Opción inválida. Por favor, selecciona 1, 2 o 3.";
        }
        break;

    case 'ESPERANDO_PRODUCTO':
        $res = consultarWooCommerce($storeUrl . "/products?search=" . urlencode($texto), $wcKey, $wcSecret);
        if ($res['code'] !== 200) {
            $reply = "⚠️ Problemas técnicos con la tienda. Por favor intenta más tarde." . $menuPrincipal;
            $estado = 'INICIO';
        } elseif (empty($res['data'])) {
            $intentos++;
            if ($intentos >= 3) {
                $reply = "🚫 <b>Límite de intentos alcanzado.</b> Derivando a soporte humano: por favor escribe a soporte@cetstore.sv." . $menuPrincipal;
                $estado = 'INICIO';
            } else {
                $reply = "No logré encontrar ningún producto que coincida con '<b>$texto</b>' 😔.\n\nPrueba con otra palabra (ej. 'Mouse' o 'RAM') (Intento $intentos/3).";
            }
        } else {
            // AHORA RECORREMOS HASTA 3 RESULTADOS EN LUGAR DE SOLO 1
            $reply = "¡Mira lo que encontré para ti! 🎉\n\n";
            $contador = 0;
            
            foreach ($res['data'] as $prod) {
                if ($contador >= 3) break; // Limitamos a 3 para no saturar el chat
                
                $stock = $prod['stock_status'] === 'instock' ? 'Disponible ✅' : 'Agotado ❌';
                $descCorta = isset($prod['short_description']) ? strip_tags($prod['short_description']) : '';
                if (empty(trim($descCorta))) {
                    $descCorta = "Sin descripción breve.";
                }

                $reply .= "📦 <b>" . $prod['name'] . "</b>\n";
                $reply .= "📝 <i>" . trim($descCorta) . "</i>\n";
                $reply .= "💰 $" . $prod['price'] . " | Stock: " . $stock . "\n";
                $reply .= "🔗 <a href='" . $prod['permalink'] . "'>Ver producto</a>\n\n";
                
                $contador++;
            }
            
            $reply .= "¿Puedo ayudarte con algo más? (Responde Sí / No)";
            $estado = 'ESPERANDO_CIERRE';
        }
        break;

    // ... (El resto del código de pedidos y cierre se mantiene exactamente igual)
    case 'ESPERANDO_PEDIDO_ID':
        if (!is_numeric($texto)) {
            $reply = "Formato inválido. Ingresa solo números. O usa /cancelar.";
        } else {
            $tempData = $texto;
            $estado = 'ESPERANDO_PEDIDO_CORREO';
            $reply = "¡Gracias! Ahora, por favor ingresa el correo electrónico con el que realizaste la compra del pedido #$tempData:";
        }
        break;

    case 'ESPERANDO_PEDIDO_CORREO':
        $res = consultarWooCommerce($storeUrl . "/orders/" . $tempData, $wcKey, $wcSecret);
        if ($res['code'] !== 200) {
            $intentos++;
            if ($intentos >= 3) {
                $reply = "🚫 <b>Límite de intentos alcanzado.</b> No pudimos validar tu pedido. Contáctanos a soporte@cetstore.sv." . $menuPrincipal;
                $estado = 'INICIO';
            } else {
                $reply = "Datos no coinciden o pedido no encontrado. Intenta ingresar tu correo nuevamente (Intento $intentos/3):";
            }
        } else {
            $orden = $res['data'];
            if (strtolower(trim($orden['billing']['email'])) === strtolower($texto)) {
                $estadoTraduccion = [
                    'pending' => 'Pendiente de pago', 'processing' => 'Procesando (En tránsito)', 
                    'on-hold' => 'En espera', 'completed' => 'Completado', 'cancelled' => 'Cancelado'
                ];
                $estadoActual = $estadoTraduccion[$orden['status']] ?? $orden['status'];
                $reply = "¡Validación exitosa! ✅\n\nTu pedido <b>#$tempData</b> se encuentra: <b>$estadoActual</b>.\nTotal de compra: $".$orden['total']."\n\n¿Puedo ayudarte con algo más? (Responde Sí / No)";
                $estado = 'ESPERANDO_CIERRE';
            } else {
                $intentos++;
                if ($intentos >= 3) {
                    $reply = "🚫 <b>Límite de intentos alcanzado.</b> Datos incorrectos. Contáctanos a soporte@cetstore.sv." . $menuPrincipal;
                    $estado = 'INICIO';
                } else {
                    $reply = "El correo no coincide con el pedido. Intenta de nuevo (Intento $intentos/3):";
                }
            }
        }
        break;

    case 'ESPERANDO_CIERRE':
        if (strtolower($texto) == 'si' || strtolower($texto) == 'sí') {
            $estado = 'INICIO';
            $reply = "¡Excelente! Por favor, selecciona una opción:" . $menuPrincipal;
        } elseif (strtolower($texto) == 'no') {
            $estado = 'INICIO';
            $reply = "¡Gracias por contactar a CET STORE! Si necesitas algo más más adelante, presiona /start. ¡Que tengas un excelente día! 👋";
        } else {
            $reply = "Por favor, responde 'Sí' o 'No'. (O usa /cancelar para salir).";
        }
        break;
}

$sesiones[$chatId] = ['estado' => $estado, 'intentos' => $estado === 'INICIO' ? 0 : $intentos, 'tempData' => $tempData];
file_put_contents($sessionFile, json_encode($sesiones));
enviarMensaje($chatId, $reply, $apiURL);
?>
