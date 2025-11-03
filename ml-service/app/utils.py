from __future__ import annotations

from PIL import Image


def extract_dominant_colors(pil: Image.Image, max_colors: int = 4):
    # simple resize + palette quantization
    small = pil.resize((50, max(1, int(50 * pil.height / max(1, pil.width)))), Image.BILINEAR)
    pal = small.convert("P", palette=Image.ADAPTIVE, colors=max(16, max_colors))
    palette = pal.getpalette()
    color_counts = sorted(pal.getcolors() or [], reverse=True)
    hexes = []
    for count, idx in color_counts[: max_colors]:
        r, g, b = palette[idx * 3 : idx * 3 + 3]
        hexes.append("#%02X%02X%02X" % (r, g, b))
    return hexes


def guess_style_from_caption(caption: str) -> tuple[str, int]:
    cap = (caption or "").lower()
    vocab = {
        "Minimalist": ["minimal", "clean", "simple", "neutral"],
        "Modern": ["modern", "sleek", "contemporary"],
        "Vintage": ["vintage", "retro", "classic"],
        "Streetwear": ["street", "urban", "hoodie", "sneaker"],
        "Formal": ["suit", "blazer", "trousers", "tie"],
        "Sporty": ["sport", "athletic", "jersey", "track"],
        "Bohemian": ["boho", "floral", "flowy"],
        "Chic": ["elegant", "chic", "fashionable"],
    }
    best = ("Uncertain", 0)
    for style, kws in vocab.items():
        score = sum(1 for kw in kws if kw in cap)
        if score > best[1]:
            best = (style, score)
    confidence = min(95, 50 + best[1] * 12) if best[1] > 0 else 40
    return best[0], confidence


