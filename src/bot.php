<?php

$ROOT = dirname(__DIR__);

/* =========================================================
   CONFIGURACIÓN
   ========================================================= */

$env = parse_ini_file($ROOT . '/.env');

$TOKEN = $env['BOT_TOKEN'] ?? null;

$HUMAN_CONTACT =
    $env['HUMAN_CONTACT']
    ?? 'un profesional de nutrición';

if (!$TOKEN) {
    exit("Error: BOT_TOKEN no encontrado en .env\n");
}

$API = "https://api.telegram.org/bot{$TOKEN}/";

$STATE_FILE = $ROOT . '/data/states.json';
$LOG_FILE   = $ROOT . '/logs/bot.log';


/* =========================================================
   CREAR DIRECTORIOS SI NO EXISTEN
   ========================================================= */

if (!is_dir($ROOT . '/data')) {
    mkdir($ROOT . '/data', 0775, true);
}

if (!is_dir($ROOT . '/logs')) {
    mkdir($ROOT . '/logs', 0775, true);
}


/* =========================================================
   DATOS NUTRICIONALES TEMPORALES
   =========================================================
   Posteriormente esta información podrá complementarse
   mediante la integración REST de la Actividad 1.
   ========================================================= */

$nutrientes = [

    'vitamina b12' => [
        'nombre' => 'Vitamina B12',
        'funcion' =>
            'Participa en la formación normal de glóbulos rojos y '
            . 'en el funcionamiento normal del sistema nervioso.',

        'fuentes' => [
            'Pescados',
            'Carnes',
            'Huevos'
        ]
    ],

    'vitamina c' => [
        'nombre' => 'Vitamina C',
        'funcion' =>
            'Contribuye al funcionamiento normal del sistema inmunitario '
            . 'y participa en la formación de colágeno.',

        'fuentes' => [
            'Naranja',
            'Guayaba',
            'Pimiento'
        ]
    ],

    'vitamina d' => [
        'nombre' => 'Vitamina D',
        'funcion' =>
            'Participa en la absorción y utilización normal '
            . 'del calcio y del fósforo.',

        'fuentes' => [
            'Pescados grasos',
            'Yema de huevo',
            'Alimentos fortificados'
        ]
    ],

    'vitamina k' => [
        'nombre' => 'Vitamina K',
        'funcion' =>
            'Participa en el proceso normal de coagulación de la sangre.',

        'fuentes' => [
            'Espinaca',
            'Brócoli',
            'Col rizada'
        ]
    ],

    'hierro' => [
        'nombre' => 'Hierro',
        'funcion' =>
            'Participa en la formación normal de hemoglobina '
            . 'y en el transporte de oxígeno.',

        'fuentes' => [
            'Carnes',
            'Lentejas',
            'Frijoles'
        ]
    ],

    'calcio' => [
        'nombre' => 'Calcio',
        'funcion' =>
            'Contribuye al mantenimiento normal de huesos y dientes.',

        'fuentes' => [
            'Leche',
            'Yogur',
            'Sardinas'
        ]
    ]
];


/* =========================================================
   ESTADOS DE CONVERSACIÓN
   ========================================================= */

function nuevoEstado()
{
    return [
        'estado'      => 'menu',
        'intentos'    => 0,

        'nutriente_1' => null,
        'nutriente_2' => null,

        'nombre'      => null,
        'contacto'    => null
    ];
}


function cargarEstados()
{
    global $STATE_FILE;

    if (!file_exists($STATE_FILE)) {
        return [];
    }

    $contenido = file_get_contents($STATE_FILE);

    if (!$contenido) {
        return [];
    }

    $datos = json_decode($contenido, true);

    return is_array($datos) ? $datos : [];
}


function guardarEstados($estados)
{
    global $STATE_FILE;

    file_put_contents(
        $STATE_FILE,
        json_encode(
            $estados,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ),
        LOCK_EX
    );
}


/* =========================================================
   LOGS
   ========================================================= */

