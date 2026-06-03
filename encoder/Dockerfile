# ==============================================================================
# Dockerfile - AI Recommendation Microservice
# ==============================================================================
#
# Builds the Python FastAPI service that powers semantic music search.
#
# Key steps:
#   1. Install system libraries needed by FAISS and MySQL connector
#   2. Install Python dependencies (sentence-transformers, faiss-cpu, etc.)
#   3. Pre-download the multilingual embedding model (~420MB) at build time
#      to avoid downloading it on every container start
#   4. Run the FastAPI app with Uvicorn on port 8000
# ==============================================================================

FROM python:3.10-slim

WORKDIR /app

# Install system dependencies:
#   - libgomp1: Required by FAISS for OpenMP parallel processing
#   - libmariadb-dev: MySQL client library for SQLAlchemy connections
#   - gcc + pkg-config: Needed to compile some Python packages
RUN apt-get update && apt-get install -y \
    libgomp1 \
    libmariadb-dev \
    gcc \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install Python dependencies
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Pre-download the Sentence Transformer model at build time
# This avoids a ~420MB download on every container start
RUN python -c "from sentence_transformers import SentenceTransformer; SentenceTransformer('paraphrase-multilingual-mpnet-base-v2')"

COPY . .

# Start the FastAPI server with hot-reload for development
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000", "--reload"]