# Documento de diseño conversacional — CitasBot

**Curso:** CET 115 — Comercio Electrónico, ciclo II/2026
**Bot:** Clínica Sonrisa Sana — reservas de citas
**Sesión:** 1 de 2 (Diseño y puesta en marcha)

---

## 1. Lista de chequeo

**¿Quién usará el chatbot?**
Pacientes actuales y nuevos de la clínica dental Sonrisa Sana que quieren agendar, consultar o cancelar una cita sin llamar por teléfono.

**¿Qué problema o problemas resuelve?**
Elimina la espera telefónica para agendar citas, permite consultar disponibilidad y gestionar citas fuera del horario de atención de la recepción, y reduce las inasistencias mediante recordatorios.

**¿Qué necesidades específicas tienen?**
- Agendar una cita rápido, sin llamar.
- Saber qué horarios hay disponibles antes de comprometerse.
- Poder cancelar o reprogramar sin fricción.
- Recibir confirmación clara de la cita (fecha, hora, servicio).

**¿Qué preguntas se pueden hacer?**
Agendar cita, consultar mis citas, cancelar cita, reprogramar cita, consultar servicios y precios, consultar ubicación/horario de atención, hablar con un humano.

**¿Qué tipo de respuesta espero en cada caso?**
- Servicio deseado → selección de lista (limpieza, consulta general, urgencia).
- Fecha deseada → texto/fecha en formato día-mes.
- Horario → selección de lista (entre las opciones disponibles).
- Número de cita (para cancelar/consultar) → número.
- Nombre y teléfono (primera vez) → texto/número.

**¿Cuál es su edad, ocupación, intereses, nivel de experiencia con tecnología?**
Adultos de 18 a 65 años, ocupaciones variadas (estudiantes, empleados, adultos mayores). Se asume nivel tecnológico medio-bajo: preferir botones y comandos cortos sobre lenguaje libre, evitar tecnicismos.

**¿Cuáles son los escenarios alternativos?**
- El usuario pide una fecha sin disponibilidad → el bot ofrece fechas/horas alternas.
- El usuario escribe un número de cita inválido → el bot pide reintentar o escribir /ayuda.
- El usuario cambia de tema a mitad de un flujo (por ejemplo, empieza a agendar y luego pregunta precios) → el bot responde la nueva pregunta y ofrece retomar el flujo anterior.
- El usuario no responde en el formato esperado (texto libre en vez de botón) → el bot reintenta interpretar la respuesta y, si no puede, repite la pregunta con las opciones.
- El usuario quiere cancelar el proceso en cualquier punto → comando /cancelar siempre disponible.

**¿UI, Accesibilidad?**
Uso de teclado de respuesta (botones) de Telegram para reducir errores de tipeo y evitar que el usuario tenga que recordar comandos. Mensajes cortos, sin jerga médica. Todo el flujo funciona también si el usuario solo escribe texto (sin usar botones).

**¿Cómo haré para validar mi prototipo?**
Prueba de Mago de Oz con dos guiones (camino feliz y camino con fricción) en la Actividad 4, seguida de revisión con las heurísticas de Grice. Antes de producción, prueba de usabilidad con 3-5 usuarios reales midiendo tiempo para completar una reserva y tasa de errores.

**Pruebas de usabilidad, desempeño**
Se mide: tiempo para completar el agendamiento de una cita, número de veces que el usuario tuvo que repetir una respuesta, y si el usuario logró cancelar/consultar sin ayuda externa.

**¿Privacidad?**
**Recolectar datos: para qué, cuáles y cuándo eliminarlos**
Se recolecta: nombre, número de teléfono y motivo de consulta, únicamente para gestionar la cita y enviar el recordatorio. No se solicitan datos médicos sensibles dentro del chat. Los datos de contacto se eliminan de la base de citas 90 días después de la fecha de la cita, salvo que el paciente tenga otra cita futura agendada.

---

