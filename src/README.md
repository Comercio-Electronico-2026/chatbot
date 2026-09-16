# src/

Código del bot de Telegram (Lab 5b). Cada alumno trabaja en su rama
`alumno/<CARNET>`. El token va en `.env` (nunca se sube).

## Configuración local

Copiar `.env.example` como `.env` y completar `BOT_TOKEN`. El modelo local se
configura con `OLLAMA_URL` y `OLLAMA_MODEL`. Para webhook también se requiere un
`WEBHOOK_SECRET` aleatorio. El bot usa reglas para las acciones críticas de citas
y Ollama solo para consultas abiertas.

## Ejecución

```bash
php src/bot.php
```

El comando anterior se usa para desarrollo con long polling. En despliegue, Nginx
y PHP-FPM ejecutan `src/webhook.php` mediante la ruta pública:

```text
https://as22027.duckdns.org/telegram-bot/bot.php
```

Los dos modos reutilizan las funciones de `src/bot.php`, pero no deben estar
activos al mismo tiempo. El estado y los logs se guardan en `.runtime/`, y el
offset del polling en `.offset`; todos están excluidos de Git.
