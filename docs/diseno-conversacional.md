# Documento de Diseño Conversacional - Bot de Comercio Electrónico CET115

## 1. Lista de Chequeo de Diseño Conversacional

* **¿Quién usará el chatbot?**
  Clientes recurrentes y potenciales de la tienda en línea TechShift que acceden desde Telegram para resolver consultas frecuentes de forma rápida, sin depender de atención telefónica o por correo electrónico.

* **¿Qué problema o problemas resuelve?**
  El chatbot busca reducir la saturación del soporte en horarios no laborales y agilizar la atención de consultas frecuentes relacionadas con:

  - Estado y entrega estimada de pedidos.
  - Consulta del catálogo y disponibilidad de productos.
  - Orientación básica sobre las funciones disponibles del bot.
  - Solicitud de soporte cuando la consulta está fuera del alcance del bot.

* **¿Qué necesidades específicas tienen?**
  Los usuarios necesitan:

  - Consultar rápidamente el estado de un pedido.
  - Conocer la fecha estimada de entrega de un pedido.
  - Consultar productos disponibles en el catálogo.
  - Recibir instrucciones claras sobre qué puede hacer el bot.
  - Cancelar una operación en cualquier momento.
  - Solicitar soporte humano cuando el bot no pueda resolver su consulta.

* **¿Qué preguntas se pueden hacer?**
  Ejemplos de consultas:

  - "¿Tienen disponible el producto X?"
  - "¿Cuál es el estado de mi pedido #1234?"
  - "¿Dónde está mi pedido 1042?"
  - "Quiero consultar mi pedido."
  - "Quiero ver el catálogo."
  - "¿Qué productos tienen?"
  - "¿Qué puedes hacer?"
  - "Necesito hablar con soporte."
  - "Cancelar."

* **¿Qué tipo de respuesta espero en cada caso?**
  - Inicio: mensaje de bienvenida y menú principal.
  - Estado de pedido: solicitar el número de pedido únicamente si el usuario todavía no lo proporcionó. El identificador debe contener exactamente 4 dígitos.
  - Pedido proporcionado dentro del mensaje: reutilizar el número detectado y consultar directamente la API, sin volver a solicitarlo.
  - Catálogo: consultar la API de catálogo y mostrar la información disponible en un formato breve y comprensible.
  - Ayuda: explicar las acciones disponibles y mostrar los comandos principales.
  - Cancelación: cancelar el flujo actual, limpiar el estado de la conversación y regresar al menú.
  - Soporte: informar que la consulta puede requerir atención humana y ofrecer la opción correspondiente disponible en la implementación.
  - Entrada no reconocida: explicar brevemente las opciones disponibles y permitir hasta 3 intentos antes de regresar al menú.
  - Fallo de API: informar que el servicio no está disponible temporalmente, sin mostrar errores técnicos, y ofrecer reintentar o regresar al menú.

* **¿Cuál es su edad, ocupación, intereses, nivel de experiencia con tecnología?**
  Público general adulto, aproximadamente entre 18 y 55 años, familiarizado con aplicaciones de mensajería y compras por internet.

* **¿Cuáles son los escenarios alternativos (errores, datos faltantes, el usuario cambia de tema)?**
  - Número de pedido con formato inválido: se informa que debe contener exactamente 4 dígitos y se permite corregirlo.
  - Tres intentos inválidos: después del tercer intento se ofrecen las opciones de volver al menú o solicitar soporte.
  - Pedido inexistente: se informa que no fue encontrado y se permite reintentar o volver al menú.
  - Tres consultas de pedido no encontradas: se ofrecen las opciones de volver al menú o solicitar soporte.
  - API no disponible: se muestra un mensaje comprensible y se permite reintentar o regresar al menú.
  - Entrada no reconocida: se solicita una opción válida y se permiten hasta 3 intentos.
  - Tres entradas no reconocidas: se muestra ayuda y se regresa al menú.
  - Cancelación durante cualquier flujo: /cancel cancela la operación actual y devuelve al menú.
  - Solicitud de ayuda durante cualquier flujo: /help muestra las opciones disponibles sin tratar la palabra "ayuda" como un dato inválido.
  - Cambio de tema durante un flujo: si el usuario solicita otra intención válida, el bot reconoce la nueva intención y abandona el flujo anterior para atenderla.
  - Solicitud de soporte: se ofrece la ruta de soporte disponible.
  - Usuario que ya proporciona el dato solicitado: el bot reutiliza el dato en lugar de pedirlo nuevamente.

