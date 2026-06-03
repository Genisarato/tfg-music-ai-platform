# 🎵 TFG Music Recommender — AI-Powered Music Discovery

An intelligent music recommendation web application that integrates with **Spotify**, uses **AI-generated song DNA** (via Ollama/LLaMA), and performs **semantic search** powered by FAISS vector similarity. Users authenticate through Spotify, sync their liked songs, and interact with a conversational chatbot that understands natural language and their personal musical taste.

> **Final Degree Project (TFG)** — Full-stack application combining Laravel 12, Vue 3, and a Python-based AI microservice.

---

## 📋 Table of Contents

- [Architecture Overview](#-architecture-overview)
- [Tech Stack](#-tech-stack)
- [Features](#-features)
- [Project Structure](#-project-structure)
- [Prerequisites](#-prerequisites)
- [Installation & Setup](#-installation--setup)
- [Environment Variables](#-environment-variables)
- [Running the Application](#-running-the-application)
- [How It Works](#-how-it-works)
- [API Endpoints](#-api-endpoints)
- [Database Schema](#-database-schema)
- [Testing](#-testing)
- [License](#-license)

---

## 🏗 Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                        Docker Network                           │
│                                                                 │
│  ┌──────────┐   ┌───────────┐   ┌──────────┐   ┌────────────┐  │
│  │  Nginx   │──▶│  Laravel  │──▶│  MySQL   │   │  AI Service│  │
│  │ :8000    │   │  PHP-FPM  │   │  :3306   │   │  (FastAPI) │  │
│  │          │   │  + Vite   │   │          │   │  :8080     │  │
│  └──────────┘   │  :5173    │   └──────────┘   └──────────┘  │
│                 └─────┬─────┘         ▲              ▲        │
│                       │               │              │        │
│                       └───────────────┴──────────────┘        │
│                                                               │
│                 ┌──────────────────────────┐                  │
│                 │  Ollama (host machine)   │                  │
│                 │  LLaMA 3.2:3b           │                  │
│                 │  :11434                  │                  │
│                 └──────────────────────────┘                  │
└───────────────────────────────────────────────────────────────┘
                          ▲
                          │ OAuth 2.0
                ┌─────────┴──────────┐
                │    Spotify API     │
                │  (Authentication   │
                │   + Library Sync)  │
                └────────────────────┘
```

The system is composed of **four Docker services** plus an external LLM:

| Service         | Role                                                                 |
|-----------------|----------------------------------------------------------------------|
| **app**         | Laravel 12 + PHP 8.4 FPM — backend API, queue worker, Vite dev      |
| **webserver**   | Nginx reverse proxy serving the application on port 8000             |
| **db**          | MySQL 8.0 — persistent storage for users, songs, features, and likes |
| **ai-service**  | Python FastAPI microservice — FAISS vector search + sentence embeddings |
| **Ollama**      | Runs locally on the host — LLaMA 3.2 for song analysis and chat replies |

---

## 🛠 Tech Stack

### Backend
| Technology       | Version | Purpose                                        |
|------------------|---------|------------------------------------------------|
| PHP              | 8.4     | Runtime for the Laravel framework              |
| Laravel          | 12      | MVC framework, routing, queues, ORM            |
| Inertia.js       | 2.x     | Server-driven SPA bridge (Laravel ↔ Vue)       |
| Laravel Fortify  | 1.x     | Authentication scaffolding (login, 2FA, etc.)  |
| MySQL            | 8.0     | Relational database                            |

### Frontend
| Technology       | Version | Purpose                                        |
|------------------|---------|------------------------------------------------|
| Vue.js           | 3.5     | Reactive UI framework                          |
| TypeScript       | 5.x     | Type safety for frontend code                  |
| Tailwind CSS     | 4.x     | Utility-first CSS framework                    |
| Vite             | 7.x     | Build tool and dev server with HMR             |
| Reka UI          | 2.x     | Headless Vue component library                 |
| Lucide Icons     | —       | Icon set used throughout the UI                |

### AI / ML Service
| Technology            | Purpose                                              |
|-----------------------|------------------------------------------------------|
| Python 3.10           | Runtime for the AI microservice                      |
| FastAPI               | Async REST API framework                             |
| Sentence Transformers | Multilingual text embeddings (`paraphrase-multilingual-mpnet-base-v2`) |
| FAISS                 | Facebook's vector similarity search library          |
| Ollama + LLaMA 3.2    | Local LLM for song analysis and conversational replies |
| Pandas / NumPy        | Data manipulation and numerical computing            |
| SQLAlchemy            | Direct DB access from the Python service             |

### Infrastructure
| Technology       | Purpose                                        |
|------------------|------------------------------------------------|
| Docker Compose   | Multi-container orchestration                  |
| Nginx            | Web server / reverse proxy                     |

---

## ✨ Features

- **Spotify OAuth Login** — Authenticate via Spotify and sync your entire library automatically
- **AI-Powered Song DNA** — Each song is analyzed by LLaMA 3.2 to generate rich musical metadata (valence, energy, mood descriptions)
- **Semantic Music Search** — Natural language queries are converted to vectors and matched against song embeddings using FAISS
- **Personalized Recommendations** — Your listening history shapes a "user profile vector" that biases results toward your taste (70% query / 30% profile)
- **Conversational Chatbot** — An AI assistant that understands music-related requests in multiple languages and responds empathetically
- **Liked Songs Library** — Browse, search, sort, and paginate your synced Spotify library
- **Background Queue Processing** — Song syncing and feature extraction run asynchronously via Laravel queues so the UI stays responsive
- **Two-Factor Authentication** — Optional 2FA via Laravel Fortify for enhanced account security
- **Dark Mode / Appearance Settings** — User-configurable theme preferences
- **Responsive Design** — Works on desktop and mobile with sidebar and bottom navigation

---

## 📁 Project Structure

```
tfgCode/
├── app/
│   ├── Actions/Fortify/          # Fortify auth action classes
│   ├── Concerns/                 # Shared traits
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── ChatBotController.php    # Chatbot page + AI search proxy
│   │   │   ├── SongController.php       # Liked songs list + manual sync
│   │   │   ├── SpotifyController.php    # OAuth flow + library sync trigger
│   │   │   ├── StartController.php      # Landing page
│   │   │   └── Settings/               # Profile, password, 2FA controllers
│   │   ├── Middleware/
│   │   │   ├── HandleAppearance.php     # Theme cookie middleware
│   │   │   └── HandleInertiaRequests.php # Inertia shared data
│   │   └── Requests/Settings/          # Form request validation classes
│   ├── Jobs/
│   │   ├── SyncSpotifySongsJob.php      # Fetches liked songs from Spotify API
│   │   └── FetchSongFeaturesJob.php     # Analyzes songs with Ollama LLM
│   ├── Models/
│   │   ├── User.php                     # User model (Spotify + Fortify)
│   │   ├── Song.php                     # Song model with Spotify metadata
│   │   ├── SongFeature.php              # Audio features + AI description
│   │   └── Recommendation.php           # Recommendation tracking model
│   └── Providers/                       # Service providers
│
├── database/
│   ├── migrations/                      # Schema definitions
│   ├── factories/                       # Model factories for testing
│   └── seeders/                         # Database seeders
│
├── encoder/                             # 🐍 Python AI Microservice
│   ├── main.py                          # FastAPI app: indexing, search, LLM
│   ├── requirements.txt                 # Python dependencies
│   ├── Dockerfile                       # Python service container
│   ├── embeddings.npy                   # Pre-computed song vectors (generated)
│   ├── songs_vector.index               # FAISS index file (generated)
│   └── index_map.csv                    # Song ID ↔ index mapping (generated)
│
├── resources/
│   ├── css/app.css                      # Global stylesheet entry point
│   ├── js/
│   │   ├── app.ts                       # Vue + Inertia app bootstrap
│   │   ├── ssr.ts                       # Server-side rendering entry
│   │   ├── pages/                       # Inertia page components
│   │   │   ├── Landing.vue              # Public landing page
│   │   │   ├── Dashboard.vue            # Post-login dashboard
│   │   │   ├── chatBot/Index.vue        # Chatbot conversation UI
│   │   │   ├── likedSongs/Index.vue     # Liked songs library view
│   │   │   ├── auth/                    # Login, Register, 2FA pages
│   │   │   └── settings/               # Profile, Password, Appearance
│   │   ├── components/                  # Reusable Vue components
│   │   │   ├── Landing/                 # Landing page sections
│   │   │   ├── principalComponents/     # Header, Sidebar, BottomNav
│   │   │   └── ui/                      # Base UI components (shadcn-vue)
│   │   ├── layouts/                     # Page layout wrappers
│   │   ├── composables/                 # Vue composables (hooks)
│   │   ├── types/                       # TypeScript type definitions
│   │   └── lib/                         # Utility functions
│   └── views/
│       └── app.blade.php               # Root Blade template for Inertia
│
├── routes/
│   ├── web.php                          # Main application routes
│   ├── settings.php                     # User settings routes
│   └── console.php                      # Artisan console commands
│
├── docker-config/
│   ├── nginx/default.conf               # Nginx server configuration
│   └── php/local.ini                    # Custom PHP settings
│
├── tests/
│   ├── Feature/                         # Feature / integration tests
│   ├── Unit/                            # Unit tests
│   ├── Pest.php                         # Pest test configuration
│   └── TestCase.php                     # Base test case
│
├── Dockerfile                           # PHP app container definition
├── docker-compose.yml                   # Multi-service orchestration
├── composer.json                        # PHP dependencies
├── package.json                         # Node.js dependencies
├── vite.config.ts                       # Vite build configuration
├── phpunit.xml                          # PHPUnit / Pest configuration
├── eslint.config.js                     # ESLint configuration
└── .env.example                         # Environment variable template
```

---

## 📦 Prerequisites

Make sure the following are installed on your system:

- **Docker** & **Docker Compose** (v2+)
- **Ollama** — Installed on the host machine (not inside Docker)
  - Download: [https://ollama.com](https://ollama.com)
  - Pull the required model: `ollama pull llama3.2:3b`
- **A Spotify Developer Account** — To create an app and obtain API credentials
  - Dashboard: [https://developer.spotify.com/dashboard](https://developer.spotify.com/dashboard)

---

## 🚀 Installation & Setup

### 1. Clone the repository

```bash
git clone <repository-url>
cd tfgCode
```

### 2. Configure environment variables

```bash
cp .env.example .env
```

Edit `.env` with your credentials (see [Environment Variables](#-environment-variables) below).

### 3. Build and start Docker containers

```bash
docker-compose up -d --build
```

### 4. Install dependencies and initialize the application

```bash
# Enter the app container
docker exec -it tfg-app bash

# Install PHP dependencies
composer install

# Generate application key
php artisan key:generate

# Run database migrations
php artisan migrate

# Install Node.js dependencies
npm install
```

### 5. Start Ollama on the host machine

```bash
ollama serve
# In another terminal:
ollama pull llama3.2:3b
```

### 6. Start the development servers

From inside the app container:

```bash
# Option A: Run all services concurrently
composer dev

# Option B: Run individually
php artisan serve &
php artisan queue:listen --tries=1 &
npm run dev
```

### 7. Access the application

- **Web App**: [http://localhost:8000](http://localhost:8000)
- **Vite HMR**: [http://localhost:5173](http://localhost:5173)
- **AI Service**: [http://localhost:8080/docs](http://localhost:8080/docs) (FastAPI Swagger)

---

## 🔑 Environment Variables

Create your `.env` file from `.env.example` and configure the following key variables:

| Variable                 | Description                                        | Example                        |
|--------------------------|----------------------------------------------------|--------------------------------|
| `APP_NAME`               | Application name                                   | `tfgWeb`                       |
| `APP_URL`                | Base URL of the application                        | `http://localhost:8000`        |
| `DB_CONNECTION`          | Database driver                                    | `mysql`                        |
| `DB_HOST`                | Database host (Docker service name)                | `db`                           |
| `DB_PORT`                | Database port                                      | `3306`                         |
| `DB_DATABASE`            | Database name                                      | `tfg_database`                 |
| `DB_USERNAME`            | Database user                                      | `root`                         |
| `DB_PASSWORD`            | Database password                                  | `root`                         |
| `SPOTIFY_CLIENT_ID`      | Spotify app client ID                              | *(from Spotify dashboard)*     |
| `SPOTIFY_CLIENT_SECRET`  | Spotify app client secret                          | *(from Spotify dashboard)*     |
| `SPOTIFY_REDIRECT_URI`   | OAuth callback URL                                 | `http://localhost:8000/callback` |
| `OLLAMA_URL`             | Ollama API base URL                                | `http://host.docker.internal:11434` |
| `OLLAMA_MODEL`           | Ollama model for song analysis                     | `llama3.2:3b`                  |
| `QUEUE_CONNECTION`       | Queue driver                                       | `database`                     |

> ⚠️ **Important**: Never commit your `.env` file. It contains sensitive API keys and secrets.

---

## 🚀 Running the Application

### Development (with Docker)

```bash
# Start all containers
docker-compose up -d

# Watch logs
docker-compose logs -f

# Enter the app container
docker exec -it tfg-app bash

# Run the dev server (inside container)
composer dev
```

### Development (without Docker)

Ensure you have PHP 8.4, MySQL 8.0, Node.js 20+, and Composer installed locally.

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev
```

### Production Build

```bash
npm run build          # Standard build
npm run build:ssr      # With server-side rendering
```

---

## 🧠 How It Works

### 1. Spotify Authentication & Sync

```
User clicks "Login with Spotify"
  └─▶ SpotifyController::redirect()    → Redirects to Spotify OAuth
       └─▶ Spotify Authorization Page
            └─▶ SpotifyController::callback()
                 ├─▶ Exchange code for access token
                 ├─▶ Fetch user profile from Spotify API
                 ├─▶ Create/update User in database
                 ├─▶ Dispatch SyncSpotifySongsJob (background)
                 └─▶ Redirect to chatbot page
```

### 2. Song Sync Pipeline (Background Queue)

```
SyncSpotifySongsJob
  ├─▶ Paginate through Spotify /me/tracks API (50 per page)
  ├─▶ Upsert each song into the `songs` table
  ├─▶ Sync the user ↔ song relationships in the `likes` pivot table
  └─▶ Dispatch FetchSongFeaturesJob

FetchSongFeaturesJob
  ├─▶ Find songs without features (batch of 20)
  ├─▶ For each song:
  │     ├─▶ Send prompt to Ollama (LLaMA 3.2)
  │     ├─▶ Parse JSON response (valence, energy, danceability, description...)
  │     └─▶ Store in `song_features` table
  ├─▶ If more songs remain → re-dispatch self with 5s delay
  └─▶ Otherwise → mark user as `is_syncing = false`
```

### 3. AI Recommendation Search

```
User types: "I want something chill and melancholic"
  └─▶ ChatBotController::ask()
       └─▶ POST to AI Service /search
            ├─▶ Check if query is music-related (via Ollama)
            ├─▶ Encode query text → 768-dim vector (Sentence Transformer)
            ├─▶ If user has liked songs:
            │     ├─▶ Compute user profile vector (mean of liked song vectors)
            │     └─▶ Blend: 70% query + 30% profile = final vector
            ├─▶ FAISS nearest-neighbor search (top 100 candidates)
            ├─▶ Filter out already-liked songs
            ├─▶ Shuffle and select top N results
            ├─▶ Generate conversational reply via Ollama
            └─▶ Return { recommended_ids, ai_reply }
```

---

## 🔌 API Endpoints

### Web Routes (`routes/web.php`)

| Method | URI                    | Controller                    | Description                           | Auth |
|--------|------------------------|-------------------------------|---------------------------------------|------|
| GET    | `/`                    | `StartController@index`       | Landing page                          | No   |
| GET    | `/auth/spotify`        | `SpotifyController@redirect`  | Initiate Spotify OAuth                | No   |
| GET    | `/callback`            | `SpotifyController@callback`  | Spotify OAuth callback                | No   |
| POST   | `/logout`              | `SpotifyController@logout`    | Log out and clear session             | No   |
| GET    | `/chatbot`             | `ChatBotController@index`     | Chatbot page                          | Yes  |
| POST   | `/chatbot/ask`         | `ChatBotController@ask`       | Send message to AI                    | No   |
| POST   | `/chatbot/like`        | `ChatBotController@like`      | Like a recommended song on Spotify    | No   |
| GET    | `/liked-songs`         | `SongController@index`        | Liked songs library                   | Yes  |
| POST   | `/liked-songs/sync-songs` | `SongController@sync`      | Trigger manual library sync           | Yes  |

### AI Service Endpoints (`encoder/main.py`)

| Method | URI        | Description                                        |
|--------|------------|----------------------------------------------------|
| POST   | `/search`  | Semantic search with user profile blending         |
| POST   | `/refresh` | Re-index all songs (rebuild FAISS + embeddings)    |

---

## 🗄 Database Schema

```
┌──────────────┐     ┌──────────────┐     ┌──────────────────┐
│    users     │     │    songs     │     │  song_features   │
├──────────────┤     ├──────────────┤     ├──────────────────┤
│ id           │     │ id           │     │ id               │
│ name         │     │ spotify_track│     │ song_id (FK)     │
│ email        │     │ title        │     │ valence          │
│ spotify_id   │     │ artist       │     │ energy           │
│ access_token │     │ album_name   │     │ danceability     │
│ refresh_token│     │ image        │     │ acousticness     │
│ avatar       │     │ timestamps   │     │ instrumentalness │
│ is_syncing   │     └──────┬───────┘     │ speechiness      │
│ password     │            │             │ tempo            │
│ 2FA fields   │            │             │ loudness         │
│ timestamps   │     ┌──────┴───────┐     │ key              │
└──────┬───────┘     │    likes     │     │ mode             │
       │             ├──────────────┤     │ time_signature   │
       └─────────────┤ user_id (FK) │     │ description      │
                     │ song_id (FK) │     │ timestamps       │
                     │ spotify_added│     └──────────────────┘
                     │ timestamps   │
                     └──────────────┘     ┌──────────────────┐
                                          │ recommendations  │
                                          ├──────────────────┤
                                          │ id               │
                                          │ user_id (FK)     │
                                          │ song_id (FK)     │
                                          │ context          │
                                          │ liked_by_user    │
                                          │ timestamps       │
                                          └──────────────────┘
```

---

## 🧪 Testing

```bash
# Run all tests (lint + PHPUnit/Pest)
composer test

# Run only Pest tests
php artisan test

# Run with coverage
php artisan test --coverage

# Lint PHP code
composer lint

# Lint and format frontend code
npm run lint
npm run format
```

---

## 📄 License

This project is licensed under the [MIT License](LICENSE).

---

<p align="center">
  Built with ❤️ as a Final Degree Project (TFG)
</p>
