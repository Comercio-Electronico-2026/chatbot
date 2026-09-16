# Pruebas del Laboratorio 5b - MusicHub Bot

## 1. Información general

- **Bot:** MusicHub Bot
- **Plataforma:** Telegram
- **Lenguaje:** PHP
- **Tienda:** MusicHub
- **Dominio:** `https://tienda.hv21011.duckdns.org`
- **Rama:** `alumno/HV21011`
- **Servicio REST:** WooCommerce REST API y WooCommerce Store API
- **Modelo de lenguaje:** Groq mediante endpoint compatible con OpenAI
- **Modelo utilizado:** `openai/gpt-oss-20b`
- **Despliegue:** Webhook HTTPS
- **Canal de soporte humano:** `hv21011@ues.edu.sv`

MusicHub Bot utiliza un patrón híbrido. Las funciones relacionadas con catálogo, pedidos, ayuda y soporte se mantienen bajo reglas controladas en PHP. Las consultas abiertas que no corresponden a estas operaciones pueden ser enviadas al modelo de lenguaje.

---

## 2. Inicio y menú principal

### Objetivo

Comprobar que el bot responde al comando `/start`, reinicia cualquier estado anterior y muestra las funciones principales.

### Entrada

```text
/start
```

### Resultado esperado

El bot debe saludar al usuario y mostrar los comandos principales.

### Resultado obtenido

**Correcto.**

El bot muestra un mensaje similar a:

```text
¡Hola, {nombre}! Qué gusto verte por MusicHub Bot 🎵.

Puedo ayudarte a buscar discos y vinilos o consultar
el estado de un pedido.

🛒 /catalogo - Buscar discos y vinilos
📦 /pedido - Consultar un pedido
❓ /ayuda - Ver las opciones disponibles
👤 /soporte - Atención humana
🚪 /salir - Terminar
```

---

## 3. Consulta de catálogo

### 3.1 Consulta mediante flujo conversacional

### Entrada

```text
/catalogo
```

### Resultado esperado

El bot debe solicitar el nombre del artista o álbum.

### Continuación

```text
Chloe
```

### Resultado obtenido

**Correcto.**

El bot encontró un producto cuyo atributo de artista contiene parcialmente el término ingresado.

Ejemplo:

```text
Ungodly Hour (Chrome Edition) – Vinilo
Artista: Chloe x Halle
```

Esto comprueba que la búsqueda permite coincidencias parciales por artista.

---

### 3.2 Consulta directa

### Entrada

```text
/catalogo Chloe
```

### Resultado esperado

El bot debe reutilizar el término proporcionado y realizar la búsqueda sin solicitarlo nuevamente.

### Resultado obtenido

**Correcto.**

La respuesta muestra información disponible del producto, incluyendo:

- Artista.
- Año.
- Formato.
- Género.
- Idioma.
- Precio.
- Disponibilidad.

---

### 3.3 Producto inexistente

### Entrada

```text
/catalogo ArtistaQueNoExiste123
```

### Resultado esperado

El bot debe informar que no encontró productos relacionados y permitir otro intento.

### Resultado obtenido

**Correcto.**

El flujo continúa permitiendo que el usuario proporcione otro término de búsqueda.

---

## 4. Consulta de pedidos

### 4.1 Pedido existente

### Entrada

```text
/pedido 17
```

### Resultado esperado

El bot debe reutilizar el número proporcionado y consultar directamente WooCommerce.

### Resultado obtenido

**Correcto.**

Respuesta obtenida:

```text
📦 Pedido #17

Estado: En espera
```

WooCommerce devolvió el estado `on-hold`, que el bot presenta al usuario como `En espera`.

---

### 4.2 Consulta mediante flujo conversacional

### Entrada

```text
/pedido
```

### Resultado esperado

El bot debe solicitar el identificador del pedido.

### Continuación

```text
17
```

### Resultado obtenido

**Correcto.**

El bot realiza la consulta y muestra el estado del pedido.

---

### 4.3 Identificador inválido

### Entrada de prueba

```text
abc
```

