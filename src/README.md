# src/

Código del bot de Telegram (Lab 5b). Cada alumno trabaja en su rama
`alumno/<CARNET>`. El token va en `.env` (nunca se sube).

## Configuración local

Copiar `.env.example` como `.env` y completar `BOT_TOKEN`. El modelo local se
configura con `OLLAMA_URL` y `OLLAMA_MODEL`. El bot usa reglas para las acciones
críticas de citas y Ollama solo para consultas abiertas.

## Ejecución

```bash
php src/bot.php
```

Durante desarrollo se usa long polling. El estado y los logs se guardan en
`.runtime/`, y el offset en `.offset`; ambos archivos están excluidos de Git.
