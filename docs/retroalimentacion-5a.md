# Retroalimentacion - Laboratorio 5a: Diseno conversacional del bot

Revision del documento `docs/diseno-conversacional.md` de cada rama `alumno/<CARNET>`
del repo `Comercio-Electronico-2026/chatbot`. 

El foco de esta revision son los
**problemas de logica en el flujo de conversacion** (diagrama de la Actividad 1 +
coherencia con el inventario de intenciones y el dialogo de muestra), ademas del
estado de la Actividad 4 (revision entre pares).

No es una nota sobre el servidor; es una revision de diseno antes de programar en
la Sesion 2.

---

## Retroalimentacion 5a - AS22027

Bot: CitasBot - reservas en clinica dental. Documento completo, con dialogo de
muestra y Actividad 4 completa con lista de cambios.

Realizado:

+ Inventario de 8 intenciones bien priorizadas; dialogo de muestra claro; peer
  review con veredictos y "cambios que deben incorporarse".
+ El sub-flujo de "agendar cita" esta bien encadenado (servicio -> fecha -> hora
  -> confirmar -> API) y termina en "¿necesita algo mas?".

Problemas de logica en el flujo:

+ **Callejones sin salida:** los sub-flujos "Consultar mis citas" (M/S/T/U) y
  "Cancelar cita" (N/V/W/X) terminan en un nodo y no vuelven a `Q` ni a `B`.
  Despues de consultar o cancelar, el diagrama no dice que pasa. Conecta todos los
  finales a "¿necesita algo mas?".
+ `K --> G` (sin disponibilidad -> ofrece alternas -> vuelve a "usuario indica
  fecha"): puede hacer bucle si nunca hay cupo; sin salida.
+ `X` (numero de cita invalido) es un callejon: no reintenta, no hay contador, no
  llega a `/ayuda`.
+ El inventario tiene 8 intenciones pero el diagrama solo implementa 3 (agendar,
  consultar, cancelar). Reprogramar, precios, ubicacion y "hablar con un humano"
  no tienen rama. "Hablar con un humano" es prioridad 3 pero no hay nodo de
  escalamiento.
+ `/cancelar` y `/ayuda` "en cualquier paso" viven solo en las notas, no en el
  diagrama.
+ El dialogo promete "Te enviare un recordatorio un dia antes" pero no hay
  intencion ni API de recordatorios (tu pareja lo marco en la pregunta 3).
+ `M` pide "telefono registrado" para identificar al paciente, pero el flujo de
  agendar nunca identifica al paciente aunque la lista de chequeo dice que se pide
  nombre y telefono la primera vez.

Pendiente / a corregir: cerrar los tres sub-flujos hacia "¿algo mas?", agregar
contador + escalamiento, dibujar reprogramar y "hablar con humano", quitar o
respaldar la promesa del recordatorio.

---

## Problemas de logica mas repetidos (leer antes del detalle por carnet)

1. **Bucle infinito en las ramas de error.** El nodo de error ("formato invalido",
   "no encontrado") vuelve a pedir el dato con una flecha directa, sin contador de
   intentos ni salida. Si el usuario insiste con un dato malo, queda atrapado.
   Correccion: tras 3 intentos, ofrecer volver al menu o derivar a un humano.

2. **Nodos sin salida (callejones).** El diagrama llega a un nodo y no sale de el:
   nodos de "ayuda", "espera nuevo mensaje", "operacion cancelada" o sub-flujos
   completos (ficha tecnica, comparar) que se dibujan como destino pero no
   continuan. Cada nodo debe tener a donde ir.

3. **El ciclo de la conversacion no cierra.** Tras resolver una intencion, el flujo
   deberia volver a un punto donde se vuelvan a mostrar las opciones. Varios
   vuelven a un nodo de decision sin re-mostrar el menu (el usuario no sabe que
   escribir), o terminan la conversacion despues de una sola consulta cuando el
   dialogo de muestra da a entender varias.

4. **"Cancelar / ayuda en cualquier momento" solo esta en la prosa.** Se declara
   como comando global en la lista de chequeo, pero en el diagrama solo sale del
   menu principal. Si el usuario escribe `/cancelar` mientras el bot espera un
   numero de pedido, el flujo lo trata como dato invalido. Hay que dibujar la
   interrupcion global (una nota o un nodo que intercepte antes de validar formato).

5. **No se reutiliza el dato que el usuario ya dio (slot filling).** El inventario
   pone como ejemplo "donde esta mi pedido 5678", pero el flujo siempre pasa por
   "pideme el numero de pedido". Si el dato ya vino en el primer mensaje, hay que
   saltar directo a la consulta.

6. **La intencion "hablar con un humano" esta en el inventario pero no en el
   diagrama.** Se ofrece como texto de salida ("...o hablar con un agente") sin una
   rama que reciba esa peticion. Promesa que el flujo no cumple.

7. **Falta la rama de fallo de la API.** Casi todos asumen que la API siempre
   responde 200. Hay que modelar "la API no responde" con un mensaje no tecnico y
   opcion de reintentar.

8. **Cambio de tema a mitad de un flujo.** Si el bot espera un dato y el usuario
   pregunta otra cosa, la mayoria de diagramas no tiene rama para eso: el flujo se
   traba. Debe reconocerse la nueva intencion y confirmarse si se abandona la
   actual.

9. **Promesas que el bot no puede cumplir (Grice - calidad).** "Te enviare un
   recordatorio", "te avisare cuando se envie": no hay sistema de notificaciones ni
   intencion/API que lo respalde. Quitar la frase o agregar el mecanismo al diseno.

10. **Inconsistencias internas del documento.** Nombres de comando distintos entre
    inventario y prosa (`/cancel` vs `/cancelar` vs `/menu`); "4 digitos" en el
    dialogo vs "entero positivo" en la lista de chequeo; peer review hecho sobre
    una version anterior a la que quedo en el archivo.
