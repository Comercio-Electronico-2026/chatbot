<?php
// Configuración inicial
$env = parse_ini_file('/home/sp21013/chatbot/.env');
$token = $env['BOT_TOKEN'];
$wc_key = $env['WC_KEY'];
$wc_secret = $env['WC_SECRET'];
$apiURL = "https://api.telegram.org/bot$token/";

// Registro de los
function registrarLog($mensaje) {
    file_put_contents('/home/sp21013/chatbot/src/bot.log', date('Y-m-d H:i:s') . " - $mensaje\n", FILE_APPEND);
}

// Se captura el mensaje entrante
$update = json_decode(file_get_contents("php://input"), true);
if (!isset($update["message"])) exit;

$chat_id = $update["message"]["chat"]["id"];
$texto = trim($update["message"]["text"]);
$textoLower = strtolower($texto);
registrarLog("ENTRADA [$chat_id]: $texto");

// Manejo de sesión, esto es para saber en qué parte del flujo está el usuario
$archivoSesion = "/tmp/sesion_bot_$chat_id.json";
$sesion = file_exists($archivoSesion) ? json_decode(file_get_contents($archivoSesion), true) : ['estado' => 'inicio', 'intentos' => 0];

$respuesta = "";

// Interceptor global (comandos, cancelación y ayuda)
if (in_array($textoLower, ['/cancelar', '/ayuda', 'quiero hablar con alguien', 'hola', '/start'])) {
    $sesion['estado'] = 'inicio';
    $sesion['intentos'] = 0;

    if ($textoLower === '/cancelar') {
        $respuesta = "Se ha cancelado la operación y los datos de la sesión han sido borrados. ¿Deseas hacer otra consulta? 😊";
    } elseif ($textoLower === '/ayuda') {
        $respuesta = "📝 Instrucciones: Escribe 'Menú' para ver el catálogo, 'Pedido' para rastrear tu orden, o 'Quiero hablar con alguien' para soporte humano.";
    } elseif ($textoLower === 'quiero hablar con alguien') {
        $respuesta = "Transfiriendo a un agente humano... Por favor, espera un momento. Adiós 👋🏻.";
    } else {
        $respuesta = "¡Hola! 👋🏻 Soy el asistente virtual de Postres SP21013. Puedo mostrarte nuestro menú, consultar tu orden o comunicarte con un agente. Puedes escribir /ayuda o /cancelar en cualquier momento. ¿Qué necesitas?";
    }
}

// Maquina de estados principal
else {
    if ($sesion['estado'] === 'inicio') {
        if (strpos($textoLower, 'menú') !== false || strpos($textoLower, 'menu') !== false) {
            $respuesta = "📋🍰 Aquí tienes nuestro catálogo: \n- Pastel de Chocolate ($25.00)\n- Pastel Tres Leches ($20.00)\n- Pastel de Limón ($20.00)\n- Caja de 6 Cupcakes ($9.00)\n- Porción de Cheesecake de Fresa ($4.50)\n¿Deseas hacer otra consulta?";
        }
        elseif (strpos($textoLower, 'pedido') !== false) {
            // Slot Filling: Buscar si ya dio el número en el mensaje
            preg_match('/\b\d{4}\b/', $texto, $coincidencias);
            if (!empty($coincidencias)) {
                $respuesta = consultarAPI($coincidencias[0]);
            } else {
                $sesion['estado'] = 'esperando_pedido';
                $respuesta = "Claro. ¿Cuál es tu número de pedido de 4 dígitos? 🧐🔍";
            }
        }
        elseif (in_array($textoLower, ['no', 'no gracias', 'no, gracias'])) {
            $respuesta = "¡Gracias por preferir Postres SP21013! Adiós 😊👋🏻.";
        }
        else {
            $respuesta = "Lo siento, no reconocí esa opción ☹️. Usa /ayuda para ver qué puedo hacer.";
        }
    }
    elseif ($sesion['estado'] === 'esperando_pedido') {
        if (preg_match('/^\d{4}$/', $texto)) {
            $respuesta = consultarAPI($texto);
            $sesion['estado'] = 'inicio';
        } else {
            $sesion['intentos']++;
            if ($sesion['intentos'] >= 3) {
                $respuesta = "Límite de intentos superado. Transfiriendo a un agente humano...";
                $sesion['estado'] = 'inicio';
            } else {
                $respuesta = "Error: El formato es incorrecto. Debe tener exactamente 4 dígitos. Intenta de nuevo (Intento {$sesion['intentos']}/3).";
            }
        }
    }
}

// Guardar estado y enviar mensaje
file_put_contents($archivoSesion, json_encode($sesion));
registrarLog("SALIDA [$chat_id]: $respuesta");
file_get_contents($apiURL . "sendMessage?chat_id=$chat_id&text=" . urlencode($respuesta));

// Función para consumir la API de WooCommerce
function consultarAPI($numero) {
    global $wc_key, $wc_secret;

    // Ruta de la API de WooCommerce para consultar un pedido específico
    $url = "https://sp21013.duckdns.org/wp-json/wc/v3/orders/$numero";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    // Autenticacion requerida por WooCommerce
    curl_setopt($ch, CURLOPT_USERPWD, $wc_key . ":" . $wc_secret);

    $resultado = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 200 && $resultado) {
        $datos = json_decode($resultado, true);

        // Extraemos datos del JSON de WooCommerce
        $estado = $datos['status'];
        $total = $datos['total'];

	// Extraemos todos los productos de la orden con sus cantidades
        if (!empty($datos['line_items'])) {
            $lista_productos = [];
            foreach ($datos['line_items'] as $item) {
                $lista_productos[] = $item['quantity'] . "x " . $item['name'];
            }
            $producto = implode(", ", $lista_productos);
        } else {
            $producto = "tu orden";
        }

        // Diccionario para traducir los estados de WooCommerce al español
        $estados_es = [
            'pending' => 'pendiente de pago',
            'processing' => 'en preparación',
            'on-hold' => 'en espera',
            'completed' => 'completado y entregado',
            'cancelled' => 'cancelado'
        ];
        $estado_traducido = $estados_es[$estado] ?? $estado;

        return "¡Genial! 😄 Tu pedido $numero ($producto) se encuentra *$estado_traducido*. Su total es de $$total. ¿Deseas hacer otra consulta? 😊";

    } elseif ($http_code == 404) {
        return "Lo siento ☹️, no encontré ningún pedido con el número $numero en la tienda. Revisa tu correo de confirmación e intenta de nuevo.";
    } else {
        return "Tengo problemas técnicos para conectar con el sistema central (Error $http_code). ¿Deseas reintentar o hablar con un agente?";
    }
}
?>