### Resultado esperado

El bot debe indicar que el identificador debe contener solamente números.

### Resultado obtenido

**Correcto.**

El sistema valida que el número:

- no esté vacío;
- contenga solamente números;
- sea un entero positivo.

No se exige una longitud fija debido a que el identificador es generado por WooCommerce.

---

### 4.4 Pedido inexistente

### Entrada

```text
/pedido 999999
```

### Resultado esperado

El bot debe indicar que no encontró el pedido y permitir otro intento.

### Resultado obtenido

**Correcto.**

El bot diferencia un pedido inexistente de un fallo técnico en la API.

---

## 5. Cambio de intención

### Objetivo

Comprobar que el bot solicita confirmación antes de abandonar una operación pendiente.

### Secuencia

```text
/catalogo
/pedido 17
```

### Resultado esperado

El bot debe detectar que existe una búsqueda de catálogo pendiente y preguntar si se desea cambiar de intención.

### Resultado obtenido

**Correcto.**

El bot muestra un mensaje similar a:

```text
Estabas buscando en el catálogo.

¿Deseas cambiar a la consulta de pedidos?
```

Además, muestra botones:

```text
Sí
No
```

### Opción Sí

Al seleccionar `Sí`, el bot cambia a la consulta de pedidos.

El número `17` ya proporcionado se reutiliza y no vuelve a solicitarse.

**Resultado: Correcto.**

### Opción No

Al seleccionar `No`, el bot conserva la operación anterior y continúa solicitando el artista o álbum.

**Resultado: Correcto.**

Después de seleccionar una opción, los botones anteriores son eliminados para evitar que una confirmación antigua vuelva a utilizarse.

---

## 6. Ayuda durante un flujo

### Objetivo

Comprobar que `/ayuda` puede utilizarse mientras existe una operación pendiente sin perder el estado de la conversación.

### Secuencia de prueba

```text
/pedido
/ayuda
17
```

### Resultado esperado

El comando `/ayuda` debe mostrar las opciones disponibles y posteriormente permitir continuar con el número solicitado.

### Resultado

La lógica del bot conserva el flujo pendiente después de utilizar `/ayuda`.

---

## 7. Volver al menú

### Entrada

```text
/menu
```

### Resultado esperado

El bot debe cancelar el flujo actual, eliminar el estado temporal y regresar al menú principal.

### Resultado obtenido

**Correcto.**

---

## 8. Finalizar la interacción

### Entrada

```text
/salir
```

### Resultado esperado

El bot debe finalizar el flujo actual y limpiar el estado temporal.

### Resultado obtenido

**Correcto.**

Ejemplo:

```text
¡Hasta luego, {nombre}! 🎵

Gracias por usar MusicHub Bot.
Puedes escribir /start cuando quieras volver.
```

---

## 9. Entradas no reconocidas y límite de intentos

### Objetivo

Evitar ciclos infinitos cuando el usuario proporciona entradas incorrectas o comandos inexistentes.

### Ejemplo

```text
/x
/y
/z
```

### Resultado esperado

Durante los primeros intentos, el bot debe informar que la opción no fue reconocida.

Después del tercer intento debe ofrecer:

```text
/menu
/ayuda
/soporte
```

El límite de tres intentos también se utiliza en los flujos de catálogo y pedidos cuando el usuario proporciona datos incorrectos.

Los fallos provocados por servicios externos no consumen intentos, debido a que no son errores cometidos por el usuario.

### Estado

**Correcto.**

---

## 10. Atención humana

### Entrada

```text
/soporte
```

### Resultado esperado

El bot debe proporcionar un canal real de atención humana y no fingir una transferencia automática.

### Resultado obtenido

**Correcto.**

Canal configurado:

```text
hv21011@ues.edu.sv
```

También se informa al usuario que no debe enviar contraseñas ni información bancaria.

---

## 11. Fallo controlado del servicio REST

### Objetivo

Comprobar que el bot continúa funcionando cuando el servicio externo no se encuentra disponible.

### Procedimiento

