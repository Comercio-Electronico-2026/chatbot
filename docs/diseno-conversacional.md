# Diseño conversacional - Tienda de computadoras
## Lista de chequeo

- **¿Quién usará el chatbot?** Clientes que quieren comprar computadoras, accesorios o consultar productos de la tienda.
- **¿Qué problema resuelve?** Ayuda a las personas a conocer productos, precios y existencias sin tener que esperar a que alguien de la tienda responda.
- **¿Qué necesidades específicas tienen?** Saber si un producto está disponible, conocer su precio y recibir una recomendación según el uso que le darán y el dinero que desean gastar.
- **¿Qué preguntas se pueden hacer?** “¿Tienen laptops?”, “¿Cuánto cuesta una memoria RAM?”, “Necesito una computadora para estudiar con $700 de presupuesto”, “¿Tienen mouse inalámbrico?” y “¿Tienen impresoras?”.
- **¿Qué tipo de respuesta espero en cada caso?** Texto para describir productos, números para precios y existencias, y listas de opciones para elegir categorías, tipo de uso o presupuesto.
- **Edad, ocupación, intereses y experiencia con tecnología:** Personas de 16 años en adelante, estudiantes, profesionales y público general. El nivel de experiencia con tecnología puede variar, por eso el bot debe usar palabras sencillas.
- **Escenarios alternativos:** Producto no encontrado, producto sin existencias, entrada vacía, mensaje no entendido, cambio de tema, petición fuera de alcance, falla de la API o cancelación de la consulta.
- **UI y accesibilidad:** Mensajes cortos, opciones claras, lenguaje sencillo, precios expresados en dólares y comandos visibles como `ayuda`, `volver` y `cancelar`.
- **¿Cómo validar el prototipo?** Se probará con compañeros usando el ejercicio de Mago de Oz: una consulta normal y otra con errores, cambio de tema y preguntas fuera del alcance del bot.
- **Pruebas de usabilidad y desempeño:** Se verificará que el usuario pueda encontrar un producto sin ayuda, que entienda los mensajes de error y que el bot responda de forma clara.
- **Privacidad:** El bot no solicitará ni almacenará datos personales para consultar el catálogo. Si el usuario brinda información, se utilizará solamente para responder la consulta actual.

## Inventario de intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
|---|---|---:|---:|---|
| Consultar disponibilidad | “¿Tienen laptop Lenovo IdeaPad?” | Alta | 1 | API de catálogo: nombre del producto y existencias. |
| Consultar precio | “¿Cuánto cuesta una laptop Lenovo?” | Alta | 1 | API de catálogo: precio del producto. |
| Buscar por categoría | “Quiero ver memorias RAM” | Alta | 1 | API de catálogo: productos por categoría. |
| Pedir recomendación | “Necesito una computadora para estudiar con $700” | Media | 2 | Catálogo: equipos según uso y presupuesto. |
| Consultar accesorios | “¿Tienen mouse inalámbrico?” | Media | 2 | API de catálogo: accesorios disponibles. |
| Pedir ayuda o menú | “Ayuda”, “Menú” o `/start` | Media | 2 | No requiere API; muestra opciones y ejemplos. |
| Volver atrás | “Volver” | Baja | 2 | No requiere API; regresa al menú principal. |
| Cancelar consulta | “Cancelar” | Baja | 2 | No requiere API; termina la consulta actual. |
| Pedir atención humana | “Quiero hablar con una persona” | Baja | 3 | No requiere API; muestra información de contacto. |

## Diagrama de flujo de la conversación

