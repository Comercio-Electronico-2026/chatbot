# Pruebas y despliegue - Laboratorio 5B
- **Carnet:** LL22030
- **Rama:** `alumno/LL22030`
- **Bot:** ll22030Bot
- **Tienda:** [https://ll22030.duckdns.org](https://ll22030.duckdns.org)
- **Webhook:** [https://ll22030.duckdns.org/chatbot/src/bot.php](https://ll22030.duckdns.org/chatbot/src/bot.php)
- **HTTPS:** Certificado válido de Let's Encrypt
- **Respuesta del endpoint:** `HTTP/1.1 200 OK`
- **Actualizaciones pendientes:** `0`
## Pruebas realizadas en Telegram
| Prueba | Entrada | Resultado obtenido | Estado |
|---|---|---|---|
| Inicio | `/start` | Mostró bienvenida y opciones de catálogo, clima, agente y cancelación | OK |
| Consulta de laptops | `¿Tienen laptops?` | Indicó que hay laptops y ofreció ayuda para elegir | OK |
| Recomendación para estudiar | `¿Qué computadora recomiendas para estudiar?` | Recomendó i5/Ryzen 5, 8 GB de RAM y SSD de 256 GB | OK |
| Ayuda | `/ayuda` | Ofreció ayuda sobre computadoras y productos | OK |
| Volver | `/volver` | Permitió iniciar otra consulta | OK |
| Cancelar | `/cancelar` | Reconoció el comando, pero no indicó claramente que canceló | Parcial |
| Comando inexistente | `/comando_inexistente_123` | Informó que no reconocía el comando y ofreció ayuda | OK |
| Producto inexistente | `producto_inexistente_123` | Indicó que no estaba disponible y ofreció una alternativa | OK |
| Mensaje vacío | `[Mensaje vacío]` | No produjo un error técnico visible | OK |
| Recomendación para programación | `¿Recomiendas alguna computadora en específico para programación?` | Recomendó i5, 16 GB de RAM y SSD de 512 GB mediante OpenAI | OK |
| Cierre | `gracias` | Respondió cordialmente | OK |
## Verificación del despliegue
La tienda funciona en:
```text
https://ll22030.duckdns.org
```
El webhook funciona en:
```text
https://ll22030.duckdns.org/chatbot/src/bot.php
```
Comprobación realizada:
```bash
curl -i https://ll22030.duckdns.org/chatbot/src/bot.php
```
Resultado:
```text
HTTP/1.1 200 OK
```
El dominio utiliza HTTPS con un certificado válido de Let's Encrypt. Telegram mostró `0` actualizaciones pendientes.
Telegram conserva un mensaje histórico de `404 Not Found`, pero las pruebas actuales entregan `HTTP 200 OK` y el bot responde correctamente.
## Evidencia en los logs
Las pruebas quedaron registradas en:
```text
/home/ll22030/html_public/chatbot/logs/bot.log
```
Se registraron, entre otras, las entradas `/cancelar`, `/comando_inexistente_123`, `producto_inexistente_123`, la recomendación para programación y `gracias`.
## Integración con OpenAI
La pregunta sobre una computadora para programación fue respondida correctamente mediante la API de OpenAI.
La clave de OpenAI permanece únicamente en `.env` y no se incluye en el repositorio.
