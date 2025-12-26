import { NextResponse } from "next/server";
import { sql } from "@vercel/postgres";

// POST - inicjalizuj tabele bazy danych
export async function POST() {
  try {
    // Tworzenie tabel
    await sql`
      CREATE TABLE IF NOT EXISTS roki (
        id SERIAL PRIMARY KEY,
        nazwa VARCHAR(100) NOT NULL,
        data_start DATE NOT NULL,
        data_koniec DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS cele_rok (
        id SERIAL PRIMARY KEY,
        rok_id INTEGER NOT NULL REFERENCES roki(id) ON DELETE CASCADE,
        kategoria VARCHAR(50) NOT NULL,
        cel TEXT,
        planowane_godziny_dziennie DECIMAL(4,2) DEFAULT 1.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS okresy (
        id SERIAL PRIMARY KEY,
        rok_id INTEGER NOT NULL REFERENCES roki(id) ON DELETE CASCADE,
        nazwa VARCHAR(100) NOT NULL,
        data_start DATE NOT NULL,
        data_koniec DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS cele_okres (
        id SERIAL PRIMARY KEY,
        okres_id INTEGER NOT NULL REFERENCES okresy(id) ON DELETE CASCADE,
        kategoria VARCHAR(50) NOT NULL,
        cel TEXT,
        status DECIMAL(3,1),
        osiagniety BOOLEAN,
        uwagi TEXT,
        completed_at TIMESTAMP,
        pozycja INTEGER DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS zadania (
        id SERIAL PRIMARY KEY,
        okres_id INTEGER REFERENCES okresy(id) ON DELETE SET NULL,
        kategoria VARCHAR(50) NOT NULL,
        dzien DATE NOT NULL,
        zadanie VARCHAR(255) NOT NULL,
        cel_todo TEXT,
        planowany_czas INTEGER DEFAULT 0,
        faktyczny_czas INTEGER,
        status VARCHAR(20) DEFAULT 'nowe',
        godzina_start TIME,
        godzina_koniec TIME,
        pozycja_harmonogram INTEGER,
        jest_cykliczne BOOLEAN DEFAULT false,
        recurring_template_id INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS stale_zadania (
        id SERIAL PRIMARY KEY,
        nazwa VARCHAR(255) NOT NULL,
        kategoria VARCHAR(50) NOT NULL,
        cel_todo TEXT,
        planowany_czas INTEGER DEFAULT 0,
        typ_powtarzania VARCHAR(50) NOT NULL DEFAULT 'codziennie',
        dni_tygodnia VARCHAR(50),
        dzien_miesiaca INTEGER,
        dni_przed_koncem_roku INTEGER,
        dni_przed_koncem_okresu INTEGER,
        minuty_po_starcie INTEGER,
        dodaj_do_listy BOOLEAN DEFAULT false,
        godzina_start TIME,
        godzina_koniec TIME,
        aktywne BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS dni_wolne (
        id SERIAL PRIMARY KEY,
        dzien DATE NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS gamification_stats (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL DEFAULT 1,
        total_xp INTEGER NOT NULL DEFAULT 0,
        current_level INTEGER NOT NULL DEFAULT 1,
        prestige INTEGER NOT NULL DEFAULT 0,
        freeze_days_available INTEGER NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS streaks (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL DEFAULT 1,
        streak_type VARCHAR(50) NOT NULL,
        current_count INTEGER NOT NULL DEFAULT 0,
        best_count INTEGER NOT NULL DEFAULT 0,
        last_date DATE,
        frozen_today BOOLEAN NOT NULL DEFAULT false,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(user_id, streak_type)
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS xp_log (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL DEFAULT 1,
        xp_amount INTEGER NOT NULL,
        xp_type VARCHAR(50) NOT NULL,
        multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00,
        description VARCHAR(255),
        condition_text VARCHAR(255),
        reference_id INTEGER,
        reference_type VARCHAR(50),
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS achievements (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL DEFAULT 1,
        achievement_key VARCHAR(50) NOT NULL,
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notified BOOLEAN NOT NULL DEFAULT false,
        UNIQUE(user_id, achievement_key)
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS daily_challenges (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL DEFAULT 1,
        challenge_date DATE NOT NULL,
        challenge_key VARCHAR(50) NOT NULL,
        challenge_data TEXT,
        xp_reward INTEGER NOT NULL,
        completed BOOLEAN NOT NULL DEFAULT false,
        completed_at TIMESTAMP,
        UNIQUE(user_id, challenge_date, challenge_key)
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS combo_state (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL DEFAULT 1,
        combo_date DATE NOT NULL,
        current_combo INTEGER NOT NULL DEFAULT 0,
        max_combo_today INTEGER NOT NULL DEFAULT 0,
        last_task_time TIMESTAMP,
        UNIQUE(user_id, combo_date)
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS abstract_goals (
        id SERIAL PRIMARY KEY,
        user_id INTEGER NOT NULL DEFAULT 1,
        nazwa VARCHAR(255) NOT NULL,
        opis TEXT,
        xp_reward INTEGER NOT NULL DEFAULT 100,
        completed BOOLEAN NOT NULL DEFAULT false,
        completed_at TIMESTAMP,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        aktywne BOOLEAN NOT NULL DEFAULT true
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS kategorie (
        id SERIAL PRIMARY KEY,
        klucz VARCHAR(50) NOT NULL UNIQUE,
        nazwa VARCHAR(100) NOT NULL,
        typ VARCHAR(20) NOT NULL DEFAULT 'wszystkie',
        aktywne BOOLEAN DEFAULT true,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS godziny_okres (
        id SERIAL PRIMARY KEY,
        okres_id INTEGER NOT NULL REFERENCES okresy(id) ON DELETE CASCADE,
        kategoria VARCHAR(50) NOT NULL,
        planowane_godziny_dziennie DECIMAL(4,2) DEFAULT 1.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(okres_id, kategoria)
      )
    `;

    await sql`
      CREATE TABLE IF NOT EXISTS stale_overrides (
        id SERIAL PRIMARY KEY,
        stale_zadanie_id INTEGER NOT NULL REFERENCES stale_zadania(id) ON DELETE CASCADE,
        okres_id INTEGER NOT NULL REFERENCES okresy(id) ON DELETE CASCADE,
        godzina_start TIME,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE(stale_zadanie_id, okres_id)
      )
    `;

    // Wstaw domyślne kategorie
    await sql`
      INSERT INTO kategorie (klucz, nazwa, typ) VALUES
        ('zapianowany', 'Zapianowany', 'wszystkie'),
        ('klejpan', 'Klejpan', 'wszystkie'),
        ('marka_langer', 'Marka Langer', 'wszystkie'),
        ('marketing_construction', 'Marketing Construction', 'wszystkie'),
        ('fjo', 'FJO (Firma Jako Osobowość)', 'wszystkie'),
        ('obsluga_telefoniczna', 'Obsługa telefoniczna', 'wszystkie'),
        ('sprawy_organizacyjne', 'Sprawy Organizacyjne', 'zadania')
      ON CONFLICT (klucz) DO NOTHING
    `;

    // Utwórz początkowe statystyki gamifikacji
    await sql`
      INSERT INTO gamification_stats (user_id, total_xp, current_level, prestige, freeze_days_available)
      VALUES (1, 0, 1, 0, 0)
      ON CONFLICT DO NOTHING
    `;

    return NextResponse.json({
      success: true,
      message: "Database initialized successfully!"
    });
  } catch (error) {
    console.error("Error initializing database:", error);
    return NextResponse.json({
      error: "Failed to initialize database",
      details: error instanceof Error ? error.message : "Unknown error"
    }, { status: 500 });
  }
}

// GET - sprawdź status bazy
export async function GET() {
  try {
    const result = await sql`SELECT COUNT(*) as count FROM kategorie`;
    return NextResponse.json({
      status: "connected",
      kategorie_count: result.rows[0].count
    });
  } catch (error) {
    return NextResponse.json({
      status: "not_initialized",
      error: error instanceof Error ? error.message : "Unknown error"
    });
  }
}
