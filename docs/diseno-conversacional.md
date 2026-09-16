## 1. Investigación del Usuario y Lista de Chequeo

* **¿Quién usará el chatbot?**
  Clientes de CET STORE: profesionales y/o entusiastas de la tecnología, especialmente interesados en periféricos, componentes y almacenamiento de computadoras.

* **¿Qué problema o problemas resuelve?**
  Proporcionará atención 24/7 a los clientes ante dudas frecuentes sobre productos (disponibilidad, precio, etc.), consultas sobre sus pedidos (estado) e información sobre formas de pago.

* **¿Qué necesidades específicas tienen?**
  Conocer disponibilidad de stock en tiempo real, verificar si su orden de compra ya fue procesada/despachada y acceder a enlaces rápidos de compra sin tener que buscar manualmente en la página web.

* **¿Qué preguntas se pueden hacer?**

  * ¿Cuál es el estado de mi pedido #512?
  * ¿Cuáles son los métodos de pago?
  * ¿Tienen disponible Memorias RAM de 16 GB?

* **¿Qué tipo de respuesta espero en cada caso?**

  * Para productos: Botones o listas con enlaces directos a la tienda web.
  * Para pedidos: Número entero (ID del pedido).
  * Para pagos: Texto con información clara.

* **¿Cuál es su edad, ocupación, intereses, nivel de experiencia con tecnología?**
  Entre 18 y 40 años. Nivel tecnológico medio-alto. Valoran respuestas rápidas y directas, sin exceso de formalidades.

* **¿Cuáles son los escenarios alternativos (errores, datos faltantes, cambio de tema)?**

  * **Error de formato:** El usuario ingresa texto cuando se espera un número. El bot avisa y pide corregir.
  * **Límite de intentos:** El usuario falla 3 veces al buscar un producto o pedido. El bot corta el flujo y deriva a un humano (`@soporteCET`).
  * **Fallo de API:** La tienda está caída o la API no responde correctamente. El bot informa que hay problemas técnicos y pide intentar más tarde.
  * **Cambio de tema / Cancelación:** El usuario escribe `/cancelar` o `/ayuda` a mitad de un proceso. El bot intercepta el comando, aborta la acción actual y muestra el menú principal o la ayuda.

* **UI, Accesibilidad:**
  Menú enumerado y botones para que el usuario sepa exactamente qué debe escribir o solicitar sin adivinar comandos.

* **¿Cómo haré para validar mi prototipo?**
  Técnica de Mago de Oz y evaluación con Heurísticas de Grice (Actividad 4).

* **Privacidad (Recolectar datos: para qué, cuáles y cuándo eliminarlos):**
  Se recolecta el ID de Telegram y el número de pedido temporalmente para consultar la API. Los datos de sesión se eliminan al finalizar la consulta o al usar el comando de cancelación.

---

## 2. Inventario de Intenciones

| Intención                    | Ejemplo de enunciado                 | Frecuencia | Prioridad | Dato / API                           |
| :--------------------------- | :----------------------------------- | :--------- | :-------- | :----------------------------------- |
| **Buscar productos**         | "¿Tienen mouse RGB con entrada USB?" | Alta       | 1         | Término / API WooCommerce            |
| **Consultar Pedidos**        | "Estado de mi pedido 2048"           | Alta       | 1         | ID Pedido (entero) / API WooCommerce |
| **Consultar formas de pago** | "¿Aceptan transferencias bancarias?" | Media      | 2         | Ninguno / Texto informativo          |
| **Cancelar interacción**     | "/cancelar", "salir"                 | Baja       | 3         | Ninguno / Lógica del bot             |
| **Solicitar ayuda**          | "/ayuda"                             | Baja       | 3         | Ninguno / Lógica del bot             |

---

## 3. Diálogo de Muestra (Camino Feliz)

**Usuario:** Estado de mi pedido 2048

*(El bot detecta la intención y hace "slot filling" extrayendo el ID 2048 directamente, saltándose el paso de pedirlo)*

**Bot:** Claro, te ayudaré a consultar tu pedido #2048. Por seguridad, por favor escribe el correo electrónico con el que realizaste la compra.