* **¿UI y Accesibilidad?**
  El bot utilizará:

  - Mensajes breves y directos.
  - Emojis únicamente como apoyo visual.
  - Botones o teclado de Telegram para las opciones principales.
  - Comandos consistentes:
    	- /start
	- /pedido
	- /catalogo
	- /help
	- /soporte
	- /cancel
  - Ejemplos concretos cuando se solicite un dato.
  - Mensajes de error comprensibles, sin mostrar información técnica de APIs, servidores o excepciones.

* **¿Cómo haré para validar mi prototipo?**
  La validación se realizará mediante:

  - Pruebas del camino feliz.
  - Pruebas de escenarios alternativos.
  - Técnica del Mago de Oz para simular la interacción y observar si las respuestas permiten completar las tareas.
  - Revisión de las respuestas utilizando las heurísticas conversacionales de Grice.
  - Pruebas de entradas inválidas, cambios de tema, cancelación, ayuda y fallos de servicio.

  También se comprobará que el flujo no tenga bucles infinitos ni nodos sin salida.

* **¿Privacidad y recolección de datos?**
  Datos utilizados durante la interacción:

  - ID de usuario de Telegram.
  - Nombre público de Telegram, cuando esté disponible.
  - Número de pedido proporcionado por el usuario.

  Finalidad:
  Los datos se utilizan únicamente para identificar la conversación, mantener el estado temporal del flujo y realizar la consulta solicitada.

  Minimización:
  El bot no solicita datos financieros ni información personal sensible para las funciones definidas en este diseño.

  Estado de conversación:
  El estado temporal de la conversación se mantiene mientras sea necesario para completar el flujo y debe expirar después de un período de inactividad definido por la implementación.

---

## 2. Inventario de Intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
| :--- | :--- | :--- | :--- | :--- |
| **Iniciar interacción** | `/start`, `hola`, `buenas` | Alta | 1 | Ninguno |
| **Estado de un pedido** | `¿dónde está mi pedido 1234?`, `/pedido` | Alta | 1 | Numero de pedido de exactamente 4 digitos / API de pedidos |
| **Consultar catálogo** | `¿tienen teclados?`, `/catalogo`, `quiero ver productos`, | Alta | 1 | Texto de busqueda / API de catalogos |
| **Pedir ayuda** | `/help`, `ayuda`, `¿qué puedes hacer?` | Media | 2 | Ninguno |
| **Cancelar operación** | `/cancel`, `cancelar`, `salir` | Media | 2 | Limpieza de estado local |
| **Solicitar soporte** | `/soporte`, `quiero hablar con alguien de soporte`, `necesito ayuda de un agente` | Media | 2 | Canal de soporte disponible |
| **Entrada no reconocida** | `asdf`, `xyz`, `mensaje fuera del alcance` | Media | 3 | Contador de intentos |

  Las intenciones /start, /help, /cancel y /soporte deben poder reconocerse independientemente del estado actual de la conversación.
  Por ejemplo, si el bot está esperando un número de pedido y el usuario escribe /cancel, el bot debe cancelar el flujo en lugar de intentar validar /cancel como número.

---

## 3. Datos y Slot Filling

  El bot debe reutilizar los datos que el usuario ya haya proporcionado.

  Estado de pedido
  Dato requerido: número de pedido.
  Formato: exactamente 4 dígitos.

  Ejemplo:
  - Usuario: ¿Dónde está mi pedido 5678?

  El bot debe detectar 5678 y consultar directamente el pedido.

  No debe responder: "Por favor, dime tu número de pedido."
  Si el número ya está presente en el mensaje.

  Solicitud sin número
  Si el usuario escribe:
  - "Quiero saber dónde está mi pedido."

  El bot debe solicitar el dato faltante:
  - "Claro. Indícame tu número de pedido de 4 dígitos. Por ejemplo: 1042."

  Validación
  Se aceptan únicamente identificadores que cumplan:
  ^\d{4}$

  Ejemplos válidos:
  - 1042
  - 5678
  - 0001

  Ejemplos inválidos:
  - 123
  - 12345
  - 12AB
  - pedido 1234 cuando el número no pueda extraerse correctamente.

  Si el número puede extraerse inequívocamente del mensaje, se reutiliza como slot de pedido.

