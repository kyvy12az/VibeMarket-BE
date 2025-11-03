VibeMarket ML Service (Scaffold)

Endpoints
- GET /health
- POST /analyze (multipart `image` or JSON `{image_base64}`)

Env vars
- MODEL_NAME: openclip:ARCH/PRETRAINED (default ViT-B-32/laion2b_s34b_b79k)
- INDEX_PATH: path to FAISS index (optional; empty index returns no products)
- CATALOG_PATH: JSONL catalog rows `{id,name,price,image,category,rating,shop}` aligned with index ids

Run locally
```
pip install -r requirements.txt
uvicorn app.main:app --reload --port 8080
```

Docker
```
docker build -t vibeml .
docker run -p 8080:8080 vibeml
```

Training (skeleton)
- Fine-tune OpenCLIP on your CSV (image_path, text, style)
- Export weights and build FAISS index from product embeddings

Folder expectations
- app/index/faiss.index (optional)
- app/index/catalog.jsonl (optional)

