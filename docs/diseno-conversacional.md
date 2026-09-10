# Documento de Diseño Conversacional - Bot de Comercio Electrónico CET115

## 1. Lista de Chequeo de Diseño Conversacional

* **¿Quién usará el chatbot?**
  Clientes recurrentes y potenciales de la tienda en línea que acceden desde Telegram para resolver dudas rápidas sin esperar soporte telefónico o por correo.
* **¿Qué problema o problemas resuelve?**
  Resuelve la saturación de soporte en horarios no laborales y la lentitud en la atención de consultas frecuentes (consultar si un producto está disponible y conocer el estado de entrega de una compra).
* **¿Qué necesidades específicas tienen?**
  Obtener confirmación rápida del stock de un producto y saber el estado y fecha estimada de entrega de su paquete ingresando únicamente el identificador de pedido.
* **¿Qué preguntas se pueden hacer?**
  - "¿Tienen disponible el producto X?"
  - "¿Cuál es el estado de mi pedido #1234?"
  - "¿Qué comandos tienes disponibles?"
* **¿Qué tipo de respuesta espero en cada caso?**
  - Disponibilidad: Texto estructurado con nombre de producto, precio y estado de stock (o selección mediante lista/botones).
  - Estado de pedido: Entrada numérica de 4 o más dígitos del pedido; respuesta con texto descriptivo del estado y fecha estimada.
  - Menú/Start: Mensaje de bienvenida con lista de comandos.
* **¿Cuál es su edad, ocupación, intereses, nivel de experiencia con tecnología?**
  Público de 18 a 55 años, familiarizado con mensajería instantánea (Telegram/WhatsApp) y compras por internet, que prefiere resolver trámites en 2 pasos en lugar de navegar una web completa.
* **¿Cuáles son los escenarios alternativos (errores, datos faltantes, el usuario cambia de tema)?**
  - Si el usuario introduce un número de pedido no numérico o con longitud inválida, el bot solicita corregirlo con un ejemplo claro.
  - Si el pedido no existe en la base de datos/API, se notifica y se da opción de reintentar o hablar con soporte.
  - Si escribe una palabra no reconocida o cambia de tema, el bot ofrece el menú principal o comando `/help`.
  - El usuario puede cancelar en cualquier momento con `/cancel`.
* **¿UI y Accesibilidad?**
  Uso de textos breves, emojis informativos para facilitar lectura rápida, comandos claros con barra inclinada (`/start`, `/pedido`, `/catalogo`, `/ayuda`, `/cancel`).
* **¿Cómo haré para validar mi prototipo?**
  Mediante pruebas de usabilidad con la técnica del Mago de Oz (simulación de turnos con pares) y validación de cobertura de casos con las heurísticas conversacionales de Grice.
* **¿Privacidad y recolección de datos?**
  - **Qué datos se recogen:** ID de usuario de Telegram, nombre público de Telegram y el número de pedido consultado.
  - **Para qué:** Únicamente para asociar la consulta durante la sesión activa y enviar la respuesta.
  - **Cuándo se eliminan:** No se almacenan datos financieros ni personales sensibles; las variables de estado en memoria expiran al cerrar la conversación o tras 30 minutos de inactividad.

---

## 2. Inventario de Intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
| :--- | :--- | :--- | :--- | :--- |
| **Iniciar interacción** | `/start`, `hola`, `buenas` | Alta | 1 | Ninguno |
| **Estado de un pedido** | `¿dónde está mi pedido 1234?`, `/pedido` | Alta | 1 | API de pedidos (código: numérico) |
| **Consultar catálogo** | `¿tienen teclados?`, `/catalogo` | Alta | 1 | API de catálogo (búsqueda: texto) |
| **Pedir ayuda** | `/help`, `ayuda`, `¿qué puedes hacer?` | Media | 2 | Ninguno |
| **Cancelar operación** | `/cancel`, `cancelar`, `salir` | Media | 2 | Limpieza de estado local |

---

## 3. Diálogo de Muestra (Camino Feliz)

**Usuario:** `/start`  
**Bot:** ¡Hola! Soy el asistente virtual de la tienda. Puedo ayudarte a consultar el catálogo de productos o rastrear el estado de tu pedido. ¿Qué deseas hacer hoy?  
**Usuario:** Quiero ver el estado de mi pedido  
**Bot:** Con gusto. Por favor, indícame tu número de pedido (son 4 dígitos, por ejemplo: 1042).  
**Usuario:** 1042  
**Bot:** Tu pedido #1042 se encuentra **En tránsito** y su entrega está programada para el lunes por la tarde. ¿Te puedo ayudar con algo más?  
**Usuario:** No, gracias.  
**Bot:** ¡Un placer atenderte! Si necesitas más ayuda, solo escribe `/start`. ¡Que tengas un excelente día!  

---

## 4. Diagrama de Flujo de la Conversación

```mermaid
flowchart TD
    Start([Usuario envía mensaje]) --> CheckCmd{¿Es /start o saludo?}
    
    CheckCmd -- Sí --> Welcome[Bot: Bienvenida + Menú de opciones]
    CheckCmd -- No --> CheckIntent{Evaluar intención}
    
    CheckIntent -- /pedido o 'rastrear' --> AskOrder[Bot: Solicita número de pedido]
    CheckIntent -- /catalogo o 'productos' --> CallCatalog[Consulta API Catálogo] --> ShowCatalog[Bot: Muestra listado de productos]
    CheckIntent -- /cancel --> CancelOp[Bot: Operación cancelada. Escribe /start]
    CheckIntent -- /help o no reconocido --> ShowHelp[Bot: No entendí. Opciones disponibles: /pedido, /catalogo, /cancel]
    
    AskOrder --> ReceiveOrder[Usuario envía código]
    ReceiveOrder --> ValidateOrder{¿Es número de 4 dígitos?}
    
    ValidateOrder -- No --> ErrorFormat[Bot: Formato inválido. Debe ser de 4 números. Reintenta o /cancel]
    ErrorFormat --> AskOrder
    
    ValidateOrder -- Sí --> QueryAPI[Consultar API de Pedidos]
    QueryAPI --> ExistOrder{¿Pedido existe?}
    
    ExistOrder -- No --> ErrorNotFound[Bot: El pedido no existe. Verifica o escribe /cancel]
    ErrorNotFound --> AskOrder
    
    ExistOrder -- Sí --> ShowStatus[Bot: Estado del pedido + Ofrecer continuar]
    
    ShowStatus --> EndFlow([Fin de turno / Espera nueva acción])
    ShowCatalog --> EndFlow
    Welcome --> EndFlow
    CancelOp --> EndFlow
    ShowHelp --> EndFlow
