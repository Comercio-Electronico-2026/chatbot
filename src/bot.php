<?php

declare(strict_types=1);

date_default_timezone_set('America/El_Salvador');

/*
|--------------------------------------------------------------------------
| Cargar variables desde .env
|--------------------------------------------------------------------------
*/

function cargarEnv(string $ruta): void
{
    if (!file_exists($ruta)) {
        fwrite(STDERR, "Error: no existe el archivo .env\n");
        exit(1);
    }

    $lineas = file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lineas === false) {
        fwrite(STDERR, "Error: no se pudo leer el archivo .env\n");
        exit(1);
    }

    foreach ($lineas as $linea) {
        $linea = trim($linea);

        if ($linea === '' || str_starts_with($linea, '#')) {
            continue;
        }

        if (str_starts_with($linea, 'export ')) {
            $linea = trim(substr($linea, 7));
        }

        $partes = explode('=', $linea, 2);

        if (count($partes) !== 2) {
            continue;
        }

        $clave = trim($partes[0]);
        $valor = trim($partes[1]);

        if (
            strlen($valor) >= 2 &&
            (($valor[0] === '"' && str_ends_with($valor, '"')) ||
             ($valor[0] === "'" && str_ends_with($valor, "'")))
        ) {
            $valor = substr($valor, 1, -1);
        }

        if ($clave !== '') {
            putenv("{$clave}={$valor}");
        }
    }
}

cargarEnv(dirname(__DIR__) . '/.env');

/*
|--------------------------------------------------------------------------
| Configuración
|--------------------------------------------------------------------------
*/

$token = trim((string) getenv('BOT_TOKEN'));
$wcUrl = rtrim(trim((string) getenv('WC_URL')), '/');
$wcConsumerKey = trim((string) getenv('WC_CONSUMER_KEY'));
$wcConsumerSecret = trim((string) getenv('WC_CONSUMER_SECRET'));
$openaiApiKey = trim((string) getenv('OPENAI_API_KEY'));
$openaiModel = trim((string) (getenv('OPENAI_MODEL') ?: 'gpt-5.6-luna'));
$humanContact = trim((string) (getenv('HUMAN_CONTACT') ?: 'WhatsApp: XXXXXXXX'));

if ($token === '') {
    fwrite(STDERR, "Error: BOT_TOKEN no está definido.\n");
    exit(1);
}

if ($wcUrl === '' || $wcConsumerKey === '' || $wcConsumerSecret === '') {
    fwrite(STDERR, "Error: faltan variables de WooCommerce en .env\n");
    exit(1);
}

if ($openaiApiKey === '') {
    fwrite(STDERR, "Error: OPENAI_API_KEY no está definido en .env\n");
    exit(1);
}

$telegramApi = "https://api.telegram.org/bot{$token}";
$offset = 0;

/*
|--------------------------------------------------------------------------
| Estados de conversación
|--------------------------------------------------------------------------
| Se mantienen en memoria mientras bot.php está ejecutándose.
*/

$estados = [];

/*
|--------------------------------------------------------------------------
| Utilidades de texto
|--------------------------------------------------------------------------
*/

function normalizarTexto(string $texto): string
{
    $texto = trim($texto);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($texto, 'UTF-8');
    }

    return strtolower($texto);
}

function textoComparacion(string $texto): string
{
    $texto = normalizarTexto($texto);

    return strtr($texto, [
        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u',
        'ñ' => 'n',
    ]);
}