## 2. Inventario de intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
|---|---|---|---|---|
| Consultar disponibilidad | ¿tienen espacio el jueves? | Alta | 1 | API de agenda |
| Agendar una cita | quiero una cita con el odontólogo | Alta | 1 | API de agenda |
| Consultar mis citas | ¿cuándo es mi próxima cita? | Media | 2 | API de citas (por paciente) |
| Cancelar una cita | quiero cancelar mi cita | Media | 2 | API de citas |
| Reprogramar una cita | necesito cambiar la hora de mi cita | Media | 2 | API de citas |
| Consultar servicios y precios | ¿cuánto cuesta una limpieza? | Baja | 3 | Catálogo de servicios |
| Consultar ubicación y horario | ¿dónde están ubicados? | Baja | 3 | Dato estático |
| Hablar con un humano | quiero hablar con alguien | Baja | 3 | Escalamiento a recepción |

---

## 3. Diagrama de flujo

```mermaid
flowchart TD
    A["/start"] --> B["Bot: saludo + menú principal"]
    B --> C{"Usuario elige"}
    C -->|"Agendar cita"| D["Bot: ¿qué servicio necesitas?"]
    C -->|"Consultar mis citas"| M["Bot: pide teléfono registrado"]
    C -->|"Cancelar cita"| N["Bot: pide número de cita"]
    C -->|"Otro tema / no reconocido"| H["Bot: muestra ayuda y menú"]

    D --> E["Usuario indica servicio"]
    E --> F["Bot: pide fecha deseada"]
    F --> G["Usuario indica fecha"]
    G --> I{"¿Hay disponibilidad?"}
    I -->|"Sí"| J["Bot: muestra horarios disponibles"]
    I -->|"No"| K["Bot: ofrece fechas/horas alternas"]
    K --> G
    J --> L["Usuario elige horario"]
    L --> P["Bot: confirma cita agendada"]
    P --> Q{"¿Necesita algo más?"}
    Q -->|"Sí"| B
    Q -->|"No"| R["Bot: despedida"]

    M --> S{"¿Se encontraron citas?"}
    S -->|"Sí"| T["Bot: muestra citas activas"]
    S -->|"No"| U["Bot: informa que no hay citas y ofrece agendar"]

    N --> V{"¿Número de cita válido?"}
    V -->|"Sí"| W["Bot: confirma cancelación"]
    V -->|"No"| X["Bot: pide reintentar o escribir /ayuda"]

    H --> B
```

**Notas del diagrama:**
- El comando `/cancelar` está disponible en cualquier paso del flujo y regresa al usuario al menú principal (B).
- El comando `/ayuda` está disponible en cualquier paso y muestra las opciones sin perder el progreso del flujo actual.
- Cualquier respuesta fuera de formato dispara un reintento con la misma pregunta, no un error genérico.

---

## 4. Diálogo de muestra (camino feliz) — intención principal: Agendar una cita

```
Usuario: Hola
Bot: Hola, soy CitasBot de Clínica Sonrisa Sana. Puedo ayudarte a agendar,
     consultar o cancelar una cita. ¿Qué necesitas?

Usuario: Quiero agendar una cita
Bot: Claro. ¿Qué servicio necesitas: limpieza, consulta general o urgencia?

Usuario: Limpieza
Bot: Perfecto. ¿Qué día te gustaría? (ejemplo: jueves 11 de septiembre)

Usuario: El jueves 11 de septiembre
Bot: Tengo espacio a las 9:00 am y a las 3:00 pm ese día. ¿Cuál prefieres?

Usuario: 3:00 pm
Bot: Confirmado: limpieza dental el jueves 11 de septiembre a las 3:00 pm.
     Te enviaré un recordatorio un día antes. ¿Necesitas algo más?

Usuario: No, gracias
Bot: Perfecto, ¡nos vemos el jueves! Escribe /start cuando quieras agendar
     otra cita.
```

De este guion salen dos datos obligatorios (servicio y fecha), una llamada a la API de agenda para consultar y confirmar disponibilidad, y un cierre que ofrece continuar. Los escenarios alternativos (fecha sin cupo, número de cita inválido, cambio de tema) están representados en el diagrama de flujo de la sección 3, no en este guion.

---

## 5. Cambios tras la revisión entre pares (Actividad 4)

*(Completar después del Mago de Oz — agregar aquí al menos tres cambios, uno por línea, indicando de qué observación proviene cada uno.)*

- [ ]
- [ ]
- [ ]
