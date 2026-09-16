# Pruebas de Sesion 2

## Entorno

- PHP 8.4 con la extension cURL.
- Telegram Bot API mediante long polling durante el desarrollo.
- Open-Meteo para geocodificacion y temperatura actual.
- Ollama local en `http://127.0.0.1:11434`.
- Modelo configurado: `qwen2.5:0.5b`.

## Pruebas funcionales

| Prueba | Entrada | Resultado esperado | Estado |
|---|---|---|---|
| Inicio | `/start` | Bienvenida y menu de botones | Pendiente de evidencia Telegram |
| Ayuda | `/ayuda` | Opciones y comandos disponibles | Pendiente de evidencia Telegram |
| Agendamiento | `Agendar cita` y datos validos | Cita creada y numero asignado | Pendiente de evidencia Telegram |
| Relleno de datos | `Quiero una limpieza el jueves 11` | No vuelve a pedir servicio ni fecha | Pendiente de evidencia Telegram |
| Entrada invalida | Tres respuestas invalidas | Ayuda progresiva y derivacion a recepcion | Pendiente de evidencia Telegram |
| Cancelacion | Numero de cita | Muestra fecha/hora y solicita confirmacion | Pendiente de evidencia Telegram |
| Cancelacion segura | `No, conservar cita` | La cita permanece activa | Pendiente de evidencia Telegram |
| Control de flujo | `/cancelar`, `/volver`, `/ayuda` | Interrumpe o conserva el paso segun corresponda | Pendiente de evidencia Telegram |
| REST | `/clima San Salvador` | Temperatura y estado del cielo | Verificado contra Open-Meteo |
| REST con error | `/clima CiudadInexistente` | Mensaje claro, sin error tecnico | Pendiente de evidencia Telegram |
| Conversacion abierta | Pregunta no critica | Respuesta de Ollama o fallback claro | Verificado contra API local |

## Comprobaciones de despliegue

```bash
php -l src/bot.php
systemctl is-active ollama
systemctl is-active citasbot
curl http://127.0.0.1:11434/api/tags
```

El token se mantiene en `.env`. El estado, el offset y los logs se mantienen en
archivos locales excluidos por `.gitignore`.

Durante desarrollo se usa long polling. El webhook HTTPS queda como etapa posterior
cuando este disponible el dominio y certificado del Laboratorio 3.
