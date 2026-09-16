# Diseño Conversacional - ElForaneo Bot 🏠

Documento de diseño para el asistente virtual de alojamiento estudiantil en Telegram, correspondiente al **Laboratorio 5 (Sesión 1 y 2)** - CET 115.

---

## 1. Investigación del Usuario y Lista de Chequeo

### ¿Quién usará el chatbot?
Estudiantes universitarios (18 a 26 años), principalmente foráneos provenientes de departamentos fuera de San Salvador, que cursan estudios en UES, UCA, UTEC o Matías Delgado y necesitan alquilar una habitación o pupilaje cerca de sus facultades.

### ¿Qué problema resuelve?
Reduce el tiempo y la incertidumbre al buscar alojamiento universitario, permitiendo consultar opciones verificadas mediante filtros rápidos (ubicación, género y presupuesto) desde mensajería ligera.

### Delimitación de alcance (Qué hace y qué NO hace)
* **Qué hace el bot:**
  * Filtra habitaciones disponibles por universidad/zona, presupuesto y restricción de género.
  * Muestra fichas resumen con servicios incluidos, precios y enlaces web a fotografías.
  * Entrega enlaces directos de WhatsApp para contactar a los arrendadores verificados.
* **Qué NO hace el bot:**
  * **No procesa pagos ni anticipos:** Las transacciones económicas se acuerdan directamente con el dueño fuera del bot.
  * **No redacta ni firma contratos de arrendamiento:** No sustituye los acuerdos legales entre arrendador y arrendatario.
  * **No gestiona reservas formales en firme ni visitas guiadas autónomas:** Solo facilita el puente de contacto.

### Perfil demográfico y tecnológico
* **Edad:** 18 a 26 años.
* **Nivel tecnológico:** Alto en mensajería móvil (Telegram, WhatsApp). Valoran respuestas inmediatas, comandos guiados por botones y enlaces directos a fichas web enriquecidas.

### Escenarios alternativos y de fricción
1. **Presupuesto no numérico o inválido:** Validación estricta con contador de reintentos (máximo 3 intentos antes de reiniciar).
2. **Búsqueda sin coincidencias:** Sugerencia inmediata de relajar filtros (opción de ajustar presupuesto o cambiar preferencia de género a Mixto).
3. **ID de habitación inexistente:** Notificación de error con límite de 3 reintentos.
4. **Comando global de escape:** Soporte para `/cancel` o *"cancelar"* en cualquier etapa del flujo para abortar la operación y reiniciar el estado.

---

## 2. Inventario de Intenciones

| Intención 🎯 | Ejemplo de enunciado 💬 | Frecuencia 📊 | Prioridad ⚡ | Dato / Endpoint API 🔌 |
| :--- | :--- | :--- | :--- | :--- |
| **Buscar habitaciones** | *"Busco cuarto para señoritas cerca de la UES por menos de $150"* | Alta | 1 | `GET /api/alojamientos/?universidad={u}&max_precio={p}&genero={g}` |
| **Consultar detalle** | *"Detalle 12"* o *"¿Qué incluye el alojamiento #12?"* | Alta | 1 | `GET /api/alojamientos/{id}/` |
| **Contactar arrendador** | *"Contactar 12"* o *"WhatsApp del dueño del 12"* | Media | 2 | `GET /api/alojamientos/{id}/contacto/` |
| **Ayuda / Cancelación** | `"/cancel"`, *"cancelar"*, `"/ayuda"` | Media | 2 | Lógica interna del bot (sin llamada a API) |

---

## 3. Diálogo de Muestra (Camino Feliz)

* **Usuario:** `/start`
* **Bot:** ¡Hola! 👋 Bienvenido a **ElForaneo Bot**. Te ayudo a encontrar habitaciones y pupilajes universitarios en El Salvador.
  * [/buscar] Buscar alojamiento
  * [/ayuda] Conocer qué puedo hacer
