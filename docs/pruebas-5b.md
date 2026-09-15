# Pruebas - Laboratorio 5b

## Flujo principal

| Prueba | Entrada | Resultado |
|---|---|---|
| Inicio | `/start` | Muestra menú principal |
| Saludo | `Hola` | Muestra menú principal |
| Fuentes | `Quiero fuentes naturales de vitamina B12` | Muestra 3 fuentes sin volver a pedir el nutriente |
| Comparación | `Comparar vitamina C y vitamina B12` | Compara ambos nutrientes |
| Ayuda | `/ayuda` | Muestra opciones disponibles |
| Cancelar | `/cancelar` | Cancela y elimina datos temporales |

## Manejo de errores

| Prueba | Entrada | Resultado |
|---|---|---|
| Nutriente inválido | `asdf` | Solicita nuevamente |
| 3 intentos | 3 datos inválidos | Detiene reintentos y ofrece ayuda |
| Fuera de alcance | `¿Qué dosis de vitamina D debo tomar?` | Deriva a profesional |
| Ciudad inexistente | `/clima CiudadQueNoExiste123` | Muestra mensaje claro |

## API REST

Servicio utilizado: Open-Meteo.

Prueba:

`/clima San Salvador`

Resultado esperado: temperatura actual de la ciudad.

Si el servicio no responde, el bot muestra un mensaje comprensible y registra
el error en el log.
