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

## Retroalimentacion 5a - HB21009

Bot: Michu Bot - accesorios para gatos. Diagrama con enfoque hibrido (chat + web).
Actividad 4 revisada por PR21064.

Realizado:

+ Inventario bien mapeado a endpoints reales de WordPress (tu revisora lo destaca).
+ Dialogo de muestra concreto; escenarios alternativos redactados.

Problemas de logica en el flujo:

+ `ErrorID --> ListaProductos` y `SinStock --> PideCategoria`: dos bucles infinitos
  sin contador. Tu revisora (PR21064) lo marco explicito: "bucle infinito...
  agregar condicion que corte a los 3 intentos... contacto de soporte humano".
+ `Cierre([Espera nuevo mensaje])` es un **callejon**: todos los caminos (envios,
  ayuda, ficha) terminan ahi y no hay flecha de vuelta a `Menu` ni a `Saludo`. El
  siguiente mensaje del usuario no tiene manejo definido.
+ `PreguntaMas --> Cierre`: el bot pregunta "¿deseas consultar otro producto?"
  pero `Cierre` no ramifica en Si/No.
+ `/cancelar` sale solo de `Menu` (tu revisora, pregunta 5): no es global.
+ El nodo `Menu` es un `{Seleccion de comando}` con solo 4 aristas de comando; un
  mensaje que no sea comando desde ese estado no tiene rama (la lista de chequeo
  dice "comando desconocido -> reitera opciones" pero no esta dibujado).
+ El endpoint de categorias (`/wp/v2/categories`) esta en el inventario pero el
  flujo siempre consulta `/posts` con busqueda.
+ Sin rama de fallo de API.

Pendiente / a corregir: cerrar `Cierre` de vuelta a `Menu`, contador + soporte
humano, fallback para no-comando, globalizar `/cancelar`.

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
