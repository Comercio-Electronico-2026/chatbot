# Diseño Conversacional - Bot Tienda de Postres SP21013

## Lista de Chequeo
* **¿Quién usará el chatbot?** Clientes de San Salvador (18-50 años, con experiencia básica en apps de mensajería) que buscan adquirir postres artesanales bajo demanda.
* **¿Qué problema o problemas resuelve?** Automatizar la atención de consultas sobre precios y disponibilidad 24/7 sin tiempos de espera.
* **¿Qué necesidades específicas tienen?** Respuestas rápidas, información clara sobre el menú y seguimiento de sus compras.
* **¿Qué preguntas se pueden hacer?** "¿Tienen pastel de chocolate?", "¿Cuánto vale el envío?", "¿Dónde está mi orden?".
* **¿Qué tipo de respuesta espero en cada caso?** Texto estructurado, selección de listas (menú) y números (precios).
* **¿Cuál es su edad, ocupación, intereses, nivel de experiencia con tecnología?** 18 a 50 años, estudiantes o profesionales ocupados, nivel de experiencia tecnológica de básico a intermedio.
* **¿Cuáles son los escenarios alternativos?** Ingresar un número de pedido incorrecto, solicitar un postre agotado o escribir comandos no reconocidos.
* **¿UI, Accesibilidad?** Uso de mensajes cortos, botones integrados en Telegram (menús inline) y emojis para lectura ágil.
* **¿Cómo haré para validar mi prototipo?** Pruebas de usabilidad "Mago de Oz" y evaluación de pares usando las heurísticas de Grice.
* **¿Privacidad?** Solo se recolectará el número de orden y el ID del chat temporalmente. Los datos de la sesión se descartan al finalizar la consulta para no almacenar información sensible.

## Inventario de Intenciones
| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
| :--- | :--- | :--- | :--- | :--- |
| Consultar catálogo | "¿Qué postres tienen hoy?" | Alta | 1 | API de catálogo |
| Estado de pedido | "¿Dónde está mi pedido 1234?" | Alta | 1 | API de pedidos |
| Contacto humano | "Quiero hablar con alguien" | Media | 2 | Transferencia a agente |

## Diálogo de Muestra (Camino Feliz)
**Usuario:** /start
**Bot:** ¡Hola! Soy el asistente virtual de Postres SP21013. Puedo mostrarte nuestro menú o consultar el estado de tu orden. ¿Qué necesitas?
**Usuario:** El estado de mi pedido
**Bot:** Claro. ¿Cuál es el número de pedido? Son cuatro dígitos y aparece en el correo de confirmación de tu compra.
**Usuario:** 1234
**Bot:** ¡Genial! Tu pedido 1234 (Pastel de Chocolate Supremo) va en camino y llega hoy por la tarde. ¿Necesitas algo más?

## Diagrama de Flujo
```mermaid
graph TD
    A[Usuario: /start] --> B[Bot: Saluda y ofrece menú o pedido]
    B --> C{Elección del usuario}
    C -->|Consultar menú| D[Bot: Muestra catálogo de postres]
    C -->|Estado de pedido| E[Bot: Solicita número de orden]
    E --> F[Usuario: Ingresa 1234]
    F --> G[Bot: Consulta API y responde estado exacto]
    G --> H[Bot: Ofrece continuar o finalizar]
    D --> H
