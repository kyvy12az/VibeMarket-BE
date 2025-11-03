FROM python:3.11-slim

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    libgl1 libglib2.0-0 && \
    rm -rf /var/lib/apt/lists/*

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY app ./app

ENV MODEL_NAME="openclip:ViT-B-32/laion2b_s34b_b79k" \
    INDEX_PATH="/app/app/index/faiss.index" \
    CATALOG_PATH="/app/app/index/catalog.jsonl"

EXPOSE 8080
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8080"]