* **Usuario:** /buscar
* **Bot:** ¿Cerca de qué universidad o en qué zona buscas? (Ejemplo: UES, UCA, Santa Tecla).
* **Usuario:** UES
* **Bot:** ¿Qué ambiente o preferencia de género necesitas? Opciones: `Señoritas`, `Varones` o `Mixto`.
* **Usuario:** Señoritas
* **Bot:** ¿Cuál es tu presupuesto mensual máximo en USD? (Ejemplo: 150).
* **Usuario:** 150
* **Bot:** Encontré 2 opciones disponibles en El Foráneo:
  1. 🏠 **Habitación individual San Luis (UES)** - $130/mes (Agua, luz y Wi-Fi). ID: `#12`
  2. 🏠 **Pupilaje cerca de Medicina** - $140/mes (Wi-Fi y cocina). ID: `#15`
  
  Escribe *"Detalle <ID>"* para ver más información o *"Contactar <ID>"* para hablar con el arrendador.
* **Usuario:** Detalle 12
* **Bot:** 📋 **Detalles de Habitación #12:**
  * **Ubicación:** Col. San Luis, portón Minerva UES.
  * **Servicios:** Agua, luz, internet 100 Mbps, clóset, baño compartido.
  * **Condiciones:** Exclusivo señoritas estudiantes. Depósito: $60.
  🌐 **Fotos completas:** https://elforaneo.com/alojamientos/12
  
  ¿Deseas el contacto de WhatsApp de esta habitación? Responde **Sí** o escribe **/buscar** para otra consulta.
* **Usuario:** Sí
* **Bot:** Puedes comunicarte directamente al WhatsApp del arrendador: `https://wa.me/50370000000`.
  ¿Deseas realizar otra búsqueda o finalizar? Responde **/buscar** o **/salir**.
* **Usuario:** /salir
* **Bot:** ¡Mucho éxito en tu búsqueda! Escribe `/start` cuando quieras volver a consultar.

---

## 4. Diagrama de Flujo Conversacional (Mermaid)

