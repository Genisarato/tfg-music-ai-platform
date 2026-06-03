"""
AI Music Recommendation Microservice
=====================================

FastAPI-based microservice that powers the semantic music search engine.

This service provides two main endpoints:
  - POST /refresh : Re-indexes all songs from the database into a FAISS vector index
  - POST /search  : Performs semantic search combining the user's query with their
                     musical taste profile to return personalized song recommendations

Architecture:
  1. Songs are stored in MySQL with AI-generated descriptions (via FetchSongFeaturesJob)
  2. During indexing, each song's description + valence/energy metadata is encoded into
     a 768-dimensional vector using a multilingual Sentence Transformer model
  3. The vectors are stored in a FAISS index for efficient nearest-neighbor search
  4. At search time, the user's query is encoded and optionally blended with their
     "taste profile" (average vector of liked songs) to personalize results
  5. An Ollama LLM generates a conversational reply to accompany the recommendations

Dependencies:
  - MySQL database (shared with the Laravel app)
  - Ollama running on the host machine (accessed via host.docker.internal)
"""

from fastapi import FastAPI, BackgroundTasks, HTTPException
from pydantic import BaseModel
from sentence_transformers import SentenceTransformer
import faiss
import pandas as pd
import numpy as np
from sqlalchemy import create_engine
import os
import random
from fastapi.middleware.cors import CORSMiddleware
import httpx

# --- Application Setup ---
app = FastAPI(title="TFG Music AI Service - Enhanced Edition")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],      # Allow all origins (internal Docker network)
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# --- Database Configuration ---
# Build the MySQL connection URL from environment variables (shared .env with Laravel)
DB_URL = f"mysql+pymysql://{os.getenv('DB_USERNAME')}:{os.getenv('DB_PASSWORD')}@{os.getenv('DB_HOST')}/{os.getenv('DB_DATABASE')}"
engine = create_engine(DB_URL)

# --- AI Model Configuration ---
# Multilingual model that understands queries in Catalan, Spanish, English, etc.
MODEL_NAME = 'paraphrase-multilingual-mpnet-base-v2'
INDEX_FILE = "songs_vector.index"    # FAISS binary index file
MAP_FILE = "index_map.csv"          # Maps FAISS row index → song database ID
VECTORS_FILE = "embeddings.npy"      # Raw embedding vectors for profile computation

# Load the sentence embedding model (downloads ~420MB on first run)
model = SentenceTransformer(MODEL_NAME)

# Global state for the vector index (loaded at startup or rebuilt via /refresh)
index = None          # FAISS IndexFlatL2 instance
song_map = None       # DataFrame mapping FAISS indices to song IDs
all_vectors = None    # NumPy array of all song embeddings
is_indexing = False   # Lock to prevent concurrent re-indexing


class SearchRequest(BaseModel):
    """Request body for the /search endpoint."""
    text: str              # Natural-language query from the user
    limit: int = 5         # Maximum number of recommendations to return
    exclude_ids: list[int] = []  # Song IDs to exclude from results


