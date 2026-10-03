# Моята библиотека (My Library)

A personal reading tracker built with Laravel. Search books, keep them on shelves
(reading / read / want to read), rate them, read free Bulgarian books right in the
site, and get AI-generated recommendations. The UI is in Bulgarian; code and comments
are in English.

## Features

- **Search** Google Books (with a language filter) and [Chitanka](https://chitanka.info),
  the free Bulgarian library, side by side. Chitanka is searchable by title *and* author.
- **Shelves**: add a book as *Чета / Прочетени / За четене*, rate it 1–5, keep notes.
  "My books" has a tab per status.
- **Book page** with details, a Google Books link and embedded preview where Google allows it.
- **Reader**: Chitanka books open in an in-site paged reader (plain text, cached on disk).
  A Google Books record is matched to a Chitanka edition by title so it can be read too.
- **Sign in with Google** (Laravel Socialite) next to classic email + password (Breeze).
- **Recommendations**: a queued job asks Gemini for 5 books based on what you have read
  and rated; they appear on the dashboard.
- Rate limiting on search and on actions that call external services.

## Stack

Laravel 13 · PHP 8.3 · MySQL · Breeze (Blade) + Tailwind · Laravel HTTP client + Cache ·
Socialite · Gemini API (Google AI Studio, free tier) via the database queue.

## Setup

Requirements: PHP 8.3+, Composer, Node.js, MySQL.

```bash
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

Create an empty MySQL database called `books` (the defaults in `.env.example` use
`root` without a password; adjust `DB_*` if yours differ), then:

```bash
php artisan migrate
php artisan serve        # http://127.0.0.1:8000
php artisan queue:work   # in a second terminal: needed for recommendations
```

### Configuration (`.env`)

| Variable | Purpose |
| --- | --- |
| `GOOGLE_BOOKS_KEY` | Google Books API key (Books API enabled in Google Cloud). Search works without it but with a tiny shared quota. |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | OAuth client for "Sign in with Google". The "Sign in with Google" buttons only appear when the ID is set. |
| `GOOGLE_REDIRECT_URI` | Must match the authorised redirect URI exactly, e.g. `http://127.0.0.1:8000/auth/google/callback`. |
| `GEMINI_API_KEY` | Google AI Studio key for recommendations. |
| `GEMINI_MODEL` | Defaults to `gemini-3.8-flash` (free tier). Google retires models, so change it here if you get a 404. |

Never commit `.env`; it is git-ignored.

## Tests

```bash
php artisan test
```

External services are never called in tests: Google Books, Chitanka and Gemini are faked
with `Http::fake()`, and the suite uses an in-memory SQLite database and a sync queue.
Covered: the API clients (mapping, caching, errors), search, shelves, the book page,
the reader, Google sign-in (with a mocked Socialite provider), recommendations,
rate limiting and the Bulgarian UI.

## How it works

- `App\Services\GoogleBooks`, `Chitanka` and `Gemini` wrap the external APIs. Responses
  are cached (24 h) and a failure in one source never breaks a page that uses another.
- `books` holds every book seen; the `book_user` pivot holds a user's status, rating and
  notes. Chitanka books are stored with `source = chitanka` and ids like `chitanka-book-234`.
- Chitanka rate-limits clients, so full texts are downloaded once, stored under
  `storage/app/private/chitanka/` and paged from disk.
- `App\Jobs\GenerateRecommendations` builds the prompt from read books, asks Gemini for
  structured JSON, drops books already on the user's shelves and replaces the previous
  batch in a transaction. The dashboard polls while the job is pending.

## Notes

- Gemini free-tier content may be used by Google to improve its products; only titles,
  authors and ratings are sent, never notes.
- Text from Chitanka belongs to its authors/translators under the licences listed there;
  the site links back to the source.
