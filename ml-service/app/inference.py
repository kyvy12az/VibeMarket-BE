from __future__ import annotations

import json
import os
from dataclasses import dataclass
from typing import Any, Dict, List, Optional, Tuple

import numpy as np
import faiss  # type: ignore
from PIL import Image

import open_clip
import torch

from .utils import extract_dominant_colors, guess_style_from_caption


@dataclass
class CatalogItem:
    id: int
    name: str
    price: float
    image: Optional[str]
    category: Optional[str]
    rating: Optional[float]
    shop: Optional[str]


class StylistEngine:
    def __init__(self, model_name: str, index_path: str, catalog_path: str) -> None:
        self.device = "cuda" if torch.cuda.is_available() else "cpu"
        self.model, self.preprocess, self.tokenizer = self._load_clip(model_name)
        self.model.eval()

        self.index_path = index_path
        self.catalog = self._load_catalog(catalog_path)
        self.index = self._load_faiss(index_path)

        # style labels for zero-shot probing (can be fine-tuned later)
        self.style_texts = [
            "minimalist outfit",
            "modern style",
            "vintage clothing",
            "streetwear fashion",
            "formal attire",
            "sporty outfit",
            "bohemian dress",
            "chic style",
        ]
        with torch.no_grad():
            tokens = self.tokenizer(self.style_texts)
            self.style_emb = self._encode_text(tokens)

    def _load_clip(self, spec: str):
        # spec format: "openclip:ARCH/PRETRAINED"
        if not spec.startswith("openclip:"):
            spec = "openclip:ViT-B-32/laion2b_s34b_b79k"
        _, rest = spec.split(":", 1)
        arch, pretrained = rest.split("/", 1)
        model, _, preprocess = open_clip.create_model_and_transforms(
            arch, pretrained=pretrained
        )
        tokenizer = open_clip.get_tokenizer(arch)
        model = model.to(self.device)
        return model, preprocess, tokenizer

    def _load_faiss(self, path: str):
        if os.path.exists(path):
            return faiss.read_index(path)
        dim = 512  # ViT-B-32
        return faiss.IndexFlatIP(dim)

    def _load_catalog(self, path: str) -> List[CatalogItem]:
        items: List[CatalogItem] = []
        if os.path.exists(path):
            with open(path, "r", encoding="utf-8") as f:
                for line in f:
                    obj = json.loads(line)
                    items.append(
                        CatalogItem(
                            id=int(obj.get("id", 0)),
                            name=str(obj.get("name", "")),
                            price=float(obj.get("price", 0)),
                            image=obj.get("image"),
                            category=obj.get("category"),
                            rating=(float(obj.get("rating")) if obj.get("rating") is not None else None),
                            shop=obj.get("shop"),
                        )
                    )
        return items

    @torch.no_grad()
    def _encode_image(self, pil: Image.Image) -> torch.Tensor:
        img = self.preprocess(pil).unsqueeze(0).to(self.device)
        feat = self.model.encode_image(img)
        feat = feat / feat.norm(dim=-1, keepdim=True)
        return feat

    @torch.no_grad()
    def _encode_text(self, tokens: torch.Tensor) -> torch.Tensor:
        tokens = tokens.to(self.device)
        feat = self.model.encode_text(tokens)
        feat = feat / feat.norm(dim=-1, keepdim=True)
        return feat

    def _style_from_zero_shot(self, pil: Image.Image) -> Tuple[str, float]:
        img_emb = self._encode_image(pil)
        scores = (img_emb @ self.style_emb.T).squeeze(0).float().cpu().numpy()
        idx = int(np.argmax(scores))
        conf = float(np.clip(scores[idx], 0, 1) * 100)
        return self.style_texts[idx].split()[0].capitalize(), conf

    def _search_products(self, image_emb: torch.Tensor, top_k: int) -> List[Dict[str, Any]]:
        if self.index is None or self.index.ntotal == 0 or not self.catalog:
            return []
        q = image_emb.cpu().numpy().astype("float32")
        sims, ids = self.index.search(q, top_k)
        sims = sims[0]
        ids = ids[0]
        res: List[Dict[str, Any]] = []
        for sim, idx in zip(sims, ids):
            if idx < 0 or idx >= len(self.catalog):
                continue
            item = self.catalog[idx]
            res.append(
                {
                    "id": item.id,
                    "name": item.name,
                    "price": item.price,
                    "image": item.image,
                    "matchScore": float(np.clip(sim, 0, 1) * 100),
                    "reason": "Khớp embedding",
                    "category": item.category,
                    "rating": item.rating,
                    "shop": item.shop,
                }
            )
        return res

    def analyze_image(self, pil: Image.Image, top_k: int = 8) -> Dict[str, Any]:
        colors = extract_dominant_colors(pil, max_colors=4)

        # Zero-shot style (có thể thay bằng caption + heuristic)
        detected, conf = self._style_from_zero_shot(pil)

        with torch.no_grad():
            emb = self._encode_image(pil)
        products = self._search_products(emb, top_k=top_k)

        mood = (
            "Professional & Clean"
            if detected == "Minimalist"
            else ("Urban & Bold" if detected == "Streetwear" else "Stylish & Confident")
        )

        return {
            "success": True,
            "analysis": {
                "caption": None,  # optional if you add a captioner
                "detectedStyle": detected,
                "confidence": int(conf),
                "colorPalette": colors,
                "mood": mood,
                "suggestions": [],
                "models": {
                    "embedding": "openclip",
                },
                "embedding": None,
                "products": products,
            },
        }


