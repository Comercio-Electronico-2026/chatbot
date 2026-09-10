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

## Retroalimentacion 5a - MT23014

Bot: Tienda Electronica (catalogo de 4 productos). Documento muy completo (6
secciones + tablas de datos), diagrama que cierra bien casi todos los ciclos.

Realizado:

+ Alcance y "lo que el bot NO hace" explicito; no afirma stock porque la API no lo
  da (decision de diseno correcta, Grice - calidad).
+ Diagrama con retorno: `V{¿algo mas?} -> B / X`, `N -> W{¿reintentar?} -> D / B`,
  `P -> E`, `R -> F`. Buen manejo del camino de regreso.
+ Rama de fallo de API (`J -->|No, error de API| N`), rama de entrada no
  reconocida (`I`), rama de multimedia no soportada (en la prosa).
+ Actividad 4 completa con las 5 preguntas del formato oficial.

Problemas de logica en el flujo:

+ `L -->|No| R --> F` (producto no encontrado): bucle sin tope de 3 intentos (tu
  revisora, pregunta 4: "carece de un tope maximo de tres intentos" y "no
  contempla escalamiento a operador humano").
+ `/ayuda` y `/cancelar` se declaran globales en la prosa ("En cualquier punto...")
  pero en el diagrama salen solo de `C`. Tu revisora califico "Resuelto" la
  pregunta 5; en rigor es "Parcial" hasta que se dibuje. Tu propia mejora #5 pide
  documentar el turno de `/cancelar` a mitad de flujo.
+ `S --> Q` ("usuario selecciona un producto" -> mostrar datos): no valida que la
  seleccion sea una de las 4 opciones.
+ No hay nodo de derivacion a humano.

Pendiente / a corregir: contador + derivacion, dibujar la interrupcion global,
validar la seleccion de producto. Documento de referencia para el grupo.

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