function contieneAlguna(string $texto, array $frases): bool
{
    foreach ($frases as $frase) {
        if (str_contains($texto, $frase)) {
            return true;
        }
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| Logs
|--------------------------------------------------------------------------
*/

function registrarLog(string $mensaje): void
{
    $directorio = dirname(__DIR__) . '/logs';

    if (!is_dir($directorio)) {
        mkdir($directorio, 0775, true);
    }

    $fecha = date('Y-m-d H:i:s');

    file_put_contents(
        $directorio . '/bot.log',
        "[{$fecha}] {$mensaje}" . PHP_EOL,
        FILE_APPEND
    );
}

/*
|--------------------------------------------------------------------------
| Teclados de Telegram
|--------------------------------------------------------------------------
*/

function tecladoPrincipal(): array
{
    return [
        'keyboard' => [
            [['text' => '🔎 Buscar producto']],
            [['text' => '❓ Ayuda'], ['text' => '👤 Atención humana']],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => false,
    ];
}

function tecladoSiNo(): array
{
    return [
        'keyboard' => [
            [['text' => 'Sí'], ['text' => 'No']],
            [['text' => '/cancelar']],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ];
}

function tecladoDerivacion(): array
{
    return [
        'keyboard' => [
            [['text' => '🏠 Volver al inicio']],
            [['text' => '👤 Atención humana']],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ];
}

function tecladoSeleccion(int $cantidad): array
{
    $fila = [];

    for ($i = 1; $i <= $cantidad; $i++) {
        $fila[] = ['text' => (string) $i];
    }

    return [
        'keyboard' => [
            $fila,
            [['text' => '/cancelar']],
        ],
        'resize_keyboard' => true,
        'one_time_keyboard' => true,
    ];
}

function tecladoParaEstado(?array $estado): array
{
    $tipo = $estado['estado'] ?? null;

    if ($tipo === 'seleccionando_producto') {
        return tecladoSeleccion(count($estado['productos'] ?? []));
    }

    if ($tipo === 'buscar_otro' || $tipo === 'reintentar_catalogo') {
        return tecladoSiNo();
    }

    if ($tipo === 'derivacion') {
        return tecladoDerivacion();
    }

    if ($tipo === 'esperando_producto') {
        return [
            'keyboard' => [[['text' => '/cancelar']]],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    return tecladoPrincipal();
}

/*
|--------------------------------------------------------------------------
| Telegram
|--------------------------------------------------------------------------
*/

function enviarMensaje(
    string $api,
    int|string $chatId,
    string $mensaje,
    ?array $replyMarkup = null
): void {
    registrarLog(
        "SALIDA chat={$chatId} mensaje=" . str_replace("\n", ' ', $mensaje)
    );

    $campos = [
        'chat_id' => $chatId,
        'text' => $mensaje,
        'disable_web_page_preview' => 'true',
    ];

    if ($replyMarkup !== null) {
        $campos['reply_markup'] = json_encode(
            $replyMarkup,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    $curl = curl_init("{$api}/sendMessage");

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $campos,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $respuesta = curl_exec($curl);

    if ($respuesta === false) {
        registrarLog('ERROR TELEGRAM sendMessage ' . curl_error($curl));
        fwrite(STDERR, 'Error enviando mensaje: ' . curl_error($curl) . PHP_EOL);
    }

    curl_close($curl);
}

function mostrarInicio(string $api, int|string $chatId): void
{
    $mensaje =
        "¡Hola! Soy FerreBot 🔧\n\n"
        . "Puedo ayudarte a:\n"
        . "• Buscar productos de la ferretería.\n"
        . "• Consultar precios reales de WooCommerce.\n"
        . "• Responder preguntas básicas de herramientas.\n\n"
        . "Ejemplos:\n"
        . "• /buscar taladro\n"
        . "• ¿Cuánto cuesta la pintura?\n"
        . "• ¿Para qué sirve una llave Allen?\n\n"
        . "También puedes usar /ayuda o /cancelar.";

    enviarMensaje($api, $chatId, $mensaje, tecladoPrincipal());
}

function mostrarAyuda(
    string $api,
    int|string $chatId,
    ?array $replyMarkup = null
): void {
    $mensaje =
        "Puedo ayudarte a:\n\n"
        . "🔎 Buscar productos:\n"
        . "/buscar taladro\n"
        . "o simplemente escribe: taladro\n\n"
        . "💲 Consultar precios:\n"
        . "¿Cuánto cuesta la pintura?\n\n"
        . "🔧 Preguntas de ferretería:\n"
        . "¿Para qué sirve una llave Allen?\n\n"
        . "❌ Cancelar una operación:\n"
        . "/cancelar\n\n"
        . "🏠 Volver al inicio:\n"
        . "/start";

    enviarMensaje(
        $api,
        $chatId,
        $mensaje,
        $replyMarkup ?? tecladoPrincipal()
    );
}

function mostrarContactoHumano(
    string $api,
    int|string $chatId,
    string $humanContact
): void {
    enviarMensaje(
        $api,
        $chatId,
        "Para continuar con una persona, comunícate con:\n{$humanContact}",
        tecladoPrincipal()
    );
}

/*
|--------------------------------------------------------------------------
| WooCommerce
|--------------------------------------------------------------------------
*/

function buscarProductos(
    string $wcUrl,
    string $consumerKey,
    string $consumerSecret,
    string $termino
): array {
    // Este proyecto usa rest_route porque en el servidor actual /wp-json/
    // puede ser interceptado por la configuración de WordPress/Apache.
    $query = http_build_query([
        'rest_route' => '/wc/v3/products',
        'search' => $termino,
        'status' => 'publish',
        'per_page' => 5,
    ]);

    $url = "{$wcUrl}/index.php?{$query}";
    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => "{$consumerKey}:{$consumerSecret}",
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $respuesta = curl_exec($curl);

    if ($respuesta === false) {
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'ok' => false,
            'productos' => [],
            'error' => $error,
        ];
    }

    $codigoHttp = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($codigoHttp < 200 || $codigoHttp >= 300) {
        return [
            'ok' => false,
            'productos' => [],
            'error' => "HTTP {$codigoHttp}",
        ];
    }

    $productos = json_decode($respuesta, true);

    if (!is_array($productos)) {
        return [
            'ok' => false,
            'productos' => [],
            'error' => 'Respuesta JSON inválida',
        ];
    }

    return [
        'ok' => true,
        'productos' => $productos,
        'error' => null,
    ];
}

function obtenerPrecio(array $producto): string
{
    $precio = trim((string) ($producto['price'] ?? ''));

    if ($precio === '') {
        return 'Precio no disponible';
    }

    return '$' . $precio;
}

function formatearProducto(array $producto): string
{
    $nombre = trim((string) ($producto['name'] ?? 'Producto'));
    $precio = obtenerPrecio($producto);

    $mensaje = "🔧 {$nombre}\nPrecio: {$precio}";

    $permalink = trim((string) ($producto['permalink'] ?? ''));

    if ($permalink !== '') {
        $mensaje .= "\n\nVer producto:\n{$permalink}";
    }

    return $mensaje;
}

function procesarBusqueda(
    string $api,
    int|string $chatId,
    string $termino,
    string $wcUrl,
    string $wcConsumerKey,
    string $wcConsumerSecret,
    array &$estados
): void {
    $termino = trim($termino);

    if ($termino === '') {
        $estados[$chatId] = [
            'estado' => 'esperando_producto',
            'intentos' => 0,
        ];

        enviarMensaje(
            $api,
            $chatId,
            "¿Qué producto deseas buscar?\nPor ejemplo: taladro.",
            [
                'keyboard' => [[['text' => '/cancelar']]],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ]
        );
        return;
    }

    registrarLog("WOOCOMMERCE búsqueda={$termino}");

    $resultado = buscarProductos(
        $wcUrl,
        $wcConsumerKey,
        $wcConsumerSecret,
        $termino
    );

    if (!$resultado['ok']) {
        registrarLog('ERROR WOOCOMMERCE ' . $resultado['error']);

        // Conservamos el término, como establece el diseño conversacional.
        $estados[$chatId] = [
            'estado' => 'reintentar_catalogo',
            'termino' => $termino,
            'intentos' => 0,
        ];

        enviarMensaje(
            $api,
            $chatId,
            "No pude consultar el catálogo en este momento.\n"
            . "Conservé tu búsqueda: \"{$termino}\".\n\n"
            . "¿Quieres intentar nuevamente?",
            tecladoSiNo()
        );
        return;
    }

    $productos = $resultado['productos'];

    if (count($productos) === 0) {
        enviarMensaje(
            $api,
            $chatId,
            "No encontré productos relacionados con \"{$termino}\".\n\n"
            . "¿Deseas buscar otro producto?",
            tecladoSiNo()
        );

        $estados[$chatId] = [
            'estado' => 'buscar_otro',
            'intentos' => 0,
        ];
        return;
    }

    if (count($productos) === 1) {
        enviarMensaje($api, $chatId, formatearProducto($productos[0]));

        enviarMensaje(
            $api,
            $chatId,
            '¿Deseas buscar otro producto?',
            tecladoSiNo()
        );

        $estados[$chatId] = [
            'estado' => 'buscar_otro',
            'intentos' => 0,
        ];
        return;
    }

    $mensaje = "Encontré varios productos:\n\n";

    foreach ($productos as $indice => $producto) {
        $numero = $indice + 1;
        $nombre = trim((string) ($producto['name'] ?? 'Producto'));
        $precio = obtenerPrecio($producto);
        $mensaje .= "{$numero}. {$nombre} - {$precio}\n";
    }

    $mensaje .= "\nSelecciona una opción escribiendo el número.";

    enviarMensaje(
        $api,
        $chatId,
        $mensaje,
        tecladoSeleccion(count($productos))
    );

    $estados[$chatId] = [
        'estado' => 'seleccionando_producto',
        'productos' => $productos,
        'intentos' => 0,
    ];
}

/*
|--------------------------------------------------------------------------
| Reconocimiento de intención de búsqueda
|--------------------------------------------------------------------------
*/

function extraerProducto(string $texto): ?string
{
    $texto = trim($texto);

    $patrones = [
        '/^\/buscar(?:@\w+)?\s+(.+)$/iu',
        '/^(?:buscar|busco)\s+(.+)$/iu',
        '/^quiero\s+buscar\s+(?:un|una|el|la)?\s*(.+)$/iu',
        '/^quiero\s+(?:un|una)\s+(.+)$/iu',
        '/^cu[aá]nto\s+cuesta\s+(?:un|una|el|la)?\s*(.+?)[?]?$/iu',
        '/^cu[aá]l\s+es\s+el\s+precio\s+(?:de|del)\s+(?:un|una|el|la)?\s*(.+?)[?]?$/iu',
        '/^precio\s+(?:de|del)\s+(?:un|una|el|la)?\s*(.+?)[?]?$/iu',
        '/^tienen\s+(?:un|una|el|la)?\s*(.+?)[?]?$/iu',
    ];

    foreach ($patrones as $patron) {
        if (preg_match($patron, $texto, $coincidencia)) {
            $producto = trim($coincidencia[1]);
            return $producto !== '' ? $producto : null;
        }
    }

    return null;
}

function parecePreguntaFueraDeAlcance(string $texto): bool
{
    $t = textoComparacion($texto);

    if (str_contains($texto, '?')) {
        return true;
    }

    return preg_match(
        '/^(quien|quienes|cuando|donde|por que|porque|cual|cuales|que|como)\b/u',
        $t
    ) === 1;
}

function pareceNombreProducto(string $texto): bool
{
    $texto = trim($texto);

    if ($texto === '' || str_starts_with($texto, '/')) {
        return false;
    }

    if (str_contains($texto, '?')) {
        return false;
    }

    $palabras = preg_split('/\s+/u', $texto) ?: [];

    // Permite búsquedas naturales cortas como "martillo" o "pintura blanca".
    return count($palabras) <= 6;
}

/*
|--------------------------------------------------------------------------
| OpenAI - consultas abiertas de ferretería
|--------------------------------------------------------------------------
*/

function consultarOpenAI(
    string $apiKey,
    string $modelo,
    string $pregunta
): array {
    $instrucciones =
        "Eres FerreBot, asistente de una tienda de ferretería. "
        . "Responde únicamente preguntas generales relacionadas con herramientas, "
        . "materiales de ferretería, reparaciones básicas y uso seguro de herramientas. "
        . "Responde en español, con palabras sencillas, de forma breve y clara. "
        . "No inventes información. Si no sabes algo, dilo. "
        . "Nunca inventes precios, existencias, descuentos, disponibilidad ni productos de la tienda. "
        . "Los productos y precios se consultan exclusivamente desde WooCommerce. "
        . "No solicites datos personales, contraseñas, datos bancarios ni información de tarjetas.";

    $datos = json_encode(
        [
            'model' => $modelo,
            'instructions' => $instrucciones,
            'input' => $pregunta,
            'max_output_tokens' => 300,
            'store' => false,
        ],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    if ($datos === false) {
        return [
            'ok' => false,
            'respuesta' => null,
            'error' => 'No se pudo construir la solicitud JSON',
            'duracion' => 0.0,
        ];
    }

    $curl = curl_init('https://api.openai.com/v1/responses');

    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $datos,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $inicio = microtime(true);
    $respuesta = curl_exec($curl);
    $duracion = round(microtime(true) - $inicio, 2);

    if ($respuesta === false) {
        $error = curl_error($curl);
        curl_close($curl);

        return [
            'ok' => false,
            'respuesta' => null,
            'error' => $error,
            'duracion' => $duracion,
        ];
    }

    $codigoHttp = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $resultado = json_decode($respuesta, true);

    if ($codigoHttp < 200 || $codigoHttp >= 300) {
        $detalle = '';

        if (is_array($resultado)) {
            $detalle = trim((string) ($resultado['error']['message'] ?? ''));
        }

        return [
            'ok' => false,
            'respuesta' => null,
            'error' => $detalle !== '' ? "HTTP {$codigoHttp}: {$detalle}" : "HTTP {$codigoHttp}",
            'duracion' => $duracion,
        ];
    }

    if (!is_array($resultado)) {
        return [
            'ok' => false,
            'respuesta' => null,
            'error' => 'Respuesta JSON inválida de OpenAI',
            'duracion' => $duracion,
        ];
    }

    $textoRespuesta = trim((string) ($resultado['output_text'] ?? ''));

    // Fallback para respuestas donde el texto venga dentro de output[].content[].
    if ($textoRespuesta === '') {
        foreach (($resultado['output'] ?? []) as $salida) {
            if (($salida['type'] ?? '') !== 'message') {
                continue;
            }

            foreach (($salida['content'] ?? []) as $contenido) {
                if (($contenido['type'] ?? '') === 'output_text') {
                    $textoRespuesta .= (string) ($contenido['text'] ?? '');
                }
            }
        }

        $textoRespuesta = trim($textoRespuesta);
    }

    if ($textoRespuesta === '') {
        return [
            'ok' => false,
            'respuesta' => null,
            'error' => 'OpenAI no devolvió texto',
            'duracion' => $duracion,
        ];
    }

    return [
        'ok' => true,
        'respuesta' => $textoRespuesta,
        'error' => null,
        'duracion' => $duracion,
    ];
}

function esConsultaAbiertaFerreteria(string $texto): bool
{
    $normalizado = textoComparacion($texto);

    $palabrasFerreteria = [
        'ferreteria', 'herramienta', 'taladro', 'martillo', 'destornillador',
        'tornillo', 'tuerca', 'perno', 'arandela', 'llave allen', 'llave inglesa',
        'llave de tubo', 'serrucho', 'sierra', 'alicate', 'pinza', 'pintura',
        'brocha', 'rodillo', 'clavo', 'cemento', 'madera', 'metal', 'concreto',
        'lija', 'silicona', 'pegamento', 'adhesivo', 'cinta', 'metro', 'nivel',
        'escalera', 'broca', 'disco de corte', 'pulidora', 'amoladora', 'soldar',
        'soldadura', 'perforar', 'atornillar', 'apretar', 'aflojar', 'cortar',
        'reparar', 'reparacion', 'instalar', 'fijar', 'atornillador', 'tuberia',
        'tubo', 'grifo', 'llave de paso', 'bisagra', 'cerradura', 'electricidad',
        'cable', 'enchufe', 'tomacorriente', 'interruptor', 'multimetro',
    ];

    $esTemaFerreteria = false;

    foreach ($palabrasFerreteria as $palabra) {
        if (str_contains($normalizado, textoComparacion($palabra))) {
            $esTemaFerreteria = true;
            break;
        }
    }

    if (!$esTemaFerreteria) {
        return false;
    }

    $indicadoresPregunta = [
        'para que', 'como se', 'como puedo', 'que es', 'que herramienta',
        'cual herramienta', 'sirve para', 'se utiliza', 'puedo usar',
        'debo usar', 'como usar', 'como funciona', 'diferencia entre',
        'que necesito', 'que me recomiendas para',
    ];

    if (str_contains($texto, '?')) {
        return true;
    }

    return contieneAlguna($normalizado, $indicadoresPregunta);
}

function procesarConsultaOpenAI(
    string $api,
    int|string $chatId,
    string $texto,
    string $openaiApiKey,
    string $openaiModel,
    array &$estados
): void {
    // El cambio de intención abandona el flujo anterior.
    unset($estados[$chatId]);

    registrarLog('OPENAI consulta abierta');

    $resultado = consultarOpenAI(
        $openaiApiKey,
        $openaiModel,
        $texto
    );

    if (!$resultado['ok']) {
        registrarLog('ERROR OPENAI ' . $resultado['error']);

        enviarMensaje(
            $api,
            $chatId,
            "No pude responder esa consulta en este momento.\n"
            . "Puedes intentar nuevamente o usar /ayuda.",
            tecladoPrincipal()
        );
        return;
    }

    registrarLog(
        'OPENAI respuesta correcta tiempo=' . $resultado['duracion'] . 's'
    );

    enviarMensaje(
        $api,
        $chatId,
        $resultado['respuesta'],
        tecladoPrincipal()
    );
}

/*
|--------------------------------------------------------------------------
| Intenciones globales
|--------------------------------------------------------------------------
*/

function solicitaHumano(string $texto): bool
{
    $texto = textoComparacion($texto);

    return contieneAlguna($texto, [
        'hablar con una persona',
        'hablar con alguien',
        'atencion humana',
        'quiero un humano',
        'quiero hablar con alguien',
        'quiero hablar con una persona',
        'asesor',
        '👤 atencion humana',
    ]);
}

function solicitaInicio(string $texto): bool
{
    $texto = textoComparacion($texto);

    return in_array($texto, [
        'inicio',
        'volver al inicio',
        'quiero regresar',
        'regresar',
        '🏠 volver al inicio',
    ], true);
}

function esSi(string $texto): bool
{
    $texto = textoComparacion($texto);
    return in_array($texto, ['si', 's', 'sí'], true);
}

function esNo(string $texto): bool
{
    $texto = textoComparacion($texto);
    return in_array($texto, ['no', 'n'], true);
}

function numeroSeleccion(string $texto): ?int
{
    $normalizado = textoComparacion($texto);

    if (preg_match('/^\s*([1-9][0-9]*)\s*$/u', $normalizado, $m)) {
        return (int) $m[1];
    }

    $ordinales = [
        'primero' => 1,
        'primera' => 1,
        'segundo' => 2,
        'segunda' => 2,
        'tercero' => 3,
        'tercera' => 3,
        'cuarto' => 4,
        'cuarta' => 4,
        'quinto' => 5,
        'quinta' => 5,
    ];

    foreach ($ordinales as $palabra => $numero) {
        if (preg_match('/\b' . preg_quote($palabra, '/') . '\b/u', $normalizado)) {
            return $numero;
        }
    }

    return null;
}

function manejarIntentoInvalido(
    string $api,
    int|string $chatId,
    array &$estados,
    string $mensajePrimerosIntentos
): bool {
    $estados[$chatId]['intentos'] =
        (int) ($estados[$chatId]['intentos'] ?? 0) + 1;

    if ($estados[$chatId]['intentos'] >= 3) {
        $estados[$chatId] = [
            'estado' => 'derivacion',
            'intentos' => 0,
        ];

        enviarMensaje(
            $api,
            $chatId,
            "No logramos completar esta interacción después de 3 intentos.\n\n"
            . "Puedes volver al inicio o solicitar atención de una persona.",
            tecladoDerivacion()
        );

        return true;
    }

    enviarMensaje($api, $chatId, $mensajePrimerosIntentos);
    return false;
}

/*
|--------------------------------------------------------------------------
| Long polling
|--------------------------------------------------------------------------
*/

echo "FerreBot iniciado con OpenAI..." . PHP_EOL;
echo "Presiona Ctrl+C para detenerlo." . PHP_EOL;

while (true) {
    $url = "{$telegramApi}/getUpdates?timeout=30&offset={$offset}";
    $curl = curl_init($url);

    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 35,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    $respuesta = curl_exec($curl);

    if ($respuesta === false) {
        registrarLog('ERROR TELEGRAM getUpdates ' . curl_error($curl));
        fwrite(STDERR, 'Error consultando Telegram: ' . curl_error($curl) . PHP_EOL);
        curl_close($curl);
        sleep(2);
        continue;
    }

    curl_close($curl);

    $datos = json_decode($respuesta, true);

    if (!is_array($datos) || ($datos['ok'] ?? false) !== true) {
        registrarLog('ERROR TELEGRAM respuesta inválida de getUpdates');
        fwrite(STDERR, "Respuesta inválida de Telegram.\n");
        sleep(2);
        continue;
    }

    foreach (($datos['result'] ?? []) as $actualizacion) {
        $updateId = (int) ($actualizacion['update_id'] ?? 0);
        $offset = max($offset, $updateId + 1);

        if (!isset($actualizacion['message'])) {
            continue;
        }

        $mensaje = $actualizacion['message'];
        $chatId = $mensaje['chat']['id'] ?? null;

        if ($chatId === null) {
            continue;
        }

        $texto = trim((string) ($mensaje['text'] ?? ''));
        $textoNormalizado = textoComparacion($texto);

        registrarLog(
            "ENTRADA chat={$chatId} mensaje=" . str_replace("\n", ' ', $texto)
        );

        /*
        |------------------------------------------------------------------
        | Comandos globales
        |------------------------------------------------------------------
        */

        if (preg_match('/^\/start(?:@\w+)?$/i', $texto) || solicitaInicio($texto)) {
            unset($estados[$chatId]);
            mostrarInicio($telegramApi, $chatId);
            continue;
        }

        if (
            preg_match('/^\/ayuda(?:@\w+)?$/i', $texto) ||
            $textoNormalizado === 'ayuda' ||
            $textoNormalizado === '❓ ayuda'
        ) {
            // /ayuda es global y no destruye el estado actual.
            mostrarAyuda(
                $telegramApi,
                $chatId,
                tecladoParaEstado($estados[$chatId] ?? null)
            );
            continue;
        }

        if (
            preg_match('/^\/cancelar(?:@\w+)?$/i', $texto) ||
            $textoNormalizado === 'cancelar'
        ) {
            unset($estados[$chatId]);
            enviarMensaje(
                $telegramApi,
                $chatId,
                "Operación cancelada. Regresaste al inicio.",
                tecladoPrincipal()
            );
            continue;
        }

        if (solicitaHumano($texto)) {
            unset($estados[$chatId]);
            mostrarContactoHumano($telegramApi, $chatId, $humanContact);
            continue;
        }

        if (
            $textoNormalizado === '🔎 buscar producto' ||
            preg_match('/^\/buscar(?:@\w+)?$/i', $texto)
        ) {
            $estados[$chatId] = [
                'estado' => 'esperando_producto',
                'intentos' => 0,
            ];

            enviarMensaje(
                $telegramApi,
                $chatId,
                "¿Qué producto deseas buscar?\nPor ejemplo: taladro."
            );
            continue;
        }

        /*
        |------------------------------------------------------------------
        | Cambio de intención: nueva búsqueda explícita
        |------------------------------------------------------------------
        */

        $productoDirecto = extraerProducto($texto);

        if ($productoDirecto !== null) {
            unset($estados[$chatId]);

            procesarBusqueda(
                $telegramApi,
                $chatId,
                $productoDirecto,
                $wcUrl,
                $wcConsumerKey,
                $wcConsumerSecret,
                $estados
            );
            continue;
        }

        /*
        |------------------------------------------------------------------
        | Cambio de intención: consulta abierta de ferretería
        |------------------------------------------------------------------
        */

        if (esConsultaAbiertaFerreteria($texto)) {
            procesarConsultaOpenAI(
                $telegramApi,
                $chatId,
                $texto,
                $openaiApiKey,
                $openaiModel,
                $estados
            );
            continue;
        }

        /*
        |------------------------------------------------------------------
        | Estado: esperando producto
        |------------------------------------------------------------------
        */

        if (($estados[$chatId]['estado'] ?? null) === 'esperando_producto') {
            if ($texto === '' || str_starts_with($texto, '/')) {
                manejarIntentoInvalido(
                    $telegramApi,
                    $chatId,
                    $estados,
                    "Necesito que escribas el nombre del producto.\n"
                    . "Por ejemplo: taladro."
                );
                continue;
            }

            procesarBusqueda(
                $telegramApi,
                $chatId,
                $texto,
                $wcUrl,
                $wcConsumerKey,
                $wcConsumerSecret,
                $estados
            );
            continue;
        }

        /*
        |------------------------------------------------------------------
        | Estado: seleccionar entre varios productos
        |------------------------------------------------------------------
        */

        if (($estados[$chatId]['estado'] ?? null) === 'seleccionando_producto') {
            $productos = $estados[$chatId]['productos'] ?? [];
            $seleccion = numeroSeleccion($texto);

            if (
                $seleccion !== null &&
                $seleccion >= 1 &&
                $seleccion <= count($productos)
            ) {
                $producto = $productos[$seleccion - 1];

                enviarMensaje(
                    $telegramApi,
                    $chatId,
                    formatearProducto($producto)
                );

                enviarMensaje(
                    $telegramApi,
                    $chatId,
                    '¿Deseas buscar otro producto?',
                    tecladoSiNo()
                );

                $estados[$chatId] = [
                    'estado' => 'buscar_otro',
                    'intentos' => 0,
                ];
                continue;
            }

            manejarIntentoInvalido(
                $telegramApi,
                $chatId,
                $estados,
                "La opción no es válida.\n"
                . "Escribe un número entre 1 y " . count($productos) . "."
            );
            continue;
        }

        /*
        |------------------------------------------------------------------
        | Estado: reintentar WooCommerce conservando el término
        |------------------------------------------------------------------
        */

        if (($estados[$chatId]['estado'] ?? null) === 'reintentar_catalogo') {
            if (esSi($texto)) {
                $termino = (string) ($estados[$chatId]['termino'] ?? '');

                procesarBusqueda(
                    $telegramApi,
                    $chatId,
                    $termino,
                    $wcUrl,
                    $wcConsumerKey,
                    $wcConsumerSecret,
                    $estados
                );
                continue;
            }

            if (esNo($texto)) {
                unset($estados[$chatId]);
                mostrarInicio($telegramApi, $chatId);
                continue;
            }

            manejarIntentoInvalido(
                $telegramApi,
                $chatId,
                $estados,
                'Responde Sí para reintentar o No para volver al inicio.'
            );
            continue;
        }

        /*
        |------------------------------------------------------------------
        | Estado: buscar otro producto
        |------------------------------------------------------------------
        */

        if (($estados[$chatId]['estado'] ?? null) === 'buscar_otro') {
            if (esSi($texto)) {
                $estados[$chatId] = [
                    'estado' => 'esperando_producto',
                    'intentos' => 0,
                ];

                enviarMensaje(
                    $telegramApi,
                    $chatId,
                    "¿Qué producto deseas buscar?\nPor ejemplo: taladro."
                );
                continue;
            }

            if (esNo($texto)) {
                unset($estados[$chatId]);
                enviarMensaje(
                    $telegramApi,
                    $chatId,
                    "Entendido. Si necesitas algo más, puedes escribir /start.",
                    tecladoPrincipal()
                );
                continue;
            }

            // Permite reutilizar el turno: "taladro" inicia otra búsqueda.
            if (pareceNombreProducto($texto)) {
                unset($estados[$chatId]);

                procesarBusqueda(
                    $telegramApi,
                    $chatId,
                    $texto,
                    $wcUrl,
                    $wcConsumerKey,
                    $wcConsumerSecret,
                    $estados
                );
                continue;
            }

            manejarIntentoInvalido(
                $telegramApi,
                $chatId,
                $estados,
                "Puedes responder Sí o No, o escribir directamente otro producto."
            );
            continue;
        }

        /*
        |------------------------------------------------------------------
        | Estado: después de tres intentos inválidos
        |------------------------------------------------------------------
        */

        if (($estados[$chatId]['estado'] ?? null) === 'derivacion') {
            if (solicitaInicio($texto)) {
                unset($estados[$chatId]);
                mostrarInicio($telegramApi, $chatId);
                continue;
            }

            if (solicitaHumano($texto)) {
                unset($estados[$chatId]);
                mostrarContactoHumano($telegramApi, $chatId, $humanContact);
                continue;
            }

            enviarMensaje(
                $telegramApi,
                $chatId,
                'Elige volver al inicio o solicitar atención humana.',
                tecladoDerivacion()
            );
            continue;
        }

        /*
        |------------------------------------------------------------------
        | Sin estado: permitir escribir solo el nombre del producto
        |------------------------------------------------------------------
        */

        if (pareceNombreProducto($texto) && !parecePreguntaFueraDeAlcance($texto)) {
            procesarBusqueda(
                $telegramApi,
                $chatId,
                $texto,
                $wcUrl,
                $wcConsumerKey,
                $wcConsumerSecret,
                $estados
            );
            continue;
        }

        /*
        |------------------------------------------------------------------
        | Fuera de alcance
        |------------------------------------------------------------------
        */

        enviarMensaje(
            $telegramApi,
            $chatId,
            "Esa consulta está fuera de mi alcance.\n\n"
            . "Puedo ayudarte a buscar productos, consultar precios o responder "
            . "preguntas básicas relacionadas con herramientas.\n\n"
            . "Usa /ayuda para ver ejemplos.",
            tecladoPrincipal()
        );
    }
}
