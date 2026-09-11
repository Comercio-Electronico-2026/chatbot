# Evaluación de Diseño Conversacional - NutriGuía (Pareja A: JO20004)

## Notas del Mago de Oz
* **Guion 1 (Camino Feliz):** Se rompe en el turno 1. El usuario saluda de forma natural con "Hola", pero el diagrama obliga estrictamente al uso del comando `/start`. Esto enviaría al usuario directamente al nodo de "Entrada no válida".
* **Guion 2 (Camino con Fricción):** Se traba en el turno 2 (Dato mal escrito). Cuando un nutriente no es reconocido, el bot entra en un bucle infinito preguntando una y otra vez en el nodo "Informar error y solicitar nuevamente", ya que el diagrama carece de un tope de intentos.

---

## 5 Preguntas de Evaluación

**1. Alcance y descubribilidad**
* **Veredicto:** Parcial
* **Evidencia:** El diálogo de muestra proyecta un menú claro y enumerado que explica las capacidades del bot inmediatamente después del comando `/start`.
* **Mejora:** Modificar el nodo inicial del diagrama de flujo para que acepte saludos comunes ("hola", "buenas") como activadores válidos del menú principal, no solo el comando exacto.

**2. Grice en el guion (Cantidad, Relación, Manera)**
* **Veredicto:** Resuelto
* **Evidencia:** El bot responde con listas concisas, sin jerga médica compleja, y hace una sola pregunta por turno para no abrumar al cliente.
* **Mejora:** Restringir las listas de fuentes naturales a un máximo de tres viñetas por mensaje para optimizar la lectura rápida en dispositivos móviles.

**3. Grice (Calidad y Relleno de datos)**
* **Veredicto:** Parcial
* **Evidencia:** Cumple la máxima de calidad al advertir transparentemente que sus respuestas no sustituyen una recomendación médica. Sin embargo, su diagrama siempre solicita los nutrientes en el segundo turno.
* **Mejora:** Añadir una validación previa en el flujo que extraiga directamente las entidades (el nutriente) del texto inicial para evitar pedir datos que el usuario ya entregó en su primer mensaje.

**4. Manejo de errores (Guion 2)**
* **Veredicto:** No resuelto
* **Evidencia:** Las ramas del diagrama generan ciclos infinitos en "Informar error y solicitar nuevamente" y no existe ninguna vía de reparación por niveles ni escalamiento a un agente.
* **Mejora:** Añadir un contador lógico en el diagrama; al detectar el tercer fallo consecutivo, romper el ciclo y derivar al nodo "Agendar asesoría" con un humano.

**5. Reglas de producto**
* **Veredicto:** Resuelto
* **Evidencia:** El flujo ilustra correctamente las intenciones globales para "Ayuda" y "Cancelar", e incluye el paso obligatorio de "Confirmar solicitud" antes de finalizar una cita.
* **Mejora:** Especificar en el diagrama que, al ejecutar el nodo "Cancelar operación", el bot enviará un mensaje garantizando el borrado inmediato de los datos ingresados en esa sesión.
