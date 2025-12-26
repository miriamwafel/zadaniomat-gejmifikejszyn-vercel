import {
  pgTable,
  serial,
  varchar,
  text,
  integer,
  boolean,
  date,
  time,
  timestamp,
  decimal,
  uniqueIndex,
} from "drizzle-orm/pg-core";

// =============================================
// ROKI (90-dniowe okresy planowania)
// =============================================
export const roki = pgTable("roki", {
  id: serial("id").primaryKey(),
  nazwa: varchar("nazwa", { length: 100 }).notNull(),
  dataStart: date("data_start").notNull(),
  dataKoniec: date("data_koniec").notNull(),
  createdAt: timestamp("created_at").defaultNow(),
});

// =============================================
// CELE ROCZNE
// =============================================
export const celeRok = pgTable("cele_rok", {
  id: serial("id").primaryKey(),
  rokId: integer("rok_id").notNull().references(() => roki.id),
  kategoria: varchar("kategoria", { length: 50 }).notNull(),
  cel: text("cel"),
  planowaneGodzinyDziennie: decimal("planowane_godziny_dziennie", { precision: 4, scale: 2 }).default("1.00"),
  createdAt: timestamp("created_at").defaultNow(),
});

// =============================================
// OKRESY (podokresy w ramach roku)
// =============================================
export const okresy = pgTable("okresy", {
  id: serial("id").primaryKey(),
  rokId: integer("rok_id").notNull().references(() => roki.id),
  nazwa: varchar("nazwa", { length: 100 }).notNull(),
  dataStart: date("data_start").notNull(),
  dataKoniec: date("data_koniec").notNull(),
  createdAt: timestamp("created_at").defaultNow(),
});

// =============================================
// CELE OKRESOWE
// =============================================
export const celeOkres = pgTable("cele_okres", {
  id: serial("id").primaryKey(),
  okresId: integer("okres_id").notNull().references(() => okresy.id),
  kategoria: varchar("kategoria", { length: 50 }).notNull(),
  cel: text("cel"),
  status: decimal("status", { precision: 3, scale: 1 }),
  osiagniety: boolean("osiagniety"),
  uwagi: text("uwagi"),
  completedAt: timestamp("completed_at"),
  pozycja: integer("pozycja").default(1),
  createdAt: timestamp("created_at").defaultNow(),
});

// =============================================
// ZADANIA
// =============================================
export const zadania = pgTable("zadania", {
  id: serial("id").primaryKey(),
  okresId: integer("okres_id").references(() => okresy.id),
  kategoria: varchar("kategoria", { length: 50 }).notNull(),
  dzien: date("dzien").notNull(),
  zadanie: varchar("zadanie", { length: 255 }).notNull(),
  celTodo: text("cel_todo"),
  planowanyCzas: integer("planowany_czas").default(0),
  faktycznyCzas: integer("faktyczny_czas"),
  status: varchar("status", { length: 20 }).default("nowe"),
  godzinaStart: time("godzina_start"),
  godzinaKoniec: time("godzina_koniec"),
  pozycjaHarmonogram: integer("pozycja_harmonogram"),
  jestCykliczne: boolean("jest_cykliczne").default(false),
  recurringTemplateId: integer("recurring_template_id"),
  createdAt: timestamp("created_at").defaultNow(),
});

// =============================================
// STALE ZADANIA (szablony cykliczne)
// =============================================
export const staleZadania = pgTable("stale_zadania", {
  id: serial("id").primaryKey(),
  nazwa: varchar("nazwa", { length: 255 }).notNull(),
  kategoria: varchar("kategoria", { length: 50 }).notNull(),
  celTodo: text("cel_todo"),
  planowanyCzas: integer("planowany_czas").default(0),
  typPowtarzania: varchar("typ_powtarzania", { length: 50 }).notNull().default("codziennie"),
  dniTygodnia: varchar("dni_tygodnia", { length: 50 }),
  dzienMiesiaca: integer("dzien_miesiaca"),
  dniPrzedKoncemRoku: integer("dni_przed_koncem_roku"),
  dniPrzedKoncemOkresu: integer("dni_przed_koncem_okresu"),
  minutyPoStartcie: integer("minuty_po_starcie"),
  dodajDoListy: boolean("dodaj_do_listy").default(false),
  godzinaStart: time("godzina_start"),
  godzinaKoniec: time("godzina_koniec"),
  aktywne: boolean("aktywne").default(true),
  createdAt: timestamp("created_at").defaultNow(),
});

// =============================================
// DNI WOLNE
// =============================================
export const dniWolne = pgTable("dni_wolne", {
  id: serial("id").primaryKey(),
  dzien: date("dzien").notNull().unique(),
  createdAt: timestamp("created_at").defaultNow(),
});

// =============================================
// GAMIFICATION - STATYSTYKI
// =============================================
export const gamificationStats = pgTable("gamification_stats", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().default(1),
  totalXp: integer("total_xp").notNull().default(0),
  currentLevel: integer("current_level").notNull().default(1),
  prestige: integer("prestige").notNull().default(0),
  freezeDaysAvailable: integer("freeze_days_available").notNull().default(0),
  createdAt: timestamp("created_at").defaultNow(),
  updatedAt: timestamp("updated_at").defaultNow(),
});

