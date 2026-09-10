# Diseño Conversacional - El Foráneo Bot 🏠

Documento de diseño para el asistente virtual de alojamiento estudiantil en Telegram, correspondiente a la **Sesión 1 del Laboratorio 5** (CET 115).

---

## 1. Investigación del Usuario y Lista de Chequeo

### ¿Quién usará el chatbot?
Estudiantes universitarios (18 a 26 años), principalmente foráneos provenientes de departamentos fuera de San Salvador, que cursan estudios en universidades como la UES, UCA, UTEC o Matías Delgado y necesitan alquilar una habitación o pupilaje cerca de sus facultades.

### ¿Qué problema o problemas resuelve?
Reduce drásticamente el tiempo, la dispersión y la incertidumbre al buscar alojamiento. Permite consultar opciones disponibles mediante filtros rápidos (ubicación, ambiente por género y presupuesto) sin necesidad de navegar formularios extensos en la web ni consumir planes de datos móviles pesados.

### ¿Qué necesidades específicas tienen?
* Encontrar habitaciones que se ajusten a su capacidad de pago mensual ($80 - $200 USD).
* Filtrar por ambiente y normativas de género (Solo Señoritas, Solo Varones, Mixto).
* Conocer con claridad los servicios incluidos (agua, luz, Wi-Fi, derecho a cocina, parqueo).
* Inspeccionar detalles visuales (fotos y mapa de ubicación) de opciones preseleccionadas.
* Obtener un canal de contacto directo y seguro con el arrendador (WhatsApp) para coordinar visitas.

### ¿Qué preguntas se pueden hacer?
* *"Busco cuarto para señoritas cerca de la UES por menos de $150"*
* *"¿Tienen habitaciones para varones cerca de la UCA?"*
* *"¿Qué incluye el alojamiento #12?"*
* *"Requisitos del cuarto 15"*
* *"Contacto del cuarto 12"*

### ¿Qué tipo de respuesta espera en cada caso?
* **Universidad / Zona:** Texto libre o selección guiada (ej. UES, UCA, Santa Tecla).
* **Preferencia de género:** Selección cerrada (`Señoritas`, `Varones`, `Mixto`).
* **Presupuesto máximo:** Valor numérico en USD (ej. `150`).
* **Identificador de alojamiento:** Número entero / ID de catálogo (ej. `12`).

### Perfil demográfico y tecnológico
* **Edad:** 18 a 26 años.
* **Ocupación:** Estudiantes universitarios de pregrado.
* **Nivel tecnológico:** Alto en aplicaciones móviles de mensajería (Telegram y WhatsApp), habituados a la inmediatez. Prefieren respuestas concisas, tarjetas estructuradas y enlaces directos a vistas web enriquecidas para inspección visual.

### Escenarios alternativos y de fricción
1. **Presupuesto no numérico o inválido:** El usuario ingresa texto ("barato", "poco") o números negativos.
2. **Búsqueda sin coincidencias exactas:** El bot no interrumpe el flujo; informa la situación y sugiere de inmediato relajar el filtro de precio o explorar opciones en ambiente mixto.
3. **ID de habitación inexistente o mal escrito:** El bot solicita verificar el código a partir del listado previo.
4. **Comando de escape / reinicio global:** El usuario puede escribir `/cancel`, *"cancelar"* o presionar `/start` en **cualquier momento** de la interacción para reiniciar la consulta y limpiar el estado temporal.

### UI y Accesibilidad
* Mensajes breves (menos de 3 párrafos) adaptados a pantallas móviles.
* Uso sistemático de emojis como anclas visuales (🏠, 💵, 📋, 🌐, 📞, ⚠️).
* Enfoque de **búsqueda asistida híbrida**: el bot guía y filtra conversacionalmente, y enlaza hacia la ficha web (`https://elforaneo.com/alojamientos/{id}`) para la visualización de galerías fotográficas y mapa geolocalizado.

