import sys
import json
import chess
import chess.svg
import cairosvg
import chess.engine
import re

PIEZA_ES_A_EN = {"C": "N", "A": "B", "T": "R", "D": "Q", "R": "K"}
PATRON_CORONACION = re.compile(r"^(.*?[a-h][1-8])(?:=|\()?([CATDR])\)?([+#]?)$")

def _traducir_pieza(jugada):
    if jugada and jugada[0] in PIEZA_ES_A_EN:
        return PIEZA_ES_A_EN[jugada[0]] + jugada[1:]
    return jugada

def traducir_san_es(jugada_es):
    jugada_es = jugada_es.strip()
    match = PATRON_CORONACION.match(jugada_es)
    if match and jugada_es[-1] not in "12345678":
        base, letra_promo, jaque = match.groups()
        base = _traducir_pieza(base)
        letra_promo_en = PIEZA_ES_A_EN.get(letra_promo, letra_promo)
        return f"{base}={letra_promo_en}{jaque}"
    return _traducir_pieza(jugada_es)

def ejecutar(accion, fen, flow, jugada_san=None):
    board = chess.Board(fen)
    engine = chess.engine.SimpleEngine.popen_uci("/usr/games/stockfish")
    evaluacion = None

    try:
        if jugada_san:
            move_en = traducir_san_es(jugada_san)
            move = board.parse_san(move_en)
            board.push(move)

        if accion == "jugar_negras_inicio":
            result = engine.play(board, chess.engine.Limit(time=0.5))
            board.push(result.move)
        elif accion == "play" and not board.is_game_over():
            result = engine.play(board, chess.engine.Limit(time=0.5))
            board.push(result.move)
        elif accion == "analizar" and jugada_san:
            info = engine.analyse(board, chess.engine.Limit(depth=15))
            score = info["score"].white()
            if score.is_mate():
                evaluacion = f"Mate en {score.mate()}"
            else:
                evaluacion = f"{score.score() / 100.0:+.2f}"

        engine.quit()

        orientacion = chess.BLACK if flow == "negras" else chess.WHITE
        svg_data = chess.svg.board(board=board, size=400, orientation=orientacion)
        cairosvg.svg2png(bytestring=svg_data.encode('utf-8'), write_to='tablero.png')

        print(json.dumps({
            "valido": True,
            "fen": board.fen(),
            "png": "tablero.png",
            "eval": evaluacion
        }))
    except Exception as e:
        engine.quit()
        print(json.dumps({"valido": False, "error": str(e)}))

if __name__ == "__main__":
    if len(sys.argv) >= 4:
        accion = sys.argv[1]
        fen = sys.argv[2]
        flow = sys.argv[3]
        jugada = sys.argv[4] if len(sys.argv) > 4 else None
        ejecutar(accion, fen, flow, jugada)
