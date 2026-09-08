# Diseño conversacional — NutriGuía

## 1. Lista de chequeo

### ¿Quién usará el chatbot?
Personas mayores de 18 años interesadas en nutrición, vitaminas, minerales y alimentación consciente.

### ¿Qué problema resuelve?
Resuelve dudas rápidas sobre nutrientes, sus funciones y fuentes naturales. También orienta al usuario para solicitar una asesoría nutricional.

### ¿Qué necesidades específicas tienen?
- Obtener información clara y rápida.
- Comparar vitaminas o nutrientes.
- Consultar fuentes naturales.
- Solicitar una asesoría profesional.

### ¿Qué preguntas se pueden hacer?
- ¿Cuál es la diferencia entre vitamina B y vitamina B12?
- ¿Qué alimentos contienen vitamina K?
- ¿Cómo puedo obtener vitamina D naturalmente?
- ¿Para qué sirve la vitamina C?
- ¿Cómo solicito una asesoría?

### ¿Qué tipo de respuesta se espera?
- Texto breve.
- Listas de alimentos.
- Comparaciones entre nutrientes.
- Opciones de selección.

### ¿Cuál es su edad, ocupación, intereses y nivel tecnológico?
Personas mayores de 18 años, estudiantes, trabajadores o profesionales interesados en nutrición y bienestar, con conocimientos básicos o intermedios de tecnología.

### ¿Cuáles son los escenarios alternativos?
- Nutriente no reconocido.
- Información incompleta.
- Usuario cambia de tema.
- Solicita ayuda.
- Cancela la operación.
- Realiza una consulta médica fuera del alcance del bot.

### ¿UI y accesibilidad?
Se utilizarán mensajes cortos, lenguaje sencillo, listas numeradas y comandos como `/start`, `/ayuda` y `/cancelar`.

### ¿Cómo se validará el prototipo?
Mediante pruebas con usuarios, solicitándoles comparar nutrientes, consultar fuentes naturales, pedir ayuda y cancelar operaciones.

### Pruebas de usabilidad y desempeño
Se evaluará claridad de los mensajes, facilidad de uso, manejo de errores y tiempo de respuesta.

### ¿Privacidad?
No se solicitarán datos personales para consultas informativas. Solo se pedirán datos básicos cuando el usuario desee solicitar una asesoría.

### ¿Qué datos se recolectarán, para qué y cuándo se eliminarán?
Se utilizará el nombre del nutriente y el tipo de consulta para generar las respuestas. Para una asesoría podrán solicitarse datos básicos de contacto. Los datos que no sean necesarios no se conservarán.

---

## 2. Inventario de intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
|---|---|---|---|---|
| Diferenciar nutrientes | ¿Cuál es la diferencia entre vitamina B y B12? | Alta | 1 | API nutricional |
| Consultar fuentes naturales | ¿Qué alimentos contienen vitamina K? | Alta | 1 | API nutricional |
| Consultar función de un nutriente | ¿Para qué sirve la vitamina C? | Alta | 1 | API nutricional |
| Agendar asesoría | Quiero solicitar una asesoría | Baja | 2 | Sistema de citas |
| Solicitar ayuda | ¿Qué puedes hacer? | Media | 2 | No requiere API |
| Cancelar operación | Cancelar | Baja | 2 | No requiere API |

---

## 3. Diagrama de flujo de la conversación

```mermaid
flowchart TD
    A[/start] --> B[Mostrar bienvenida y menú]
    B --> C{Seleccionar opción}

    C -->|Comparar nutrientes| D[Solicitar dos nutrientes]
    D --> E{¿Datos completos y válidos?}
    E -->|Sí| F[Mostrar comparación]
    E -->|No| G[Solicitar datos nuevamente]
    G --> D

    C -->|Fuentes naturales| H[Solicitar nutriente]
    H --> I{¿Nutriente reconocido?}
    I -->|Sí| J[Mostrar fuentes naturales]
    I -->|No| K[Informar error y solicitar nuevamente]
    K --> H

    C -->|Agendar asesoría| L[Solicitar datos necesarios]
    L --> M{¿Datos completos?}
    M -->|Sí| N[Confirmar solicitud]
    M -->|No| L

    C -->|Ayuda| O[Mostrar opciones disponibles]
    O --> B

    C -->|Cancelar| P[Cancelar operación]
    P --> B

    C -->|Entrada no válida| Q[Informar opción no reconocida]
    Q --> B

    F --> R{¿Desea otra consulta?}
    J --> R
    N --> R

    R -->|Sí| B
    R -->|No| S[Despedida]
```

---

## 4. Diálogo de muestra — Camino feliz

**Usuario:** /start

**Bot:** ¡Hola! Soy NutriGuía, tu Asesor Virtual de Nutrición Natural. Puedo ayudarte a:

1. Comparar nutrientes.
2. Consultar fuentes naturales.
3. Solicitar una asesoría nutricional.

¿Qué deseas hacer?

**Usuario:** Consultar fuentes naturales.

**Bot:** Claro. ¿Qué vitamina o nutriente deseas consultar?

**Usuario:** Vitamina B12.

**Bot:** Algunas fuentes de vitamina B12 son:

1. Pescados.
2. Carnes.
3. Huevos.
4. Leche y productos lácteos.
5. Alimentos fortificados.

Esta información tiene fines educativos y no sustituye una recomendación profesional.

¿Deseas realizar otra consulta?

**Usuario:** No.

**Bot:** Gracias por utilizar NutriGuía.