```mermaid
flowchart TD
    A[Usuario inicia conversación] --> B[Bot saluda, explica qué hace y muestra ejemplos]
    B --> C{¿Qué desea consultar?}

    C -->|Disponibilidad o precio| D{¿El usuario indicó el producto?}
    D -->|Sí| E[Consultar catálogo]
    D -->|No| F[Bot pide solo el dato que falta]
    F --> E

    E --> G{¿La API respondió?}
    G -->|Sí, hay existencias| H[Bot muestra precio y disponibilidad]
    G -->|Sí, sin existencias| I[Bot informa que no hay existencias y ofrece alternativas]
    G -->|Producto no encontrado| J[Bot informa que no encontró el producto y muestra categorías]
    G -->|No| K[Bot informa que no puede consultar el catálogo y ofrece reintentar]

    C -->|Categoría| L[Bot muestra productos de la categoría]
    C -->|Recomendación| M[Bot pregunta para qué usará el equipo y cuál es su presupuesto]
    M --> N[Bot recomienda productos disponibles]
    C -->|Ayuda| O[Bot muestra ejemplos de preguntas]
    C -->|Volver| B
    C -->|Cancelar| P[Bot confirma el cierre de la consulta]
    C -->|Petición fuera de alcance| Q[Bot explica lo que puede hacer y ofrece atención humana]
    C -->|Mensaje vacío o no entendido| R{¿Ha fallado menos de 3 veces?}

    R -->|Sí| S[Bot muestra ejemplos y opciones válidas]
    R -->|No| T[Bot ofrece volver al menú o pedir atención humana]

    H --> U{¿Desea hacer otra consulta?}
    I --> U
    J --> U
    K --> U
    L --> U
    N --> U
    O --> U
    Q --> U

    S --> B
    T --> B
    U -->|Sí| B
    U -->|No| P
```

Ahora durante la conversación, en cualquier momento el usuario puede escribir `ayuda`, `volver` o `cancelar`. Si cambia de tema, el bot va a reconocer la nueva intención y vuelve al menú correspondiente.

## Diálogo de muestra - camino feliz

> **Corrección aplicada:** ahora la bienvenida explica mejor las funciones del bot, contiene ejemplos de preguntas y se evita pedir nuevamente datos que el usuario ya había dado.

**Usuario:** `/start`

**Bot:** ¡Hola! Soy el asistente de la tienda de computadoras. Puedo ayudarte a consultar productos, precios, existencias, accesorios y recomendaciones según tu presupuesto. Puedes preguntar por laptops, RAM, mouse o impresoras. ¿Qué necesitas?

**Usuario:** ¿Tienen laptop Lenovo IdeaPad para estudiar?

**Bot:** Sí. Según el catálogo, tenemos la Lenovo IdeaPad con 8 GB de RAM y 512 GB SSD. Su precio es $650 y está disponible. ¿Deseas consultar otro producto o pedir una recomendación?

**Usuario:** No, gracias.

**Bot:** De nada. Consulta finalizada. Escribe `/start` cuando necesites buscar otro producto.

---

**Veredicto Final Pareja B**

1-  ¿El mensaje de bienvenida explica en una o dos frases qué hace el bot y qué más puede pedir el usuario?
**Calificación: Parcial.** La bienvenida está bastante clara porque dice que el bot puede consultar productos, precios y disponibilidad. El problema es que no menciona de entrada que también puede recomendar equipos o buscar accesorios, y tampoco le da ejemplos al usuario para seguir conversando. Yo agregaría al final algo como: “También puedo recomendarte una computadora según el uso que le darás. Puedes preguntar por laptops, RAM, mouse o impresoras”.

2- ¿Los mensajes del bot cumplen con cantidad, relación y manera?
**Calificación: Resuelto.** El diálogo se entiende bien y las respuestas tienen sentido con lo que pregunta el usuario. Por ejemplo, cuando pregunta por laptops, el bot le dice qué marcas tiene y luego le pregunta para qué la necesita. No usa palabras complicadas ni hace varias preguntas al mismo tiempo. Como pequeña mejora, se podría aclarar que los modelos y precios dependen de la disponibilidad, para que no parezca que siempre tendrá exactamente los mismos productos.

