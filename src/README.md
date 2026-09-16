# src/ — Bot de Telegram (Lab 5b)

Bot del asistente de tienda para Comercio Electrónico (CET 115). Implementa el
diseño conversacional de `docs/diseno-conversacional.md` (Sesión 1, rama
`alumno/BH23004`) con arquitectura híbrida: reglas fijas para las acciones
críticas y un LLM (OpenAI, endpoint OpenAI-compatible) solo para la conversación
abierta.

## Requisitos

- PHP >= 8.0 con la extensión `php-curl` (`apt install php-cli php-curl`)
- Token del bot en `.env` (copia `.env.example`), NUNCA en el repositorio

## Ejecución en desarrollo (long polling)

```bash
cp .env.example .env        # y rellenar BOT_TOKEN
php src/poll.php
```

`poll.php` usa `getUpdates`, no necesita HTTPS. Nada más de instalar nada
adicional: solo PHP y curl.

## Despliegue con webhook (producción)

1. Dominio con HTTPS válido. Este laboratorio reutiliza el dominio del
   Laboratorio 3 (`bh23004.duckdns.org`) con certificado Let's Encrypt / Certbot:

   ```bash
   sudo certbot --nginx -d bh23004.duckdns.org
   ```

2. Nginx sirve el repositorio como raíz; el webhook queda en
   `https://bh23004.duckdns.org/src/index.php`.

   ```nginx
   location ~ \.php$ {
       include snippets/fastcgi-php.conf;
       fastcgi_pass unix:/run/php/php8.2-fpm.sock;
   }
   ```

3. Registra el webhook (lo hace `setWebhook.php`, que además llama a
   `getWebhookInfo` para verificar):

   ```bash
   php src/setWebhook.php
   ```

4. Long polling y webhook no pueden estar activos a la vez (el otro recibe
   error 409). Para pasar a long polling: `deleteWebhook` contra la Bot API.

Seguridad del webhook: si `WEBHOOK_SECRET` está definida en `.env`,
`setWebhook.php` la registra como `secret_token` e `index.php` rechaza
(`403`) toda actualización que no traiga el encabezado
`X-Telegram-Bot-Api-Secret-Token` correcto.

## Estructura

| Archivo | Rol |
| --- | --- |
| `bootstrap.php` | Carga `.env` y todas las clases |
| `lib/Env.php` | Lector del archivo `.env` |
| `lib/Log.php` | Log local de entradas/salidas/errores (archivo configurable con `LOG_FILE`) |
| `lib/TelegramClient.php` | Llamadas a la Bot API: `sendMessage`, `answerCallbackQuery`, teclados (menú, sí/no, inline) |
| `lib/Session.php` | Estado conversacional por `chat_id` (JSON en `/tmp`): estado, contador de intentos, contexto |
| `lib/PedidosClient.php` | Seguimiento de pedidos de 4 dígitos (simulación local determinista, mimetiza la API de Pedidos del diseño) |
| `lib/CatalogClient.php` | Catálogo vía REST (FakeStoreAPI) con búsqueda ES→EN y manejo de fallo |
| `lib/OpenMeteoClient.php` | Clima vía REST (Open-Meteo): geocodificación + forecast actual |
| `lib/LLMClient.php` | LLM por endpoint OpenAI-compatible (`chat/completions`) |
| `lib/Router.php` | Máquina de estados: intenciones, slot filling, reintentos, cambio de tema, soporte |
| `lib/App.php` | Fábrica de `TelegramClient` y `Router` |
| `index.php` | Entrada del webhook (lee `php://input`, responde 200 de inmediato) |
| `poll.php` | Entrada por long polling (desarrollo local) |
| `setWebhook.php` | Registra el webhook y muestra `getWebhookInfo` |

## Modelo pedidos

No hay API real de pedidos; `PedidosClient` la simula de forma determinista para
poder probar todas las ramas del diagrama:

- IDs de 4 dígitos y NO terminados en 0: pedido existe con estado según
  `id % 4` (Recibido / En preparación / En tránsito / Entregado) y fecha
  estimada (`id % 3 + 1` días; fecha pasada si Entregado).
- IDs terminados en 0 o fuera de 1000–9999: «no registrado» (rama OrderNotFound).

Cuando exista la API real de la tienda basta reemplazar esta clase manteniendo
la misma interfaz.

## Catálogo

Servicio REST externo público FakeStoreAPI (`https://fakestoreapi.com`), sin
clave. Sus títulos están en inglés, por eso `CatalogClient::search()` revisa
primero el texto tal cual y si no hay coincidencia traduce palabra por palabra
con un mini diccionario ES→EN (teclado→keyboard, monitores→monitor...). Las
categorías del catálogo se muestran como teclado inline; también se puede
buscar por texto libre.

## LLM híbrido

- Reglas fijas (auditable): `/start`, `/help`, `/cancel`, pedidos, catálogo,
  clima, soporte, cambio de tema. Todo intento, envío y error queda en el log.
- Solo lo que no calza con ninguna intención va al LLM (`LLMClient`), que
  recibe el texto del usuario y un system prompt que le prohíbe inventar
  seguimientos o precios y de redirigir acciones críticas al asesor humano.
- El bot NUNCA envía al modelo: el `BOT_TOKEN`, la `LLM_API_KEY` ni datos
  personales adicionales (solo el texto de la consulta).
- Si el LLM falla (sin red, sin API key, HTTP >= 400), responde un mensaje de
  respaldo apuntando a `/help`; nunca queda colgado.

## Buenas prácticas aplicadas

- Token y API key en `.env` (`.gitignore` lo excluye; `*.log` también excluye los logs).
- Log local de todo lo que entra y sale (`Log.php`).
- Validación de entradas: pedido = 4 dígitos exactos; producto = texto limpio;
  ciudad = texto; ubicación Telegram como tipo de dato soportado.
- Confirmación explícita antes de la única acción irreversible (crear ticket:
  estado `CONFIRM_SUPPORT` con teclado Sí/No).
- Sin error 500 al usuario: si una API o el LLM fallan, hay mensaje claro y
  opción de reintentar o volver al menú.
- Límites de Telegram respetados: el bot responde 1:1 por chat (con webhook
  responde `200` de inmediato vía `fastcgi_finish_request` para evitar retrys),
  y los mensajes al mismo chat van uno a uno.