```mermaid
flowchart TD
    Start([Inicio: /start o saludo]) --> Saludo[Bot: Bienvenida y menú. Opciones: /buscar o /ayuda]
    
    Saludo --> EsperaAccionInicial[/Usuario selecciona opción/]
    EsperaAccionInicial --> CheckGlobalCancel1{¿Escribió /cancel?}
    CheckGlobalCancel1 -- Sí --> CancelGlobal
    CheckGlobalCancel1 -- No --> CheckOpcion{¿Qué solicitó?}
    
    CheckOpcion -- /ayuda --> MuestraAyuda[Bot explica funciones, alcance y límites] --> Start
    CheckOpcion -- /buscar o mensaje libre --> CheckSlots{¿Entrada contiene zona, género y precio?}
    
    CheckSlots -- Sí (Slot filling completo) --> ConsultaAPI
    CheckSlots -- No (Faltan parámetros) --> PideZona[Bot solicita Universidad o Zona]
    
    %% Recolección: Zona
    PideZona --> InpZona[/Usuario ingresa zona/]
    InpZona --> CheckGlobalCancel2{¿Escribió /cancel?}
    CheckGlobalCancel2 -- Sí --> CancelGlobal
    CheckGlobalCancel2 -- No --> PideGenero[Bot solicita Género: Señoritas / Varones / Mixto]
    
    %% Recolección: Género con reintentos
    PideGenero --> InpGenero[/Usuario ingresa género/]
    InpGenero --> CheckGlobalCancel3{¿Escribió /cancel?}
    CheckGlobalCancel3 -- Sí --> CancelGlobal
    CheckGlobalCancel3 -- No --> CheckGeneroValido{¿Opción válida?}
    
    CheckGeneroValido -- No --> SumaErrorGen[Contador error género +1]
    SumaErrorGen --> CheckMaxGen{¿Reintentos >= 3?}
    CheckMaxGen -- Sí --> ExcesoFallos[Bot: Demasiados intentos fallidos. Reiniciando...] --> ResetState
    CheckMaxGen -- No --> ErrorGenero[Bot: Indica Señoritas, Varones o Mixto] --> PideGenero
    
    CheckGeneroValido -- Sí --> PidePrecio[Bot solicita Presupuesto Máximo en USD]
    
    %% Recolección: Precio con reintentos
    PidePrecio --> InpPrecio[/Usuario ingresa presupuesto/]
    InpPrecio --> CheckGlobalCancel4{¿Escribió /cancel?}
    CheckGlobalCancel4 -- Sí --> CancelGlobal
    CheckGlobalCancel4 -- No --> CheckPrecioNum{¿Es número > 0?}
    
    CheckPrecioNum -- No --> SumaErrorPrecio[Contador error precio +1]
    SumaErrorPrecio --> CheckMaxPrecio{¿Reintentos >= 3?}
    CheckMaxPrecio -- Sí --> ExcesoFallos
    CheckMaxPrecio -- No --> ErrorPrecio[Bot: Ingresa un monto numérico válido, ej: 150] --> PidePrecio
    
    %% Consulta API y Manejo de Resultados
    CheckPrecioNum -- Sí --> ConsultaAPI[Llamada API: GET /api/alojamientos/]
    ConsultaAPI --> CheckResultados{¿Hay resultados?}
    
    %% Solución observación 5: Bifurcación al no haber resultados
    CheckResultados -- No --> Sugerencia[Bot: Sin resultados. ¿Deseas cambiar presupuesto o ver opciones en ambiente Mixto?]
    Sugerencia --> InpSugerencia[/Usuario elige: Precio o Género/]
    InpSugerencia --> DecisionSugerencia{¿Qué desea ajustar?}
    DecisionSugerencia -- Presupuesto --> PidePrecio
    DecisionSugerencia -- Género / Mixto --> PideGenero
    DecisionSugerencia -- Cancelar --> CancelGlobal

    CheckResultados -- Sí --> MuestraLista[Bot lista alojamientos con ID y precio]
    
    %% Selección de alojamiento con reintentos
    MuestraLista --> EsperaAccion[/Usuario pide Detalle o Contacto con ID/]
    EsperaAccion --> CheckGlobalCancel5{¿Escribió /cancel?}
    CheckGlobalCancel5 -- Sí --> CancelGlobal
    CheckGlobalCancel5 -- No --> CheckIDValido{¿ID existe en catálogo?}
    
    CheckIDValido -- No --> SumaErrorID[Contador error ID +1]
    SumaErrorID --> CheckMaxID{¿Reintentos >= 3?}
    CheckMaxID -- Sí --> ExcesoFallos
    CheckMaxID -- No --> ErrorID[Bot: ID no encontrado. Revisa los números listados] --> EsperaAccion
    
    CheckIDValido -- Sí --> DetalleOContacto{¿Tipo de petición?}
    
    %% Flujo Detalle con estado de confirmación de contacto
    DetalleOContacto -- Detalle --> MuestraDetalle[Bot muestra resumen + Enlace web al anuncio con fotos]
    MuestraDetalle --> EsperaConfirmaContacto[/Usuario responde Sí para WhatsApp o /buscar/]
    EsperaConfirmaContacto --> CheckDeseaWhatsApp{¿Desea el WhatsApp?}
    CheckDeseaWhatsApp -- Sí --> MuestraContacto
    CheckDeseaWhatsApp -- No / /buscar --> PideZona
    CheckDeseaWhatsApp -- /cancel --> CancelGlobal

    %% Flujo Contacto Directo
    DetalleOContacto -- Contacto --> MuestraContacto[Bot entrega enlace directo de WhatsApp]
    
    %% Cierre de ciclo sin callejones sin salida
    MuestraContacto --> PreguntaCierreContacto[Bot: ¿Deseas realizar otra búsqueda (/buscar) o salir (/salir)?]
    PreguntaCierreContacto --> EsperaCierre[/Usuario elige acción/]
    EsperaCierre --> DecisionCierre{¿Acción?}
    DecisionCierre -- /buscar --> PideZona
    DecisionCierre -- /salir o finalizar --> FinCiclo[Bot: ¡Éxitos en tu búsqueda!] --> ResetState
    DecisionCierre -- /cancel --> CancelGlobal
    
    %% Manejador Global de Cancelación y Reset
    CancelGlobal[Bot: Operación cancelada] --> ResetState
    ResetState[Limpiar variables temporales de sesión] --> Start