// =============================================
// GAMIFICATION - STREAKI
// =============================================
export const streaks = pgTable("streaks", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().default(1),
  streakType: varchar("streak_type", { length: 50 }).notNull(),
  currentCount: integer("current_count").notNull().default(0),
  bestCount: integer("best_count").notNull().default(0),
  lastDate: date("last_date"),
  frozenToday: boolean("frozen_today").notNull().default(false),
  createdAt: timestamp("created_at").defaultNow(),
  updatedAt: timestamp("updated_at").defaultNow(),
}, (table) => ({
  userStreakIdx: uniqueIndex("user_streak_idx").on(table.userId, table.streakType),
}));

// =============================================
// GAMIFICATION - XP LOG
// =============================================
export const xpLog = pgTable("xp_log", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().default(1),
  xpAmount: integer("xp_amount").notNull(),
  xpType: varchar("xp_type", { length: 50 }).notNull(),
  multiplier: decimal("multiplier", { precision: 4, scale: 2 }).notNull().default("1.00"),
  description: varchar("description", { length: 255 }),
  conditionText: varchar("condition_text", { length: 255 }),
  referenceId: integer("reference_id"),
  referenceType: varchar("reference_type", { length: 50 }),
  earnedAt: timestamp("earned_at").defaultNow(),
});

// =============================================
// GAMIFICATION - ACHIEVEMENTS
// =============================================
export const achievements = pgTable("achievements", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().default(1),
  achievementKey: varchar("achievement_key", { length: 50 }).notNull(),
  earnedAt: timestamp("earned_at").defaultNow(),
  notified: boolean("notified").notNull().default(false),
}, (table) => ({
  userAchievementIdx: uniqueIndex("user_achievement_idx").on(table.userId, table.achievementKey),
}));

// =============================================
// GAMIFICATION - DAILY CHALLENGES
// =============================================
export const dailyChallenges = pgTable("daily_challenges", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().default(1),
  challengeDate: date("challenge_date").notNull(),
  challengeKey: varchar("challenge_key", { length: 50 }).notNull(),
  challengeData: text("challenge_data"),
  xpReward: integer("xp_reward").notNull(),
  completed: boolean("completed").notNull().default(false),
  completedAt: timestamp("completed_at"),
}, (table) => ({
  userDateChallengeIdx: uniqueIndex("user_date_challenge_idx").on(table.userId, table.challengeDate, table.challengeKey),
}));

// =============================================
// GAMIFICATION - COMBO STATE
// =============================================
export const comboState = pgTable("combo_state", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().default(1),
  comboDate: date("combo_date").notNull(),
  currentCombo: integer("current_combo").notNull().default(0),
  maxComboToday: integer("max_combo_today").notNull().default(0),
  lastTaskTime: timestamp("last_task_time"),
}, (table) => ({
  userComboDateIdx: uniqueIndex("user_combo_date_idx").on(table.userId, table.comboDate),
}));

// =============================================
// ABSTRACT GOALS
// =============================================
export const abstractGoals = pgTable("abstract_goals", {
  id: serial("id").primaryKey(),
  userId: integer("user_id").notNull().default(1),
  nazwa: varchar("nazwa", { length: 255 }).notNull(),
  opis: text("opis"),
  xpReward: integer("xp_reward").notNull().default(100),
  completed: boolean("completed").notNull().default(false),
  completedAt: timestamp("completed_at"),
  createdAt: timestamp("created_at").defaultNow(),
  aktywne: boolean("aktywne").notNull().default(true),
});

// =============================================
// KATEGORIE (opcje)
// =============================================
export const kategorie = pgTable("kategorie", {
  id: serial("id").primaryKey(),
  klucz: varchar("klucz", { length: 50 }).notNull().unique(),
  nazwa: varchar("nazwa", { length: 100 }).notNull(),
  typ: varchar("typ", { length: 20 }).notNull().default("wszystkie"), // 'cele', 'zadania', 'wszystkie'
  aktywne: boolean("aktywne").default(true),
  createdAt: timestamp("created_at").defaultNow(),
});

// =============================================
// GODZINY OKRES (planowane godziny per kategoria per okres)
// =============================================
export const godzinyOkres = pgTable("godziny_okres", {
  id: serial("id").primaryKey(),
  okresId: integer("okres_id").notNull().references(() => okresy.id),
  kategoria: varchar("kategoria", { length: 50 }).notNull(),
  planowaneGodzinyDziennie: decimal("planowane_godziny_dziennie", { precision: 4, scale: 2 }).default("1.00"),
  createdAt: timestamp("created_at").defaultNow(),
}, (table) => ({
  okresKategoriaIdx: uniqueIndex("okres_kategoria_idx").on(table.okresId, table.kategoria),
}));

// =============================================
// STALE OVERRIDES (nadpisania godzin stałych zadań per okres)
// =============================================
export const staleOverrides = pgTable("stale_overrides", {
  id: serial("id").primaryKey(),
  staleZadanieId: integer("stale_zadanie_id").notNull().references(() => staleZadania.id),
  okresId: integer("okres_id").notNull().references(() => okresy.id),
  godzinaStart: time("godzina_start"),
  createdAt: timestamp("created_at").defaultNow(),
}, (table) => ({
  staleOkresIdx: uniqueIndex("stale_okres_idx").on(table.staleZadanieId, table.okresId),
}));

// =============================================
// TYPES
// =============================================
export type Rok = typeof roki.$inferSelect;
export type NewRok = typeof roki.$inferInsert;
export type Okres = typeof okresy.$inferSelect;
export type NewOkres = typeof okresy.$inferInsert;
export type Zadanie = typeof zadania.$inferSelect;
export type NewZadanie = typeof zadania.$inferInsert;
export type GamificationStats = typeof gamificationStats.$inferSelect;
export type Streak = typeof streaks.$inferSelect;
export type Achievement = typeof achievements.$inferSelect;
