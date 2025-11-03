"""
Minimal skeleton to fine-tune OpenCLIP on (image, text, style) CSV.
Fill in dataloader and training loop for your dataset.
"""
from __future__ import annotations

import csv
import os
from dataclasses import dataclass
from typing import Iterator, Tuple

import torch
from torch.utils.data import Dataset, DataLoader
import torchvision.transforms as T
from PIL import Image
import open_clip


@dataclass
class Row:
    image_path: str
    text: str
    style: str


class CsvDataset(Dataset[Tuple[Image.Image, str]]):
    def __init__(self, csv_path: str, root: str, preprocess):
        self.rows: list[Row] = []
        with open(csv_path, "r", encoding="utf-8") as f:
            reader = csv.DictReader(f)
            for r in reader:
                self.rows.append(
                    Row(r["image_path"], r["text"], r.get("style", ""))
                )
        self.root = root
        self.preprocess = preprocess

    def __len__(self) -> int:
        return len(self.rows)

    def __getitem__(self, idx: int):
        row = self.rows[idx]
        img = Image.open(os.path.join(self.root, row.image_path)).convert("RGB")
        return self.preprocess(img), row.text


def train(csv_path: str, img_root: str, arch: str = "ViT-B-32", pretrained: str = "laion2b_s34b_b79k", epochs: int = 3, batch_size: int = 128):
    device = "cuda" if torch.cuda.is_available() else "cpu"
    model, _, preprocess = open_clip.create_model_and_transforms(arch, pretrained=pretrained)
    tokenizer = open_clip.get_tokenizer(arch)
    model = model.to(device)
    dataset = CsvDataset(csv_path, img_root, preprocess)
    loader = DataLoader(dataset, batch_size=batch_size, shuffle=True, num_workers=2)
    optimizer = torch.optim.AdamW(model.parameters(), lr=5e-5)

    model.train()
    for epoch in range(epochs):
        for images, texts in loader:
            images = images.to(device)
            tokens = tokenizer(list(texts)).to(device)
            image_features = model.encode_image(images)
            text_features = model.encode_text(tokens)
            image_features = image_features / image_features.norm(dim=-1, keepdim=True)
            text_features = text_features / text_features.norm(dim=-1, keepdim=True)
            logit_scale = model.logit_scale.exp()
            logits_per_image = logit_scale * image_features @ text_features.t()
            logits_per_text = logits_per_image.t()
            labels = torch.arange(images.size(0), device=device)
            loss = (torch.nn.functional.cross_entropy(logits_per_image, labels) + torch.nn.functional.cross_entropy(logits_per_text, labels)) / 2
            optimizer.zero_grad()
            loss.backward()
            optimizer.step()
        print(f"epoch {epoch+1}: loss={loss.item():.4f}")

    os.makedirs("./models", exist_ok=True)
    torch.save(model.state_dict(), "./models/openclip_finetuned.pt")


if __name__ == "__main__":
    import argparse

    ap = argparse.ArgumentParser()
    ap.add_argument("--csv", required=True)
    ap.add_argument("--images", required=True)
    ap.add_argument("--epochs", type=int, default=3)
    ap.add_argument("--batch", type=int, default=128)
    args = ap.parse_args()

    train(args.csv, args.images, epochs=args.epochs, batch_size=args.batch)


