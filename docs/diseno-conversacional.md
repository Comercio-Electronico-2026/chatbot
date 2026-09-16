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
- "Quiero hablar con un agente"

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
- Falla la conexión con la API de catálogo o de pedidos. 

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
| Consultar disponibilidad de otros artículos | "¿tienen peluche de Pikachu?" | Media | 2 | API de catálogo (otros) |
| Consultar precio de un producto | "¿cuánto cuesta el sobre de Paldea?" | Media | 2 | API de catálogo |
| Hablar con un agente humano | "quiero hablar con una persona" | Baja | 2 | Reenvío a Grupo de Soporte en Telegram (chat_id / Reply) |
| Cancelar | "cancelar",  "menú" | Baja | 1 | Control de flujo interno |

---

### Diagrama de flujo

```mermaid
flowchart TD
    A(["/start"]) --> B["Bienvenida: menú principal (Sobres 🎴 / Ropa 👕 / Otros artículos 🎁 / Estado de pedido 📦)"]
    B --> C{"¿Qué desea el usuario?"}

    %% ---------- SOBRES ----------
    C -->|Ver sobres| D0{"¿El mensaje ya incluye la expansión?"}
    D0 -->|Sí| D1
    D0 -->|No| D["Preguntar expansión"]
    D --> DX{"¿Es cancelar/ayuda o cambio de tema?"}
    DX -->|Sí| X
    DX -->|No| D1{"¿Existe en catálogo?"}
    D1 -->|Con stock| D2["Mostrar disponibilidad y precio"]
    D1 -->|Sin stock| D3["Sin stock. Sugerir otra expansión o revisar más tarde"]
    D1 -->|No existe| D4["No encontré ese sobre. Sugerir ver expansiones disponibles"]
    D1 -->|Falla la API| D5["Error de conexión: reintentar o volver al menú"]
    D2 --> Z
    D3 --> Z
    D4 --> Z
    D5 -->|Reintentar| D1
    D5 -->|Menú| B

    %% ---------- ROPA ----------
    C -->|Ver ropa| E0{"¿El mensaje ya incluye prenda y talla?"}
    E0 -->|Sí| E1
    E0 -->|No| E["Preguntar prenda y/o talla"]
    E --> EX{"¿Es cancelar/ayuda o cambio de tema?"}
    EX -->|Sí| X
    EX -->|No| E1{"¿Existe la prenda y talla?"}
    E1 -->|Sí| E2["Mostrar disponibilidad y precio"]
    E1 -->|Talla agotada| E3["Sugerir otra talla o prenda"]
    E1 -->|No existe| E4["No encontré esa prenda"]
    E1 -->|Falla la API| E5["Error de conexión: reintentar o volver al menú"]
    E2 --> Z
    E3 --> Z
    E4 --> Z
    E5 -->|Reintentar| E1
    E5 -->|Menú| B

    %% ---------- OTROS ARTÍCULOS ----------
    C -->|Ver otros artículos| F0["Mostrar categorías: peluches, mochilas, tazas, llaveros"]
    F0 --> F1["Preguntar qué artículo interesa"]
    F1 --> FX{"¿Es cancelar/ayuda o cambio de tema?"}
    FX -->|Sí| X
    FX -->|No| F2{"¿Existe en catálogo?"}
    F2 -->|Sí| F3["Mostrar disponibilidad y precio"]
    F2 -->|Sin stock| F4["Sugerir otro artículo"]
    F2 -->|No existe| F5["No encontré ese artículo"]
    F3 --> Z
    F4 --> Z
    F5 --> Z

    %% ---------- ESTADO DE PEDIDO ----------
    C -->|Estado de pedido| G0{"¿El mensaje ya incluye el número de pedido?"}
    G0 -->|Sí| G1
    G0 -->|No| G["Preguntar número de pedido"]
    G --> GX{"¿Es cancelar/ayuda o cambio de tema?"}
    GX -->|Sí| X
    GX -->|No| G1{"¿Formato válido de 4 dígitos?"}
    G1 -->|No| G2["Formato inválido: explicar y pedir de nuevo"]
    G2 --> GC{"¿Van 3 intentos fallidos?"}
    GC -->|No| G
    GC -->|Sí| J
    G1 -->|Sí| G3{"¿Existe el pedido?"}
    G3 -->|Sí| G4["Mostrar estado del pedido"]
    G3 -->|No| G5["No encontré ese pedido: reintentar o hablar con agente"]
    G3 -->|Falla la API| G6["Error de conexión: reintentar o volver al menú"]
    G4 --> Z
    G5 -->|Reintentar| G
    G5 -->|Hablar con agente| J
    G6 -->|Reintentar| G3
    G6 -->|Menú| B

    %% ---------- AGENTE HUMANO ----------
    C -->|Hablar con un agente| J["Cambiar estado del usuario a 'EN_SOPORTE'"]
    J --> J1["Notificar al usuario: 'Un agente te atenderá. Escribe tu mensaje.' y alertar al Grupo de Soporte"]
    J1 --> J2["Esperar entrada del usuario o del agente"]
    J2 --> J3{"¿Qué evento ocurre?"}
    J3 -->|Usuario escribe mensaje| J4["Bot reenvía mensaje al Grupo Privado de Agentes"] --> J2
    J3 -->|Agente responde en grupo con 'Reply'| J5["Bot detecta cita y reenvía respuesta al chat privado del usuario"] --> J2
    J3 -->|Usuario presiona 'Salir de Soporte' o Agente usa '/cerrar'| J6["Restablecer estado del usuario a NORMAL"] --> Z

    %% ---------- MENSAJE NO RECONOCIDO ----------
    C -->|Mensaje no reconocido| H["No entendí eso. Mostrar de nuevo las opciones del menú"]
    H --> HC{"¿Van 3 intentos fallidos?"}
    HC -->|No| C
    HC -->|Sí| J

    %% ---------- INTERCEPCIÓN GLOBAL (cancelar / ayuda / cambio de tema) ----------
    X["Detectó 'cancelar', 'ayuda' o una intención distinta a la actual"] --> XQ{"¿Es cancelar o ayuda?"}
    XQ -->|Sí| B
    XQ -->|No, es otra intención| XC["Confirmar: '¿Dejamos esto y pasamos a [nueva intención]?'"]
    XC -->|Confirma| C
    XC -->|Prefiere seguir| C

    %% ---------- CIERRE DEL CICLO ----------
    Z{"¿Necesitas algo más?"}
    Z -->|Sí| B
    Z -->|No| M["Despedida: ¡Gracias por visitar PokeThings! Atrápalos a todos 🎴"]

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

De este guion salen: un dato obligatorio (nombre de la expansión), una llamada a la API de catálogo de sobres, y un cierre que ofrece continuar. Los escenarios alternativos (sobre agotado, expansión inexistente, cambio de tema, falla de API, límite de intentos) están representados en el diagrama de flujo, no en este guion.

---