def run_indexing_logic():
    """
    Re-index all songs from the database into a FAISS vector index.

    This function:
      1. Queries all songs with features from the database
      2. Builds a "rich description" for each song combining title, artist,
         AI description, and semantic mood/energy tags derived from numerical values
      3. Encodes all descriptions into 768-dim vectors using Sentence Transformers
      4. Creates a new FAISS L2 (Euclidean distance) index
      5. Persists the index, mapping, and vectors to disk for fast startup
    """
    global index, song_map, all_vectors, is_indexing
    is_indexing = True
    try:
        print("🚀 Re-indexing with rich musical DNA (Text + Valence + Energy)...")

        # Fetch songs with their AI-generated features
        query = """
            SELECT s.id, s.title, s.artist, f.description, f.valence, f.energy 
            FROM songs s 
            JOIN song_features f ON s.id = f.song_id
            WHERE f.description IS NOT NULL
        """
        df = pd.read_sql(query, engine)

        if df.empty:
            print("⚠️ No data to index.")
            return

        def build_rich_description(row):
            """
            Build a semantically rich text representation of a song.

            Converts the numerical valence and energy values into descriptive
            text tags that the embedding model can understand, creating a
            richer signal for similarity search than the description alone.

            Args:
                row: DataFrame row with title, artist, description, valence, energy

            Returns:
                str: Enriched description combining all musical dimensions
            """
            v = row['valence']
            e = row['energy']

            # Map valence (0.0-1.0) to semantic mood descriptions
            if v < 0.35:
                mood_tag = "very sad, melancholic, depressive, dark"
            elif v > 0.65:
                mood_tag = "happy, joyful, positive, upbeat"
            else:
                mood_tag = "neutral mood, balanced"

            # Map energy (0.0-1.0) to semantic intensity descriptions
            if e < 0.35:
                energy_tag = "chill, calm, slow, relaxing, low energy"
            elif e > 0.65:
                energy_tag = "high energy, powerful, loud, intense, aggressive"
            else:
                energy_tag = "mid-tempo, moderate energy"

            # Combine all dimensions into a single text for embedding
            return (f"{row['title']} by {row['artist']}. "
                    f"Description: {row['description']}. "
                    f"Vibes: {mood_tag}. Intensity: {energy_tag}.")

        texts_to_encode = df.apply(build_rich_description, axis=1).tolist()

        # Generate embeddings using the sentence transformer model
        embeddings = model.encode(texts_to_encode, show_progress_bar=True)
        all_vectors = np.array(embeddings).astype('float32')

        # Create a FAISS index using L2 (Euclidean) distance
        d = all_vectors.shape[1]  # Vector dimensionality (768)
        new_index = faiss.IndexFlatL2(d)
        new_index.add(all_vectors)

        # Persist to disk for fast startup on container restart
        faiss.write_index(new_index, INDEX_FILE)
        df[['id']].to_csv(MAP_FILE, index=False)
        np.save(VECTORS_FILE, all_vectors)

        index = new_index
        song_map = df[['id']]
        print(f"✅ Indexing complete. {len(df)} songs with emotional context.")
    finally:
        is_indexing = False


@app.on_event("startup")
def startup():
    """
    Load the pre-built FAISS index and song mapping on application startup.

    If the index files exist on disk (from a previous /refresh call),
    they are loaded into memory to avoid re-indexing on every restart.
    """
    global index, song_map, all_vectors
    if os.path.exists(INDEX_FILE) and os.path.exists(MAP_FILE) and os.path.exists(VECTORS_FILE):
        try:
            index = faiss.read_index(INDEX_FILE)
            song_map = pd.read_csv(MAP_FILE)
            all_vectors = np.load(VECTORS_FILE)
            print("📁 Vector memory loaded successfully.")
        except Exception as e:
            print(f"⚠️ Error loading index files: {e}")


@app.post("/refresh")
def refresh(background_tasks: BackgroundTasks):
    """
    Trigger a full re-indexing of all songs in the background.

    This endpoint should be called after new songs have been synced and
    their features have been generated by the FetchSongFeaturesJob.
    Only one indexing operation can run at a time (423 if already running).

    Returns:
        dict: Status message indicating the re-indexing has started
    """
    if is_indexing:
        raise HTTPException(status_code=423, detail="Re-indexing is already in progress.")
    background_tasks.add_task(run_indexing_logic)
    return {"status": "processing", "message": "Recalculating vectors with musical DNA..."}


class SearchRequest(BaseModel):
    """Request body for the /search endpoint."""
    text: str              # Natural-language query (e.g., "something chill and sad")
    limit: int = 5         # Maximum number of recommendations to return
    user_id: int = None    # Optional user ID for personalized results


