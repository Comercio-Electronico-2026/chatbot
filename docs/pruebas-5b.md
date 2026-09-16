# Pruebas — Laboratorio 5b (Sesión 2)

Bot: asistente de tienda (BH23004). Las pruebas se hicieron contra el bot en
marcha (usuario de Telegram: se comparte en la entrega) y se repiten tras la
revisión entre pares. El log de entradas/salidas queda en
`($LOG_FILE|/tmp/tg-shop-bot.log)`.

## Cómo levantar el bot

- Desarrollo local (sin HTTPS): `php src/poll.php`
- Producción con webhook: `php src/setWebhook.php` (dominio
  `bh23004.duckdns.org` con Certbot del Laboratorio 3)

## Casos de prueba

| # | Caso | Entrada | Resultado esperado | ✔ |
| --- | --- | --- | --- | --- |
| 1 | Camino feliz pedido (diseño, diálogo de muestra) | `/start` → botón «Rastrear pedido» → `4821` | Mensaje de bienvenida con menú; «Tu pedido #4821 se encuentra En tránsito... Entrega programada...»; pregunta «¿Deseas consultar algo más?» | Ok |
| 2 | Slot filling pedido | `pedido 4821` (sin pasar por el botón) | Salta directo al resultado del pedido, sin pedir el dato | Ok |
| 3 | Slot filling otra frase | `¿dónde está mi pedido 5678?` | Mismo resultado directo | Ok |
| 4 | Formato inválido con reintentos | `abc`, luego `123`, luego `xyz` (estando en ASK_ORDER) | Salida 1: «Formato inválido... Intento 1 de 3»; intento 2 idem; al 3.º: «Se agotaron los 3 intentos. ¿Quieres que te derive con un asesor humano?» (Sí/No) | Ok |
| 5 | Pedido no registrado | `4820` (simulación: IDs terminados en 0) | «No encontré ningún pedido registrado... (Intento N de 3)», re-ask, no bucle infinito | Ok |
| 6 | Derivar a humano | «Sí» tras ver OFFER_HUMAN | Pregunta confirmación irreversibilidad del ticket; «Sí» → ticket `#T-xxxx` creado + enlace soporte | Ok |
| 7 | Rechazo de ticket irreversible | «Hablar con un asesor» → «No» | «Sin problema, no creé ningún ticket...» y regreso al menú | Ok |
| 8 | Catálogo por inline keyboard | «Consultar catálogo» → tap categoría «Electronics» | Teclado inline con categorías; al tap, lista de productos + precios; pregunta de cierre | Ok |
| 9 | Búsqueda de producto | `precio de teclado` (desde menú) | Lista coincidencias (usa diccionario ES→EN, encuentra «keyboards») | Ok |
| 10 | Producto no encontrado ×3 | `zzzzxxx` tres veces | «No encontré coincidencias...» con Intento N de 3 y al 3.º oferta de asesor | Ok |
| 11 | Cancelar a mitad de flujo | «Rastrear pedido» → luego `cancelar` | «Operación cancelada...» vuelve al menú (y lo mismo con /cancel y con «salir») | Ok |
| 12 | /help sin perder contexto | «Rastrear pedido» → `ayuda` | Muestra la ayuda Y re-pregunta el número de pedido | Ok |
| 13 | Cambio de tema a mitad de flujo | «Rastrear pedido» → `clima` | «¿Deseas cancelar la operación actual y atender tu nueva solicitud?»; «Sí» → pregunta la ciudad; «No» → retoma el pedido | Ok |
| 14 | Clima camino feliz | «Clima» → `San Salvador` | «En San Salvador, El Salvador hay X°C..., cielo despejado...» | Ok |
| 15 | Slot filling clima | `/clima Santa Ana` | Igual, directo | Ok |
| 16 | Ciudad inexistente | `Elfheiwn` | «No encontré la ciudad... (Intento N de 3)» | Ok |
| 17 | Ubicación Telegram | botón de ubicación en ASK_CITY | Usa lat/lon contra Open-Meteo («tu ubicación») | Ok |
| 18 | Mensaje vacío / no texto | stickers, mensajes vacíos | «No recibí texto. Usa el teclado o escribe /help» sin romper | Ok |
| 19 | Fuera de alcance (LLM híbrido) | «¿puedo pagar con cripto?» | Respuesta conversacional del LLM en español, máx 3 oraciones | Ok |
| 20 | LLM caído / sin API key | quitar `LLM_API_KEY` y probar 19 | «No pude procesar tu consulta con el asistente inteligente...» + sugerencia /help (nunca error 500) | Ok |
| 21 | Acción crítica por reglas (no LLM) | «confirmar pago», «cancelar mi pedido» | Se responde con reglas (confirmación o envío a asesor), no lo inventa el LLM | Ok |
| 22 | Comando inexistente | `/foo` | Lo trata como texto libre → LLM (o mensaje de respaldo si el LLM falla) | Ok |
| 23 | Datos minimizados | ver log y prompt enviado | El LLM solo recibe el texto de la consulta; el `BOT_TOKEN` y datos personales no viajan al modelo | Ok |
| 24 | Webhook HTTPS | `getWebhookInfo` | Estado «válido», URL https, sin `last_error_message` | Ok |

## Simular fallo de las APIs (caso «API no responde»)

- Catálogo/api clima: desconectar la red o bloquear el DNS;
  `CatalogClient`/`OpenMeteoClient` devuelven `null` → el router cae en
  `apiFail`: «No pude conectarme con el sistema en este momento. ¿Quieres que
  intente de nuevo?» con teclado Reintentar/Menú. Probado con conexión
  degradada: mensaje claro, sin 500, y «Reintentar» re-lista categorías o
  re-envía la consulta.
- Pedidos: los IDs terminados en 0 cubren la rama `OrderNotFound` (prueba #5).
