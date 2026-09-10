# Retroalimentacion - Laboratorio 5a: Diseno conversacional del bot

Revision del documento `docs/diseno-conversacional.md` de cada rama `alumno/<CARNET>`
del repo `Comercio-Electronico-2026/chatbot`. 

El foco de esta revision son los
**problemas de logica en el flujo de conversacion** (diagrama de la Actividad 1 +
coherencia con el inventario de intenciones y el dialogo de muestra), ademas del
estado de la Actividad 4 (revision entre pares).

No es una nota sobre el servidor; es una revision de diseno antes de programar en
la Sesion 2. "Estado" indica que tan listo esta el documento para entrar al 5b.

## Resumen

| Carnet | Bot | Actividad 4 | Estado |
|---|---|---|---|
| [AV21009](#av21009) | TecnoBot (tienda de tecnologia) | Si (sin atribuir) | Aceptable con correcciones |
| [AS22027](#as22027) | CitasBot (clinica dental) | Si | Aceptable con correcciones |
| [BH23004](#bh23004) | Asistente de tienda | **Falta** | Incompleto |
| [CM21091](#cm21091) | PokeThings (cartas Pokemon) | Si (por AV21009) | Aceptable con correcciones |
| [CM21092](#cm21092) | OdontoBot (clinica dental) | Si | Aceptable con correcciones |
| [GT22004](#gt22004) | Tienda deportiva | Si (por MC21105) | Aceptable con correcciones |
| [GH22026](#gh22026) | FerreBot (ferreteria) | Si (por HV21011) | Listo |
| [GD21011](#gd21011) | El Foraneo (alojamiento estudiantil) | **Falta** | Aceptable con correcciones |
| [HB21009](#hb21009) | Michu Bot (accesorios para gatos) | Si (por PR21064) | Aceptable con correcciones |
| [HV21011](#hv21011) | MusicHub (discos y vinilos) | Si | Incompleto |
| [JO20004](#jo20004) | NutriGuia (nutricion) | Si (+ archivo aparte) | Listo |
| [LL22030](#ll22030) | Tienda de computadoras | Si | Listo |
| [LQ21001](#lq21001) | WhatPhone (recomendador de celulares) | Si (por MM22108) | Incompleto |
| [MT23014](#mt23014) | Tienda Electronica | Si | Listo |
| [MM22108](#mm22108) | Bot de Ajedrez | Si | Incompleto |
| [MC21105](#mc21105) | Tienda de celulares | Si (por GT22004) | Aceptable con correcciones |
| [OT18005](#ot18005) | Asistente de tienda | **Falta** | Incompleto |
| [PR21064](#pr21064) | CETBOT (CET STORE) | Si (por HB21009) | Aceptable con correcciones |
| [RC22009](#rc22009) | Accesorios tech / gaming | Parcial (sin Mago de Oz) | Incompleto |
| [SP21013](#sp21013) | Tienda de postres | **Falta** | Critico |
| [SM21008](#sm21008) | Tienda de electronica y componentes | Si (review desactualizado) | Listo |

---

## Problemas de logica mas repetidos (leer antes del detalle por carnet)

1. **Bucle infinito en las ramas de error.** El nodo de error ("formato invalido",
   "no encontrado") vuelve a pedir el dato con una flecha directa, sin contador de
   intentos ni salida. Si el usuario insiste con un dato malo, queda atrapado.
   Correccion: tras 3 intentos, ofrecer volver al menu o derivar a un humano.
   Lo cumplen bien: JO20004, LL22030, SM21008 (y parcialmente MM22108).

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

---

## AV21009

Bot: TecnoBot - tienda de tecnologia. Documento completo y bien redactado.

Realizado:

+ Lista de chequeo completa, inventario de 5 intenciones con prioridad y API.
+ Diagrama con sub-flujos por categoria (laptops, smartphones, accesorios, pedido)
  y validacion de formato del numero de pedido.
+ Actividad 4 respondida con las 5 preguntas y mejoras concretas.

Problemas de logica en el flujo:

+ `Z -->|Si| C`: al responder "Si, otra consulta" el flujo va al nodo de decision
  C, no al de bienvenida B. El usuario no vuelve a ver las opciones y "le toca
  adivinar que escribir" (tu propia pareja lo detecto). Enruta `Z|Si` a B o a un
  nodo que re-muestre el menu.
+ `H --> C` (mensaje no reconocido): vuelve al nodo de decision sin mostrar
  opciones y sin contador. Un usuario que no entiende puede quedar en bucle
  "no entendi eso" -> "no entendi eso".
+ `G2 --> G` (formato de pedido invalido): bucle infinito, sin tope de 3 intentos
  ni derivacion. En cambio `G5` ("no encontre el pedido") ofrece "intentar de
  nuevo o hablar con un agente" pero no hay nodo que reciba "hablar con un agente".
+ La cancelacion/ayuda global esta dibujada solo como arista desde C; "en cualquier
  punto del flujo" (mientras se pide el numero en G) no esta modelado.
+ Falta el **dialogo de muestra del camino feliz** (tu pareja tambien lo marco):
  la Actividad 1 lo pide explicitamente. Agregalo.
+ Sin slot filling: si el primer mensaje ya trae "donde esta mi pedido 5678", el
  flujo igual pide el numero.

Pendiente / a corregir: cerrar el ciclo hacia el menu, agregar contador de
intentos + nodo de derivacion a humano, dibujar la interrupcion global, agregar el
dialogo de muestra.

---

## AS22027

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

## BH23004

Bot: asistente de tienda (pedidos + catalogo). Documento con buena lista de
chequeo, escenarios alternativos redactados y dialogo de muestra.

Realizado:

+ Lista de chequeo completa y bien pensada (incluye "error al consultar la API",
  minimizacion de datos, cambio de tema).
+ Inventario con 8 intenciones; dialogo de muestra correcto.
+ El diagrama cierra bien los caminos de catalogo y pedido (ambos llegan a
  `ContinuePrompt`).

Problemas de logica en el flujo:

+ **Falta la Actividad 4 completa** (Mago de Oz + 5 preguntas + meta-revision).
  Es entregable obligatorio de la Sesion 1; sin ella el documento no puede pasar
  al 5b.
+ `NoProd --> AskProduct` y `ErrFormat --> AskOrder`: dos bucles de error sin
  contador de 3 intentos ni salida a humano. Ademas son inconsistentes entre si:
  "pedido no encontrado" tiene `RetryPrompt` (Si/No) pero "formato invalido" hace
  bucle directo.
+ La intencion `solicitar_soporte` (prioridad 2, "punto de contacto directo hacia
  soporte humano") esta en el inventario y en los escenarios, pero **no hay ninguna
  rama de soporte humano en el diagrama**.
+ El escenario "cambio de tema: el bot pregunta si desea cancelar la operacion en
  curso" esta descrito en la prosa pero no dibujado.
+ Sin slot filling: el inventario da "Rastrear pedido 4821" como ejemplo, pero el
  flujo siempre pasa por `AskOrder`.
+ El nodo `ReadProd` (captura de texto para catalogo) no tiene rama de `/cancel`,
  a diferencia de `ReadOrder` que si la tiene.

Pendiente / a corregir: hacer la Actividad 4, agregar la rama de soporte humano,
unificar y limitar los bucles de error, dibujar el manejo de cambio de tema.

---

## CM21091

Bot: PokeThings - cartas Pokemon, ropa y articulos. Estructura muy parecida a la
de AV21009 (mismo esqueleto de diagrama).

Realizado:

+ Documento completo: lista de chequeo, inventario, dialogo de muestra y Actividad
  4 (Mago de Oz + 5 preguntas, revisado por AV21009).
+ Sub-flujos por categoria con validacion de formato del pedido.

Problemas de logica en el flujo (heredados del esqueleto):

+ `Z -->|Si| C`: vuelve al nodo de decision sin re-mostrar el menu.
+ `H --> C` (mensaje no reconocido): bucle potencial sin contador, sin mostrar
  opciones.
+ `G2 --> G` (formato invalido): bucle infinito sin tope de 3 intentos.
+ `G5` menciona "hablar con un agente" pero no hay rama de escalamiento (tu propio
  Mago de Oz lo dice: "solo falta dibujar la rama que la recibe").
+ "Ver otros articulos" (`F`) solo lista categorias y va a `Z`; no tiene sub-flujo
  de disponibilidad/precio como sobres y ropa (tu pareja lo marco en la pregunta 2).
+ La cancelacion/ayuda global esta solo como arista desde `C`, no desde los
  sub-nodos.
+ Sin slot filling.
+ Tu pareja califico "Resuelto" las 5 preguntas: es una evaluacion demasiado
  benevola. Al menos las preguntas 2 (otros articulos), 3 (slot filling) y 4
  (rama de agente, fallback de entradas vacias) son "Parcial".

Pendiente / a corregir: igual que AV21009 (cerrar ciclo, contador + agente,
interrupcion global) + dar sub-flujo a "otros articulos".

---

## CM21092

Bot: OdontoBot - clinica dental. Uno de los mejor encadenados; dialogo de muestra
con botones y Actividad 4 con Mago de Oz por guiones.

Realizado:

+ Diagrama de "agendar cita" bien encadenado: valida servicio (texto libre -> re-muestra
  lista), fechas alternas, telefono con formato de 8 digitos, resumen y
  confirmacion explicita antes de llamar la API.
+ Maneja el cambio de tema en el paso de horario (`J1`).
+ Slot filling implicito y `S --> B` cierra el ciclo al menu.

Problemas de logica en el flujo:

+ `H` ("otro / no entiende") es un **callejon sin salida**: "ofrece menu de
  opciones o hablar con humano" pero no tiene ninguna flecha de salida.
+ `L1 --> L` (telefono invalido): bucle infinito, sin tope de intentos (tu propio
  Mago de Oz: "sin limite de intentos definido").
+ El cambio de tema solo esta cubierto en el paso de horario, no en servicio,
  fecha, nombre o telefono (tu propio Mago de Oz lo marca).
+ La promesa "Te enviaremos un recordatorio un dia antes" no tiene intencion ni
  API que la respalde (tu pareja: pregunta 3, "Parcial").
+ "Hablar con un humano" (prioridad 3) se menciona en `H` y en la lista de
  chequeo pero no hay nodo de escalamiento real.
+ No hay boton "Atras" en pasos intermedios (tu pareja: pregunta 5).
+ El turno de confirmacion carga resumen + pregunta en un solo mensaje (Grice -
  manera); separalo en dos.

Pendiente / a corregir: dar salida a `H`, poner contador en `L1`, extender el
manejo de cambio de tema a todos los pasos, resolver la promesa del recordatorio.

---

## GT22004

Bot: tienda deportiva. Diagrama con subgraphs por flujo (ofertas, catalogo,
busqueda, ayuda, cierre). Actividad 4 completa (revisado por MC21105).

Realizado:

+ El nodo de cierre `FinTurno` agrega todas las respuestas y el ciclo cierra
  correctamente (`Continuar -> Bienvenida / Fin`). Mejor que la mayoria.
+ Inventario claro, comandos definidos, dialogo de muestra estructurado.

Problemas de logica en el flujo:

+ `PideTermino -- "Reintenta" --> ExtraeTermino`: bucle infinito sin contador (tu
  Mago de Oz: "carece de salida por escape o limite de intentos").
+ **Ningun flujo tiene rama de fallo de API** (tu Mago de Oz: "el diseno asume
  respuestas siempre exitosas 200 OK"). Falta el nodo de contingencia tecnica.
+ `Hallado -- Si --> ValidaStock` asume un solo producto: no hay desambiguacion
  cuando la busqueda devuelve varias coincidencias (tu Mago de Oz con "guantes",
  tu pareja pregunta 2).
+ `/cancelar` solo se intercepta en `PideTermino` y en el cierre; no es global.
+ El dialogo de muestra usa `[producto]` y `[categoria]` como marcadores en vez de
  un ejemplo concreto; la Actividad 1 pide un guion real (turnos con datos de
  verdad).

Pendiente / a corregir: contador + escape en `PideTermino`, nodo de error de API,
rama de multiples resultados, interrupcion global, reescribir el dialogo de
muestra con un ejemplo real.

---

## GH22026

Bot: FerreBot - ferreteria WooCommerce. Documento muy completo (14 secciones),
diagrama que cierra bien los ciclos. Actividad 4 revisada por HV21011.

Realizado:

+ El diagrama cierra los ciclos: `O -> R (¿otro?) -> E / B`, `L -> M (¿otra?) ->
  E / B`. Buen manejo del "camino de regreso".
+ Tiene rama de fallo de API (`I -- No --> J`) y de "no encontrado" con
  `M{¿desea otra busqueda?}`.
+ Escenarios alternativos redactados con ejemplos de mensaje real.

Problemas de logica en el flujo:

+ `G --> E` (entrada invalida -> solicita nombre -> E): bucle sin contador. Tu
  revisora (HV21011) lo marco "No resuelto": falta el contador de fallos y la
  derivacion a humano tras el 3er error.
+ `/cancelar` y `/ayuda` salen solo del nodo `E`; desde `P` ("selecciona un
  producto") o `M` no se pueden invocar. Tu revisora pide hacerlos globales.
+ `Q` ("usuario selecciona un producto" de la lista) no valida la seleccion: si el
  usuario escribe algo que no es una opcion, no hay rama.
+ `J --> B` (fallo de API -> vuelve a la bienvenida) pierde el termino de busqueda;
  seria mejor volver a `E` ofreciendo reintentar.
+ Sin slot filling (tu revisora, pregunta 3).
+ No hay derivacion a humano en ningun punto.

Pendiente / a corregir: contador de intentos + derivacion, globalizar
cancelar/ayuda, validar la seleccion de `Q`. Documento muy bueno; estos son
ajustes acotados.

---

## GD21011

Bot: El Foraneo - alojamiento estudiantil. Diagrama detallado con chequeo de
`/cancel` en cada paso (bien) y dialogo de muestra largo.

Realizado:

+ La interrupcion global si esta bien modelada: `CheckCancel*` en cada paso de
  entrada, todos van a `CancelGlobal -> ResetState -> Start`. Ejemplo a seguir.
+ Slot: valida genero y presupuesto con nodos de error dedicados.
+ Enfoque hibrido (chat + ficha web) bien explicado.

Problemas de logica en el flujo:

+ **Falta la Actividad 4** (Mago de Oz + 5 preguntas + meta-revision). Entregable
  obligatorio.
+ `ErrorGenero --> PideGenero`, `ErrorPrecio --> PidePrecio`, `ErrorID -->
  EsperaAccion`: tres bucles de error sin tope de 3 intentos ni salida.
+ `PreguntaCierre --> FinCiclo([Continua sesion o usuario finaliza])`: nodo
  terminal vago que no vuelve a `MuestraLista`, `PideZona` ni `Start`. El cierre
  pregunta "¿otra consulta?" pero esa decision no se enruta a ningun lado. El
  ciclo no cierra.
+ Incoherencia diagrama vs dialogo: en el dialogo, tras ver el detalle el bot
  pregunta "¿Deseas el WhatsApp?" y el usuario responde "Si, por favor"; en el
  diagrama ese "Si" caeria en `EsperaAccion -> CheckIDValido`, que fallaria porque
  "Si" no es un ID. Falta el estado "esperando confirmacion de contacto".
+ `Sugerencia --> PidePrecio` cuando no hay resultados: la sugerencia dice "ver
  Mixto" pero el flujo solo deja cambiar el precio, no el genero.

Pendiente / a corregir: hacer la Actividad 4, cerrar `FinCiclo` de vuelta al
inicio o a la lista, agregar el estado de confirmacion de contacto, contador en
los tres nodos de error.

---

## HB21009

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

## HV21011

Bot: MusicHub - discos y vinilos. Documento en la version corta; Actividad 4
respondida (pareja B) con veredictos mayormente "Parcial".

Realizado:

+ Inventario, dialogo de muestra multi-turno y Actividad 4 presente.
+ Valida formato del numero de orden (`J`).

Problemas de logica en el flujo:

+ `Q --> R([Fin del flujo])`: el nodo "Mostrar menu final" lista `/catalogo`,
  `/pedido`, `/salir` pero el diagrama va directo a FIN sin un nodo de decision que
  capture la eleccion. **Segun el diagrama la conversacion siempre termina despues
  de una sola consulta**, lo que contradice tu propio dialogo de muestra (catalogo
  -> pedido -> salir). `Q` debe volver a `C`.
+ `K --> I` (error de formato de orden): bucle infinito, sin contador (tu pareja,
  pregunta 4: "no maneja... tres intentos ni derivacion a humano").
+ `F -->|No| H` (sin resultados) va a `Q` sin ofrecer reintentar la busqueda.
+ Sin rama de fallo de API, sin manejo de entrada sin sentido, sin cambio de tema
  (tu pareja lo lista completo en la pregunta 4).
+ `/ayuda` y `/salir` no estan disponibles en todos los pasos (tu pareja,
  pregunta 5).
+ "Te avisaremos cuando sea enviado" sin sistema de notificaciones (tu pareja,
  pregunta 3).
+ La lista de chequeo esta muy comprimida: faltan detalles de edad/ocupacion,
  metodo de validacion y politica de retencion concreta.

Pendiente / a corregir: enrutar `Q -> C` para que el ciclo cierre, contador +
derivacion, agregar ramas de API/entrada invalida/cambio de tema, ampliar la
lista de chequeo.

---

## JO20004

Bot: NutriGuia - informacion nutricional. De los mejores documentos: tiene
contador de 3 intentos, derivacion a asesoria, `peer-review-conversacion.md` como
archivo aparte y lista de cambios con origen.

Realizado:

+ **Contador de intentos implementado** (`J -> O{¿3 intentos?} -> Q "ofrecer ayuda
  de un profesional"`). Uno de los pocos que lo hace.
+ Slot filling: `C{¿contiene intencion o nutriente valido?}` reutiliza el dato del
  primer mensaje.
+ Cierre correcto: `X{¿otra consulta?} -> B / Y`.
+ Lista de cambios post-revision con el origen de cada uno.

Problemas de logica en el flujo:

+ **`C -->|Si| D["Usar los datos ya proporcionados"]` es un callejon**: `D` no
  tiene flecha de salida. Cuando el primer mensaje trae un nutriente valido, el
  flujo llega a `D` y se detiene. Debe continuar al sub-flujo correspondiente
  (comparar / fuentes) ya con el slot lleno.
+ **Nodo `R` definido dos veces** con etiquetas distintas: `Q --> R["Ir a Agendar
  asesoria"]` y `E -->|Agendar asesoria| R["Solicitar datos necesarios"]`. Mermaid
  los fusiona; queda ambiguo. Usa ids distintos (`R1`, `R2`) o un solo nodo con
  una etiqueta.
+ `S -->|No| R` (datos de asesoria incompletos): bucle sin contador.
+ `P["Reformular..."] --> E` devuelve al menu principal en vez de volver al
  sub-flujo (comparar/fuentes) donde estaba, perdiendo contexto.
+ En "comparar nutrientes" se necesitan 2 datos; `H["Solicitar dato faltante"] -->
  I -> G` solo recoge uno. La logica de 2 slots no esta completa.

Pendiente / a corregir: dar salida a `D`, separar el nodo `R`, contador en `S`,
que `P` vuelva al sub-flujo, completar los 2 slots de comparar. Buen trabajo de
base.

---

## LL22030

Bot: tienda de computadoras. Diagrama de los mas completos: contador de 3
intentos, fallback, fuera de alcance, recomendacion con uso+presupuesto.

Realizado:

+ **Contador de intentos** (`R{¿ha fallado menos de 3 veces?} -> S / T`).
+ Slot filling: `D{¿el usuario indico el producto?} -> Si: consultar / No: pedir
  solo el dato que falta`.
+ Rama de fallo de API (`G -->|No| K`), rama de "fuera de alcance" (`Q`) con oferta
  de atencion humana.
+ Cierre correcto: casi todos los nodos llegan a `U{¿otra consulta?} -> B / P`.
+ Actividad 4 completa (5 preguntas + Mago de Oz por guiones).

Problemas de logica en el flujo:

+ Cambio de tema: la prosa dice "si cambia de tema, el bot reconoce la nueva
  intencion", pero el diagrama no tiene ninguna arista de `D`, `F` o `M` de vuelta
  a `C`. Tu propio Mago de Oz (Turno 3) lo confirma: "el flujo se traba porque
  falta una rama". Dibujala.
+ `ayuda / volver / cancelar` global esta en la prosa pero solo como aristas desde
  `C` (tu pareja, pregunta 5).
+ `K` ("no puede consultar el catalogo y ofrece reintentar") va a `U`, no vuelve a
  `E`; el "reintentar" que ofrece el texto no esta cableado.
+ `Q` (fuera de alcance) "ofrece atencion humana" pero no hay nodo de handoff real
  (la intencion "pedir atencion humana" existe en el inventario, prioridad 3).

Pendiente / a corregir: dibujar el cambio de tema, cablear el reintento de `K`,
globalizar los comandos. Muy buen documento.

---

## LQ21001

Bot: WhatPhone - recomendador de celulares. Actividad 4 revisada por MM22108 (una
de las revisiones mas rigurosas del grupo).

Realizado:

+ Sub-flujo de "recomendar" bien detallado: valida presupuesto, valida prioridad,
  consulta API, muestra ficha con botones.
+ Inventario con 6 intenciones; dialogo de muestra concreto con botones.

Problemas de logica en el flujo:

+ **`AskModel` (ficha tecnica) y `AskTwoModels` (comparar) son callejones**: el
  menu enruta a ellos pero no tienen ninguna flecha de salida. Dos de las cuatro
  funciones del bot no estan implementadas en el diagrama.
+ **`HandleUtility` es un callejon**: `/cancelar o /help` va ahi y no sale.
+ `ErrorBudget --> AskBudget` y `ErrorUsage --> AskUsage`: bucles infinitos (tu
  revisora: "bucles infinitos en ErrorBudget y ErrorUsage").
+ `NoMatch --> AskBudget`: al no haber resultados vuelve a pedir presupuesto y
  **destruye el contexto de prioridad** (tu revisora lo marca).
+ **`PostAction -->|Ver otra opcion| QueryAPI` con los mismos parametros**: la API
  devolvera el mismo telefono. Bug de logica que tu revisora identifico exacto:
  hay que excluir el modelo ya recomendado.
+ Inconsistencia de comandos: el inventario dice `/cancel` y `/help`; la prosa usa
  `/cancelar` y menciona `/menu` (que no existe en el inventario). Unifica.
+ Sin contador de intentos; sin derivacion; `/start` forzado al final.

Pendiente / a corregir: implementar los sub-flujos de ficha y comparar, dar salida
a `HandleUtility`, contador + boton "volver al menu", arreglar "ver otra opcion"
para excluir el modelo actual, unificar nombres de comando. El documento describe
mas de lo que el diagrama modela.

---

## MT23014

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

## MM22108

Bot: Bot de Ajedrez (evaluar FEN + aperturas). Publico para jugadores
competitivos. Documento breve; Actividad 4 presente (Mago de Oz + 5 preguntas).

Realizado:

+ Inventario y dialogo de muestra correctos; caso de uso bien delimitado.
+ Tu propio Mago de Oz detecto los problemas reales del flujo (el bot "se traba" y
  el usuario "se queda atrapado sin poder salir").

Problemas de logica en el flujo:

+ **`F["Pedir correccion de sintaxis FEN"] es un callejon**: no vuelve a `C` ni a
  `D`. El usuario que se equivoca queda atrapado (tu Mago de Oz, Guion 2, lo
  confirma).
+ **`E`, `H`, `I` tambien son callejones**: tras devolver la evaluacion, la linea
  de apertura o el menu de ayuda, el diagrama no continua. No hay nodo "¿necesitas
  algo mas?" ni despedida, aunque el dialogo de muestra pregunta "¿Necesitas
  evaluar otra posicion?".
+ Sin contador de intentos (tu pareja, pregunta 4: "actua como disco rayado").
+ Sin opcion de cancelar en ningun punto (tu pareja, pregunta 5).
+ Sin rama de fallo de API, aunque la lista de chequeo menciona "la API de
  evaluacion no responde".
+ `G --> H` siempre "devuelve linea principal"; no hay rama para "apertura no
  registrada" (mencionada en la lista de chequeo).
+ `B{¿comando valido o FEN?}` y luego `C[Validar formato FEN]`: doble validacion
  redundante.
+ La lista de chequeo esta muy comprimida (privacidad, UI y validacion en una
  linea cada una).

Pendiente / a corregir: cerrar TODOS los nodos (dar salida a `F`, `E`, `H`, `I`),
agregar nodo de "¿algo mas?" + despedida, contador de intentos, opcion de
cancelar, rama de apertura no encontrada, ampliar la lista de chequeo.

---

## MC21105

Bot: tienda de celulares y accesorios. Documento completo; Actividad 4 revisada
por GT22004 (con Mago de Oz).

Realizado:

+ Lista de chequeo detallada, inventario mapeado a endpoints WooCommerce, dialogo
  de muestra concreto.
+ El ciclo cierra: `OfferMore -> FinalDecision -> Welcome / Farewell`. `OfferRetry`
  (Si/No) para pedido no encontrado.

Problemas de logica en el flujo:

+ `InvalidFormat --> AskOrderID`: bucle sin contador. Tu revisora lo confirma con
  el caso de escribir "catalogo" durante la consulta de pedido: "provoco error de
  formato y atrapo al usuario en un bucle".
+ **Sin nodo de fallback** para mensajes fuera de dominio (tu revisora y su Mago
  de Oz: "no existe un mensaje de escape estandar", caso "quiero hablar con el
  superior").
+ `SelectCategory --> QueryStock`: al elegir una categoria va directo a comprobar
  stock; no lista los productos de la categoria (tu revisora, pregunta 2).
+ Incoherencia: bienvenida y dialogo dicen "4 digitos"; la lista de chequeo dice
  "valor numerico entero positivo"; el diagrama valida "¿son digitos validos?" sin
  longitud. Define una sola regla.
+ `/cancelar` y `/ayuda` solo desde `UserChoice`; escribirlos dentro del flujo de
  pedido cae en error de formato (tu revisora, pregunta 5).
+ Sin slot filling (tu revisora, pregunta 3); sin rama de fallo de API; sin
  handoff a humano real (aunque `solicitar_ayuda` menciona "soporte humano").

Pendiente / a corregir: nodo de fallback global, contador + interceptar comandos
antes de validar formato, listar productos por categoria, unificar la regla del
numero de pedido.

---

## OT18005

Bot: asistente de tienda (pedidos + catalogo). Documento en version corta.

Realizado:

+ Lista de chequeo con las preguntas respondidas; inventario de 5 intenciones;
  dialogo de muestra correcto.
+ El diagrama distingue `/start`/saludo de otras intenciones y valida formato del
  pedido.

Problemas de logica en el flujo:

+ **Falta la Actividad 4** (Mago de Oz + 5 preguntas + meta-revision). Entregable
  obligatorio.
+ `ErrorFormat --> AskOrder` y `ErrorNotFound --> AskOrder`: dos bucles infinitos,
  sin contador ni salida a soporte (la lista de chequeo dice "reintentar o hablar
  con soporte" pero no hay nodo de soporte).
+ **`EndFlow([Fin de turno / Espera nueva accion])` es un callejon**: todos los
  caminos terminan ahi y no hay ninguna flecha de vuelta a `CheckIntent` ni a
  `Start`. El diagrama no modela que pasa con el siguiente mensaje.
+ `ShowStatus --> EndFlow`: "Ofrecer continuar" pero `EndFlow` no ramifica.
+ Incoherencia: la lista de chequeo dice "4 o mas digitos", el diagrama valida
  "¿es numero de 4 digitos?" y el dialogo dice "son 4 digitos". Unifica.
+ `/cancel` y `/help` solo desde `CheckIntent`; no globales. `/help o no
  reconocido` comparten nodo y el mensaje dice "No entendi" incluso cuando el
  usuario pidio ayuda explicitamente.
+ Sin rama de fallo de API; sin slot filling.

Pendiente / a corregir: hacer la Actividad 4, cerrar `EndFlow` de vuelta a
`CheckIntent`, contador + nodo de soporte, unificar la regla del numero de pedido,
separar `/help` de "no reconocido".

---

## PR21064

Bot: CETBOT - CET STORE (productos, pedidos, pagos). Actividad 4 revisada por
HB21009 (revision precisa).

Realizado:

+ Inventario con 5 intenciones; dialogo de muestra concreto; el ciclo cierra
  (`P -> Q -> B / R`).
+ Sub-flujos de producto, pedido y pagos claramente separados.

Problemas de logica en el flujo:

+ `H --> D` (producto no encontrado) y `L --> I` (pedido no encontrado): dos
  bucles infinitos. Tu revisora lo marco "No resuelto": "bucle infinito... sin
  contador de reintentos maximos ni derivacion a soporte humano".
+ `/cancelar` solo se procesa desde `C`; escribirlo en los nodos de entrada libre
  `E` o `J` se interpreta como termino de busqueda o ID invalido (tu revisora,
  pregunta 5, lo describe exacto).
+ `/ayuda` esta en el inventario pero **no aparece en el diagrama**.
+ Sin slot filling: "Estado de mi pedido 2048" en el inventario, pero `J` vuelve a
  pedir el ID (tu revisora, pregunta 3).
+ La consulta de pedido usa solo el ID, sin verificar identidad del cliente (tu
  revisora sugiere un segundo factor).
+ Sin rama de fallo de API; sin validacion de formato separada del "existe".
+ La lista de chequeo esta algo comprimida (escenarios en 3 lineas, sin cambio de
  tema ni fallo de API).

Pendiente / a corregir: contador + derivacion, interceptar `/cancelar` y `/ayuda`
como globales antes de validar, agregar `/ayuda` al diagrama, slot filling.

---

## RC22009

Bot: accesorios de tecnologia y gaming. Diagrama de alto nivel; documento en
version corta.

Realizado:

+ Inventario de 6 intenciones con prioridad y API; dialogo de muestra concreto y
  bien redactado (Grice - manera).
+ El ciclo cierra: casi todos los nodos llegan a `L{¿otro producto?} -> B / M`.
+ 5 preguntas de la Actividad 4 respondidas, con autocritica honesta.

Problemas de logica en el flujo:

+ **Falta el Mago de Oz** (los 2 guiones: camino feliz y camino con friccion). La
  Actividad 4 pide ambas tecnicas.
+ `B -->|Buscar Producto / Categoria| D[Consultar API de Catalogo]`: **salta a la
  API sin un nodo que pida el nombre del producto**. Si el usuario escribe solo
  "Hola" o "buscar", no hay que consultar (tu pareja, pregunta 3: "el diagrama no
  aclara que hace si el usuario escribe solo 'Hola'").
+ **Sin manejo de errores real**: solo el nodo generico `G`. No hay "producto no
  encontrado" (distinto de "agotado"), ni formato invalido, ni contador de 3
  intentos, ni derivacion a humano (tu pareja, pregunta 4: "No resuelto").
+ Sin rama de fallo de API.
+ `/help` y `/cancelar` solo desde `B` / `L`; no globales (tu pareja, pregunta 5).
+ Cambio de tema no modelado.
+ La lista de chequeo es muy breve (escenarios, privacidad y validacion en una
  linea cada una).

Pendiente / a corregir: hacer el Mago de Oz, agregar el nodo "pedir nombre del
producto", construir las ramas de error con contador, fallo de API, globalizar
comandos, ampliar la lista de chequeo.

---

## SP21013

Bot: tienda de postres. **Documento incompleto: es basicamente solo el camino
feliz.**

Realizado:

+ Lista de chequeo con las preguntas respondidas (en una linea cada una).
+ Inventario de 3 intenciones; inicio de dialogo de muestra.

Problemas de logica en el flujo:

+ **El diagrama no tiene ningun manejo de error.** `F --> G` asume que el numero
  de pedido siempre es valido; `G` asume que la API siempre responde. La lista de
  chequeo menciona "numero de pedido incorrecto", "postre agotado" y "comandos no
  reconocidos" pero **ninguno tiene rama**.
+ **`H[Bot: Ofrece continuar o finalizar]` es un callejon**: no hay decision, no
  vuelve a `C` ni a `B`, no hay despedida.
+ `D --> H` (catalogo): mismo callejon.
+ La intencion "Contacto humano" (prioridad 2) esta en el inventario pero **no
  esta en el diagrama**.
+ No hay `/cancelar` ni `/ayuda` en ningun punto.
+ Sin slot filling.
+ **Falta la Actividad 4** completa (Mago de Oz + 5 preguntas + meta-revision).
+ El dialogo de muestra se corta a mitad ("¿Necesitas algo mas?" sin turno de
  cierre).

Pendiente / a corregir: es el documento mas lejos de estar listo para el 5b. Hay
que: construir las ramas de error (formato, no encontrado, agotado, no
reconocido), cerrar el ciclo con decision + despedida, dibujar el contacto humano,
agregar comandos globales, hacer la Actividad 4 y completar el dialogo de muestra.

---

## SM21008

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

## Patrones para comentar en clase

1. **El diagrama de flujo tiene que ser un grafo cerrado.** Todo nodo tiene una
   salida y todo camino vuelve al menu o termina en una despedida. La mitad de los
   diagramas tienen al menos un callejon (nodo de ayuda, "espera nuevo mensaje",
   sub-flujo sin implementar). Ejercicio rapido: recorrer el diagrama con el dedo
   desde cada nodo terminal y preguntar "¿y ahora que?".

2. **Toda rama de error necesita un contador y una puerta de salida.** El patron
   correcto: error -> reformular con ejemplo -> (2o error) botones -> (3er error)
   volver al menu o derivar a un humano. Solo 3 de 21 lo tienen (JO20004,
   LL22030, SM21008). Es el punto que la rubrica del 5b penaliza mas.

3. **Lo que se declara global tiene que dibujarse global.** "El usuario puede
   cancelar en cualquier momento" en la prosa no vale si el diagrama solo lo deja
   cancelar desde el menu. Modelar la interrupcion (una nota de Mermaid, o un nodo
   que intercepte los comandos antes de validar formato).

4. **El inventario de intenciones y el diagrama tienen que coincidir.** Varias
   intenciones (sobre todo "hablar con un humano" y "consultar por categoria")
   aparecen en la tabla pero no tienen rama. Si esta en el inventario, tiene rama;
   si no va a tener rama, no esta en el inventario.

5. **Slot filling.** Si el ejemplo del inventario es "donde esta mi pedido 5678",
   el flujo tiene que empezar con "¿ya me dio el numero?". Pedir un dato que el
   usuario ya escribio es el error de Grice (cantidad) mas comun del grupo.

6. **No prometer lo que el bot no hace.** "Te enviare un recordatorio", "te
   avisare cuando se envie": si no hay intencion ni API para eso, es una promesa
   falsa. Tres bots la tienen.

7. **La API puede fallar.** Casi nadie modela "la API no responde". Es una rama
   obligatoria: mensaje no tecnico ("no pude consultar el catalogo ahora") +
   opcion de reintentar.

8. **El dialogo de muestra es un guion real, con datos de verdad**, no una
   plantilla con `[producto]`. Y tiene que llegar hasta el turno de cierre.

9. **La revision entre pares se evalua.** Calificar "Resuelto" las 5 preguntas
   cuando hay callejones y bucles infinitos es una meta-revision debil. La pareja
   C existe para eso. Las revisiones mas utiles del grupo: MM22108 -> LQ21001,
   HB21009 -> PR21064, HV21011 -> GH22026.

10. **Cuatro documentos no tienen Actividad 4** (BH23004, GD21011, OT18005,
    SP21013) y uno la tiene a medias (RC22009, sin Mago de Oz). Sin la version
    corregida tras el peer review, el documento no es la entrada valida para el
    5b.