### Validación del Prototipo
* **Pruebas de usabilidad:** Evaluación mediante la técnica de Mago de Oz y las heurísticas de Grice en la Actividad 4 para comprobar naturalidad y ausencia de callejones sin salida.
* **Pruebas de desempeño:** Verificación de latencias menores a 1 segundo en el consumo de la API REST externa durante la Sesión 2.

### Privacidad y Manejo de Datos
* **Datos recolectados:** Telegram User ID, nombre público y parámetros temporales de la búsqueda en curso (zona, género, presupuesto).
* **Finalidad:** Gestión del estado conversacional y consulta a los endpoints de *El Foráneo*.
* **Retención y eliminación:** No se almacenan credenciales ni datos bancarios. Los estados temporales de sesión expiran tras 2 horas de inactividad o al invocar `/cancel`.

---

## 2. Inventario de Intenciones

| Intención 🎯 | Ejemplo de enunciado 💬 | Frecuencia 📊 | Prioridad ⚡ | Dato / Endpoint API 🔌 |
| :--- | :--- | :--- | :--- | :--- |
| **Buscar habitaciones** | *"Busco cuarto para señoritas cerca de la UES por menos de $150"* | Alta | 1 | `GET /api/alojamientos/?universidad={u}&max_precio={p}&genero={g}` |
| **Consultar detalle** | *"¿Qué incluye el alojamiento #12?"* o *"Detalle 12"* | Alta | 1 | `GET /api/alojamientos/{id}/` |
| **Contactar arrendador** | *"Quiero el WhatsApp del dueño del cuarto 12"* | Media | 2 | `GET /api/alojamientos/{id}/contacto/` |
| **Ayuda / Cancelar** | *"Ayuda"*, `"/cancel"`, *"cancelar"*, *"¿cómo funciona?"* | Media | 2 | Lógica interna del bot (sin llamada a API) |

---

## 3. Diálogo de Muestra (Camino Feliz con Enlace Web)

* **Usuario:** `/start`
* **Bot:** ¡Hola! 👋 Bienvenido a **El Foráneo Bot**. Te ayudo a encontrar habitación o pupilaje cerca de tu universidad en El Salvador. ¿Te gustaría buscar opciones disponibles ahora?
  * [🔍 Buscar cuarto]  [ℹ️ Ayuda]
* **Usuario:** Sí, quiero buscar cuarto.
* **Bot:** Perfecto. ¿Cerca de qué universidad o en qué zona buscas? (Ejemplo: UES, UCA, Santa Tecla).
* **Usuario:** UES
* **Bot:** ¿Qué ambiente o preferencia de género buscas? Responde: `Señoritas`, `Varones` o `Mixto`.
* **Usuario:** Señoritas
* **Bot:** Anotado. ¿Cuál es tu presupuesto mensual máximo en USD? (Ejemplo: 150).
* **Usuario:** 150
* **Bot:** Encontré 2 opciones para ti en El Foráneo:
  1. 🏠 **Habitación individual San Luis (UES)** - $130/mes (Incluye agua, luz y Wi-Fi). ID: `#12`
  2. 🏠 **Pupilaje cerca de Medicina** - $140/mes (Incluye Wi-Fi y cocina). ID: `#15`
  
  ¿Deseas ver más detalles de alguna? Puedes responder *"Detalle 12"* o *"Contactar 12"*.
* **Usuario:** Detalle 12
* **Bot:** 📋 **Detalles de Habitación #12:**
  * **Ubicación:** Col. San Luis, portón Minerva UES.
  * **Servicios:** Agua, luz, internet 100 Mbps, clóset y baño compartido.
  * **Restricciones:** Solo mujeres estudiantes, no fumar. Depósito de $60.
  
  🌐 **Fotos y ubicación en mapa:**
  https://elforaneo.com/alojamientos/12
  
  ¿Deseas el enlace de WhatsApp de la arrendadora para agendar visita?
