# Diseño Conversacional — PokeThings Bot

**Curso:** CET 115 — Comercio Electrónico
**Laboratorio 5 — Sesión 1**
**Bot:** Tienda de sobres (boosters) de cartas Pokémon, ropa temática y artículos variados (peluches, mochilas, tazas, llaveros, etc.)

---

## Actividad 1 — Diseño de conversación efectiva

### Lista de chequeo

**¿Quién usará el chatbot?**
Fans y coleccionistas de Pokémon que compran en línea: personas que buscan sobres de cartas de expansiones específicas, y clientes interesados en ropa y merchandising con temática Pokémon. 

**¿Qué problema o problemas resuelve?**
- Reduce el tiempo de espera para saber si un producto específico (sobre, playera, talla) está disponible, sin tener que llamar o escribir a un vendedor.
- Da seguimiento al estado de un pedido sin depender de que un agente humano revise manualmente.

**¿Qué necesidades específicas tienen?**
- Saber si hay stock de un producto puntual antes de decidir comprar (ej. sobres de una expansión reciente, talla de una playera).
- Dar seguimiento a un pedido ya realizado.

**¿Qué preguntas se pueden hacer?**
- "¿Tienen sobres de Scarlet & Violet?"
- "¿Qué tallas hay de la playera de Charizard?"
- "¿Cuánto cuesta el sobre de Paldea Evolved?"
- "¿Dónde está mi pedido 1234?"

**¿Qué tipo de respuesta espero en cada caso?**
- Producto / Talla: Selección de lista o texto corto.
- Número de pedido → texto/número (4 dígitos).
- Confirmaciones (sí/no, continuar) → selección de botón.

**¿Cuál es su edad, ocupación, intereses, nivel de experiencia con tecnología?**
- Edad: principalmente 13–35 años (adolescentes coleccionistas y jóvenes adultos), con un segmento secundario de padres 30–50 años comprando regalos.
- Ocupación: estudiantes, profesionales jóvenes, coleccionistas/gamers.
- Intereses: cartas coleccionables, videojuegos, anime, moda casual con temática geek.
- Nivel tecnológico: medio a alto. Interacción simplificada mediante botones guiados.

**¿Cuáles son los escenarios alternativos (errores, datos faltantes, el usuario cambia de tema)?**
- El usuario pregunta por un producto que no existe o está agotado.
- El usuario escribe un número de pedido inexistente o con formato incorrecto (letras, menos de 4 dígitos).
- El usuario cambia de intención a mitad del flujo (ej. está dando su número de pedido y de repente pregunta por una playera).
- El usuario escribe algo que el bot no reconoce (mensaje ambiguo o fuera de dominio).
- El usuario pide ayuda o cancelar en cualquier punto del flujo.

**¿UI, Accesibilidad?**
- Uso de *inline keyboards* de Telegram para las opciones principales (Sobres 🎴, Ropa 👕, Otros artículos 🎁, Estado de pedido 📦), reduciendo la necesidad de escribir texto libre.
- Mensajes cortos, sin jerga técnica ni abreviaciones de sistema (nunca mostrar errores tipo "Error 404").
- Texto siempre legible sin depender de imágenes; si se envía una foto de producto, se incluye descripción en texto.

**¿Cómo haré para validar mi prototipo?**
Mediante la técnica del Mago de Oz (Actividad 4): un compañero simula ser el bot siguiendo únicamente el guion y el diagrama de flujo de este documento, mientras otro actúa como usuario real, sin mejorar ni improvisar respuestas. Esto permite detectar huecos en el diseño antes de programarlo.

**Pruebas de usabilidad, desempeño**
- Verificar que un usuario nuevo pueda completar el flujo de "consultar disponibilidad de un sobre" sin ayuda externa.
- Medir cuántos turnos toma llegar a una respuesta útil.
- Revisar que el bot ofrezca continuar o cerrar la conversación después de cada intención resuelta.

**¿Privacidad?**
- Datos que se recolectan: número de pedido (para consultarlo en la API), y si el usuario decide comprar, nombre y dirección de envío (fuera del alcance de este bot inicial, se derivaría a un checkout externo).
- Para qué: únicamente para responder la consulta del usuario en el momento; el bot no almacena historial de conversación más allá de la sesión activa.
- No se solicitan ni almacenan datos de pago (tarjetas) dentro del chat.
- Cuándo se eliminan: los datos de sesión (contexto de la conversación) se descartan al cerrar o expirar el chat; no se conserva un historial permanente salvo el registro de pedidos que ya existe en el sistema de la tienda (ajeno al bot).

