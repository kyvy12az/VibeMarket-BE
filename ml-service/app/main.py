from fastapi import FastAPI, File, UploadFile, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel
from typing import List, Optional
import base64
import io
from PIL import Image
import os

from .inference import StylistEngine


app = FastAPI(title="VibeMarket AI Stylist", version="0.1.0")


engine: Optional[StylistEngine] = None


@app.on_event("startup")
def _startup() -> None:
    global engine
    engine = StylistEngine(
        model_name=os.getenv("MODEL_NAME", "openclip:ViT-B-32/laion2b_s34b_b79k"),
        index_path=os.getenv("INDEX_PATH", "./app/index/faiss.index"),
        catalog_path=os.getenv("CATALOG_PATH", "./app/index/catalog.jsonl"),
    )


@app.get("/health")
def health():
    return {"status": "ok"}


class AnalyzeRequest(BaseModel):
    image_base64: Optional[str] = None


def _read_image_from_base64(data_uri: str) -> Image.Image:
    payload = data_uri
    if "," in payload:
        payload = payload.split(",", 1)[1]
    raw = base64.b64decode(payload)
    return Image.open(io.BytesIO(raw)).convert("RGB")


@app.post("/analyze")
async def analyze(
    image: UploadFile | None = File(default=None),
    body: AnalyzeRequest | None = None,
):
    if engine is None:
        raise HTTPException(503, "engine not ready")

    pil: Image.Image | None = None
    if image is not None:
        content = await image.read()
        pil = Image.open(io.BytesIO(content)).convert("RGB")
    elif body and body.image_base64:
        pil = _read_image_from_base64(body.image_base64)

    if pil is None:
        raise HTTPException(400, "no image provided")

    result = engine.analyze_image(pil, top_k=8)
    return JSONResponse(result)