3- ¿El bot promete solo información que puede verificar? ¿Evita pedir datos repetidos?
**Calificación: Parcial.** El diseño menciona que los precios y existencias saldrán de una API de catálogo, así que en teoría el bot puede verificar la información antes de responder. Sin embargo, en el flujo el bot pide el nombre del producto, aunque el usuario podría haber dado parte de esa información desde el inicio. También, para recomendar una computadora, solo pregunta el uso, pero el inventario menciona que la recomendación depende del uso y presupuesto. Sería mejor que el bot aproveche los datos que el usuario ya escribió y, para una recomendación, pregunte también cuánto piensa gastar.

4- ¿El diagrama maneja las cuatro situaciones de fricción y ofrece una reparación clara?
**Calificación: Parcial.** El diseño sí toma en cuenta cuando el mensaje no se entiende y cuando no se encuentra un producto, lo cual está bien. Pero faltan situaciones como que el usuario escriba algo vacío, cambie de tema mientras el bot le pide un producto, pida algo que la tienda no hace o que falle la API. Tampoco se indica qué pasa después de varios intentos incorrectos. Yo agregaría mensajes como: “No entendí tu consulta. Puedes escribir laptops, accesorios o ayuda”, y después de tres intentos ofrecer volver al menú o contactar a una persona.

5- ¿El usuario puede cancelar, volver atrás o pedir ayuda en cualquier momento? ¿Cada intención termina claramente?
**Calificación: Parcial.** El flujo incluye ayuda y cancelar, y después de mostrar productos o una recomendación el bot pregunta si el usuario quiere seguir consultando. Eso hace que la conversación tenga un cierre bastante claro. Sin embargo, ayuda y cancelar aparecen solo cuando el bot muestra el menú inicial; no se ve que el usuario pueda usarlos mientras está respondiendo otra pregunta. Sería bueno agregar en cada paso algo como: “Puedes escribir ayuda, volver o cancelar cuando quieras”, y al finalizar usar un mensaje como: “Listo, terminé la consulta. Escribe /start si necesitas buscar otro producto”.

**Notas del Mago de Oz**

 Guion 1 - Camino feliz
- **Turno 1:** El usuario saluda y el bot responde explicando que puede consultar productos, precios y disponibilidad. En este punto el flujo funciona bien, aunque podría mencionar también las recomendaciones y accesorios desde la bienvenida.
- **Turno 2:** El usuario pregunta si hay laptops. El bot responde que tiene laptops Lenovo, HP y Asus, y pregunta para qué la necesita. La conversación sigue de manera natural.
- **Turno 3:** El usuario indica que necesita una laptop para estudiar. El bot recomienda un equipo, muestra sus especificaciones, precio y disponibilidad. Este paso funciona, pero sería mejor que también pregunte el presupuesto antes de recomendar un producto.
- **Turno 4:** El usuario responde que no desea consultar otro producto. El bot se despide de forma clara. El camino feliz se completa sin problemas importantes.


Guion 2 - Camino con fricción

- **Turno 1 - Entrada vacía o sin sentido:** Si el usuario escribe “.” o “asdf”, el diagrama lo toma como mensaje no entendido y vuelve a mostrar las opciones. Esto ayuda, pero sería mejor mostrar ejemplos concretos como “Puedes consultar laptops, RAM, mouse o impresoras”.
- **Turno 2 - Producto mal escrito o inexistente:** El diagrama contempla que el producto no sea encontrado y el bot informa esa situación. Sin embargo, no ofrece alternativas para continuar, como sugerir productos parecidos o mostrar categorías disponibles.
- **Turno 3 - Cambio de tema:** Si el bot está esperando el nombre de un producto y el usuario cambia de tema, por ejemplo pregunta por accesorios, el diagrama no muestra cómo manejar ese cambio. Aquí el flujo se traba porque falta una rama para reconocer la nueva intención.
- **Turno 4 - Petición fuera de alcance:** Si el usuario pide un descuento o algo que el bot no puede hacer, el diseño no tiene una respuesta específica. Sería bueno agregar un mensaje como: “No puedo aplicar descuentos, pero puedo ayudarte a consultar precios y productos disponibles”. También se podría ofrecer atención humana si fuera necesario.