La URL utilizada para consultar el catálogo puede ser configurada opcionalmente mediante la variable:

```text
STORE_API_URL
```

Para simular un fallo se agregó temporalmente al archivo `.env`:

```text
STORE_API_URL=https://127.0.0.1:9/wp-json/wc/store/v1/products
```

Posteriormente se realizó:

```text
/catalogo Chloe
```

### Resultado esperado

El bot no debe mostrar excepciones, códigos HTTP ni otros mensajes técnicos.

Debe informar de forma comprensible que el catálogo no puede consultarse temporalmente.

### Resultado obtenido

**Correcto.**

El bot informó que no podía consultar el catálogo y permitió volver a intentarlo.

El fallo del servicio no incrementó el contador de errores del usuario.

### Recuperación

La variable temporal `STORE_API_URL` fue eliminada del archivo `.env`.

Posteriormente se realizó nuevamente:

```text
/catalogo Chloe
```

### Resultado obtenido

**Correcto.**

La consulta volvió a mostrar productos normalmente.

---

## 12. Registro de logs

### Objetivo

Comprobar que las entradas y salidas del bot son registradas para facilitar la depuración.

### Archivo utilizado

```text
storage/logs/bot.log
```

### Ejemplo

```text
IN chat=<referencia> | /start
OUT chat=<referencia> | ¡Hola, ...!
```

### Resultado obtenido

**Correcto.**

Los mensajes recibidos son registrados como `IN` y las respuestas como `OUT`.

El identificador real del chat no se almacena directamente. En su lugar se genera una referencia mediante hash.

Los archivos `.log` se encuentran excluidos del repositorio mediante `.gitignore`.

---

## 13. Webhook HTTPS

### Objetivo

Comprobar que Telegram puede enviar las actualizaciones directamente al servidor sin mantener un proceso de long polling abierto.

### Endpoint

```text
https://tienda.hv21011.duckdns.org/musichub-webhook.php
```

### Seguridad

El webhook utiliza un secreto independiente almacenado en `.env`:

```text
TELEGRAM_WEBHOOK_SECRET
```

El servidor verifica el encabezado enviado por Telegram antes de procesar la solicitud.

### Prueba mediante GET

Se realizó una solicitud `GET` al endpoint.

Resultado:

```text
405 Method Not Allowed
```

Este resultado es correcto, debido a que el webhook únicamente acepta peticiones `POST`.

### Prueba mediante POST

Se realizó una petición `POST` utilizando el secreto correspondiente.

Resultado:

```text
200 OK
```

### Prueba funcional

Se detuvo el proceso de long polling.

Posteriormente se enviaron desde Telegram:

```text
/start
/pedido 17
/catalogo Chloe
```

### Resultado obtenido

**Correcto.**

El bot continuó respondiendo sin mantener `php src/bot.php` ejecutándose en la terminal.

Esto confirma que Telegram entrega las actualizaciones mediante HTTPS al webhook configurado.

---

## 14. Integración con modelo de lenguaje

### Objetivo

Agregar capacidad para responder consultas abiertas sin sustituir las reglas utilizadas para las acciones principales del bot.

La máquina virtual dispone de recursos limitados para ejecutar un modelo local mediante Ollama, por lo que se utilizó un endpoint compatible con OpenAI mediante Groq.

### Modelo utilizado

```text
openai/gpt-oss-20b
```

### Verificación de la API

Primero se consultó la lista de modelos disponibles.

Resultado:

```text
HTTP: 200
```

Posteriormente se realizó una consulta de prueba:

```text
¿Qué diferencia hay entre un vinilo y un CD?
```

Resultado:

```text
HTTP: 200
```

La API respondió correctamente.

---

## 15. Patrón híbrido

Las acciones principales del bot no son procesadas por el modelo de lenguaje.

El flujo utilizado es:

```text
/start
/menu
/ayuda
/soporte
/salir
        |
        v
    Reglas PHP


/catalogo
        |
        v
    Reglas PHP
        |
        v
WooCommerce Store API


/pedido
        |
        v
    Reglas PHP
        |
        v
WooCommerce REST API


Consulta abierta
        |
        v
       Groq
```