---

### Inventario de intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
|---|---|---|---|---|
| Consultar disponibilidad de sobres | "¿tienen sobres de Scarlet & Violet?" | Alta | 1 | API de catálogo (sobres) |
| Estado de un pedido | "¿dónde está mi pedido 1234?" | Alta | 1 | API de pedidos |
| Consultar disponibilidad de ropa/talla | "¿qué tallas hay de la playera de Charizard?" | Alta | 1 | API de catálogo (ropa) |
| Consultar precio de un producto | "¿cuánto cuesta el sobre de Paldea?" | Media | 2 | API de catálogo |
| Cancelar / pedir ayuda | "cancelar", "ayuda", "menú" | Baja | 1 | Control de flujo interno |

---

### Diagrama de flujo

```mermaid
flowchart TD
    A[/"start"/] --> B["Bienvenida: ¡Hola, entrenador! Soy PokéBot. Puedo ayudarte con sobres, ropa, otros artículos o el estado de tu pedido."]
    B --> C{¿Qué desea el usuario?}

    C -->|Ver sobres| D[Solicitar expansión o nombre del sobre]
    D --> D1{¿Existe en catálogo?}
    D1 -->|Sí, con stock| D2[Mostrar disponibilidad y precio]
    D1 -->|Sin stock| D3["Lo siento, no hay stock de ese sobre ahora. ¿Quieres ver otra expansión o que te avise cuando vuelva?"]
    D1 -->|No existe| D4["No encontré ese sobre. ¿Quieres ver las expansiones disponibles?"]
    D2 --> Z[¿Necesitas algo más?]
    D3 --> Z
    D4 --> Z

    C -->|Ver ropa| E[Solicitar prenda y/o talla]
    E --> E1{¿Existe la prenda y talla?}
    E1 -->|Sí| E2[Mostrar disponibilidad y precio]
    E1 -->|Talla agotada| E3["Esa talla está agotada. ¿Quieres ver otra talla o prenda?"]
    E1 -->|No existe| E4["No encontré esa prenda. ¿Quieres ver el catálogo de ropa?"]
    E2 --> Z
    E3 --> Z
    E4 --> Z

    C -->|Ver otros artículos| F[Mostrar categorías: peluches, mochilas, tazas, llaveros]
    F --> Z

    C -->|Estado de pedido| G[Solicitar número de pedido]
    G --> G1{¿Formato válido de 4 dígitos?}
    G1 -->|No| G2["Ese número no parece válido. Debe tener 4 dígitos. ¿Puedes verificarlo?"]
    G2 --> G
    G1 -->|Sí| G3{¿Existe el pedido?}
    G3 -->|Sí| G4[Mostrar estado del pedido]
    G3 -->|No| G5["No encontré un pedido con ese número. ¿Quieres intentar de nuevo o hablar con un agente?"]
    G4 --> Z
    G5 --> Z



    C -->|Mensaje no reconocido| H["No entendí eso. Puedo ayudarte con sobres, ropa, otros artículos o el estado de tu pedido. Escribe 'ayuda' si necesitas ver las opciones de nuevo."]
    H --> C
    
    C -->|cancelar / ayuda en cualquier punto| I[Volver al menú principal]
    I --> B

    Z -->|Sí| C
    Z -->|No| J["Despedida: ¡Gracias por visitar PokeThings! Atrápalos a todos 🎴"]
```

---

### Diálogo de muestra (camino feliz)

Intención principal: **consultar disponibilidad de sobres**.

```
Usuario: Hola
Bot: ¡Hola, entrenador! Soy PokéBot 🎴. Puedo ayudarte con sobres, ropa, otros
     artículos o el estado de tu pedido. ¿Qué necesitas?
Usuario: Quiero ver sobres
Bot: Claro. ¿De qué expansión buscas el sobre? Por ejemplo: Scarlet & Violet,
     Paldea Evolved, Obsidian Flames.
Usuario: Scarlet & Violet
Bot: Sí tenemos disponible el sobre de Scarlet & Violet. Precio: $6.50 c/u,
     con 3 unidades en existencia. ¿Necesitas algo más?
Usuario: No, gracias
Bot: ¡Gracias por visitar PokeThings! Atrápalos a todos 🎴
```

De este guion salen: un dato obligatorio (nombre de la expansión), una llamada a la API de catálogo de sobres, y un cierre que ofrece continuar. Los escenarios alternativos (sobre agotado, expansión inexistente, cambio de tema) están representados en el diagrama de flujo, no en este guion.

---