function logBot($tipo, $mensaje)
{
    global $LOG_FILE;

    $fecha = date('Y-m-d H:i:s');

    file_put_contents(
        $LOG_FILE,
        "[$fecha][$tipo] {$mensaje}\n",
        FILE_APPEND | LOCK_EX
    );
}


/* =========================================================
   TELEGRAM API
   ========================================================= */

function telegram($metodo, $datos = [])
{
    global $API;

    $curl = curl_init($API . $metodo);

    curl_setopt_array($curl, [

        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $datos,

        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 35
    ]);

    $respuesta = curl_exec($curl);

    if ($respuesta === false) {

        logBot(
            'ERROR',
            'Telegram: ' . curl_error($curl)
        );

        curl_close($curl);

        return [];
    }

    curl_close($curl);

    $json = json_decode($respuesta, true);

    return is_array($json) ? $json : [];
}


/* =========================================================
   MENÚ DE TELEGRAM
   ========================================================= */

function tecladoPrincipal()
{
    return json_encode([

        'keyboard' => [

            [
                ['text' => 'Comparar nutrientes'],
                ['text' => 'Fuentes naturales']
            ],

            [
                ['text' => 'Función de un nutriente'],
                ['text' => 'Agendar asesoría']
            ],

            [
                ['text' => 'Ayuda'],
                ['text' => 'Cancelar']
            ]
        ],

        'resize_keyboard' => true
    ]);
}


/* =========================================================
   ENVIAR MENSAJES
   ========================================================= */

function enviarMensaje($chatId, $texto, $mostrarMenu = false)
{
    logBot('OUT', $texto);

    $datos = [
        'chat_id' => $chatId,
        'text'    => $texto
    ];

    if ($mostrarMenu) {
        $datos['reply_markup'] = tecladoPrincipal();
    }

    telegram('sendMessage', $datos);
}


/* =========================================================
   NORMALIZACIÓN DE TEXTO
   ========================================================= */

function normalizar($texto)
{
    $texto = mb_strtolower(trim($texto));

    return strtr($texto, [

        'á' => 'a',
        'é' => 'e',
        'í' => 'i',
        'ó' => 'o',
        'ú' => 'u',
        'ü' => 'u'
    ]);
}


/* =========================================================
   DETECTAR NUTRIENTES
   ========================================================= */

function detectarNutrientes($texto)
{
    global $nutrientes;

    $textoNormal = normalizar($texto);

    $encontrados = [];

    foreach ($nutrientes as $clave => $datos) {

        $claveNormal = normalizar($clave);

        if (str_contains($textoNormal, $claveNormal)) {
            $encontrados[] = $clave;
        }
    }

    return array_values(array_unique($encontrados));
}


/* =========================================================
   DETECTAR CONSULTAS FUERA DE ALCANCE
   ========================================================= */

function esConsultaMedicaFueraDeAlcance($texto)
{
    $texto = normalizar($texto);

    $palabras = [

        'medicamento',
        'medicina',
        'tratamiento',
        'diagnostico',
        'dosis',
        'recetar',
        'receta',
        'enfermedad',
        'sintoma',
        'sintomas'
    ];

    foreach ($palabras as $palabra) {

        if (str_contains($texto, $palabra)) {
            return true;
        }
    }

    return false;
}


/* =========================================================
   MENSAJE PRINCIPAL
   ========================================================= */

function mostrarMenu($chatId)
{
    enviarMensaje(

        $chatId,

        "¡Hola! Soy NutriGuía 🌿\n\n"

        . "Puedo ayudarte a:\n"

        . "1. Comparar vitaminas y nutrientes.\n"
        . "2. Conocer fuentes naturales.\n"
        . "3. Consultar para qué sirve un nutriente.\n"
        . "4. Solicitar una asesoría nutricional.\n\n"

        . "Elige una opción o escribe tu consulta directamente.\n\n"

        . "Puedes usar /ayuda, /volver o /cancelar en cualquier momento.",

        true
    );
}