**Usuario:** [chris@ejemplo.com](mailto:chris@ejemplo.com)

**Bot:** ¡Gracias! Tu pedido #2048 se encuentra **En tránsito** y será entregado hoy por la tarde. ¿Puedo ayudarte con algo más? (Responde Sí / No, o usa `/ayuda`).

**Usuario:** No

**Bot:** Gracias por preferir CET STORE. Presiona `/start` cuando necesites ayuda otra vez.

---

## 4. Diagrama de Flujo de la Conversación

```mermaid
flowchart TD
    %% Inicio y Menú
    A([Inicio: /start]) --> B[Bot: Saludo y Menú Principal]
    B --> C{Selección del usuario}

    %% Comandos Globales
    C -->|/ayuda| Ayuda[Bot: Muestra lista de comandos y contacto] --> B
    C -->|/cancelar| O[Bot: Operación cancelada. Regresando al menú] --> B

    %% OPCIÓN 1: PRODUCTOS
    C -->|Opción 1: Producto| D[Bot pide nombre del producto]
    D --> E[/Usuario ingresa texto/]

    %% Intercepción Global 1
    E --> GlobalCheck1{¿Es /cancelar o /ayuda?}
    GlobalCheck1 -->|/ayuda| Ayuda
    GlobalCheck1 -->|/cancelar| O
    GlobalCheck1 -->|No| F_API_Call1[Consulta API WooCommerce]

    %% Validación API Productos
    F_API_Call1 --> F_API_Status1{¿API Responde 200?}
    F_API_Status1 -->|Error/Timeout| API_Fail[Bot: Problemas técnicos. Intenta más tarde] --> B
    F_API_Status1 -->|OK| F{¿Existe en catálogo?}

    F -->|Sí| G[Bot muestra nombre, precio, stock y link] --> P
    F -->|No| H_Count1{¿Intentos fallidos < 3?}
    H_Count1 -->|Sí| H[Bot: No encontrado. Intenta de nuevo] --> D
    H_Count1 -->|No| DerivarHumano[Bot: Límite de intentos. Derivando a @soporteCET] --> B

    %% OPCIÓN 2: PEDIDOS (Slot Filling + Doble Factor)
    C -->|Opción 2: Pedido| SlotCheck{¿Mensaje inicial incluye ID?}
    SlotCheck -->|Sí| I_Correo[Bot extrae ID y pide Correo]
    SlotCheck -->|No| I[Bot pide ID de pedido]
    I --> J1[/Usuario ingresa ID/]

    %% Intercepción Global 2 y Formato
    J1 --> GlobalCheck2{¿Es /cancelar o /ayuda?}
    GlobalCheck2 -->|Comando Global| GlobalCheck1
    GlobalCheck2 -->|No| ValidaFormato{¿Es un número entero?}
    ValidaFormato -->|No| FormatErr[Bot: Formato inválido. Ingresa solo números] --> I
    ValidaFormato -->|Sí| I_Correo

    I_Correo --> J2[/Usuario ingresa correo/]
    J2 --> F_API_Call2[Consulta API WooCommerce Pedidos]

    %% Validación API Pedidos
    F_API_Call2 --> F_API_Status2{¿API Responde 200?}
    F_API_Status2 -->|Error/Timeout| API_Fail
    F_API_Status2 -->|OK| K{¿ID y Correo coinciden?}

    K -->|Sí| M[Bot muestra estado del pedido] --> P
    K -->|No| H_Count2{¿Intentos fallidos < 3?}
    H_Count2 -->|Sí| L[Bot: Datos no coinciden. Intenta de nuevo] --> I_Correo
    H_Count2 -->|No| DerivarHumano

    %% OPCIÓN 3: PAGOS
    C -->|Opción 3: Pagos| N[Bot muestra info de pagos] --> P

    %% Cierre
    C -->|Texto no válido| ErrMsg[Bot: Opción inválida] --> B

    P[Bot: ¿Puedo ayudarte con algo más?] --> Q[/Usuario responde Sí o No/]
    Q -->|Sí| B
    Q -->|No| R([Fin de la conversación])
```

---

## 5. Revisión entre pares - Revisado por HB21009

### 1. Alcance y descubribilidad

