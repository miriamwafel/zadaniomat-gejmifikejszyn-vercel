# Zadaniomat OKR

System zarządzania celami i zadaniami z gamifikacją. Przepisany z pluginu WordPress na Next.js z Vercel Postgres.

## Funkcje

- **Zarządzanie zadaniami** - dodawanie, edycja, usuwanie zadań
- **Kategorie** - organizacja zadań według kategorii
- **Gamifikacja** - system XP i poziomów za ukończone zadania
- **Harmonogram dzienny** - przeglądanie zadań per dzień
- **Roki i okresy** - planowanie celów na 90-dniowe okresy

## Szybki start na Vercel

### 1. Deploy na Vercel

1. Zaimportuj repozytorium na Vercel
2. Vercel automatycznie wykryje Next.js

### 2. Dodaj bazę danych Vercel Postgres

1. W panelu Vercel przejdź do swojego projektu
2. Kliknij **Storage** → **Create Database** → **Postgres**
3. Nazwij bazę (np. `zadaniomat-db`)
4. Kliknij **Connect** - zmienne środowiskowe zostaną automatycznie dodane

### 3. Zainicjalizuj bazę danych

Po pierwszym wdrożeniu:
1. Otwórz aplikację
2. Kliknij "Zainicjalizuj bazę danych"
3. Gotowe!

## Rozwój lokalny

```bash
# Zainstaluj zależności
npm install

# Skopiuj plik zmiennych środowiskowych
cp .env.local.example .env.local

# Uzupełnij POSTGRES_URL w .env.local

# Uruchom serwer deweloperski
npm run dev
```

## Struktura projektu

```
src/
├── app/
│   ├── api/           # API endpoints
│   │   ├── zadania/   # CRUD zadań
│   │   ├── roki/      # CRUD roków
│   │   ├── okresy/    # CRUD okresów
│   │   ├── gamification/ # System XP
│   │   ├── kategorie/ # Zarządzanie kategoriami
│   │   └── init-db/   # Inicjalizacja bazy
│   ├── page.tsx       # Dashboard główny
│   └── layout.tsx     # Layout aplikacji
├── components/        # Komponenty React
├── db/               # Schema bazy danych (Drizzle)
└── lib/              # Utilities
```

## API Endpoints

| Endpoint | Metody | Opis |
|----------|--------|------|
| `/api/zadania` | GET, POST, PUT, DELETE | Zarządzanie zadaniami |
| `/api/roki` | GET, POST, PUT, DELETE | Zarządzanie rokami |
| `/api/okresy` | GET, POST, PUT, DELETE | Zarządzanie okresami |
| `/api/gamification` | GET, POST | Statystyki i XP |
| `/api/kategorie` | GET, POST, PUT, DELETE | Kategorie |
| `/api/init-db` | GET, POST | Status i inicjalizacja bazy |

## Technologie

- **Next.js 16** - Framework React
- **Vercel Postgres** - Baza danych
- **Drizzle ORM** - Type-safe ORM
- **Tailwind CSS** - Styling
- **TypeScript** - Typowanie