/* =========================================================
   RESPUESTA: FUENTES NATURALES
   ========================================================= */

function responderFuentes($chatId, $nutriente)
{
    global $nutrientes;

    $datos = $nutrientes[$nutriente];

    $fuentes = $datos['fuentes'];

    $mensaje =

        "Estas son algunas fuentes de "
        . $datos['nombre']
        . ":\n\n"

        . "1. {$fuentes[0]}\n"
        . "2. {$fuentes[1]}\n"
        . "3. {$fuentes[2]}\n\n"

        . "Esta información es educativa y no sustituye "
        . "una recomendación nutricional personalizada.\n\n"

        . "¿Quieres realizar otra consulta?";

    enviarMensaje($chatId, $mensaje);
}


/* =========================================================
   RESPUESTA: FUNCIÓN
   ========================================================= */

function responderFuncion($chatId, $nutriente)
{
    global $nutrientes;

    $datos = $nutrientes[$nutriente];

    enviarMensaje(

        $chatId,

        $datos['nombre']
        . "\n\n"

        . $datos['funcion']

        . "\n\nEsta información tiene fines educativos."

        . "\n\n¿Quieres realizar otra consulta?"
    );
}


/* =========================================================
   RESPUESTA: COMPARACIÓN
   ========================================================= */

function responderComparacion($chatId, $n1, $n2)
{
    global $nutrientes;

    $dato1 = $nutrientes[$n1];
    $dato2 = $nutrientes[$n2];

    $mensaje =

        "Comparación entre "
        . $dato1['nombre']
        . " y "
        . $dato2['nombre']
        . ":\n\n"

        . $dato1['nombre']
        . ":\n"
        . $dato1['funcion']
        . "\n\n"

        . $dato2['nombre']
        . ":\n"
        . $dato2['funcion']

        . "\n\n¿Quieres realizar otra consulta?";

    enviarMensaje($chatId, $mensaje);
}


/* =========================================================
   PREPARAR PREGUNTA DE CONTINUACIÓN
   ========================================================= */

function esperarContinuacion(&$estado)
{
    $estado['estado'] = 'esperando_continuar';

    $estado['intentos'] = 0;

    $estado['nutriente_1'] = null;
    $estado['nutriente_2'] = null;

    $estado['nombre']   = null;
    $estado['contacto'] = null;
}


/* =========================================================
   MANEJO DE ERRORES
   ========================================================= */

function errorIntento($chatId, &$estado, $mensaje)
{
    global $HUMAN_CONTACT;

    $estado['intentos']++;

    /* Primer intento */

    if ($estado['intentos'] === 1) {

        enviarMensaje(

            $chatId,

            $mensaje

            . "\n\nPrueba nuevamente siguiendo el ejemplo mostrado."

            . "\n\nIntento 1 de 3."
        );

        return;
    }


    /* Segundo intento */

    if ($estado['intentos'] === 2) {

        enviarMensaje(

            $chatId,

            "Todavía no pude identificar correctamente el dato.\n\n"

            . "Puedes intentarlo nuevamente, usar /ayuda "
            . "o cancelar con /cancelar.\n\n"

            . "Intento 2 de 3."
        );

        return;
    }


    /* Tercer intento */

    enviarMensaje(

        $chatId,

        "No pude completar la consulta después de tres intentos.\n\n"

        . "Puedes volver al menú o solicitar una asesoría "
        . "con un profesional de nutrición.\n\n"

        . "Contacto disponible: {$HUMAN_CONTACT}",

        true
    );

    $estado = nuevoEstado();
}


/* =========================================================
   INICIAR FLUJO DE ASESORÍA
   ========================================================= */

function iniciarAsesoria($chatId, &$estado)
{
    $estado = nuevoEstado();

    $estado['estado'] = 'esperando_nombre_asesoria';

    enviarMensaje(

        $chatId,

        "Claro. Para solicitar una asesoría necesito algunos datos.\n\n"

        . "Primero, ¿cuál es tu nombre?"
    );
}