De esta forma, las operaciones críticas permanecen bajo reglas controladas y el modelo se utiliza únicamente para conversación abierta.

---

## 16. Consulta abierta mediante IA

### Entrada

```text
¿Cuáles son las diferencias principales entre vinilo, CD y streaming?
```

### Resultado esperado

La consulta no corresponde a una acción crítica, por lo que puede ser enviada al modelo de lenguaje.

### Resultado obtenido

**Correcto.**

Groq respondió en español explicando las diferencias entre los formatos.

El bot permite formato básico, como texto en negrita, convirtiendo la respuesta al formato HTML aceptado por Telegram.

También se indicó al modelo que evite utilizar tablas Markdown y prefiera listas para mantener una presentación adecuada dentro de Telegram.

---

## 17. Protección de consultas relacionadas con pedidos

### Entrada

```text
quiero consultar mi pedido 17
```

### Resultado esperado

La consulta no debe enviarse al modelo de lenguaje.

### Resultado obtenido

**Correcto.**

El bot detecta que el mensaje está relacionado con un pedido y dirige al usuario al flujo controlado.

Ejemplo:

```text
/pedido 17
```

La consulta del pedido continúa realizándose directamente mediante WooCommerce.

---

## 18. Protección básica de datos personales

Antes de enviar una consulta abierta al modelo de lenguaje, el bot verifica patrones que pueden representar información personal.

Se consideran, entre otros:

- Correos electrónicos.
- Números telefónicos.
- DUI.
- Secuencias numéricas largas.

Cuando se detecta uno de estos patrones, el mensaje no se envía al modelo.

El bot permite continuar mediante:

```text
/menu
```

o solicitar atención mediante:

```text
/soporte
```

Las credenciales de Telegram, WooCommerce y Groq permanecen almacenadas en `.env`, archivo que está excluido del repositorio.

### Estado

**Correcto.**

---

## 19. Resumen de resultados

| Prueba | Resultado |
| :--- | :--- |
| Inicio mediante `/start` | Correcto |
| Menú principal | Correcto |
| Consulta de catálogo | Correcto |
| Búsqueda parcial por artista | Correcto |
| Producto inexistente | Correcto |
| Consulta de pedido existente | Correcto |
| Pedido inexistente | Correcto |
| Validación del número de pedido | Correcto |
| Reutilización de datos | Correcto |
| Cambio de intención | Correcto |
| Botones Sí / No | Correcto |
| Ayuda durante un flujo | Correcto |
| `/menu` | Correcto |
| `/salir` | Correcto |
| `/soporte` | Correcto |
| Máximo de tres intentos | Correcto |
| Fallo controlado de API REST | Correcto |
| Recuperación de API REST | Correcto |
| Logs de entrada y salida | Correcto |
| Webhook HTTPS | Correcto |
| Integración con Groq | Correcto |
| Consulta abierta | Correcto |
| Patrón híbrido | Correcto |
| Protección de consultas de pedidos | Correcto |
| Protección básica de datos personales | Correcto |

---

## 20. Conclusión

MusicHub Bot cubre el flujo conversacional definido para las consultas de catálogo y pedidos, incluyendo validación de datos, manejo de errores, cambio de intención, ayuda, regreso al menú y atención humana.

La integración con WooCommerce permite consultar información real del catálogo y el estado de los pedidos. Cuando el servicio REST presenta un fallo, el bot muestra un mensaje comprensible sin exponer errores técnicos internos.

El bot se encuentra desplegado mediante webhook HTTPS, por lo que no depende de mantener un proceso de long polling activo en la terminal.

Como complemento se integró un modelo de lenguaje mediante Groq siguiendo un patrón híbrido. Las operaciones relacionadas con catálogo, pedidos y soporte permanecen bajo reglas controladas, mientras que el modelo se utiliza para consultas abiertas.

Las credenciales utilizadas por Telegram, WooCommerce y Groq permanecen almacenadas en `.env` y no forman parte del repositorio.