* **Veredicto:** Parcial
* **Evidencia:** El mensaje inicial de bienvenida (nodo B) presenta con claridad los servicios soportados (productos, pedidos y pagos). No obstante, no especifica qué tareas quedan fuera de su alcance (como garantías o soporte técnico) ni expone directamente al usuario los comandos globales de escape (`/ayuda` y `/cancelar`).
* **Mejora propuesta:** Reescribir el mensaje de bienvenida para explicitar tanto lo que el bot no gestiona como la existencia de los comandos de ayuda y cancelación desde el primer contacto.

### 2. Grice en el guion — cantidad, relación y manera

* **Veredicto:** Parcial
* **Evidencia:** La respuesta con la ficha técnica del producto (nodo G) entrega datos concretos y relevantes. Sin embargo, en el mensaje donde se solicita el término de búsqueda (nodo D), la redacción es redundante e incumple la máxima de manera al no orientar al usuario sobre el formato o sintaxis esperada.
* **Mejora propuesta:** Modificar la solicitud de búsqueda agregando ejemplos concisos de palabras clave (por ejemplo: *SSD, RAM, Mouse*) y recordando la opción de cancelar la operación.

### 3. Grice — calidad y validación de datos

* **Veredicto:** No resuelto
* **Evidencia:** La consulta de órdenes solo requiere el identificador numérico de la compra, lo cual resulta insuficiente para comprobar la identidad del cliente sobre la API. Además, el flujo no implementa extracción de entidades (*slot filling*): si el usuario escribe de entrada *«Estado de mi pedido 2048»*, el bot ignora el dato y le vuelve a pedir el ID en el paso de captura (nodo J).
* **Mejora propuesta:** Exigir un segundo factor de autenticación (como el correo electrónico vinculado a la orden) e incorporar una regla condicional previa para que, si el identificador ya fue provisto en la consulta inicial, se procese directamente sin volver a solicitarlo.

### 4. Manejo de errores

* **Veredicto:** No resuelto
* **Evidencia:** Cuando la búsqueda de una orden falla en la validación (nodo K), el bot emite un mensaje de error y retorna en bucle infinito a pedir el dato, sin registrar un contador de reintentos máximos ni ofrecer derivación a un canal de soporte humano.
* **Mejora propuesta:** Implementar una compuerta con tope de tres intentos consecutivos; si se supera dicho límite, el bot debe proporcionar los datos de contacto de atención técnica/humana y redirigir al menú inicial.

### 5. Reglas del producto

* **Veredicto:** Parcial
* **Evidencia:** Aunque `/cancelar` existe en el diseño, únicamente se procesa dentro del menú principal. Si el usuario escribe `/cancelar` mientras el bot espera la palabra clave de un producto o el identificador de un pedido (nodos de entrada libre E y J), el sistema lo procesa erróneamente como un término de búsqueda o un ID no válido.
* **Mejora propuesta:** Configurar el comando `/cancelar` como una interrupción global evaluada con prioridad en cualquier paso de captura interactiva, permitiendo al usuario abortar la tarea en cualquier punto.

---

## 6. Registro de Ajustes (Resolución de Actividad 4)

Basado en la revisión por pares, se implementaron los siguientes cambios en el diseño:

1. **Alcance y descubribilidad / Reglas del producto:** Se incluyeron los comandos `/ayuda` y `/cancelar` de forma explícita en el diagrama de flujo mediante nodos interceptores (`GlobalCheck`), garantizando que sean salidas globales en cualquier etapa de la captura de datos.

2. **Grice - Calidad y Validación de Datos:** Se implementó el *slot filling* para que el bot extraiga el ID del pedido desde la primera consulta. Además, se sumó la solicitud del correo electrónico como segundo factor de autenticación antes de consumir la API.

3. **Manejo de Errores y Fallos de API:** Se rompieron los bucles infinitos agregando un contador de intentos fallidos (tope de 3) que deriva al usuario a soporte humano. Adicionalmente, se incluyó una rama para gestionar caídas o tiempos de espera de la API.

4. **Grice - Manera:** Se separó la validación de formato para indicar claramente al usuario cuando debe ingresar únicamente números.
