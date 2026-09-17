# Diseño Conversacional - Bot de Ajedrez

## Actividad 1 - Lista de Chequeo

* **¿Quién usará el chatbot?** Jugadores de ajedrez, de habla hispana, desde
  principiantes hasta nivel competitivo.
* **¿Qué problema o problemas resuelve?** Evaluar posiciones tácticas,
  consultar aperturas, y jugar una partida contra el análisis del bot sin
  abrir una interfaz pesada.
* **¿Qué necesidades específicas tienen?**
  - Evaluación de posición (Stockfish) a partir de FEN.
  - Consulta de líneas de apertura reales (Lichess Opening Explorer, por
    secuencia de jugadas explícita).
  - Registrar una partida jugada a jugada sin repetir el FEN completo cada vez.
  - Resolver un puzzle diario.
* **¿Qué preguntas se pueden hacer?**
  - "Evalúa esta posición: `<FEN>`"
  - `/apertura d4 d5`
  - "Quiero jugar una partida" → luego solo jugadas: "e4", "Cf3", "Axb5"
  - "Dame el puzzle de hoy"
* **¿Qué tipo de respuesta espero en cada caso?** Texto con evaluación
  numérica (ej. +1.5), notación algebraica en español, tablero como imagen
  PNG, y mensajes de estado claros ("Jugada válida", "Jugada ilegal, intenta
  de nuevo").
* **¿Perfil del usuario?** Estudiantes y jugadores de torneo, nivel técnico
  variable, familiarizados con notación ajedrecística en español.
* **¿Escenarios alternativos?**
  - FEN inválido.
  - Jugada mal escrita (sintaxis) o ilegal en el tablero (dos casos distintos)
    en el modo "Sigue la Partida".
  - Secuencia de apertura con una jugada inválida a mitad de camino.
  - La API de evaluación o de puzzles no responde.
  - El usuario cambia de tema a mitad de un flujo.
  - El usuario pide cancelar o ayuda en cualquier punto.
* **¿UI, Accesibilidad?** Texto en español para menús y mensajes de estado;
  el tablero se envía como imagen PNG en cada jugada y en los puzzles, para
  mantener legibilidad en clientes móviles.
* **¿Cómo valido el prototipo?** Pruebas de Mago de Oz con partidas reales,
  incluyendo jugadas mal escritas y cambios de tema a mitad de conversación.
* **¿Privacidad?** No se guardan FENs ni partidas más allá de lo necesario
  para retomarlas con `/seguir_partida`, borrables con `/borrar_datos`.

## Actividad 2 - Inventario de Intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
| :--- | :--- | :--- | :--- | :--- |
| Evaluar FEN | "Evalúa esta posición: `rnbqkbnr/...`" | Alta | 1 | Stockfish local |
| Sigue la Partida | "Quiero jugar una partida" → "e4", "Cf3"... | Alta | 1 | python-chess + Stockfish |
| Consultar Apertura | `/apertura d4 d5` (comando explícito, jugadas en español) | Media | 2 | Lichess Opening Explorer API (con caché) |
| Puzzle del día | "Dame el puzzle de hoy" | Media | 2 | API pública de Lichess |
| Ayuda | "/ayuda" o "¿Qué puedes hacer?" | Baja | 3 | Ninguna |
| Cancelar (global) | "/cancelar" en cualquier punto | Media | 1 | Ninguna |
| Hablar con un humano | "Quiero hablar con alguien" | Baja | 3 | Deriva a contacto del profesor/admin |

## Actividad 3 - Diálogo de Muestra: "Sigue la Partida"

**Usuario:** /start
**Bot:** Hola. Puedo evaluar posiciones, darte líneas de apertura, jugar una
partida contigo o darte el puzzle del día. Escribe `/ayuda` en cualquier
momento. ¿Qué necesitas?

**Usuario:** Quiero jugar una partida.
**Bot:** Perfecto, partida nueva desde la posición inicial. Juegas con blancas.
Escribe tu jugada en notación española (ej. "e4", "Cf3", "0-0"), o
`/cancelar` para terminar la partida en cualquier momento.

**Usuario:** e4
**Bot:** Jugada registrada: 1. e4. Respondo: 1... e5. Evaluación actual: +0.2.
¿Tu siguiente jugada?

**Usuario:** Cf3
**Bot:** Jugada registrada: 2. Cf3. Respondo: 2... Cc6. Evaluación actual: +0.3.
*(se envía el tablero como imagen PNG)*
¿Tu siguiente jugada?

**Usuario:** Ce9  *(sintaxis inválida: la casilla "e9" no existe, las filas van de 1 a 8)*
**Bot:** No reconozco "Ce9": la casilla "e9" no existe en el tablero. Revisa
que la columna sea una letra de "a" a "h" y la fila un número de "1" a "8"
(ej. "Ce5"). Escribe `/ayuda_notacion` para ver la lista completa de símbolos.
*(intento 1 de 2 — error de sintaxis)*

**Usuario:** Ab5
**Bot:** "Ab5" está bien escrito, pero no es una jugada legal en esta posición:
el alfil de casillas oscuras no tiene línea abierta hasta b5. ¿Otra jugada?
*(intento 1 de 2 — error de legalidad, es un contador independiente del de sintaxis)*

**Usuario:** mejor quiero ver aperturas
**Bot:** Entendido, cambias a consulta de aperturas. Tu partida en curso queda
guardada — escribe `/seguir_partida` cuando quieras retomarla. Usa
`/apertura <jugadas>` para consultar una línea, ej. `/apertura d4 d5`.

**Usuario:** /apertura d4 d5
**Bot:** Después de 1. d4 d5, las continuaciones más jugadas en partidas de
maestros son 2. Cf3 (42%) y 2. c4 (35%), con resultados equilibrados para
ambos bandos. ¿Quieres consultar otra secuencia con `/apertura` o volver al menú?

## Diagrama de Flujo (Mermaid)

```mermaid
graph TD
    Start([Usuario envía mensaje]) --> Global{¿Es comando global?}

    %% Interceptor global: se evalúa ANTES que cualquier estado activo
    Global -- "/cancelar" --> Cancel[Cancelar acción activa] --> Menu
    Global -- "/ayuda" --> Help[Mostrar menú de ayuda] --> Menu
    Global -- "/humano" --> Human[Derivar a contacto humano] --> Menu
    Global -- No es comando global --> Intent{¿Qué intención detecto?}

    Intent -- Evaluar FEN --> ValFEN[Validar formato FEN]
    Intent -- Sigue la Partida --> StartGame[Iniciar/retomar partida]
    Intent -- Consultar Apertura --> ParseOpening{¿Formato "/apertura jugadas" válido?}
    ParseOpening -- No --> AskFormat[Explicar formato con ejemplo: /apertura d4 d5] --> Menu
    ParseOpening -- Sí --> Simulate[Simular jugadas una a una - traducir pieza ES/EN + coronación + parse_san]
    Simulate -- Jugada N inválida o ambigua --> ReportFail[Guardar jugadas válidas previas, indicar cuál falló] --> WaitOpeningFix[Esperar corrección desde esa jugada] --> Simulate
    Simulate -- Secuencia completa válida --> CacheCheck{¿FEN resultante ya en caché?}
    CacheCheck -- Sí --> ShowOpening
    CacheCheck -- No --> LimitRate[Espaciar consulta por usuario] --> Opening[Consultar Lichess Opening Explorer con el FEN]
    Opening -- Encontrada --> SaveCache[Guardar resultado en caché con TTL] --> ShowOpening
    Opening -- No encontrada --> NotFoundOpening
    Intent -- Puzzle del día --> Puzzle[Pedir puzzle a API de Lichess]
    Intent -- No reconocida / cambio de tema --> ConfirmSwitch[Confirmar si abandona flujo actual] --> Menu

    %% --- Rama Evaluar FEN ---
    ValFEN -- Formato correcto --> ApiEval[Consultar Stockfish - motor del pool]
    ValFEN -- Formato incorrecto --> ErrFEN{¿Intentos < 2?}
    ErrFEN -- Sí --> AskFEN[Pedir corrección con ejemplo] --> ValFEN
    ErrFEN -- No --> OfferOut[Ofrecer cancelar o cambiar intención] --> Menu
    ApiEval -- OK --> ShowEval[Mostrar evaluación + mejor jugada] --> Menu
    ApiEval -- Falla / timeout --> ApiFail1[Mensaje no técnico + reintentar] --> ValFEN

    %% --- Rama Sigue la Partida ---
    StartGame --> WaitMove[Esperar jugada en notación española]
    WaitMove --> ValSyntax{¿Sintaxis SAN española válida?}

    ValSyntax -- No --> ErrSyntax{¿Intentos sintaxis < 2?}
    ErrSyntax -- Sí --> AskSyntax[Pedir corrección con ejemplo de notación española] --> WaitMove
    ErrSyntax -- No --> OfferOutMove[Ofrecer cancelar o cambiar intención] --> Menu

    ValSyntax -- Sí --> ValLegal{¿Jugada legal en el tablero?}
    ValLegal -- No --> ErrLegal{¿Intentos legalidad < 2?}
    ErrLegal -- Sí --> AskLegal[Explicar por qué es ilegal - clavada, jaque, etc.] --> WaitMove
    ErrLegal -- No --> OfferOutMove

    ValLegal -- Sí --> ApplyMove[Aplicar jugada del usuario] --> EvalAsync[Evaluar - tomar motor del pool] --> EngineReply[Motor responde su jugada] --> ShowState[Renderizar tablero como PNG] --> WaitMove
    ApplyMove -- Jaque mate / tablas --> EndGame[Anunciar fin de partida] --> Menu

    %% --- Rama Consultar Apertura ---
    ShowOpening[Mostrar línea principal y estadísticas] --> Menu
    NotFoundOpening[Avisar que no hay datos para esa secuencia] --> Menu

    %% --- Rama Puzzle del día ---
    Puzzle -- OK --> ShowPuzzle[Renderizar tablero PNG + indicar de quién es el turno] --> WaitPuzzleMove[Esperar jugada del usuario]
    WaitPuzzleMove --> CheckStep{¿Jugada = siguiente paso correcto de la secuencia?}
    CheckStep -- Sí, y quedan pasos --> EnginePuzzleReply[Reproducir respuesta del rival] --> ShowPuzzleState[Renderizar tablero PNG actualizado] --> WaitPuzzleMove
    CheckStep -- Sí, y era el último paso --> Success[Felicitar, mostrar objetivo táctico, limpiar estado de puzzle] --> Menu
    CheckStep -- No --> RetryPuzzle{¿Intentos < 2?}
    RetryPuzzle -- Sí --> HintPuzzle[Dar pista - ej. pieza a mover] --> WaitPuzzleMove
    RetryPuzzle -- No --> RevealPuzzle[Mostrar solución completa, limpiar estado de puzzle] --> Menu
    Puzzle -- Falla / timeout --> ApiFail2[Mensaje no técnico + reintentar] --> Menu

    Menu[Mostrar menú principal] --> Start
```
