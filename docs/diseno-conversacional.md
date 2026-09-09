# Diseño Conversacional: Bot de Comercio Electrónico CET115

**Estudiante:** Carnet GT22004  
**Asignatura:** Comercio Electrónico (CET115 - Ciclo II / 2026)  
**Docente:** Ing. William Ernesto Vides Ortez  
**Entregable:** Guía 5  - Diseño y puesta en marcha del bot
---

## 1. Lista de Chequeo de Diseño Conversacional 

* **¿Quién usará el chatbot?:** Deportistas, entusiastas del entrenamiento y clientes de la tienda deportiva en línea que buscan equipamiento, calzado, indumentaria o suplementos, y desean consultar disponibilidad, precios u ofertas sin recorrer manualmente el sitio web.
* **¿Qué problema o problemas resuelve?:** Automatiza la atención de primer contacto para preguntas repetitivas sobre artículos deportivos, promociones de temporada y existencias, brindando respuestas inmediatas y evitando esperas de soporte.
* **¿Qué necesidades específicas tienen?:** Confirmar rápidamente si un artículo deportivo está en inventario, ver precios y descuentos activos con el comando `/ofertas`, y recibir el enlace directo para comprar en la tienda.
* **¿Qué preguntas se pueden hacer?:**
  * "¿Tienen disponible [producto]?"
  * "¿Cuáles son las ofertas de hoy?" 
  * "¿Qué precio tiene [producto]?"
  * "¿Qué categorías deportivas manejan?"
  * "¿Qué comandos puedo utilizar?"
* **¿Qué tipo de respuesta espero en cada caso?:**
  * *Consulta de artículo:* Texto estructurado con el nombre del [producto], precio regular/rebajado en USD, disponibilidad (en stock / agotado) y enlace web directo.
  * *Consulta de promociones (`/ofertas`):* Listado de [producto] en descuento, mostrando precio anterior, precio rebajado y disponibilidad.
  * *Consulta general de catálogo:* Lista de [categoría] deportivas disponibles.
  * *Navegación / Comandos:* Respuestas directas y concisas mediante comandos (`/start`, `/catalogo`, `/ofertas`, `/cancelar`).
* **¿Cuál es su edad, ocupación, intereses, nivel de experiencia con tecnología?:** Usuarios de 16 a 50 años, estudiantes, atletas o personas interesadas en el fitness y la actividad física, con nivel tecnológico básico a intermedio habituados al uso de aplicaciones de mensajería.
* **¿Cuáles son los escenarios alternativos (errores, datos faltantes, el usuario cambia de tema)?:**
  * *Sin promociones vigentes:* Si no hay artículos deportivos con rebaja al usar `/ofertas`, el bot notifica que no hay ofertas activas y sugiere ver el catálogo general.
  * *Sin coincidencias en búsqueda:* Si la búsqueda de [producto] no devuelve resultados, el bot informa la falta de existencias y lista las [categoría] deportivas principales.
  * *Entrada vacía o muy corta:* El bot solicita escribir el nombre del artículo deportivo de forma más descriptiva.
  * *Mensaje no reconocido (Fallback):* El bot responde con amabilidad explicando que no comprende la consulta y despliega los comandos disponibles.
  * *Interrupción o cambio de tema:* El usuario puede escribir `/cancelar` para anular la consulta actual y volver al inicio.
* **¿UI, Accesibilidad?:** Mensajes breves y fáciles de leer en pantalla móvil, uso de viñetas para características y opciones guiadas sin tecnicismos.
* **¿Cómo haré para validar mi prototipo? (Pruebas de usabilidad, desempeño):** Validación con la técnica de Mago de Oz en la revisión entre pares, evaluando la claridad y pertinencia turno a turno según las heurísticas de Grice.
* **¿Privacidad? (Recolectar datos: para qué, cuáles y cuándo eliminarlos):** Solo se procesa el identificador de chat de Telegram y la palabra clave del [producto] deportivo consultado. No se solicitan datos personales ni métodos de pago en el chat, y los estados temporales de sesión se liberan al terminar la conversación.

---

## 2. Inventario de Intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
| :--- | :--- | :--- | :--- | :--- |
| `saludo_inicio` | `/start`, `hola`, `buenas tardes` | Alta | 1 | Ninguno |
| `consultar_catalogo` | `ver productos`, `/catalogo`, `¿qué venden?` | Alta | 1 | API de catálogo (Categorías/Productos) |
| `consultar_ofertas` | `/ofertas`, `descuentos deportivos`, `promociones de hoy` | Alta | 1 | API de catálogo (filtro on_sale) |
| `buscar_producto` | `¿tienen [producto]?`, `precio de [producto]`, `buscar [producto]` | Alta | 1 | Término de búsqueda / API de catálogo |
| `ayuda_menu` | `/help`, `ayuda`, `¿cómo funciona?` | Media | 2 | Ninguno |
| `cancelar_accion` | `/cancelar`, `salir`, `olvídalo` | Baja | 2 | Ninguno |

---

## 3. Diálogo de Muestra (Camino Feliz)

**Usuario:** `/start`

**Bot:** ¡Hola! 👋 Soy tu asistente en la tienda deportiva.\nEscribe el nombre de un producto, o usa /ofertas para descuentos y /catalogo para ver categorías.

**Usuario:** `/ofertas`

**Bot:** 🔥 **Promociones Deportivas Actuales:**

1. **[producto]**
   * **Categoría:** [categoría]
   * **Precio de oferta:** $0.00 USD *(Antes: $0.00 USD)*
   * **Disponibilidad:** En existencia
   * **Enlace:** `https://gt22004.duckdns.org/producto/[producto]`

