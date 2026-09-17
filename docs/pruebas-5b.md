# Pruebas del bot — Laboratorio 5b, MT23014

Fecha de verificación: 16 de septiembre de 2026.
Entorno: Debian 13, PHP 8.4, Nginx y PHP-FPM.
Bot: `@TiendaElectronicaCETBot`.

## Validación del flujo

Se ejecutaron **64 comprobaciones automatizadas** con datos de prueba. Todas
pasaron. Las comprobaciones del flujo utilizaron respuestas controladas para
validar los escenarios de conversación y fallo sin depender de los proveedores.

| Área | Comportamiento verificado |
|---|---|
| Camino feliz | Bienvenida, menú, catálogo, selección de producto, precio, enlace de compra y cierre. |
| Datos y selección | Reutilización del nombre recibido, nombres ambiguos, categorías, ofertas y validación de IDs. |
| Navegación global | Ayuda, cancelación, volver y atención desde los estados de conversación. |
| Reparación | Tope de tres intentos, comandos desconocidos, producto inexistente y formatos no admitidos. |
| Alcance | Pedidos y pagos derivados al sitio de la tienda; consultas abiertas enrutadas al servicio de lenguaje. |
| Fallos externos | Mensajes claros ante errores del catálogo o del servicio de lenguaje; catálogo operativo cuando falla este último. |
| Privacidad | Correo y teléfono identificados antes del envío al servicio externo; logs sin texto libre ni IDs de chat. |
| Estado temporal | Persistencia entre solicitudes, actualización duplicada, actualización fuera de orden, expiración y limpieza. |
| Formato de datos | Precios según unidades menores de moneda y extracción del texto de Responses API. |
| Configuración | Actualización de credenciales durante reinstalación, conservación del secreto del webhook y datos fuera del acceso público. |

## Recepción HTTP

El webhook se verificó con configuración y actualizaciones de prueba:

| Solicitud | HTTP observado |
|---|---:|
| GET | 405 |
| POST sin secreto | 403 |
| POST autenticado con JSON inválido | 400 |
| POST autenticado sin ID de actualización | 400 |
| POST autenticado con actualización sin mensaje de chat | 200 |

Estas pruebas no enviaron mensajes a usuarios. La revisión de sintaxis de los
cuatro archivos PHP del bot pasó.

## Integraciones reales

Las verificaciones de integración utilizaron la configuración privada de la VM.
No se imprimieron ni registraron claves.

| Comprobación | Resultado observado |
|---|---|
| `php src/bot.php --check` | Variables requeridas configuradas, catálogo con cuatro productos y Telegram identificado como `@TiendaElectronicaCETBot`. |
| WooCommerce Store API | JSON de productos desde `https://mt23014.duckdns.org/index.php?rest_route=/wc/store/v1/products`. |
| `php src/bot.php --check-ai` | El endpoint de lenguaje devolvió una respuesta usando el catálogo público real. |

La prueba del endpoint de lenguaje verifica autenticación, conexión y respuesta.
No evalúa por sí sola la exactitud de todas las respuestas del modelo.

## Despliegue HTTPS

La validación de Nginx pasó y el webhook quedó registrado:

```text
https://mt23014.duckdns.org/telegram/bot.php
```

| Comprobación posterior | Resultado observado |
|---|---|
| `php src/bot.php --webhook-info` | URL correcta, cero actualizaciones en cola y sin campos de error de entrega. |
| Servicios | Nginx y PHP 8.4-FPM activos. |
| GET público con verificación TLS | 405. |
| POST público sin secreto | 403. |
| Limpieza | Cron cada 15 minutos con el usuario de PHP-FPM. |

La comprobación del endpoint incluye reintentos para esperar la recarga de Nginx.
Las tres pruebas de despliegue pasaron: 404 transitorio seguido de 405, rechazo de
404 persistente y recuperación de un fallo de conexión transitorio.

El estado de conversación expira después de 30 minutos de inactividad; la
limpieza elimina archivos vencidos y logs con más de siete días. La aplicación,
las credenciales y los datos de ejecución se encuentran fuera del acceso público.

Estas evidencias verifican el código, las integraciones, el registro del webhook
y su accesibilidad. La evaluación de usabilidad y la revisión entre pares se
realizan sobre el bot en marcha. El enlace de atención abre el sitio de la tienda;
no hay transferencia automática a un operador.
