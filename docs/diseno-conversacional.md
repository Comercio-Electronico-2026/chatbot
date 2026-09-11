# Diseño Conversacional - Bot Tienda de Postres SP21013

## Lista de Chequeo
* **¿Quién usará el chatbot?** Clientes de San Salvador (18-50 años, con experiencia básica en apps de mensajería) que buscan adquirir postres artesanales bajo demanda.
* **¿Qué problema o problemas resuelve?** Automatizar la atención de consultas sobre precios y disponibilidad 24/7 sin tiempos de espera.
* **¿Qué necesidades específicas tienen?** Respuestas rápidas, información clara sobre el menú y seguimiento de sus compras.
* **¿Qué preguntas se pueden hacer?** "¿Tienen pastel de chocolate?", "¿Cuánto vale el envío?", "¿Dónde está mi orden?", "¿Me hacen descuento?".
* **¿Qué tipo de respuesta espero en cada caso?** Texto estructurado, selección de listas (menú) y números (precios).
* **¿Cuál es su edad, ocupación, intereses, nivel de experiencia con tecnología?** 18 a 50 años, estudiantes o profesionales ocupados, nivel de experiencia tecnológica de básico a intermedio.
* **¿Cuáles son los escenarios alternativos?** Ingresar un número de pedido incorrecto, superar el límite de intentos, solicitar un postre agotado, cambiar de tema a mitad del flujo, o escribir comandos no reconocidos.
* **¿UI, Accesibilidad?** Uso de mensajes cortos, botones integrados en Telegram (menús inline) y emojis para lectura ágil.
* **¿Cómo haré para validar mi prototipo?** Pruebas de usabilidad "Mago de Oz" y evaluación de pares usando las heurísticas de Grice.
* **¿Privacidad?** Solo se recolectará el número de orden y el ID del chat temporalmente. Los datos de la sesión se descartan al finalizar la consulta para no almacenar información sensible.

## Inventario de Intenciones
| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
| :--- | :--- | :--- | :--- | :--- |
| Consultar catálogo | "¿Qué postres tienen hoy?" | Alta | 1 | API de catálogo |
| Estado de pedido | "¿Dónde está mi pedido 1234?" | Alta | 1 | API de pedidos |
| Contacto humano | "Quiero hablar con alguien" | Media | 2 | Transferencia a agente |
| Ayuda | "/ayuda" | Baja | 3 | Flujo interno |
| Cancelar | "/cancelar" | Baja | 3 | Flujo interno |

## Diálogo de Muestra (Camino Feliz)
**Usuario:** Hola
**Bot:** ¡Hola! Soy el asistente virtual de Postres SP21013. Puedo mostrarte nuestro menú, consultar tu orden o comunicarte con alguien. Puedes escribir /ayuda en cualquier momento. ¿Qué necesitas?
**Usuario:** El estado de mi pedido
**Bot:** Claro. ¿Cuál es tu número de pedido de 4 dígitos?
**Usuario:** 1234
**Bot:** ¡Genial! Tu pedido 1234 (Pastel de Chocolate Supremo) va en camino y llega hoy por la tarde. ¿Necesitas algo más?

## Diagrama de Flujo
```mermaid
graph TD
    A[Usuario: /start o 'Hola'] --> B[Bot: Saluda, ofrece opciones y menciona /ayuda]
    B --> C{Elección del usuario}
    
    C -->|Consultar menú| D[Bot: Muestra catálogo de postres]
    
    C -->|Estado de pedido| E{¿Dio el número en el primer mensaje?}
    E -->|No| E1[Bot: Solicita número de orden de 4 dígitos]
    E1 --> F[Usuario: Ingresa dato]
    E -->|Sí| G{¿Número válido?}
    F --> G
    
    G -->|Sí| G1[Bot: Consulta API y responde estado exacto]
    G -->|No| G2{¿Intentos < 3?}
    G2 -->|Sí| E2[Bot: Mensaje de error y solicita de nuevo]
    E2 --> F
    G2 -->|No| H[Bot: Límite de intentos. Transfiere a agente humano]
    
    C -->|Ayuda / Fuera de alcance| I[Bot: Muestra opciones válidas o transfiere a agente]
    C -->|Cancelar| J[Bot: Cancela operación y borra datos en memoria]
    
    G1 --> K[Bot: Ofrece continuar o finalizar]
    D --> K
    H --> K
    I --> B
    J --> B
```

## Cambios aplicados tras la revisión entre pares (Pareja B: JO20004)

*   **Se permitió iniciar la conversación con un saludo natural:** Se modificó el diagrama de flujo para aceptar entradas comunes como "Hola" o "Buenos días", sin limitar el inicio exclusivamente al comando `/start`. *(Origen: Observación del Guion 1 - Camino feliz)*.
*   **Se mejoró la descubribilidad de las opciones:** Se añadió al mensaje de bienvenida la mención explícita de la opción de contacto humano y el uso del comando `/ayuda`. *(Origen: Observación de la Pregunta 1 - Alcance y descubribilidad)*.
*   **Se optimizó la longitud de los mensajes:** Se acortó ligeramente el texto del bot en el turno donde solicita el número de pedido para hacerlo más ágil. *(Origen: Observación de la Pregunta 2 - Grice: cantidad, relación y manera)*.
*   **Se evitó la redundancia al pedir datos:** Se incorporó una validación en el diagrama de flujo para no volver a solicitar el número de orden si el usuario ya lo proporcionó en su primer mensaje. *(Origen: Observación de la Pregunta 3 - Grice: calidad y relleno de datos)*.
*   **Se integraron flujos de error y navegación global:** Se agregaron las ramas faltantes al diagrama de flujo para manejar entradas inválidas con un tope de 3 intentos, comandos globales (`/ayuda`, `/cancelar`) y la derivación estructurada a un agente humano. *(Origen: Observaciones de la Pregunta 4 - Manejo de errores y Pregunta 5 - Reglas de producto)*.