/* =========================================================
   PROCESAR MENSAJE
   ========================================================= */

function procesarMensaje($chatId, $texto)
{
    global $nutrientes, $HUMAN_CONTACT;

    $estados = cargarEstados();


    /* Crear estado del usuario */

    if (!isset($estados[$chatId])) {
        $estados[$chatId] = nuevoEstado();
    }


    $estado =& $estados[$chatId];

    $textoNormal = normalizar($texto);


    /* =====================================================
       LOG DE ENTRADA
       ===================================================== */

    /*
     * No registramos nombre/contacto literalmente
     * para reducir exposición de datos personales.
     */

    if (
        $estado['estado'] === 'esperando_nombre_asesoria'
        ||
        $estado['estado'] === 'esperando_contacto_asesoria'
    ) {

        logBot('IN', '[dato personal oculto]');

    } else {

        logBot('IN', $texto);
    }


    /* =====================================================
       COMANDOS GLOBALES
       ===================================================== */

    if (

        $textoNormal === '/start'
        ||
        $textoNormal === 'hola'
        ||
        $textoNormal === 'buenas'
        ||
        $textoNormal === 'buenos dias'
        ||
        $textoNormal === 'buenas tardes'
        ||
        $textoNormal === 'buenas noches'

    ) {

        $estado = nuevoEstado();

        mostrarMenu($chatId);

        guardarEstados($estados);

        return;
    }


    /* AYUDA */

    if (

        $textoNormal === '/ayuda'
        ||
        $textoNormal === 'ayuda'

    ) {

        enviarMensaje(

            $chatId,

            "¿Necesitas ayuda? Estas son mis opciones:\n\n"

            . "1. Comparar vitaminas o nutrientes.\n"
            . "2. Consultar fuentes naturales.\n"
            . "3. Conocer la función de un nutriente.\n"
            . "4. Solicitar una asesoría nutricional.\n\n"

            . "Puedes usar /volver para regresar al menú "
            . "o /cancelar para detener la operación actual.",

            true
        );

        guardarEstados($estados);

        return;
    }


    /* VOLVER */

    if (

        $textoNormal === '/volver'
        ||
        $textoNormal === 'volver'

    ) {

        $estado = nuevoEstado();

        enviarMensaje(
            $chatId,
            "Regresamos al menú principal.",
            true
        );

        guardarEstados($estados);

        return;
    }


    /* CANCELAR */

    if (

        $textoNormal === '/cancelar'
        ||
        $textoNormal === 'cancelar'

    ) {

        $estado = nuevoEstado();

        enviarMensaje(

            $chatId,

            "Consulta cancelada.\n\n"

            . "Los datos temporales de esta operación fueron descartados.\n\n"

            . "Puedes iniciar otra consulta cuando quieras.",

            true
        );

        guardarEstados($estados);

        return;
    }


    /* CONSULTA MÉDICA FUERA DE ALCANCE */
    if (esConsultaMedicaFueraDeAlcance($texto)) {

        enviarMensaje(

            $chatId,

            "Puedo brindarte información educativa sobre nutrientes, "
            . "pero no puedo realizar diagnósticos ni indicar medicamentos, "
            . "tratamientos o dosis.\n\n"

            . "Para este tipo de consulta debes acudir a un profesional "
            . "de salud o nutrición.\n\n"

            . "Si lo deseas, puedes seleccionar "
            . "\"Agendar asesoría\".",

            true
        );

        $estado = nuevoEstado();

        guardarEstados($estados);

        return;
    }


    /* ¿QUIERE CONTINUAR? */
    if ($estado['estado'] === 'esperando_continuar') {

        if (

            $textoNormal === 'si'
            ||
            $textoNormal === 'sí'
            ||
            $textoNormal === 'claro'

        ) {

            $estado = nuevoEstado();

            mostrarMenu($chatId);

            guardarEstados($estados);

            return;
        }


        if (

            $textoNormal === 'no'
            ||
            $textoNormal === 'salir'
            ||
            $textoNormal === 'finalizar'

        ) {

            $estado = nuevoEstado();

            enviarMensaje(

                $chatId,

                "Gracias por usar NutriGuía 🌿\n\n"
                . "Puedes volver cuando quieras para consultar otro nutriente."
            );

            guardarEstados($estados);

            return;
        }


        errorIntento(

            $chatId,

            $estado,

            "Responde Sí para realizar otra consulta "
            . "o No para finalizar."
        );

        guardarEstados($estados);

        return;
    }


    /* CAMBIO DE INTENCIÓN */
    if (

        str_contains($textoNormal, 'agendar asesoria')
        ||
        str_contains($textoNormal, 'solicitar asesoria')
        ||
        str_contains($textoNormal, 'quiero una asesoria')
        ||
        str_contains($textoNormal, 'quiero una cita')

    ) {

        iniciarAsesoria($chatId, $estado);

        guardarEstados($estados);

        return;
    }


    /* FLUJO: FUENTES NATURALES */
    if ($estado['estado'] === 'esperando_fuente') {

        /* Si cambia explícitamente a comparar */
        if (str_contains($textoNormal, 'compar')) {

            $detectados = detectarNutrientes($texto);

            if (count($detectados) >= 2) {

                responderComparacion(
                    $chatId,
                    $detectados[0],
                    $detectados[1]
                );

                esperarContinuacion($estado);

            } elseif (count($detectados) === 1) {

                $estado['nutriente_1'] = $detectados[0];

                $estado['estado'] =
                    'esperando_nutriente_2';

                $estado['intentos'] = 0;

                enviarMensaje(

                    $chatId,

                    "Ya tengo "
                    . $nutrientes[$detectados[0]]['nombre']
                    . ".\n\n"

                    . "¿Con qué otro nutriente quieres compararlo?"
                );

            } else {

                $estado = nuevoEstado();

                $estado['estado'] =
                    'esperando_nutriente_1';

                enviarMensaje(

                    $chatId,

                    "¿Qué dos nutrientes quieres comparar?\n\n"
                    . "Por ejemplo: vitamina C y vitamina B12."
                );
            }

            guardarEstados($estados);

            return;
        }


        $detectados = detectarNutrientes($texto);


        if ($detectados) {

            responderFuentes(
                $chatId,
                $detectados[0]
            );

            esperarContinuacion($estado);

        } else {

            errorIntento(

                $chatId,

                $estado,

                "No reconocí ese nutriente.\n\n"
                . "Por ejemplo: vitamina B12, "
                . "vitamina C, hierro o calcio."
            );
        }


        guardarEstados($estados);

        return;
    }


    /* FLUJO: FUNCIÓN */
    if ($estado['estado'] === 'esperando_funcion') {

        $detectados = detectarNutrientes($texto);


        if ($detectados) {

            responderFuncion(
                $chatId,
                $detectados[0]
            );

            esperarContinuacion($estado);

        } else {

            errorIntento(

                $chatId,

                $estado,

                "No reconocí ese nutriente.\n\n"
                . "Por ejemplo: vitamina C, "
                . "vitamina D, hierro o calcio."
            );
        }


        guardarEstados($estados);

        return;
    }


    /* COMPARACIÓN - PRIMER NUTRIENTE */
    if ($estado['estado'] === 'esperando_nutriente_1') {

        $detectados = detectarNutrientes($texto);


        /* Ya proporcionó ambos */

        if (count($detectados) >= 2) {

            responderComparacion(

                $chatId,

                $detectados[0],
                $detectados[1]
            );

            esperarContinuacion($estado);
        }


        /* Proporcionó solo el primero */

        elseif (count($detectados) === 1) {

            $estado['nutriente_1'] =
                $detectados[0];

            $estado['estado'] =
                'esperando_nutriente_2';

            $estado['intentos'] = 0;


            enviarMensaje(

                $chatId,

                "Ya tengo "
                . $nutrientes[$detectados[0]]['nombre']
                . ".\n\n"

                . "¿Con qué otro nutriente quieres compararlo?\n\n"

                . "Por ejemplo: vitamina B12, "
                . "vitamina D o hierro."
            );
        }


        /* Ninguno reconocido */

        else {

            errorIntento(

                $chatId,

                $estado,

                "No reconocí ningún nutriente.\n\n"
                . "Por ejemplo: vitamina C y vitamina B12."
            );
        }


        guardarEstados($estados);

        return;
    }


    /* COMPARACIÓN - SEGUNDO NUTRIENTE */
    if ($estado['estado'] === 'esperando_nutriente_2') {

        $detectados = detectarNutrientes($texto);


        if (!$detectados) {

            errorIntento(

                $chatId,

                $estado,

                "No reconocí el segundo nutriente.\n\n"
                . "Por ejemplo: vitamina C, "
                . "vitamina D o hierro."
            );

            guardarEstados($estados);

            return;
        }


        $segundo = $detectados[0];


        /* Evitar comparar consigo mismo */

        if ($segundo === $estado['nutriente_1']) {

            errorIntento(

                $chatId,

                $estado,

                "Ya seleccionaste ese nutriente.\n\n"
                . "Elige uno diferente para realizar la comparación."
            );

            guardarEstados($estados);

            return;
        }


        responderComparacion(

            $chatId,

            $estado['nutriente_1'],
            $segundo
        );


        esperarContinuacion($estado);

        guardarEstados($estados);

        return;
    }


    /* NUTRIENTE SIN INTENCIÓN DEFINIDA */
    if ($estado['estado'] === 'esperando_accion_nutriente') {

        $nutriente = $estado['nutriente_1'];


        if (str_contains($textoNormal, 'fuente')) {

            responderFuentes(
                $chatId,
                $nutriente
            );

            esperarContinuacion($estado);

        }

        elseif (

            str_contains($textoNormal, 'funcion')
            ||
            str_contains($textoNormal, 'sirve')
            ||
            str_contains($textoNormal, 'beneficio')

        ) {

            responderFuncion(
                $chatId,
                $nutriente
            );

            esperarContinuacion($estado);

        }

        elseif (str_contains($textoNormal, 'compar')) {

            $estado['estado'] =
                'esperando_nutriente_2';

            $estado['intentos'] = 0;


            enviarMensaje(

                $chatId,

                "Perfecto. Ya tengo "
                . $nutrientes[$nutriente]['nombre']
                . ".\n\n"

                . "¿Con qué otro nutriente quieres compararlo?"
            );

        }

        else {

            errorIntento(

                $chatId,

                $estado,

                "Indica qué quieres consultar:\n\n"
                . "• Fuentes naturales\n"
                . "• Función\n"
                . "• Comparar"
            );
        }


        guardarEstados($estados);

        return;
    }


    /* ASESORÍA - NOMBRE */
    if ($estado['estado'] === 'esperando_nombre_asesoria') {

        if (mb_strlen(trim($texto)) < 2) {

            errorIntento(

                $chatId,

                $estado,

                "El nombre parece incompleto."
            );

        } else {

            $estado['nombre'] = trim($texto);

            $estado['estado'] =
                'esperando_contacto_asesoria';

            $estado['intentos'] = 0;


            enviarMensaje(

                $chatId,

                "Gracias.\n\n"

                . "Ahora indícame un medio de contacto "
                . "para continuar con la solicitud."
            );
        }


        guardarEstados($estados);

        return;
    }


    /* ASESORÍA - CONTACTO */
    if ($estado['estado'] === 'esperando_contacto_asesoria') {

        if (mb_strlen(trim($texto)) < 4) {

            errorIntento(

                $chatId,

                $estado,

                "El dato de contacto parece incompleto."
            );

        } else {

            $estado['contacto'] = trim($texto);

            $estado['estado'] =
                'confirmando_asesoria';

            $estado['intentos'] = 0;


            enviarMensaje(

                $chatId,

                "Ya tengo los datos necesarios.\n\n"

                . "¿Confirmas que deseas solicitar "
                . "la asesoría?\n\n"

                . "Responde Sí o No."
            );
        }


        guardarEstados($estados);

        return;
    }


    /* ASESORÍA - CONFIRMACIÓN */
    if ($estado['estado'] === 'confirmando_asesoria') {

        if (

            $textoNormal === 'si'
            ||
            $textoNormal === 'sí'

        ) {

            enviarMensaje(

                $chatId,

                "Solicitud confirmada ✅\n\n"

                . "Puedes continuar la atención con "
                . "{$HUMAN_CONTACT}.\n\n"

                . "¿Quieres realizar otra consulta?"
            );


            esperarContinuacion($estado);

        }

        elseif ($textoNormal === 'no') {

            $estado = nuevoEstado();


            enviarMensaje(

                $chatId,

                "Solicitud cancelada.\n\n"

                . "Los datos temporales fueron descartados.\n\n"

                . "Puedes iniciar otra consulta cuando quieras.",

                true
            );

        }

        else {

            errorIntento(

                $chatId,

                $estado,

                "Para confirmar la solicitud responde Sí o No."
            );
        }


        guardarEstados($estados);

        return;
    }


    /* DETECCIÓN DE INTENCIÓN DESDE EL MENÚ */
    $detectados = detectarNutrientes($texto);


    /* COMPARAR */
    if (

        str_contains($textoNormal, 'compar')
        ||
        $textoNormal === 'comparar nutrientes'

    ) {

        /* Ya escribió ambos */

        if (count($detectados) >= 2) {

            responderComparacion(

                $chatId,

                $detectados[0],
                $detectados[1]
            );


            esperarContinuacion($estado);

        }


        /* Ya escribió uno */

        elseif (count($detectados) === 1) {

            $estado['nutriente_1'] =
                $detectados[0];

            $estado['estado'] =
                'esperando_nutriente_2';

            $estado['intentos'] = 0;


            enviarMensaje(

                $chatId,

                "Ya tengo "
                . $nutrientes[$detectados[0]]['nombre']
                . ".\n\n"

                . "¿Con qué otro nutriente quieres compararlo?\n\n"

                . "Por ejemplo: vitamina B12, "
                . "vitamina D o hierro."
            );

        }


        /* No escribió ninguno */

        else {

            $estado['estado'] =
                'esperando_nutriente_1';

            $estado['intentos'] = 0;


            enviarMensaje(

                $chatId,

                "¿Qué dos nutrientes quieres comparar?\n\n"

                . "Por ejemplo: vitamina C y vitamina B12."
            );
        }
    }


    /* FUENTES NATURALES */
    elseif (

        str_contains($textoNormal, 'fuente')
        ||
        str_contains($textoNormal, 'alimento')
        ||
        str_contains($textoNormal, 'natural')
        ||
        str_contains($textoNormal, 'contiene')
        ||
        str_contains($textoNormal, 'obtener')

    ) {

        /* Ya escribio el nutriente */
        if ($detectados) {

            responderFuentes(
                $chatId,
                $detectados[0]
            );


            esperarContinuacion($estado);

        }


        /* Falta el nutriente */

        else {

            $estado['estado'] =
                'esperando_fuente';

            $estado['intentos'] = 0;


            enviarMensaje(

                $chatId,

                "¿Qué vitamina o nutriente quieres consultar?\n\n"

                . "Por ejemplo: vitamina B12, "
                . "vitamina C, hierro o calcio."
            );
        }
    }


    /* FUNCIÓN DEL NUTRIENTE */
    elseif (

        str_contains($textoNormal, 'funcion')
        ||
        str_contains($textoNormal, 'sirve')
        ||
        str_contains($textoNormal, 'beneficio')

    ) {

        if ($detectados) {

            responderFuncion(
                $chatId,
                $detectados[0]
            );


            esperarContinuacion($estado);

        }

        else {

            $estado['estado'] =
                'esperando_funcion';

            $estado['intentos'] = 0;


            enviarMensaje(

                $chatId,

                "¿De qué nutriente quieres conocer su función?\n\n"

                . "Por ejemplo: vitamina C, "
                . "vitamina D, hierro o calcio."
            );
        }
    }


    /* ASESORÍA */
    elseif (

        str_contains($textoNormal, 'asesoria')
        ||
        str_contains($textoNormal, 'cita')

    ) {

        iniciarAsesoria(
            $chatId,
            $estado
        );
    }


    /* BOTONES */
    elseif ($textoNormal === 'fuentes naturales') {

        $estado['estado'] =
            'esperando_fuente';

        $estado['intentos'] = 0;


        enviarMensaje(

            $chatId,

            "¿Qué vitamina o nutriente quieres consultar?\n\n"

            . "Por ejemplo: vitamina B12, "
            . "vitamina C, hierro o calcio."
        );
    }


    elseif (

        $textoNormal === 'funcion de un nutriente'

    ) {

        $estado['estado'] =
            'esperando_funcion';

        $estado['intentos'] = 0;


        enviarMensaje(

            $chatId,

            "¿De qué nutriente quieres conocer su función?"
        );
    }


    elseif ($textoNormal === 'agendar asesoria') {

        iniciarAsesoria(
            $chatId,
            $estado
        );
    }


    /* Reconoce en nutriente pero sin contexto */
    elseif ($detectados) {

        $estado = nuevoEstado();

        $estado['estado'] =
            'esperando_accion_nutriente';

        $estado['nutriente_1'] =
            $detectados[0];


        enviarMensaje(

            $chatId,

            "Identifiqué "
            . $nutrientes[$detectados[0]]['nombre']
            . ".\n\n"

            . "¿Qué quieres consultar sobre este nutriente?\n\n"

            . "• Fuentes naturales\n"
            . "• Función\n"
            . "• Compararlo con otro nutriente"
        );
    }


    /* Entrada no reconocida */
    else {

        errorIntento(

            $chatId,

            $estado,

            "No pude identificar tu consulta.\n\n"

            . "Puedes preguntarme, por ejemplo:\n"

            . "• ¿Qué alimentos contienen vitamina B12?\n"
            . "• ¿Para qué sirve la vitamina C?\n"
            . "• Quiero comparar vitamina C y vitamina B12.\n\n"

            . "También puedes usar /ayuda."
        );
    }


    guardarEstados($estados);
}


/* PROCESAR UPDATE DE TELEGRAM */

function procesarUpdate($update)
{
    if (!isset($update['message']['chat']['id'])) {
        return;
    }


    $chatId =
        $update['message']['chat']['id'];


    $texto =
        trim($update['message']['text'] ?? '');


    if ($texto === '') {
        $texto = '[mensaje vacío]';
    }


    procesarMensaje(
        $chatId,
        $texto
    );
}


/* LONG POLLING */

if (php_sapi_name() === 'cli') {

    echo "====================================\n";
    echo " NutriGuia iniciado\n";
    echo " Esperando mensajes de Telegram...\n";
    echo "====================================\n";


    $offset = 0;


    while (true) {

        $respuesta = telegram(

            'getUpdates',

            [
                'timeout' => 25,
                'offset'  => $offset
            ]
        );


        foreach (
            $respuesta['result'] ?? []
            as $update
        ) {

            $offset =
                $update['update_id'] + 1;


            procesarUpdate($update);
        }
    }
}


/* WEBHOOK */

else {

    $contenido =
        file_get_contents('php://input');


    $update =
        json_decode(
            $contenido,
            true
        );


    if (is_array($update)) {
        procesarUpdate($update);
    }
}
