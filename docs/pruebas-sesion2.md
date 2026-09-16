# Pruebas de Sesion 2

## Entorno

- PHP 8.4 con la extension cURL.
- Telegram Bot API mediante webhook HTTPS en el despliegue.
- Open-Meteo para geocodificacion y temperatura actual.
- API de OpenAI mediante HTTPS.
- Modelo configurado: `gpt-5.6-luna`.

## Pruebas funcionales

| Prueba | Entrada | Resultado esperado | Estado |
|---|---|---|---|
| Inicio | `/start` | Bienvenida y menu de botones | Verificado por webhook HTTPS |
| Ayuda | `/ayuda` | Opciones y comandos disponibles | Pendiente de evidencia Telegram |
| Agendamiento | `Agendar cita` y datos validos | Cita creada y numero asignado | Pendiente de evidencia Telegram |
| Relleno de datos | `Quiero una limpieza el jueves 11` | No vuelve a pedir servicio ni fecha | Pendiente de evidencia Telegram |
| Entrada invalida | Tres respuestas invalidas | Ayuda progresiva y derivacion a recepcion | Pendiente de evidencia Telegram |
| Cancelacion | Numero de cita | Muestra fecha/hora y solicita confirmacion | Pendiente de evidencia Telegram |
| Cancelacion segura | `No, conservar cita` | La cita permanece activa | Pendiente de evidencia Telegram |
| Control de flujo | `/cancelar`, `/volver`, `/ayuda` | Interrumpe o conserva el paso segun corresponda | Pendiente de evidencia Telegram |
| REST | `/clima San Salvador` | Temperatura y estado del cielo | Verificado contra Open-Meteo |
| REST con error | `/clima CiudadInexistente` | Mensaje claro, sin error tecnico | Pendiente de evidencia Telegram |
| Conversacion abierta | Pregunta no critica | Respuesta de OpenAI o fallback claro | API verificada; pendiente de evidencia Telegram |

## Comprobaciones de despliegue

```bash
php -l src/bot.php
php -l src/webhook.php
systemctl is-active nginx
systemctl is-active php8.4-fpm
grep OPENAI_MODEL /home/as22027/chatbot/.env
```

El token del bot y la clave de OpenAI se mantienen en `.env`. El estado, el offset y los logs se mantienen en
archivos locales excluidos por `.gitignore`.

## Webhook HTTPS

- URL: `https://as22027.duckdns.org/telegram-bot/bot.php`.
- El dominio resuelve al servidor público `147.182.162.20`, que reenvía tráfico a
  la VM mediante WireGuard.
- El certificado HTTPS es válido.
- El endpoint acepta solo POST y valida el encabezado secreto de Telegram.
- `getWebhookInfo` confirmó la URL, `max_connections=1` y cero updates pendientes.
- El servicio `citasbot` de long polling quedó deshabilitado e inactivo para evitar
  conflictos con el webhook.
