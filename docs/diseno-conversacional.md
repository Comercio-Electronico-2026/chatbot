# Diseño Conversacional - MusicHub Bot

## 1. Lista de Chequeo de Diseño Conversacional

### ¿Quién usará el chatbot?: 
Clientes de la tienda en línea y amantes de la música que buscan información rápida sobre discos/vinilos o el estado de sus compras.

### ¿Qué problema o problemas resuelve?: 
Automatiza consultas frecuentes sobre el catálogo y pedidos, reduciendo la fricción para el usuario y el tiempo de espera.

### ¿Qué necesidades específicas tienen?: 
Saber si un artista o álbum está disponible, conocer detalles (formato, año, precio) y verificar en qué estado se encuentra su orden.

### ¿Qué preguntas se pueden hacer?: 
* "¿Tienen discos de Daft Punk?" 
* "¿Cuánto cuesta el álbum?" 
* "¿Qué pasó con mi pedido?"

### ¿Qué tipo de respuesta espero en cada caso?: 
* Fichas de producto en texto (Artista, Álbum, Año, Formato, Precio) para el catálogo.
* Respuestas de una línea indicando el estado del pedido (Procesando, Completado, etc.).

### Perfil de usuario 
* **Edad** entre 18 a 55 años.
* Coleccionistas de formatos físicos.
* Manejo intermedio de tecnología y compras online.

### ¿Cuáles son los escenarios alternativos?: 
El disco buscado no arroja resultados en la API, o el ID del pedido ingresado no existe.

### UI, Accesibilidad: 
* Textos estructurados por líneas para fácil lectura.
* Uso de emojis para hacer la lectura amena y clara.

### ¿Cómo haré para validar mi prototipo?: 
Simulación "Mago de Oz" y validación con las heurísticas de Grice en la revisión por pares.

### Privacidad: 
Solo se pide el número de orden generado por WooCommerce. No se solicitan datos bancarios, contraseñas ni direcciones completas en el chat. Los datos en memoria temporal se descartan al finalizar la interacción.

## 2. Inventario de Intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
| :--- | :--- | :--- | :--- | :--- |
| Iniciar interacción | `/start` | Alta | 1 | Mensaje estático de bienvenida |
| Consultar catálogo | `/catalogo` | Alta | 1 | `GET /api/products` |
| Estado de un pedido | `/pedido` | Alta | 1 | `GET /api/orders/{id}` |
| Ayuda / Cancelar | `/ayuda`, `/salir` | Baja | 2 | Reglas del bot y reinicio del flujo |

## 3. Diálogo de Muestra (Camino Feliz)

**Usuario:** `/start`
* **Bot:** ¡Hola! Bienvenido al bot de MusicHub 🎵. Por favor, selecciona una opción para continuar:
`/catalogo` - Buscar discos y vinilos en la tienda
`/pedido` - Revisar el estado de tu compra
* **Usuario:** `/catalogo`
* **Bot:** ¡Genial! Escribe el nombre del artista o álbum que buscas.
* **Usuario:** Daft Punk
* **Bot:** Encontré esto en la tienda: \
**Random Access Memories** - Daft Punk (2013) \
Formato: Vinilo \
Precio: $35.00 \
¿Te puedo ayudar con algo más? \
`/catalogo` - Hacer otra búsqueda \
`/pedido` - Revisar una orden \
`/salir` - Terminar
* **Usuario:** `/pedido`
* **Bot:** Claro, por favor escribe el número de tu pedido (lo encuentras en el correo de confirmación de tu compra).
* **Usuario:** 0306
* **Bot:** Tu pedido #0306 está actualmente **Procesando** 📦. Te avisaremos cuando sea enviado. \
¿Deseas hacer otra consulta? \
`/catalogo` - Hacer otra búsqueda \
`/pedido` - Revisar una orden \
`/salir` - Terminar
* **Usuario:** `/salir`
* **Bot:** ¡Que disfrutes la música! Escribe `/start` para volver a hablar.

## 4. Diagrama de Flujo

```mermaid
flowchart TD
    A([Inicio: /start]) --> B[Mensaje de bienvenida y opciones]
    B --> C{Comando ingresado}
    
    C -->|/catalogo| D[Pedir término de búsqueda]
    D --> E[Llamada API /products]
    E --> F{¿Hay resultados?}
    F -->|Sí| G[Mostrar ficha del producto]
    F -->|No| H[Avisar 'Sin resultados']
    
    C -->|/pedido| I[Solicitar número de orden]
    I --> J{¿Formato válido?}
    J -->|No| K[Indicar error de formato]
    K --> I
    J -->|Sí| L[Llamada API /orders/id]
    L --> M{¿Orden existe?}
    M -->|Sí| N[Mostrar estado de la orden]
    M -->|No| O[Avisar que no se encontró]
    
    C -->|Comando desconocido| P[Mensaje de ayuda]
    P --> B
    
    G --> Q[Mostrar menú final]
    H --> Q
    N --> Q
    O --> Q
    
    Q --> R([Fin del flujo])
