# Diseño conversacional - Tienda de Accesorios de Tecnología y Gaming
## 1. Lista de chequeo

* **¿Quién usará el chatbot?** Clientes interesados en adquirir periféricos, accesorios de tecnología y componentes gaming para su setup.
* **¿Qué problema resuelve?** Automatiza la consulta de disponibilidad, especificaciones y precios de periféricos sin depender de atención humana en tiempo real.
* **¿Qué necesidades específicas tienen?** Verificar existencias, comparar precios de accesorios (audífonos, teclados, mouse, hubs) y recibir sugerencias según el uso (gaming o productividad).
* **¿Qué preguntas se pueden hacer?** “¿Tienen audífonos bluetooth?”, “¿Cuánto cuesta el Mouse Gamer Óptico?”, “Necesito un teclado mecánico para jugar” o “¿Tienen adaptadores Hub USB-C?”.
* **¿Qué tipo de respuesta espero en cada caso?** Texto con detalles del periférico, precios claros expresados en dólares ($), estado del inventario y menús con listas de selección.
* **Edad, ocupación, intereses y experiencia con tecnología:** Personas de 16 a 50 años, estudiantes, entusiastas del gaming y profesionales. Tienen un nivel de experiencia tecnológica básico a intermedio.
* **Escenarios alternativos:** Producto no encontrado en el catálogo, producto agotado, entrada no comprendida por el bot o cancelación del usuario.
* **UI y accesibilidad:** Mensajes breves y concisos, uso de negritas para resaltar nombres y precios, comandos sencillos (`/start`, `/help`, `Menú`).
* **¿Cómo validar el prototipo?** Pruebas de usabilidad mediante simulaciones de búsqueda de productos con usuarios reales para validar la fluidez del diálogo.
* **Pruebas de usabilidad y desempeño:** Evaluar la claridad de las respuestas, la precisión en los precios informados y el tiempo de respuesta ante consultas repetidas.
* **Privacidad:** No se solicitan ni almacenan datos personales ni bancarios para realizar consultas de catálogo.

---

## 2. Inventario de intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
| :--- | :--- | :--- | :--- | :--- |
| **Consultar disponibilidad** | “¿Tienen audífonos bluetooth?” | Alta | 1 | API de catálogo: nombre del producto y existencias. |
| **Consultar precio** | “¿Cuánto cuesta el Hub USB-C Multiport 7 en 1?” | Alta | 1 | API de catálogo: precio actualizado en USD. |
| **Buscar productos Gamer** | “Quiero ver teclados o mouses gamer” | Alta | 1 | API de catálogo: filtrado por categoría Gaming. |
| **Pedir recomendación** | “Necesito un mouse para jugar” | Media | 2 | Catálogo: sugerencia de equipos según uso (ej. Mouse Gamer Óptico 7200 DPI). |
| **Pedir ayuda** | “Ayuda” o `/help` | Media | 2 | Respuesta con menú de opciones y comandos disponibles. |
| **Cancelar consulta** | “Cancelar” o `Salir` | Baja | 2 | Finaliza el flujo de la conversación activa. |

---

## 3. Diálogo de muestra - camino feliz

* **Usuario:** Hola.
* **Bot:** ¡Hola! Bienvenido a nuestra tienda de accesorios de tecnología y gaming. Puedo ayudarte a consultar precios, catálogo y disponibilidad de nuestros productos. ¿Qué estás buscando hoy?
* **Usuario:** Quiero saber si tienen audífonos bluetooth.
* **Bot:** Sí, contamos con los **Audífonos Inalámbricos Bluetooth Pro**. Tienen un precio de **$40.00** y están disponibles en inventario. ¿Te gustaría consultar otro producto?
* **Usuario:** ¿Cuánto cuesta el teclado mecánico?
* **Bot:** El **Teclado Mecánico Gaming RGB** tiene un precio de **$65.00** y contamos con unidades en stock. ¿Deseas información sobre algún otro accesorio?
* **Usuario:** No, eso sería todo. Gracias.
* **Bot:** ¡Un gusto ayudarte! Escribe `/start` o `Hola` cuando desees realizar una nueva consulta.

---

## 4. Diagrama de flujo de la conversación

```mermaid
flowchart TD
    A[Inicio / Mensaje del Usuario] --> B{¿Reconoce Intención?}
    
    B -->|Saludo / /start| C[Mostrar bienvenida y opciones del catálogo]
    B -->|Buscar Producto / Categoría| D[Consultar API de Catálogo]
    B -->|Pedir Recomendación Gamer| E[Filtrar accesorios por tipo: Gaming / Oficina]
    B -->|Pedir Ayuda / /help| F[Mostrar guía de uso]
    B -->|Entrada no reconocida| G[Mensaje de aclaración + sugerir menú]
    
    D --> H{¿Producto disponible?}
    H -->|En stock| I[Mostrar nombre del accesorio, precio $ y existencias]
    H -->|Agotado| J[Notificar sin existencias + sugerir similar]
    
    E --> K[Recomendar Mouse Gamer 7200 DPI o Teclado RGB]
    
    I --> L{¿Desea consultar otro producto?}
    J --> L
    K --> L
    F --> L
    G --> L
    
    L -->|Sí| B
    L -->|No / Cancelar| M[Despedida y fin de interacción]
 ```