@app.post("/search")
def search(request: SearchRequest):
    """
    Perform a personalized semantic music search.

    This endpoint combines three signals to find the best recommendations:
      1. **Query Intent** (70%): The user's natural-language request encoded as a vector
      2. **User Profile** (30%): The average vector of the user's liked songs
      3. **Filtering**: Already-liked songs are excluded from results

    The search process:
      1. Validate the query is music-related (via Ollama)
      2. Encode the query text into a 768-dim vector
      3. If the user has liked songs, blend query vector with profile vector
      4. Search the FAISS index for nearest neighbors
      5. Filter out duplicates and already-liked songs
      6. Shuffle candidates and select the top N
      7. Generate a conversational AI reply via Ollama

    Args:
        request: SearchRequest with text, limit, and optional user_id

    Returns:
        dict: {recommended_ids: [int], ai_reply: str}
    """
    if index is None or song_map is None:
        raise HTTPException(status_code=500, detail="Search engine not initialized.")

    # Guard: only answer music-related queries
    if not is_music_related(request.text):
        return {
            "recommended_ids": [],
            "ai_reply": "I'm sorry, but I can only help with music-related topics. Would you like to search for an artist or genre?"
        }

    # --- Step 1: Load user's liked song IDs for personalization ---
    liked_song_ids = []
    if request.user_id:
        try:
            query = "SELECT song_id FROM likes WHERE user_id = %s"
            likes_df = pd.read_sql(query, engine, params=(request.user_id,))
            liked_song_ids = likes_df['song_id'].tolist()
        except Exception as e:
            print(f"⚠️ Warning reading likes: {e}")

    # --- Step 2: Encode the user's query into a vector ---
    intent_vector = model.encode([request.text])[0]

    # --- Step 3: Blend with user profile if they have liked songs ---
    if liked_song_ids:
        # Find the FAISS indices corresponding to liked songs
        liked_indices = song_map[song_map['id'].isin(liked_song_ids)].index.tolist()
        if liked_indices:
            # Compute the user's "taste profile" as the mean of liked song vectors
            profile_vector = np.mean(all_vectors[liked_indices], axis=0)
            # Weighted blend: 70% query intent, 30% personal taste
            w_text, w_profile = 0.6, 0.4
            final_vector = (intent_vector * w_text) + (profile_vector * w_profile)
        else:
            final_vector = intent_vector
    else:
        final_vector = intent_vector

    final_vector = final_vector.reshape(1, -1).astype('float32')

    # --- Step 4: FAISS nearest-neighbor search ---
    # Search for 100 candidates to have enough after filtering
    distances, indices = index.search(final_vector, 100)

    candidate_indices = [idx for idx in indices[0] if idx != -1]

    if not candidate_indices:
        return {"recommended_ids": []}

    candidate_db_ids = song_map.iloc[candidate_indices]['id'].tolist()

    # Fetch audio features for candidate songs (used for potential re-ranking)
    placeholders = ', '.join(['%s'] * len(candidate_db_ids))
    query_features = f"SELECT song_id, valence, energy FROM song_features WHERE song_id IN ({placeholders})"

    try:
        features_df = pd.read_sql(query_features, engine, params=candidate_db_ids)
        features_dict = features_df.set_index('song_id').to_dict('index')
    except:
        features_dict = {}

    # --- Step 5: Filter out liked songs and deduplicate ---
    final_candidates = []
    seen_ids = set()

    for idx in indices[0]:
        if idx == -1:
            continue
        s_id = int(song_map.iloc[idx]['id'])

        # Skip songs already in the user's library
        if s_id in liked_song_ids:
            continue

        if s_id not in seen_ids:
            final_candidates.append(s_id)
            seen_ids.add(s_id)

        # Gather 6x the requested limit to ensure variety after shuffling
        if len(final_candidates) >= request.limit * 6:
            break

    # --- Step 6: Shuffle for variety and pick final results ---
    random.shuffle(final_candidates)

    final_ids = final_candidates[:request.limit]
    if not final_ids:
        return {"recommended_ids": [], "ai_reply": "I couldn't find songs matching your request."}

    # --- Step 7: Fetch song info for the AI reply ---
    placeholders = ', '.join(['%s'] * len(final_ids))
    query_info = f"SELECT title, artist FROM songs WHERE id IN ({placeholders})"
    info_df = pd.read_sql(query_info, engine, params=tuple(final_ids))

    songs_for_ai = [f"'{row['title']}' by {row['artist']}" for _, row in info_df.iterrows()]

    # --- Step 8: Build the user's musical DNA description for the LLM ---
    user_dna_description = "a new user without a clear musical history"

    if liked_song_ids:
        placeholders = ', '.join(['%s'] * len(liked_song_ids))
        query_dna = f"SELECT AVG(valence) as avg_v, AVG(energy) as avg_e FROM song_features WHERE song_id IN ({placeholders})"
        dna_df = pd.read_sql(query_dna, engine, params=tuple(liked_song_ids))

        avg_v = dna_df['avg_v'].iloc[0] or 0.5
        avg_e = dna_df['avg_e'].iloc[0] or 0.5

        # Map numerical averages to human-readable descriptions for the LLM
        mood = "melancholic, deep, and soulful" if avg_v < 0.4 else "cheerful, positive, and optimistic" if avg_v > 0.6 else "balanced and neutral"
        intensity = "calm, relaxed, and mellow" if avg_e < 0.4 else "energetic, powerful, and intense" if avg_e > 0.6 else "moderate tempo"

        user_dna_description = f"a user who typically prefers {mood} content with an {intensity} style"

    # --- Step 9: Generate conversational AI reply via Ollama ---
    prompt = f"""
    You are a professional music curator. 
    User's music DNA: {user_dna_description}
    
    EXAMPLES OF PERFECT RESPONSES:
    User: "Donam música trista"
    Assistant: He buscat temes melancòlics que encaixen amb el teu gust per la música profunda. Espero que aquestes cançons t'ajudin a desconnectar.
    
    User: "Quiero algo movido"
    Assistant: Aquí tienes una selección con mucha energía per activar tu tarde. Son ritmos potentes que combinan perfectamente con tu estilo habitual.

    NOW DO THE SAME FOR THIS REQUEST:
    User's request: "{request.text}"
    Songs found: {', '.join(songs_for_ai)}

    RULE: 
    - Use 2 sentences max.
    - Use ONLY the user's language. 
    - NO translations in parentheses.
    - Be empathetic and calm.
    - ONLY mention or reference the songs provided in the [SELECTED RECOMMENDATIONS] list. Do not invent other artists or songs.
    
    Assistant:
    """

    # Default fallback reply if Ollama is unreachable
    ai_reply = "Here are some recommendations based on your request:"

    try:
        # Call the Ollama LLM running on the host machine
        ollama_url = "http://host.docker.internal:11434/api/generate"

        response = httpx.post(
            ollama_url,
            json={
                "model": "llama3.2:3b",
                "prompt": prompt,
                "stream": False,
                "options": {
                    "temperature": 0.85  # Slightly creative for natural responses
                }
            },
            timeout=20.0
        )

        if response.status_code == 200:
            ai_reply = response.json().get('response', ai_reply)
    except Exception as e:
        print(f"⚠️ Error connecting to Ollama: {e}")
    print(ai_reply)
    return {
        "recommended_ids": final_ids,
        "ai_reply": ai_reply
    }


def is_music_related(text: str) -> bool:
    """
    Use the Ollama LLM to determine if a user query is music-related.

    This guard prevents the chatbot from being used for non-music purposes.
    If the LLM is unreachable, defaults to True (permissive) to avoid
    blocking legitimate requests.

    Args:
        text: The user's input message

    Returns:
        bool: True if the query is music-related, False otherwise
    """
    check_prompt = f"""
    Analyze if the following user input is related to music, artists, genres, moods for listening, or song requests.
    Respond ONLY with 'YES' or 'NO'.
    
    Input: "{text}"
    Music related? 
    """
    try:
        ollama_url = "http://host.docker.internal:11434/api/generate"
        response = httpx.post(ollama_url, json={
                "model": "llama3.2:3b",
                "prompt": check_prompt,
                "stream": False
            }, timeout=30.0)

        result = response.json().get('response', 'YES').strip().upper()
        return "YES" in result
    except:
        # Default to permissive if Ollama is unreachable
        return True