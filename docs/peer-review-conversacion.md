# Peer Review

## Diseño evaluado
**Bot Tienda de Postres SP21013**

## Notas del Mago de Oz

### Guion 1 - Camino feliz
**Problema:** El diseño inicia con `/start`, pero no contempla un saludo normal como "Hola".

**Mejora:** Permitir saludos como "Hola" o "Buenos días" y mostrar el mismo mensaje de bienvenida.

### Guion 2 - Camino con fricción

**Entrada sin sentido:** No se contempla qué hacer si el usuario escribe `asdf` o `.`.

**Dato incorrecto:** Se menciona el pedido incorrecto en la lista de chequeo, pero no aparece en el diagrama.

**Cambio de tema:** El diagrama no permite cambiar de intención a mitad de una consulta.

**Fuera de alcance:** No existe una respuesta para solicitudes como "¿Me hacen un descuento?".

**Mejora general:** Agregar validaciones, límite de 3 intentos y opción de contacto humano.

---

## 5 preguntas de evaluación

### 1. Alcance y descubribilidad
**Veredicto:** Parcial.

**Evidencia:** La bienvenida explica que puede mostrar el menú y consultar pedidos, pero no menciona contacto humano ni ayuda.

**Mejora:** Agregar `/ayuda` y mencionar la opción de hablar con una persona.

### 2. Grice - cantidad, relación y manera
**Veredicto:** Resuelto.

**Evidencia:** Los mensajes son breves, claros y relacionados con la conversación.

**Mejora:** Acortar ligeramente el mensaje donde solicita el número de pedido.

### 3. Grice - calidad y relleno de datos
**Veredicto:** Parcial.

**Evidencia:** Usa una API de pedidos, pero no se especifica si devuelve toda la información que muestra el bot.

**Mejora:** Mostrar solo datos que la API pueda verificar y no volver a pedir el número si el usuario ya lo escribió.

### 4. Manejo de errores
**Veredicto:** No resuelto.

**Evidencia:** El diagrama no incluye entradas inválidas, cambio de tema, fuera de alcance, tres intentos ni derivación a humano.

**Mejora:** Agregar esas ramas al diagrama.

### 5. Reglas de producto
**Veredicto:** Parcial.

**Evidencia:** El bot ofrece continuar al final, pero no contempla `/ayuda`, `/cancelar` ni volver atrás.

**Mejora:** Agregar esas opciones al flujo.