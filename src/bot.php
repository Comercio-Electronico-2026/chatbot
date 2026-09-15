<?php

/*
 * NutriGuía - Laboratorio 5b
 * Carnet: JO20004
 *
 * Funciones:
 * - /start y saludos
 * - /ayuda
 * - /volver
 * - /cancelar
 * - Comparar nutrientes
 * - Consultar fuentes naturales
 * - Consultar función de un nutriente
 * - Solicitar asesoría
 * - Máximo 3 intentos
 * - Slot filling
 * - Reutilización de datos
 * - Consultas fuera de alcance
 * - Logs
 * - API REST Open-Meteo
 * - Long polling
 * - Webhook
 */


/* =========================================================
   CONFIGURACIÓN
   ========================================================= */

$ROOT = dirname(__DIR__);

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
   DIRECTORIOS
   ========================================================= */

if (!is_dir($ROOT . '/data')) {
    mkdir($ROOT . '/data', 0775, true);
}

if (!is_dir($ROOT . '/logs')) {
    mkdir($ROOT . '/logs', 0775, true);
}


/* =========================================================
   DATOS NUTRICIONALES
   ========================================================= */

$nutrientes = [

    'vitamina b' => [
        'nombre' => 'Vitaminas del complejo B',

        'aliases' => [
            'vitamina b',
            'complejo b',
            'vitaminas b'
        ],

        'funcion' =>
            'El complejo B agrupa varias vitaminas que participan '
            . 'en diferentes procesos del metabolismo y del '
            . 'funcionamiento normal del organismo.',

        'fuentes' => [
            'Cereales integrales',
            'Legumbres',
            'Carnes'
        ]
    ],

    'vitamina b12' => [
        'nombre' => 'Vitamina B12',

        'aliases' => [
            'vitamina b12',
            'b12',
            'cobalamina'
        ],

        'funcion' =>
            'Participa en la formación normal de glóbulos rojos '
            . 'y en el funcionamiento normal del sistema nervioso.',

        'fuentes' => [
            'Pescados',
            'Carnes',
            'Huevos'
        ]
    ],

    'vitamina c' => [
        'nombre' => 'Vitamina C',

        'aliases' => [
            'vitamina c'
        ],

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

        'aliases' => [
            'vitamina d'
        ],

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

        'aliases' => [
            'vitamina k'
        ],

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

        'aliases' => [
            'hierro'
        ],

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

        'aliases' => [
            'calcio'
        ],

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
   ESTADOS
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
   TELEGRAM
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

    $codigo = curl_getinfo(
        $curl,
        CURLINFO_HTTP_CODE
    );

    curl_close($curl);

    if ($codigo < 200 || $codigo >= 300) {

        logBot(
            'ERROR',
            "Telegram respondió HTTP {$codigo}"
        );

        return [];
    }

    $json = json_decode($respuesta, true);

    return is_array($json) ? $json : [];
}


/* =========================================================
   TECLADOS
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
                ['text' => 'Consultar clima']
            ],

            [
                ['text' => 'Agendar asesoría'],
                ['text' => 'Ayuda']
            ],

            [
                ['text' => 'Cancelar']
            ],
        ],

        'resize_keyboard' => true
    ]);
}


function tecladoSiNo()
{
    return json_encode([
        'keyboard' => [

            [
                ['text' => 'Sí'],
                ['text' => 'No']
            ],

            [
                ['text' => 'Cancelar']
            ]
        ],

        'resize_keyboard' => true,
        'one_time_keyboard' => true
    ]);
}


/* =========================================================
   ENVIAR MENSAJES
   ========================================================= */

function enviarMensaje(
    $chatId,
    $texto,
    $menu = false,
    $siNo = false
) {

    logBot('OUT', $texto);

    $datos = [
        'chat_id' => $chatId,
        'text'    => $texto
    ];

    if ($menu) {
        $datos['reply_markup'] = tecladoPrincipal();
    }

    if ($siNo) {
        $datos['reply_markup'] = tecladoSiNo();
    }

    telegram(
        'sendMessage',
        $datos
    );
}


/* =========================================================
   TEXTO
   ========================================================= */

function normalizar($texto)
{
    $texto = mb_strtolower(
        trim($texto)
    );

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

    $texto = normalizar($texto);

    $encontrados = [];

    foreach ($nutrientes as $clave => $datos) {

        foreach ($datos['aliases'] as $alias) {

            $alias = normalizar($alias);

            $patron =
                '~(?<![\pL\pN])'
                . preg_quote($alias, '~')
                . '(?![\pL\pN])~u';

            if (preg_match($patron, $texto)) {

                $encontrados[] = $clave;

                break;
            }
        }
    }

    return array_values(
        array_unique($encontrados)
    );
}


/* =========================================================
   FUERA DE ALCANCE
   ========================================================= */

function esConsultaMedicaFueraDeAlcance($texto)
{
    $texto = normalizar($texto);

    $terminos = [
        'medicamento',
        'medicina',
        'tratamiento',
        'diagnostico',
        'dosis',
        'recetar',
        'receta',
        'enfermedad',
        'sintoma',
        'sintomas',
        'cuanto debo tomar',
        'cuantas pastillas',
        'que pastilla'
    ];

    foreach ($terminos as $termino) {

        if (str_contains($texto, $termino)) {
            return true;
        }
    }

    return false;
}


/* =========================================================
   MENÚ
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
        . "4. Consultar la temperatura de una ciudad.\n\n"
        . "5. Solicitar una asesoria nutricional.\n\n"

        . "Elige una opción del menú o escribe tu consulta directamente.\n\n"

        . "También puedes usar:\n"
        . "/clima San Salvador\n"
        . "/ayuda\n"
        . "/volver\n"
        . "/cancelar",

        true
    );
}


/* =========================================================
   FUENTES
   ========================================================= */

function responderFuentes($chatId, $nutriente)
{
    global $nutrientes;

    $datos = $nutrientes[$nutriente];

    $fuentes = $datos['fuentes'];

    enviarMensaje(
        $chatId,

        "Estas son algunas fuentes de "
        . $datos['nombre']
        . ":\n\n"

        . "1. {$fuentes[0]}\n"
        . "2. {$fuentes[1]}\n"
        . "3. {$fuentes[2]}\n\n"

        . "Esta información es educativa y no sustituye "
        . "una recomendación nutricional personalizada.\n\n"

        . "¿Quieres realizar otra consulta?",

        false,
        true
    );
}


/* =========================================================
   FUNCIÓN
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

        . "\n\n¿Quieres realizar otra consulta?",

        false,
        true
    );
}


/* =========================================================
   COMPARACIÓN
   ========================================================= */

function responderComparacion(
    $chatId,
    $n1,
    $n2
) {

    global $nutrientes;

    $dato1 = $nutrientes[$n1];
    $dato2 = $nutrientes[$n2];

    enviarMensaje(
        $chatId,

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

        . "\n\n¿Quieres realizar otra consulta?",

        false,
        true
    );
}


/* =========================================================
   ESPERAR CONTINUACIÓN
   ========================================================= */

function esperarContinuacion(&$estado)
{
    $estado['estado'] = 'esperando_continuar';

    $estado['intentos'] = 0;

    $estado['nutriente_1'] = null;
    $estado['nutriente_2'] = null;

    $estado['nombre'] = null;
    $estado['contacto'] = null;
}


/* =========================================================
   ERRORES
   ========================================================= */

function errorIntento(
    $chatId,
    &$estado,
    $mensaje
) {

    global $HUMAN_CONTACT;

    $estado['intentos']++;


    if ($estado['intentos'] === 1) {

        enviarMensaje(
            $chatId,

            $mensaje

            . "\n\nPrueba nuevamente siguiendo "
            . "el ejemplo mostrado."

            . "\n\nIntento 1 de 3."
        );

        return;
    }


    if ($estado['intentos'] === 2) {

        enviarMensaje(
            $chatId,

            "Todavía no pude identificar correctamente "
            . "el dato.\n\n"

            . "Puedes intentarlo nuevamente, usar /ayuda "
            . "o cancelar con /cancelar.\n\n"

            . "Intento 2 de 3."
        );

        return;
    }


    enviarMensaje(
        $chatId,

        "No pude completar la consulta después "
        . "de tres intentos.\n\n"

        . "Puedes volver al menú o solicitar "
        . "una asesoría con un profesional "
        . "de nutrición.\n\n"

        . "Contacto disponible: {$HUMAN_CONTACT}",

        true
    );


    $estado = nuevoEstado();
}


/* =========================================================
   ASESORÍA
   ========================================================= */

function iniciarAsesoria(
    $chatId,
    &$estado
) {

    $estado = nuevoEstado();

    $estado['estado'] =
        'esperando_nombre_asesoria';


    enviarMensaje(
        $chatId,

        "Claro. Para solicitar una asesoría "
        . "necesito algunos datos.\n\n"

        . "Primero, ¿cuál es tu nombre?"
    );
}


/* =========================================================
   API REST - HTTP GET
   ========================================================= */

function httpGetJson($url)
{
    $curl = curl_init($url);

    curl_setopt_array(
        $curl,
        [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 12,
            CURLOPT_FOLLOWLOCATION => true,

            CURLOPT_HTTPHEADER => [
                'Accept: application/json'
            ]
        ]
    );

    $respuesta = curl_exec($curl);


    if ($respuesta === false) {

        logBot(
            'ERROR',
            'API REST: '
            . curl_error($curl)
        );

        curl_close($curl);

        return null;
    }


    $codigo =
        curl_getinfo(
            $curl,
            CURLINFO_HTTP_CODE
        );


    curl_close($curl);


    if (
        $codigo < 200
        ||
        $codigo >= 300
    ) {

        logBot(
            'ERROR',
            "API REST respondió HTTP {$codigo}"
        );

        return null;
    }


    $datos =
        json_decode(
            $respuesta,
            true
        );


    if (!is_array($datos)) {

        logBot(
            'ERROR',
            'La API REST devolvió JSON inválido'
        );

        return null;
    }


    return $datos;
}


/* =========================================================
   API REST - OPEN-METEO
   ========================================================= */

function consultarClima($ciudad)
{
    /*
     * PASO 1:
     * Convertir nombre de ciudad
     * en latitud y longitud.
     */

    $urlGeocoding =

        'https://geocoding-api.open-meteo.com/v1/search?'

        . http_build_query([
            'name'     => $ciudad,
            'count'    => 1,
            'language' => 'es',
            'format'   => 'json'
        ]);


    $geo =
        httpGetJson(
            $urlGeocoding
        );


    if ($geo === null) {

        return [
            'ok'   => false,
            'tipo' => 'api'
        ];
    }


    if (empty($geo['results'][0])) {

        return [
            'ok'   => false,
            'tipo' => 'ciudad'
        ];
    }


    $lugar =
        $geo['results'][0];


    $latitud =
        $lugar['latitude'];

    $longitud =
        $lugar['longitude'];


    $nombre =
        $lugar['name']
        ?? $ciudad;


    $pais =
        $lugar['country']
        ?? '';


    /*
     * PASO 2:
     * Consultar clima actual.
     */

    $urlClima =

        'https://api.open-meteo.com/v1/forecast?'

        . http_build_query([
            'latitude'  => $latitud,
            'longitude' => $longitud,
            'current'   => 'temperature_2m',
            'timezone'  => 'auto'
        ]);


    $clima =
        httpGetJson(
            $urlClima
        );


    if ($clima === null) {

        return [
            'ok'   => false,
            'tipo' => 'api'
        ];
    }


    if (
        !isset(
            $clima['current']['temperature_2m']
        )
    ) {

        return [
            'ok'   => false,
            'tipo' => 'datos'
        ];
    }


    return [
        'ok'          => true,
        'ciudad'      => $nombre,
        'pais'        => $pais,
        'temperatura' =>
            $clima['current']['temperature_2m']
    ];
}


/* =========================================================
   RESPONDER CLIMA
   ========================================================= */

function responderClima(
    $chatId,
    $resultado
) {

    $ubicacion =
        $resultado['ciudad'];


    if (
        $resultado['pais'] !== ''
    ) {

        $ubicacion .=
            ', '
            . $resultado['pais'];
    }


    enviarMensaje(
        $chatId,

        "Temperatura actual en "
        . $ubicacion
        . ": "
        . $resultado['temperatura']
        . " °C.\n\n"

        . "Este dato proviene del servicio "
        . "externo Open-Meteo y es únicamente "
        . "informativo.\n\n"

        . "¿Quieres realizar otra consulta?",

        false,
        true
    );
}


/* =========================================================
   PROCESAR MENSAJE
   ========================================================= */

function procesarMensaje(
    $chatId,
    $texto
) {

    global
        $nutrientes,
        $HUMAN_CONTACT;


    $estados =
        cargarEstados();


    if (
        !isset(
            $estados[$chatId]
        )
    ) {

        $estados[$chatId] =
            nuevoEstado();
    }


    $estado =&
        $estados[$chatId];


    $textoNormal =
        normalizar($texto);


    /* =====================================================
       LOG DE ENTRADA
       ===================================================== */

    if (

        $estado['estado']
        ===
        'esperando_nombre_asesoria'

        ||

        $estado['estado']
        ===
        'esperando_contacto_asesoria'

    ) {

        logBot(
            'IN',
            '[dato personal oculto]'
        );

    } else {

        logBot(
            'IN',
            $texto
        );
    }


    /* =====================================================
       /START Y SALUDOS
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

        $estado =
            nuevoEstado();


        mostrarMenu(
            $chatId
        );


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       AYUDA
       ===================================================== */

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
            . "4. Solicitar una asesoría nutricional.\n"
            . "5. Consultar temperatura con /clima CIUDAD.\n\n"

            . "Ejemplo:\n"
            . "/clima San Salvador\n\n"

            . "Puedes usar /volver o /cancelar "
            . "en cualquier momento.",

            true
        );


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       VOLVER
       ===================================================== */

    if (

        $textoNormal === '/volver'
        ||
        $textoNormal === 'volver'

    ) {

        $estado =
            nuevoEstado();


        enviarMensaje(
            $chatId,

            "Regresamos al menú principal.",

            true
        );


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       CANCELAR
       ===================================================== */

    if (

        $textoNormal === '/cancelar'
        ||
        $textoNormal === 'cancelar'

    ) {

        $estado =
            nuevoEstado();


        enviarMensaje(
            $chatId,

            "Consulta cancelada.\n\n"

            . "Los datos temporales de esta operación "
            . "fueron descartados.\n\n"

            . "Puedes iniciar otra consulta cuando quieras.",

            true
        );


        guardarEstados(
            $estados
        );

        return;
    }

/* =====================================================
   BOTÓN CONSULTAR CLIMA
   ===================================================== */

if ($textoNormal === 'consultar clima') {

    $estado['estado'] =
        'esperando_ciudad_clima';

    $estado['intentos'] = 0;

    enviarMensaje(
        $chatId,

        "¿De qué ciudad quieres consultar "
        . "la temperatura actual?\n\n"

        . "Por ejemplo: San Salvador."
    );

    guardarEstados(
        $estados
    );

    return;
}

    /* =====================================================
       /CLIMA CIUDAD
       ===================================================== */

    if (
        str_starts_with(
            $textoNormal,
            '/clima'
        )
    ) {

        /*
         * Ejemplo:
         * /clima San Salvador
         */

        $ciudad =
            trim(
                preg_replace(
                    '/^\/clima\s*/iu',
                    '',
                    $texto
                )
            );


        /*
         * Si no proporcionó ciudad,
         * pedimos solamente ese dato.
         */

        if (
            $ciudad === ''
        ) {

            $estado['estado'] =
                'esperando_ciudad_clima';


            $estado['intentos'] = 0;


            enviarMensaje(
                $chatId,

                "¿De qué ciudad quieres consultar "
                . "la temperatura actual?\n\n"

                . "Por ejemplo: San Salvador."
            );


            guardarEstados(
                $estados
            );

            return;
        }


        $resultado =
            consultarClima(
                $ciudad
            );


        if (
            !$resultado['ok']
        ) {

            if (
                $resultado['tipo']
                ===
                'ciudad'
            ) {

                enviarMensaje(
                    $chatId,

                    "No encontré esa ciudad.\n\n"

                    . "Prueba escribiendo, por ejemplo:\n"

                    . "/clima San Salvador"
                );

            } else {

                enviarMensaje(
                    $chatId,

                    "No pude consultar el servicio "
                    . "de clima en este momento.\n\n"

                    . "Puedes intentarlo nuevamente más tarde."
                );
            }


            guardarEstados(
                $estados
            );

            return;
        }


        responderClima(
            $chatId,
            $resultado
        );


        esperarContinuacion(
            $estado
        );


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       CONSULTA FUERA DE ALCANCE
       ===================================================== */

    if (
        esConsultaMedicaFueraDeAlcance(
            $texto
        )
    ) {

        enviarMensaje(
            $chatId,

            "Puedo brindarte información educativa "
            . "sobre nutrientes, pero no puedo realizar "
            . "diagnósticos ni indicar medicamentos, "
            . "tratamientos o dosis.\n\n"

            . "Para este tipo de consulta debes acudir "
            . "a un profesional de salud o nutrición.\n\n"

            . "Si lo deseas, puedes seleccionar "
            . "\"Agendar asesoría\".",

            true
        );


        $estado =
            nuevoEstado();


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       ESTADO: ESPERANDO CIUDAD
       ===================================================== */

    if (
        $estado['estado']
        ===
        'esperando_ciudad_clima'
    ) {

        if (
            mb_strlen(
                trim($texto)
            )
            <
            2
        ) {

            errorIntento(
                $chatId,
                $estado,

                "Escribe el nombre de una ciudad.\n\n"
                . "Por ejemplo: San Salvador."
            );


            guardarEstados(
                $estados
            );

            return;
        }


        $resultado =
            consultarClima(
                trim($texto)
            );


        if (
            !$resultado['ok']
        ) {

            /*
             * Ciudad no encontrada:
             * cuenta como intento incorrecto.
             */

            if (
                $resultado['tipo']
                ===
                'ciudad'
            ) {

                errorIntento(
                    $chatId,
                    $estado,

                    "No encontré esa ciudad.\n\n"

                    . "Por ejemplo: "
                    . "San Salvador."
                );

            }

            /*
             * Si falla la API,
             * no culpamos al usuario ni
             * consumimos sus tres intentos.
             */

            else {

                enviarMensaje(
                    $chatId,

                    "El servicio de clima no está "
                    . "disponible en este momento.\n\n"

                    . "Inténtalo nuevamente más tarde.",

                    true
                );


                $estado =
                    nuevoEstado();
            }


            guardarEstados(
                $estados
            );

            return;
        }


        responderClima(
            $chatId,
            $resultado
        );


        esperarContinuacion(
            $estado
        );


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       CONTINUAR
       ===================================================== */

    if (
        $estado['estado']
        ===
        'esperando_continuar'
    ) {

        if (
            $textoNormal === 'si'
        ) {

            $estado =
                nuevoEstado();


            mostrarMenu(
                $chatId
            );


            guardarEstados(
                $estados
            );

            return;
        }


        if (

            $textoNormal === 'no'
            ||
            $textoNormal === 'salir'
            ||
            $textoNormal === 'finalizar'

        ) {

            $estado =
                nuevoEstado();


            enviarMensaje(
                $chatId,

                "Gracias por usar NutriGuía 🌿\n\n"

                . "Puedes volver cuando quieras "
                . "para realizar otra consulta."
            );


            guardarEstados(
                $estados
            );

            return;
        }


        errorIntento(
            $chatId,
            $estado,

            "Responde Sí para realizar otra consulta "
            . "o No para finalizar."
        );


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       CAMBIO A ASESORÍA
       ===================================================== */

    if (

        str_contains(
            $textoNormal,
            'agendar asesoria'
        )
        ||
        str_contains(
            $textoNormal,
            'solicitar asesoria'
        )
        ||
        str_contains(
            $textoNormal,
            'quiero una asesoria'
        )
        ||
        str_contains(
            $textoNormal,
            'quiero una cita'
        )

    ) {

        iniciarAsesoria(
            $chatId,
            $estado
        );


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       ESPERANDO FUENTE
       ===================================================== */

    if (
        $estado['estado']
        ===
        'esperando_fuente'
    ) {

        $detectados =
            detectarNutrientes(
                $texto
            );


        if ($detectados) {

            responderFuentes(
                $chatId,
                $detectados[0]
            );


            esperarContinuacion(
                $estado
            );

        } else {

            errorIntento(
                $chatId,
                $estado,

                "No reconocí ese nutriente.\n\n"

                . "Por ejemplo: vitamina B12, "
                . "vitamina C, hierro o calcio."
            );
        }


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       ESPERANDO FUNCIÓN
       ===================================================== */

    if (
        $estado['estado']
        ===
        'esperando_funcion'
    ) {

        $detectados =
            detectarNutrientes(
                $texto
            );


        if ($detectados) {

            responderFuncion(
                $chatId,
                $detectados[0]
            );


            esperarContinuacion(
                $estado
            );

        } else {

            errorIntento(
                $chatId,
                $estado,

                "No reconocí ese nutriente.\n\n"

                . "Por ejemplo: vitamina C, "
                . "vitamina D, hierro o calcio."
            );
        }


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       COMPARAR - PRIMER NUTRIENTE
       ===================================================== */

    if (
        $estado['estado']
        ===
        'esperando_nutriente_1'
    ) {

        $detectados =
            detectarNutrientes(
                $texto
            );


        if (
            count($detectados)
            >=
            2
        ) {

            responderComparacion(
                $chatId,
                $detectados[0],
                $detectados[1]
            );


            esperarContinuacion(
                $estado
            );

        }


        elseif (
            count($detectados)
            ===
            1
        ) {

            $estado['nutriente_1'] =
                $detectados[0];


            $estado['estado'] =
                'esperando_nutriente_2';


            $estado['intentos'] = 0;


            enviarMensaje(
                $chatId,

                "Ya tengo "
                . $nutrientes[
                    $detectados[0]
                ]['nombre']
                . ".\n\n"

                . "¿Con qué otro nutriente "
                . "quieres compararlo?\n\n"

                . "Por ejemplo: vitamina B12, "
                . "vitamina D o hierro."
            );

        }


        else {

            errorIntento(
                $chatId,
                $estado,

                "No reconocí ningún nutriente.\n\n"

                . "Por ejemplo: vitamina B "
                . "y vitamina B12."
            );
        }


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       COMPARAR - SEGUNDO NUTRIENTE
       ===================================================== */

    if (
        $estado['estado']
        ===
        'esperando_nutriente_2'
    ) {

        $detectados =
            detectarNutrientes(
                $texto
            );


        if (!$detectados) {

            errorIntento(
                $chatId,
                $estado,

                "No reconocí el segundo nutriente.\n\n"

                . "Por ejemplo: vitamina C, "
                . "vitamina D o hierro."
            );


            guardarEstados(
                $estados
            );

            return;
        }


        $segundo =
            $detectados[0];


        if (
            $segundo
            ===
            $estado['nutriente_1']
        ) {

            errorIntento(
                $chatId,
                $estado,

                "Ya seleccionaste ese nutriente.\n\n"

                . "Elige uno diferente "
                . "para realizar la comparación."
            );


            guardarEstados(
                $estados
            );

            return;
        }


        responderComparacion(
            $chatId,
            $estado['nutriente_1'],
            $segundo
        );


        esperarContinuacion(
            $estado
        );


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       NUTRIENTE YA IDENTIFICADO
       ===================================================== */

    if (
        $estado['estado']
        ===
        'esperando_accion_nutriente'
    ) {

        $nutriente =
            $estado['nutriente_1'];


        if (

            str_contains(
                $textoNormal,
                'fuente'
            )

            ||

            str_contains(
                $textoNormal,
                'natural'
            )

        ) {

            responderFuentes(
                $chatId,
                $nutriente
            );


            esperarContinuacion(
                $estado
            );

        }


        elseif (

            str_contains(
                $textoNormal,
                'funcion'
            )

            ||

            str_contains(
                $textoNormal,
                'sirve'
            )

            ||

            str_contains(
                $textoNormal,
                'beneficio'
            )

        ) {

            responderFuncion(
                $chatId,
                $nutriente
            );


            esperarContinuacion(
                $estado
            );

        }


        elseif (
            str_contains(
                $textoNormal,
                'compar'
            )
        ) {

            $estado['estado'] =
                'esperando_nutriente_2';


            $estado['intentos'] = 0;


            enviarMensaje(
                $chatId,

                "Perfecto. Ya tengo "
                . $nutrientes[
                    $nutriente
                ]['nombre']
                . ".\n\n"

                . "¿Con qué otro nutriente "
                . "quieres compararlo?"
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


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       ASESORÍA - NOMBRE
       ===================================================== */

    if (
        $estado['estado']
        ===
        'esperando_nombre_asesoria'
    ) {

        if (
            mb_strlen(
                trim($texto)
            )
            <
            2
        ) {

            errorIntento(
                $chatId,
                $estado,

                "El nombre parece incompleto."
            );

        } else {

            $estado['nombre'] =
                trim($texto);


            $estado['estado'] =
                'esperando_contacto_asesoria';


            $estado['intentos'] = 0;


            enviarMensaje(
                $chatId,

                "Gracias.\n\n"

                . "Ahora indícame un medio "
                . "de contacto para continuar "
                . "con la solicitud."
            );
        }


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       ASESORÍA - CONTACTO
       ===================================================== */

    if (
        $estado['estado']
        ===
        'esperando_contacto_asesoria'
    ) {

        if (
            mb_strlen(
                trim($texto)
            )
            <
            4
        ) {

            errorIntento(
                $chatId,
                $estado,

                "El dato de contacto parece incompleto."
            );

        } else {

            $estado['contacto'] =
                trim($texto);


            $estado['estado'] =
                'confirmando_asesoria';


            $estado['intentos'] = 0;


            enviarMensaje(
                $chatId,

                "Ya tengo los datos necesarios.\n\n"

                . "¿Confirmas que deseas "
                . "solicitar la asesoría?\n\n"

                . "Responde Sí o No.",

                false,
                true
            );
        }


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       ASESORÍA - CONFIRMACIÓN
       ===================================================== */

    if (
        $estado['estado']
        ===
        'confirmando_asesoria'
    ) {

        if (
            $textoNormal
            ===
            'si'
        ) {

            enviarMensaje(
                $chatId,

                "Solicitud confirmada ✅\n\n"

                . "Puedes continuar la atención con "
                . "{$HUMAN_CONTACT}.\n\n"

                . "¿Quieres realizar otra consulta?",

                false,
                true
            );


            esperarContinuacion(
                $estado
            );

        }


        elseif (
            $textoNormal
            ===
            'no'
        ) {

            $estado =
                nuevoEstado();


            enviarMensaje(
                $chatId,

                "Solicitud cancelada.\n\n"

                . "Los datos temporales "
                . "fueron descartados.\n\n"

                . "Puedes iniciar otra consulta "
                . "cuando quieras.",

                true
            );

        }


        else {

            errorIntento(
                $chatId,
                $estado,

                "Para confirmar la solicitud "
                . "responde Sí o No."
            );
        }


        guardarEstados(
            $estados
        );

        return;
    }


    /* =====================================================
       DETECTAR INTENCIÓN
       ===================================================== */

    $detectados =
        detectarNutrientes(
            $texto
        );


    /* COMPARAR */

    if (
        str_contains(
            $textoNormal,
            'compar'
        )
    ) {

        if (
            count($detectados)
            >=
            2
        ) {

            responderComparacion(
                $chatId,
                $detectados[0],
                $detectados[1]
            );


            esperarContinuacion(
                $estado
            );

        }


        elseif (
            count($detectados)
            ===
            1
        ) {

            $estado['nutriente_1'] =
                $detectados[0];


            $estado['estado'] =
                'esperando_nutriente_2';


            $estado['intentos'] = 0;


            enviarMensaje(
                $chatId,

                "Ya tengo "
                . $nutrientes[
                    $detectados[0]
                ]['nombre']
                . ".\n\n"

                . "¿Con qué otro nutriente "
                . "quieres compararlo?"
            );

        }


        else {

            $estado['estado'] =
                'esperando_nutriente_1';


            $estado['intentos'] = 0;


            enviarMensaje(
                $chatId,

                "¿Qué dos nutrientes quieres comparar?\n\n"

                . "Por ejemplo: vitamina B "
                . "y vitamina B12."
            );
        }
    }


    /* FUENTES */

    elseif (

        str_contains(
            $textoNormal,
            'fuente'
        )

        ||

        str_contains(
            $textoNormal,
            'alimento'
        )

        ||

        str_contains(
            $textoNormal,
            'natural'
        )

        ||

        str_contains(
            $textoNormal,
            'contiene'
        )

        ||

        str_contains(
            $textoNormal,
            'obtener'
        )

    ) {

        if ($detectados) {

            responderFuentes(
                $chatId,
                $detectados[0]
            );


            esperarContinuacion(
                $estado
            );

        } else {

            $estado['estado'] =
                'esperando_fuente';


            $estado['intentos'] = 0;


            enviarMensaje(
                $chatId,

                "¿Qué vitamina o nutriente "
                . "quieres consultar?\n\n"

                . "Por ejemplo: vitamina B12, "
                . "vitamina C, hierro o calcio."
            );
        }
    }


    /* FUNCIÓN */

    elseif (

        str_contains(
            $textoNormal,
            'funcion'
        )

        ||

        str_contains(
            $textoNormal,
            'sirve'
        )

        ||

        str_contains(
            $textoNormal,
            'beneficio'
        )

    ) {

        if ($detectados) {

            responderFuncion(
                $chatId,
                $detectados[0]
            );


            esperarContinuacion(
                $estado
            );

        } else {

            $estado['estado'] =
                'esperando_funcion';


            $estado['intentos'] = 0;


            enviarMensaje(
                $chatId,

                "¿De qué nutriente quieres "
                . "conocer su función?\n\n"

                . "Por ejemplo: vitamina C, "
                . "vitamina D, hierro o calcio."
            );
        }
    }


    /* ASESORÍA */

    elseif (

        str_contains(
            $textoNormal,
            'asesoria'
        )

        ||

        str_contains(
            $textoNormal,
            'cita'
        )

    ) {

        iniciarAsesoria(
            $chatId,
            $estado
        );
    }


    /* NUTRIENTE SIN INTENCIÓN */

    elseif ($detectados) {

        $estado =
            nuevoEstado();


        $estado['estado'] =
            'esperando_accion_nutriente';


        $estado['nutriente_1'] =
            $detectados[0];


        enviarMensaje(
            $chatId,

            "Identifiqué "
            . $nutrientes[
                $detectados[0]
            ]['nombre']
            . ".\n\n"

            . "¿Qué quieres consultar "
            . "sobre este nutriente?\n\n"

            . "• Fuentes naturales\n"
            . "• Función\n"
            . "• Compararlo con otro nutriente"
        );
    }


    /* NO RECONOCIDO */

    else {

        errorIntento(
            $chatId,
            $estado,

            "No pude identificar tu consulta.\n\n"

            . "Puedes preguntarme, por ejemplo:\n"

            . "• ¿Qué alimentos contienen vitamina B12?\n"
            . "• ¿Para qué sirve la vitamina C?\n"
            . "• Quiero comparar vitamina B y vitamina B12.\n\n"

            . "También puedes usar /ayuda."
        );
    }


    guardarEstados(
        $estados
    );
}


/* =========================================================
   PROCESAR UPDATE
   ========================================================= */

function procesarUpdate($update)
{
    if (
        !isset(
            $update['message']['chat']['id']
        )
    ) {
        return;
    }


    $chatId =
        $update['message']['chat']['id'];


    $texto =
        trim(
            $update['message']['text']
            ?? ''
        );


    if ($texto === '') {
        $texto = '[mensaje vacío]';
    }


    procesarMensaje(
        $chatId,
        $texto
    );
}


/* =========================================================
   LONG POLLING
   ========================================================= */

if (
    php_sapi_name()
    ===
    'cli'
) {

    echo "====================================\n";
    echo " NutriGuia iniciado\n";
    echo " Esperando mensajes de Telegram...\n";
    echo "====================================\n";


    $offset = 0;


    while (true) {

        $respuesta =
            telegram(
                'getUpdates',
                [
                    'timeout' => 25,
                    'offset'  => $offset
                ]
            );


        foreach (
            $respuesta['result']
            ?? []
            as $update
        ) {

            $offset =
                $update['update_id']
                + 1;


            procesarUpdate(
                $update
            );
        }
    }
}


/* =========================================================
   WEBHOOK
   ========================================================= */

else {

    $contenido =
        file_get_contents(
            'php://input'
        );


    $update =
        json_decode(
            $contenido,
            true
        );


    if (
        is_array($update)
    ) {

        procesarUpdate(
            $update
        );
    }
}
