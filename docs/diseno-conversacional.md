# Diseño Conversacional - Bot de Ajedrez

## Actividad 1 - Lista de Chequeo
* **¿Quién usará el chatbot?** Jugadores de ajedrez competitivo.
* **¿Qué problema o problemas resuelve?** Permite evaluar rápidamente posiciones tácticas y consultar líneas principales de apertura sin abrir interfaces pesadas.
* **¿Qué necesidades específicas tienen?** Obtener evaluación de módulo (Stockfish) a partir de una cadena FEN y revisar variantes de aperturas (ej. sistema Londres o Gambito de Dama d4).
* **¿Qué preguntas se pueden hacer?** "¿Cuál es la evaluación de esta posición FEN?", "¿Qué línea principal sigue después de d4 d5?".
* **¿Qué tipo de respuesta espero en cada caso?** Texto con la evaluación numérica (ej. +1.5), imagen del tablero, o notación algebraica de la variante.
* **¿Cuál es su edad, ocupación, intereses, nivel de experiencia con tecnología?** Estudiantes y profesionales, jugadores de torneos, alto nivel de experiencia técnica y familiaridad con notación ajedrecística.
* **¿Cuáles son los escenarios alternativos?** El usuario ingresa una cadena FEN inválida, la API de evaluación no responde, o solicita una apertura no registrada.
* **¿UI, Accesibilidad?** Interfaz basada en texto puro y respuestas rápidas.
* **¿Cómo haré para validar mi prototipo?** Pruebas de usabilidad ingresando FENs complejos extraídos de partidas reales.
* **¿Privacidad?** No se guardan registros de las preparaciones de apertura ni historiales de FENs consultados más allá del tiempo de sesión.

## Inventario de Intenciones

| Intención | Ejemplo de enunciado | Frecuencia | Prioridad | Dato / API |
| :--- | :--- | :--- | :--- | :--- |
| Evaluar FEN | "Evalúa esta posición: rnbqkbnr/pppppppp/8/8/3P4/8/PPP1PPPP/RNBQKBNR b KQkq - 0 1" | Alta | 1 | API de Lichess / Stockfish |
| Consultar Apertura | "¿Líneas principales para d4?" | Media | 2 | Base de datos local/API de aperturas |
| Ayuda | "/help" o "¿Qué puedes hacer?" | Baja | 3 | Ninguna |

## Diálogo de Muestra (Camino Feliz)
**Usuario:** /start
**Bot:** Hola. Puedo evaluar posiciones si me envías una cadena FEN, o darte líneas de apertura si me indicas el movimiento inicial. ¿Qué necesitas?
**Usuario:** Evaluar posición.
**Bot:** Envíame la cadena FEN exacta de la posición.
**Usuario:** r1bqkb1r/pppp1ppp/2n2n2/4p3/3P4/5N2/PPP1PPPP/RNBQKB1R w KQkq - 2 4
**Bot:** Evaluando... La posición tiene una ventaja blanca de +0.8. El mejor movimiento sugerido es dxe5. ¿Necesitas evaluar otra posición?

## Diagrama de Flujo

```mermaid
graph TD
    A[Usuario envía mensaje] --> B{¿Es un comando válido o FEN?}
    B -- Sí, es FEN --> C[Validar formato FEN]
    C -- Formato correcto --> D[Consultar API de Evaluación]
    D --> E[Devolver evaluación y mejor jugada]
    C -- Formato incorrecto --> F[Pedir corrección de sintaxis FEN]
    B -- Sí, consulta apertura --> G[Buscar en base de datos d4/e4]
    G --> H[Devolver línea principal]
    B -- No / Otro --> I[Mostrar menú de ayuda]
