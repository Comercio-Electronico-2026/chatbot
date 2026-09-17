# Evidencias de Git en GitHub — repo del chatbot (Lab 5a / 5b y siguientes)

GitHub Classroom ya no está disponible. La evidencia de Git del curso se lleva en
la organización de GitHub que se creó: **`Comercio-Electronico-2026`**. No hay
autograder que genere un repo por alumno; el flujo es manual y se apoya en **una
rama por alumno más un Pull Request** que funciona como tablero de evidencia.

## Modelo

- Cada alumno trabaja en su rama `alumno/<CARNET>` (tu carnet en mayúsculas, como
  aparece en la lista de clase). Ver la tabla de abajo.
- `main` no se toca. Cada alumno abre **un** Pull Request `alumno/<CARNET>` →
  `main`, en estado *Draft*, y **no se fusiona**. Ese PR acumula los commits de
  5a y 5b; su historial (commits, fechas, diffs, la pestaña *Files changed*) es la
  evidencia que se califica.
- El documento de diseño va en `docs/diseno-conversacional.md` de la rama del
  alumno. El código del bot (Sesión 2) va en `src/`.
- El token del bot y el `.env` **nunca** se suben (van en `.gitignore`).

Son 21 ramas de alumno, una por carnet de la lista de clase.

## Rama por alumno

| # | Carnet | Rama |
|---|---|---|
| 1 | AV21009 | `alumno/AV21009` |
| 2 | AS22027 | `alumno/AS22027` |
| 3 | BH23004 | `alumno/BH23004` |
| 4 | CM21091 | `alumno/CM21091` |
| 5 | CM21092 | `alumno/CM21092` |
| 6 | GT22004 | `alumno/GT22004` |
| 7 | GH22026 | `alumno/GH22026` |
| 8 | GD21011 | `alumno/GD21011` |
| 9 | HB21009 | `alumno/HB21009` |
| 10 | HV21011 | `alumno/HV21011` |
| 11 | JO20004 | `alumno/JO20004` |
| 12 | LL22030 | `alumno/LL22030` |
| 13 | LQ21001 | `alumno/LQ21001` |
| 14 | MT23014 | `alumno/MT23014` |
| 15 | MM22108 | `alumno/MM22108` |
| 16 | MC21105 | `alumno/MC21105` |
| 17 | OT18005 | `alumno/OT18005` |
| 18 | PR21064 | `alumno/PR21064` |
| 19 | RC22009 | `alumno/RC22009` |
| 20 | SP21013 | `alumno/SP21013` |
| 21 | SM21008 | `alumno/SM21008` |

---

## Pasos del alumno

```bash
# 1. Clonar el repositorio del curso
git clone https://github.com/Comercio-Electronico-2026/chatbot.git
cd chatbot

# 2. Crear TU rama (carnet en MAYUSCULAS, como en la lista de clase)
git switch -c alumno/AV21009          # <-- cambiar por tu carnet

# 3. Configurar tu identidad (si no lo has hecho en esta maquina)
git config user.name  "Nombre Apellido"
git config user.email "tu-correo@ues.edu.sv"

# 4. Preparar el token del bot SIN subirlo
cp .env.example .env
#   editar .env y poner BOT_TOKEN=... (lo da BotFather). .env ya esta en .gitignore.

# 5. Escribir el documento de diseno de la Actividad 1
#   docs/diseno-conversacional.md  (lista de chequeo, inventario de intenciones,
#   diagrama de flujo y dialogo de muestra, todo en ese archivo)
git add docs/diseno-conversacional.md
git commit -m "Lab 5a: inventario de intenciones y dialogo del camino feliz"

# 6. Subir tu rama la primera vez
git push -u origin alumno/AV21009
#   (las siguientes veces basta con: git push)
```

7. En GitHub, abrir **un** Pull Request:
   - *base*: `main` ← *compare*: `alumno/<CARNET>`.
   - Título: `Lab 5 — <CARNET> — Nombre Apellido`.
   - Botón *Create pull request* → elegir **Create draft pull request**. **No
     fusionar.**
   - En la descripción, ir anotando qué entra: `5a: documento de diseño`,
     luego `5b: bot funcional + API REST`.
   - Pasar al docente el enlace del PR (es lo que pide la guía: "informar al
     docente el enlace de tu Pull Request").

8. Para el Lab 5b se sigue en **la misma rama y el mismo PR**: más commits, se
   actualiza la descripción.

**Si el token se te escapó a un commit:** avisar al docente, revocarlo de
inmediato en BotFather (`/token` → *Revoke current token*), generar uno nuevo y
reescribir el historial (`git rebase` o `git filter-repo`) antes del siguiente
push.