## 4. Dialogo de muestra (Camino Feliz)
  Usuario: /start

  Bot: 
  ¡Hola! 👋 Soy el asistente virtual de TechShift.

  Puedo ayudarte a consultar el estado de un pedido o revisar nuestro catálogo.

  ¿Qué deseas hacer?

  Botones:

  📦 Consultar pedido
  🛍️ Ver catálogo
  ❓ Ayuda
  👤 Soporte

  Usuario: Quiero ver el estado de mi pedido.

  Bot:
  Con gusto. Indícame tu número de pedido de 4 dígitos.
  Ejemplo: 1042

  Usuario: 1042

  Bot:
  Tu pedido #1042 se encuentra En tránsito.

  La fecha estimada de entrega es lunes por la tarde.

  ¿Deseas realizar otra consulta?

  Botones:

  📦 Consultar pedido
  🛍️ Ver catálogo
  ❓ Ayuda
  👤 Soporte
  ❌ Cancelar

  Usuario: No, gracias.

  Bot:
  ¡Con gusto! 👋 Cuando necesites ayuda, puedes escribir /start.

---

## 5. Diagrama de Flujo de la Conversación

```mermaid
flowchart TD

    Start([Usuario envía mensaje]) --> GlobalCmd{¿Comando global?}

    GlobalCmd -- /cancel --> CancelOp[Cancelar operación actual]
    CancelOp --> Menu

    GlobalCmd -- /help --> Help[Mostrar comandos disponibles]
    Help --> Menu

    GlobalCmd -- No --> Intent{Detectar intención}

    Intent -- /start --> Menu[Bienvenida + menú]
    Intent -- pedido --> HasOrder{¿Ya incluyó número de pedido?}
    Intent -- catálogo --> HasProduct{¿Ya indicó producto?}
    Intent -- soporte --> Support[Mostrar información de soporte]
    Intent -- desconocido --> Unknown[Incrementar intentos desconocidos]

    Unknown --> UnknownCount{¿Menos de 3 intentos?}
    UnknownCount -- Sí --> Menu
    UnknownCount -- No --> Support

    HasOrder -- No --> AskOrder[Solicitar número de pedido]
    AskOrder --> ReceiveOrder[Usuario envía mensaje]
    ReceiveOrder --> OrderGlobal{¿/cancel o /help?}

    OrderGlobal -- /cancel --> CancelOp
    OrderGlobal -- /help --> Help
    OrderGlobal -- No --> ValidateOrder{¿Número de exactamente 4 dígitos?}

    HasOrder -- Sí --> QueryOrder
    ValidateOrder -- Sí --> QueryOrder[Consultar API de pedidos]

    ValidateOrder -- No --> OrderAttempts[Incrementar intentos]
    OrderAttempts --> OrderCount{¿Menos de 3?}
    OrderCount -- Sí --> AskOrder
    OrderCount -- No --> Support

    QueryOrder --> OrderAPI{¿API respondió?}

    OrderAPI -- No --> APIError[Informar problema temporal]
    APIError --> Menu

    OrderAPI -- Sí --> OrderExists{¿Pedido existe?}

    OrderExists -- No --> NotFound[Pedido no encontrado]
    NotFound --> NotFoundCount{¿Menos de 3 intentos?}
    NotFoundCount -- Sí --> AskOrder
    NotFoundCount -- No --> Support

    OrderExists -- Sí --> ShowStatus[Mostrar estado y fecha estimada]
    ShowStatus --> Menu

    HasProduct -- No --> AskProduct[Solicitar nombre del producto]
    AskProduct --> ProductInput[Usuario proporciona producto]
    ProductInput --> QueryCatalog[Consultar API de catálogo]

    HasProduct -- Sí --> QueryCatalog

    QueryCatalog --> CatalogAPI{¿API respondió?}
    CatalogAPI -- No --> CatalogError[Informar problema temporal]
    CatalogError --> Menu

    CatalogAPI -- Sí --> ShowProducts[Mostrar productos encontrados]
    ShowProducts --> Menu

    Support --> Menu