* **Usuario:** Sí, por favor.
* **Bot:** Puedes comunicarte directamente al WhatsApp: `https://wa.me/50370000000`. ¿Deseas hacer otra búsqueda o consultar otro alojamiento?

---

## 4. Diagrama de Flujo Conversacional (Mermaid)

```mermaid
flowchart TD
    Start([Inicio: /start o saludo]) --> Saludo[Bot: Bienvenida y menú inicial]
    
    Saludo --> EsperaInicio[/Usuario responde confirmación o /buscar/]
    EsperaInicio --> CheckCancelStart{¿Escribió /cancel?}
    CheckCancelStart -- Sí --> CancelGlobal
    CheckCancelStart -- No --> CheckDeseaBuscar{¿Desea buscar?}
    
    CheckDeseaBuscar -- No / Ayuda --> MuestraAyuda[Bot: Muestra instrucciones de uso] --> Start
    CheckDeseaBuscar -- Sí --> PideZona[Bot solicita Universidad o Zona]
    
    PideZona --> InpZona[/Usuario ingresa zona/]
    InpZona --> CheckCancelZona{¿Escribió /cancel?}
    CheckCancelZona -- Sí --> CancelGlobal
    CheckCancelZona -- No --> PideGenero[Bot solicita Género: Señoritas / Varones / Mixto]
    
    PideGenero --> InpGenero[/Usuario ingresa género/]
    InpGenero --> CheckCancelGen{¿Escribió /cancel?}
    CheckCancelGen -- Sí --> CancelGlobal
    CheckCancelGen -- No --> CheckGeneroValido{¿Opción válida?}
    
    CheckGeneroValido -- No --> ErrorGenero[Bot: Indica Señoritas, Varones o Mixto] --> PideGenero
    CheckGeneroValido -- Sí --> PidePrecio[Bot solicita Presupuesto Máximo en USD]
    
    PidePrecio --> InpPrecio[/Usuario ingresa presupuesto/]
    InpPrecio --> CheckCancelPrecio{¿Escribió /cancel?}
    CheckCancelPrecio -- Sí --> CancelGlobal
    CheckCancelPrecio -- No --> CheckPrecioNum{¿Es número > 0?}
    
    CheckPrecioNum -- No --> ErrorPrecio[Bot: Ingresa un monto válido en números, ej: 150] --> PidePrecio
    CheckPrecioNum -- Sí --> ConsultaAPI[Llamada API: GET /api/alojamientos/]
    
    ConsultaAPI --> CheckResultados{¿Hay resultados?}
    CheckResultados -- No --> Sugerencia[Bot: Sin resultados exactos. Sugiere ampliar precio o ver Mixto] --> PidePrecio
    CheckResultados -- Sí --> MuestraLista[Bot lista alojamientos con ID y precio]
    
    MuestraLista --> EsperaAccion[/Usuario pide Detalle, Contacto o /cancel/]
    EsperaAccion --> CheckCancelFin{¿Escribió /cancel?}
    CheckCancelFin -- Sí --> CancelGlobal
    CheckCancelFin -- No --> CheckIDValido{¿ID existe en catálogo?}
    
    CheckIDValido -- No --> ErrorID[Bot: ID no encontrado. Verifica el número de la lista] --> EsperaAccion
    CheckIDValido -- Sí --> DetalleOContacto{¿Tipo de petición?}
    
    DetalleOContacto -- Detalle --> MuestraDetalle[Bot muestra resumen + Enlace web al anuncio con fotos]
    DetalleOContacto -- Contacto --> MuestraContacto[Bot entrega enlace directo de WhatsApp]
    
    MuestraDetalle --> PreguntaCierre[Bot: ¿Deseas el contacto o hacer otra consulta?]
    MuestraContacto --> PreguntaCierre
    PreguntaCierre --> FinCiclo([Continúa sesión o usuario finaliza])
    
    CancelGlobal[Bot: Operación cancelada] --> ResetState[Limpiar estado temporal de sesión] --> Start