2. **[producto]**
   * **Categoría:** [categoría]
   * **Precio de oferta:** $0.00 USD *(Antes: $0.00 USD)*
   * **Disponibilidad:** En existencia
   * **Enlace:** `https://gt22004.duckdns.org/producto/[producto]`

¿Deseas consultar detalles de algún [producto] o revisar una [categoría] deportiva?

**Usuario:** ¿Tienen disponible [producto]?

**Bot:** Sí, tenemos disponible el artículo.
* **Producto:** [producto]
* **Categoría:** [categoría]
* **Precio:** $0.00 USD
* **Disponibilidad:** En existencia
* **Enlace directo:** `https://gt22004.duckdns.org/producto/[producto]`

¿Deseas buscar otro artículo o consultar alguna otra sección?

**Usuario:** No, eso sería todo, gracias.

**Bot:** Con gusto. Si necesitas consultar algo más adelante, escribe `/start`, `/catalogo` o `/ofertas`. ¡Mucho éxito en tu entrenamiento!


---

## 4. Diagrama de Flujo Conversacional

```mermaid
flowchart TD
    %% Estilos visuales
    classDef startEnd fill:#1e293b,stroke:#0ea5e9,stroke-width:2px,color:#fff;
    classDef apiCall fill:#0f766e,stroke:#14b8a6,stroke-width:2px,color:#fff;
    classDef decision fill:#334155,stroke:#94a3b8,stroke-width:2px,color:#fff;
    classDef errorState fill:#7f1d1d,stroke:#ef4444,stroke-width:1px,color:#fff;
    classDef response fill:#1e293b,stroke:#64748b,stroke-width:1px,color:#fff;

    subgraph INICIO["Entrada y Menú"]
        Inicio([Inicio: Usuario escribe /start]):::startEnd --> Bienvenida["Bot muestra saludo y opciones"]:::response
        Bienvenida --> EntradaUsuario[/"Usuario ingresa mensaje o comando"/]
        EntradaUsuario --> DetectaIntencion{¿Qué intención detecta?}:::decision
    end

    subgraph OFERTAS["Flujo: Ofertas y Promociones"]
        DetectaIntencion -- "/ofertas" --> LlamaAPIOfertas["Consultar ofertas vía API"]:::apiCall
        LlamaAPIOfertas --> HayOfertas{¿Hay ofertas?}:::decision
        HayOfertas -- Sí --> MuestraOfertas["Listar [producto] con precio rebajado"]:::response
        HayOfertas -- No --> SinOfertas["Informar: Sin ofertas vigentes"]:::errorState
    end

    subgraph CATALOGO["Flujo: Catálogo"]
        DetectaIntencion -- "/catalogo" --> LlamaAPICat["Consultar categorías vía API"]:::apiCall
        LlamaAPICat --> MuestraCategorias["Listar [categoría] disponibles"]:::response
    end

    subgraph BUSQUEDA["Flujo: Búsqueda de Producto"]
        DetectaIntencion -- "Búsqueda de producto" --> ExtraeTermino["Extraer nombre de [producto]"]:::response
        ExtraeTermino --> ValidaTermino{¿Texto válido?}:::decision

        ValidaTermino -- "No (vacío)" --> PideTermino["Solicitar término válido"]:::errorState
        PideTermino -- "Reintenta" --> ExtraeTermino

        ValidaTermino -- "Sí" --> ConsultaAPI["Consultar API con [producto]"]:::apiCall
        ConsultaAPI --> Hallado{¿Coincidencias?}:::decision

        Hallado -- No --> SinResultados["Notificar producto no encontrado"]:::errorState
        SinResultados --> SugiereCat["Sugerir ver [categoría]"]:::response

        Hallado -- Sí --> ValidaStock{¿Hay stock?}:::decision
        ValidaStock -- Sí --> MuestraDisponible["Mostrar [producto], precio y stock"]:::response
        ValidaStock -- No --> MuestraAgotado["Mostrar [producto] como Agotado"]:::errorState
    end

    subgraph AYUDA["Flujo: Ayuda y Fallback"]
        DetectaIntencion -- "Ayuda o Desconocido" --> MuestraAyuda["Desplegar comandos: /start, /catalogo, /ofertas"]:::response
    end

    subgraph CIERRE["Cierre y Continuación"]
        MuestraOfertas & SinOfertas & MuestraCategorias & SugiereCat & MuestraDisponible & MuestraAgotado & MuestraAyuda --> FinTurno["Ofrecer realizar otra consulta"]:::response
        PideTermino -- "/cancelar" --> FinTurno
        
        FinTurno --> Continuar{¿Continuar?}:::decision
        Continuar -- Sí --> Bienvenida
        Continuar -- "No / /cancelar" --> Fin([Fin de la sesión]):::startEnd
    end
```
---

### Ajustes y Correcciones al Documento

* **Sección 1 (Lista de Chequeo):**
  * **¿Qué tipo de respuesta espero en cada caso?:** Agregar qué ocurre si la búsqueda devuelve varios artículos parecidos (ejemplo: *"El bot muestra una lista de 3 a 5 coincidencias con sus respectivos enlaces para que el usuario elija"*).
  * **Escenarios alternativos:** Añadir la contingencia de fallo de infraestructura: *"Fallo del servidor/API: El bot notifica un inconveniente temporal de conexión y sugiere intentar nuevamente en un momento"*.

* **Sección 2 (Inventario de Intenciones):**
  * **Intención `ayuda_menu`:** Incluir explícitamente el enunciado de ejemplo `/help` y definir si se manejará `/help` o `/ayuda` (o ambos como alias).

* **Sección 3 (Diálogo de Muestra):**
  * **Menú de bienvenida y opciones de cierre:** Asegurar que el bot mencione explícitamente los comandos `/cancelar` y `/help` para mantener coherencia con las intenciones declaradas y los requisitos de la guía.
