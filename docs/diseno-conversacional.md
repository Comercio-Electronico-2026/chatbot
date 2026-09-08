## 1. Investigación del Usuario y Lista de Chequeo

* **¿Quién usará el chatbot?**  
  Clientes de CET STORE: profesionales y/o entusiastas de la tecnología, especialmente interesados en periféricos, componentes y almacenamiento de computadoras.

* **¿Qué problema o problemas resuelve?**  
  Proporcionará atención 24/7 a los clientes ante dudas frecuentes sobre productos (disponibilidad, precio, etc.), consultas sobre sus pedidos (estado) e información sobre formas de pago.

* **¿Qué necesidades específicas tienen?**  
  Conocer disponibilidad de stock en tiempo real, verificar si su orden de compra ya fue procesada/despachada y acceder a enlaces rápidos de compra sin tener que buscar manualmente en la página web.

* **¿Qué preguntas se pueden hacer?**  
  - ¿Cuál es el estado de mi pedido #512?
  - ¿Cuáles son los métodos de pago?
  - ¿Tienen disponible Memorias RAM de 16 GB?

* **¿Qué tipo de respuesta espero en cada caso?**  
  - Para productos: Botones o listas con enlaces directos a la tienda web.
  - Para pedidos: Número entero (ID del pedido).
  - Para pagos: Texto con información clara.

* **¿Cuál es su edad, ocupación, intereses, nivel de experiencia con tecnología?**  
  Entre 18 y 40 años. Nivel tecnológico medio-alto. Valoran respuestas rápidas y directas, sin exceso de formalidades.

* **¿Cuáles son los escenarios alternativos (errores, datos faltantes, cambio de tema)?**  
  - Se ingresa un ID de pedido que no existe.
  - El producto consultado no existe en el catálogo o no hay stock.
  - El usuario abandona o cancela la operación a la mitad.

* **UI, Accesibilidad:**  
  Menú enumerado y botones para que el usuario sepa exactamente qué debe escribir o solicitar sin adivinar comandos.

* **¿Cómo haré para validar mi prototipo?**  
  Técnica de Mago de Oz y evaluación con Heurísticas de Grice (Actividad 4).

* **Privacidad (Recolectar datos: para qué, cuáles y cuándo eliminarlos):**  
  Se recolecta el ID de Telegram y el número de pedido temporalmente para consultar la API. Los datos de sesión se eliminan al finalizar la consulta o al usar el comando de cancelación.

---

## 2. Inventario de Intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
| :--- | :--- | :--- | :--- | :--- |
| **Buscar productos** | "¿Tienen mouse RGB con entrada USB?" | Alta | 1 | Término / API WooCommerce |
| **Consultar Pedidos** | "Estado de mi pedido 2048" | Alta | 1 | ID Pedido (entero) / API WooCommerce |
| **Consultar formas de pago** | "¿Aceptan transferencias bancarias?" | Media | 2 | Ninguno / Texto informativo |
| **Cancelar interacción** | "/cancelar", "salir" | Baja | 3 | Ninguno / Lógica del bot |
| **Solicitar ayuda** | "/ayuda" | Baja | 3 | Ninguno / Lógica del bot |

---

## 3. Diálogo de Muestra (Camino Feliz)

**Usuario:** `/start`  
**Bot:** Hola, bienvenido a CET STORE. Soy CETBOT, ¿cómo te ayudo hoy?  
1. Consultar un producto  
2. Consultar estado de mi pedido  
3. Métodos de pago  

**Usuario:** 1  
**Bot:** ¿Qué producto o componente estás buscando? Escribe el nombre o la categoría y te traeré la información importante.  

**Usuario:** SSD KINGSTON 1TB  
**Bot:** Encontré estos excelentes productos para ti:  
- **SSD KINGSTON 1TB** — $85.00 (15 unidades disponibles)  
Enlace directo: https://tiendapr21064.duckdns.org/producto/ssd-kingston-1tb  

¿Puedo ayudarte con algo más?  

**Usuario:** No  
**Bot:** Gracias por preferir CET STORE. Presiona `/start` cuando necesites ayuda otra vez.

---

## 4. Diagrama de Flujo de la Conversación

```mermaid
flowchart TD
    A([Inicio: /start]) --> B[Bot: Saludo y Menú Principal]
    B --> C{Selección del usuario}
    
    C -->|Opción 1: Producto| D[Bot pide nombre del producto]
    D --> E[/Usuario ingresa texto/]
    E --> F{¿Existe en catálogo?}
    F -->|Sí| G[Bot muestra nombre, precio, stock y link]
    F -->|No| H[Bot: Producto no encontrado. Intenta con otra palabra]
    H --> D
    
    C -->|Opción 2: Pedido| I[Bot pide número de ID]
    I --> J[/Usuario ingresa número/]
    J --> K{¿ID válido y existe?}
    K -->|No| L[Bot: Error, pedido no encontrado]
    L --> I
    K -->|Sí| M[Bot muestra estado del pedido]
    
    C -->|Opción 3: Pagos| N[Bot muestra información de métodos de pago]
    
    C -->|/cancelar o texto inválido| O[Bot cancela flujo actual y orienta al menú]
    O --> B
    
    G --> P[Bot: ¿Puedo ayudarte con algo más?]
    M --> P
    N --> P
    
    P --> Q[/Usuario responde Sí o No/]
    Q -->|Sí| B
    Q -->|No| R([Fin de la conversación])
