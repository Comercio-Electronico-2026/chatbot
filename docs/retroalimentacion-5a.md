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

## Retroalimentacion 5a - SM21008

Bot: tienda de electronica y componentes. De los mas completos: contador de 3
intentos, nodo de fuera de alcance, lineas punteadas para comandos globales.

Realizado:

+ **Contador de intentos** (`I{¿mas de 3 intentos fallidos?} -> I1 / I2 "ofrece
  contactar a un humano"`).
+ Nodo de "peticion fuera de alcance" (`O`) con oferta de atencion humana.
+ Lineas punteadas de "cambio de tema / comandos" desde `D` y `F` de vuelta a `C`.
+ Slot filling explicito (`D` "lo solicita solo si no se dio al inicio").
+ Fallo de API contemplado (dentro de `M`).
+ Lista de cambios post-revision con el origen de cada uno.

Problemas de logica en el flujo:

+ **Arista sobrante `I --> B`**: el nodo de decision `I` tiene, ademas de
  `-->|No|` y `-->|Si|`, una flecha incondicional a `B`. Bug del diagrama;
  borrala.
+ `E["muestra lista de componentes de la categoria"] --> N` directo: despues de
  listar una categoria el usuario no puede elegir un item de esa lista; falta el
  nodo de seleccion.
+ `M` mezcla "no encontrado" y "error de API" en un solo nodo y mensaje; son
  situaciones distintas (una sugiere corregir la busqueda, la otra reintentar).
+ Las punteadas de cambio de tema salen solo de `D` y `F`, no de la seleccion en
  `E` (tu propio Mago de Oz, Turno 3, aun marca el cambio de tema como trabado).
+ `H` "Bot finaliza la consulta" no tiene mensaje de despedida (tu Mago de Oz,
  Turno 4).
+ La evaluacion de tu pareja B esta **desactualizada**: dice "falta el dialogo de
  muestra" y por eso califica la pregunta 2 como "No resuelto", pero el documento
  actual si lo tiene (lo agregaste en los cambios). Deja una nota aclarando que la
  version final ya incorpora esos cambios, o pide una re-lectura corta.

Pendiente / a corregir: borrar la arista `I --> B`, agregar el nodo de seleccion
de item en la lista de categoria, separar "no encontrado" de "error de API",
mensaje de despedida en `H`. Muy buen documento; son ajustes menores.

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
