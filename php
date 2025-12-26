/**
 * Plugin Name: Zadaniomat OKR
 * Description: System zarządzania celami i zadaniami z rokami 90-dniowymi
 * Version: 4.0 AJAX
 * Author: Ty
 */

// Ustaw strefę czasową na Warszawę
date_default_timezone_set('Europe/Warsaw');

// =============================================
// KATEGORIE - DOMYŚLNE WARTOŚCI
// =============================================
define('ZADANIOMAT_DEFAULT_KATEGORIE', [
    'zapianowany' => 'Zapianowany',
    'klejpan' => 'Klejpan',
    'marka_langer' => 'Marka Langer',
    'marketing_construction' => 'Marketing Construction',
    'fjo' => 'FJO (Firma Jako Osobowość)',
    'obsluga_telefoniczna' => 'Obsługa telefoniczna'
]);

define('ZADANIOMAT_DEFAULT_KATEGORIE_ZADANIA', [
    'zapianowany' => 'Zapianowany',
    'klejpan' => 'Klejpan',
    'marka_langer' => 'Marka Langer',
    'marketing_construction' => 'Marketing Construction',
    'fjo' => 'FJO (Firma Jako Osobowość)',
    'obsluga_telefoniczna' => 'Obsługa telefoniczna',
    'sprawy_organizacyjne' => 'Sprawy Organizacyjne'
]);

// Funkcje do pobierania kategorii (z opcji lub domyślnych)
function zadaniomat_get_kategorie() {
    $saved = get_option('zadaniomat_kategorie');
    if ($saved && is_array($saved) && !empty($saved)) {
        return $saved;
    }
    return ZADANIOMAT_DEFAULT_KATEGORIE;
}

function zadaniomat_get_kategorie_zadania() {
    $saved = get_option('zadaniomat_kategorie_zadania');
    if ($saved && is_array($saved) && !empty($saved)) {
        return $saved;
    }
    return ZADANIOMAT_DEFAULT_KATEGORIE_ZADANIA;
}

// Stałe dla kompatybilności wstecznej (dynamicznie generowane)
define('ZADANIOMAT_KATEGORIE', zadaniomat_get_kategorie());
define('ZADANIOMAT_KATEGORIE_ZADANIA', zadaniomat_get_kategorie_zadania());

// =============================================
// TWORZENIE TABEL
// =============================================
function zadaniomat_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    $sql1 = "CREATE TABLE IF NOT EXISTS $table_roki (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nazwa VARCHAR(100) NOT NULL,
        data_start DATE NOT NULL,
        data_koniec DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    $table_cele_rok = $wpdb->prefix . 'zadaniomat_cele_rok';
    $sql2 = "CREATE TABLE IF NOT EXISTS $table_cele_rok (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rok_id INT NOT NULL,
        kategoria VARCHAR(50) NOT NULL,
        cel TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $sql3 = "CREATE TABLE IF NOT EXISTS $table_okresy (
        id INT AUTO_INCREMENT PRIMARY KEY,
        rok_id INT NOT NULL,
        nazwa VARCHAR(100) NOT NULL,
        data_start DATE NOT NULL,
        data_koniec DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
    $sql4 = "CREATE TABLE IF NOT EXISTS $table_cele_okres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        okres_id INT NOT NULL,
        kategoria VARCHAR(50) NOT NULL,
        cel TEXT,
        status DECIMAL(3,1) DEFAULT NULL,
        osiagniety TINYINT(1) DEFAULT NULL,
        uwagi TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";
    
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $sql5 = "CREATE TABLE IF NOT EXISTS $table_zadania (
        id INT AUTO_INCREMENT PRIMARY KEY,
        okres_id INT DEFAULT NULL,
        kategoria VARCHAR(50) NOT NULL,
        dzien DATE NOT NULL,
        zadanie VARCHAR(255) NOT NULL,
        cel_todo TEXT,
        planowany_czas INT DEFAULT 0,
        faktyczny_czas INT DEFAULT NULL,
        status VARCHAR(20) DEFAULT 'nowe',
        godzina_start TIME DEFAULT NULL,
        godzina_koniec TIME DEFAULT NULL,
        pozycja_harmonogram INT DEFAULT NULL,
        jest_cykliczne TINYINT(1) DEFAULT 0,
        recurring_template_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Dodaj kolumnę jest_cykliczne do istniejącej tabeli (jeśli nie istnieje)
    $column_check = $wpdb->get_results("SHOW COLUMNS FROM $table_zadania LIKE 'jest_cykliczne'");
    if (empty($column_check)) {
        $wpdb->query("ALTER TABLE $table_zadania ADD COLUMN jest_cykliczne TINYINT(1) DEFAULT 0");
    }

    // Dodaj kolumnę recurring_template_id do tabeli zadań (jeśli nie istnieje)
    $column_check = $wpdb->get_results("SHOW COLUMNS FROM $table_zadania LIKE 'recurring_template_id'");
    if (empty($column_check)) {
        $wpdb->query("ALTER TABLE $table_zadania ADD COLUMN recurring_template_id INT DEFAULT NULL");
    }

    // Tabela stałych zadań (cyklicznych) - teraz jako wzorce (templates)
    $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $sql6 = "CREATE TABLE IF NOT EXISTS $table_stale (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nazwa VARCHAR(255) NOT NULL,
        kategoria VARCHAR(50) NOT NULL,
        cel_todo TEXT,
        planowany_czas INT DEFAULT 0,
        typ_powtarzania VARCHAR(50) NOT NULL DEFAULT 'codziennie',
        dni_tygodnia VARCHAR(50) DEFAULT NULL,
        dzien_miesiaca INT DEFAULT NULL,
        dni_przed_koncem_roku INT DEFAULT NULL,
        dni_przed_koncem_okresu INT DEFAULT NULL,
        minuty_po_starcie INT DEFAULT NULL,
        dodaj_do_listy TINYINT(1) DEFAULT 0,
        godzina_start TIME DEFAULT NULL,
        godzina_koniec TIME DEFAULT NULL,
        aktywne TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Dodaj kolumnę cel_todo do stale_zadania (jeśli nie istnieje)
    $column_check = $wpdb->get_results("SHOW COLUMNS FROM $table_stale LIKE 'cel_todo'");
    if (empty($column_check)) {
        $wpdb->query("ALTER TABLE $table_stale ADD COLUMN cel_todo TEXT AFTER kategoria");
    }

    // Tabela nadpisań godzin stałych zadań per okres
    $table_stale_overrides = $wpdb->prefix . 'zadaniomat_stale_overrides';
    $sql_overrides = "CREATE TABLE IF NOT EXISTS $table_stale_overrides (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stale_zadanie_id INT NOT NULL,
        okres_id INT NOT NULL,
        godzina_start TIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY stale_okres (stale_zadanie_id, okres_id)
    ) $charset_collate;";

    // Tabela planowanych godzin dziennie per okres i kategoria
    $table_godziny_okres = $wpdb->prefix . 'zadaniomat_godziny_okres';
    $sql_godziny_okres = "CREATE TABLE IF NOT EXISTS $table_godziny_okres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        okres_id INT NOT NULL,
        kategoria VARCHAR(50) NOT NULL,
        planowane_godziny_dziennie DECIMAL(4,2) DEFAULT 1.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY okres_kategoria (okres_id, kategoria)
    ) $charset_collate;";

    // Tabela dni wolnych
    $table_dni_wolne = $wpdb->prefix . 'zadaniomat_dni_wolne';
    $sql7 = "CREATE TABLE IF NOT EXISTS $table_dni_wolne (
        id INT AUTO_INCREMENT PRIMARY KEY,
        dzien DATE NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // =============================================
    // GAMIFICATION TABLES
    // =============================================

    // Główne statystyki gracza
    $table_gamification_stats = $wpdb->prefix . 'zadaniomat_gamification_stats';
    $sql8 = "CREATE TABLE IF NOT EXISTS $table_gamification_stats (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 1,
        total_xp INT NOT NULL DEFAULT 0,
        current_level INT NOT NULL DEFAULT 1,
        prestige INT NOT NULL DEFAULT 0,
        freeze_days_available INT NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Śledzenie streaków
    $table_streaks = $wpdb->prefix . 'zadaniomat_streaks';
    $sql9 = "CREATE TABLE IF NOT EXISTS $table_streaks (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 1,
        streak_type VARCHAR(50) NOT NULL,
        current_count INT NOT NULL DEFAULT 0,
        best_count INT NOT NULL DEFAULT 0,
        last_date DATE DEFAULT NULL,
        frozen_today TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY user_streak (user_id, streak_type)
    ) $charset_collate;";

    // Log XP
    $table_xp_log = $wpdb->prefix . 'zadaniomat_xp_log';
    $sql10 = "CREATE TABLE IF NOT EXISTS $table_xp_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 1,
        xp_amount INT NOT NULL,
        xp_type VARCHAR(50) NOT NULL,
        multiplier DECIMAL(4,2) NOT NULL DEFAULT 1.00,
        description VARCHAR(255) DEFAULT NULL,
        condition_text VARCHAR(255) DEFAULT NULL,
        reference_id INT DEFAULT NULL,
        reference_type VARCHAR(50) DEFAULT NULL,
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) $charset_collate;";

    // Zdobyte odznaki
    $table_achievements = $wpdb->prefix . 'zadaniomat_achievements';
    $sql11 = "CREATE TABLE IF NOT EXISTS $table_achievements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 1,
        achievement_key VARCHAR(50) NOT NULL,
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        notified TINYINT(1) NOT NULL DEFAULT 0,
        UNIQUE KEY user_achievement (user_id, achievement_key)
    ) $charset_collate;";

    // Wyzwania dnia
    $table_daily_challenges = $wpdb->prefix . 'zadaniomat_daily_challenges';
    $sql12 = "CREATE TABLE IF NOT EXISTS $table_daily_challenges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 1,
        challenge_date DATE NOT NULL,
        challenge_key VARCHAR(50) NOT NULL,
        challenge_data TEXT DEFAULT NULL,
        xp_reward INT NOT NULL,
        completed TINYINT(1) NOT NULL DEFAULT 0,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY user_date_challenge (user_id, challenge_date, challenge_key)
    ) $charset_collate;";

    // Stan combo dziennego
    $table_combo_state = $wpdb->prefix . 'zadaniomat_combo_state';
    $sql13 = "CREATE TABLE IF NOT EXISTS $table_combo_state (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 1,
        combo_date DATE NOT NULL,
        current_combo INT NOT NULL DEFAULT 0,
        max_combo_today INT NOT NULL DEFAULT 0,
        last_task_time TIMESTAMP NULL DEFAULT NULL,
        UNIQUE KEY user_combo_date (user_id, combo_date)
    ) $charset_collate;";

    // Abstrakcyjne cele użytkownika
    $table_abstract_goals = $wpdb->prefix . 'zadaniomat_abstract_goals';
    $sql14 = "CREATE TABLE IF NOT EXISTS $table_abstract_goals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL DEFAULT 1,
        nazwa VARCHAR(255) NOT NULL,
        opis TEXT,
        xp_reward INT NOT NULL DEFAULT 100,
        completed TINYINT(1) NOT NULL DEFAULT 0,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        aktywne TINYINT(1) NOT NULL DEFAULT 1
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql2);
    dbDelta($sql3);
    dbDelta($sql4);
    dbDelta($sql5);
    dbDelta($sql6);
    dbDelta($sql_overrides);
    dbDelta($sql_godziny_okres);
    dbDelta($sql7);
    dbDelta($sql8);
    dbDelta($sql9);
    dbDelta($sql10);
    dbDelta($sql11);
    dbDelta($sql12);
    dbDelta($sql13);
    dbDelta($sql14);
}

add_action('admin_init', function() {
    global $wpdb;
    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_roki'") != $table_roki) {
        zadaniomat_create_tables();
    }
    
    // Migracja - dodaj nowe kolumny jeśli nie istnieją
    $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_cele_okres");
    
    if (!in_array('osiagniety', $columns)) {
        $wpdb->query("ALTER TABLE $table_cele_okres ADD COLUMN osiagniety TINYINT(1) DEFAULT NULL");
    }
    if (!in_array('uwagi', $columns)) {
        $wpdb->query("ALTER TABLE $table_cele_okres ADD COLUMN uwagi TEXT");
    }

    // Migracja - dodaj kolumny harmonogramu do zadań
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $zadania_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_zadania");

    if (!in_array('godzina_start', $zadania_columns)) {
        $wpdb->query("ALTER TABLE $table_zadania ADD COLUMN godzina_start TIME DEFAULT NULL");
    }
    if (!in_array('godzina_koniec', $zadania_columns)) {
        $wpdb->query("ALTER TABLE $table_zadania ADD COLUMN godzina_koniec TIME DEFAULT NULL");
    }
    if (!in_array('pozycja_harmonogram', $zadania_columns)) {
        $wpdb->query("ALTER TABLE $table_zadania ADD COLUMN pozycja_harmonogram INT DEFAULT NULL");
    }

    // Utwórz tabelę stałych zadań jeśli nie istnieje
    $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_stale'") != $table_stale) {
        zadaniomat_create_tables();
    }

    // Migracja - dodaj kolumny do stałych zadań
    $stale_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_stale");
    if (!in_array('dni_przed_koncem_roku', $stale_columns)) {
        $wpdb->query("ALTER TABLE $table_stale ADD COLUMN dni_przed_koncem_roku INT DEFAULT NULL");
    }
    if (!in_array('dni_przed_koncem_okresu', $stale_columns)) {
        $wpdb->query("ALTER TABLE $table_stale ADD COLUMN dni_przed_koncem_okresu INT DEFAULT NULL");
    }
    if (!in_array('minuty_po_starcie', $stale_columns)) {
        $wpdb->query("ALTER TABLE $table_stale ADD COLUMN minuty_po_starcie INT DEFAULT NULL");
    }
    if (!in_array('dodaj_do_listy', $stale_columns)) {
        $wpdb->query("ALTER TABLE $table_stale ADD COLUMN dodaj_do_listy TINYINT(1) DEFAULT 0");
    }

    // Migracja - zmień typ_powtarzania na VARCHAR jeśli jest ENUM
    $column_info = $wpdb->get_row("SHOW COLUMNS FROM $table_stale WHERE Field = 'typ_powtarzania'");
    if ($column_info && strpos($column_info->Type, 'enum') !== false) {
        $wpdb->query("ALTER TABLE $table_stale MODIFY COLUMN typ_powtarzania VARCHAR(50) NOT NULL DEFAULT 'codziennie'");
    }

    // Migracja - dodaj kolumnę planowane_godziny_dziennie do cele_rok
    $table_cele_rok = $wpdb->prefix . 'zadaniomat_cele_rok';
    $cele_rok_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_cele_rok");
    if (!in_array('planowane_godziny_dziennie', $cele_rok_columns)) {
        $wpdb->query("ALTER TABLE $table_cele_rok ADD COLUMN planowane_godziny_dziennie DECIMAL(4,2) DEFAULT 1.00");
    }

    // Migracja - zmień status z DECIMAL na VARCHAR(20) z wartościami tekstowymi
    $status_info = $wpdb->get_row("SHOW COLUMNS FROM $table_zadania WHERE Field = 'status'");
    if ($status_info && strpos($status_info->Type, 'decimal') !== false) {
        // Najpierw dodaj nową kolumnę tymczasową
        $wpdb->query("ALTER TABLE $table_zadania ADD COLUMN status_new VARCHAR(20) DEFAULT 'nowe'");

        // Przekonwertuj wartości: null/0 -> 'nowe', 1 -> 'zakonczone'
        $wpdb->query("UPDATE $table_zadania SET status_new = CASE
            WHEN status IS NULL OR status < 0.5 THEN 'nowe'
            WHEN status >= 1 THEN 'zakonczone'
            ELSE 'rozpoczete'
        END");

        // Usuń starą kolumnę i zmień nazwę nowej
        $wpdb->query("ALTER TABLE $table_zadania DROP COLUMN status");
        $wpdb->query("ALTER TABLE $table_zadania CHANGE COLUMN status_new status VARCHAR(20) DEFAULT 'nowe'");
    }

    // Migracja - dodaj kolumny dla wielu celów w okresie
    if (!in_array('completed_at', $columns)) {
        $wpdb->query("ALTER TABLE $table_cele_okres ADD COLUMN completed_at DATETIME DEFAULT NULL");
    }
    if (!in_array('pozycja', $columns)) {
        $wpdb->query("ALTER TABLE $table_cele_okres ADD COLUMN pozycja INT DEFAULT 1");
    }

    // Utwórz tabelę dni wolnych jeśli nie istnieje
    $table_dni_wolne = $wpdb->prefix . 'zadaniomat_dni_wolne';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_dni_wolne'") != $table_dni_wolne) {
        zadaniomat_create_tables();
    }

    // Migracja - utwórz tabele gamifikacji jeśli nie istnieją
    $table_gamification_stats = $wpdb->prefix . 'zadaniomat_gamification_stats';
    if($wpdb->get_var("SHOW TABLES LIKE '$table_gamification_stats'") != $table_gamification_stats) {
        zadaniomat_create_tables();
    }

    // Migracja - dodaj kolumnę condition_text do xp_log
    $table_xp_log = $wpdb->prefix . 'zadaniomat_xp_log';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_xp_log'") == $table_xp_log) {
        $xp_log_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_xp_log");
        if (!in_array('condition_text', $xp_log_columns)) {
            $wpdb->query("ALTER TABLE $table_xp_log ADD COLUMN condition_text VARCHAR(255) DEFAULT NULL AFTER description");
        }
    }

    // Migracja - dodaj kolumnę recurring_template_id do zadań
    $zadania_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_zadania");
    if (!in_array('recurring_template_id', $zadania_columns)) {
        $wpdb->query("ALTER TABLE $table_zadania ADD COLUMN recurring_template_id INT DEFAULT NULL");
    }

    // Migracja - normalizuj klucze kategorii w zadaniach i opcjach
    // Konwertuj wszystko do formatu: małe litery, podkreślniki zamiast spacji

    // 1. Pobierz wszystkie unikalne kategorie z zadań
    $kategorie_w_zadaniach = $wpdb->get_col("SELECT DISTINCT kategoria FROM $table_zadania WHERE kategoria IS NOT NULL");
    $kategorie_w_stale = $wpdb->get_col("SELECT DISTINCT kategoria FROM $table_stale WHERE kategoria IS NOT NULL");
    $wszystkie_kategorie = array_unique(array_merge($kategorie_w_zadaniach, $kategorie_w_stale));

    // 2. Dla każdej kategorii, jeśli nie jest w formacie klucza, zaktualizuj
    foreach ($wszystkie_kategorie as $kat) {
        $proper_key = sanitize_key(str_replace(' ', '_', $kat));
        if ($kat !== $proper_key && !empty($proper_key)) {
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_zadania SET kategoria = %s WHERE kategoria = %s",
                $proper_key, $kat
            ));
            $wpdb->query($wpdb->prepare(
                "UPDATE $table_stale SET kategoria = %s WHERE kategoria = %s",
                $proper_key, $kat
            ));
        }
    }

    // 3. Napraw też zapisane opcje kategorii
    $saved_kategorie = get_option('zadaniomat_kategorie_zadania');
    if ($saved_kategorie && is_array($saved_kategorie)) {
        $fixed_kategorie = [];
        foreach ($saved_kategorie as $key => $label) {
            $proper_key = sanitize_key(str_replace(' ', '_', $key));
            $fixed_kategorie[$proper_key] = $label;
        }
        if ($fixed_kategorie !== $saved_kategorie) {
            update_option('zadaniomat_kategorie_zadania', $fixed_kategorie);
        }
    }

    $saved_kategorie_cele = get_option('zadaniomat_kategorie');
    if ($saved_kategorie_cele && is_array($saved_kategorie_cele)) {
        $fixed_kategorie_cele = [];
        foreach ($saved_kategorie_cele as $key => $label) {
            $proper_key = sanitize_key(str_replace(' ', '_', $key));
            $fixed_kategorie_cele[$proper_key] = $label;
        }
        if ($fixed_kategorie_cele !== $saved_kategorie_cele) {
            update_option('zadaniomat_kategorie', $fixed_kategorie_cele);
        }
    }

    // Migracja - dodaj kolumnę cel_todo do stałych zadań
    $stale_columns = $wpdb->get_col("SHOW COLUMNS FROM $table_stale");
    if (!in_array('cel_todo', $stale_columns)) {
        $wpdb->query("ALTER TABLE $table_stale ADD COLUMN cel_todo TEXT AFTER kategoria");
    }

    // Migracja - wygeneruj zadania dla istniejących aktywnych stałych zadań (jednorazowo)
    $migration_done = get_option('zadaniomat_recurring_migration_done', false);
    if (!$migration_done) {
        $aktywne_stale = $wpdb->get_results("SELECT id FROM $table_stale WHERE aktywne = 1");
        foreach ($aktywne_stale as $stale) {
            zadaniomat_generate_recurring_tasks($stale->id);
        }
        update_option('zadaniomat_recurring_migration_done', true);
    }
});

// =============================================
// HELPER FUNCTIONS
// =============================================
function zadaniomat_get_current_okres($date = null) {
    global $wpdb;
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $date = $date ?: date('Y-m-d');
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_okresy WHERE %s BETWEEN data_start AND data_koniec LIMIT 1",
        $date
    ));
}

function zadaniomat_get_current_rok($date = null) {
    global $wpdb;
    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    $date = $date ?: date('Y-m-d');
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_roki WHERE %s BETWEEN data_start AND data_koniec LIMIT 1",
        $date
    ));
}

function zadaniomat_get_kategoria_label($key) {
    return ZADANIOMAT_KATEGORIE_ZADANIA[$key] ?? $key;
}

// =============================================
// RECURRING TASKS - GENERATE FROM TEMPLATE
// =============================================

/**
 * Generuje zadania z template'a stałego zadania na dany rok (90-dniowy okres)
 * @param int $template_id - ID stałego zadania (template)
 * @param int|null $rok_id - ID roku (jeśli null, używa aktualnego)
 * @return array - lista utworzonych zadań
 */
function zadaniomat_generate_recurring_tasks($template_id, $rok_id = null) {
    global $wpdb;

    $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $table_roki = $wpdb->prefix . 'zadaniomat_roki';

    // Pobierz template
    $template = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_stale WHERE id = %d", $template_id));
    if (!$template || !$template->aktywne) return [];

    // Pobierz rok
    if ($rok_id) {
        $rok = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_roki WHERE id = %d", $rok_id));
    } else {
        $rok = zadaniomat_get_current_rok();
    }
    if (!$rok) return [];

    // Pobierz okresy w tym roku
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $okresy = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_okresy WHERE rok_id = %d ORDER BY data_start",
        $rok->id
    ));

    // Pobierz nadpisania godzin per okres
    $table_overrides = $wpdb->prefix . 'zadaniomat_stale_overrides';
    $overrides_raw = $wpdb->get_results($wpdb->prepare(
        "SELECT okres_id, godzina_start FROM $table_overrides WHERE stale_zadanie_id = %d",
        $template_id
    ));
    $godziny_override = [];
    foreach ($overrides_raw as $o) {
        $godziny_override[$o->okres_id] = $o->godzina_start;
    }

    $created_tasks = [];
    $start_date = new DateTime($rok->data_start);
    $end_date = new DateTime($rok->data_koniec);
    $today = new DateTime();

    // Nie generuj zadań wstecz - zacznij od dziś lub startu roku (cokolwiek jest późniejsze)
    if ($start_date < $today) {
        $start_date = clone $today;
    }

    $current = clone $start_date;

    while ($current <= $end_date) {
        $match = false;
        $dayOfWeek = strtolower($current->format('D'));
        $dayOfWeekPl = ['mon' => 'pn', 'tue' => 'wt', 'wed' => 'sr', 'thu' => 'cz', 'fri' => 'pt', 'sat' => 'so', 'sun' => 'nd'];
        $dayPl = $dayOfWeekPl[$dayOfWeek];
        $dayOfMonth = intval($current->format('j'));
        $date_str = $current->format('Y-m-d');

        // Sprawdź typ powtarzania
        if ($template->typ_powtarzania === 'codziennie') {
            $match = true;
        } elseif ($template->typ_powtarzania === 'dni_tygodnia' && !empty($template->dni_tygodnia)) {
            $dni = explode(',', $template->dni_tygodnia);
            $match = in_array($dayPl, $dni);
        } elseif ($template->typ_powtarzania === 'dzien_miesiaca' && $template->dzien_miesiaca) {
            $match = ($dayOfMonth === intval($template->dzien_miesiaca));
        } elseif ($template->typ_powtarzania === 'dni_przed_koncem_roku' && $template->dni_przed_koncem_roku) {
            // Oblicz ile dni pozostało do końca roku
            $diff = $current->diff($end_date);
            if ($diff->invert === 0 && $diff->days === intval($template->dni_przed_koncem_roku)) {
                $match = true;
            }
        } elseif ($template->typ_powtarzania === 'dni_przed_koncem_okresu' && $template->dni_przed_koncem_okresu) {
            // Znajdź okres dla tego dnia
            foreach ($okresy as $okres) {
                if ($date_str >= $okres->data_start && $date_str <= $okres->data_koniec) {
                    $okres_koniec = new DateTime($okres->data_koniec);
                    // Oblicz ile dni pozostało do końca okresu
                    $diff = $current->diff($okres_koniec);
                    if ($diff->invert === 0 && $diff->days === intval($template->dni_przed_koncem_okresu)) {
                        $match = true;
                    }
                    break;
                }
            }
        }

        if ($match) {
            // Sprawdź czy zadanie już istnieje na ten dzień
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_zadania WHERE recurring_template_id = %d AND dzien = %s",
                $template_id, $date_str
            ));

            if (!$existing) {
                // Znajdź okres dla tego dnia
                $okres_id = null;
                foreach ($okresy as $okres) {
                    if ($date_str >= $okres->data_start && $date_str <= $okres->data_koniec) {
                        $okres_id = $okres->id;
                        break;
                    }
                }

                // Użyj nadpisanej godziny dla okresu lub domyślnej z template'a
                $godzina_start = $template->godzina_start;
                $godzina_koniec = $template->godzina_koniec;
                if ($okres_id && isset($godziny_override[$okres_id])) {
                    $godzina_start = $godziny_override[$okres_id];
                    // Oblicz godzinę końca na podstawie nowej godziny startu i czasu trwania
                    if ($godzina_start && $template->planowany_czas) {
                        $start_time = DateTime::createFromFormat('H:i:s', $godzina_start) ?: DateTime::createFromFormat('H:i', $godzina_start);
                        if ($start_time) {
                            $start_time->modify('+' . intval($template->planowany_czas) . ' minutes');
                            $godzina_koniec = $start_time->format('H:i:s');
                        }
                    }
                }

                // Utwórz zadanie
                $wpdb->insert($table_zadania, [
                    'okres_id' => $okres_id,
                    'kategoria' => $template->kategoria,
                    'dzien' => $date_str,
                    'zadanie' => $template->nazwa,
                    'cel_todo' => $template->cel_todo,
                    'planowany_czas' => $template->planowany_czas,
                    'status' => 'nowe',
                    'godzina_start' => $godzina_start,
                    'godzina_koniec' => $godzina_koniec,
                    'jest_cykliczne' => 1,
                    'recurring_template_id' => $template_id
                ]);

                $created_tasks[] = $wpdb->insert_id;
            }
        }

        $current->modify('+1 day');
    }

    return $created_tasks;
}

/**
 * Usuwa przyszłe zadania wygenerowane z template'a
 * @param int $template_id - ID stałego zadania (template)
 * @param bool $delete_today - czy usunąć też zadania z dzisiaj
 * @return int - liczba usuniętych zadań
 */
function zadaniomat_delete_future_recurring_tasks($template_id, $delete_today = false) {
    global $wpdb;
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $today = date('Y-m-d');

    $operator = $delete_today ? '>=' : '>';

    // Usuń tylko niezrealizowane przyszłe zadania
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_zadania WHERE recurring_template_id = %d AND dzien $operator %s AND status IN ('nowe', 'w_trakcie')",
        $template_id, $today
    ));

    return $deleted;
}

/**
 * Regeneruje zadania dla template'a (usuwa przyszłe i tworzy nowe)
 * @param int $template_id - ID stałego zadania (template)
 * @return array - ['deleted' => int, 'created' => array]
 */
function zadaniomat_regenerate_recurring_tasks($template_id) {
    $deleted = zadaniomat_delete_future_recurring_tasks($template_id);
    $created = zadaniomat_generate_recurring_tasks($template_id);
    return ['deleted' => $deleted, 'created' => $created];
}

// =============================================
// GAMIFICATION SYSTEM - CONFIGURABLE
// =============================================

// Domyślne ustawienia gamifikacji
function zadaniomat_get_default_gam_config() {
    return [
        // Poziomy
        'levels' => [
            1 => ['xp' => 0, 'name' => 'Świeżak', 'icon' => '🌱'],
            2 => ['xp' => 150, 'name' => 'Planista', 'icon' => '📝'],
            3 => ['xp' => 400, 'name' => 'Celownik', 'icon' => '🎯'],
            4 => ['xp' => 750, 'name' => 'Wykonawca', 'icon' => '⚡'],
            5 => ['xp' => 1200, 'name' => 'Rzemieślnik', 'icon' => '🔧'],
            6 => ['xp' => 1800, 'name' => 'Profesjonalista', 'icon' => '💼'],
            7 => ['xp' => 2600, 'name' => 'Ekspert', 'icon' => '🏆'],
            8 => ['xp' => 3600, 'name' => 'Mistrz', 'icon' => '🎖️'],
            9 => ['xp' => 5000, 'name' => 'Guru', 'icon' => '👑'],
            10 => ['xp' => 7000, 'name' => 'Legenda', 'icon' => '🌟'],
        ],
        // XP za akcje
        'xp_values' => [
            'task_complete' => 10,
            'cyclic_task_complete' => 15,
            'goal_category_bonus' => 2,
            'goal_achieved_base' => 100,
            'goal_chain_increment' => 20,
            'all_goals_period' => 300,
            'all_tasks_day' => 30,
            'early_start_bonus' => 20,
            'early_planning_bonus' => 15,
            'category_hour' => 5,
            'coverage_3_categories' => 15,
            'coverage_4_categories' => 30,
            'coverage_5_categories' => 50,
            'coverage_6_categories' => 80,
        ],
        // Mnożniki streaka
        'streak_multipliers' => [
            ['min_days' => 0, 'multiplier' => 1.0],
            ['min_days' => 3, 'multiplier' => 1.2],
            ['min_days' => 7, 'multiplier' => 1.4],
            ['min_days' => 14, 'multiplier' => 1.6],
            ['min_days' => 30, 'multiplier' => 1.8],
            ['min_days' => 60, 'multiplier' => 2.0],
        ],
        // Mnożniki combo
        'combo_multipliers' => [
            ['min_combo' => 0, 'multiplier' => 1.0],
            ['min_combo' => 2, 'multiplier' => 1.1],
            ['min_combo' => 3, 'multiplier' => 1.2],
            ['min_combo' => 4, 'multiplier' => 1.3],
            ['min_combo' => 5, 'multiplier' => 1.5],
        ],
        // Warunki streaka work_days
        'streak_conditions' => [
            'min_tasks' => 3,
            'min_hours' => 4,
            'or_all_tasks_done' => true,
        ],
        // Ustawienia czasowe
        'time_settings' => [
            'early_start_before' => '08:00',
            'early_planning_before' => '10:00',
            'combo_timeout_hours' => 2,
            'morning_work_hours' => 3,
            'morning_work_deadline' => '12:00',
        ],
        // Streak milestones
        'streak_milestones' => [
            'work_days' => [3 => 25, 7 => 75, 14 => 150, 30 => 300, 60 => 600, 90 => 1000],
            'early_start' => [7 => 50, 14 => 100, 30 => 250],
            'early_planning' => [7 => 40, 14 => 80],
            'full_coverage' => [7 => 100, 14 => 200],
        ],
        // Wymagaj potwierdzenia XP
        'require_xp_confirmation' => false,
    ];
}

// Pobierz konfigurację gamifikacji
function zadaniomat_get_gam_config($key = null) {
    $saved = get_option('zadaniomat_gam_config', []);
    $defaults = zadaniomat_get_default_gam_config();
    $config = array_replace_recursive($defaults, $saved);

    if ($key !== null) {
        return $config[$key] ?? null;
    }
    return $config;
}

// Zapisz konfigurację gamifikacji
function zadaniomat_save_gam_config($config) {
    update_option('zadaniomat_gam_config', $config);
}

// Pobierz odznaki (z możliwością edycji)
function zadaniomat_get_achievements() {
    $saved = get_option('zadaniomat_achievements_config');
    if ($saved) return $saved;

    return [
        // Streak - Główny
        'streak_3' => ['name' => 'Zapałka', 'icon' => '🔥', 'desc' => '3 dni streak', 'xp' => 25, 'condition' => 'Utrzymaj streak przez 3 dni robocze z rzędu'],
        'streak_7' => ['name' => 'Ognisko', 'icon' => '🔥🔥', 'desc' => '7 dni streak', 'xp' => 75, 'condition' => 'Utrzymaj streak przez 7 dni roboczych z rzędu'],
        'streak_14' => ['name' => 'Pożar', 'icon' => '🔥🔥🔥', 'desc' => '14 dni streak', 'xp' => 150, 'condition' => 'Utrzymaj streak przez 14 dni roboczych z rzędu'],
        'streak_30' => ['name' => 'Wulkan', 'icon' => '🌋', 'desc' => '30 dni streak', 'xp' => 300, 'condition' => 'Utrzymaj streak przez 30 dni roboczych z rzędu'],
        'streak_60' => ['name' => 'Słońce', 'icon' => '☀️', 'desc' => '60 dni streak', 'xp' => 600, 'condition' => 'Utrzymaj streak przez 60 dni roboczych z rzędu'],
        'streak_90' => ['name' => 'Supernowa', 'icon' => '🌟', 'desc' => '90 dni streak', 'xp' => 1000, 'condition' => 'Utrzymaj streak przez 90 dni roboczych z rzędu'],
        // Streak - Wczesny start
        'early_start_7' => ['name' => 'Ranny Ptaszek', 'icon' => '🐦', 'desc' => '7 dni startu przed 8:00', 'xp' => 50, 'condition' => 'Rozpocznij pracę przed 8:00 przez 7 dni z rzędu'],
        'early_start_14' => ['name' => 'Świt', 'icon' => '🌅', 'desc' => '14 dni startu przed 8:00', 'xp' => 100, 'condition' => 'Rozpocznij pracę przed 8:00 przez 14 dni z rzędu'],
        'early_start_30' => ['name' => 'Mistrz Poranka', 'icon' => '🌄', 'desc' => '30 dni startu przed 8:00', 'xp' => 250, 'condition' => 'Rozpocznij pracę przed 8:00 przez 30 dni z rzędu'],
        // Streak - Wczesne planowanie
        'early_plan_7' => ['name' => 'Planista', 'icon' => '📋', 'desc' => '7 dni planowania przed 10:00', 'xp' => 40, 'condition' => 'Oznacz listę poranną przed 10:00 przez 7 dni z rzędu'],
        'early_plan_14' => ['name' => 'Strateg', 'icon' => '🗺️', 'desc' => '14 dni planowania przed 10:00', 'xp' => 80, 'condition' => 'Oznacz listę poranną przed 10:00 przez 14 dni z rzędu'],
        'early_plan_30' => ['name' => 'Architekt Dnia', 'icon' => '🏛️', 'desc' => '30 dni planowania przed 10:00', 'xp' => 200, 'condition' => 'Oznacz listę poranną przed 10:00 przez 30 dni z rzędu'],
        // Pokrycie kategorii
        'full_coverage_1' => ['name' => 'Wszechstronny', 'icon' => '🎨', 'desc' => 'Pierwszy dzień z 6 kategoriami', 'xp' => 50, 'condition' => 'Ukończ min. 1 zadanie w każdej z 6 kategorii celów w jednym dniu'],
        'full_coverage_7' => ['name' => 'Żongler', 'icon' => '🎪', 'desc' => '7 dni pełnego pokrycia', 'xp' => 150, 'condition' => 'Pełne pokrycie kategorii przez 7 dni z rzędu'],
        'full_coverage_14' => ['name' => 'Mistrz Równowagi', 'icon' => '⚖️', 'desc' => '14 dni pełnego pokrycia', 'xp' => 300, 'condition' => 'Pełne pokrycie kategorii przez 14 dni z rzędu'],
        // Cele
        'first_goal' => ['name' => 'Snajper', 'icon' => '🎯', 'desc' => 'Pierwszy osiągnięty cel', 'xp' => 25, 'condition' => 'Osiągnij swój pierwszy cel okresu'],
        'goals_10' => ['name' => 'Łucznik', 'icon' => '🏹', 'desc' => '10 osiągniętych celów', 'xp' => 100, 'condition' => 'Osiągnij łącznie 10 celów okresowych'],
        'goals_25' => ['name' => 'Strzelec Wyborowy', 'icon' => '🎖️', 'desc' => '25 osiągniętych celów', 'xp' => 250, 'condition' => 'Osiągnij łącznie 25 celów okresowych'],
        'goals_50' => ['name' => 'Komandos', 'icon' => '💪', 'desc' => '50 osiągniętych celów', 'xp' => 500, 'condition' => 'Osiągnij łącznie 50 celów okresowych'],
        'goal_x2' => ['name' => 'Podwójne Uderzenie', 'icon' => '🎯🎯', 'desc' => 'Dwa cele w jednej kategorii', 'xp' => 50, 'condition' => 'Osiągnij 2 cele w tej samej kategorii w jednym okresie'],
        'goal_x3' => ['name' => 'Hat-trick', 'icon' => '🎩', 'desc' => 'Trzy cele w jednej kategorii', 'xp' => 100, 'condition' => 'Osiągnij 3 cele w tej samej kategorii w jednym okresie'],
        'perfect_period' => ['name' => 'Perfekcyjny Okres', 'icon' => '🏆', 'desc' => 'Wszystkie cele okresu osiągnięte', 'xp' => 200, 'condition' => 'Osiągnij wszystkie zaplanowane cele w okresie'],
        'perfect_year' => ['name' => 'Perfekcyjny Rok', 'icon' => '👑', 'desc' => 'Wszystkie cele roku osiągnięte', 'xp' => 500, 'condition' => 'Osiągnij wszystkie cele roczne'],
        // Zadania
        'tasks_100' => ['name' => 'Pracuś', 'icon' => '📝', 'desc' => '100 ukończonych zadań', 'xp' => 100, 'condition' => 'Ukończ łącznie 100 zadań'],
        'tasks_500' => ['name' => 'Pracoholik', 'icon' => '💼', 'desc' => '500 ukończonych zadań', 'xp' => 300, 'condition' => 'Ukończ łącznie 500 zadań'],
        'tasks_1000' => ['name' => 'Maszyna', 'icon' => '⚙️', 'desc' => '1000 ukończonych zadań', 'xp' => 600, 'condition' => 'Ukończ łącznie 1000 zadań'],
        'cyclic_50' => ['name' => 'Nawyk', 'icon' => '🔄', 'desc' => '50 zadań cyklicznych', 'xp' => 75, 'condition' => 'Ukończ łącznie 50 zadań cyklicznych'],
        'cyclic_200' => ['name' => 'Rutyna', 'icon' => '🔁', 'desc' => '200 zadań cyklicznych', 'xp' => 200, 'condition' => 'Ukończ łącznie 200 zadań cyklicznych'],
        // Czas pracy
        'hours_100' => ['name' => 'Stażysta', 'icon' => '⏰', 'desc' => '100h przepracowanych', 'xp' => 100, 'condition' => 'Przepracuj łącznie 100 godzin'],
        'hours_500' => ['name' => 'Weteran', 'icon' => '🎖️', 'desc' => '500h przepracowanych', 'xp' => 300, 'condition' => 'Przepracuj łącznie 500 godzin'],
        'hours_1000' => ['name' => 'Legenda', 'icon' => '🏛️', 'desc' => '1000h przepracowanych', 'xp' => 600, 'condition' => 'Przepracuj łącznie 1000 godzin'],
        // Combo
        'combo_5' => ['name' => 'Kombinator', 'icon' => '⚡', 'desc' => 'Osiągnij combo x5', 'xp' => 30, 'condition' => 'Ukończ 5 zadań bez przerwy dłuższej niż 2h'],
        'combo_5_10times' => ['name' => 'Mistrz Combo', 'icon' => '💥', 'desc' => 'Combo x5 dziesięć razy', 'xp' => 100, 'condition' => 'Osiągnij combo x5 w 10 różnych dniach'],
        // Specjalne
        'first_day' => ['name' => 'Początek Drogi', 'icon' => '🚀', 'desc' => 'Ukończ pierwszy dzień', 'xp' => 10, 'condition' => 'Ukończ swoje pierwsze zadanie'],
        'level_5' => ['name' => 'Awans', 'icon' => '⬆️', 'desc' => 'Osiągnij level 5', 'xp' => 50, 'condition' => 'Zdobądź wystarczająco XP by osiągnąć level 5'],
        'level_10' => ['name' => 'Szczyt', 'icon' => '🗻', 'desc' => 'Osiągnij level 10', 'xp' => 200, 'condition' => 'Zdobądź wystarczająco XP by osiągnąć level 10'],
        'prestige_1' => ['name' => 'Odrodzenie', 'icon' => '🔮', 'desc' => 'Pierwszy prestige', 'xp' => 100, 'condition' => 'Zresetuj postęp na level 10 by zdobyć prestige'],
    ];
}

// Pobierz wyzwania dnia (z możliwością edycji)
function zadaniomat_get_daily_challenges_config() {
    $saved = get_option('zadaniomat_challenges_config');
    if ($saved) return $saved;

    $config = zadaniomat_get_gam_config();
    $time = $config['time_settings'];

    return [
        'complete_5_tasks' => [
            'desc' => 'Ukończ 5 zadań',
            'xp' => 25,
            'difficulty' => 'easy',
            'condition' => 'Ukończ minimum 5 zadań dzisiaj (zmień status na "zakończone")',
            'param' => 5
        ],
        'complete_8_tasks' => [
            'desc' => 'Ukończ 8 zadań',
            'xp' => 40,
            'difficulty' => 'medium',
            'condition' => 'Ukończ minimum 8 zadań dzisiaj',
            'param' => 8
        ],
        'work_6_hours' => [
            'desc' => 'Przepracuj 6 godzin',
            'xp' => 35,
            'difficulty' => 'medium',
            'condition' => 'Suma faktycznego czasu zadań musi wynosić min. 6 godzin',
            'param' => 360,
            'group' => 'work_hours'
        ],
        'work_8_hours' => [
            'desc' => 'Przepracuj 8 godzin',
            'xp' => 50,
            'difficulty' => 'hard',
            'condition' => 'Suma faktycznego czasu zadań musi wynosić min. 8 godzin',
            'param' => 480,
            'group' => 'work_hours'
        ],
        '4_categories' => [
            'desc' => 'Zadania w 4 kategoriach',
            'xp' => 30,
            'difficulty' => 'easy',
            'condition' => 'Ukończ min. 1 zadanie w 4 różnych kategoriach',
            'param' => 4,
            'group' => 'categories'
        ],
        'all_categories' => [
            'desc' => 'Zadania we wszystkich kategoriach',
            'xp' => 60,
            'difficulty' => 'hard',
            'condition' => 'Ukończ min. 1 zadanie w każdej z 6 kategorii celów',
            'param' => 6,
            'group' => 'categories'
        ],
        'all_cyclic' => [
            'desc' => 'Wykonaj wszystkie zadania cykliczne',
            'xp' => 35,
            'difficulty' => 'medium',
            'condition' => 'Ukończ wszystkie stałe zadania zaplanowane na dziś'
        ],
        'start_before_8' => [
            'desc' => 'Zacznij przed 8:00',
            'xp' => 25,
            'difficulty' => 'medium',
            'condition' => 'Ustaw godzinę startu dnia przed godziną 8:00',
            'param' => '08:00',
            'group' => 'early_start'
        ],
        'start_before_9' => [
            'desc' => 'Zacznij przed 9:00',
            'xp' => 15,
            'difficulty' => 'easy',
            'condition' => 'Ustaw godzinę startu dnia przed godziną 9:00',
            'param' => '09:00',
            'group' => 'early_start'
        ],
        'morning_plan' => [
            'desc' => 'Zaplanuj dzień rano',
            'xp' => 20,
            'difficulty' => 'easy',
            'condition' => 'Zaznacz checkbox "Lista poranna gotowa" przed godziną ' . $time['early_planning_before'],
            'param' => $time['early_planning_before']
        ],
        'morning_work' => [
            'desc' => $time['morning_work_hours'] . 'h pracy do ' . $time['morning_work_deadline'],
            'xp' => 30,
            'difficulty' => 'hard',
            'condition' => 'Przepracuj min. ' . $time['morning_work_hours'] . ' godziny przed godziną ' . $time['morning_work_deadline'],
            'param_hours' => $time['morning_work_hours'],
            'param_deadline' => $time['morning_work_deadline']
        ],
        'combo_3' => [
            'desc' => 'Osiągnij combo x3',
            'xp' => 20,
            'difficulty' => 'easy',
            'condition' => 'Ukończ 3 zadania z rzędu bez przerwy dłuższej niż 2h',
            'param' => 3,
            'group' => 'combo'
        ],
        'combo_5' => [
            'desc' => 'Osiągnij combo x5',
            'xp' => 35,
            'difficulty' => 'medium',
            'condition' => 'Ukończ 5 zadań z rzędu bez przerwy dłuższej niż 2h',
            'param' => 5,
            'group' => 'combo'
        ],
        'finish_all' => [
            'desc' => 'Ukończ wszystkie zaplanowane',
            'xp' => 40,
            'difficulty' => 'medium',
            'condition' => 'Wszystkie zadania zaplanowane na dziś muszą mieć status "zakończone"'
        ],
        'category_focus' => [
            'desc' => '3h w jednej kategorii',
            'xp' => 25,
            'difficulty' => 'medium',
            'condition' => 'Przepracuj min. 3 godziny w zadaniach jednej kategorii',
            'param' => 180
        ],
    ];
}

// Kompatybilność wsteczna - stałe (używane w niektórych miejscach)
function zadaniomat_get_level_thresholds() {
    $levels = zadaniomat_get_gam_config('levels');
    $thresholds = [];
    foreach ($levels as $level => $data) {
        $thresholds[$level] = $data['xp'];
    }
    return $thresholds;
}

function zadaniomat_get_level_names() {
    $levels = zadaniomat_get_gam_config('levels');
    $names = [];
    foreach ($levels as $level => $data) {
        $names[$level] = $data['name'];
    }
    return $names;
}

function zadaniomat_get_level_icons() {
    $levels = zadaniomat_get_gam_config('levels');
    $icons = [];
    foreach ($levels as $level => $data) {
        $icons[$level] = $data['icon'];
    }
    return $icons;
}

// Aliasy dla kompatybilności
define('ZADANIOMAT_LEVEL_THRESHOLDS', zadaniomat_get_level_thresholds());
define('ZADANIOMAT_LEVEL_NAMES', zadaniomat_get_level_names());
define('ZADANIOMAT_LEVEL_ICONS', zadaniomat_get_level_icons());
define('ZADANIOMAT_ACHIEVEMENTS', zadaniomat_get_achievements());
define('ZADANIOMAT_DAILY_CHALLENGES', zadaniomat_get_daily_challenges_config());
define('ZADANIOMAT_STREAK_MILESTONES', zadaniomat_get_gam_config('streak_milestones'));

// =============================================
// GAMIFICATION HELPER FUNCTIONS
// =============================================

// Pobierz lub utwórz statystyki gracza
function zadaniomat_get_gamification_stats($user_id = 1) {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_gamification_stats';

    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d", $user_id
    ));

    if (!$stats) {
        $wpdb->insert($table, ['user_id' => $user_id]);
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d", $user_id
        ));
    }

    return $stats;
}

// Oblicz poziom na podstawie XP
function zadaniomat_calculate_level($xp) {
    $thresholds = ZADANIOMAT_LEVEL_THRESHOLDS;
    for ($i = 10; $i >= 1; $i--) {
        if ($xp >= $thresholds[$i]) {
            return $i;
        }
    }
    return 1;
}

// Pobierz informacje o poziomie
function zadaniomat_get_level_info($level) {
    return [
        'level' => $level,
        'name' => ZADANIOMAT_LEVEL_NAMES[$level] ?? 'Unknown',
        'icon' => ZADANIOMAT_LEVEL_ICONS[$level] ?? '❓',
        'xp_required' => ZADANIOMAT_LEVEL_THRESHOLDS[$level] ?? 0,
        'xp_next' => isset(ZADANIOMAT_LEVEL_THRESHOLDS[$level + 1]) ? ZADANIOMAT_LEVEL_THRESHOLDS[$level + 1] : null,
    ];
}

// Pobierz mnożnik od streaka
function zadaniomat_get_streak_multiplier($streak_count) {
    if ($streak_count < 3) return 1.0;
    if ($streak_count < 7) return 1.2;
    if ($streak_count < 14) return 1.4;
    if ($streak_count < 30) return 1.6;
    if ($streak_count < 60) return 1.8;
    return 2.0;
}

// Pobierz mnożnik od combo
function zadaniomat_get_combo_multiplier($combo) {
    if ($combo < 2) return 1.0;
    if ($combo == 2) return 1.1;
    if ($combo == 3) return 1.2;
    if ($combo == 4) return 1.3;
    return 1.5;
}

// Pobierz streak użytkownika
function zadaniomat_get_streak($user_id, $streak_type) {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_streaks';

    $streak = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND streak_type = %s",
        $user_id, $streak_type
    ));

    if (!$streak) {
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'streak_type' => $streak_type,
            'current_count' => 0,
            'best_count' => 0
        ]);
        $streak = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND streak_type = %s",
            $user_id, $streak_type
        ));
    }

    return $streak;
}

// Pobierz lub utwórz stan combo dla dnia
function zadaniomat_get_combo_state($user_id, $date) {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_combo_state';

    $combo = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND combo_date = %s",
        $user_id, $date
    ));

    if (!$combo) {
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'combo_date' => $date,
            'current_combo' => 0,
            'max_combo_today' => 0
        ]);
        $combo = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d AND combo_date = %s",
            $user_id, $date
        ));
    }

    return $combo;
}

// Dodaj XP graczowi
function zadaniomat_add_xp($user_id, $xp_amount, $xp_type, $description = '', $reference_id = null, $reference_type = null, $multiplier = 1.0, $condition_text = '') {
    global $wpdb;

    if ($xp_amount <= 0) return ['xp_added' => 0, 'level_up' => false];

    $stats_table = $wpdb->prefix . 'zadaniomat_gamification_stats';
    $log_table = $wpdb->prefix . 'zadaniomat_xp_log';

    // Pobierz aktualne statystyki
    $stats = zadaniomat_get_gamification_stats($user_id);
    $old_level = $stats->current_level;
    $old_xp = $stats->total_xp;

    // Zastosuj bonus prestige
    if ($stats->prestige > 0) {
        $prestige_bonus = 1 + ($stats->prestige * 0.05);
        $xp_amount = round($xp_amount * $prestige_bonus);
    }

    // Finalna kwota XP z mnożnikiem
    $final_xp = round($xp_amount * $multiplier);

    // Dodaj XP
    $new_xp = $old_xp + $final_xp;
    $new_level = zadaniomat_calculate_level($new_xp);

    // Aktualizuj statystyki
    $wpdb->update($stats_table, [
        'total_xp' => $new_xp,
        'current_level' => $new_level
    ], ['user_id' => $user_id]);

    // Zapisz log - najpierw sprawdź czy kolumna condition_text istnieje
    $log_data = [
        'user_id' => $user_id,
        'xp_amount' => $final_xp,
        'xp_type' => $xp_type,
        'multiplier' => $multiplier,
        'description' => $description,
        'reference_id' => $reference_id,
        'reference_type' => $reference_type
    ];

    // Dodaj condition_text tylko jeśli kolumna istnieje
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $log_table");
    if (in_array('condition_text', $columns)) {
        $log_data['condition_text'] = $condition_text;
    }

    $wpdb->insert($log_table, $log_data);

    // Sprawdź level up
    $level_up = $new_level > $old_level;

    // Sprawdź odznaki poziomów
    if ($new_level >= 5 && $old_level < 5) {
        zadaniomat_award_achievement($user_id, 'level_5');
    }
    if ($new_level >= 10 && $old_level < 10) {
        zadaniomat_award_achievement($user_id, 'level_10');
    }

    return [
        'xp_added' => $final_xp,
        'total_xp' => $new_xp,
        'old_level' => $old_level,
        'new_level' => $new_level,
        'level_up' => $level_up,
        'level_info' => zadaniomat_get_level_info($new_level)
    ];
}

// Aktualizuj combo przy ukończeniu zadania
function zadaniomat_update_combo($user_id, $task_time = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_combo_state';
    $today = date('Y-m-d');
    $now = $task_time ?: current_time('mysql');

    $combo = zadaniomat_get_combo_state($user_id, $today);
    $new_combo = 1;

    // Sprawdź czy przerwa < 2 godziny
    if ($combo->last_task_time) {
        $last_time = strtotime($combo->last_task_time);
        $current_time = strtotime($now);
        $diff_hours = ($current_time - $last_time) / 3600;

        if ($diff_hours < 2) {
            $new_combo = $combo->current_combo + 1;
        }
    }

    $max_combo = max($combo->max_combo_today, $new_combo);

    $wpdb->update($table, [
        'current_combo' => $new_combo,
        'max_combo_today' => $max_combo,
        'last_task_time' => $now
    ], ['id' => $combo->id]);

    // Sprawdź odznaki combo
    if ($new_combo >= 5) {
        zadaniomat_award_achievement($user_id, 'combo_5');
    }

    return [
        'combo' => $new_combo,
        'max_combo' => $max_combo,
        'multiplier' => zadaniomat_get_combo_multiplier($new_combo)
    ];
}

// Przyznaj odznakę
function zadaniomat_award_achievement($user_id, $achievement_key) {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_achievements';

    // Sprawdź czy już nie ma
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND achievement_key = %s",
        $user_id, $achievement_key
    ));

    if ($existing) return null;

    // Dodaj odznakę
    $wpdb->insert($table, [
        'user_id' => $user_id,
        'achievement_key' => $achievement_key,
        'notified' => 0
    ]);

    // Pobierz dane odznaki
    $achievement = ZADANIOMAT_ACHIEVEMENTS[$achievement_key] ?? null;
    if ($achievement && $achievement['xp'] > 0) {
        zadaniomat_add_xp($user_id, $achievement['xp'], 'achievement', "Odznaka: " . $achievement['name']);
    }

    return $achievement;
}

// Sprawdź czy dzień jest roboczy (helper dla streaków)
function zadaniomat_is_work_day($date) {
    global $wpdb;
    $table_dni_wolne = $wpdb->prefix . 'zadaniomat_dni_wolne';

    $day_of_week = date('w', strtotime($date));
    $is_weekend = ($day_of_week == 0 || $day_of_week == 6);

    $is_marked = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_dni_wolne WHERE dzien = %s", $date
    )) > 0;

    if ($is_weekend) {
        return $is_marked; // Weekend roboczy jeśli oznaczony
    } else {
        return !$is_marked; // Dzień roboczy jeśli NIE oznaczony jako wolny
    }
}

// Aktualizuj streak
function zadaniomat_update_streak($user_id, $streak_type, $date = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_streaks';
    $date = $date ?: date('Y-m-d');

    $streak = zadaniomat_get_streak($user_id, $streak_type);
    $last_date = $streak->last_date;

    // Jeśli już dziś zaliczone - nic nie rób
    if ($last_date === $date) {
        return ['updated' => false, 'streak' => $streak];
    }

    $new_count = 1;
    $milestone_reached = null;

    if ($last_date) {
        $last_timestamp = strtotime($last_date);
        $today_timestamp = strtotime($date);
        $days_diff = ($today_timestamp - $last_timestamp) / 86400;

        if ($days_diff == 1) {
            // Kontynuacja streaka
            $new_count = $streak->current_count + 1;
        } else if ($days_diff > 1) {
            // Sprawdź dni robocze w przerwie
            $work_days_between = 0;
            $check_date = date('Y-m-d', $last_timestamp + 86400);
            while (strtotime($check_date) < $today_timestamp) {
                if (zadaniomat_is_work_day($check_date)) {
                    $work_days_between++;
                }
                $check_date = date('Y-m-d', strtotime($check_date) + 86400);
            }

            if ($work_days_between == 0) {
                // Same dni wolne - kontynuuj
                $new_count = $streak->current_count + 1;
            } else {
                // Reset streaka
                $new_count = 1;
            }
        }
    }

    // Aktualizuj best
    $new_best = max($streak->best_count, $new_count);

    $wpdb->update($table, [
        'current_count' => $new_count,
        'best_count' => $new_best,
        'last_date' => $date,
        'frozen_today' => 0
    ], ['id' => $streak->id]);

    // Sprawdź milestones
    $milestones = ZADANIOMAT_STREAK_MILESTONES[$streak_type] ?? [];
    foreach ($milestones as $milestone => $xp) {
        if ($new_count >= $milestone && $streak->current_count < $milestone) {
            zadaniomat_add_xp($user_id, $xp, 'streak_milestone', "Streak $streak_type: $milestone dni");
            $milestone_reached = $milestone;
        }
    }

    // Sprawdź odznaki streaka
    zadaniomat_check_streak_achievements($user_id, $streak_type, $new_count);

    return [
        'updated' => true,
        'new_count' => $new_count,
        'best_count' => $new_best,
        'milestone_reached' => $milestone_reached,
        'multiplier' => $streak_type === 'work_days' ? zadaniomat_get_streak_multiplier($new_count) : 1.0
    ];
}

// Sprawdź odznaki streaka
function zadaniomat_check_streak_achievements($user_id, $streak_type, $streak_count) {
    $achievements_map = [
        'work_days' => [3 => 'streak_3', 7 => 'streak_7', 14 => 'streak_14', 30 => 'streak_30', 60 => 'streak_60', 90 => 'streak_90'],
        'early_start' => [7 => 'early_start_7', 14 => 'early_start_14', 30 => 'early_start_30'],
        'early_planning' => [7 => 'early_plan_7', 14 => 'early_plan_14', 30 => 'early_plan_30'],
        'full_coverage' => [1 => 'full_coverage_1', 7 => 'full_coverage_7', 14 => 'full_coverage_14'],
    ];

    if (!isset($achievements_map[$streak_type])) return;

    foreach ($achievements_map[$streak_type] as $threshold => $achievement_key) {
        if ($streak_count >= $threshold) {
            zadaniomat_award_achievement($user_id, $achievement_key);
        }
    }
}

// Sprawdź czy poprzedni dzień kwalifikuje się do streaka (min. 1 zadanie w każdej kategorii LUB 7h pracy)
function zadaniomat_previous_day_qualifies_for_streak($user_id) {
    global $wpdb;
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    // Sprawdź czy wczoraj był dzień roboczy
    if (!zadaniomat_is_work_day($yesterday)) {
        return true; // Dni wolne nie wymagają pracy
    }

    // Kryterium 1: 7h pracy (420 minut)
    $total_minutes = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(faktyczny_czas) FROM $table_zadania WHERE dzien = %s AND status = 'zakonczone'",
        $yesterday
    ));
    if (($total_minutes ?: 0) >= 420) {
        return true;
    }

    // Kryterium 2: Minimum 1 zadanie w każdej kategorii celów
    $goal_categories = ['zdrowie', 'relacje', 'praca', 'finanse', 'rozwoj', 'radosc'];
    $covered_categories = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT kategoria FROM $table_zadania WHERE dzien = %s AND status = 'zakonczone'",
        $yesterday
    ));

    $all_covered = true;
    foreach ($goal_categories as $cat) {
        if (!in_array($cat, $covered_categories)) {
            $all_covered = false;
            break;
        }
    }

    return $all_covered;
}

// Sprawdź czy zadanie jest cykliczne (po ID lub nazwie)
function zadaniomat_is_cyclic_task($task_name, $task_id = null) {
    global $wpdb;

    // Najpierw sprawdź flagę jest_cykliczne w tabeli zadań
    if ($task_id) {
        $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
        $jest_cykliczne = $wpdb->get_var($wpdb->prepare(
            "SELECT jest_cykliczne FROM $table_zadania WHERE id = %d",
            $task_id
        ));
        if ($jest_cykliczne) return true;
    }

    // Następnie sprawdź po nazwie w tabeli stałych zadań
    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    return $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE nazwa = %s AND aktywne = 1",
        $task_name
    )) > 0;
}

// Sprawdź czy kategoria jest kategorią celów
function zadaniomat_is_goal_category($kategoria) {
    $goal_categories = array_keys(ZADANIOMAT_KATEGORIE);
    return in_array($kategoria, $goal_categories);
}

// Główna funkcja przetwarzająca ukończenie zadania
function zadaniomat_process_task_completion($task_id, $user_id = 1) {
    global $wpdb;
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';

    // Pobierz dane zadania
    $task = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_zadania WHERE id = %d", $task_id
    ));

    if (!$task || $task->status !== 'zakonczone') {
        return null;
    }

    $result = [
        'task_id' => $task_id,
        'xp_earned' => 0,
        'xp_breakdown' => [],
        'combo' => null,
        'level_up' => false,
        'achievements' => []
    ];

    // Pobierz streak multiplier - tylko jeśli wczoraj było spełnione kryterium
    $work_streak = zadaniomat_get_streak($user_id, 'work_days');
    $streak_multiplier = 1.0;
    if ($work_streak->current_count > 0 && zadaniomat_previous_day_qualifies_for_streak($user_id)) {
        $streak_multiplier = zadaniomat_get_streak_multiplier($work_streak->current_count);
    }

    // Aktualizuj combo
    $combo_result = zadaniomat_update_combo($user_id);
    $combo_multiplier = $combo_result['multiplier'];
    $result['combo'] = $combo_result;

    // Łączny mnożnik
    $total_multiplier = $streak_multiplier * $combo_multiplier;

    // Oblicz bazowe XP
    $is_cyclic = zadaniomat_is_cyclic_task($task->zadanie, $task_id);
    $is_goal_category = zadaniomat_is_goal_category($task->kategoria);

    $base_xp = $is_cyclic ? 15 : 10;
    if ($is_goal_category) {
        $base_xp += 2;
    }

    // Dodaj XP
    $xp_result = zadaniomat_add_xp(
        $user_id,
        $base_xp,
        $is_cyclic ? 'cyclic_task_complete' : 'task_complete',
        "Ukończono: " . $task->zadanie,
        $task_id,
        'task',
        $total_multiplier
    );

    $result['xp_earned'] = $xp_result['xp_added'];
    $result['xp_breakdown'] = [
        'base' => $base_xp,
        'streak_multiplier' => $streak_multiplier,
        'combo_multiplier' => $combo_multiplier,
        'total_multiplier' => $total_multiplier
    ];
    $result['level_up'] = $xp_result['level_up'];
    $result['level_info'] = $xp_result['level_info'];
    $result['total_xp'] = $xp_result['total_xp'];

    // Sprawdź odznaki zadań
    zadaniomat_check_task_achievements($user_id);

    // Sprawdź wyzwania dnia
    zadaniomat_check_daily_challenges($user_id);

    return $result;
}

// Sprawdź odznaki związane z zadaniami
function zadaniomat_check_task_achievements($user_id) {
    global $wpdb;
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';

    // Policz ukończone zadania
    $completed_tasks = $wpdb->get_var(
        "SELECT COUNT(*) FROM $table_zadania WHERE status = 'zakonczone'"
    );

    if ($completed_tasks >= 100) zadaniomat_award_achievement($user_id, 'tasks_100');
    if ($completed_tasks >= 500) zadaniomat_award_achievement($user_id, 'tasks_500');
    if ($completed_tasks >= 1000) zadaniomat_award_achievement($user_id, 'tasks_1000');

    // Policz zadania cykliczne (te które mają odpowiednik w stale_zadania)
    $cyclic_count = $wpdb->get_var(
        "SELECT COUNT(*) FROM $table_zadania z
         INNER JOIN $table_stale s ON z.zadanie = s.nazwa
         WHERE z.status = 'zakonczone'"
    );

    if ($cyclic_count >= 50) zadaniomat_award_achievement($user_id, 'cyclic_50');
    if ($cyclic_count >= 200) zadaniomat_award_achievement($user_id, 'cyclic_200');

    // Policz przepracowane godziny
    $total_minutes = $wpdb->get_var(
        "SELECT SUM(faktyczny_czas) FROM $table_zadania WHERE status = 'zakonczone' AND faktyczny_czas > 0"
    );
    $total_hours = ($total_minutes ?: 0) / 60;

    if ($total_hours >= 100) zadaniomat_award_achievement($user_id, 'hours_100');
    if ($total_hours >= 500) zadaniomat_award_achievement($user_id, 'hours_500');
    if ($total_hours >= 1000) zadaniomat_award_achievement($user_id, 'hours_1000');

    // Pierwszy dzień
    if ($completed_tasks >= 1) {
        zadaniomat_award_achievement($user_id, 'first_day');
    }
}

// Wygeneruj wyzwania dnia
function zadaniomat_generate_daily_challenges($user_id, $date) {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_daily_challenges';

    // Sprawdź czy już są wyzwania na dziś
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE user_id = %d AND challenge_date = %s",
        $user_id, $date
    ));

    if ($existing > 0) return;

    // Pobierz wczorajsze wyzwania
    $yesterday = date('Y-m-d', strtotime($date) - 86400);
    $yesterday_challenges = $wpdb->get_col($wpdb->prepare(
        "SELECT challenge_key FROM $table WHERE user_id = %d AND challenge_date = %s",
        $user_id, $yesterday
    ));

    // Pula wyzwań
    $all_challenges = ZADANIOMAT_DAILY_CHALLENGES;
    $available = array_diff(array_keys($all_challenges), $yesterday_challenges);

    if (count($available) < 3) {
        $available = array_keys($all_challenges);
    }

    // Wybierz po jednym z każdej trudności, unikając duplikatów z tej samej grupy
    $by_difficulty = ['easy' => [], 'medium' => [], 'hard' => []];
    foreach ($available as $key) {
        $diff = $all_challenges[$key]['difficulty'];
        $by_difficulty[$diff][] = $key;
    }

    $selected = [];
    $used_groups = [];

    foreach (['easy', 'medium', 'hard'] as $diff) {
        if (!empty($by_difficulty[$diff])) {
            shuffle($by_difficulty[$diff]);
            // Znajdź wyzwanie które nie należy do już użytej grupy
            foreach ($by_difficulty[$diff] as $candidate) {
                $group = $all_challenges[$candidate]['group'] ?? null;
                if ($group === null || !in_array($group, $used_groups)) {
                    $selected[] = $candidate;
                    if ($group !== null) {
                        $used_groups[] = $group;
                    }
                    break;
                }
            }
        }
    }

    // Dodaj wyzwania
    foreach ($selected as $challenge_key) {
        $challenge = $all_challenges[$challenge_key];
        $wpdb->insert($table, [
            'user_id' => $user_id,
            'challenge_date' => $date,
            'challenge_key' => $challenge_key,
            'xp_reward' => $challenge['xp']
        ]);
    }
}

// Sprawdź i ukończ wyzwania dnia
function zadaniomat_check_daily_challenges($user_id, $date = null) {
    global $wpdb;
    $date = $date ?: date('Y-m-d');
    $table = $wpdb->prefix . 'zadaniomat_daily_challenges';
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $table_combo = $wpdb->prefix . 'zadaniomat_combo_state';

    // Pobierz nieukończone wyzwania
    $challenges = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND challenge_date = %s AND completed = 0",
        $user_id, $date
    ));

    $completed = [];

    foreach ($challenges as $challenge) {
        $is_completed = false;

        switch ($challenge->challenge_key) {
            case 'complete_5_tasks':
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_zadania WHERE dzien = %s AND status = 'zakonczone'", $date
                ));
                $is_completed = $count >= 5;
                break;

            case 'complete_8_tasks':
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_zadania WHERE dzien = %s AND status = 'zakonczone'", $date
                ));
                $is_completed = $count >= 8;
                break;

            case 'work_6_hours':
                // Liczy cały faktyczny czas pracy (nie tylko ukończone zadania)
                $minutes = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(faktyczny_czas) FROM $table_zadania WHERE dzien = %s", $date
                ));
                $is_completed = ($minutes ?: 0) >= 360;
                break;

            case 'work_8_hours':
                // Liczy cały faktyczny czas pracy (nie tylko ukończone zadania)
                $minutes = $wpdb->get_var($wpdb->prepare(
                    "SELECT SUM(faktyczny_czas) FROM $table_zadania WHERE dzien = %s", $date
                ));
                $is_completed = ($minutes ?: 0) >= 480;
                break;

            case '4_categories':
                $categories = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT kategoria) FROM $table_zadania WHERE dzien = %s AND status = 'zakonczone'", $date
                ));
                $is_completed = $categories >= 4;
                break;

            case 'all_categories':
                $categories = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT kategoria) FROM $table_zadania WHERE dzien = %s AND status = 'zakonczone'", $date
                ));
                $is_completed = $categories >= 6;
                break;

            case 'combo_3':
                $combo = $wpdb->get_var($wpdb->prepare(
                    "SELECT max_combo_today FROM $table_combo WHERE user_id = %d AND combo_date = %s",
                    $user_id, $date
                ));
                $is_completed = ($combo ?: 0) >= 3;
                break;

            case 'combo_5':
                $combo = $wpdb->get_var($wpdb->prepare(
                    "SELECT max_combo_today FROM $table_combo WHERE user_id = %d AND combo_date = %s",
                    $user_id, $date
                ));
                $is_completed = ($combo ?: 0) >= 5;
                break;

            case 'finish_all':
                // Tylko zadania z przypisanym czasem (nie szablony)
                $total = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_zadania WHERE dzien = %s AND planowany_czas > 0", $date
                ));
                $completed_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_zadania WHERE dzien = %s AND planowany_czas > 0 AND status = 'zakonczone'", $date
                ));
                $is_completed = $total > 0 && $total == $completed_count;
                break;

            case 'start_before_8':
                $start_time = get_option("zadaniomat_start_dnia_$date");
                $is_completed = $start_time && strtotime($start_time) < strtotime('08:00:00');
                break;

            case 'start_before_9':
                $start_time = get_option("zadaniomat_start_dnia_$date");
                $is_completed = $start_time && strtotime($start_time) < strtotime('09:00:00');
                break;

            case 'plan_before_10':
                $first_task = $wpdb->get_var($wpdb->prepare(
                    "SELECT created_at FROM $table_zadania WHERE dzien = %s ORDER BY created_at ASC LIMIT 1", $date
                ));
                if ($first_task) {
                    $task_date = date('Y-m-d', strtotime($first_task));
                    $task_time = date('H:i:s', strtotime($first_task));
                    $is_completed = $task_date == $date && strtotime($task_time) < strtotime('10:00:00');
                }
                break;

            case 'category_focus':
                $max_time = $wpdb->get_var($wpdb->prepare(
                    "SELECT MAX(cat_time) FROM (
                        SELECT SUM(faktyczny_czas) as cat_time FROM $table_zadania
                        WHERE dzien = %s AND status = 'zakonczone' GROUP BY kategoria
                    ) as subquery", $date
                ));
                $is_completed = ($max_time ?: 0) >= 180;
                break;

            case 'morning_plan':
                // Check if morning checklist was checked before deadline
                $morning_data = get_option('zadaniomat_morning_checklist', []);
                if (isset($morning_data[$date]) && $morning_data[$date]['checked']) {
                    $config = zadaniomat_get_gam_config();
                    $deadline = $config['time_settings']['early_planning_before'] ?? '10:00';
                    $check_time = $morning_data[$date]['time'];
                    $is_completed = $check_time <= $deadline;
                }
                break;

            case 'morning_work':
                // Check if X hours worked before Y deadline
                $config = zadaniomat_get_gam_config();
                $required_hours = $config['time_settings']['morning_work_hours'] ?? 3;
                $deadline = $config['time_settings']['morning_work_deadline'] ?? '12:00';
                $current_time = date('H:i');

                // If we're still before the deadline, count all completed work today
                // After deadline, only count work that was marked complete before deadline
                if ($current_time <= $deadline) {
                    $minutes = $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(faktyczny_czas) FROM $table_zadania
                         WHERE dzien = %s AND status = 'zakonczone'",
                        $date
                    ));
                } else {
                    $minutes = $wpdb->get_var($wpdb->prepare(
                        "SELECT SUM(faktyczny_czas) FROM $table_zadania
                         WHERE dzien = %s AND status = 'zakonczone'
                         AND DATE(updated_at) = %s AND TIME(updated_at) <= %s",
                        $date, $date, $deadline . ':00'
                    ));
                }
                $is_completed = ($minutes ?: 0) >= ($required_hours * 60);
                break;

            case 'all_cyclic':
                // Check if all cyclic tasks for today are completed
                $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';

                // Get cyclic tasks that should appear today
                $today_weekday = strtolower(date('D', strtotime($date)));
                $day_map = ['mon' => 'pn', 'tue' => 'wt', 'wed' => 'sr', 'thu' => 'cz', 'fri' => 'pt', 'sat' => 'so', 'sun' => 'nd'];
                $weekday_pl = $day_map[$today_weekday] ?? 'pn';

                $cyclic_names = $wpdb->get_col($wpdb->prepare(
                    "SELECT nazwa FROM $table_stale WHERE aktywne = 1 AND (
                        typ_powtarzania = 'codziennie' OR
                        (typ_powtarzania = 'dni_tygodnia' AND dni_tygodnia LIKE %s)
                    )",
                    '%' . $weekday_pl . '%'
                ));

                if (!empty($cyclic_names)) {
                    $completed_cyclic = 0;
                    foreach ($cyclic_names as $name) {
                        $exists = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM $table_zadania WHERE dzien = %s AND nazwa = %s AND status = 'zakonczone'",
                            $date, $name
                        ));
                        if ($exists > 0) $completed_cyclic++;
                    }
                    $is_completed = $completed_cyclic >= count($cyclic_names);
                }
                break;
        }

        if ($is_completed) {
            $wpdb->update($table, [
                'completed' => 1,
                'completed_at' => current_time('mysql')
            ], ['id' => $challenge->id]);

            $challenge_def = ZADANIOMAT_DAILY_CHALLENGES[$challenge->challenge_key] ?? [];
            $desc = $challenge_def['desc'] ?? $challenge->challenge_key;
            $condition = $challenge_def['condition'] ?? '';
            $full_desc = "Wyzwanie: " . $desc . ($condition ? " | Warunek: " . $condition : '');
            zadaniomat_add_xp($user_id, $challenge->xp_reward, 'daily_challenge', $full_desc);

            $completed[] = $challenge->challenge_key;
        }
    }

    return $completed;
}

// Przetwórz osiągnięcie celu
function zadaniomat_process_goal_completion($cel_id, $user_id = 1) {
    global $wpdb;
    $table_cele = $wpdb->prefix . 'zadaniomat_cele_okres';

    $cel = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_cele WHERE id = %d", $cel_id
    ));

    if (!$cel || $cel->osiagniety != 1) return null;

    $result = [
        'cel_id' => $cel_id,
        'xp_earned' => 0,
        'achievements' => []
    ];

    // Policz pozycję celu (x1, x2, x3...)
    $position = $cel->pozycja ?: 1;
    $base_xp = 100 + (($position - 1) * 20); // x1 = 100, x2 = 120, x3 = 140...

    $xp_result = zadaniomat_add_xp($user_id, $base_xp, 'goal_achieved',
        "Cel osiągnięty: " . mb_substr($cel->cel, 0, 50), $cel_id, 'goal');

    $result['xp_earned'] = $xp_result['xp_added'];
    $result['level_up'] = $xp_result['level_up'];
    $result['total_xp'] = $xp_result['total_xp'];

    // Sprawdź odznaki celów
    $total_goals = $wpdb->get_var(
        "SELECT COUNT(*) FROM $table_cele WHERE osiagniety = 1"
    );

    if ($total_goals == 1) zadaniomat_award_achievement($user_id, 'first_goal');
    if ($total_goals >= 10) zadaniomat_award_achievement($user_id, 'goals_10');
    if ($total_goals >= 25) zadaniomat_award_achievement($user_id, 'goals_25');
    if ($total_goals >= 50) zadaniomat_award_achievement($user_id, 'goals_50');

    // Sprawdź odznaki wielokrotnych celów w tej samej kategorii i okresie
    $goals_in_same_category = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_cele
         WHERE okres_id = %d AND kategoria = %s AND osiagniety = 1",
        $cel->okres_id, $cel->kategoria
    ));
    if ($goals_in_same_category >= 2) zadaniomat_award_achievement($user_id, 'goal_x2');
    if ($goals_in_same_category >= 3) zadaniomat_award_achievement($user_id, 'goal_x3');

    // Sprawdź czy wszystkie cele okresu osiągnięte
    $okres_cele = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) as total, SUM(CASE WHEN osiagniety = 1 THEN 1 ELSE 0 END) as completed
         FROM $table_cele WHERE okres_id = %d AND cel IS NOT NULL AND cel != ''",
        $cel->okres_id
    ));

    if ($okres_cele && $okres_cele->total > 0 && $okres_cele->total == $okres_cele->completed) {
        // Wszystkie cele okresu osiągnięte! Przyznaj tylko odznakę (która sama doda XP)
        zadaniomat_award_achievement($user_id, 'perfect_period');
        $result['all_goals_completed'] = true;
    }

    return $result;
}

// Przetwórz bonusy na koniec dnia
function zadaniomat_process_end_of_day($user_id, $date) {
    global $wpdb;
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $result = ['bonuses' => [], 'total_xp' => 0];

    // Sprawdź czy dzień roboczy
    if (!zadaniomat_is_work_day($date)) {
        return $result;
    }

    // Wszystkie zadania dnia ukończone
    $total = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_zadania WHERE dzien = %s", $date
    ));
    $completed = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_zadania WHERE dzien = %s AND status = 'zakonczone'", $date
    ));

    if ($total > 0 && $total == $completed) {
        zadaniomat_add_xp($user_id, 30, 'all_tasks_day', "Wszystkie zadania dnia ukończone");
        $result['bonuses'][] = ['type' => 'all_tasks', 'xp' => 30];
        $result['total_xp'] += 30;
    }

    // Wczesny start (przed 8:00)
    $start_time = get_option("zadaniomat_start_dnia_$date");
    if ($start_time && strtotime($start_time) < strtotime('08:00:00')) {
        zadaniomat_add_xp($user_id, 20, 'early_start_bonus', "Wczesny start dnia");
        $result['bonuses'][] = ['type' => 'early_start', 'xp' => 20];
        $result['total_xp'] += 20;

        // Aktualizuj streak wczesnego startu
        zadaniomat_update_streak($user_id, 'early_start', $date);
    }

    // Wczesne planowanie (przed 10:00)
    $first_task = $wpdb->get_row($wpdb->prepare(
        "SELECT created_at FROM $table_zadania WHERE dzien = %s ORDER BY created_at ASC LIMIT 1", $date
    ));
    if ($first_task) {
        $task_date = date('Y-m-d', strtotime($first_task->created_at));
        $task_time = date('H:i:s', strtotime($first_task->created_at));
        if ($task_date == $date && strtotime($task_time) < strtotime('10:00:00')) {
            zadaniomat_add_xp($user_id, 15, 'early_planning_bonus', "Wczesne planowanie dnia");
            $result['bonuses'][] = ['type' => 'early_planning', 'xp' => 15];
            $result['total_xp'] += 15;

            // Aktualizuj streak wczesnego planowania
            zadaniomat_update_streak($user_id, 'early_planning', $date);
        }
    }

    // Pokrycie kategorii
    $goal_categories = array_keys(ZADANIOMAT_KATEGORIE);
    $covered = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT kategoria) FROM $table_zadania
         WHERE dzien = %s AND status = 'zakonczone' AND kategoria IN ('" . implode("','", $goal_categories) . "')",
        $date
    ));

    $coverage_bonus = 0;
    if ($covered >= 6) {
        $coverage_bonus = 80;
        zadaniomat_update_streak($user_id, 'full_coverage', $date);
    } else if ($covered >= 5) {
        $coverage_bonus = 50;
    } else if ($covered >= 4) {
        $coverage_bonus = 30;
    } else if ($covered >= 3) {
        $coverage_bonus = 15;
    }

    if ($coverage_bonus > 0) {
        zadaniomat_add_xp($user_id, $coverage_bonus, 'category_coverage', "Pokrycie $covered kategorii");
        $result['bonuses'][] = ['type' => 'coverage', 'categories' => $covered, 'xp' => $coverage_bonus];
        $result['total_xp'] += $coverage_bonus;
    }

    // Godziny w kategoriach celów
    $hours_in_goal_categories = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(faktyczny_czas) FROM $table_zadania
         WHERE dzien = %s AND status = 'zakonczone' AND kategoria IN ('" . implode("','", $goal_categories) . "')",
        $date
    ));
    $full_hours = floor(($hours_in_goal_categories ?: 0) / 60);

    if ($full_hours > 0) {
        $config = zadaniomat_get_gam_config();
        $xp_per_hour = $config['xp_values']['category_hour'] ?? 5;
        $hours_xp = $full_hours * $xp_per_hour;
        zadaniomat_add_xp($user_id, $hours_xp, 'category_hour', "$full_hours godzin w kategoriach celów (po $xp_per_hour XP/h)");
        $result['bonuses'][] = ['type' => 'hours', 'hours' => $full_hours, 'xp' => $hours_xp];
        $result['total_xp'] += $hours_xp;
    }

    // Aktualizuj główny streak (work_days)
    // Warunki: min 3 ukończone zadania LUB min 4h pracy LUB 100% zadań
    $work_minutes = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(faktyczny_czas) FROM $table_zadania WHERE dzien = %s AND status = 'zakonczone'", $date
    ));
    $work_hours = ($work_minutes ?: 0) / 60;

    $qualifies_for_streak = ($completed >= 3) || ($work_hours >= 4) || ($total > 0 && $completed == $total);

    if ($qualifies_for_streak) {
        zadaniomat_update_streak($user_id, 'work_days', $date);
    }

    return $result;
}

// Pobierz wszystkie niewyświetlone odznaki
function zadaniomat_get_unnotified_achievements($user_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_achievements';

    $achievements = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d AND notified = 0 ORDER BY earned_at DESC",
        $user_id
    ));

    $result = [];
    foreach ($achievements as $a) {
        $data = ZADANIOMAT_ACHIEVEMENTS[$a->achievement_key] ?? null;
        if ($data) {
            $result[] = [
                'key' => $a->achievement_key,
                'name' => $data['name'],
                'icon' => $data['icon'],
                'desc' => $data['desc'],
                'xp' => $data['xp'],
                'earned_at' => $a->earned_at
            ];
        }
    }

    return $result;
}

// Oznacz odznaki jako wyświetlone
function zadaniomat_mark_achievements_notified($user_id, $achievement_keys = null) {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_achievements';

    if ($achievement_keys) {
        $placeholders = implode(',', array_fill(0, count($achievement_keys), '%s'));
        $wpdb->query($wpdb->prepare(
            "UPDATE $table SET notified = 1 WHERE user_id = %d AND achievement_key IN ($placeholders)",
            array_merge([$user_id], $achievement_keys)
        ));
    } else {
        $wpdb->update($table, ['notified' => 1], ['user_id' => $user_id, 'notified' => 0]);
    }
}

// Pobierz pełne dane gamifikacji dla UI
function zadaniomat_get_gamification_data($user_id = 1) {
    global $wpdb;
    $today = date('Y-m-d');

    // Wygeneruj wyzwania dnia jeśli nie istnieją
    zadaniomat_generate_daily_challenges($user_id, $today);

    // Statystyki gracza
    $stats = zadaniomat_get_gamification_stats($user_id);
    $level_info = zadaniomat_get_level_info($stats->current_level);

    // Oblicz progress do następnego poziomu
    $current_level_xp = ZADANIOMAT_LEVEL_THRESHOLDS[$stats->current_level];
    $next_level_xp = isset(ZADANIOMAT_LEVEL_THRESHOLDS[$stats->current_level + 1])
        ? ZADANIOMAT_LEVEL_THRESHOLDS[$stats->current_level + 1]
        : $current_level_xp;
    $xp_in_level = $stats->total_xp - $current_level_xp;
    $xp_needed = $next_level_xp - $current_level_xp;
    $progress_percent = $xp_needed > 0 ? min(100, round(($xp_in_level / $xp_needed) * 100)) : 100;

    // Streaki
    $streaks = [];
    $streak_types = ['work_days', 'early_start', 'early_planning', 'full_coverage', 'all_tasks_done', 'cyclic_tasks'];
    foreach ($streak_types as $type) {
        $s = zadaniomat_get_streak($user_id, $type);
        $streaks[$type] = [
            'current' => $s->current_count,
            'best' => $s->best_count,
            'last_date' => $s->last_date
        ];
    }

    // Combo dzisiejsze
    $combo = zadaniomat_get_combo_state($user_id, $today);

    // Wyzwania dnia
    $table_challenges = $wpdb->prefix . 'zadaniomat_daily_challenges';
    $challenges = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_challenges WHERE user_id = %d AND challenge_date = %s",
        $user_id, $today
    ));

    $challenges_data = [];
    $challenges_config = zadaniomat_get_daily_challenges_config();
    foreach ($challenges as $c) {
        $def = $challenges_config[$c->challenge_key] ?? ZADANIOMAT_DAILY_CHALLENGES[$c->challenge_key] ?? null;
        $challenges_data[] = [
            'key' => $c->challenge_key,
            'desc' => $def['desc'] ?? $c->challenge_key,
            'xp' => $c->xp_reward,
            'completed' => (bool)$c->completed,
            'difficulty' => $def['difficulty'] ?? 'medium',
            'condition' => $def['condition'] ?? ''
        ];
    }

    // Niewyświetlone odznaki
    $new_achievements = zadaniomat_get_unnotified_achievements($user_id);

    // Dzisiejsze XP
    $table_xp = $wpdb->prefix . 'zadaniomat_xp_log';
    $today_xp = $wpdb->get_var($wpdb->prepare(
        "SELECT SUM(xp_amount) FROM $table_xp WHERE user_id = %d AND DATE(earned_at) = %s",
        $user_id, $today
    ));

    // Mnożniki
    $streak_multiplier = zadaniomat_get_streak_multiplier($streaks['work_days']['current']);
    $combo_multiplier = zadaniomat_get_combo_multiplier($combo->current_combo);

    return [
        'stats' => [
            'total_xp' => $stats->total_xp,
            'current_level' => $stats->current_level,
            'prestige' => $stats->prestige,
            'freeze_days' => $stats->freeze_days_available
        ],
        'level' => [
            'level' => $level_info['level'],
            'name' => $level_info['name'],
            'icon' => $level_info['icon'],
            'current_xp' => $stats->total_xp,
            'level_start_xp' => $current_level_xp,
            'next_level_xp' => $next_level_xp,
            'progress_percent' => $progress_percent,
            'xp_in_level' => $xp_in_level,
            'xp_needed' => $xp_needed
        ],
        'streaks' => $streaks,
        'combo' => [
            'current' => $combo->current_combo,
            'max_today' => $combo->max_combo_today,
            'multiplier' => $combo_multiplier
        ],
        'multipliers' => [
            'streak' => $streak_multiplier,
            'combo' => $combo_multiplier,
            'total' => $streak_multiplier * $combo_multiplier
        ],
        'challenges' => $challenges_data,
        'new_achievements' => $new_achievements,
        'today_xp' => (int)($today_xp ?: 0)
    ];
}

// Prestige - reset z bonusem
function zadaniomat_do_prestige($user_id = 1) {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_gamification_stats';

    $stats = zadaniomat_get_gamification_stats($user_id);

    // Wymagania: level 10 i 7000+ XP
    if ($stats->current_level < 10 || $stats->total_xp < 7000) {
        return ['success' => false, 'error' => 'Wymagany level 10 i 7000 XP'];
    }

    $new_prestige = $stats->prestige + 1;

    $wpdb->update($table, [
        'total_xp' => 0,
        'current_level' => 1,
        'prestige' => $new_prestige,
        'freeze_days_available' => $stats->freeze_days_available + 1
    ], ['user_id' => $user_id]);

    // Odznaka prestige
    if ($new_prestige == 1) {
        zadaniomat_award_achievement($user_id, 'prestige_1');
    }

    return [
        'success' => true,
        'new_prestige' => $new_prestige,
        'freeze_days' => $stats->freeze_days_available + 1
    ];
}

// =============================================
// MENU
// =============================================
add_action('admin_menu', function() {
    add_menu_page('Zadaniomat', 'Zadaniomat', 'manage_options', 'zadaniomat', 'zadaniomat_page_main', 'dashicons-list-view', 30);
    add_submenu_page('zadaniomat', 'Dashboard', '📋 Dashboard', 'manage_options', 'zadaniomat', 'zadaniomat_page_main');
    add_submenu_page('zadaniomat', 'Gamifikacja', '🎮 Gamifikacja', 'manage_options', 'zadaniomat-gamification', 'zadaniomat_page_gamification');
    add_submenu_page('zadaniomat', 'Ustawienia', '⚙️ Ustawienia', 'manage_options', 'zadaniomat-settings', 'zadaniomat_page_settings');
});

// =============================================
// ALL AJAX HANDLERS
// =============================================

// Helper to ensure zadania table has all columns
function zadaniomat_ensure_zadania_columns() {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_zadania';

    // Check for jest_cykliczne column
    $column_check = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'jest_cykliczne'");
    if (empty($column_check)) {
        $wpdb->query("ALTER TABLE $table ADD COLUMN jest_cykliczne TINYINT(1) DEFAULT 0");
    }
}

// Dodaj zadanie
add_action('wp_ajax_zadaniomat_add_task', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $task_date = sanitize_text_field($_POST['dzien']);
    $auto_okres = zadaniomat_get_current_okres($task_date);

    // Ensure all columns exist
    zadaniomat_ensure_zadania_columns();

    $result = $wpdb->insert($table, [
        'okres_id' => $auto_okres ? $auto_okres->id : null,
        'kategoria' => sanitize_text_field($_POST['kategoria']),
        'dzien' => $task_date,
        'zadanie' => sanitize_text_field($_POST['zadanie']),
        'cel_todo' => sanitize_textarea_field($_POST['cel_todo']),
        'planowany_czas' => intval($_POST['planowany_czas']),
        'jest_cykliczne' => !empty($_POST['jest_cykliczne']) ? 1 : 0
    ]);

    if ($result === false) {
        wp_send_json_error('Błąd bazy danych: ' . $wpdb->last_error);
        return;
    }

    $task_id = $wpdb->insert_id;
    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $task_id));

    if (!$task) {
        wp_send_json_error('Nie udało się pobrać zadania po dodaniu');
        return;
    }

    wp_send_json_success([
        'task' => $task,
        'kategoria_label' => zadaniomat_get_kategoria_label($task->kategoria)
    ]);
});

// Edytuj zadanie
add_action('wp_ajax_zadaniomat_edit_task', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $task_date = sanitize_text_field($_POST['dzien']);
    $auto_okres = zadaniomat_get_current_okres($task_date);
    $id = intval($_POST['id']);

    // Ensure jest_cykliczne column exists
    zadaniomat_ensure_zadania_columns();

    $wpdb->update($table, [
        'okres_id' => $auto_okres ? $auto_okres->id : null,
        'kategoria' => sanitize_text_field($_POST['kategoria']),
        'dzien' => $task_date,
        'zadanie' => sanitize_text_field($_POST['zadanie']),
        'cel_todo' => sanitize_textarea_field($_POST['cel_todo']),
        'planowany_czas' => intval($_POST['planowany_czas']),
        'jest_cykliczne' => !empty($_POST['jest_cykliczne']) ? 1 : 0
    ], ['id' => $id]);

    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

    wp_send_json_success([
        'task' => $task,
        'kategoria_label' => zadaniomat_get_kategoria_label($task->kategoria)
    ]);
});

// Usuń zadanie
add_action('wp_ajax_zadaniomat_delete_task', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $id = intval($_POST['id']);

    $wpdb->delete($table, ['id' => $id]);

    wp_send_json_success();
});

// Zbiorowe usuwanie zadań
add_action('wp_ajax_zadaniomat_bulk_delete', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];

    if (empty($ids)) {
        wp_send_json_error(['message' => 'Brak zadań do usunięcia']);
    }

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", $ids));

    wp_send_json_success(['deleted' => count($ids)]);
});

// Zbiorowe kopiowanie zadań
add_action('wp_ajax_zadaniomat_bulk_copy', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $ids = isset($_POST['ids']) ? array_map('intval', $_POST['ids']) : [];
    $target_date = sanitize_text_field($_POST['target_date']);

    if (empty($ids) || empty($target_date)) {
        wp_send_json_error(['message' => 'Brak zadań lub daty docelowej']);
    }

    $auto_okres = zadaniomat_get_current_okres($target_date);

    $placeholders = implode(',', array_fill(0, count($ids), '%d'));
    $tasks = $wpdb->get_results($wpdb->prepare("SELECT kategoria, zadanie, cel_todo, planowany_czas FROM $table WHERE id IN ($placeholders)", $ids));

    foreach ($tasks as $task) {
        $wpdb->insert($table, [
            'okres_id' => $auto_okres ? $auto_okres->id : null,
            'kategoria' => $task->kategoria,
            'dzien' => $target_date,
            'zadanie' => $task->zadanie,
            'cel_todo' => $task->cel_todo,
            'planowany_czas' => $task->planowany_czas
        ]);
    }

    wp_send_json_success(['copied' => count($tasks)]);
});

// Szybka aktualizacja (status, faktyczny czas)
add_action('wp_ajax_zadaniomat_quick_update', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $id = intval($_POST['id']);
    $field = sanitize_text_field($_POST['field']);
    $value = $_POST['value'] === '' ? null : sanitize_text_field($_POST['value']);

    // Pobierz poprzedni status przed aktualizacją
    $old_status = null;
    if ($field === 'status') {
        $old_status = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM $table WHERE id = %d", $id
        ));
    }

    if (in_array($field, ['faktyczny_czas', 'status'])) {
        $wpdb->update($table, [$field => $value], ['id' => $id]);
    }

    // Jeśli zmieniono status na 'zakonczone' - przyznaj XP
    $gamification_result = null;
    if ($field === 'status' && $value === 'zakonczone' && $old_status !== 'zakonczone') {
        $gamification_result = zadaniomat_process_task_completion($id, 1);
    }

    wp_send_json_success(['gamification' => $gamification_result]);
});

// Przenieś zadanie
add_action('wp_ajax_zadaniomat_move_task', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $id = intval($_POST['id']);
    $new_date = sanitize_text_field($_POST['new_date']);
    $new_okres = zadaniomat_get_current_okres($new_date);

    $wpdb->update($table, [
        'dzien' => $new_date,
        'okres_id' => $new_okres ? $new_okres->id : null
    ], ['id' => $id]);

    wp_send_json_success();
});

// Kopiuj pojedyncze zadanie na inny dzień
add_action('wp_ajax_zadaniomat_copy_task_to_date', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $id = intval($_POST['id']);
    $target_date = sanitize_text_field($_POST['target_date']);
    $new_okres = zadaniomat_get_current_okres($target_date);

    // Pobierz oryginalne zadanie
    $task = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

    if (!$task) {
        wp_send_json_error('Zadanie nie istnieje');
        return;
    }

    // Skopiuj zadanie na nowy dzień (ze statusem "nowe", bez czasu faktycznego)
    $wpdb->insert($table, [
        'okres_id' => $new_okres ? $new_okres->id : null,
        'kategoria' => $task->kategoria,
        'dzien' => $target_date,
        'zadanie' => $task->zadanie,
        'cel_todo' => $task->cel_todo,
        'planowany_czas' => $task->planowany_czas,
        'faktyczny_czas' => null,
        'status' => 'nowe',
        'godzina_start' => $task->godzina_start,
        'godzina_koniec' => $task->godzina_koniec,
        'pozycja_harmonogram' => null,
        'jest_cykliczne' => $task->jest_cykliczne ?? 0
    ]);

    wp_send_json_success(['new_id' => $wpdb->insert_id]);
});

// Pobierz zadania dla zakresu dat
add_action('wp_ajax_zadaniomat_get_tasks', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);
    
    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE dzien BETWEEN %s AND %s ORDER BY dzien ASC, id ASC",
        $start, $end
    ));
    
    // Dodaj labele kategorii
    foreach ($tasks as &$task) {
        $task->kategoria_label = zadaniomat_get_kategoria_label($task->kategoria);
    }
    
    wp_send_json_success(['tasks' => $tasks]);
});

// Pobierz nieukończone zadania (zaległe - status nowe lub rozpoczete)
add_action('wp_ajax_zadaniomat_get_overdue', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';

    $tasks = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE dzien < %s AND (status IS NULL OR status = 'nowe') ORDER BY dzien ASC",
        date('Y-m-d')
    ));

    foreach ($tasks as &$task) {
        $task->kategoria_label = zadaniomat_get_kategoria_label($task->kategoria);
    }

    wp_send_json_success(['tasks' => $tasks]);
});

// Pobierz dni z zadaniami (dla kalendarza)
add_action('wp_ajax_zadaniomat_get_calendar_dots', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $month = sanitize_text_field($_POST['month']);
    $month_start = $month . '-01';
    $month_end = date('Y-m-t', strtotime($month_start));
    
    $days = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT dzien FROM $table WHERE dzien BETWEEN %s AND %s",
        $month_start, $month_end
    ));
    
    wp_send_json_success(['days' => $days]);
});

// Pobierz statystyki dnia (AJAX refresh)
add_action('wp_ajax_zadaniomat_get_daily_stats', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';

    // Kategorie celów - dynamicznie z ustawień (wszystkie kategorie celów)
    $kategorie_celow = array_keys(zadaniomat_get_kategorie());
    $kategorie_celow_escaped = array_map('esc_sql', $kategorie_celow);
    $kategorie_celow_sql = "'" . implode("','", $kategorie_celow_escaped) . "'";

    // Statystyki dla kategorii celów
    $cele_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT SUM(faktyczny_czas) as faktyczny_min, SUM(planowany_czas) as planowany_min
         FROM $table_zadania
         WHERE dzien = %s AND kategoria IN ($kategorie_celow_sql)",
        $date
    ));

    // Statystyki dla innych kategorii
    $inne_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT SUM(faktyczny_czas) as faktyczny_min
         FROM $table_zadania
         WHERE dzien = %s AND kategoria NOT IN ($kategorie_celow_sql)",
        $date
    ));

    // Statystyki wszystkich zadań
    $total_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT COUNT(*) as liczba_zadan,
                SUM(faktyczny_czas) as faktyczny_min,
                SUM(CASE WHEN status = 'zakonczone' THEN 1 ELSE 0 END) as ukonczone
         FROM $table_zadania
         WHERE dzien = %s",
        $date
    ));

    $faktyczny_cele_h = ($cele_stats->faktyczny_min ?: 0) / 60;
    $planowany_cele_h = ($cele_stats->planowany_min ?: 0) / 60;
    $faktyczny_inne_h = ($inne_stats->faktyczny_min ?: 0) / 60;
    $faktyczny_total_h = ($total_stats->faktyczny_min ?: 0) / 60;
    $procent = $planowany_cele_h > 0 ? min(100, round(($faktyczny_cele_h / $planowany_cele_h) * 100)) : 0;

    wp_send_json_success([
        'cele_hours' => number_format($faktyczny_cele_h, 1),
        'planned_hours' => number_format($planowany_cele_h, 1),
        'inne_hours' => number_format($faktyczny_inne_h, 1),
        'razem_hours' => number_format($faktyczny_total_h, 1),
        'procent' => $procent,
        'tasks_count' => $total_stats->liczba_zadan ?: 0,
        'tasks_done' => $total_stats->ukonczone ?: 0
    ]);
});

// Zapisz cel okresu
add_action('wp_ajax_zadaniomat_save_cel_okres', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $okres_id = intval($_POST['okres_id']);
    $kategoria = sanitize_text_field($_POST['kategoria']);
    $cel = sanitize_textarea_field($_POST['cel']);
    $cel_id = isset($_POST['cel_id']) ? intval($_POST['cel_id']) : 0;

    // Jeśli mamy konkretne cel_id, aktualizuj ten cel
    if ($cel_id > 0) {
        $wpdb->update($table, ['cel' => $cel], ['id' => $cel_id]);
    } else {
        // Szukaj istniejącego NIEUKOŃCZONEGO celu dla tej kategorii
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE okres_id = %d AND kategoria = %s AND completed_at IS NULL ORDER BY id DESC LIMIT 1",
            $okres_id, $kategoria
        ));

        if ($existing) {
            $wpdb->update($table, ['cel' => $cel], ['id' => $existing->id]);
            $cel_id = $existing->id;
        } else {
            // Brak nieukończonych celów - dodaj nowy
            $max_pozycja = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(pozycja) FROM $table WHERE okres_id = %d AND kategoria = %s",
                $okres_id, $kategoria
            ));
            $wpdb->insert($table, [
                'okres_id' => $okres_id,
                'kategoria' => $kategoria,
                'cel' => $cel,
                'pozycja' => ($max_pozycja ?: 0) + 1
            ]);
            $cel_id = $wpdb->insert_id;
        }
    }

    wp_send_json_success(['cel_id' => $cel_id]);
});

// Aktualizuj status celu okresu
add_action('wp_ajax_zadaniomat_update_cel_okres_status', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $id = intval($_POST['id']);

    $update_data = [];

    // Obsługa statusu procentowego
    if (isset($_POST['status'])) {
        $update_data['status'] = $_POST['status'] === '' ? null : floatval($_POST['status']);
    }

    // Obsługa osiągnięcia celu
    if (isset($_POST['osiagniety'])) {
        $update_data['osiagniety'] = $_POST['osiagniety'] === '' ? null : intval($_POST['osiagniety']);
    }

    if (!empty($update_data)) {
        $wpdb->update($table, $update_data, ['id' => $id]);
    }

    wp_send_json_success();
});

// Pobierz cele okresu (do modala) - wszystkie cele, włącznie z ukończonymi
add_action('wp_ajax_zadaniomat_get_okres_cele', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table_cele = $wpdb->prefix . 'zadaniomat_cele_okres';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $okres_id = intval($_POST['okres_id']);

    $okres = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_okresy WHERE id = %d", $okres_id));
    $cele = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_cele WHERE okres_id = %d ORDER BY kategoria, pozycja ASC, id ASC", $okres_id));

    // Grupuj cele po kategorii - WSZYSTKIE cele (aktywne i ukończone)
    $cele_by_kat = [];
    foreach ($cele as $c) {
        if (!isset($cele_by_kat[$c->kategoria])) {
            $cele_by_kat[$c->kategoria] = [];
        }
        $cele_by_kat[$c->kategoria][] = $c;
    }

    wp_send_json_success([
        'okres' => $okres,
        'cele' => $cele_by_kat,
        'kategorie' => ZADANIOMAT_KATEGORIE
    ]);
});

// Zapisz podsumowanie celu okresu (osiągnięty + uwagi) - teraz obsługuje konkretny cel_id
add_action('wp_ajax_zadaniomat_save_cel_podsumowanie', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $cel_id = isset($_POST['cel_id']) ? intval($_POST['cel_id']) : 0;
    $okres_id = intval($_POST['okres_id']);
    $kategoria = sanitize_text_field($_POST['kategoria']);
    $osiagniety = $_POST['osiagniety'] === '' ? null : intval($_POST['osiagniety']);
    $uwagi = isset($_POST['uwagi']) ? sanitize_textarea_field($_POST['uwagi']) : '';

    $update_data = ['osiagniety' => $osiagniety, 'uwagi' => $uwagi];

    // Jeśli mamy cel_id, aktualizuj ten konkretny cel
    if ($cel_id > 0) {
        $wpdb->update($table, $update_data, ['id' => $cel_id]);
        wp_send_json_success(['cel_id' => $cel_id]);
    } else {
        // Kompatybilność wsteczna - szukaj istniejącego celu
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table WHERE okres_id = %d AND kategoria = %s AND completed_at IS NULL ORDER BY id DESC LIMIT 1", $okres_id, $kategoria
        ));

        if ($existing) {
            $wpdb->update($table, $update_data, ['id' => $existing->id]);
            wp_send_json_success(['cel_id' => $existing->id]);
        } else {
            wp_send_json_error(['message' => 'Nie znaleziono celu']);
        }
    }
});

// Zapisz cel roku
add_action('wp_ajax_zadaniomat_save_cel_rok', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    
    $table = $wpdb->prefix . 'zadaniomat_cele_rok';
    $rok_id = intval($_POST['rok_id']);
    $kategoria = sanitize_text_field($_POST['kategoria']);
    $cel = sanitize_textarea_field($_POST['cel']);
    
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table WHERE rok_id = %d AND kategoria = %s", $rok_id, $kategoria
    ));
    
    if ($existing) {
        $wpdb->update($table, ['cel' => $cel], ['id' => $existing->id]);
    } else {
        $wpdb->insert($table, ['rok_id' => $rok_id, 'kategoria' => $kategoria, 'cel' => $cel]);
    }
    
    wp_send_json_success();
});

// Zapisz planowane godziny dziennie dla kategorii (per okres)
add_action('wp_ajax_zadaniomat_save_planowane_godziny', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_godziny_okres';
    $okres_id = intval($_POST['okres_id']);
    $kategoria = sanitize_text_field($_POST['kategoria']);
    $godziny = floatval($_POST['planowane_godziny_dziennie']);

    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT id FROM $table WHERE okres_id = %d AND kategoria = %s", $okres_id, $kategoria
    ));

    if ($existing) {
        $wpdb->update($table, ['planowane_godziny_dziennie' => $godziny], ['id' => $existing->id]);
    } else {
        $wpdb->insert($table, ['okres_id' => $okres_id, 'kategoria' => $kategoria, 'planowane_godziny_dziennie' => $godziny]);
    }

    wp_send_json_success();
});

// Pobierz wszystkie lata i okresy
add_action('wp_ajax_zadaniomat_get_all_roki_okresy', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';

    $roki = $wpdb->get_results("SELECT * FROM $table_roki ORDER BY data_start DESC");
    $okresy = $wpdb->get_results("SELECT * FROM $table_okresy ORDER BY data_start DESC");

    wp_send_json_success([
        'roki' => $roki,
        'okresy' => $okresy
    ]);
});

// Pobierz statystyki godzin dla okresu/roku
add_action('wp_ajax_zadaniomat_get_stats', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $table_godziny_okres = $wpdb->prefix . 'zadaniomat_godziny_okres';
    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';

    $filter_type = sanitize_text_field($_POST['filter_type']); // 'rok' lub 'okres'
    $filter_id = intval($_POST['filter_id']);

    // Pobierz daty
    $okres_id = null;
    if ($filter_type === 'rok') {
        $filter_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_roki WHERE id = %d", $filter_id));
        $rok_id = $filter_id;
        // Dla roku bierzemy aktualny okres
        $today = date('Y-m-d');
        $current_okres = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_okresy WHERE rok_id = %d AND %s BETWEEN data_start AND data_koniec",
            $rok_id, $today
        ));
        $okres_id = $current_okres ? $current_okres->id : null;
    } else {
        $filter_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_okresy WHERE id = %d", $filter_id));
        $rok_id = $filter_data ? $filter_data->rok_id : null;
        $okres_id = $filter_id;
    }

    if (!$filter_data) {
        wp_send_json_error(['message' => 'Nie znaleziono okresu/roku']);
        return;
    }

    $start_date = $filter_data->data_start;
    $end_date = $filter_data->data_koniec;

    // Policz dni robocze w okresie
    // Logika: weekendy domyślnie wolne, dni Pn-Pt domyślnie robocze
    // Wpis w tabeli dni_wolne = odwrócony status
    $table_dni_wolne = $wpdb->prefix . 'zadaniomat_dni_wolne';

    // Pobierz dni z odwróconym statusem
    $dni_override = $wpdb->get_col($wpdb->prepare(
        "SELECT dzien FROM $table_dni_wolne WHERE dzien BETWEEN %s AND %s",
        $start_date, $end_date
    ));
    $dni_override_set = array_flip($dni_override);

    // Policz dni robocze
    $current = new DateTime($start_date);
    $end = new DateTime($end_date);
    $total_days = 0;
    $dni_wolne_count = 0;

    while ($current <= $end) {
        $total_days++;
        $weekday = (int)$current->format('N'); // 1=Pn, 6=Sb, 7=Nd
        $date_str = $current->format('Y-m-d');
        $is_weekend = ($weekday >= 6);
        $has_override = isset($dni_override_set[$date_str]);

        // Weekend domyślnie wolny, wpis w tabeli = roboczy
        // Dzień Pn-Pt domyślnie roboczy, wpis w tabeli = wolny
        $is_free = $is_weekend ? !$has_override : $has_override;

        if ($is_free) {
            $dni_wolne_count++;
        }
        $current->modify('+1 day');
    }

    $dni_w_okresie = $total_days - $dni_wolne_count;

    // Pobierz statystyki per kategoria
    $stats = $wpdb->get_results($wpdb->prepare(
        "SELECT
            kategoria,
            COUNT(*) as liczba_zadan,
            SUM(COALESCE(faktyczny_czas, 0)) as faktyczny_czas_suma,
            SUM(COALESCE(planowany_czas, 0)) as planowany_czas_suma,
            SUM(CASE WHEN status = 'zakonczone' THEN 1 ELSE 0 END) as ukonczone
        FROM $table_zadania
        WHERE dzien BETWEEN %s AND %s
        GROUP BY kategoria",
        $start_date, $end_date
    ));

    // Pobierz planowane godziny dziennie per kategoria (z tabeli godziny_okres)
    $planowane = [];
    if ($okres_id) {
        $planowane_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT kategoria, planowane_godziny_dziennie FROM $table_godziny_okres WHERE okres_id = %d",
            $okres_id
        ));
        foreach ($planowane_raw as $p) {
            $planowane[$p->kategoria] = floatval($p->planowane_godziny_dziennie);
        }
    }

    // Przygotuj dane wynikowe
    $stats_by_kategoria = [];
    foreach ($stats as $s) {
        $planowane_dziennie = isset($planowane[$s->kategoria]) ? $planowane[$s->kategoria] : 1.0;
        $planowane_w_okresie = $planowane_dziennie * $dni_w_okresie * 60; // w minutach
        $faktyczny = intval($s->faktyczny_czas_suma);
        $procent = $planowane_w_okresie > 0 ? round(($faktyczny / $planowane_w_okresie) * 100, 1) : 0;

        $stats_by_kategoria[$s->kategoria] = [
            'liczba_zadan' => intval($s->liczba_zadan),
            'ukonczone' => intval($s->ukonczone),
            'faktyczny_czas' => $faktyczny,
            'planowany_czas' => intval($s->planowany_czas_suma),
            'planowane_godziny_dziennie' => $planowane_dziennie,
            'planowane_w_okresie' => $planowane_w_okresie,
            'procent_realizacji' => $procent
        ];
    }

    // Podsumowanie ogólne
    $total_faktyczny = array_sum(array_column($stats_by_kategoria, 'faktyczny_czas'));
    $total_planowany = array_sum(array_column($stats_by_kategoria, 'planowany_czas'));
    $total_zadan = array_sum(array_column($stats_by_kategoria, 'liczba_zadan'));
    $total_ukonczone = array_sum(array_column($stats_by_kategoria, 'ukonczone'));

    wp_send_json_success([
        'filter_type' => $filter_type,
        'filter_data' => $filter_data,
        'dni_w_okresie' => $dni_w_okresie,
        'dni_wszystkie' => $total_days,
        'dni_wolne' => intval($dni_wolne_count),
        'rok_id' => $rok_id,
        'okres_id' => $okres_id,
        'stats_by_kategoria' => $stats_by_kategoria,
        'total' => [
            'faktyczny_czas' => $total_faktyczny,
            'planowany_czas' => $total_planowany,
            'liczba_zadan' => $total_zadan,
            'ukonczone' => $total_ukonczone
        ],
        'planowane_godziny' => $planowane
    ]);
});

// Pobierz dni okresu z oznaczeniem wolnych
add_action('wp_ajax_zadaniomat_get_okres_days', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $filter_type = sanitize_text_field($_POST['filter_type']);
    $filter_id = intval($_POST['filter_id']);

    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $table_dni_wolne = $wpdb->prefix . 'zadaniomat_dni_wolne';

    if ($filter_type === 'rok') {
        $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_roki WHERE id = %d", $filter_id));
    } else {
        $data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_okresy WHERE id = %d", $filter_id));
    }

    if (!$data) {
        wp_send_json_error(['message' => 'Nie znaleziono okresu']);
        return;
    }

    // Pobierz dni z odwróconym statusem
    $dni_override = $wpdb->get_col($wpdb->prepare(
        "SELECT dzien FROM $table_dni_wolne WHERE dzien BETWEEN %s AND %s",
        $data->data_start, $data->data_koniec
    ));
    $dni_override_set = array_flip($dni_override);

    // Generuj listę dni
    // Logika: weekendy domyślnie wolne, dni Pn-Pt domyślnie robocze
    // Wpis w tabeli = odwrócony status
    $days = [];
    $current = new DateTime($data->data_start);
    $end = new DateTime($data->data_koniec);

    while ($current <= $end) {
        $date_str = $current->format('Y-m-d');
        $weekday = (int)$current->format('N'); // 1=Pn, 6=Sb, 7=Nd
        $is_weekend = ($weekday >= 6);
        $has_override = isset($dni_override_set[$date_str]);

        // Weekend domyślnie wolny, wpis = roboczy
        // Dzień Pn-Pt domyślnie roboczy, wpis = wolny
        $is_free = $is_weekend ? !$has_override : $has_override;

        $days[] = [
            'date' => $date_str,
            'day' => (int)$current->format('d'),
            'weekday' => $weekday,
            'is_free' => $is_free,
            'is_weekend' => $is_weekend
        ];
        $current->modify('+1 day');
    }

    wp_send_json_success(['days' => $days]);
});

// Pobierz kategorie
add_action('wp_ajax_zadaniomat_get_kategorie', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    wp_send_json_success([
        'kategorie' => zadaniomat_get_kategorie(),
        'kategorie_zadania' => zadaniomat_get_kategorie_zadania()
    ]);
});

// Zapisz kategorie
add_action('wp_ajax_zadaniomat_save_kategorie', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $kategorie = [];
    $kategorie_zadania = [];

    if (isset($_POST['kategorie']) && is_array($_POST['kategorie'])) {
        foreach ($_POST['kategorie'] as $kat) {
            $key = sanitize_key($kat['key']);
            $label = sanitize_text_field($kat['label']);
            if ($key && $label) {
                $kategorie[$key] = $label;
            }
        }
    }

    if (isset($_POST['kategorie_zadania']) && is_array($_POST['kategorie_zadania'])) {
        foreach ($_POST['kategorie_zadania'] as $kat) {
            $key = sanitize_key($kat['key']);
            $label = sanitize_text_field($kat['label']);
            if ($key && $label) {
                $kategorie_zadania[$key] = $label;
            }
        }
    }

    if (!empty($kategorie)) {
        update_option('zadaniomat_kategorie', $kategorie);
    }
    if (!empty($kategorie_zadania)) {
        update_option('zadaniomat_kategorie_zadania', $kategorie_zadania);
    }

    wp_send_json_success();
});

// Resetuj kategorie do domyślnych
add_action('wp_ajax_zadaniomat_reset_kategorie', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    delete_option('zadaniomat_kategorie');
    delete_option('zadaniomat_kategorie_zadania');

    wp_send_json_success([
        'kategorie' => ZADANIOMAT_DEFAULT_KATEGORIE,
        'kategorie_zadania' => ZADANIOMAT_DEFAULT_KATEGORIE_ZADANIA
    ]);
});

// =============================================
// WIELOKROTNE CELE W OKRESIE - AJAX HANDLERS
// =============================================

// Oznacz cel jako ukończony i opcjonalnie dodaj nowy
add_action('wp_ajax_zadaniomat_complete_goal', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $cel_id = intval($_POST['cel_id']);

    // Oznacz cel jako ukończony
    $wpdb->update($table, [
        'completed_at' => current_time('mysql'),
        'osiagniety' => 1
    ], ['id' => $cel_id]);

    // Przyznaj XP za osiągnięcie celu
    $gamification_result = zadaniomat_process_goal_completion($cel_id, 1);

    wp_send_json_success([
        'cel_id' => $cel_id,
        'gamification' => $gamification_result
    ]);
});

// Dodaj kolejny cel w tej samej kategorii i okresie
add_action('wp_ajax_zadaniomat_add_next_goal', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $okres_id = intval($_POST['okres_id']);
    $kategoria = sanitize_text_field($_POST['kategoria']);
    $cel = sanitize_textarea_field($_POST['cel']);

    // Znajdź maksymalną pozycję dla tej kategorii w okresie
    $max_pozycja = $wpdb->get_var($wpdb->prepare(
        "SELECT MAX(pozycja) FROM $table WHERE okres_id = %d AND kategoria = %s",
        $okres_id, $kategoria
    ));
    $new_pozycja = ($max_pozycja ?: 0) + 1;

    $wpdb->insert($table, [
        'okres_id' => $okres_id,
        'kategoria' => $kategoria,
        'cel' => $cel,
        'pozycja' => $new_pozycja
    ]);

    $cel_id = $wpdb->insert_id;

    wp_send_json_success([
        'cel_id' => $cel_id,
        'pozycja' => $new_pozycja
    ]);
});

// Pobierz wszystkie cele dla kategorii w okresie (włącznie z ukończonymi)
add_action('wp_ajax_zadaniomat_get_category_goals', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $okres_id = intval($_POST['okres_id']);
    $kategoria = sanitize_text_field($_POST['kategoria']);

    $cele = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE okres_id = %d AND kategoria = %s ORDER BY pozycja ASC",
        $okres_id, $kategoria
    ));

    $completed_count = 0;
    foreach ($cele as $cel) {
        if ($cel->completed_at) {
            $completed_count++;
        }
    }

    wp_send_json_success([
        'cele' => $cele,
        'completed_count' => $completed_count,
        'total_count' => count($cele)
    ]);
});

// Pobierz podsumowanie celów dla okresu (licznik x2, x3 itp.)
add_action('wp_ajax_zadaniomat_get_goals_summary', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $okres_id = intval($_POST['okres_id']);

    $summary = $wpdb->get_results($wpdb->prepare(
        "SELECT kategoria,
                COUNT(*) as total,
                SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed
         FROM $table
         WHERE okres_id = %d
         GROUP BY kategoria",
        $okres_id
    ));

    $result = [];
    foreach ($summary as $s) {
        $result[$s->kategoria] = [
            'total' => intval($s->total),
            'completed' => intval($s->completed)
        ];
    }

    wp_send_json_success(['summary' => $result]);
});

// Pobierz cel po ID
add_action('wp_ajax_zadaniomat_get_cel_by_id', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $cel_id = intval($_POST['cel_id']);

    $cel = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d", $cel_id
    ));

    if ($cel) {
        wp_send_json_success($cel);
    } else {
        wp_send_json_error(['message' => 'Cel nie znaleziony']);
    }
});

// Aktualizuj tekst celu
add_action('wp_ajax_zadaniomat_update_cel_text', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $cel_id = intval($_POST['cel_id']);
    $cel = sanitize_textarea_field($_POST['cel']);

    $wpdb->update($table, ['cel' => $cel], ['id' => $cel_id]);

    wp_send_json_success(['cel_id' => $cel_id, 'cel' => $cel]);
});

// Cofnij ukończenie celu (przywróć jako aktywny)
add_action('wp_ajax_zadaniomat_uncomplete_goal', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $cel_id = intval($_POST['cel_id']);

    // Pobierz cel
    $cel = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d", $cel_id
    ));

    if (!$cel) {
        wp_send_json_error(['message' => 'Cel nie znaleziony']);
        return;
    }

    // Cofnij ukończenie
    $wpdb->update($table, [
        'completed_at' => null,
        'osiagniety' => null
    ], ['id' => $cel_id]);

    wp_send_json_success([
        'cel_id' => $cel_id,
        'cel' => $cel->cel,
        'kategoria' => $cel->kategoria
    ]);
});

// Usuń cel
add_action('wp_ajax_zadaniomat_delete_cel', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_cele_okres';
    $cel_id = intval($_POST['cel_id']);

    $wpdb->delete($table, ['id' => $cel_id]);

    wp_send_json_success(['cel_id' => $cel_id]);
});

// =============================================
// DNI WOLNE - AJAX HANDLERS
// =============================================

// Toggle dnia wolnego
add_action('wp_ajax_zadaniomat_toggle_dzien_wolny', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_dni_wolne';
    $dzien = sanitize_text_field($_POST['dzien']);

    // Sprawdź dzień tygodnia
    $day_of_week = date('w', strtotime($dzien));
    $is_weekend = ($day_of_week == 0 || $day_of_week == 6);

    // Sprawdź czy dzień jest w tabeli (ma odwrócony status)
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE dzien = %s", $dzien
    ));

    if ($existing) {
        // Usuń wpis - wróć do domyślnego statusu
        $wpdb->delete($table, ['dzien' => $dzien]);
        // Po usunięciu: weekend = wolny, dzień roboczy = roboczy
        $is_wolny = $is_weekend;
    } else {
        // Dodaj wpis - odwróć domyślny status
        $wpdb->insert($table, ['dzien' => $dzien]);
        // Po dodaniu: weekend = roboczy, dzień roboczy = wolny
        $is_wolny = !$is_weekend;
    }

    wp_send_json_success(['dzien' => $dzien, 'is_wolny' => $is_wolny]);
});

// Pobierz dni wolne dla miesiąca
add_action('wp_ajax_zadaniomat_get_dni_wolne', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_dni_wolne';
    $start = sanitize_text_field($_POST['start']);
    $end = sanitize_text_field($_POST['end']);

    $dni = $wpdb->get_col($wpdb->prepare(
        "SELECT dzien FROM $table WHERE dzien BETWEEN %s AND %s",
        $start, $end
    ));

    wp_send_json_success(['dni_wolne' => $dni]);
});

// Sprawdź czy dzień jest roboczy
add_action('wp_ajax_zadaniomat_is_dzien_roboczy', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_dni_wolne';
    $dzien = sanitize_text_field($_POST['dzien']);

    // Sprawdź dzień tygodnia (0 = niedziela, 6 = sobota)
    $day_of_week = date('w', strtotime($dzien));
    $is_weekend = ($day_of_week == 0 || $day_of_week == 6);

    // Sprawdź czy jest w tabeli dni wolnych
    $is_marked_wolny = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE dzien = %s", $dzien
    )) > 0;

    // Dzień jest roboczy jeśli: (pn-pt i nie oznaczony jako wolny) LUB (weekend ale NIE oznaczony jako wolny przez użytkownika)
    // Logika: pn-pt domyślnie robocze, sobota-niedziela domyślnie wolne
    // Jeśli jest w tabeli dni_wolne to jest ODWROTNIE niż domyślnie
    $is_roboczy = false;
    if ($is_weekend) {
        // Weekend - domyślnie wolny, ale jeśli jest w tabeli to jest roboczy
        $is_roboczy = $is_marked_wolny;
    } else {
        // Pn-Pt - domyślnie roboczy, ale jeśli jest w tabeli to jest wolny
        $is_roboczy = !$is_marked_wolny;
    }

    wp_send_json_success([
        'dzien' => $dzien,
        'is_roboczy' => $is_roboczy,
        'is_weekend' => $is_weekend,
        'is_marked' => $is_marked_wolny
    ]);
});

// =============================================
// SKRÓTY KATEGORII - AJAX HANDLERS
// =============================================

// Pobierz skróty kategorii
add_action('wp_ajax_zadaniomat_get_skroty', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $skroty = get_option('zadaniomat_skroty_kategorii', []);

    wp_send_json_success(['skroty' => $skroty]);
});

// Zapisz skróty kategorii
add_action('wp_ajax_zadaniomat_save_skroty', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $skroty = [];
    if (isset($_POST['skroty']) && is_array($_POST['skroty'])) {
        foreach ($_POST['skroty'] as $kat => $skrot) {
            $key = sanitize_key($kat);
            $value = sanitize_text_field($skrot);
            if ($key) {
                $skroty[$key] = $value;
            }
        }
    }

    update_option('zadaniomat_skroty_kategorii', $skroty);

    wp_send_json_success();
});

// =============================================
// NIEOZNACZONE CELE - AJAX HANDLERS
// =============================================

// Pobierz nieoznaczone cele z zakończonych okresów
add_action('wp_ajax_zadaniomat_get_unmarked_goals', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table_cele = $wpdb->prefix . 'zadaniomat_cele_okres';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';

    // Znajdź cele z zakończonych okresów, które nie mają ustawionego osiagniety
    $unmarked = $wpdb->get_results(
        "SELECT c.*, o.nazwa as okres_nazwa, o.data_start, o.data_koniec
         FROM $table_cele c
         JOIN $table_okresy o ON c.okres_id = o.id
         WHERE o.data_koniec < CURDATE()
         AND c.osiagniety IS NULL
         AND c.cel IS NOT NULL AND c.cel != ''
         ORDER BY o.data_koniec DESC, c.kategoria"
    );

    foreach ($unmarked as &$cel) {
        $cel->kategoria_label = zadaniomat_get_kategoria_label($cel->kategoria);
    }

    wp_send_json_success(['unmarked' => $unmarked]);
});

// =============================================
// GAMIFICATION - AJAX HANDLERS
// =============================================

// Pobierz dane gamifikacji
add_action('wp_ajax_zadaniomat_get_gamification', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    $data = zadaniomat_get_gamification_data(1);
    wp_send_json_success($data);
});

// Oznacz odznaki jako wyświetlone
add_action('wp_ajax_zadaniomat_mark_achievements_notified', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    $keys = isset($_POST['keys']) ? array_map('sanitize_text_field', $_POST['keys']) : null;
    zadaniomat_mark_achievements_notified(1, $keys);
    wp_send_json_success();
});

// Wykonaj prestige
add_action('wp_ajax_zadaniomat_do_prestige', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    $result = zadaniomat_do_prestige(1);
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
});

// Przetwórz bonusy końca dnia (wywoływane przy pierwszym wejściu następnego dnia)
add_action('wp_ajax_zadaniomat_process_end_of_day', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d', strtotime('-1 day')));
    $result = zadaniomat_process_end_of_day(1, $date);
    wp_send_json_success($result);
});

// Pobierz historię XP
add_action('wp_ajax_zadaniomat_get_xp_history', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_xp_log';
    $limit = intval($_POST['limit'] ?? 50);
    $offset = intval($_POST['offset'] ?? 0);

    $history = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = 1 ORDER BY earned_at DESC LIMIT %d OFFSET %d",
        $limit, $offset
    ));

    wp_send_json_success(['history' => $history]);
});

// Pobierz wszystkie zdobyte odznaki
add_action('wp_ajax_zadaniomat_get_achievements', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_achievements';
    $earned = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table WHERE user_id = %d ORDER BY earned_at DESC", 1
    ));

    $result = [];
    foreach ($earned as $a) {
        $data = ZADANIOMAT_ACHIEVEMENTS[$a->achievement_key] ?? null;
        if ($data) {
            $result[] = [
                'key' => $a->achievement_key,
                'name' => $data['name'],
                'icon' => $data['icon'],
                'desc' => $data['desc'],
                'xp' => $data['xp'],
                'earned_at' => $a->earned_at
            ];
        }
    }

    // Dodaj nieodblokowane odznaki
    $all_achievements = ZADANIOMAT_ACHIEVEMENTS;
    $earned_keys = array_column($earned, 'achievement_key');
    $locked = [];
    foreach ($all_achievements as $key => $data) {
        if (!in_array($key, $earned_keys)) {
            $locked[] = [
                'key' => $key,
                'name' => $data['name'],
                'icon' => $data['icon'],
                'desc' => $data['desc'],
                'xp' => $data['xp'],
                'earned_at' => null
            ];
        }
    }

    wp_send_json_success([
        'earned' => $result,
        'locked' => $locked
    ]);
});

// =============================================
// ABSTRACT GOALS AJAX HANDLERS
// =============================================

// Helper function to ensure abstract goals table exists
function zadaniomat_ensure_abstract_goals_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'zadaniomat_abstract_goals';

    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL DEFAULT 1,
            nazwa VARCHAR(255) NOT NULL,
            opis TEXT,
            xp_reward INT NOT NULL DEFAULT 100,
            completed TINYINT(1) NOT NULL DEFAULT 0,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            aktywne TINYINT(1) NOT NULL DEFAULT 1
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    return $table;
}

// Pobierz listę abstrakcyjnych celów
add_action('wp_ajax_zadaniomat_get_abstract_goals', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = zadaniomat_ensure_abstract_goals_table();
    $include_completed = !empty($_POST['include_completed']);

    if ($include_completed) {
        // For settings page - show all goals
        $goals = $wpdb->get_results(
            "SELECT * FROM $table WHERE user_id = 1 AND aktywne = 1 ORDER BY completed ASC, created_at DESC"
        );
    } else {
        // For dashboard - show only active (not completed) goals
        $goals = $wpdb->get_results(
            "SELECT * FROM $table WHERE user_id = 1 AND aktywne = 1 AND completed = 0 ORDER BY created_at DESC"
        );
    }

    wp_send_json_success(['goals' => $goals ?: []]);
});

// Dodaj abstrakcyjny cel
add_action('wp_ajax_zadaniomat_add_abstract_goal', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = zadaniomat_ensure_abstract_goals_table();
    $nazwa = sanitize_text_field($_POST['nazwa'] ?? '');
    $opis = sanitize_textarea_field($_POST['opis'] ?? '');
    $xp_reward = intval($_POST['xp_reward'] ?? 100);

    if (empty($nazwa)) {
        wp_send_json_error('Podaj nazwę celu');
        return;
    }

    $result = $wpdb->insert($table, [
        'user_id' => 1,
        'nazwa' => $nazwa,
        'opis' => $opis,
        'xp_reward' => $xp_reward
    ]);

    if ($result === false) {
        wp_send_json_error('Błąd bazy danych: ' . $wpdb->last_error);
        return;
    }

    wp_send_json_success(['id' => $wpdb->insert_id]);
});

// Ukończ abstrakcyjny cel
add_action('wp_ajax_zadaniomat_complete_abstract_goal', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = zadaniomat_ensure_abstract_goals_table();
    $id = intval($_POST['id'] ?? 0);

    if (!$id) {
        wp_send_json_error('Brak ID celu');
        return;
    }

    $goal = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));

    if (!$goal || $goal->completed) {
        wp_send_json_error('Cel nie istnieje lub już ukończony');
        return;
    }

    // Oznacz jako ukończony
    $wpdb->update($table, [
        'completed' => 1,
        'completed_at' => current_time('mysql')
    ], ['id' => $id]);

    // Dodaj XP
    $xp_result = zadaniomat_add_xp(1, $goal->xp_reward, 'abstract_goal', "Cel abstrakcyjny: " . $goal->nazwa);

    wp_send_json_success([
        'xp_earned' => $xp_result['xp_added'],
        'total_xp' => $xp_result['total_xp'],
        'level_up' => $xp_result['level_up'],
        'level_info' => $xp_result['level_info']
    ]);
});

// Usuń abstrakcyjny cel
add_action('wp_ajax_zadaniomat_delete_abstract_goal', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = zadaniomat_ensure_abstract_goals_table();
    $id = intval($_POST['id'] ?? 0);

    if (!$id) {
        wp_send_json_error('Brak ID celu');
        return;
    }

    $wpdb->delete($table, ['id' => $id]);

    wp_send_json_success();
});

// Edytuj abstrakcyjny cel
add_action('wp_ajax_zadaniomat_edit_abstract_goal', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = zadaniomat_ensure_abstract_goals_table();
    $id = intval($_POST['id'] ?? 0);
    $nazwa = sanitize_text_field($_POST['nazwa'] ?? '');
    $opis = sanitize_textarea_field($_POST['opis'] ?? '');
    $xp_reward = intval($_POST['xp_reward'] ?? 100);

    if (!$id || empty($nazwa)) {
        wp_send_json_error('Brak wymaganych danych');
        return;
    }

    $wpdb->update($table, [
        'nazwa' => $nazwa,
        'opis' => $opis,
        'xp_reward' => $xp_reward
    ], ['id' => $id]);

    wp_send_json_success();
});

// =============================================
// GAMIFICATION SETTINGS AJAX HANDLERS
// =============================================

// Pobierz historię XP z filtrowaniem i paginacją (zaawansowana wersja)
add_action('wp_ajax_zadaniomat_get_xp_history_advanced', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_xp_log';
    $page = max(1, intval($_POST['page'] ?? 1));
    $per_page = min(100, max(10, intval($_POST['per_page'] ?? 50)));
    $offset = ($page - 1) * $per_page;
    $filter_type = sanitize_text_field($_POST['filter_type'] ?? '');
    $filter_date = sanitize_text_field($_POST['filter_date'] ?? '');

    $where = "user_id = 1";
    $params = [];

    if ($filter_type) {
        $where .= " AND xp_type LIKE %s";
        $params[] = '%' . $wpdb->esc_like($filter_type) . '%';
    }
    if ($filter_date) {
        $where .= " AND DATE(earned_at) = %s";
        $params[] = $filter_date;
    }

    // Get total count
    if (empty($params)) {
        $total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE $where");
        $total_xp = $wpdb->get_var("SELECT SUM(ROUND(xp_amount * multiplier)) FROM $table WHERE $where") ?: 0;
    } else {
        $total_count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where", $params));
        $total_xp = $wpdb->get_var($wpdb->prepare("SELECT SUM(ROUND(xp_amount * multiplier)) FROM $table WHERE $where", $params)) ?: 0;
    }

    // Get paginated entries
    $params[] = $per_page;
    $params[] = $offset;

    if (count($params) > 2) {
        $sql = "SELECT *, ROUND(xp_amount * multiplier) as xp_final FROM $table WHERE $where ORDER BY earned_at DESC LIMIT %d OFFSET %d";
        $entries = $wpdb->get_results($wpdb->prepare($sql, $params));
    } else {
        $entries = $wpdb->get_results($wpdb->prepare(
            "SELECT *, ROUND(xp_amount * multiplier) as xp_final FROM $table WHERE $where ORDER BY earned_at DESC LIMIT %d OFFSET %d",
            $per_page, $offset
        ));
    }

    wp_send_json_success([
        'entries' => $entries,
        'total_count' => intval($total_count),
        'total_xp' => intval($total_xp),
        'page' => $page,
        'per_page' => $per_page
    ]);
});

// Usuń wpis XP i odejmij punkty
add_action('wp_ajax_zadaniomat_delete_xp_entry', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $entry_id = intval($_POST['entry_id'] ?? 0);
    if (!$entry_id) {
        wp_send_json_error('Brak ID wpisu');
        return;
    }

    $xp_log_table = $wpdb->prefix . 'zadaniomat_xp_log';
    $stats_table = $wpdb->prefix . 'zadaniomat_gamification_stats';

    // Get the entry to find XP amount (calculate xp_final from xp_amount * multiplier)
    $entry = $wpdb->get_row($wpdb->prepare(
        "SELECT *, ROUND(xp_amount * multiplier) as xp_final FROM $xp_log_table WHERE id = %d",
        $entry_id
    ));
    if (!$entry) {
        wp_send_json_error('Wpis nie istnieje');
        return;
    }

    $xp_to_deduct = $entry->xp_final ?: $entry->xp_amount;

    // Deduct XP from total
    $wpdb->query($wpdb->prepare(
        "UPDATE $stats_table SET total_xp = GREATEST(0, total_xp - %d) WHERE user_id = %d",
        $xp_to_deduct, $entry->user_id
    ));

    // Delete the entry
    $wpdb->delete($xp_log_table, ['id' => $entry_id]);

    // Recalculate level
    $stats = $wpdb->get_row($wpdb->prepare("SELECT total_xp FROM $stats_table WHERE user_id = %d", $entry->user_id));
    if ($stats) {
        $new_level = zadaniomat_calculate_level($stats->total_xp);
        $wpdb->update($stats_table, ['level' => $new_level], ['user_id' => $entry->user_id]);
    }

    wp_send_json_success(['deleted_xp' => $xp_to_deduct]);
});

// Zapisz konfigurację gamifikacji
add_action('wp_ajax_zadaniomat_save_gam_config', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $config_key = sanitize_text_field($_POST['config_key'] ?? '');
    $current_config = get_option('zadaniomat_gam_config', []);

    switch ($config_key) {
        case 'levels':
            $levels = json_decode(stripslashes($_POST['config_value'] ?? '{}'), true);
            if ($levels) {
                $current_config['levels'] = $levels;
            }
            break;

        case 'xp_values':
            $xp_values = json_decode(stripslashes($_POST['config_value'] ?? '{}'), true);
            if ($xp_values) {
                $current_config['xp_values'] = $xp_values;
            }
            break;

        case 'multipliers':
            $streak_mults = json_decode(stripslashes($_POST['streak_multipliers'] ?? '[]'), true);
            $combo_mults = json_decode(stripslashes($_POST['combo_multipliers'] ?? '[]'), true);
            if ($streak_mults) {
                $current_config['streak_multipliers'] = $streak_mults;
            }
            if ($combo_mults) {
                $current_config['combo_multipliers'] = $combo_mults;
            }
            break;

        case 'other':
            $time_settings = json_decode(stripslashes($_POST['time_settings'] ?? '{}'), true);
            $streak_conditions = json_decode(stripslashes($_POST['streak_conditions'] ?? '{}'), true);
            $require_confirmation = !empty($_POST['require_xp_confirmation']);

            if ($time_settings) {
                $current_config['time_settings'] = $time_settings;
            }
            if ($streak_conditions) {
                $current_config['streak_conditions'] = $streak_conditions;
            }
            $current_config['require_xp_confirmation'] = $require_confirmation;
            break;

        default:
            $config_value = json_decode(stripslashes($_POST['config_value'] ?? '{}'), true);
            if ($config_value && $config_key) {
                $current_config[$config_key] = $config_value;
            }
    }

    update_option('zadaniomat_gam_config', $current_config);
    wp_send_json_success(['saved' => $config_key]);
});

// Resetuj konfigurację gamifikacji
add_action('wp_ajax_zadaniomat_reset_gam_config', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $config_key = sanitize_text_field($_POST['config_key'] ?? '');
    $defaults = zadaniomat_get_default_gam_config();
    $current_config = get_option('zadaniomat_gam_config', []);

    switch ($config_key) {
        case 'levels':
            $current_config['levels'] = $defaults['levels'];
            break;
        case 'xp_values':
            $current_config['xp_values'] = $defaults['xp_values'];
            break;
        case 'multipliers':
            $current_config['streak_multipliers'] = $defaults['streak_multipliers'];
            $current_config['combo_multipliers'] = $defaults['combo_multipliers'];
            break;
        case 'other':
            $current_config['time_settings'] = $defaults['time_settings'];
            $current_config['streak_conditions'] = $defaults['streak_conditions'];
            $current_config['require_xp_confirmation'] = $defaults['require_xp_confirmation'];
            break;
        default:
            // Reset all
            delete_option('zadaniomat_gam_config');
            wp_send_json_success(['reset' => 'all']);
            return;
    }

    update_option('zadaniomat_gam_config', $current_config);
    wp_send_json_success(['reset' => $config_key]);
});

// Zapisz konfigurację wyzwań
add_action('wp_ajax_zadaniomat_save_challenges_config', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $challenges = json_decode(stripslashes($_POST['challenges'] ?? '{}'), true);
    if ($challenges) {
        // Merge with existing challenges to preserve params
        $existing = zadaniomat_get_daily_challenges_config();
        foreach ($challenges as $key => $new_data) {
            if (isset($existing[$key])) {
                // Preserve original params but update editable fields
                $existing[$key]['desc'] = $new_data['desc'] ?? $existing[$key]['desc'];
                $existing[$key]['xp'] = $new_data['xp'] ?? $existing[$key]['xp'];
                $existing[$key]['difficulty'] = $new_data['difficulty'] ?? $existing[$key]['difficulty'];
                $existing[$key]['condition'] = $new_data['condition'] ?? '';
            }
        }
        update_option('zadaniomat_challenges_config', $existing);
        wp_send_json_success();
    } else {
        wp_send_json_error('Invalid data');
    }
});

// Resetuj konfigurację wyzwań
add_action('wp_ajax_zadaniomat_reset_challenges_config', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    delete_option('zadaniomat_challenges_config');
    wp_send_json_success();
});

// Zapisz konfigurację osiągnięć
add_action('wp_ajax_zadaniomat_save_achievements_config', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $achievements = json_decode(stripslashes($_POST['achievements'] ?? '{}'), true);
    if ($achievements) {
        update_option('zadaniomat_achievements_config', $achievements);
        wp_send_json_success();
    } else {
        wp_send_json_error('Invalid data');
    }
});

// Resetuj konfigurację osiągnięć
add_action('wp_ajax_zadaniomat_reset_achievements_config', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    delete_option('zadaniomat_achievements_config');
    wp_send_json_success();
});

// ULTIMATE RESET - wyzeruj całą gamifikację
add_action('wp_ajax_zadaniomat_ultimate_reset', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    // Wyczyść tabele gamifikacji
    $tables = [
        $wpdb->prefix . 'zadaniomat_gamification_stats',
        $wpdb->prefix . 'zadaniomat_streaks',
        $wpdb->prefix . 'zadaniomat_xp_log',
        $wpdb->prefix . 'zadaniomat_achievements',
        $wpdb->prefix . 'zadaniomat_daily_challenges',
        $wpdb->prefix . 'zadaniomat_combo_state'
    ];

    foreach ($tables as $table) {
        $wpdb->query("TRUNCATE TABLE $table");
    }

    // Zresetuj opcje
    delete_option('zadaniomat_morning_checklist');
    delete_option('zadaniomat_challenges_config');
    delete_option('zadaniomat_achievements_config');

    // Utwórz nowy rekord statystyk dla użytkownika
    $stats_table = $wpdb->prefix . 'zadaniomat_gamification_stats';
    $wpdb->insert($stats_table, [
        'user_id' => 1,
        'total_xp' => 0,
        'current_level' => 1,
        'prestige' => 0,
        'freeze_days_available' => 3,
        'freeze_days_used' => 0
    ]);

    wp_send_json_success(['message' => 'Gamifikacja zresetowana!']);
});

// Pobierz stan listy porannej
add_action('wp_ajax_zadaniomat_get_morning_checklist', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
    $data = get_option('zadaniomat_morning_checklist', []);

    $checked = isset($data[$date]) && $data[$date]['checked'];
    $time = isset($data[$date]) ? $data[$date]['time'] : '';

    wp_send_json_success([
        'checked' => $checked,
        'time' => $time
    ]);
});

// Ustaw stan listy porannej
add_action('wp_ajax_zadaniomat_set_morning_checklist', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');
    $date = sanitize_text_field($_POST['date'] ?? date('Y-m-d'));
    $checked = !empty($_POST['checked']);

    $data = get_option('zadaniomat_morning_checklist', []);

    if ($checked) {
        $time = date('H:i');
        $data[$date] = [
            'checked' => true,
            'time' => $time
        ];

        // Award XP if before deadline
        $config = zadaniomat_get_gam_config();
        $deadline = $config['time_settings']['early_planning_before'] ?? '10:00';
        if ($time <= $deadline) {
            // Check if early planning bonus already awarded today
            global $wpdb;
            $xp_log_table = $wpdb->prefix . 'zadaniomat_xp_log';
            $already_awarded = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $xp_log_table WHERE user_id = 1 AND xp_type = 'early_planning' AND DATE(earned_at) = %s",
                $date
            ));

            if (!$already_awarded) {
                $xp = $config['xp_values']['early_planning_bonus'] ?? 15;
                zadaniomat_add_xp(1, $xp, 'early_planning', 'Lista poranna przed ' . $deadline);
            }
        }
    } else {
        unset($data[$date]);
        $time = '';
    }

    update_option('zadaniomat_morning_checklist', $data);

    // Check daily challenges after morning checklist is updated
    zadaniomat_check_daily_challenges(1, $date);

    wp_send_json_success([
        'checked' => $checked,
        'time' => $time
    ]);
});

// =============================================
// PUBLICZNA STRONA - AJAX HANDLERS
// =============================================

// Pobierz dane dla publicznej strony (bez wymogu logowania)
add_action('wp_ajax_zadaniomat_public_get_data', 'zadaniomat_public_get_data_handler');
add_action('wp_ajax_nopriv_zadaniomat_public_get_data', 'zadaniomat_public_get_data_handler');

function zadaniomat_public_get_data_handler() {
    global $wpdb;

    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $table_cele_rok = $wpdb->prefix . 'zadaniomat_cele_rok';
    $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
    $table_dni_wolne = $wpdb->prefix . 'zadaniomat_dni_wolne';

    $filter_type = sanitize_text_field($_POST['filter_type'] ?? ''); // 'rok', 'okres', 'dzien'
    $filter_id = intval($_POST['filter_id'] ?? 0);
    $filter_date = sanitize_text_field($_POST['filter_date'] ?? '');

    $result = [
        'roki' => [],
        'okresy' => [],
        'stats' => null,
        'cele' => [],
        'day_stats' => null
    ];

    // Pobierz wszystkie roki i okresy
    $result['roki'] = $wpdb->get_results("SELECT * FROM $table_roki ORDER BY data_start DESC");
    $result['okresy'] = $wpdb->get_results("SELECT * FROM $table_okresy ORDER BY data_start DESC");

    // Pobierz skróty kategorii
    $skroty = get_option('zadaniomat_skroty_kategorii', []);
    $result['skroty'] = $skroty;
    $result['kategorie'] = zadaniomat_get_kategorie_zadania();

    // Jeśli mamy filtr - pobierz statystyki
    if ($filter_type === 'rok' && $filter_id) {
        $rok = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_roki WHERE id = %d", $filter_id));
        if ($rok) {
            $result['stats'] = zadaniomat_calculate_public_stats($rok->data_start, $rok->data_koniec, $filter_id, 'rok');
            $result['cele'] = zadaniomat_get_public_goals($filter_id, 'rok');
        }
    } elseif ($filter_type === 'okres' && $filter_id) {
        $okres = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_okresy WHERE id = %d", $filter_id));
        if ($okres) {
            $result['stats'] = zadaniomat_calculate_public_stats($okres->data_start, $okres->data_koniec, $okres->rok_id, 'okres');
            $result['cele'] = zadaniomat_get_public_goals($filter_id, 'okres');
        }
    }

    // Statystyki dla konkretnego dnia
    if ($filter_date) {
        $result['day_stats'] = zadaniomat_get_day_stats($filter_date);
    }

    wp_send_json_success($result);
}

// Helper - oblicz statystyki publiczne
function zadaniomat_calculate_public_stats($start_date, $end_date, $rok_id, $type) {
    global $wpdb;

    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $table_cele_rok = $wpdb->prefix . 'zadaniomat_cele_rok';
    $table_dni_wolne = $wpdb->prefix . 'zadaniomat_dni_wolne';

    // Policz dni
    $date1 = new DateTime($start_date);
    $date2 = new DateTime($end_date);
    $dni_w_okresie = $date2->diff($date1)->days + 1;

    // Policz dni robocze
    $dni_robocze = 0;
    $current = clone $date1;
    while ($current <= $date2) {
        $day_of_week = $current->format('w');
        $is_weekend = ($day_of_week == 0 || $day_of_week == 6);
        $date_str = $current->format('Y-m-d');

        $is_marked = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_dni_wolne WHERE dzien = %s", $date_str
        )) > 0;

        if ($is_weekend) {
            if ($is_marked) $dni_robocze++; // Weekend oznaczony jako roboczy
        } else {
            if (!$is_marked) $dni_robocze++; // Dzień roboczy nie oznaczony jako wolny
        }

        $current->modify('+1 day');
    }

    // Podstawowe statystyki
    $stats = $wpdb->get_row($wpdb->prepare(
        "SELECT
            COUNT(*) as liczba_zadan,
            SUM(COALESCE(faktyczny_czas, 0)) as faktyczny_czas_suma,
            SUM(CASE WHEN status = 'zakonczone' THEN 1 ELSE 0 END) as ukonczone
         FROM $table_zadania
         WHERE dzien BETWEEN %s AND %s",
        $start_date, $end_date
    ));

    // Średnia godzina rozpoczęcia pracy (tylko dni robocze)
    $start_times = $wpdb->get_col($wpdb->prepare(
        "SELECT MIN(godzina_start)
         FROM $table_zadania
         WHERE dzien BETWEEN %s AND %s
         AND godzina_start IS NOT NULL
         GROUP BY dzien",
        $start_date, $end_date
    ));

    $avg_start = null;
    if (!empty($start_times)) {
        $total_minutes = 0;
        $count = 0;
        foreach ($start_times as $time) {
            if ($time) {
                list($h, $m, $s) = explode(':', $time);
                $total_minutes += $h * 60 + $m;
                $count++;
            }
        }
        if ($count > 0) {
            $avg_minutes = round($total_minutes / $count);
            $avg_start = sprintf('%02d:%02d', floor($avg_minutes / 60), $avg_minutes % 60);
        }
    }

    // Statystyki per kategoria
    $stats_by_kat = $wpdb->get_results($wpdb->prepare(
        "SELECT
            kategoria,
            COUNT(*) as liczba_zadan,
            SUM(COALESCE(faktyczny_czas, 0)) as faktyczny_czas
         FROM $table_zadania
         WHERE dzien BETWEEN %s AND %s
         GROUP BY kategoria",
        $start_date, $end_date
    ));

    $by_kategoria = [];
    foreach ($stats_by_kat as $s) {
        $by_kategoria[$s->kategoria] = [
            'liczba_zadan' => intval($s->liczba_zadan),
            'faktyczny_czas' => intval($s->faktyczny_czas)
        ];
    }

    // Progres (procent ukończonych)
    $progress = $stats->liczba_zadan > 0
        ? round(($stats->ukonczone / $stats->liczba_zadan) * 100, 1)
        : 0;

    return [
        'dni_w_okresie' => $dni_w_okresie,
        'dni_robocze' => $dni_robocze,
        'liczba_zadan' => intval($stats->liczba_zadan),
        'ukonczone' => intval($stats->ukonczone),
        'faktyczny_czas' => intval($stats->faktyczny_czas_suma),
        'progress' => $progress,
        'avg_start_time' => $avg_start,
        'by_kategoria' => $by_kategoria
    ];
}

// Helper - pobierz cele dla publicznej strony
function zadaniomat_get_public_goals($filter_id, $type) {
    global $wpdb;

    $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
    $table_cele_rok = $wpdb->prefix . 'zadaniomat_cele_rok';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';

    $cele = [];

    if ($type === 'rok') {
        // Cele strategiczne dla roku
        $cele_rok = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_cele_rok WHERE rok_id = %d", $filter_id
        ));
        foreach ($cele_rok as $c) {
            $c->kategoria_label = zadaniomat_get_kategoria_label($c->kategoria);
            $c->type = 'rok';
        }
        $cele['rok'] = $cele_rok;

        // Cele okresowe dla wszystkich okresów w tym roku
        $okresy = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_okresy WHERE rok_id = %d ORDER BY data_start", $filter_id
        ));

        foreach ($okresy as $okres) {
            $cele_okres = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_cele_okres WHERE okres_id = %d ORDER BY kategoria, pozycja", $okres->id
            ));
            foreach ($cele_okres as $c) {
                $c->kategoria_label = zadaniomat_get_kategoria_label($c->kategoria);
                $c->okres_nazwa = $okres->nazwa;
            }
            $cele['okresy'][$okres->id] = [
                'okres' => $okres,
                'cele' => $cele_okres
            ];
        }
    } else {
        // Cele dla konkretnego okresu
        $cele_okres = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_cele_okres WHERE okres_id = %d ORDER BY kategoria, pozycja", $filter_id
        ));
        foreach ($cele_okres as $c) {
            $c->kategoria_label = zadaniomat_get_kategoria_label($c->kategoria);
        }
        $cele['okres'] = $cele_okres;
    }

    return $cele;
}

// Helper - statystyki dla konkretnego dnia
function zadaniomat_get_day_stats($date) {
    global $wpdb;

    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $skroty = get_option('zadaniomat_skroty_kategorii', []);

    $stats = $wpdb->get_results($wpdb->prepare(
        "SELECT
            kategoria,
            COUNT(*) as liczba_zadan,
            SUM(COALESCE(faktyczny_czas, 0)) as faktyczny_czas
         FROM $table_zadania
         WHERE dzien = %s
         GROUP BY kategoria",
        $date
    ));

    $result = [];
    foreach ($stats as $s) {
        $result[$s->kategoria] = [
            'liczba_zadan' => intval($s->liczba_zadan),
            'faktyczny_czas' => intval($s->faktyczny_czas),
            'skrot' => $skroty[$s->kategoria] ?? '',
            'kategoria_label' => zadaniomat_get_kategoria_label($s->kategoria)
        ];
    }

    // Godzina rozpoczęcia pracy
    $start_time = $wpdb->get_var($wpdb->prepare(
        "SELECT MIN(godzina_start) FROM $table_zadania WHERE dzien = %s AND godzina_start IS NOT NULL",
        $date
    ));

    return [
        'date' => $date,
        'by_kategoria' => $result,
        'start_time' => $start_time
    ];
}

// =============================================
// HARMONOGRAM DNIA - AJAX HANDLERS
// =============================================

// Pobierz stałe zadania
add_action('wp_ajax_zadaniomat_get_stale_zadania', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $stale = $wpdb->get_results("SELECT * FROM $table ORDER BY godzina_start ASC, id ASC");

    foreach ($stale as &$s) {
        $s->kategoria_label = zadaniomat_get_kategoria_label($s->kategoria);
    }

    wp_send_json_success(['stale_zadania' => $stale]);
});

// Dodaj stałe zadanie (template) i generuj zadania na rok
add_action('wp_ajax_zadaniomat_add_stale_zadanie', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';

    $wpdb->insert($table, [
        'nazwa' => sanitize_text_field($_POST['nazwa']),
        'kategoria' => sanitize_text_field($_POST['kategoria']),
        'cel_todo' => sanitize_textarea_field($_POST['cel_todo'] ?? ''),
        'planowany_czas' => intval($_POST['planowany_czas']),
        'typ_powtarzania' => sanitize_text_field($_POST['typ_powtarzania']),
        'dni_tygodnia' => sanitize_text_field($_POST['dni_tygodnia'] ?? ''),
        'dzien_miesiaca' => !empty($_POST['dzien_miesiaca']) ? intval($_POST['dzien_miesiaca']) : null,
        'dni_przed_koncem_roku' => !empty($_POST['dni_przed_koncem_roku']) ? intval($_POST['dni_przed_koncem_roku']) : null,
        'dni_przed_koncem_okresu' => !empty($_POST['dni_przed_koncem_okresu']) ? intval($_POST['dni_przed_koncem_okresu']) : null,
        'minuty_po_starcie' => null,
        'dodaj_do_listy' => 1, // Zawsze dodawane do listy jako normalne zadania
        'godzina_start' => !empty($_POST['godzina_start']) ? sanitize_text_field($_POST['godzina_start']) : null,
        'godzina_koniec' => !empty($_POST['godzina_koniec']) ? sanitize_text_field($_POST['godzina_koniec']) : null,
        'aktywne' => 1
    ]);

    $id = $wpdb->insert_id;

    // Generuj zadania na aktualny rok (90 dni)
    $created_tasks = zadaniomat_generate_recurring_tasks($id);

    $zadanie = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    $zadanie->kategoria_label = zadaniomat_get_kategoria_label($zadanie->kategoria);

    wp_send_json_success([
        'zadanie' => $zadanie,
        'generated_count' => count($created_tasks)
    ]);
});

// Edytuj stałe zadanie (template) i regeneruj przyszłe zadania
add_action('wp_ajax_zadaniomat_edit_stale_zadanie', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $id = intval($_POST['id']);

    $wpdb->update($table, [
        'nazwa' => sanitize_text_field($_POST['nazwa']),
        'kategoria' => sanitize_text_field($_POST['kategoria']),
        'cel_todo' => sanitize_textarea_field($_POST['cel_todo'] ?? ''),
        'planowany_czas' => intval($_POST['planowany_czas']),
        'typ_powtarzania' => sanitize_text_field($_POST['typ_powtarzania']),
        'dni_tygodnia' => sanitize_text_field($_POST['dni_tygodnia'] ?? ''),
        'dzien_miesiaca' => !empty($_POST['dzien_miesiaca']) ? intval($_POST['dzien_miesiaca']) : null,
        'dni_przed_koncem_roku' => !empty($_POST['dni_przed_koncem_roku']) ? intval($_POST['dni_przed_koncem_roku']) : null,
        'dni_przed_koncem_okresu' => !empty($_POST['dni_przed_koncem_okresu']) ? intval($_POST['dni_przed_koncem_okresu']) : null,
        'minuty_po_starcie' => null,
        'dodaj_do_listy' => 1,
        'godzina_start' => !empty($_POST['godzina_start']) ? sanitize_text_field($_POST['godzina_start']) : null,
        'godzina_koniec' => !empty($_POST['godzina_koniec']) ? sanitize_text_field($_POST['godzina_koniec']) : null
    ], ['id' => $id]);

    // Regeneruj przyszłe zadania z nowego template'a
    $result = zadaniomat_regenerate_recurring_tasks($id);

    $zadanie = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
    $zadanie->kategoria_label = zadaniomat_get_kategoria_label($zadanie->kategoria);

    wp_send_json_success([
        'zadanie' => $zadanie,
        'deleted_count' => $result['deleted'],
        'generated_count' => count($result['created'])
    ]);
});

// Usuń stałe zadanie (template) z opcją usunięcia przyszłych zadań
add_action('wp_ajax_zadaniomat_delete_stale_zadanie', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $id = intval($_POST['id']);
    $delete_future = !empty($_POST['delete_future']); // Czy usunąć przyszłe zadania

    $deleted_tasks = 0;
    if ($delete_future) {
        // Usuń przyszłe zadania wygenerowane z tego template'a
        $deleted_tasks = zadaniomat_delete_future_recurring_tasks($id, true);
    } else {
        // Tylko odłącz przyszłe zadania od template'a (zachowaj je jako zwykłe zadania)
        $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
        $wpdb->query($wpdb->prepare(
            "UPDATE $table_zadania SET recurring_template_id = NULL WHERE recurring_template_id = %d",
            $id
        ));
    }

    // Usuń template
    $wpdb->delete($table, ['id' => $id]);

    wp_send_json_success(['deleted_tasks' => $deleted_tasks]);
});

// Pobierz liczbę przyszłych zadań dla template'a (do dialogu usuwania)
add_action('wp_ajax_zadaniomat_count_future_tasks', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $template_id = intval($_POST['template_id']);
    $today = date('Y-m-d');

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_zadania WHERE recurring_template_id = %d AND dzien >= %s AND status IN ('nowe', 'w_trakcie')",
        $template_id, $today
    ));

    wp_send_json_success(['count' => intval($count)]);
});

// Toggle aktywność stałego zadania - regeneruj zadania przy aktywacji
add_action('wp_ajax_zadaniomat_toggle_stale_zadanie', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $id = intval($_POST['id']);
    $aktywne = intval($_POST['aktywne']);

    $wpdb->update($table, ['aktywne' => $aktywne], ['id' => $id]);

    $result = ['deleted' => 0, 'created' => []];
    if ($aktywne) {
        // Przy aktywacji - generuj zadania na rok
        $result['created'] = zadaniomat_generate_recurring_tasks($id);
    } else {
        // Przy dezaktywacji - usuń przyszłe niezrealizowane zadania
        $result['deleted'] = zadaniomat_delete_future_recurring_tasks($id);
    }

    wp_send_json_success([
        'deleted_count' => $result['deleted'],
        'generated_count' => count($result['created'])
    ]);
});

// Pobierz nadpisania godzin dla stałego zadania
add_action('wp_ajax_zadaniomat_get_stale_overrides', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $stale_id = intval($_POST['stale_id']);
    $table_overrides = $wpdb->prefix . 'zadaniomat_stale_overrides';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';

    // Pobierz wszystkie okresy z aktualnego roku i przyszłe
    $today = date('Y-m-d');
    $okresy = $wpdb->get_results($wpdb->prepare(
        "SELECT o.*, r.nazwa as rok_nazwa FROM $table_okresy o
         LEFT JOIN {$wpdb->prefix}zadaniomat_roki r ON o.rok_id = r.id
         WHERE o.data_koniec >= %s ORDER BY o.data_start ASC",
        $today
    ));

    // Pobierz nadpisania
    $overrides = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_overrides WHERE stale_zadanie_id = %d",
        $stale_id
    ));

    // Mapuj nadpisania per okres
    $overrides_map = [];
    foreach ($overrides as $o) {
        $overrides_map[$o->okres_id] = $o->godzina_start ? substr($o->godzina_start, 0, 5) : '';
    }

    wp_send_json_success([
        'okresy' => $okresy,
        'overrides' => $overrides_map
    ]);
});

// Zapisz nadpisanie godziny dla stałego zadania w okresie
add_action('wp_ajax_zadaniomat_save_stale_override', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $stale_id = intval($_POST['stale_id']);
    $okres_id = intval($_POST['okres_id']);
    $godzina_start = sanitize_text_field($_POST['godzina_start']);
    $table = $wpdb->prefix . 'zadaniomat_stale_overrides';

    if (empty($godzina_start)) {
        // Usuń nadpisanie
        $wpdb->delete($table, [
            'stale_zadanie_id' => $stale_id,
            'okres_id' => $okres_id
        ]);
    } else {
        // Upsert nadpisania
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE stale_zadanie_id = %d AND okres_id = %d",
            $stale_id, $okres_id
        ));

        if ($existing) {
            $wpdb->update($table, ['godzina_start' => $godzina_start], [
                'stale_zadanie_id' => $stale_id,
                'okres_id' => $okres_id
            ]);
        } else {
            $wpdb->insert($table, [
                'stale_zadanie_id' => $stale_id,
                'okres_id' => $okres_id,
                'godzina_start' => $godzina_start
            ]);
        }
    }

    // Regeneruj przyszłe zadania żeby zastosować nową godzinę
    $result = zadaniomat_regenerate_recurring_tasks($stale_id);

    wp_send_json_success([
        'regenerated' => count($result['created'])
    ]);
});

// Pobierz stałe zadania dla danego dnia (sprawdza typ powtarzania)
add_action('wp_ajax_zadaniomat_get_stale_for_day', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $dzien = sanitize_text_field($_POST['dzien']);
    $date = new DateTime($dzien);
    $dayOfWeek = strtolower($date->format('D')); // mon, tue, wed...
    $dayOfWeekPl = ['mon' => 'pn', 'tue' => 'wt', 'wed' => 'sr', 'thu' => 'cz', 'fri' => 'pt', 'sat' => 'so', 'sun' => 'nd'];
    $dayPl = $dayOfWeekPl[$dayOfWeek];
    $dayOfMonth = intval($date->format('j'));

    $stale = $wpdb->get_results("SELECT * FROM $table WHERE aktywne = 1 ORDER BY godzina_start ASC");

    // Pobierz aktualny rok (90-dniowy okres) i okres dla tego dnia
    $current_rok = zadaniomat_get_current_rok($dzien);
    $current_okres = zadaniomat_get_current_okres($dzien);

    // Pobierz godzinę startu dnia (dla minuty_po_starcie)
    $start_dnia = get_option('zadaniomat_start_dnia_' . $dzien, '');

    $matching = [];
    foreach ($stale as $s) {
        $match = false;

        if ($s->typ_powtarzania === 'codziennie') {
            $match = true;
        } elseif ($s->typ_powtarzania === 'dni_tygodnia' && !empty($s->dni_tygodnia)) {
            $dni = explode(',', $s->dni_tygodnia);
            $match = in_array($dayPl, $dni);
        } elseif ($s->typ_powtarzania === 'dzien_miesiaca' && $s->dzien_miesiaca) {
            $match = ($dayOfMonth === intval($s->dzien_miesiaca));
        } elseif ($s->typ_powtarzania === 'dni_przed_koncem_roku' && $s->dni_przed_koncem_roku && $current_rok) {
            $rok_koniec = new DateTime($current_rok->data_koniec);
            $diff = $date->diff($rok_koniec);
            if ($diff->invert === 0 && $diff->days === intval($s->dni_przed_koncem_roku)) {
                $match = true;
            }
        } elseif ($s->typ_powtarzania === 'dni_przed_koncem_okresu' && $s->dni_przed_koncem_okresu && $current_okres) {
            $okres_koniec = new DateTime($current_okres->data_koniec);
            $diff = $date->diff($okres_koniec);
            if ($diff->invert === 0 && $diff->days === intval($s->dni_przed_koncem_okresu)) {
                $match = true;
            }
        }

        if ($match) {
            $s->kategoria_label = zadaniomat_get_kategoria_label($s->kategoria);

            // Oblicz godzinę startu jeśli ustawiono minuty_po_starcie
            if ($s->minuty_po_starcie && $start_dnia) {
                $start_time = DateTime::createFromFormat('H:i', $start_dnia);
                if ($start_time) {
                    $start_time->modify('+' . intval($s->minuty_po_starcie) . ' minutes');
                    $s->godzina_start = $start_time->format('H:i:s');
                }
            }

            $matching[] = $s;
        }
    }

    wp_send_json_success(['stale_zadania' => $matching]);
});

// Aktualizuj godziny zadania w harmonogramie
add_action('wp_ajax_zadaniomat_update_harmonogram', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table = $wpdb->prefix . 'zadaniomat_zadania';
    $id = intval($_POST['id']);
    $godzina_start = !empty($_POST['godzina_start']) ? sanitize_text_field($_POST['godzina_start']) : null;
    $godzina_koniec = !empty($_POST['godzina_koniec']) ? sanitize_text_field($_POST['godzina_koniec']) : null;
    $pozycja = isset($_POST['pozycja']) ? intval($_POST['pozycja']) : null;

    $wpdb->update($table, [
        'godzina_start' => $godzina_start,
        'godzina_koniec' => $godzina_koniec,
        'pozycja_harmonogram' => $pozycja
    ], ['id' => $id]);

    wp_send_json_success();
});

// Pobierz zadania na dziś z harmonogramem
add_action('wp_ajax_zadaniomat_get_harmonogram', function() {
    global $wpdb;
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';
    $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';
    $dzien = sanitize_text_field($_POST['dzien']);

    // Pobierz zadania na ten dzień
    $zadania = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_zadania WHERE dzien = %s ORDER BY godzina_start ASC, pozycja_harmonogram ASC, id ASC",
        $dzien
    ));

    foreach ($zadania as &$z) {
        $z->kategoria_label = zadaniomat_get_kategoria_label($z->kategoria);
        $z->is_stale = false;
    }

    // Pobierz stałe zadania dla tego dnia
    $date = new DateTime($dzien);
    $dayOfWeek = strtolower($date->format('D'));
    $dayOfWeekPl = ['mon' => 'pn', 'tue' => 'wt', 'wed' => 'sr', 'thu' => 'cz', 'fri' => 'pt', 'sat' => 'so', 'sun' => 'nd'];
    $dayPl = $dayOfWeekPl[$dayOfWeek];
    $dayOfMonth = intval($date->format('j'));

    $stale = $wpdb->get_results("SELECT * FROM $table_stale WHERE aktywne = 1 ORDER BY godzina_start ASC");
    $stale_matching = [];

    // Pobierz aktualny rok (90-dniowy okres) i okres dla tego dnia
    $current_rok = zadaniomat_get_current_rok($dzien);
    $current_okres = zadaniomat_get_current_okres($dzien);

    // Pobierz godzinę startu dnia (dla minuty_po_starcie)
    $start_dnia = get_option('zadaniomat_start_dnia_' . $dzien, '');

    foreach ($stale as $s) {
        $match = false;

        if ($s->typ_powtarzania === 'codziennie') {
            $match = true;
        } elseif ($s->typ_powtarzania === 'dni_tygodnia' && !empty($s->dni_tygodnia)) {
            $dni = explode(',', $s->dni_tygodnia);
            $match = in_array($dayPl, $dni);
        } elseif ($s->typ_powtarzania === 'dzien_miesiaca' && $s->dzien_miesiaca) {
            $match = ($dayOfMonth === intval($s->dzien_miesiaca));
        } elseif ($s->typ_powtarzania === 'dni_przed_koncem_roku' && $s->dni_przed_koncem_roku && $current_rok) {
            // Oblicz ile dni przed końcem roku (90-dniowego okresu)
            $rok_koniec = new DateTime($current_rok->data_koniec);
            $diff = $date->diff($rok_koniec);
            if ($diff->invert === 0 && $diff->days === intval($s->dni_przed_koncem_roku)) {
                $match = true;
            }
        } elseif ($s->typ_powtarzania === 'dni_przed_koncem_okresu' && $s->dni_przed_koncem_okresu && $current_okres) {
            // Oblicz ile dni przed końcem okresu 2-tygodniowego
            $okres_koniec = new DateTime($current_okres->data_koniec);
            $diff = $date->diff($okres_koniec);
            if ($diff->invert === 0 && $diff->days === intval($s->dni_przed_koncem_okresu)) {
                $match = true;
            }
        }

        if ($match) {
            // Sprawdź czy zadanie z tego template'a już istnieje na ten dzień
            $existing_task = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_zadania WHERE recurring_template_id = %d AND dzien = %s",
                $s->id, $dzien
            ));

            // Jeśli zadanie już istnieje, nie dodawaj szablonu (zadanie jest w głównej liście)
            if ($existing_task) {
                continue;
            }

            $s->kategoria_label = zadaniomat_get_kategoria_label($s->kategoria);
            $s->is_stale = true;
            $s->zadanie = $s->nazwa;

            // Oblicz godzinę startu jeśli ustawiono minuty_po_starcie
            if ($s->minuty_po_starcie && $start_dnia) {
                $start_time = DateTime::createFromFormat('H:i', $start_dnia);
                if ($start_time) {
                    $start_time->modify('+' . intval($s->minuty_po_starcie) . ' minutes');
                    $s->godzina_start = $start_time->format('H:i:s');
                }
            }

            $stale_matching[] = $s;
        }
    }

    wp_send_json_success([
        'zadania' => $zadania,
        'stale_zadania' => $stale_matching
    ]);
});

// Zapisz godzinę startu dnia
add_action('wp_ajax_zadaniomat_save_start_dnia', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $godzina = sanitize_text_field($_POST['godzina']);
    $dzien = sanitize_text_field($_POST['dzien']);

    update_option('zadaniomat_start_dnia_' . $dzien, $godzina);

    wp_send_json_success();
});

// Pobierz godzinę startu dnia
add_action('wp_ajax_zadaniomat_get_start_dnia', function() {
    check_ajax_referer('zadaniomat_ajax', 'nonce');

    $dzien = sanitize_text_field($_POST['dzien']);
    $godzina = get_option('zadaniomat_start_dnia_' . $dzien, '');

    wp_send_json_success(['godzina' => $godzina]);
});

// =============================================
// STYLE CSS
// =============================================
add_action('admin_head', function() {
    $page = isset($_GET['page']) ? $_GET['page'] : '';
    if (strpos($page, 'zadaniomat') !== false) {
        ?>
        <style>
            .zadaniomat-wrap { max-width: 1600px; margin: 20px auto; }
            .zadaniomat-card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 20px; }
            .zadaniomat-card h2 { margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 12px; font-size: 18px; }
            
            .main-layout { display: grid; grid-template-columns: 320px 1fr; gap: 20px; }
            @media (max-width: 1200px) { .main-layout { grid-template-columns: 1fr; } }
            
            /* Kalendarz */
            .calendar-wrap { background: #fff; border-radius: 12px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
            .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
            .calendar-header h3 { margin: 0; font-size: 16px; }
            .calendar-nav { display: flex; gap: 5px; }
            .calendar-nav button { background: #f0f0f0; border: none; padding: 5px 12px; border-radius: 6px; cursor: pointer; }
            .calendar-nav button:hover { background: #e0e0e0; }
            .calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px; }
            .calendar-day-header { text-align: center; font-size: 11px; font-weight: 600; color: #888; padding: 8px 0; }
            .calendar-day { 
                aspect-ratio: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; 
                border-radius: 8px; cursor: pointer; font-size: 14px; position: relative; transition: all 0.2s;
            }
            .calendar-day:hover { background: #f5f5f5; }
            .calendar-day.other-month { color: #ccc; }
            .calendar-day.today { background: #e3f2fd; font-weight: 600; }
            .calendar-day.selected { background: #667eea; color: #fff; }
            .calendar-day.has-tasks::after { content: ''; width: 6px; height: 6px; background: #28a745; border-radius: 50%; position: absolute; bottom: 4px; }
            .calendar-day.selected.has-tasks::after { background: #fff; }
            
            /* Okres banner */
            .okres-banner { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
            .okres-banner h2 { margin: 0 0 5px 0; color: #fff; border: none; padding: 0; font-size: 20px; }
            .okres-banner .dates { opacity: 0.9; font-size: 14px; }
            .no-okres-banner { background: #f8d7da; color: #721c24; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
            
            /* Cele grid */
            .cele-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; }
            .cel-card { background: #f8f9fa; padding: 12px; border-radius: 8px; border-left: 4px solid #007bff; }
            .cel-card.zapianowany { border-left-color: #28a745; }
            .cel-card.klejpan { border-left-color: #17a2b8; }
            .cel-card.marka_langer { border-left-color: #ffc107; }
            .cel-card.marketing_construction { border-left-color: #dc3545; }
            .cel-card.fjo { border-left-color: #6f42c1; }
            .cel-card.obsluga_telefoniczna { border-left-color: #e91e63; }
            .cel-card.sprawy_organizacyjne { border-left-color: #6c757d; }
            .cel-card h4 { margin: 0 0 8px 0; font-size: 12px; color: #666; }
            .cel-card textarea { width: 100%; min-height: 50px; padding: 8px; border: 1px solid #e0e0e0; border-radius: 6px; font-size: 13px; resize: vertical; }
            .cel-card textarea.hidden { display: none; }
            .cel-card .status-row { margin-top: 8px; display: flex; align-items: center; gap: 8px; }
            .cel-card .status-row select { padding: 4px 8px; font-size: 12px; border-radius: 4px; border: 1px solid #ddd; }

            .cel-rok-display {
                font-size: 11px;
                color: #666;
                background: #e9ecef;
                padding: 6px 8px;
                border-radius: 4px;
                margin-bottom: 8px;
                line-height: 1.4;
            }

            .cel-okres-display {
                font-size: 13px;
                color: #333;
                padding: 8px;
                border: 1px dashed #ccc;
                border-radius: 6px;
                min-height: 40px;
                cursor: pointer;
                line-height: 1.5;
                transition: background 0.2s;
            }

            .cel-okres-display:hover {
                background: #fff;
                border-color: #007bff;
            }

            .cel-okres-display.empty {
                color: #999;
                font-style: italic;
            }

            .cel-okres-display .placeholder {
                color: #aaa;
            }

            .cel-okres-display.editing {
                display: none;
            }
            
            /* Formularz */
            .task-form { background: #f8f9fa; padding: 20px; border-radius: 12px; margin-bottom: 20px; }
            .task-form h3 { margin: 0 0 15px 0; font-size: 16px; }
            .form-row { display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap; }
            .form-group { flex: 1; min-width: 150px; }
            .form-group.wide { flex: 2; min-width: 300px; }
            .form-group label { display: block; font-size: 12px; font-weight: 600; color: #555; margin-bottom: 4px; }
            .form-group input, .form-group select, .form-group textarea { 
                width: 100%; padding: 10px 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 14px; box-sizing: border-box;
            }
            .form-group textarea { min-height: 60px; resize: vertical; }
            .form-group input:focus, .form-group select:focus, .form-group textarea:focus { border-color: #667eea; outline: none; box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
            
            /* Dzienne sekcje */
            .day-section { margin-bottom: 25px; }
            .day-header { background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); padding: 12px 16px; border-radius: 10px 10px 0 0; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #dee2e6; }
            .day-header.today-header { background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border-bottom-color: #28a745; }
            .day-header h3 { margin: 0; font-size: 15px; }
            .day-header .day-stats { font-size: 13px; color: #666; }
            
            .day-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 0 0 10px 10px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .day-table th, .day-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 13px; }
            .day-table th { background: #fafafa; font-weight: 600; font-size: 11px; text-transform: uppercase; color: #888; }
            .day-table tr:last-child td { border-bottom: none; }
            .day-table tr:hover { background: #fafafa; }
            
            /* Status wierszy zadań */
            .status-nowe { background-color: #fff !important; }
            .status-rozpoczete { background-color: #fff3cd !important; }
            .status-w_trakcie { background-color: #e8f4fd !important; }
            .status-zakonczone { background-color: #d4edda !important; }
            .status-zakonczone td strong { text-decoration: line-through; color: #666; }
            .status-niezrealizowane { background-color: #ffe4e1 !important; }
            .status-niezrealizowane td strong { text-decoration: line-through; color: #999; }
            .status-anulowane { background-color: #f8d7da !important; }
            .status-anulowane td strong { text-decoration: line-through; color: #999; }

            /* Dropdown statusu */
            .status-select {
                padding: 4px 8px;
                border-radius: 6px;
                border: 1px solid #ddd;
                font-size: 12px;
                cursor: pointer;
                min-width: 100px;
            }
            .status-select.status-nowe { background: #f8f9fa; border-color: #dee2e6; }
            .status-select.status-rozpoczete { background: #fff3cd; border-color: #ffc107; color: #856404; }
            .status-select.status-w_trakcie { background: #e8f4fd; border-color: #17a2b8; color: #0c5460; }
            .status-select.status-zakonczone { background: #d4edda; border-color: #28a745; color: #155724; }
            .status-select.status-niezrealizowane { background: #ffe4e1; border-color: #ff6b6b; color: #c92a2a; }
            .status-select.status-anulowane { background: #f8d7da; border-color: #dc3545; color: #721c24; }

            .task-done-checkbox {
                width: 20px;
                height: 20px;
                cursor: pointer;
                accent-color: #28a745;
            }
            .status-cell {
                text-align: center;
            }
            
            .inline-input { width: 60px; padding: 6px; border: 1px solid #ddd; border-radius: 6px; text-align: center; font-size: 13px; }
            .inline-select { padding: 6px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
            
            .btn-delete { color: #dc3545; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 4px; }
            .btn-delete:hover { background: #f8d7da; }
            .btn-edit { color: #007bff; background: none; border: none; cursor: pointer; padding: 4px 8px; border-radius: 4px; }
            .btn-edit:hover { background: #e3f2fd; }

            /* Bulk actions */
            .task-checkbox { width: 18px; height: 18px; cursor: pointer; accent-color: #667eea; }
            .bulk-actions { display: none; align-items: center; gap: 10px; margin-left: 15px; }
            .bulk-actions.visible { display: flex; }
            .bulk-actions button { padding: 5px 12px; font-size: 12px; border: none; border-radius: 6px; cursor: pointer; display: flex; align-items: center; gap: 4px; }
            .btn-bulk-delete { background: #dc3545; color: #fff; }
            .btn-bulk-delete:hover { background: #c82333; }
            .btn-bulk-copy { background: #667eea; color: #fff; }
            .btn-bulk-copy:hover { background: #5a6fd6; }
            .bulk-copy-date { padding: 4px 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 12px; }
            .select-all-label { font-size: 12px; color: #666; display: flex; align-items: center; gap: 5px; cursor: pointer; }
            .day-header-left { display: flex; align-items: center; gap: 10px; }
            .selected-count { font-size: 12px; color: #667eea; font-weight: 600; }
            
            .kategoria-badge { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
            .kategoria-badge.zapianowany { background: #d4edda; color: #155724; }
            .kategoria-badge.klejpan { background: #d1ecf1; color: #0c5460; }
            .kategoria-badge.marka_langer { background: #fff3cd; color: #856404; }
            .kategoria-badge.marketing_construction { background: #f8d7da; color: #721c24; }
            .kategoria-badge.fjo { background: #e2d9f3; color: #4a235a; }
            .kategoria-badge.sprawy_organizacyjne { background: #e2e3e5; color: #383d41; }
            .kategoria-badge.obsluga_telefoniczna { background: #fce4ec; color: #880e4f; }

            .saved-flash { animation: flash-green 0.5s ease; }
            @keyframes flash-green { 0% { background-color: #28a745; color: #fff; } 100% { background-color: transparent; } }
            
            .empty-day { text-align: center; padding: 30px; color: #999; font-size: 14px; background: #fff; border-radius: 0 0 10px 10px; }
            .empty-day-cell { text-align: center; padding: 20px; color: #999; font-size: 14px; }
            
            /* Empty slots for today */
            .empty-slot { background: #f8f9fa; }
            .empty-slot:hover { background: #f0f0f0; }
            .quick-add-row { background: #e8f4f8; border-top: 2px solid #007bff; }
            .quick-add-row:hover { background: #dbeef5; }
            .quick-add-kategoria {
                width: 100%;
                padding: 6px 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 12px;
                background: #fff;
            }
            .slot-input { 
                width: 100%; 
                padding: 8px 10px; 
                border: 1px dashed #ccc; 
                border-radius: 6px; 
                font-size: 13px; 
                background: #fff;
                transition: all 0.2s;
            }
            .slot-input:focus {
                border-style: solid;
                border-color: #667eea;
                outline: none;
                box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            }
            .slot-input::placeholder { color: #bbb; }
            .btn-add-slot {
                background: #28a745;
                color: #fff;
                border: none;
                padding: 6px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
            }
            .btn-add-slot:hover { background: #218838; }
            .btn-hide-slot {
                background: none;
                color: #6c757d;
                border: none;
                padding: 6px 8px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 14px;
                margin-left: 4px;
            }
            .btn-hide-slot:hover { background: #f0f0f0; color: #333; }

            /* Copy/Paste buttons */
            .btn-copy {
                background: none;
                border: none;
                cursor: pointer;
                padding: 4px 6px;
                border-radius: 4px;
                font-size: 14px;
                color: #6c757d;
            }
            .btn-copy:hover { background: #e9ecef; color: #495057; }
            .btn-paste {
                background: #17a2b8;
                color: #fff;
                border: none;
                padding: 4px 10px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 12px;
                margin-right: 10px;
            }
            .btn-paste:hover { background: #138496; }
            
            .day-header-actions {
                display: flex;
                align-items: center;
                gap: 10px;
            }
            
            .action-buttons {
                white-space: nowrap;
            }
            .action-buttons button {
                margin-right: 2px;
            }
            
            /* Overdue alerts */
            .overdue-alert { background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%); border: 1px solid #fc8181; border-left: 4px solid #e53e3e; border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; }
            .overdue-alert h3 { margin: 0 0 12px 0; color: #c53030; font-size: 16px; }
            .overdue-task { background: #fff; border-radius: 8px; padding: 12px 15px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
            .overdue-task:last-child { margin-bottom: 0; }
            .overdue-task-info { flex: 1; min-width: 200px; }
            .overdue-task-info .task-name { font-weight: 600; color: #333; }
            .overdue-task-info .task-meta { font-size: 12px; color: #888; margin-top: 3px; }
            .overdue-task-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
            .overdue-task-actions input[type="date"] { padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
            .overdue-task-actions button, .overdue-task-actions .btn { padding: 6px 12px; border-radius: 6px; font-size: 13px; cursor: pointer; border: none; }
            .btn-move { background: #4299e1; color: #fff; }
            .btn-move:hover { background: #3182ce; }
            .btn-complete { background: #48bb78; color: #fff; }
            .btn-complete:hover { background: #38a169; }
            .overdue-status-select { padding: 6px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
            
            /* Toast notifications */
            .toast { position: fixed; bottom: 20px; right: 20px; background: #333; color: #fff; padding: 12px 20px; border-radius: 8px; z-index: 9999; animation: slideIn 0.3s ease; }
            .toast.success { background: #28a745; }
            .toast.error { background: #dc3545; }
            @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
            
            /* Loading overlay */
            .loading { opacity: 0.5; pointer-events: none; }
            
            /* Edit modal */
            .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 9998; display: flex; align-items: center; justify-content: center; }
            .modal { background: #fff; border-radius: 12px; padding: 25px; max-width: 700px; width: 90%; max-height: 90vh; overflow-y: auto; }
            .modal h3 { margin: 0 0 20px 0; }
            .modal-buttons { display: flex; gap: 10px; margin-top: 20px; }
            .modal-close { position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 24px; cursor: pointer; color: #999; }
            .modal-close:hover { color: #333; }
            
            /* Okres review modal */
            .okres-modal { position: relative; }
            .okres-modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; margin: -25px -25px 20px -25px; border-radius: 12px 12px 0 0; }
            .okres-modal-header h3 { color: #fff; margin: 0; }
            .okres-modal-header .dates { opacity: 0.9; font-size: 14px; margin-top: 5px; }
            .okres-modal-header.past { background: linear-gradient(135deg, #6c757d 0%, #495057 100%); }
            
            .cel-review-card { background: #f8f9fa; border-radius: 8px; padding: 15px; margin-bottom: 15px; border-left: 4px solid #007bff; }
            .cel-review-card.zapianowany { border-left-color: #28a745; }
            .cel-review-card.klejpan { border-left-color: #17a2b8; }
            .cel-review-card.marka_langer { border-left-color: #ffc107; }
            .cel-review-card.marketing_construction { border-left-color: #dc3545; }
            .cel-review-card.fjo { border-left-color: #6f42c1; }
            .cel-review-card.obsluga_telefoniczna { border-left-color: #e91e63; }
            .cel-review-card h4 { margin: 0 0 10px 0; font-size: 14px; color: #333; }
            .cel-review-card .cel-text { background: #fff; padding: 10px; border-radius: 6px; margin-bottom: 10px; font-size: 13px; color: #555; min-height: 40px; }
            .cel-review-card .cel-text.empty { color: #999; font-style: italic; }
            .cel-review-row { display: flex; gap: 15px; align-items: flex-start; flex-wrap: wrap; }
            .cel-review-row .field { flex: 1; min-width: 150px; }
            .cel-review-row label { display: block; font-size: 12px; font-weight: 600; color: #666; margin-bottom: 4px; }
            .cel-review-row select, .cel-review-row textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 6px; font-size: 13px; }
            .cel-review-row textarea { min-height: 60px; resize: vertical; }
            
            .osiagniety-yes { background: #d4edda !important; border-color: #28a745 !important; }
            .osiagniety-no { background: #f8d7da !important; border-color: #dc3545 !important; }
            .osiagniety-partial { background: #fff3cd !important; border-color: #ffc107 !important; }
            
            /* Settings */
            .settings-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
            .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; margin-bottom: 15px; }
            .settings-table { width: 100%; border-collapse: collapse; }
            .settings-table th, .settings-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #eee; }
            .settings-table th { background: #f8f9fa; }
            
            /* Info box */
            .day-info { margin-top: 15px; padding: 15px; background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
            .day-info h4 { margin: 0 0 8px 0; font-size: 14px; color: #666; }
            .day-info .date-big { font-size: 24px; font-weight: 600; color: #667eea; }
            .day-info .day-name { font-size: 14px; color: #888; margin-top: 4px; }
            .day-info .okres-name { font-size: 12px; color: #28a745; margin-top: 8px; }

            /* Tasks with Goals layout */
            .tasks-with-goals-layout { display: flex; gap: 20px; align-items: flex-start; }
            .goals-panel { width: 220px; flex-shrink: 0; background: #fff; border-radius: 12px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); position: sticky; top: 20px; }
            .goals-panel h3 { margin: 0 0 12px 0; font-size: 14px; color: #333; border-bottom: 1px solid #eee; padding-bottom: 8px; }
            .goals-panel-list { display: flex; flex-direction: column; gap: 8px; }
            .goal-panel-item { padding: 10px 12px; border-radius: 8px; border-left: 4px solid #667eea; background: #f8f9fa; display: flex; gap: 10px; align-items: flex-start; }
            .goal-panel-item.goal-achieved { opacity: 0.7; }
            .goal-panel-item.goal-not-achieved { background: #fff5f5; }
            .goal-panel-status { cursor: pointer; font-size: 16px; user-select: none; transition: transform 0.2s; }
            .goal-panel-status:hover { transform: scale(1.2); }
            .goal-panel-content { flex: 1; }
            .goal-panel-item.zapianowany { border-left-color: #28a745; background: #f0fff4; }
            .goal-panel-item.klejpan { border-left-color: #17a2b8; background: #e8f8fb; }
            .goal-panel-item.marka_langer { border-left-color: #ffc107; background: #fffbeb; }
            .goal-panel-item.marketing_construction { border-left-color: #dc3545; background: #fff5f5; }
            .goal-panel-item.fjo { border-left-color: #6f42c1; background: #f8f5ff; }
            .goal-panel-item.obsluga_telefoniczna { border-left-color: #20c997; background: #e6fffa; }
            .goal-panel-item.sprawy_organizacyjne { border-left-color: #6c757d; background: #f8f9fa; }
            .goal-panel-item.poboczne_tematy { border-left-color: #fd7e14; background: #fff8f0; }
            .cel-item.osiagniety-yes { background: #d4edda !important; border-color: #28a745 !important; }
            .cel-item.osiagniety-no { background: #f8d7da !important; border-color: #dc3545 !important; }
            .goal-panel-kategoria { display: block; font-size: 10px; font-weight: 600; color: #888; text-transform: uppercase; margin-bottom: 4px; }
            .goal-panel-kategoria .goal-hours { font-weight: 400; color: #667eea; }
            .goal-panel-text { display: block; font-size: 12px; color: #333; line-height: 1.4; }
            .goals-panel .no-goals { color: #888; font-size: 12px; font-style: italic; margin: 0; }
            .tasks-panel { flex: 1; min-width: 0; }

            /* Daily progress section */
            .daily-progress-section { margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
            .daily-progress-section h4 { margin: 0 0 10px 0; font-size: 13px; color: #333; }
            .daily-progress-label { font-size: 11px; font-weight: 600; color: #555; margin-bottom: 4px; }
            .daily-progress-bar-container { position: relative; background: #e9ecef; border-radius: 10px; height: 24px; overflow: hidden; }
            .daily-progress-bar { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); border-radius: 10px; transition: width 0.5s ease; }
            .daily-progress-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-size: 11px; font-weight: 600; color: #333; white-space: nowrap; }
            .daily-other-stats { display: flex; gap: 15px; margin-top: 10px; font-size: 12px; color: #555; font-weight: 500; }
            .daily-stats-mini { display: flex; gap: 10px; margin-top: 8px; font-size: 11px; color: #666; }

            /* ========== HARMONOGRAM DNIA ========== */

            /* Modal startu dnia */
            .start-day-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.6);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease;
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .start-day-modal {
                background: #fff;
                border-radius: 16px;
                padding: 30px;
                max-width: 400px;
                width: 90%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                animation: slideUp 0.3s ease;
            }
            @keyframes slideUp {
                from { transform: translateY(20px); opacity: 0; }
                to { transform: translateY(0); opacity: 1; }
            }
            .start-day-modal h2 {
                margin: 0 0 10px 0;
                font-size: 24px;
                color: #333;
            }
            .start-day-modal p {
                color: #666;
                margin-bottom: 20px;
            }
            .start-day-modal .current-time {
                font-size: 48px;
                font-weight: 700;
                color: #667eea;
                margin: 20px 0;
            }
            .start-day-modal input[type="time"] {
                font-size: 24px;
                padding: 10px 20px;
                border: 2px solid #ddd;
                border-radius: 10px;
                text-align: center;
                width: 150px;
            }
            .start-day-modal input[type="time"]:focus {
                border-color: #667eea;
                outline: none;
            }
            .start-day-modal .btn-start-now {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                border: none;
                padding: 15px 40px;
                border-radius: 10px;
                font-size: 18px;
                font-weight: 600;
                cursor: pointer;
                margin: 20px 10px 10px;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .start-day-modal .btn-start-now:hover {
                transform: translateY(-2px);
                box-shadow: 0 5px 20px rgba(102,126,234,0.4);
            }
            .start-day-modal .btn-skip {
                background: transparent;
                border: none;
                color: #888;
                cursor: pointer;
                font-size: 14px;
                padding: 10px;
            }
            .start-day-modal .btn-skip:hover {
                color: #333;
            }

            /* Harmonogram godzinowy */
            .harmonogram-container {
                background: #fff;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                padding: 20px;
                margin-bottom: 20px;
            }
            .harmonogram-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
                padding-bottom: 15px;
                border-bottom: 2px solid #f0f0f0;
            }
            .harmonogram-header h2 {
                margin: 0;
                font-size: 18px;
                display: flex;
                align-items: center;
                gap: 10px;
            }
            .harmonogram-header .start-time-badge {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                padding: 5px 12px;
                border-radius: 20px;
                font-size: 13px;
                font-weight: 500;
            }
            /* Nawigacja daty - wspólne style */
            .date-nav {
                display: flex;
                align-items: center;
                gap: 8px;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 8px 15px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            .date-nav .nav-btn {
                background: #fff;
                border: none;
                width: 36px;
                height: 36px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            .date-nav .nav-btn:hover {
                background: #667eea;
                color: white;
                transform: scale(1.1);
            }
            .date-nav .date-display {
                display: flex;
                flex-direction: column;
                align-items: center;
                padding: 0 10px;
                min-width: 120px;
            }
            .date-nav .date-day {
                font-size: 11px;
                color: #888;
                text-transform: uppercase;
                letter-spacing: 1px;
            }
            .date-nav .date-value {
                font-size: 15px;
                font-weight: 600;
                color: #333;
                cursor: pointer;
            }
            .date-nav .date-value:hover {
                color: #667eea;
            }
            .date-nav input[type="date"] {
                position: absolute;
                opacity: 0;
                width: 0;
                height: 0;
            }
            .date-nav .btn-today {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 20px;
                cursor: pointer;
                font-size: 12px;
                font-weight: 600;
                transition: all 0.2s;
                box-shadow: 0 2px 8px rgba(102,126,234,0.3);
            }
            .date-nav .btn-today:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(102,126,234,0.4);
            }
            .harmonogram-date-nav, .tasks-date-nav {
                display: flex;
                align-items: center;
                gap: 8px;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                padding: 8px 15px;
                border-radius: 12px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            }
            .harmonogram-date-nav button, .tasks-date-nav button {
                background: #fff;
                border: none;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 14px;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            }
            .harmonogram-date-nav button:hover, .tasks-date-nav button:hover {
                background: #667eea;
                color: white;
                transform: scale(1.1);
            }
            .harmonogram-date-nav button.btn-today, .tasks-date-nav button.btn-today {
                width: auto;
                padding: 0 14px;
                border-radius: 16px;
                background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
                color: white;
                font-weight: 600;
                font-size: 12px;
            }
            .harmonogram-date-nav button.btn-today:hover, .tasks-date-nav button.btn-today:hover {
                background: linear-gradient(135deg, #218838 0%, #1aa179 100%);
            }
            .harmonogram-date-nav input[type="date"], .tasks-date-nav input[type="date"] {
                padding: 6px 12px;
                border: none;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 500;
                background: #fff;
                box-shadow: 0 2px 4px rgba(0,0,0,0.08);
                cursor: pointer;
            }
            .harmonogram-date-nav input[type="date"]:hover, .tasks-date-nav input[type="date"]:hover {
                box-shadow: 0 2px 8px rgba(102,126,234,0.3);
            }
            .tasks-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
            }
            .tasks-header h2 {
                margin: 0;
            }
            .tasks-hours-summary {
                display: flex;
                gap: 20px;
                padding: 10px 15px;
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 8px;
                margin-bottom: 15px;
                font-size: 13px;
            }
            .tasks-hours-summary .hours-item {
                display: flex;
                align-items: center;
                gap: 5px;
            }
            .tasks-hours-summary strong {
                color: #667eea;
            }
            .harmonogram-actions {
                display: flex;
                gap: 10px;
            }
            .btn-change-start {
                background: #f0f0f0;
                border: none;
                padding: 8px 15px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 13px;
            }
            .btn-change-start:hover {
                background: #e0e0e0;
            }
            .btn-toggle-view {
                background: #667eea;
                color: #fff;
                border: none;
                padding: 8px 15px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 13px;
            }
            .btn-toggle-view:hover {
                background: #5a6fd6;
            }

            /* Timeline */
            .harmonogram-timeline {
                position: relative;
                min-height: 600px;
            }
            .timeline-hour {
                display: flex;
                min-height: 60px;
                border-bottom: 1px solid #f0f0f0;
                position: relative;
            }
            .timeline-hour:last-child {
                border-bottom: none;
            }
            .timeline-hour-label {
                width: 60px;
                padding: 8px 10px;
                font-size: 13px;
                font-weight: 600;
                color: #888;
                flex-shrink: 0;
                border-right: 2px solid #f0f0f0;
            }
            .timeline-hour-content {
                flex: 1;
                min-height: 60px;
                position: relative;
                padding: 5px;
            }
            .timeline-hour-content.dragover {
                background: rgba(102,126,234,0.1);
            }
            .timeline-current-time {
                position: absolute;
                left: 60px;
                right: 0;
                height: 2px;
                background: #e53e3e;
                z-index: 10;
            }
            .timeline-current-time::before {
                content: '';
                position: absolute;
                left: -6px;
                top: -4px;
                width: 10px;
                height: 10px;
                background: #e53e3e;
                border-radius: 50%;
            }

            /* Zadania w harmonogramie */
            .harmonogram-task {
                background: #fff;
                border: 1px solid #e0e0e0;
                border-left: 4px solid #667eea;
                border-radius: 8px;
                padding: 10px 12px;
                margin: 3px 0;
                cursor: grab;
                transition: all 0.2s;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .harmonogram-task:hover {
                box-shadow: 0 3px 10px rgba(0,0,0,0.1);
                transform: translateY(-1px);
            }
            .harmonogram-task.dragging {
                opacity: 0.5;
                cursor: grabbing;
            }
            .harmonogram-task.is-stale {
                background: #f8f9fa;
                border-style: dashed;
            }
            .harmonogram-task.zapianowany { border-left-color: #28a745; }
            .harmonogram-task.klejpan { border-left-color: #17a2b8; }
            .harmonogram-task.marka_langer { border-left-color: #ffc107; }
            .harmonogram-task.marketing_construction { border-left-color: #dc3545; }
            .harmonogram-task.fjo { border-left-color: #6f42c1; }
            .harmonogram-task.obsluga_telefoniczna { border-left-color: #e91e63; }
            .harmonogram-task.sprawy_organizacyjne { border-left-color: #607d8b; }

            /* Statusy w harmonogramie */
            .harmonogram-status-nowe { }
            .harmonogram-status-rozpoczete {
                background: #fff3cd !important;
                border-color: #ffc107 !important;
            }
            .harmonogram-status-zakonczone {
                background: #d4edda !important;
                border-color: #28a745 !important;
            }
            .harmonogram-status-zakonczone .harmonogram-task-name {
                text-decoration: line-through;
                color: #666;
            }
            .harmonogram-status-anulowane {
                background: #f8d7da !important;
                border-color: #dc3545 !important;
            }
            .harmonogram-status-anulowane .harmonogram-task-name {
                text-decoration: line-through;
                color: #999;
            }
            .harmonogram-task-checkbox {
                width: 18px;
                height: 18px;
                margin-right: 10px;
                cursor: pointer;
                accent-color: #28a745;
            }
            .done-badge {
                color: #28a745;
                font-weight: bold;
            }
            .anulowane-badge {
                color: #dc3545;
                font-weight: bold;
            }

            .harmonogram-task-info {
                flex: 1;
            }
            .harmonogram-task-name {
                font-weight: 600;
                color: #333;
                font-size: 14px;
            }
            .harmonogram-task-meta {
                font-size: 12px;
                color: #888;
                margin-top: 3px;
                display: flex;
                gap: 10px;
                align-items: center;
            }
            .harmonogram-task-time {
                display: flex;
                align-items: center;
                gap: 5px;
                font-size: 12px;
                color: #667eea;
                font-weight: 500;
            }
            .harmonogram-task-actions {
                display: flex;
                gap: 5px;
            }
            .harmonogram-task-actions button {
                background: none;
                border: none;
                cursor: pointer;
                padding: 5px;
                border-radius: 4px;
                font-size: 14px;
            }
            .harmonogram-task-actions button:hover {
                background: #f0f0f0;
            }

            /* Edytowalna godzina */
            .harmonogram-task-time-edit {
                display: flex;
                align-items: center;
                gap: 3px;
                margin-right: 10px;
            }
            .time-input-small {
                width: 80px;
                padding: 6px 10px;
                border: none;
                border-radius: 8px;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                background: linear-gradient(135deg, #f0f4ff 0%, #e8ecf8 100%);
                color: #4a5568;
                transition: all 0.2s;
            }
            .time-input-small:hover {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                transform: scale(1.05);
            }
            .time-input-small:focus {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                outline: none;
                box-shadow: 0 2px 10px rgba(102,126,234,0.4);
            }
            .end-time {
                font-size: 12px;
                color: #888;
                background: #f0f0f0;
                padding: 4px 8px;
                border-radius: 6px;
            }

            /* Nieprzypisane zadania */
            .unscheduled-tasks {
                background: #f8f9fa;
                border-radius: 12px;
                padding: 15px;
                margin-bottom: 20px;
            }
            .unscheduled-tasks h3 {
                margin: 0 0 15px 0;
                font-size: 14px;
                color: #666;
            }
            .unscheduled-tasks-list {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            .unscheduled-task {
                background: #fff;
                border: 1px solid #ddd;
                border-left: 3px solid #667eea;
                border-radius: 6px;
                padding: 8px 12px;
                cursor: grab;
                font-size: 13px;
                transition: all 0.2s;
            }
            .unscheduled-task:hover {
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .unscheduled-task.dragging {
                opacity: 0.5;
            }

            /* Stałe zadania badge */
            .stale-badge {
                background: #e9ecef;
                color: #495057;
                padding: 2px 8px;
                border-radius: 10px;
                font-size: 10px;
                font-weight: 600;
            }

            /* Cykliczne zadania badge */
            .cykliczne-badge,
            .cyclic-badge {
                display: inline-block;
                margin-left: 5px;
                font-size: 12px;
                opacity: 0.7;
            }
            .task-cykliczne,
            tr.is-cyclic {
                background: linear-gradient(90deg, rgba(102, 126, 234, 0.05), transparent) !important;
            }
            tr.is-cyclic td:first-child {
                border-left: 3px solid #667eea;
            }

            .stale-task-row {
                background: #f8f9fa;
                border-left: 3px solid #6c757d;
            }

            .stale-task-row td {
                color: #6c757d;
            }

            .btn-convert-stale {
                background: #17a2b8;
                color: white;
                border: none;
                padding: 4px 8px;
                border-radius: 4px;
                cursor: pointer;
            }

            .btn-convert-stale:hover {
                background: #138496;
            }

            /* Widok listy vs timeline toggle */
            .view-toggle {
                display: flex;
                gap: 5px;
                background: #f0f0f0;
                padding: 3px;
                border-radius: 8px;
            }
            .view-toggle button {
                background: transparent;
                border: none;
                padding: 6px 12px;
                border-radius: 6px;
                cursor: pointer;
                font-size: 13px;
            }
            .view-toggle button.active {
                background: #fff;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }

            /* Stałe zadania w ustawieniach */
            .stale-zadania-table {
                width: 100%;
                border-collapse: collapse;
            }
            .stale-zadania-table th,
            .stale-zadania-table td {
                padding: 12px;
                text-align: left;
                border-bottom: 1px solid #f0f0f0;
            }
            .stale-zadania-table th {
                background: #f8f9fa;
                font-size: 12px;
                text-transform: uppercase;
                color: #666;
            }
            .stale-zadania-form {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 20px;
            }
            .stale-zadania-form .form-row {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
                margin-bottom: 15px;
            }
            .dni-tygodnia-checkboxes {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .dni-tygodnia-checkboxes label {
                display: flex;
                align-items: center;
                gap: 5px;
                padding: 5px 10px;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 6px;
                cursor: pointer;
                font-size: 13px;
            }
            .dni-tygodnia-checkboxes input:checked + span {
                color: #667eea;
                font-weight: 600;
            }
            .toggle-switch {
                position: relative;
                width: 50px;
                height: 26px;
            }
            .toggle-switch input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .toggle-slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: #ccc;
                border-radius: 26px;
                transition: 0.3s;
            }
            .toggle-slider:before {
                content: '';
                position: absolute;
                height: 20px;
                width: 20px;
                left: 3px;
                bottom: 3px;
                background: #fff;
                border-radius: 50%;
                transition: 0.3s;
            }
            .toggle-switch input:checked + .toggle-slider {
                background: #28a745;
            }
            .toggle-switch input:checked + .toggle-slider:before {
                transform: translateX(24px);
            }

            /* ========== TIMER / CZASOMIERZ ========== */
            .timer-btn {
                background: none;
                border: none;
                cursor: pointer;
                font-size: 16px;
                padding: 4px 8px;
                border-radius: 6px;
                transition: all 0.2s;
            }
            .timer-btn:hover {
                background: #f0f0f0;
            }
            .timer-btn.running {
                background: #e8f5e9;
                animation: pulse 1.5s infinite;
            }
            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.6; }
            }
            .timer-display {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                padding: 6px 12px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: 600;
                font-family: 'Courier New', monospace;
            }
            .timer-display.warning {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                animation: pulse 0.5s infinite;
            }
            .timer-display .timer-time {
                min-width: 60px;
                text-align: center;
            }
            .timer-display button {
                background: rgba(255,255,255,0.2);
                border: none;
                color: #fff;
                width: 24px;
                height: 24px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .timer-display button:hover {
                background: rgba(255,255,255,0.3);
            }

            /* Timer w wierszu zadania */
            .task-timer-cell {
                white-space: nowrap;
            }
            .task-timer-cell .timer-cell-content {
                display: inline-flex;
                align-items: center;
                gap: 5px;
            }
            .time-tracked {
                font-size: 12px;
                color: #28a745;
                font-weight: 600;
            }
            .time-edit-input {
                width: 50px;
                padding: 2px 4px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 12px;
                text-align: center;
            }

            /* Modal timera */
            .timer-modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.7);
                z-index: 10001;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s ease;
            }
            .timer-modal {
                background: #fff;
                border-radius: 20px;
                padding: 40px;
                max-width: 450px;
                width: 90%;
                text-align: center;
                box-shadow: 0 25px 80px rgba(0,0,0,0.4);
                animation: slideUp 0.3s ease;
            }
            .timer-modal h2 {
                margin: 0 0 10px 0;
                font-size: 28px;
            }
            .timer-modal .task-name {
                color: #666;
                font-size: 16px;
                margin-bottom: 20px;
            }
            .timer-modal .timer-big {
                font-size: 64px;
                font-weight: 700;
                font-family: 'Courier New', monospace;
                color: #667eea;
                margin: 30px 0;
            }
            .timer-modal .timer-big.overtime {
                color: #e53e3e;
            }
            .timer-modal .time-info {
                font-size: 14px;
                color: #888;
                margin-bottom: 30px;
            }
            .timer-modal-actions {
                display: flex;
                gap: 15px;
                justify-content: center;
                flex-wrap: wrap;
            }
            .timer-modal-actions button {
                padding: 12px 25px;
                border-radius: 10px;
                border: none;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }
            .btn-timer-done {
                background: #28a745;
                color: #fff;
            }
            .btn-timer-done:hover {
                background: #218838;
                transform: translateY(-2px);
            }
            .btn-timer-extend {
                background: #667eea;
                color: #fff;
            }
            .btn-timer-extend:hover {
                background: #5a6fd6;
                transform: translateY(-2px);
            }
            .btn-timer-stop {
                background: #dc3545;
                color: #fff;
            }
            .btn-timer-stop:hover {
                background: #c82333;
            }
            .btn-timer-mute {
                background: #dc3545;
                color: #fff;
            }
            @keyframes pulse-alarm {
                0%, 100% { opacity: 1; transform: scale(1); }
                50% { opacity: 0.7; transform: scale(1.05); }
            }
            .btn-timer-cancel {
                background: #f0f0f0;
                color: #333;
            }

            /* Extend options */
            .extend-options {
                display: flex;
                gap: 10px;
                justify-content: center;
                margin-top: 20px;
            }
            .extend-options button {
                padding: 8px 16px;
                border-radius: 8px;
                border: 2px solid #667eea;
                background: #fff;
                color: #667eea;
                font-weight: 600;
                cursor: pointer;
            }
            .extend-options button:hover {
                background: #667eea;
                color: #fff;
            }

            /* Floating timer */
            .floating-timer {
                position: fixed;
                bottom: 30px;
                right: 30px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                padding: 15px 25px;
                border-radius: 50px;
                box-shadow: 0 10px 40px rgba(102,126,234,0.4);
                z-index: 9999;
                display: flex;
                align-items: center;
                gap: 15px;
                cursor: pointer;
                animation: slideUp 0.3s ease;
            }
            .floating-timer:hover {
                transform: translateY(-3px);
                box-shadow: 0 15px 50px rgba(102,126,234,0.5);
            }
            .floating-timer .ft-time {
                font-size: 24px;
                font-weight: 700;
                font-family: 'Courier New', monospace;
            }
            .floating-timer .ft-task {
                font-size: 13px;
                max-width: 200px;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }
            .floating-timer .ft-actions {
                display: flex;
                gap: 8px;
            }
            .floating-timer .ft-actions button {
                background: rgba(255,255,255,0.2);
                border: none;
                color: #fff;
                width: 32px;
                height: 32px;
                border-radius: 50%;
                cursor: pointer;
                font-size: 14px;
            }
            .floating-timer .ft-actions button:hover {
                background: rgba(255,255,255,0.3);
            }
            .floating-timer.overtime {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            }

            /* ========== STATYSTYKI I FILTRY ========== */
            .stats-filters-section {
                background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
                border-radius: 12px;
                padding: 20px;
                margin-bottom: 20px;
            }
            .stats-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
                flex-wrap: wrap;
                gap: 15px;
            }
            .stats-header h2 {
                margin: 0;
                font-size: 18px;
                color: #333;
            }
            .stats-filters {
                display: flex;
                gap: 10px;
                align-items: center;
                flex-wrap: wrap;
            }
            .stats-filters select {
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 8px;
                font-size: 14px;
                background: #fff;
                min-width: 200px;
            }
            .stats-filters select:focus {
                border-color: #667eea;
                outline: none;
            }
            .filter-info {
                font-size: 13px;
                color: #666;
                background: #fff;
                padding: 6px 12px;
                border-radius: 6px;
            }

            /* Podsumowanie ogólne */
            .stats-summary {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 15px;
                margin-bottom: 20px;
            }
            .stat-box {
                background: #fff;
                padding: 15px;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .stat-box .stat-value {
                font-size: 28px;
                font-weight: 700;
                color: #667eea;
            }
            .stat-box .stat-label {
                font-size: 12px;
                color: #888;
                margin-top: 5px;
            }
            .stat-box.hours .stat-value { color: #28a745; }
            .stat-box.tasks .stat-value { color: #17a2b8; }
            .stat-box.completed .stat-value { color: #ffc107; }

            /* Wizualizacja dni okresu */
            .okres-days-visualization { margin: 15px 0; padding: 15px; background: #fff; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
            .okres-days-grid { display: flex; flex-wrap: wrap; gap: 4px; margin-bottom: 10px; }
            .okres-day-tile {
                width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center;
                font-size: 12px; font-weight: 600; cursor: default; transition: transform 0.1s;
            }
            .okres-day-tile:hover { transform: scale(1.1); }
            .okres-day-tile.working { background: #d4edda; color: #155724; border: 1px solid #28a745; }
            .okres-day-tile.free { background: #f8d7da; color: #721c24; border: 1px solid #dc3545; }
            .okres-day-tile.today { box-shadow: 0 0 0 2px #667eea; }
            .okres-days-legend { display: flex; gap: 15px; font-size: 12px; color: #666; }
            .legend-dot { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 4px; vertical-align: middle; }
            .legend-dot.working { background: #d4edda; border: 1px solid #28a745; }
            .legend-dot.free { background: #f8d7da; border: 1px solid #dc3545; }

            /* Statystyki per kategoria */
            .stats-categories {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 15px;
            }
            .stat-category-card {
                background: #fff;
                border-radius: 10px;
                padding: 15px;
                border-left: 4px solid #667eea;
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .stat-category-card.zapianowany { border-left-color: #28a745; }
            .stat-category-card.klejpan { border-left-color: #17a2b8; }
            .stat-category-card.marka_langer { border-left-color: #ffc107; }
            .stat-category-card.marketing_construction { border-left-color: #dc3545; }
            .stat-category-card.fjo { border-left-color: #6f42c1; }
            .stat-category-card.obsluga_telefoniczna { border-left-color: #e91e63; }
            .stat-category-card.sprawy_organizacyjne { border-left-color: #6c757d; }

            .stat-category-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
            }
            .stat-category-header h4 {
                margin: 0;
                font-size: 14px;
                color: #333;
            }
            .stat-category-header .edit-hours-btn {
                background: none;
                border: none;
                color: #667eea;
                cursor: pointer;
                font-size: 12px;
                padding: 4px 8px;
                border-radius: 4px;
            }
            .stat-category-header .edit-hours-btn:hover {
                background: #f0f0f0;
            }

            .stat-category-info {
                display: flex;
                gap: 15px;
                margin-bottom: 12px;
                font-size: 13px;
                color: #666;
            }
            .stat-category-info span {
                display: flex;
                align-items: center;
                gap: 4px;
            }

            /* Suwak postępu */
            .progress-container {
                margin-top: 10px;
            }
            .progress-bar-bg {
                height: 20px;
                background: #e9ecef;
                border-radius: 10px;
                overflow: hidden;
                position: relative;
            }
            .progress-bar-fill {
                height: 100%;
                border-radius: 10px;
                transition: width 0.5s ease;
                position: relative;
            }
            .progress-bar-fill.low { background: linear-gradient(90deg, #dc3545 0%, #f5576c 100%); }
            .progress-bar-fill.medium { background: linear-gradient(90deg, #ffc107 0%, #fd7e14 100%); }
            .progress-bar-fill.high { background: linear-gradient(90deg, #28a745 0%, #20c997 100%); }
            .progress-bar-fill.over { background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); }

            .progress-label {
                position: absolute;
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
                font-size: 12px;
                font-weight: 600;
                color: #333;
            }
            .progress-bar-fill .progress-label {
                color: #fff;
                right: auto;
                left: 10px;
            }

            .progress-details {
                display: flex;
                justify-content: space-between;
                font-size: 11px;
                color: #888;
                margin-top: 5px;
            }

            /* Edycja planowanych godzin */
            .hours-edit-row {
                display: flex;
                align-items: center;
                gap: 8px;
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px dashed #e0e0e0;
            }
            .hours-edit-row label {
                font-size: 12px;
                color: #666;
            }
            .hours-edit-row input {
                width: 70px;
                padding: 5px 8px;
                border: 1px solid #ddd;
                border-radius: 6px;
                font-size: 13px;
                text-align: center;
            }
            .hours-edit-row .btn-save-hours {
                background: #28a745;
                color: #fff;
                border: none;
                padding: 5px 10px;
                border-radius: 6px;
                font-size: 12px;
                cursor: pointer;
            }
            .hours-edit-row .btn-save-hours:hover {
                background: #218838;
            }

            /* Toggle sekcji statystyk */
            .stats-toggle-btn {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: #fff;
                border: none;
                padding: 10px 20px;
                border-radius: 8px;
                cursor: pointer;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 8px;
                margin-bottom: 15px;
            }
            .stats-toggle-btn:hover {
                opacity: 0.9;
            }
            .stats-content {
                display: none;
            }
            .stats-content.visible {
                display: block;
            }

            /* =============================================
               GAMIFICATION STYLES
               ============================================= */
            .gamification-panel {
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                border-radius: 16px;
                padding: 20px;
                margin-bottom: 20px;
                color: #fff;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            }
            .gamification-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 15px;
            }
            .gamification-level {
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .level-icon {
                font-size: 36px;
                filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            }
            .level-info h3 {
                margin: 0;
                font-size: 18px;
                color: #fff;
            }
            .level-info .level-name {
                color: #a0aec0;
                font-size: 13px;
            }
            .xp-bar-container {
                flex: 1;
                max-width: 300px;
                margin: 0 20px;
            }
            .xp-bar {
                background: rgba(255,255,255,0.1);
                border-radius: 10px;
                height: 20px;
                overflow: hidden;
                position: relative;
            }
            .xp-bar-fill {
                background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
                height: 100%;
                border-radius: 10px;
                transition: width 0.5s ease;
            }
            .xp-bar-text {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                font-size: 11px;
                font-weight: 600;
                color: #fff;
                text-shadow: 0 1px 2px rgba(0,0,0,0.5);
            }
            .xp-today {
                text-align: right;
                font-size: 14px;
                color: #a0aec0;
            }
            .xp-today .xp-value {
                font-size: 24px;
                font-weight: 700;
                color: #48bb78;
            }
            .gamification-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                gap: 12px;
                margin-top: 15px;
            }
            .gam-stat-box {
                background: rgba(255,255,255,0.05);
                border-radius: 10px;
                padding: 12px;
                text-align: center;
            }
            .gam-stat-box .stat-icon {
                font-size: 20px;
                margin-bottom: 4px;
            }
            .gam-stat-box .stat-value {
                font-size: 20px;
                font-weight: 700;
                color: #fff;
            }
            .gam-stat-box .stat-label {
                font-size: 11px;
                color: #a0aec0;
                margin-top: 2px;
            }
            .gam-stat-box .stat-multiplier {
                font-size: 10px;
                color: #48bb78;
                margin-top: 2px;
            }
            .gam-stat-box.streak .stat-value { color: #f6ad55; }
            .gam-stat-box.combo .stat-value { color: #63b3ed; }

            /* Morning checklist bar - under tasks header */
            .morning-checklist-bar {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 10px 15px;
                background: linear-gradient(90deg, #f0fff4 0%, #c6f6d5 100%);
                border: 1px solid #9ae6b4;
                border-radius: 8px;
                margin-bottom: 15px;
            }
            .morning-checklist-bar.checked {
                background: linear-gradient(90deg, #c6f6d5 0%, #9ae6b4 100%);
                border-color: #48bb78;
            }
            .morning-checklist-toggle {
                position: relative;
                width: 50px;
                height: 26px;
                cursor: pointer;
            }
            .morning-checklist-toggle input {
                opacity: 0;
                width: 0;
                height: 0;
            }
            .toggle-slider {
                position: absolute;
                top: 0; left: 0; right: 0; bottom: 0;
                background: #cbd5e0;
                border-radius: 26px;
                transition: 0.3s;
            }
            .toggle-slider:before {
                content: "";
                position: absolute;
                width: 20px;
                height: 20px;
                left: 3px;
                bottom: 3px;
                background: white;
                border-radius: 50%;
                transition: 0.3s;
            }
            .morning-checklist-toggle input:checked + .toggle-slider {
                background: #48bb78;
            }
            .morning-checklist-toggle input:checked + .toggle-slider:before {
                transform: translateX(24px);
            }
            .morning-checklist-bar .checklist-label {
                font-weight: 600;
                color: #2d3748;
                font-size: 14px;
            }
            .morning-checklist-bar.checked .checklist-label {
                color: #276749;
            }
            .morning-checklist-bar .checklist-status {
                margin-left: auto;
                font-size: 13px;
                color: #718096;
            }
            .morning-checklist-bar.checked .checklist-status {
                color: #276749;
                font-weight: 600;
            }

            /* Wyzwania dnia */
            .challenges-section {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid rgba(255,255,255,0.1);
            }
            .challenges-title {
                font-size: 14px;
                color: #a0aec0;
                margin-bottom: 10px;
                display: flex;
                align-items: center;
                gap: 8px;
            }
            .challenges-list {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }
            .challenge-item {
                background: rgba(255,255,255,0.05);
                border-radius: 8px;
                padding: 10px 14px;
                display: flex;
                align-items: center;
                gap: 10px;
                font-size: 13px;
            }
            .challenge-item.completed {
                background: rgba(72, 187, 120, 0.2);
            }
            .challenge-item .check {
                width: 18px;
                height: 18px;
                border: 2px solid #a0aec0;
                border-radius: 4px;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .challenge-item.completed .check {
                background: #48bb78;
                border-color: #48bb78;
            }
            .challenge-item .xp-reward {
                color: #48bb78;
                font-weight: 600;
                margin-left: auto;
            }
            /* Abstrakcyjne cele */
            .abstract-goals-section {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid rgba(255,255,255,0.1);
            }
            .btn-toggle-abstract {
                background: rgba(255,255,255,0.1);
                border: none;
                color: #fff;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 11px;
                cursor: pointer;
                margin-left: 10px;
            }
            .btn-toggle-abstract:hover {
                background: rgba(255,255,255,0.2);
            }
            .abstract-goals-list {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            .abstract-goal-item {
                background: rgba(255,255,255,0.05);
                border-radius: 8px;
                padding: 12px 16px;
                display: flex;
                align-items: center;
                gap: 12px;
                transition: all 0.2s;
            }
            .abstract-goal-item:hover {
                background: rgba(255,255,255,0.1);
            }
            .abstract-goal-item.completed {
                background: rgba(72, 187, 120, 0.2);
                opacity: 0.7;
            }
            .abstract-goal-item .goal-check {
                width: 24px;
                height: 24px;
                border: 2px solid #667eea;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.2s;
            }
            .abstract-goal-item .goal-check:hover {
                background: rgba(102, 126, 234, 0.3);
            }
            .abstract-goal-item.completed .goal-check {
                background: #48bb78;
                border-color: #48bb78;
            }
            .abstract-goal-item .goal-info {
                flex: 1;
            }
            .abstract-goal-item .goal-name {
                font-weight: 600;
                color: #fff;
            }
            .abstract-goal-item .goal-desc {
                font-size: 12px;
                color: #a0aec0;
                margin-top: 2px;
            }
            .abstract-goal-item .goal-xp {
                background: linear-gradient(135deg, #667eea, #764ba2);
                color: #fff;
                padding: 4px 10px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 12px;
            }
            .challenge-item.completed .xp-reward {
                text-decoration: line-through;
                opacity: 0.5;
            }
            .challenge-item .info-btn {
                cursor: pointer;
                opacity: 0.5;
                font-size: 14px;
                margin-left: 5px;
                position: relative;
            }
            .challenge-item .info-btn:hover {
                opacity: 1;
            }
            .challenge-item .info-tooltip {
                position: absolute;
                bottom: 100%;
                left: 50%;
                transform: translateX(-50%);
                background: #1a1a2e;
                border: 1px solid #4a5568;
                border-radius: 6px;
                padding: 8px 12px;
                font-size: 11px;
                color: #e2e8f0;
                white-space: nowrap;
                z-index: 100;
                display: none;
                min-width: 200px;
                max-width: 300px;
                white-space: normal;
                text-align: left;
                box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            }
            .challenge-item .info-btn:hover .info-tooltip {
                display: block;
            }

            /* XP Popup */
            .xp-popup {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) scale(0.8);
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                border-radius: 16px;
                padding: 30px 40px;
                color: #fff;
                text-align: center;
                z-index: 100000;
                box-shadow: 0 10px 50px rgba(0,0,0,0.5);
                opacity: 0;
                transition: all 0.3s ease;
                pointer-events: none;
            }
            .xp-popup.show {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
            .xp-popup .xp-amount {
                font-size: 48px;
                font-weight: 700;
                color: #48bb78;
                text-shadow: 0 2px 10px rgba(72,187,120,0.5);
            }
            .xp-popup .xp-label {
                font-size: 14px;
                color: #a0aec0;
                margin-top: 5px;
            }
            .xp-popup .xp-breakdown {
                margin-top: 15px;
                padding-top: 15px;
                border-top: 1px solid rgba(255,255,255,0.1);
                font-size: 12px;
                color: #a0aec0;
            }
            .xp-popup .xp-breakdown div {
                margin: 4px 0;
            }
            .xp-popup .combo-info {
                margin-top: 10px;
                font-size: 16px;
                color: #f6ad55;
            }

            /* Level Up Popup */
            .level-up-popup {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) scale(0.8);
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 20px;
                padding: 40px 50px;
                color: #fff;
                text-align: center;
                z-index: 100001;
                box-shadow: 0 10px 50px rgba(102,126,234,0.5);
                opacity: 0;
                transition: all 0.3s ease;
                pointer-events: none;
            }
            .level-up-popup.show {
                opacity: 1;
                transform: translate(-50%, -50%) scale(1);
            }
            .level-up-popup .level-up-title {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 20px;
            }
            .level-up-popup .level-change {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 20px;
                font-size: 24px;
            }
            .level-up-popup .level-icon-big {
                font-size: 48px;
            }
            .level-up-popup .arrow {
                font-size: 32px;
            }
            .level-up-popup .new-level-name {
                font-size: 18px;
                margin-top: 15px;
                opacity: 0.9;
            }

            /* Achievement Popup */
            .achievement-popup {
                position: fixed;
                top: 20px;
                right: 20px;
                background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
                border-radius: 12px;
                padding: 15px 20px;
                color: #fff;
                z-index: 100002;
                box-shadow: 0 5px 30px rgba(0,0,0,0.4);
                display: flex;
                align-items: center;
                gap: 15px;
                transform: translateX(120%);
                transition: transform 0.4s ease;
                border-left: 4px solid #f6ad55;
            }
            .achievement-popup.show {
                transform: translateX(0);
            }
            .achievement-popup .ach-icon {
                font-size: 36px;
            }
            .achievement-popup .ach-info h4 {
                margin: 0;
                font-size: 16px;
                color: #f6ad55;
            }
            .achievement-popup .ach-info p {
                margin: 4px 0 0 0;
                font-size: 12px;
                color: #a0aec0;
            }
            .achievement-popup .ach-xp {
                font-size: 14px;
                color: #48bb78;
                font-weight: 600;
            }

            /* Overlay for popups */
            .popup-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 99999;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }
            .popup-overlay.show {
                opacity: 1;
            }
        </style>
        <?php
    }
});

// =============================================
// STRONA GŁÓWNA - DASHBOARD (AJAX VERSION)
// =============================================
function zadaniomat_page_main() {
    global $wpdb;
    $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
    $table_cele_rok = $wpdb->prefix . 'zadaniomat_cele_rok';
    
    $current_okres = zadaniomat_get_current_okres();
    $current_rok = zadaniomat_get_current_rok();
    
    // Cele dla aktualnego okresu
    $cele_okres = [];
    if ($current_okres) {
        $cele_raw = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_cele_okres WHERE okres_id = %d", $current_okres->id));
        foreach ($cele_raw as $c) {
            $cele_okres[$c->kategoria] = ['cel' => $c->cel, 'status' => $c->status, 'id' => $c->id];
        }
    }
    
    // Cele roku
    $cele_rok = [];
    if ($current_rok) {
        $cele_raw = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_cele_rok WHERE rok_id = %d", $current_rok->id));
        foreach ($cele_raw as $c) {
            $cele_rok[$c->kategoria] = $c->cel;
        }
    }
    
    $kategorie_json = json_encode(ZADANIOMAT_KATEGORIE_ZADANIA);
    $today = date('Y-m-d');
    $current_month = date('Y-m');
    
    ?>
    <div class="wrap zadaniomat-wrap">
        <h1 style="margin-bottom: 20px;">📋 Zadaniomat</h1>

        <!-- Gamification Panel -->
        <div class="gamification-panel" id="gamification-panel">
            <div class="gamification-header">
                <div class="gamification-level">
                    <span class="level-icon" id="gam-level-icon">🌱</span>
                    <div class="level-info">
                        <h3>Level <span id="gam-level-num">1</span></h3>
                        <div class="level-name" id="gam-level-name">Świeżak</div>
                    </div>
                </div>
                <div class="xp-bar-container">
                    <div class="xp-bar">
                        <div class="xp-bar-fill" id="gam-xp-bar" style="width: 0%"></div>
                        <div class="xp-bar-text" id="gam-xp-text">0 / 150 XP</div>
                    </div>
                </div>
                <div class="xp-today">
                    <div>Dziś</div>
                    <div class="xp-value">+<span id="gam-today-xp">0</span> XP</div>
                </div>
            </div>
            <div class="gamification-stats">
                <div class="gam-stat-box streak">
                    <div class="stat-icon">🔥</div>
                    <div class="stat-value" id="gam-streak">0</div>
                    <div class="stat-label">Dni streak</div>
                    <div class="stat-multiplier" id="gam-streak-mult">1.0x</div>
                </div>
                <div class="gam-stat-box combo">
                    <div class="stat-icon">⚡</div>
                    <div class="stat-value" id="gam-combo">0</div>
                    <div class="stat-label">Combo</div>
                    <div class="stat-multiplier" id="gam-combo-mult">1.0x</div>
                </div>
                <div class="gam-stat-box">
                    <div class="stat-icon">🌅</div>
                    <div class="stat-value" id="gam-early-start">0</div>
                    <div class="stat-label">Wczesny start</div>
                </div>
                <div class="gam-stat-box">
                    <div class="stat-icon">🎨</div>
                    <div class="stat-value" id="gam-coverage">0</div>
                    <div class="stat-label">Pełne pokrycie</div>
                </div>
                <div class="gam-stat-box">
                    <div class="stat-icon">❄️</div>
                    <div class="stat-value" id="gam-freeze">0</div>
                    <div class="stat-label">Freeze days</div>
                </div>
            </div>
            <div class="challenges-section">
                <div class="challenges-title">🎲 Wyzwania dnia</div>
                <div class="challenges-list" id="gam-challenges">
                    <!-- Challenges will be loaded here -->
                </div>
            </div>
            <div class="abstract-goals-section" id="abstract-goals-section" style="display:none;">
                <div class="challenges-title">🎯 Cele abstrakcyjne <button class="btn-toggle-abstract" onclick="toggleAbstractGoals()">Pokaż/Ukryj</button></div>
                <div class="abstract-goals-list" id="abstract-goals-list">
                    <!-- Abstract goals will be loaded here -->
                </div>
            </div>
        </div>

        <!-- Popup elements -->
        <div class="popup-overlay" id="popup-overlay"></div>
        <div class="xp-popup" id="xp-popup">
            <div class="xp-amount" id="xp-popup-amount">+0 XP</div>
            <div class="xp-label">Zdobyte doświadczenie</div>
            <div class="xp-breakdown" id="xp-popup-breakdown"></div>
            <div class="combo-info" id="xp-popup-combo"></div>
        </div>
        <div class="level-up-popup" id="level-up-popup">
            <div class="level-up-title">🎉 LEVEL UP! 🎉</div>
            <div class="level-change">
                <span class="level-icon-big" id="level-up-old-icon">🌱</span>
                <span class="arrow">→</span>
                <span class="level-icon-big" id="level-up-new-icon">📝</span>
            </div>
            <div class="new-level-name" id="level-up-new-name">Level 2 - Planista</div>
        </div>
        <div class="achievement-popup" id="achievement-popup">
            <div class="ach-icon" id="ach-popup-icon">🏆</div>
            <div class="ach-info">
                <h4 id="ach-popup-name">Nowa odznaka!</h4>
                <p id="ach-popup-desc">Opis odznaki</p>
            </div>
            <div class="ach-xp" id="ach-popup-xp">+0 XP</div>
        </div>

        <!-- Overdue alerts container -->
        <div id="overdue-container"></div>

        <!-- Sekcja statystyk i filtrów -->
        <div class="stats-filters-section">
            <button class="stats-toggle-btn" onclick="toggleStatsSection()">
                📊 <span id="stats-toggle-text">Ukryj statystyki</span>
            </button>

            <div class="stats-content visible" id="stats-content">
                <div class="stats-header">
                    <h2>📊 Podsumowanie godzin i postęp celów</h2>
                    <div class="stats-filters">
                        <select id="stats-rok-filter" onchange="onRokFilterChange()">
                            <option value="">-- Wybierz rok (90 dni) --</option>
                        </select>
                        <select id="stats-okres-filter" onchange="loadStats()">
                            <option value="">-- Wybierz okres (2 tyg.) --</option>
                        </select>
                        <span class="filter-info" id="filter-info"></span>
                    </div>
                </div>

                <!-- Podsumowanie ogólne -->
                <div class="stats-summary" id="stats-summary">
                    <div class="stat-box hours">
                        <div class="stat-value" id="total-hours">0h</div>
                        <div class="stat-label">Przepracowane godziny</div>
                    </div>
                    <div class="stat-box tasks">
                        <div class="stat-value" id="total-tasks">0</div>
                        <div class="stat-label">Liczba zadań</div>
                    </div>
                    <div class="stat-box completed">
                        <div class="stat-value" id="total-completed">0</div>
                        <div class="stat-label">Ukończonych</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value" id="total-days">0</div>
                        <div class="stat-label">Dni roboczych</div>
                        <div class="stat-sublabel" id="total-days-info" style="font-size:11px;color:#888;margin-top:2px;"></div>
                    </div>
                </div>

                <!-- Wizualizacja dni okresu -->
                <div class="okres-days-visualization" id="okres-days-container">
                    <h4 style="margin: 15px 0 10px; font-size: 13px; color: #666;">📅 Dni w okresie:</h4>
                    <div class="okres-days-grid" id="okres-days-grid"></div>
                    <div class="okres-days-legend">
                        <span><span class="legend-dot working"></span> Roboczy</span>
                        <span><span class="legend-dot free"></span> Wolny</span>
                    </div>
                </div>

                <!-- Statystyki per kategoria -->
                <h3 style="margin: 20px 0 15px; font-size: 16px; color: #333;">📈 Postęp celów wg kategorii</h3>
                <div class="stats-categories" id="stats-categories">
                    <p style="color: #888; text-align: center;">Wybierz rok lub okres aby zobaczyć statystyki...</p>
                </div>
            </div>
        </div>

        <div class="main-layout">
            <!-- SIDEBAR -->
            <div class="sidebar">
                <div class="calendar-wrap">
                    <div class="calendar-header">
                        <h3 id="calendar-title"></h3>
                        <div class="calendar-nav">
                            <button onclick="changeMonth(-1)">←</button>
                            <button onclick="goToToday()">Dziś</button>
                            <button onclick="changeMonth(1)">→</button>
                        </div>
                    </div>
                    <div class="calendar-grid" id="calendar-grid"></div>
                </div>
                
                <div class="day-info">
                    <h4>📅 Wybrany dzień</h4>
                    <div class="date-big" id="selected-date-display"></div>
                    <div class="day-name" id="selected-day-name"></div>
                    <div class="okres-name" id="selected-okres-name"></div>

                    <!-- Toggle dzień wolny/roboczy -->
                    <div style="margin-top:10px; padding-top:10px; border-top:1px solid #eee;">
                        <button type="button" id="toggle-day-type-btn"
                                onclick="toggleDzienWolny()"
                                class="button button-small"
                                style="width:100%; font-size:11px;">
                            🔄 Oznacz jako dzień wolny
                        </button>
                        <div id="day-type-info" style="font-size:11px; color:#666; margin-top:5px; text-align:center;"></div>
                    </div>
                </div>
            </div>
            
            <!-- CONTENT -->
            <div class="content">
                <?php if ($current_okres): ?>
                    <div class="okres-banner">
                        <h2>🎯 <?php echo esc_html($current_okres->nazwa); ?></h2>
                        <div class="dates"><?php echo date('d.m', strtotime($current_okres->data_start)); ?> - <?php echo date('d.m.Y', strtotime($current_okres->data_koniec)); ?></div>
                        <?php if ($current_rok): ?>
                            <div style="opacity: 0.8; font-size: 13px; margin-top: 5px;">📅 <?php echo esc_html($current_rok->nazwa); ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="zadaniomat-card">
                        <h2>🎯 Cele na ten okres (2 tygodnie)</h2>
                        <div class="cele-grid">
                            <?php foreach (ZADANIOMAT_KATEGORIE as $key => $label):
                                $cel_data = $cele_okres[$key] ?? ['cel' => '', 'status' => null, 'id' => null];
                                $cel_rok = $cele_rok[$key] ?? '';
                            ?>
                                <div class="cel-card <?php echo $key; ?>" data-kategoria="<?php echo $key; ?>">
                                    <h4><?php echo $label; ?> <span class="goals-counter" id="counter-<?php echo $key; ?>" style="display:none; background:#28a745; color:#fff; padding:2px 6px; border-radius:10px; font-size:10px; margin-left:5px;"></span></h4>
                                    <?php if ($cel_rok): ?>
                                        <div class="cel-rok-display">
                                            <strong>Cel roczny:</strong> <?php echo esc_html($cel_rok); ?>
                                        </div>
                                    <?php endif; ?>

                                    <!-- Lista ukończonych celów -->
                                    <div class="completed-goals-list" id="completed-<?php echo $key; ?>" style="display:none; margin-bottom:8px;"></div>

                                    <div class="cel-okres-display <?php echo empty($cel_data['cel']) ? 'empty' : ''; ?>"
                                         data-okres="<?php echo $current_okres->id; ?>"
                                         data-kategoria="<?php echo $key; ?>"
                                         data-cel-id="<?php echo $cel_data['id'] ?: ''; ?>"
                                         onclick="editCelOkres(this)">
                                        <?php if ($cel_data['cel']): ?>
                                            <?php echo nl2br(esc_html($cel_data['cel'])); ?>
                                        <?php else: ?>
                                            <span class="placeholder">Kliknij aby dodać cel na 2 tygodnie...</span>
                                        <?php endif; ?>
                                    </div>
                                    <textarea class="cel-okres-input hidden"
                                              data-okres="<?php echo $current_okres->id; ?>"
                                              data-kategoria="<?php echo $key; ?>"
                                              data-cel-id="<?php echo $cel_data['id'] ?: ''; ?>"
                                              placeholder="Cel na 2 tygodnie..."><?php echo esc_textarea($cel_data['cel']); ?></textarea>

                                    <!-- Przycisk ukończ i dodaj kolejny -->
                                    <div class="goal-actions" style="margin-top:8px; display:flex; gap:5px; flex-wrap:wrap;">
                                        <button type="button" class="button button-small complete-goal-btn"
                                                data-okres="<?php echo $current_okres->id; ?>"
                                                data-kategoria="<?php echo $key; ?>"
                                                onclick="completeAndAddNew(this)"
                                                style="font-size:11px; <?php echo empty($cel_data['cel']) ? 'display:none;' : ''; ?>">
                                            ✅ Ukończ i dodaj nowy
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="no-okres-banner">
                        <strong>⚠️ Brak aktywnego okresu</strong><br>
                        Przejdź do <a href="<?php echo admin_url('admin.php?page=zadaniomat-settings'); ?>">Ustawień</a> i dodaj rok oraz okresy 2-tygodniowe.
                    </div>
                <?php endif; ?>

                <!-- Formularz zadania -->
                <div class="task-form">
                    <h3 id="form-title">➕ Dodaj zadanie</h3>
                    <form id="task-form">
                        <input type="hidden" id="edit-task-id" value="">
                        <div class="form-row">
                            <div class="form-group">
                                <label>📅 Dzień</label>
                                <input type="date" id="task-date" required value="<?php echo $today; ?>" onchange="syncDates(this.value)">
                            </div>
                            <div class="form-group">
                                <label>📁 Kategoria</label>
                                <select id="task-kategoria" required>
                                    <?php foreach (ZADANIOMAT_KATEGORIE_ZADANIA as $key => $label): ?>
                                        <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>⏱️ Planowany czas (min)</label>
                                <input type="number" id="task-czas" min="0" placeholder="np. 30">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group wide">
                                <label>📝 Zadanie</label>
                                <input type="text" id="task-nazwa" required placeholder="Co masz do zrobienia?">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group wide">
                                <label>🎯 Określony cel TO DO</label>
                                <textarea id="task-cel" placeholder="Szczegółowy opis celu..."></textarea>
                            </div>
                        </div>
                        <button type="submit" class="button button-primary button-large" id="submit-btn">➕ Dodaj zadanie</button>
                        <button type="button" class="button button-large" id="cancel-edit-btn" style="display:none;" onclick="cancelEdit()">Anuluj</button>
                    </form>
                </div>

                <!-- Layout: Cele + Zadania -->
                <div class="tasks-with-goals-layout">
                    <!-- Cele na okres - lewa strona -->
                    <?php if ($current_okres): ?>
                    <div class="goals-panel">
                        <?php
                        // Pobierz dane do dzisiejszego progresu
                        $table_godziny_okres = $wpdb->prefix . 'zadaniomat_godziny_okres';
                        $table_zadania = $wpdb->prefix . 'zadaniomat_zadania';

                        // Wszystkie kategorie zadań (używamy tych samych co w statystykach)
                        $kategorie_celow = zadaniomat_get_kategorie_zadania();
                        $kategorie_celow_keys = array_keys($kategorie_celow);

                        // Planowane godziny dziennie per kategoria (z tabeli per okres)
                        $planowane_raw = $wpdb->get_results($wpdb->prepare(
                            "SELECT kategoria, planowane_godziny_dziennie FROM $table_godziny_okres WHERE okres_id = %d",
                            $current_okres->id
                        ));
                        $planowane_map = [];
                        $total_planowane = 0;
                        foreach ($planowane_raw as $p) {
                            $planowane_map[$p->kategoria] = floatval($p->planowane_godziny_dziennie);
                            // Wszystkie kategorie liczą się do progresu
                            if (in_array($p->kategoria, $kategorie_celow_keys)) {
                                $total_planowane += floatval($p->planowane_godziny_dziennie);
                            }
                        }

                        // Faktycznie przepracowane dzisiaj - rozdzielone na cele vs inne
                        $dzis_stats_cele = $wpdb->get_row($wpdb->prepare(
                            "SELECT SUM(COALESCE(faktyczny_czas, 0)) as faktyczny_min,
                                    COUNT(*) as liczba_zadan,
                                    SUM(CASE WHEN status = 'zakonczone' THEN 1 ELSE 0 END) as ukonczone
                             FROM $table_zadania WHERE dzien = %s AND kategoria IN ('" . implode("','", array_map('esc_sql', $kategorie_celow_keys)) . "')",
                            $today
                        ));

                        $dzis_stats_inne = $wpdb->get_row($wpdb->prepare(
                            "SELECT SUM(COALESCE(faktyczny_czas, 0)) as faktyczny_min,
                                    COUNT(*) as liczba_zadan,
                                    SUM(CASE WHEN status = 'zakonczone' THEN 1 ELSE 0 END) as ukonczone
                             FROM $table_zadania WHERE dzien = %s AND (kategoria IS NULL OR kategoria NOT IN ('" . implode("','", array_map('esc_sql', $kategorie_celow_keys)) . "'))",
                            $today
                        ));

                        $dzis_stats_total = $wpdb->get_row($wpdb->prepare(
                            "SELECT SUM(COALESCE(faktyczny_czas, 0)) as faktyczny_min,
                                    COUNT(*) as liczba_zadan,
                                    SUM(CASE WHEN status = 'zakonczone' THEN 1 ELSE 0 END) as ukonczone
                             FROM $table_zadania WHERE dzien = %s",
                            $today
                        ));

                        $faktyczny_cele_h = ($dzis_stats_cele->faktyczny_min ?: 0) / 60;
                        $faktyczny_inne_h = ($dzis_stats_inne->faktyczny_min ?: 0) / 60;
                        $faktyczny_total_h = ($dzis_stats_total->faktyczny_min ?: 0) / 60;
                        $procent_dnia = $total_planowane > 0 ? min(100, round(($faktyczny_cele_h / $total_planowane) * 100)) : 0;
                        ?>

                        <!-- Dzisiejszy progres -->
                        <div class="daily-progress-section" id="daily-progress-section">
                            <h4>📊 Dziś: <?php echo date('d.m'); ?></h4>

                            <!-- Progres celów -->
                            <div class="daily-progress-label">🎯 Cele:</div>
                            <div class="daily-progress-bar-container">
                                <div class="daily-progress-bar" id="daily-progress-bar" style="width: <?php echo $procent_dnia; ?>%"></div>
                                <span class="daily-progress-text" id="daily-progress-text"><?php echo number_format($faktyczny_cele_h, 1); ?>h / <?php echo number_format($total_planowane, 1); ?>h (<?php echo $procent_dnia; ?>%)</span>
                            </div>

                            <!-- Czas na inne zadania -->
                            <div class="daily-other-stats">
                                <span>📁 Inne: <span id="daily-inne-hours"><?php echo number_format($faktyczny_inne_h, 1); ?></span>h</span>
                                <span>⏱️ Razem: <span id="daily-razem-hours"><?php echo number_format($faktyczny_total_h, 1); ?></span>h</span>
                            </div>

                            <div class="daily-stats-mini">
                                <span>📋 <span id="daily-tasks-count"><?php echo $dzis_stats_total->liczba_zadan ?: 0; ?></span> zadań</span>
                                <span>✅ <span id="daily-tasks-done"><?php echo $dzis_stats_total->ukonczone ?: 0; ?></span> ukończ.</span>
                            </div>
                        </div>

                        <h3>🎯 Cele: <?php echo esc_html($current_okres->nazwa); ?></h3>
                        <div class="goals-panel-list">
                            <?php
                            $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
                            // Pobierz najnowszy aktywny cel dla każdej kategorii
                            $panel_cele_raw = $wpdb->get_results($wpdb->prepare(
                                "SELECT c1.* FROM $table_cele_okres c1
                                 INNER JOIN (
                                     SELECT kategoria, MAX(id) as max_id
                                     FROM $table_cele_okres
                                     WHERE okres_id = %d AND cel IS NOT NULL AND cel != '' AND completed_at IS NULL
                                     GROUP BY kategoria
                                 ) c2 ON c1.id = c2.max_id",
                                $current_okres->id
                            ));
                            // Uporządkuj według kolejności kategorii zadań
                            $panel_cele_map = [];
                            foreach ($panel_cele_raw as $cel) {
                                $panel_cele_map[$cel->kategoria] = $cel;
                            }
                            $has_goals = false;
                            // Tylko kategorie celów (nie wszystkie kategorie zadań)
                            foreach ($kategorie_celow as $kat_key => $kat_label):
                                if (isset($panel_cele_map[$kat_key])):
                                    $cel = $panel_cele_map[$kat_key];
                                    $planowane_h = isset($planowane_map[$kat_key]) ? $planowane_map[$kat_key] : 0;
                                    $has_goals = true;
                            ?>
                                <div class="goal-panel-item <?php echo esc_attr($cel->kategoria); ?><?php echo ($cel->osiagniety === '1' || $cel->osiagniety == 1) ? ' goal-achieved' : (($cel->osiagniety === '0' || $cel->osiagniety == 0) ? ' goal-not-achieved' : ''); ?>" data-cel-id="<?php echo $cel->id; ?>">
                                    <div class="goal-panel-status" onclick="toggleGoalStatus(<?php echo $cel->id; ?>, this)" title="Kliknij aby zmienić status">
                                        <?php if ($cel->osiagniety === '1' || $cel->osiagniety == 1): ?>
                                            ✅
                                        <?php elseif ($cel->osiagniety === '0' || $cel->osiagniety == 0): ?>
                                            ❌
                                        <?php else: ?>
                                            ⬜
                                        <?php endif; ?>
                                    </div>
                                    <div class="goal-panel-content">
                                        <span class="goal-panel-kategoria"><?php echo esc_html($kat_label); ?><?php if ($planowane_h > 0): ?> <span class="goal-hours">(<?php echo $planowane_h; ?>h/d)</span><?php endif; ?></span>
                                        <span class="goal-panel-text"><?php echo esc_html($cel->cel); ?></span>
                                    </div>
                                </div>
                            <?php
                                endif;
                            endforeach;
                            if (!$has_goals):
                            ?>
                                <p class="no-goals">Brak aktywnych celów</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Zadania na dziś - prawa strona -->
                    <div class="tasks-panel">
                        <div class="zadaniomat-card" id="today-tasks-section">
                            <div class="tasks-header">
                                <h2>📋 Zadania na dziś</h2>
                                <div class="tasks-date-nav">
                                    <button onclick="changeTasksDate(-1)" title="Poprzedni dzień">←</button>
                                    <input type="date" id="tasks-list-date" value="<?php echo $today; ?>" onchange="loadTasksForDate(this.value)">
                                    <button onclick="changeTasksDate(1)" title="Następny dzień">→</button>
                                    <button onclick="goToTodayTasks()" class="btn-today">Dziś</button>
                                </div>
                            </div>
                            <div class="tasks-hours-summary" id="tasks-hours-summary">
                                <span class="hours-item">⏱️ Przepracowano: <strong id="hours-worked">0h 0min</strong></span>
                                <span class="hours-item">📊 Zaplanowano: <strong id="hours-planned">0h 0min</strong></span>
                                <span class="hours-item">✅ Ukończono: <strong id="tasks-completed-count">0/0</strong></span>
                            </div>
                            <div class="morning-checklist-bar" id="morning-checklist-bar">
                                <label class="morning-checklist-toggle">
                                    <input type="checkbox" id="morning-checklist-checkbox">
                                    <span class="toggle-slider"></span>
                                </label>
                                <span class="checklist-label">📋 Lista poranna gotowa</span>
                                <span class="checklist-status" id="morning-checklist-status"></span>
                            </div>
                            <div id="today-tasks-container"></div>
                        </div>
                    </div>
                </div>

                <!-- Harmonogram dnia - z wyborem daty -->
                <div id="harmonogram-section">
                    <div class="harmonogram-container">
                        <div class="harmonogram-header">
                            <h2>
                                📅 Harmonogram dnia
                                <span class="start-time-badge" id="start-time-badge">Start: --:--</span>
                            </h2>
                            <div class="harmonogram-date-nav">
                                <button onclick="changeHarmonogramDate(-1)" title="Poprzedni dzień">←</button>
                                <input type="date" id="harmonogram-date" value="<?php echo $today; ?>" onchange="loadHarmonogramForDate(this.value)">
                                <button onclick="changeHarmonogramDate(1)" title="Następny dzień">→</button>
                                <button onclick="goToTodayHarmonogram()" class="btn-today">Dziś</button>
                            </div>
                            <div class="harmonogram-actions">
                                <button class="btn-change-start" onclick="showStartDayModal()">⏰ Zmień start</button>
                                <div class="view-toggle">
                                    <button class="active" data-view="timeline" onclick="toggleHarmonogramView('timeline')">📊 Timeline</button>
                                    <button data-view="list" onclick="toggleHarmonogramView('list')">📋 Lista</button>
                                </div>
                            </div>
                        </div>

                        <!-- Nieprzypisane zadania (do przeciągnięcia) -->
                        <div class="unscheduled-tasks" id="unscheduled-tasks">
                            <h3>📦 Zadania do przypisania <span id="unscheduled-count"></span></h3>
                            <div class="unscheduled-tasks-list" id="unscheduled-list"></div>
                        </div>

                        <!-- Timeline godzinowy -->
                        <div class="harmonogram-timeline" id="harmonogram-timeline"></div>
                    </div>
                </div>

                <!-- Inne dni -->
                <div class="zadaniomat-card">
                    <h2>📋 Zadania - inne dni</h2>
                    <div id="tasks-container"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal startu dnia -->
    <div id="start-day-modal-container"></div>

    <!-- Timer containers -->
    <div id="timer-modal-container"></div>
    <div id="floating-timer-container"></div>

    <!-- Toast container -->
    <div id="toast-container"></div>
    
    <script>
    (function($) {
        // ==================== CONFIG ====================
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var nonce = '<?php echo wp_create_nonce('zadaniomat_ajax'); ?>';
        var kategorie = <?php echo $kategorie_json; ?>;
        var today = '<?php echo $today; ?>';
        var dayNames = ['Niedziela', 'Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota'];
        var monthNames = ['', 'Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'];
        
        // ==================== STATE ====================
        var selectedDate = today;
        var currentMonth = '<?php echo $current_month; ?>';
        var daysWithTasks = [];
        var tasksCache = {};
        var copiedTask = null; // Skopiowane zadanie

        // State dla statystyk
        var allRoki = [];
        var allOkresy = [];
        var currentRokId = <?php echo $current_rok ? $current_rok->id : 'null'; ?>;
        var currentOkresId = <?php echo $current_okres ? $current_okres->id : 'null'; ?>;
        var statsVisible = true;

        // ==================== INIT ====================
        $(document).ready(function() {
            renderCalendar();
            loadOverdueTasks();
            loadTasks();
            updateDateInfo();
            bindEvents();
            checkShowHarmonogram();
            loadRokiOkresy(); // Załaduj lata i okresy dla filtrów
            loadAllGoalsSummaries(); // Załaduj podsumowania celów
            checkUnmarkedGoals(); // Sprawdź nieoznaczone cele
            restoreTimerFromStorage(); // Przywróć timer jeśli był aktywny
            loadGamificationData(); // Załaduj dane gamifikacji
        });

        // ==================== GAMIFICATION ====================
        var gamificationData = null;
        var levelIcons = {1:'🌱',2:'📝',3:'🎯',4:'⚡',5:'🔧',6:'💼',7:'🏆',8:'🎖️',9:'👑',10:'🌟'};

        function loadGamificationData() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_gamification',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    gamificationData = response.data;
                    updateGamificationUI();
                    showNewAchievements();
                    loadAbstractGoals();
                }
            });
        }

        function updateGamificationUI() {
            if (!gamificationData) return;
            var d = gamificationData;

            // Level info
            $('#gam-level-icon').text(d.level.icon);
            $('#gam-level-num').text(d.level.level);
            $('#gam-level-name').text(d.level.name);

            // XP bar
            $('#gam-xp-bar').css('width', d.level.progress_percent + '%');
            $('#gam-xp-text').text(d.level.current_xp + ' / ' + d.level.next_level_xp + ' XP');

            // Today XP
            $('#gam-today-xp').text(d.today_xp);

            // Streaks
            $('#gam-streak').text(d.streaks.work_days.current);
            $('#gam-streak-mult').text(d.multipliers.streak.toFixed(1) + 'x');
            $('#gam-combo').text(d.combo.current);
            $('#gam-combo-mult').text(d.multipliers.combo.toFixed(1) + 'x');
            $('#gam-early-start').text(d.streaks.early_start.current);
            $('#gam-coverage').text(d.streaks.full_coverage.current);
            $('#gam-freeze').text(d.stats.freeze_days);

            // Challenges with info tooltips
            var challengesHtml = '';
            d.challenges.forEach(function(c) {
                var completed = c.completed ? 'completed' : '';
                var check = c.completed ? '✓' : '';
                var condition = c.condition || '';
                var tooltipHtml = condition ? '<span class="info-btn">ℹ️<span class="info-tooltip">' + escapeHtml(condition) + '</span></span>' : '';
                challengesHtml += '<div class="challenge-item ' + completed + '">' +
                    '<div class="check">' + check + '</div>' +
                    '<span>' + escapeHtml(c.desc) + tooltipHtml + '</span>' +
                    '<span class="xp-reward">+' + c.xp + ' XP</span>' +
                    '</div>';
            });
            $('#gam-challenges').html(challengesHtml);

            // Load morning checklist state
            loadMorningChecklist();
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Morning checklist functions
        function loadMorningChecklist() {
            var dateToLoad = $('#tasks-list-date').val() || currentDate;
            $.post(ajaxurl, {
                action: 'zadaniomat_get_morning_checklist',
                nonce: nonce,
                date: dateToLoad
            }, function(response) {
                if (response.success && response.data) {
                    var checked = response.data.checked;
                    var time = response.data.time;
                    $('#morning-checklist-checkbox').prop('checked', checked);
                    if (checked) {
                        $('#morning-checklist-bar').addClass('checked');
                        $('#morning-checklist-status').html('✅ Zapisano o <strong>' + time + '</strong>');
                    } else {
                        $('#morning-checklist-bar').removeClass('checked');
                        $('#morning-checklist-status').text('');
                    }
                }
            });
        }

        // Auto-save morning checklist on checkbox change
        $(document).on('change', '#morning-checklist-checkbox', function() {
            var checked = $(this).is(':checked');
            var dateToSave = $('#tasks-list-date').val() || currentDate;

            // Disable checkbox during save
            $(this).prop('disabled', true);
            $('#morning-checklist-status').text('Zapisywanie...');

            $.post(ajaxurl, {
                action: 'zadaniomat_set_morning_checklist',
                nonce: nonce,
                date: dateToSave,
                checked: checked ? 1 : 0
            }, function(response) {
                $('#morning-checklist-checkbox').prop('disabled', false);
                if (response.success) {
                    if (checked) {
                        $('#morning-checklist-bar').addClass('checked');
                        $('#morning-checklist-status').html('✅ Zapisano o <strong>' + response.data.time + '</strong>');
                        // Refresh gamification data to update challenges
                        loadGamificationData();
                    } else {
                        $('#morning-checklist-bar').removeClass('checked');
                        $('#morning-checklist-status').text('');
                    }
                }
            });
        });

        // ==================== ABSTRACT GOALS ====================
        var abstractGoalsVisible = false;

        function loadAbstractGoals() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_abstract_goals',
                nonce: nonce
            }, function(response) {
                if (response.success && response.data.goals.length > 0) {
                    $('#abstract-goals-section').show();
                    renderAbstractGoals(response.data.goals);
                } else {
                    $('#abstract-goals-section').hide();
                }
            });
        }

        function renderAbstractGoals(goals) {
            var html = '';
            goals.forEach(function(g) {
                var completed = g.completed == 1 ? 'completed' : '';
                var check = g.completed == 1 ? '✓' : '';
                var onclick = g.completed == 1 ? '' : 'onclick="completeAbstractGoal(' + g.id + ')"';
                html += '<div class="abstract-goal-item ' + completed + '" data-id="' + g.id + '">';
                html += '<div class="goal-check" ' + onclick + '>' + check + '</div>';
                html += '<div class="goal-info">';
                html += '<div class="goal-name">' + escapeHtml(g.nazwa) + '</div>';
                if (g.opis) {
                    html += '<div class="goal-desc">' + escapeHtml(g.opis) + '</div>';
                }
                html += '</div>';
                html += '<div class="goal-xp">+' + g.xp_reward + ' XP</div>';
                html += '</div>';
            });
            $('#abstract-goals-list').html(html || '<p style="color:#a0aec0;font-size:12px;">Brak celów abstrakcyjnych. Dodaj je w ustawieniach gamifikacji.</p>');
        }

        window.toggleAbstractGoals = function() {
            abstractGoalsVisible = !abstractGoalsVisible;
            $('#abstract-goals-list').slideToggle(200);
        };

        window.completeAbstractGoal = function(id) {
            if (!confirm('Czy na pewno chcesz oznaczyć ten cel jako ukończony i odebrać XP?')) return;

            $.post(ajaxurl, {
                action: 'zadaniomat_complete_abstract_goal',
                nonce: nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    showToast('🎯 Cel osiągnięty! +' + response.data.xp_earned + ' XP', 'success');
                    loadAbstractGoals();
                    loadGamificationData();

                    if (response.data.level_up) {
                        showLevelUpPopup(response.data.level_info);
                    }
                } else {
                    showToast('Błąd: ' + (response.data || 'Nieznany błąd'), 'error');
                }
            });
        };

        function showNewAchievements() {
            if (!gamificationData || !gamificationData.new_achievements.length) return;

            var achievements = gamificationData.new_achievements;
            var delay = 0;

            achievements.forEach(function(ach) {
                setTimeout(function() {
                    showAchievementPopup(ach);
                }, delay);
                delay += 3500;
            });

            // Mark as notified
            var keys = achievements.map(function(a) { return a.key; });
            $.post(ajaxurl, {
                action: 'zadaniomat_mark_achievements_notified',
                nonce: nonce,
                keys: keys
            });
        }

        function showAchievementPopup(ach) {
            $('#ach-popup-icon').text(ach.icon);
            $('#ach-popup-name').text(ach.name);
            $('#ach-popup-desc').text(ach.desc);
            $('#ach-popup-xp').text('+' + ach.xp + ' XP');

            var $popup = $('#achievement-popup');
            $popup.addClass('show');

            setTimeout(function() {
                $popup.removeClass('show');
            }, 3000);
        }

        function showXpPopup(result) {
            if (!result || !result.xp_earned) return;

            var $overlay = $('#popup-overlay');
            var $popup = $('#xp-popup');

            $('#xp-popup-amount').text('+' + result.xp_earned + ' XP');

            var breakdown = '';
            if (result.xp_breakdown) {
                breakdown += '<div>Bazowe: ' + result.xp_breakdown.base + ' XP</div>';
                if (result.xp_breakdown.streak_multiplier > 1) {
                    breakdown += '<div>Streak ' + result.xp_breakdown.streak_multiplier.toFixed(1) + 'x</div>';
                }
                if (result.xp_breakdown.combo_multiplier > 1) {
                    breakdown += '<div>Combo ' + result.xp_breakdown.combo_multiplier.toFixed(1) + 'x</div>';
                }
            }
            $('#xp-popup-breakdown').html(breakdown);

            if (result.combo && result.combo.combo > 1) {
                $('#xp-popup-combo').text('Combo: ' + result.combo.combo + ' 🔥');
            } else {
                $('#xp-popup-combo').text('');
            }

            $overlay.addClass('show');
            $popup.addClass('show');

            setTimeout(function() {
                $popup.removeClass('show');
                $overlay.removeClass('show');

                // Check for level up
                if (result.level_up) {
                    setTimeout(function() {
                        showLevelUpPopup(result);
                    }, 200);
                }
            }, 1500);

            // Refresh gamification data
            loadGamificationData();
        }

        function showLevelUpPopup(result) {
            if (!result.level_info) return;

            var oldLevel = result.level_info.level - 1;
            var newLevel = result.level_info.level;

            $('#level-up-old-icon').text(levelIcons[oldLevel] || '❓');
            $('#level-up-new-icon').text(result.level_info.icon);
            $('#level-up-new-name').text('Level ' + newLevel + ' - ' + result.level_info.name);

            var $overlay = $('#popup-overlay');
            var $popup = $('#level-up-popup');

            $overlay.addClass('show');
            $popup.addClass('show');

            setTimeout(function() {
                $popup.removeClass('show');
                $overlay.removeClass('show');
            }, 2500);
        }

        // ==================== STATYSTYKI ====================
        window.toggleStatsSection = function() {
            statsVisible = !statsVisible;
            var $content = $('#stats-content');
            var $text = $('#stats-toggle-text');

            if (statsVisible) {
                $content.addClass('visible');
                $text.text('Ukryj statystyki');
                // Jeśli jeszcze nie załadowano, załaduj z aktualnym rokiem
                if (currentRokId && !$('#stats-rok-filter').val()) {
                    $('#stats-rok-filter').val(currentRokId);
                    onRokFilterChange();
                }
            } else {
                $content.removeClass('visible');
                $text.text('Pokaż statystyki i postęp celów');
            }
        };

        window.loadRokiOkresy = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_all_roki_okresy',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    allRoki = response.data.roki;
                    allOkresy = response.data.okresy;
                    renderRokiSelect();
                    // Auto-select current rok and okres if available
                    if (currentRokId) {
                        $('#stats-rok-filter').val(currentRokId);
                        onRokFilterChange();
                    }
                }
            });
        };

        function renderRokiSelect() {
            var html = '<option value="">-- Wybierz rok (90 dni) --</option>';
            allRoki.forEach(function(rok) {
                var selected = rok.id == currentRokId ? 'selected' : '';
                html += '<option value="' + rok.id + '" ' + selected + '>' + rok.nazwa + ' (' + formatDate(rok.data_start) + ' - ' + formatDate(rok.data_koniec) + ')</option>';
            });
            $('#stats-rok-filter').html(html);
        }

        window.onRokFilterChange = function() {
            var rokId = $('#stats-rok-filter').val();
            var html = '<option value="">-- Wszystkie okresy --</option>';

            if (rokId) {
                var okresy = allOkresy.filter(function(o) { return o.rok_id == rokId; });
                okresy.forEach(function(okres) {
                    var selected = okres.id == currentOkresId ? 'selected' : '';
                    html += '<option value="' + okres.id + '" ' + selected + '>' + okres.nazwa + ' (' + formatDate(okres.data_start) + ' - ' + formatDate(okres.data_koniec) + ')</option>';
                });
            }
            $('#stats-okres-filter').html(html);
            // Auto-select current okres if available
            if (currentOkresId) {
                $('#stats-okres-filter').val(currentOkresId);
            }
            loadStats();
        };

        window.loadStats = function() {
            var rokId = $('#stats-rok-filter').val();
            var okresId = $('#stats-okres-filter').val();

            if (!rokId && !okresId) {
                $('#stats-categories').html('<p style="color: #888; text-align: center;">Wybierz rok lub okres aby zobaczyć statystyki...</p>');
                $('#okres-days-grid').html('');
                return;
            }

            var filterType = okresId ? 'okres' : 'rok';
            var filterId = okresId || rokId;

            $.post(ajaxurl, {
                action: 'zadaniomat_get_stats',
                nonce: nonce,
                filter_type: filterType,
                filter_id: filterId
            }, function(response) {
                if (response.success) {
                    renderStats(response.data);
                }
            });

            // Załaduj wizualizację dni
            $.post(ajaxurl, {
                action: 'zadaniomat_get_okres_days',
                nonce: nonce,
                filter_type: filterType,
                filter_id: filterId
            }, function(response) {
                if (response.success) {
                    renderOkresDays(response.data.days);
                }
            });
        };

        function renderOkresDays(days) {
            var html = '';
            days.forEach(function(d) {
                var isToday = d.date === today;
                var typeClass = d.is_free ? 'free' : 'working';
                var todayClass = isToday ? ' today' : '';
                var weekdayNames = ['', 'Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'Sb', 'Nd'];
                var title = d.date + ' (' + weekdayNames[d.weekday] + ') - ' + (d.is_free ? 'Wolne' : 'Roboczy');
                html += '<div class="okres-day-tile ' + typeClass + todayClass + '" title="' + title + '">' + d.day + '</div>';
            });
            $('#okres-days-grid').html(html);
        }

        // currentOkresId jest już zdefiniowane wyżej z PHP (linia 7318)

        function renderStats(data) {
            // Zapisz okres_id do późniejszego użycia przy zapisie godzin
            currentOkresId = data.okres_id;

            // Aktualizuj info o filtrze
            var filterText = data.filter_data.nazwa + ' (' + formatDate(data.filter_data.data_start) + ' - ' + formatDate(data.filter_data.data_koniec) + ')';
            $('#filter-info').text(filterText);

            // Podsumowanie ogólne
            var totalHours = (data.total.faktyczny_czas / 60).toFixed(1);
            $('#total-hours').text(totalHours + 'h');
            $('#total-tasks').text(data.total.liczba_zadan);
            $('#total-completed').text(data.total.ukonczone);
            $('#total-days').text(data.dni_w_okresie);
            // Pokaż info o dniach wolnych
            if (data.dni_wolne > 0) {
                $('#total-days-info').text('(- ' + data.dni_wolne + ' woln.)');
            } else {
                $('#total-days-info').text('');
            }

            // Statystyki per kategoria
            var html = '';
            var planowaneGodzinyMap = data.planowane_godziny || {};
            Object.keys(kategorie).forEach(function(kat) {
                // Pobierz planowane godziny z mapy cele_rok (priorytet)
                var planowaneGodziny = planowaneGodzinyMap[kat] || 1.0;

                var stats = data.stats_by_kategoria[kat] || {
                    liczba_zadan: 0,
                    ukonczone: 0,
                    faktyczny_czas: 0,
                    planowane_godziny_dziennie: planowaneGodziny,
                    planowane_w_okresie: planowaneGodziny * data.dni_w_okresie * 60,
                    procent_realizacji: 0
                };

                var planowaneWOkresieMin = planowaneGodziny * data.dni_w_okresie * 60;
                var faktycznyMin = stats.faktyczny_czas || 0;
                var procent = planowaneWOkresieMin > 0 ? Math.round((faktycznyMin / planowaneWOkresieMin) * 100) : 0;

                var progressClass = 'low';
                if (procent >= 100) progressClass = 'over';
                else if (procent >= 70) progressClass = 'high';
                else if (procent >= 40) progressClass = 'medium';

                var progressWidth = Math.min(procent, 100);

                html += '<div class="stat-category-card ' + kat + '">';
                html += '  <div class="stat-category-header">';
                html += '    <h4>' + kategorie[kat] + '</h4>';
                html += '    <button class="edit-hours-btn" onclick="toggleHoursEdit(\'' + kat + '\')">⏱️ Ustaw h/dzień</button>';
                html += '  </div>';
                html += '  <div class="stat-category-info">';
                html += '    <span>📋 ' + stats.liczba_zadan + ' zadań</span>';
                html += '    <span>✅ ' + stats.ukonczone + ' ukończ.</span>';
                html += '    <span>⏱️ ' + (faktycznyMin / 60).toFixed(1) + 'h przeprac.</span>';
                html += '  </div>';
                html += '  <div class="progress-container">';
                html += '    <div class="progress-bar-bg">';
                html += '      <div class="progress-bar-fill ' + progressClass + '" style="width: ' + progressWidth + '%">';
                if (procent >= 20) {
                    html += '        <span class="progress-label">' + procent + '%</span>';
                }
                html += '      </div>';
                if (procent < 20) {
                    html += '      <span class="progress-label">' + procent + '%</span>';
                }
                html += '    </div>';
                html += '    <div class="progress-details">';
                html += '      <span>Cel: ' + planowaneGodziny + 'h/dzień × ' + data.dni_w_okresie + ' dni = ' + (planowaneWOkresieMin / 60).toFixed(0) + 'h</span>';
                html += '      <span>Zrobione: ' + (faktycznyMin / 60).toFixed(1) + 'h</span>';
                html += '    </div>';
                html += '  </div>';
                html += '  <div class="hours-edit-row" id="hours-edit-' + kat + '" style="display: none;">';
                html += '    <label>Planowane h/dzień:</label>';
                html += '    <input type="number" step="0.25" min="0" max="24" value="' + planowaneGodziny + '" id="hours-input-' + kat + '">';
                html += '    <button class="btn-save-hours" onclick="savePlanowaneGodziny(\'' + kat + '\')">Zapisz</button>';
                html += '  </div>';
                html += '</div>';
            });

            $('#stats-categories').html(html);
        }

        window.toggleHoursEdit = function(kategoria) {
            var $row = $('#hours-edit-' + kategoria);
            $row.toggle();
        };

        window.savePlanowaneGodziny = function(kategoria) {
            if (!currentOkresId) {
                showToast('Najpierw wybierz okres', 'error');
                return;
            }

            var godziny = parseFloat($('#hours-input-' + kategoria).val()) || 1.0;

            $.post(ajaxurl, {
                action: 'zadaniomat_save_planowane_godziny',
                nonce: nonce,
                okres_id: currentOkresId,
                kategoria: kategoria,
                planowane_godziny_dziennie: godziny
            }, function(response) {
                if (response.success) {
                    showToast('Zapisano planowane godziny dla tego okresu!', 'success');
                    $('#hours-edit-' + kategoria).hide();
                    loadStats(); // Odśwież statystyki
                }
            });
        };

        function formatDate(dateStr) {
            var d = new Date(dateStr);
            return d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear();
        }
        
        // ==================== CALENDAR ====================
        window.renderCalendar = function() {
            var monthStart = currentMonth + '-01';
            var monthDate = new Date(monthStart);
            var year = monthDate.getFullYear();
            var month = monthDate.getMonth();
            var daysInMonth = new Date(year, month + 1, 0).getDate();
            var firstDayWeekday = new Date(year, month, 1).getDay();
            if (firstDayWeekday === 0) firstDayWeekday = 7; // Make Monday = 1
            
            $('#calendar-title').text(monthNames[month + 1] + ' ' + year);
            
            var html = '';
            ['Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So', 'Nd'].forEach(function(d) {
                html += '<div class="calendar-day-header">' + d + '</div>';
            });
            
            // Empty cells
            for (var i = 1; i < firstDayWeekday; i++) {
                html += '<div class="calendar-day other-month"></div>';
            }
            
            // Days
            for (var day = 1; day <= daysInMonth; day++) {
                var dateStr = currentMonth + '-' + String(day).padStart(2, '0');
                var classes = ['calendar-day'];
                if (dateStr === today) classes.push('today');
                if (dateStr === selectedDate) classes.push('selected');
                if (daysWithTasks.indexOf(dateStr) !== -1) classes.push('has-tasks');
                
                html += '<div class="' + classes.join(' ') + '" data-date="' + dateStr + '">' + day + '</div>';
            }
            
            $('#calendar-grid').html(html);
            
            // Load calendar dots
            loadCalendarDots();
        };
        
        window.loadCalendarDots = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_calendar_dots',
                nonce: nonce,
                month: currentMonth
            }, function(response) {
                if (response.success) {
                    daysWithTasks = response.data.days;
                    $('.calendar-day').each(function() {
                        var date = $(this).data('date');
                        if (daysWithTasks.indexOf(date) !== -1) {
                            $(this).addClass('has-tasks');
                        }
                    });
                }
            });
        };
        
        window.changeMonth = function(delta) {
            var parts = currentMonth.split('-');
            var date = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1 + delta, 1);
            currentMonth = date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0');
            renderCalendar();
        };
        
        window.goToToday = function() {
            selectedDate = today;
            currentMonth = today.substring(0, 7);
            renderCalendar();
            loadTasks();
            updateDateInfo();
            $('#task-date').val(today);
        };
        
        window.selectDate = function(date) {
            syncDates(date);
            $('.calendar-day').removeClass('selected');
            $('.calendar-day[data-date="' + date + '"]').addClass('selected');
            updateDateInfo();
        };

        window.updateDateInfo = function() {
            var d = new Date(selectedDate);
            $('#selected-date-display').text(d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear());
            $('#selected-day-name').text(dayNames[d.getDay()]);
            // Okres info could be loaded via AJAX if needed

            // Aktualizuj info o dniu roboczym/wolnym
            updateDayTypeInfo();
        };
        
        // ==================== TASKS ====================
        var staleZadaniaForSelectedDate = [];

        window.loadTasks = function() {
            var start = addDays(selectedDate, -1);
            var end = addDays(selectedDate, 5);

            $('#tasks-container').addClass('loading');
            $('#today-tasks-container').addClass('loading');

            // Pobierz zadania i stałe zadania dla wybranej daty równolegle
            var tasksRequest = $.post(ajaxurl, {
                action: 'zadaniomat_get_tasks',
                nonce: nonce,
                start: start,
                end: end
            });

            var staleRequest = $.post(ajaxurl, {
                action: 'zadaniomat_get_stale_for_day',
                nonce: nonce,
                dzien: selectedDate
            });

            $.when(tasksRequest, staleRequest).done(function(tasksResp, staleResp) {
                $('#tasks-container').removeClass('loading');
                $('#today-tasks-container').removeClass('loading');

                var tasks = tasksResp[0].success ? tasksResp[0].data.tasks : [];

                // Pobierz stałe zadania z opcją "dodaj do listy"
                staleZadaniaForSelectedDate = [];
                if (staleResp[0].success) {
                    staleZadaniaForSelectedDate = staleResp[0].data.stale_zadania.filter(function(s) {
                        return s.dodaj_do_listy == 1;
                    });
                }

                renderTasks(tasks, start, end);
            });
        };
        
        window.renderTasks = function(tasks, start, end) {
            // Group by day
            var byDay = {};
            tasks.forEach(function(t) {
                if (!byDay[t.dzien]) byDay[t.dzien] = [];
                byDay[t.dzien].push(t);
            });

            var todayHtml = '';
            var otherDaysHtml = '';
            var current = start;
            while (current <= end) {
                var dayTasks = byDay[current] || [];
                var isToday = (current === today);
                var isSelected = (current === selectedDate);
                var html = '';
                
                // Calculate stats
                var planned = 0, actual = 0;
                dayTasks.forEach(function(t) {
                    planned += parseInt(t.planowany_czas) || 0;
                    actual += parseInt(t.faktyczny_czas) || 0;
                });
                
                var d = new Date(current);
                var dayName = ['Nd', 'Pn', 'Wt', 'Śr', 'Cz', 'Pt', 'So'][d.getDay()];
                
                html += '<div class="day-section" data-day="' + current + '">';
                html += '<div class="day-header ' + (isToday ? 'today-header' : '') + '">';
                html += '<div class="day-header-left">';
                html += '<h3>';
                if (isToday) html += '🔵 ';
                if (isSelected) html += '📍 ';
                html += dayName + ', ' + d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear();
                if (isToday) html += ' <span style="font-weight:normal;font-size:12px;">(dziś)</span>';
                html += '</h3>';
                // Bulk actions
                if (dayTasks.length > 0) {
                    html += '<div class="bulk-actions" data-day="' + current + '">';
                    html += '<span class="selected-count"></span>';
                    html += '<button class="btn-bulk-delete" onclick="bulkDeleteTasks(\'' + current + '\')">🗑️ Usuń zaznaczone</button>';
                    html += '<button class="btn-bulk-copy" onclick="bulkCopyTasks(\'' + current + '\')">📄 Kopiuj zaznaczone do:</button>';
                    html += '<input type="date" class="bulk-copy-date" value="' + addDays(current, 1) + '">';
                    html += '</div>';
                }
                html += '</div>';
                html += '<div class="day-header-actions">';
                if (copiedTask) {
                    html += '<button class="btn-paste" onclick="pasteTask(\'' + current + '\')" title="Wklej skopiowane zadanie">📋 Wklej</button>';
                }
                if (dayTasks.length > 0) {
                    html += '<span class="day-stats">' + dayTasks.length + ' zadań • Plan: ' + planned + ' min • Fakt: ' + actual + ' min</span>';
                }
                html += '</div></div>';

                html += '<table class="day-table"><thead><tr>';
                // Zawsze dodaj kolumnę checkbox dla wybranej daty (lub gdy są zadania)
                if (isSelected || dayTasks.length > 0) {
                    html += '<th style="width:30px;">';
                    if (dayTasks.length > 0) {
                        html += '<input type="checkbox" class="select-all-checkbox" data-day="' + current + '" title="Zaznacz wszystkie">';
                    }
                    html += '</th>';
                }
                html += '<th style="width:130px;">Kategoria</th><th>Zadanie</th><th style="width:180px;">Cel TO DO</th>';
                html += '<th style="width:50px;">Plan</th><th style="width:70px;">Fakt</th><th style="width:50px;">✓</th><th style="width:90px;">Akcje</th>';
                html += '</tr></thead><tbody>';
                
                // Dla wybranej daty - pokaż sloty dla każdej kategorii
                if (isSelected) {
                    var usedKategorie = {};
                    dayTasks.forEach(function(t) { usedKategorie[t.kategoria] = true; });
                    var hiddenCategories = getHiddenCategories(current);

                    // Najpierw istniejące zadania
                    dayTasks.forEach(function(t) {
                        html += renderTaskRow(t, current);
                    });

                    // Potem stałe zadania z opcją "dodaj do listy"
                    staleZadaniaForSelectedDate.forEach(function(stale) {
                        // Sprawdź czy nie ma już zadania w tej kategorii (nie duplikuj)
                        if (!usedKategorie[stale.kategoria]) {
                            html += renderStaleTaskRow(stale, current);
                            usedKategorie[stale.kategoria] = true;
                        }
                    });

                    // Potem puste sloty dla brakujących kategorii (bez ukrytych)
                    var hiddenCount = 0;
                    for (var kat in kategorie) {
                        if (!usedKategorie[kat]) {
                            if (hiddenCategories.includes(kat)) {
                                hiddenCount++;
                            } else {
                                html += renderEmptySlot(current, kat, kategorie[kat]);
                            }
                        }
                    }

                    // Pokaż link do przywrócenia ukrytych kategorii
                    if (hiddenCount > 0) {
                        html += '<tr class="hidden-categories-notice"><td colspan="8" style="text-align:center;padding:10px;background:#f9f9f9;font-size:12px;">';
                        html += '<a href="#" onclick="showAllCategories(\'' + current + '\'); return false;">👁️ Pokaż ' + hiddenCount + ' ukryte kategorie</a>';
                        html += '</td></tr>';
                    }

                    // Dodaj wiersz szybkiego dodawania zadań
                    html += renderQuickAddRow(current);
                } else {
                    // Dla innych dni - normalne wyświetlanie
                    if (dayTasks.length === 0) {
                        html += '<tr><td colspan="7" class="empty-day-cell">Brak zadań <a href="#" onclick="selectDate(\'' + current + '\'); return false;">+ Dodaj</a>';
                        if (copiedTask) {
                            html += ' lub <a href="#" onclick="pasteTask(\'' + current + '\'); return false;">📋 Wklej</a>';
                        }
                        html += '</td></tr>';
                    } else {
                        dayTasks.forEach(function(t) {
                            html += renderTaskRow(t, current);
                        });
                    }
                }
                
                html += '</tbody></table></div>';

                // Rozdziel wybrane zadania od innych dni
                if (isSelected) {
                    todayHtml += html;
                } else {
                    otherDaysHtml += html;
                }

                current = addDays(current, 1);
            }

            // Renderuj do osobnych kontenerów
            $('#today-tasks-container').html(todayHtml || '<p style="color:#888;padding:20px;text-align:center;">Wybierz dziś w kalendarzu aby zobaczyć zadania</p>');
            $('#tasks-container').html(otherDaysHtml || '<p style="color:#888;padding:20px;text-align:center;">Brak zadań na inne dni</p>');

            // Aktualizuj podsumowanie godzin dla wybranej daty
            updateHoursSummary(byDay[selectedDate] || []);
        };

        window.updateHoursSummary = function(tasks) {
            var planned = 0, actual = 0, completed = 0, total = 0;
            tasks.forEach(function(t) {
                if (parseInt(t.planowany_czas) > 0) { // Tylko zadania z czasem (nie szablony)
                    total++;
                    planned += parseInt(t.planowany_czas) || 0;
                    actual += parseInt(t.faktyczny_czas) || 0;
                    if (t.status === 'zakonczone') completed++;
                }
            });

            var hoursWorked = Math.floor(actual / 60);
            var minsWorked = actual % 60;
            var hoursPlanned = Math.floor(planned / 60);
            var minsPlanned = planned % 60;

            $('#hours-worked').text(hoursWorked + 'h ' + minsWorked + 'min');
            $('#hours-planned').text(hoursPlanned + 'h ' + minsPlanned + 'min');
            $('#tasks-completed-count').text(completed + '/' + total);

            // Odśwież też panel dzienny po lewej
            refreshDailyProgressPanel();
        };

        // Odśwież panel progresu dziennego (lewy panel)
        window.refreshDailyProgressPanel = function() {
            var today = '<?php echo date('Y-m-d'); ?>';
            $.post(ajaxurl, {
                action: 'zadaniomat_get_daily_stats',
                nonce: nonce,
                date: today
            }, function(response) {
                if (response.success) {
                    var d = response.data;
                    $('#daily-progress-bar').css('width', d.procent + '%');
                    $('#daily-progress-text').text(d.cele_hours + 'h / ' + d.planned_hours + 'h (' + d.procent + '%)');
                    $('#daily-inne-hours').text(d.inne_hours);
                    $('#daily-razem-hours').text(d.razem_hours);
                    $('#daily-tasks-count').text(d.tasks_count);
                    $('#daily-tasks-done').text(d.tasks_done);
                }
            });
        };

        window.renderTaskRow = function(t, day) {
            var taskStatus = t.status || 'nowe';
            var statusClass = 'status-' + taskStatus;
            var isCyclic = t.jest_cykliczne == 1 || t.recurring_template_id;

            var planowany = parseInt(t.planowany_czas) || 0;
            var faktyczny = parseInt(t.faktyczny_czas) || 0;
            var isActiveTimer = activeTimer && activeTimer.taskId == t.id;

            var html = '<tr class="' + statusClass + (isCyclic ? ' is-cyclic' : '') + '" data-task-id="' + t.id + '">';
            html += '<td><input type="checkbox" class="task-checkbox" data-task-id="' + t.id + '" data-day="' + day + '"></td>';
            html += '<td><span class="kategoria-badge ' + t.kategoria + '">' + t.kategoria_label + '</span></td>';
            html += '<td><strong>' + escapeHtml(t.zadanie) + '</strong>' + (isCyclic ? ' <span class="cyclic-badge" title="Zadanie cykliczne">🔄</span>' : '') + '</td>';
            html += '<td style="font-size:12px;color:#666;">' + escapeHtml(t.cel_todo || '') + '</td>';

            // Kolumna czasu z timerem
            html += '<td class="task-timer-cell">';
            html += '<div class="timer-cell-content">';
            html += '<span>' + planowany + '</span>';
            if (planowany > 0) {
                html += '<button class="timer-btn' + (isActiveTimer ? ' running' : '') + '" onclick="startTimer(' + t.id + ', \'' + escapeHtml(t.zadanie).replace(/'/g, "\\'") + '\', ' + planowany + ', ' + faktyczny + ')" title="' + (isActiveTimer ? 'Timer działa' : 'Uruchom timer') + '">';
                html += isActiveTimer ? '⏸️' : '▶️';
                html += '</button>';
            }
            html += '</div></td>';

            // Faktyczny czas z możliwością edycji
            html += '<td class="task-timer-cell">';
            html += '<div class="timer-cell-content">';
            if (faktyczny > 0) {
                html += '<span class="time-tracked" onclick="editFaktycznyCzas(' + t.id + ', ' + faktyczny + ')" style="cursor:pointer;" title="Kliknij aby edytować">' + faktyczny + ' min</span>';
            } else {
                html += '<input type="number" class="inline-input quick-update" data-field="faktyczny_czas" data-id="' + t.id + '" value="" placeholder="-" min="0" style="width:50px;">';
            }
            // Przycisk do dodatkowej sesji timera
            if (planowany > 0 && !isActiveTimer) {
                html += '<button class="timer-btn" onclick="promptAdditionalTimer(' + t.id + ', \'' + escapeHtml(t.zadanie).replace(/'/g, "\\'") + '\', ' + faktyczny + ')" title="Dodaj czas">';
                html += '➕';
                html += '</button>';
            }
            html += '</div></td>';

            // Status - dropdown ze statusami
            html += '<td class="status-cell">';
            html += '<select class="status-select status-' + taskStatus + '" onchange="changeTaskStatus(' + t.id + ', this.value)">';
            html += '<option value="nowe"' + (taskStatus === 'nowe' ? ' selected' : '') + '>Nowe</option>';
            html += '<option value="rozpoczete"' + (taskStatus === 'rozpoczete' ? ' selected' : '') + '>Rozpoczęte</option>';
            html += '<option value="w_trakcie"' + (taskStatus === 'w_trakcie' ? ' selected' : '') + '>W trakcie (kont.)</option>';
            html += '<option value="zakonczone"' + (taskStatus === 'zakonczone' ? ' selected' : '') + '>Zakończone</option>';
            html += '<option value="niezrealizowane"' + (taskStatus === 'niezrealizowane' ? ' selected' : '') + '>Niezrealizowane</option>';
            html += '<option value="anulowane"' + (taskStatus === 'anulowane' ? ' selected' : '') + '>Anulowane</option>';
            html += '</select>';
            html += '</td>';
            html += '<td class="action-buttons">';
            html += '<button class="btn-copy" onclick="copyTaskToDate(' + t.id + ')" title="Kopiuj na inny dzień">📄</button>';
            html += '<button class="btn-edit" onclick="editTask(' + t.id + ', this)" title="Edytuj">✏️</button>';
            html += '<button class="btn-delete" onclick="deleteTask(' + t.id + ')" title="Usuń">🗑️</button>';
            html += '</td></tr>';
            return html;
        };

        // Renderuj wiersz stałego zadania (z opcją dodaj do listy)
        window.renderStaleTaskRow = function(stale, day) {
            var planowany = parseInt(stale.planowany_czas) || 0;

            var html = '<tr class="stale-task-row" data-stale-id="' + stale.id + '">';
            html += '<td></td>'; // Pusta komórka checkbox (stałe nie mają checkboxa)
            html += '<td><span class="kategoria-badge ' + stale.kategoria + '">' + stale.kategoria_label + '</span></td>';
            html += '<td><strong>' + escapeHtml(stale.nazwa) + '</strong> <span class="stale-badge">🔄 Stałe</span></td>';
            html += '<td style="font-size:12px;color:#666;">-</td>'; // Brak celu TODO dla stałych
            html += '<td>' + planowany + '</td>';
            html += '<td>-</td>'; // Brak faktycznego czasu
            html += '<td>-</td>'; // Brak statusu
            html += '<td class="action-buttons">';
            html += '<button class="btn-convert-stale" onclick="convertStaleToTask(' + stale.id + ', \'' + day + '\')" title="Przekształć w zadanie">📋</button>';
            html += '</td></tr>';
            return html;
        };

        // Przekształć stałe zadanie w zwykłe zadanie
        window.convertStaleToTask = function(staleId, day) {
            var stale = staleZadaniaForSelectedDate.find(function(s) { return s.id == staleId; });
            if (!stale) return;

            $.post(ajaxurl, {
                action: 'zadaniomat_add_task',
                nonce: nonce,
                dzien: day,
                kategoria: stale.kategoria,
                zadanie: stale.nazwa,
                cel_todo: '',
                planowany_czas: stale.planowany_czas || 0,
                jest_cykliczne: 1
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie utworzone ze stałego!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };

        // Prompt do dodatkowej sesji timera
        window.promptAdditionalTimer = function(taskId, taskName, currentMinutes) {
            var minutes = prompt('Na ile minut uruchomić timer?\n(Aktualny zapisany czas: ' + currentMinutes + ' min)', '15');
            if (minutes === null) return;

            var mins = parseInt(minutes);
            if (isNaN(mins) || mins <= 0) {
                alert('Podaj prawidłową liczbę minut');
                return;
            }

            // Pobierz aktualny faktyczny czas i ustaw jako elapsedBefore
            activeTimer = {
                taskId: taskId,
                taskName: taskName,
                plannedTime: mins * 60,
                startTime: Date.now(),
                elapsedBefore: currentMinutes * 60, // Poprzedni czas
                interval: null,
                notified: false
            };

            initTimerAudio();
            requestNotificationPermission();

            activeTimer.interval = setInterval(updateTimerDisplay, 1000);
            updateTimerDisplay();
            renderFloatingTimer();

            showToast('⏱️ Timer uruchomiony (+' + mins + ' min)', 'success');
        };
        
        // Pobierz ukryte kategorie dla danego dnia
        window.getHiddenCategories = function(day) {
            var key = 'zadaniomat_hidden_categories_' + day;
            var hidden = localStorage.getItem(key);
            return hidden ? JSON.parse(hidden) : [];
        };

        // Ukryj kategorię dla danego dnia
        window.hideEmptySlot = function(day, kategoria) {
            var key = 'zadaniomat_hidden_categories_' + day;
            var hidden = getHiddenCategories(day);
            if (!hidden.includes(kategoria)) {
                hidden.push(kategoria);
                localStorage.setItem(key, JSON.stringify(hidden));
            }
            loadTasks();
            showToast('Kategoria ukryta na dziś', 'success');
        };

        // Przywróć wszystkie ukryte kategorie dla dnia
        window.showAllCategories = function(day) {
            var key = 'zadaniomat_hidden_categories_' + day;
            localStorage.removeItem(key);
            loadTasks();
            showToast('Przywrócono wszystkie kategorie', 'success');
        };

        window.renderEmptySlot = function(day, kategoria, kategoriaLabel) {
            var html = '<tr class="empty-slot" data-day="' + day + '" data-kategoria="' + kategoria + '">';
            html += '<td></td>'; // Pusta komórka dla checkboxa
            html += '<td><span class="kategoria-badge ' + kategoria + '">' + kategoriaLabel + '</span></td>';
            html += '<td><input type="text" class="slot-input slot-zadanie" placeholder="Wpisz zadanie..." data-field="zadanie"></td>';
            html += '<td><input type="text" class="slot-input slot-cel" placeholder="Cel TO DO..." data-field="cel_todo"></td>';
            html += '<td><input type="number" class="slot-input slot-czas" placeholder="-" min="0" style="width:45px;" data-field="planowany_czas"></td>';
            html += '<td>-</td>';
            html += '<td>-</td>';
            html += '<td class="action-buttons">';
            html += '<button class="btn-add-slot" onclick="saveSlot(this)" title="Dodaj">➕</button>';
            html += '<button class="btn-hide-slot" onclick="hideEmptySlot(\'' + day + '\', \'' + kategoria + '\')" title="Ukryj na dziś">👁️‍🗨️</button>';
            html += '</td>';
            html += '</tr>';
            return html;
        };

        // Renderuj wiersz szybkiego dodawania z wyborem kategorii
        window.renderQuickAddRow = function(day) {
            var html = '<tr class="quick-add-row" data-day="' + day + '">';
            html += '<td></td>';
            html += '<td>';
            html += '<select class="quick-add-kategoria">';
            for (var kat in kategorie) {
                html += '<option value="' + kat + '">' + kategorie[kat] + '</option>';
            }
            html += '</select>';
            html += '</td>';
            html += '<td><input type="text" class="slot-input quick-add-zadanie" placeholder="Wpisz zadanie..." data-field="zadanie"></td>';
            html += '<td><input type="text" class="slot-input quick-add-cel" placeholder="Cel TO DO..." data-field="cel_todo"></td>';
            html += '<td><input type="number" class="slot-input quick-add-czas" placeholder="-" min="0" style="width:45px;" data-field="planowany_czas"></td>';
            html += '<td>-</td>';
            html += '<td>-</td>';
            html += '<td class="action-buttons">';
            html += '<button class="btn-add-slot" onclick="saveQuickAdd(this)" title="Dodaj">➕</button>';
            html += '</td>';
            html += '</tr>';
            return html;
        };

        // Zapisz zadanie z quick add wiersza
        window.saveQuickAdd = function(btn) {
            var $row = $(btn).closest('tr');
            var day = $row.data('day');
            var kategoria = $row.find('.quick-add-kategoria').val();
            var zadanie = $row.find('.quick-add-zadanie').val().trim();
            var cel = $row.find('.quick-add-cel').val().trim();
            var czas = $row.find('.quick-add-czas').val();

            if (!zadanie) {
                showToast('Wpisz nazwę zadania', 'error');
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_add_task',
                nonce: nonce,
                dzien: day,
                kategoria: kategoria,
                zadanie: zadanie,
                cel_todo: cel,
                planowany_czas: czas || 0
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie dodane!', 'success');
                    // Wyczyść pola
                    $row.find('.quick-add-zadanie').val('');
                    $row.find('.quick-add-cel').val('');
                    $row.find('.quick-add-czas').val('');
                    // Odśwież listę i harmonogram
                    loadTasks();
                    loadHarmonogram();
                } else {
                    showToast('Błąd: ' + response.data, 'error');
                }
            });
        };
        
        // Zapisz zadanie z empty slotu
        window.saveSlot = function(btn) {
            var $row = $(btn).closest('tr');
            var day = $row.data('day');
            var kategoria = $row.data('kategoria');
            var zadanieVal = $row.find('.slot-zadanie').val();
            var celVal = $row.find('.slot-cel').val();
            var zadanie = zadanieVal ? zadanieVal.trim() : '';
            var cel = celVal ? celVal.trim() : '';
            var czas = $row.find('.slot-czas').val() || 0;
            
            if (!zadanie) {
                showToast('Wpisz nazwę zadania!', 'error');
                $row.find('.slot-zadanie').focus();
                return;
            }
            
            $.post(ajaxurl, {
                action: 'zadaniomat_add_task',
                nonce: nonce,
                dzien: day,
                kategoria: kategoria,
                zadanie: zadanie,
                cel_todo: cel,
                planowany_czas: czas
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie dodane!', 'success');
                    loadTasks();
                    loadHarmonogram(); // Odśwież harmonogram
                    loadCalendarDots();
                }
            });
        };
        
        // Kopiuj zadanie
        window.copyTask = function(id, btn) {
            var $row = $(btn).closest('tr');
            var $daySection = $row.closest('.day-section');
            
            copiedTask = {
                kategoria: $row.find('.kategoria-badge').attr('class').replace('kategoria-badge ', '').trim(),
                zadanie: $row.find('strong').text(),
                cel_todo: $row.find('td:eq(2)').text(),
                planowany_czas: $row.find('td:eq(3)').text()
            };
            
            showToast('Zadanie skopiowane! Wybierz dzień i kliknij "Wklej"', 'success');
            
            // Odśwież żeby pokazać przyciski wklejania
            loadTasks();
        };
        
        // Wklej zadanie
        window.pasteTask = function(day) {
            if (!copiedTask) {
                showToast('Najpierw skopiuj jakieś zadanie!', 'error');
                return;
            }
            
            $.post(ajaxurl, {
                action: 'zadaniomat_add_task',
                nonce: nonce,
                dzien: day,
                kategoria: copiedTask.kategoria,
                zadanie: copiedTask.zadanie,
                cel_todo: copiedTask.cel_todo,
                planowany_czas: copiedTask.planowany_czas
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie wklejone!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };
        
        // ==================== OVERDUE ====================
        window.loadOverdueTasks = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_overdue',
                nonce: nonce
            }, function(response) {
                if (response.success && response.data.tasks.length > 0) {
                    renderOverdueTasks(response.data.tasks);
                } else {
                    $('#overdue-container').html('');
                }
            });
        };
        
        window.renderOverdueTasks = function(tasks) {
            var html = '<div class="overdue-alert">';
            html += '<h3>⚠️ Masz ' + tasks.length + ' nieukończonych zadań z przeszłości!</h3>';

            tasks.forEach(function(t) {
                var d = new Date(t.dzien);
                var taskStatus = t.status || 'nowe';
                html += '<div class="overdue-task" data-task-id="' + t.id + '">';
                html += '<div class="overdue-task-info">';
                html += '<div class="task-name">' + escapeHtml(t.zadanie) + '</div>';
                html += '<div class="task-meta">📅 ' + d.getDate() + '.' + (d.getMonth() + 1) + '.' + d.getFullYear() + ' • ';
                html += '<span class="kategoria-badge ' + t.kategoria + '">' + t.kategoria_label + '</span>';
                html += '</div></div>';
                html += '<div class="overdue-task-actions">';

                // Status - dropdown ze statusami
                html += '<span style="font-size:12px;color:#666;">Status:</span>';
                html += '<select class="status-select status-' + taskStatus + '" onchange="updateOverdueStatus(' + t.id + ', this.value)">';
                html += '<option value="nowe"' + (taskStatus === 'nowe' ? ' selected' : '') + '>Nowe</option>';
                html += '<option value="rozpoczete"' + (taskStatus === 'rozpoczete' ? ' selected' : '') + '>Rozpoczęte</option>';
                html += '<option value="w_trakcie"' + (taskStatus === 'w_trakcie' ? ' selected' : '') + '>W trakcie (kont.)</option>';
                html += '<option value="zakonczone"' + (taskStatus === 'zakonczone' ? ' selected' : '') + '>Zakończone</option>';
                html += '<option value="niezrealizowane"' + (taskStatus === 'niezrealizowane' ? ' selected' : '') + '>Niezrealizowane</option>';
                html += '<option value="anulowane"' + (taskStatus === 'anulowane' ? ' selected' : '') + '>Anulowane</option>';
                html += '</select>';

                // Kopiuj na inny dzień
                html += '<span style="font-size:12px;color:#666;margin-left:15px;">Kopiuj na:</span>';
                html += '<input type="date" class="copy-date" value="' + today + '" min="' + today + '">';
                html += '<button class="btn-copy" onclick="copyOverdueTask(' + t.id + ', this)" title="Skopiuj na wybrany dzień">📄 Kopiuj</button>';

                html += '</div></div>';
            });

            html += '</div>';
            $('#overdue-container').html(html);
        };

        // Kopiuj zaległe zadanie na nowy dzień
        window.copyOverdueTask = function(taskId, btn) {
            var $container = $(btn).closest('.overdue-task');
            var targetDate = $container.find('.copy-date').val();

            if (!targetDate) {
                showToast('Wybierz datę!', 'error');
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_copy_task_to_date',
                nonce: nonce,
                id: taskId,
                target_date: targetDate
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie skopiowane na ' + targetDate, 'success');
                    loadTasks();
                    loadCalendarDots();
                } else {
                    alert('Błąd podczas kopiowania: ' + (response.data || 'Nieznany błąd'));
                }
            });
        };
        
        window.moveOverdueTask = function(id, btn) {
            var $container = $(btn).closest('.overdue-task');
            var newDate = $container.find('.move-date').val();
            
            if (!newDate) { showToast('Wybierz datę!', 'error'); return; }
            
            $.post(ajaxurl, {
                action: 'zadaniomat_move_task',
                nonce: nonce,
                id: id,
                new_date: newDate
            }, function(response) {
                if (response.success) {
                    $container.slideUp(300, function() {
                        $(this).remove();
                        if ($('.overdue-task').length === 0) $('.overdue-alert').slideUp();
                    });
                    showToast('Zadanie przeniesione!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };
        
        window.updateOverdueStatus = function(id, status) {
            if (status === '') return;

            $.post(ajaxurl, {
                action: 'zadaniomat_quick_update',
                nonce: nonce,
                id: id,
                field: 'status',
                value: status
            }, function(response) {
                if (response.success) {
                    // Ukryj zadanie jeśli status to zakonczone, anulowane, w_trakcie lub niezrealizowane
                    if (status === 'zakonczone' || status === 'anulowane' || status === 'w_trakcie' || status === 'niezrealizowane') {
                        var $container = $('[data-task-id="' + id + '"].overdue-task');
                        $container.slideUp(300, function() {
                            $(this).remove();
                            if ($('.overdue-task').length === 0) $('.overdue-alert').slideUp();
                        });
                    }
                    var statusLabels = {
                        'nowe': 'Nowe',
                        'rozpoczete': 'Rozpoczęte',
                        'w_trakcie': 'W trakcie (kont.)',
                        'zakonczone': 'Zakończone',
                        'niezrealizowane': 'Niezrealizowane',
                        'anulowane': 'Anulowane'
                    };
                    showToast('Status: ' + statusLabels[status], 'success');
                    loadTasks();

                    // Gamification - pokaż XP popup jeśli zadanie ukończone
                    if (status === 'zakonczone' && response.data && response.data.gamification) {
                        showXpPopup(response.data.gamification);
                    }
                }
            });
        };
        
        // ==================== FORM HANDLING ====================
        window.bindEvents = function() {
            // Calendar click
            $(document).on('click', '.calendar-day:not(.other-month)', function() {
                var date = $(this).data('date');
                if (date) selectDate(date);
            });

            // Checkbox pojedynczego zadania
            $(document).on('change', '.task-checkbox', function() {
                var day = $(this).data('day');
                updateBulkActionsVisibility(day);
            });

            // Checkbox "zaznacz wszystkie"
            $(document).on('change', '.select-all-checkbox', function() {
                var day = $(this).data('day');
                var isChecked = $(this).prop('checked');
                var $daySection = $('.day-section[data-day="' + day + '"]');
                $daySection.find('.task-checkbox').prop('checked', isChecked);
                updateBulkActionsVisibility(day);
            });

            // Enter w slotach - zapisuje lub przechodzi do następnego pola
            $(document).on('keydown', '.slot-input', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    var $this = $(this);
                    var $row = $this.closest('tr');
                    var $next = $this.closest('td').next().find('.slot-input');
                    
                    if ($next.length) {
                        $next.focus();
                    } else {
                        // Ostatnie pole - zapisz
                        saveSlot($row.find('.btn-add-slot')[0]);
                    }
                }
            });
            
            // Quick update
            $(document).on('change', '.quick-update', function() {
                var $this = $(this);
                var $row = $this.closest('tr');
                var field = $this.data('field');
                var value = $this.val();

                $.post(ajaxurl, {
                    action: 'zadaniomat_quick_update',
                    nonce: nonce,
                    id: $this.data('id'),
                    field: field,
                    value: value
                }, function(response) {
                    if (response.success) {
                        $this.addClass('saved-flash');
                        setTimeout(function() { $this.removeClass('saved-flash'); }, 500);

                        // Odśwież dane jeśli zmieniono czas lub status
                        if (field === 'faktyczny_czas' || field === 'planowany_czas' || field === 'status') {
                            loadTasks();
                        }

                        // Gamification - pokaż XP popup jeśli zadanie ukończone
                        if (field === 'status' && value === 'zakonczone' && response.data && response.data.gamification) {
                            showXpPopup(response.data.gamification);
                        }
                    }
                });
            });
            
            // Form submit
            $('#task-form').on('submit', function(e) {
                e.preventDefault();
                
                var editId = $('#edit-task-id').val();
                var action = editId ? 'zadaniomat_edit_task' : 'zadaniomat_add_task';
                
                var data = {
                    action: action,
                    nonce: nonce,
                    dzien: $('#task-date').val(),
                    kategoria: $('#task-kategoria').val(),
                    zadanie: $('#task-nazwa').val(),
                    cel_todo: $('#task-cel').val(),
                    planowany_czas: $('#task-czas').val() || 0
                };
                
                if (editId) data.id = editId;
                
                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        showToast(editId ? 'Zadanie zaktualizowane!' : 'Zadanie dodane!', 'success');
                        resetForm();
                        loadTasks();
                        loadHarmonogram(); // Odśwież harmonogram
                        loadCalendarDots();
                        loadOverdueTasks();
                    }
                });
            });
            
            // Edytuj cel okresu - kliknięcie na tekst
            window.editCelOkres = function(element) {
                var $display = $(element);
                var $card = $display.closest('.cel-card');
                var $textarea = $card.find('.cel-okres-input');

                // Ukryj display, pokaż textarea
                $display.addClass('editing');
                $textarea.removeClass('hidden').focus();

                // Zaznacz tekst
                $textarea[0].select();
            };

            // Zapisz cel okresu i wróć do widoku
            $(document).on('blur', '.cel-okres-input', function() {
                var $textarea = $(this);
                var $card = $textarea.closest('.cel-card');
                var $display = $card.find('.cel-okres-display');
                var cel = $textarea.val().trim();

                // Ukryj textarea, pokaż display
                $textarea.addClass('hidden');
                $display.removeClass('editing');

                // Aktualizuj tekst display
                if (cel) {
                    $display.html(cel.replace(/\n/g, '<br>')).removeClass('empty');
                } else {
                    $display.html('<span class="placeholder">Kliknij aby dodać cel na 2 tygodnie...</span>').addClass('empty');
                }

                // Zapisz do bazy
                $.post(ajaxurl, {
                    action: 'zadaniomat_save_cel_okres',
                    nonce: nonce,
                    okres_id: $textarea.data('okres'),
                    kategoria: $textarea.data('kategoria'),
                    cel: cel
                }, function(response) {
                    if (response.success) {
                        showToast('Cel zapisany!', 'success');
                        if (response.data.cel_id) {
                            $textarea.data('cel-id', response.data.cel_id);
                            $display.data('cel-id', response.data.cel_id);
                        }
                    }
                });
            });

            // Zapisz też na Enter (z Shift+Enter dla nowej linii)
            $(document).on('keydown', '.cel-okres-input', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    $(this).blur();
                }
                if (e.key === 'Escape') {
                    $(this).blur();
                }
            });
            
            // Status celu okresu
            $(document).on('change', '.cel-okres-status', function() {
                var $this = $(this);
                $.post(ajaxurl, {
                    action: 'zadaniomat_update_cel_okres_status',
                    nonce: nonce,
                    id: $this.data('id'),
                    status: $this.val()
                }, function(response) {
                    if (response.success) {
                        $this.addClass('saved-flash');
                        setTimeout(function() { $this.removeClass('saved-flash'); }, 500);
                    }
                });
            });

            // Po zapisie celu, pokaż przycisk "Ukończ i dodaj nowy"
            $(document).on('blur', '.cel-okres-input', function() {
                var $textarea = $(this);
                var cel = $textarea.val().trim();
                var $card = $textarea.closest('.cel-card');
                var $btn = $card.find('.complete-goal-btn');

                if (cel) {
                    $btn.show();
                } else {
                    $btn.hide();
                }
            });
        };

        // ==================== WIELOKROTNE CELE ====================
        window.completeAndAddNew = function(btn) {
            var $btn = $(btn);
            var okresId = $btn.data('okres');
            var kategoria = $btn.data('kategoria');
            var $card = $btn.closest('.cel-card');
            var $display = $card.find('.cel-okres-display');
            var $textarea = $card.find('.cel-okres-input');
            var celId = $display.data('cel-id');

            if (!celId) {
                showToast('Najpierw zapisz cel', 'error');
                return;
            }

            // Oznacz cel jako ukończony
            $.post(ajaxurl, {
                action: 'zadaniomat_complete_goal',
                nonce: nonce,
                cel_id: celId
            }, function(response) {
                if (response.success) {
                    // Wyczyść pole tekstowe dla nowego celu
                    $textarea.val('');
                    $display.html('<span class="placeholder">Kliknij aby dodać kolejny cel...</span>').addClass('empty');
                    $display.data('cel-id', '');
                    $textarea.data('cel-id', '');
                    $btn.hide();

                    // Odśwież licznik i listę ukończonych
                    loadGoalsSummary(okresId, kategoria);

                    showToast('Cel ukończony! Dodaj kolejny.', 'success');

                    // Gamification - pokaż XP popup za osiągnięcie celu
                    if (response.data && response.data.gamification) {
                        showXpPopup(response.data.gamification);
                    }
                }
            });
        };

        // Przełącz status celu (z dashboardu)
        window.toggleGoalStatus = function(celId, element) {
            var $el = $(element);
            var $item = $el.closest('.goal-panel-item');
            var currentStatus = $item.hasClass('goal-achieved') ? '1' : ($item.hasClass('goal-not-achieved') ? '0' : '');

            // Cykl: puste -> tak -> nie -> puste
            var newStatus = '';
            if (currentStatus === '') newStatus = '1';
            else if (currentStatus === '1') newStatus = '0';
            else newStatus = '';

            $.post(ajaxurl, {
                action: 'zadaniomat_save_cel_podsumowanie',
                nonce: nonce,
                cel_id: celId,
                okres_id: currentOkresId,
                kategoria: '',
                osiagniety: newStatus,
                uwagi: ''
            }, function(response) {
                if (response.success) {
                    // Aktualizuj wygląd
                    $item.removeClass('goal-achieved goal-not-achieved');
                    if (newStatus === '1') {
                        $item.addClass('goal-achieved');
                        $el.text('✅');
                    } else if (newStatus === '0') {
                        $item.addClass('goal-not-achieved');
                        $el.text('❌');
                    } else {
                        $el.text('⬜');
                    }
                    showToast('Status zapisany!', 'success');
                }
            });
        };

        window.loadGoalsSummary = function(okresId, kategoria) {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_category_goals',
                nonce: nonce,
                okres_id: okresId,
                kategoria: kategoria
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    var $counter = $('#counter-' + kategoria);
                    var $completed = $('#completed-' + kategoria);

                    if (data.completed_count > 0) {
                        $counter.text('x' + data.completed_count).show();

                        // Pokaż listę ukończonych celów z możliwością edycji
                        var html = '<div style="font-size:11px; color:#666; margin-bottom:5px;"><strong>Ukończone:</strong></div>';
                        data.cele.forEach(function(cel) {
                            if (cel.completed_at) {
                                html += '<div class="completed-goal-item" data-cel-id="' + cel.id + '" data-kategoria="' + kategoria + '" style="font-size:11px; color:#28a745; padding:3px 6px; background:#f0fff4; border-radius:4px; margin-bottom:3px; cursor:pointer; display:flex; align-items:center; gap:5px;" title="Kliknij aby edytować">';
                                html += '<span style="flex:1;">✓ ' + escapeHtml(cel.cel.substring(0, 50)) + (cel.cel.length > 50 ? '...' : '') + '</span>';
                                html += '<button onclick="event.stopPropagation(); editCompletedGoal(' + cel.id + ', \'' + kategoria + '\')" class="edit-completed-btn" style="background:none; border:none; cursor:pointer; font-size:10px; padding:2px 4px;" title="Edytuj">✏️</button>';
                                html += '<button onclick="event.stopPropagation(); uncompleteGoal(' + cel.id + ', \'' + kategoria + '\')" class="uncomplete-btn" style="background:none; border:none; cursor:pointer; font-size:10px; padding:2px 4px; color:#dc3545;" title="Cofnij ukończenie">↩️</button>';
                                html += '</div>';
                            }
                        });
                        $completed.html(html).show();
                    } else {
                        $counter.hide();
                        $completed.hide();
                    }
                }
            });
        };

        // Załaduj podsumowanie celów przy starcie
        window.loadAllGoalsSummaries = function() {
            var okresId = currentOkresId;
            if (!okresId) return;

            Object.keys(kategorie).forEach(function(kat) {
                loadGoalsSummary(okresId, kat);
            });
        };

        // Edytuj ukończony cel
        window.editCompletedGoal = function(celId, kategoria) {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_cel_by_id',
                nonce: nonce,
                cel_id: celId
            }, function(response) {
                if (response.success) {
                    var cel = response.data;
                    var newText = prompt('Edytuj cel:', cel.cel);
                    if (newText !== null && newText.trim() !== '') {
                        $.post(ajaxurl, {
                            action: 'zadaniomat_update_cel_text',
                            nonce: nonce,
                            cel_id: celId,
                            cel: newText.trim()
                        }, function(resp) {
                            if (resp.success) {
                                loadGoalsSummary(currentOkresId, kategoria);
                                showToast('Cel zaktualizowany!', 'success');
                            }
                        });
                    }
                }
            });
        };

        // Cofnij ukończenie celu (przywróć jako aktywny)
        window.uncompleteGoal = function(celId, kategoria) {
            if (!confirm('Czy na pewno chcesz cofnąć ukończenie tego celu? Stanie się ponownie aktywnym celem.')) {
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_uncomplete_goal',
                nonce: nonce,
                cel_id: celId
            }, function(response) {
                if (response.success) {
                    // Odśwież widok
                    loadGoalsSummary(currentOkresId, kategoria);

                    // Ustaw ten cel jako aktywny w karcie
                    var $card = $('.cel-card[data-kategoria="' + kategoria + '"]');
                    var $display = $card.find('.cel-okres-display');
                    var $textarea = $card.find('.cel-okres-input');

                    $display.html(escapeHtml(response.data.cel)).removeClass('empty');
                    $display.data('cel-id', celId);
                    $textarea.val(response.data.cel);
                    $textarea.data('cel-id', celId);
                    $card.find('.complete-goal-btn').show();

                    showToast('Cel przywrócony jako aktywny!', 'success');
                }
            });
        };

        // ==================== POWIADOMIENIA O NIEOZNACZONYCH CELACH ====================
        window.checkUnmarkedGoals = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_unmarked_goals',
                nonce: nonce
            }, function(response) {
                if (response.success && response.data.unmarked && response.data.unmarked.length > 0) {
                    showUnmarkedGoalsAlert(response.data.unmarked);
                }
            });
        };

        window.showUnmarkedGoalsAlert = function(goals) {
            var html = '<div class="unmarked-goals-alert" style="position:fixed; top:80px; right:20px; width:350px; background:#fff3cd; border:2px solid #ffc107; border-radius:12px; padding:15px; box-shadow:0 4px 20px rgba(0,0,0,0.2); z-index:9999;">';
            html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">';
            html += '<strong style="color:#856404;">⚠️ Nieoznaczone cele!</strong>';
            html += '<button onclick="$(this).closest(\'.unmarked-goals-alert\').fadeOut()" style="background:none; border:none; font-size:18px; cursor:pointer; color:#856404;">&times;</button>';
            html += '</div>';
            html += '<p style="font-size:12px; color:#856404; margin-bottom:10px;">Masz cele z zakończonych okresów, które nie zostały oznaczone jako osiągnięte/nieosiągnięte:</p>';
            html += '<div style="max-height:200px; overflow-y:auto;">';

            goals.forEach(function(goal) {
                html += '<div style="background:#fff; padding:8px; border-radius:6px; margin-bottom:5px; font-size:12px;">';
                html += '<div style="color:#666; font-size:10px;">' + goal.okres_nazwa + ' | ' + goal.kategoria_label + '</div>';
                html += '<div style="color:#333;">' + escapeHtml(goal.cel.substring(0, 60)) + (goal.cel.length > 60 ? '...' : '') + '</div>';
                html += '<div style="margin-top:5px;">';
                html += '<button onclick="markGoalAchieved(' + goal.id + ', 1, this)" class="button button-small" style="font-size:10px; background:#28a745; color:#fff; border:none;">✓ Osiągnięty</button> ';
                html += '<button onclick="markGoalAchieved(' + goal.id + ', 0, this)" class="button button-small" style="font-size:10px; background:#dc3545; color:#fff; border:none;">✗ Nie osiągnięty</button>';
                html += '</div>';
                html += '</div>';
            });

            html += '</div></div>';

            // Usuń poprzedni alert jeśli istnieje
            $('.unmarked-goals-alert').remove();
            $('body').append(html);
        };

        window.markGoalAchieved = function(goalId, osiagniety, btn) {
            var $goalDiv = $(btn).closest('div').parent();

            $.post(ajaxurl, {
                action: 'zadaniomat_save_cel_podsumowanie',
                nonce: nonce,
                okres_id: 0, // nie używamy, bo mamy już id celu
                kategoria: '',
                osiagniety: osiagniety,
                uwagi: ''
            });

            // Użyj bezpośredniego update
            $.post(ajaxurl, {
                action: 'zadaniomat_update_cel_okres_status',
                nonce: nonce,
                id: goalId,
                osiagniety: osiagniety
            }, function(response) {
                if (response.success) {
                    $goalDiv.fadeOut(300, function() {
                        $(this).remove();
                        // Jeśli nie ma więcej celów, ukryj alert
                        if ($('.unmarked-goals-alert > div:last-child > div').length === 0) {
                            $('.unmarked-goals-alert').fadeOut();
                        }
                    });
                    showToast(osiagniety ? 'Cel oznaczony jako osiągnięty' : 'Cel oznaczony jako nieosiągnięty', 'success');
                }
            });
        };

        // ==================== DNI WOLNE / ROBOCZE ====================
        window.toggleDzienWolny = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_toggle_dzien_wolny',
                nonce: nonce,
                dzien: selectedDate
            }, function(response) {
                if (response.success) {
                    updateDayTypeInfo();
                    renderCalendar(); // Odśwież kalendarz
                    showToast(response.data.is_wolny ? 'Dzień oznaczony jako wolny' : 'Dzień oznaczony jako roboczy', 'success');
                }
            });
        };

        window.updateDayTypeInfo = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_is_dzien_roboczy',
                nonce: nonce,
                dzien: selectedDate
            }, function(response) {
                if (response.success) {
                    var data = response.data;
                    var $btn = $('#toggle-day-type-btn');
                    var $info = $('#day-type-info');

                    if (data.is_roboczy) {
                        $btn.text('🔄 Oznacz jako dzień wolny');
                        $info.html('<span style="color:#28a745;">✓ Dzień roboczy</span>');
                    } else {
                        $btn.text('🔄 Oznacz jako dzień roboczy');
                        $info.html('<span style="color:#dc3545;">✗ Dzień wolny</span>');
                    }

                    if (data.is_weekend) {
                        $info.append(' <span style="color:#888;">(weekend)</span>');
                    }
                }
            });
        };

        // ==================== EDIT / DELETE ====================
        window.editTask = function(id, btn) {
            var $row = $(btn).closest('tr');
            var $daySection = $row.closest('.day-section');

            // Get task data from row
            var kategoria = $row.find('.kategoria-badge').attr('class').replace('kategoria-badge ', '').trim();
            var zadanie = $row.find('strong').text();
            var cel = $row.find('td:eq(2)').text();
            var plan = $row.find('td:eq(3)').text();
            var dzien = $daySection.data('day');

            $('#edit-task-id').val(id);
            $('#task-date').val(dzien);
            $('#task-kategoria').val(kategoria);
            $('#task-nazwa').val(zadanie);
            $('#task-cel').val(cel);
            $('#task-czas').val(plan);

            $('#form-title').text('✏️ Edytuj zadanie');
            $('#submit-btn').text('💾 Zapisz zmiany');
            $('#cancel-edit-btn').show();

            $('html, body').animate({ scrollTop: $('.task-form').offset().top - 50 }, 300);
        };
        
        window.cancelEdit = function() {
            resetForm();
        };
        
        window.resetForm = function() {
            $('#edit-task-id').val('');
            $('#task-nazwa').val('');
            $('#task-cel').val('');
            $('#task-czas').val('');
            $('#form-title').text('➕ Dodaj zadanie');
            $('#submit-btn').text('➕ Dodaj zadanie');
            $('#cancel-edit-btn').hide();
        };
        
        window.deleteTask = function(id) {
            if (!confirm('Na pewno usunąć to zadanie?')) return;

            $.post(ajaxurl, {
                action: 'zadaniomat_delete_task',
                nonce: nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie usunięte!', 'success');
                    // Odśwież wszystko
                    loadTasks();
                    loadHarmonogram();
                    loadCalendarDots();
                }
            });
        };

        // Funkcja do przeliczania statystyk dnia
        window.recalculateDayStats = function($daySection) {
            var $rows = $daySection.find('tr[data-task-id]');
            var taskCount = $rows.length;
            var planned = 0, actual = 0;

            $rows.each(function() {
                var $row = $(this);
                // Planowany czas jest w 5. kolumnie (index 4) - po dodaniu kolumny checkbox
                var planText = $row.find('td:eq(4)').text();
                planned += parseInt(planText) || 0;
                // Faktyczny czas jest w input w 6. kolumnie
                var actualVal = $row.find('input[data-field="faktyczny_czas"]').val();
                actual += parseInt(actualVal) || 0;
            });

            var $stats = $daySection.find('.day-stats');
            if (taskCount > 0) {
                $stats.text(taskCount + ' zadań • Plan: ' + planned + ' min • Fakt: ' + actual + ' min');
            } else {
                $stats.text('');
            }
        };

        // ==================== BULK ACTIONS ====================
        // Aktualizuj widoczność przycisków akcji zbiorowych
        window.updateBulkActionsVisibility = function(day) {
            var $daySection = $('.day-section[data-day="' + day + '"]');
            var $checkboxes = $daySection.find('.task-checkbox:checked');
            var $bulkActions = $daySection.find('.bulk-actions');
            var $selectedCount = $bulkActions.find('.selected-count');

            if ($checkboxes.length > 0) {
                $bulkActions.addClass('visible');
                $selectedCount.text($checkboxes.length + ' zaznaczonych');
            } else {
                $bulkActions.removeClass('visible');
                $selectedCount.text('');
            }
        };

        // Zbiorowe usuwanie zadań
        window.bulkDeleteTasks = function(day) {
            var $daySection = $('.day-section[data-day="' + day + '"]');
            var $checkboxes = $daySection.find('.task-checkbox:checked');
            var ids = [];

            $checkboxes.each(function() {
                ids.push($(this).data('task-id'));
            });

            if (ids.length === 0) {
                showToast('Zaznacz najpierw zadania do usunięcia!', 'error');
                return;
            }

            if (!confirm('Na pewno usunąć ' + ids.length + ' zadań?')) return;

            $.post(ajaxurl, {
                action: 'zadaniomat_bulk_delete',
                nonce: nonce,
                ids: ids
            }, function(response) {
                if (response.success) {
                    showToast('Usunięto ' + response.data.deleted + ' zadań!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };

        // Zbiorowe kopiowanie zadań
        window.bulkCopyTasks = function(day) {
            var $daySection = $('.day-section[data-day="' + day + '"]');
            var $checkboxes = $daySection.find('.task-checkbox:checked');
            var targetDate = $daySection.find('.bulk-copy-date').val();
            var ids = [];

            $checkboxes.each(function() {
                ids.push($(this).data('task-id'));
            });

            if (ids.length === 0) {
                showToast('Zaznacz najpierw zadania do skopiowania!', 'error');
                return;
            }

            if (!targetDate) {
                showToast('Wybierz datę docelową!', 'error');
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_bulk_copy',
                nonce: nonce,
                ids: ids,
                target_date: targetDate
            }, function(response) {
                if (response.success) {
                    showToast('Skopiowano ' + response.data.copied + ' zadań na ' + targetDate + '!', 'success');
                    loadTasks();
                    loadCalendarDots();
                }
            });
        };

        // ==================== HELPERS ====================
        window.addDays = function(dateStr, days) {
            var d = new Date(dateStr);
            d.setDate(d.getDate() + days);
            return d.toISOString().split('T')[0];
        };
        
        window.escapeHtml = function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        window.showToast = function(message, type) {
            var toast = $('<div class="toast ' + type + '">' + message + '</div>');
            $('#toast-container').append(toast);
            setTimeout(function() { toast.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        };

        // ==================== HARMONOGRAM DNIA ====================
        var startDnia = null;
        var harmonogramView = 'timeline';
        var harmonogramTasks = [];
        var harmonogramStale = [];
        var draggedTask = null;
        var harmonogramDate = today; // Data aktualnie wyświetlana w harmonogramie

        // Sprawdź czy pokazać harmonogram
        window.checkShowHarmonogram = function() {
            $('#harmonogram-section').show();
            checkStartDnia();
        };

        // Zmiana daty harmonogramu - synchronizuje też z listą zadań
        window.changeHarmonogramDate = function(delta) {
            var currentDate = new Date(harmonogramDate);
            currentDate.setDate(currentDate.getDate() + delta);
            var newDate = currentDate.toISOString().split('T')[0];
            syncDates(newDate);
        };

        window.loadHarmonogramForDate = function(dateStr) {
            syncDates(dateStr);
        };

        window.goToTodayHarmonogram = function() {
            syncDates(today);
        };

        // Synchronizacja dat między harmonogramem a listą zadań
        window.syncDates = function(dateStr) {
            selectedDate = dateStr;
            harmonogramDate = dateStr;

            // Aktualizuj wszystkie kontrolki daty
            $('#harmonogram-date').val(dateStr);
            $('#tasks-list-date').val(dateStr);
            $('#task-date').val(dateStr);

            // Aktualizuj kalendarz
            $('.calendar-day').removeClass('selected');
            $('.calendar-day[data-date="' + dateStr + '"]').addClass('selected');

            // Aktualizuj nagłówki
            updateHarmonogramHeader();
            updateTasksHeader();

            // Przeładuj dane
            loadTasks();
            checkStartDnia();
            loadMorningChecklist();
        };

        window.updateTasksHeader = function() {
            var isToday = selectedDate === today;
            var headerText = isToday ? '📋 Zadania na dziś' : '📋 Zadania: ' + selectedDate;
            $('#today-tasks-section .tasks-header h2').text(headerText);
        };

        window.updateHarmonogramHeader = function() {
            var isToday = harmonogramDate === today;
            var dateObj = new Date(harmonogramDate);
            var days = ['niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota'];
            var dayName = days[dateObj.getDay()];
            var headerText = isToday ? '📅 Harmonogram dnia' : '📅 Harmonogram: ' + dayName + ' ' + harmonogramDate;
            $('#harmonogram-section h2').contents().first().replaceWith(headerText + ' ');
        };

        // ==================== NAWIGACJA DATY LISTY ZADAŃ ====================
        window.changeTasksDate = function(delta) {
            var currentDate = new Date(selectedDate);
            currentDate.setDate(currentDate.getDate() + delta);
            var newDate = currentDate.toISOString().split('T')[0];
            syncDates(newDate);
        };

        window.loadTasksForDate = function(dateStr) {
            syncDates(dateStr);
        };

        window.goToTodayTasks = function() {
            syncDates(today);
        };

        // Sprawdź czy użytkownik ustawił godzinę startu
        window.checkStartDnia = function() {
            var isToday = harmonogramDate === today;

            // Sprawdź localStorage najpierw
            var savedStart = localStorage.getItem('zadaniomat_start_' + harmonogramDate);
            if (savedStart) {
                startDnia = savedStart;
                updateStartBadge();
                loadHarmonogram();
                return;
            }

            // Sprawdź w bazie
            $.post(ajaxurl, {
                action: 'zadaniomat_get_start_dnia',
                nonce: nonce,
                dzien: harmonogramDate
            }, function(response) {
                if (response.success && response.data.godzina) {
                    startDnia = response.data.godzina;
                    localStorage.setItem('zadaniomat_start_' + harmonogramDate, startDnia);
                    updateStartBadge();
                    loadHarmonogram();
                } else if (isToday) {
                    // Pokaż modal startu dnia tylko dla dzisiaj
                    showStartDayModal();
                } else {
                    // Dla innych dni użyj domyślnej godziny jeśli nie ma zapisanej
                    startDnia = '09:00';
                    updateStartBadge();
                    loadHarmonogram();
                }
            });
        };

        // Modal startu dnia
        window.showStartDayModal = function() {
            var now = new Date();
            var currentTime = String(now.getHours()).padStart(2, '0') + ':' + String(now.getMinutes()).padStart(2, '0');

            var html = '<div class="start-day-modal-overlay" onclick="closeStartDayModal(event)">';
            html += '<div class="start-day-modal" onclick="event.stopPropagation()">';
            html += '<h2>☀️ Dzień dobry!</h2>';
            html += '<p>O której godzinie zaczynasz dzisiaj pracę?</p>';
            html += '<div class="current-time">' + currentTime + '</div>';
            html += '<div style="margin: 20px 0;">';
            html += '<input type="time" id="start-time-input" value="' + currentTime + '">';
            html += '</div>';
            html += '<button class="btn-start-now" onclick="setStartDnia()">🚀 Zaczynam!</button>';
            html += '<br><button class="btn-skip" onclick="skipStartDnia()">Pomiń na dziś</button>';
            html += '</div></div>';

            $('#start-day-modal-container').html(html);
        };

        window.closeStartDayModal = function(event) {
            if (event && event.target !== event.currentTarget) return;
            $('#start-day-modal-container').html('');
        };

        window.setStartDnia = function() {
            startDnia = $('#start-time-input').val();

            // Zapisz w localStorage
            localStorage.setItem('zadaniomat_start_' + harmonogramDate, startDnia);

            // Zapisz w bazie
            $.post(ajaxurl, {
                action: 'zadaniomat_save_start_dnia',
                nonce: nonce,
                dzien: harmonogramDate,
                godzina: startDnia
            });

            closeStartDayModal();
            updateStartBadge();
            loadHarmonogram();
            showToast('Dzień rozpoczęty o ' + startDnia + '!', 'success');
        };

        window.skipStartDnia = function() {
            startDnia = null;
            localStorage.setItem('zadaniomat_start_' + harmonogramDate, 'skipped');
            closeStartDayModal();
            $('#harmonogram-section').hide();
        };

        window.updateStartBadge = function() {
            if (startDnia && startDnia !== 'skipped') {
                $('#start-time-badge').text('Start: ' + startDnia);
            }
        };

        // Załaduj harmonogram
        window.loadHarmonogram = function() {
            if (!startDnia || startDnia === 'skipped') return;

            $.post(ajaxurl, {
                action: 'zadaniomat_get_harmonogram',
                nonce: nonce,
                dzien: harmonogramDate
            }, function(response) {
                if (response.success) {
                    harmonogramTasks = response.data.zadania;
                    // Filtruj stałe zadania - te z "dodaj do listy" nie pokazuj w harmonogramie
                    // bo są już widoczne w liście zadań
                    harmonogramStale = response.data.stale_zadania.filter(function(s) {
                        return s.dodaj_do_listy != 1;
                    });
                    loadStaleModifications(); // Zastosuj modyfikacje dla wybranego dnia
                    renderHarmonogram();
                }
            });
        };

        // Renderuj harmonogram
        window.renderHarmonogram = function() {
            if (harmonogramView === 'timeline') {
                renderTimelineView();
            } else {
                renderListView();
            }
            renderUnscheduledTasks();
            updateCurrentTimeLine();
        };

        // Renderuj widok timeline
        window.renderTimelineView = function() {
            var startHour = parseInt(startDnia.split(':')[0]);
            var endHour = 20; // Domyślny koniec dnia

            var html = '';

            // Stwórz godziny
            for (var h = startHour; h <= endHour; h++) {
                var hourStr = String(h).padStart(2, '0') + ':00';
                var tasksInHour = getTasksForHour(h);

                html += '<div class="timeline-hour" data-hour="' + h + '">';
                html += '<div class="timeline-hour-label">' + hourStr + '</div>';
                html += '<div class="timeline-hour-content" ondragover="handleDragOver(event)" ondrop="handleDrop(event, ' + h + ')" ondragleave="handleDragLeave(event)">';

                // Renderuj zadania w tej godzinie
                tasksInHour.forEach(function(task) {
                    html += renderHarmonogramTask(task);
                });

                // Renderuj stałe zadania w tej godzinie
                harmonogramStale.forEach(function(stale) {
                    if (stale.godzina_start) {
                        var staleHour = parseInt(stale.godzina_start.split(':')[0]);
                        if (staleHour === h) {
                            html += renderHarmonogramTask(stale, true);
                        }
                    }
                });

                html += '</div></div>';
            }

            $('#harmonogram-timeline').html(html);
        };

        // Pobierz zadania dla godziny
        window.getTasksForHour = function(hour) {
            return harmonogramTasks.filter(function(task) {
                if (task.godzina_start) {
                    var taskHour = parseInt(task.godzina_start.split(':')[0]);
                    return taskHour === hour;
                }
                return false;
            });
        };

        // Renderuj pojedyncze zadanie w harmonogramie
        window.renderHarmonogramTask = function(task, isStale) {
            var staleClass = isStale ? ' is-stale' : '';
            var taskId = isStale ? 'stale-' + task.id : task.id;
            var draggable = 'draggable="true" ondragstart="handleDragStart(event, \'' + taskId + '\')"';

            // Sprawdź status zadania
            var taskStatus = !isStale ? (task.status || 'nowe') : 'nowe';
            var isDone = taskStatus === 'zakonczone';
            var isAnulowane = taskStatus === 'anulowane';
            var statusClass = !isStale ? ' harmonogram-status-' + taskStatus : '';

            var html = '<div class="harmonogram-task ' + task.kategoria + staleClass + statusClass + '" data-id="' + taskId + '" data-is-stale="' + (isStale ? '1' : '0') + '" ' + draggable + '>';

            // Checkbox dla szybkiego oznaczenia jako zakończone (tylko dla zwykłych zadań)
            if (!isStale) {
                html += '<input type="checkbox" class="harmonogram-task-checkbox" ' + (isDone ? 'checked' : '') + ' onchange="toggleHarmonogramTaskDone(' + task.id + ', this.checked)" title="Oznacz jako ' + (isDone ? 'niewykonane' : 'zakończone') + '">';
            }

            html += '<div class="harmonogram-task-info">';
            html += '<div class="harmonogram-task-name">' + escapeHtml(task.zadanie || task.nazwa) + '</div>';
            html += '<div class="harmonogram-task-meta">';
            html += '<span class="kategoria-badge ' + task.kategoria + '">' + task.kategoria_label + '</span>';
            if (task.planowany_czas) {
                html += '<span>⏱️ ' + task.planowany_czas + ' min</span>';
            }
            if (isStale) {
                html += '<span class="stale-badge">🔄 Stałe</span>';
            }
            if (isDone) {
                html += '<span class="done-badge">✅</span>';
            }
            if (isAnulowane) {
                html += '<span class="anulowane-badge">❌</span>';
            }
            html += '</div></div>';

            // Edytowalna godzina
            if (task.godzina_start) {
                var startTime = task.godzina_start.substring(0, 5);
                var endTime = task.godzina_koniec ? task.godzina_koniec.substring(0, 5) : '';

                html += '<div class="harmonogram-task-time-edit">';
                html += '<input type="time" class="time-input-small" value="' + startTime + '" onchange="updateTaskTime(\'' + taskId + '\', this.value, \'' + (isStale ? '1' : '0') + '\')" title="Zmień godzinę rozpoczęcia">';
                if (endTime) {
                    html += '<span style="margin: 0 3px;">-</span>';
                    html += '<span class="end-time">' + endTime + '</span>';
                }
                html += '</div>';
            }

            // Akcje dla wszystkich zadań (nie tylko zwykłych)
            html += '<div class="harmonogram-task-actions">';
            if (isStale) {
                html += '<button onclick="hideStaleForToday(\'' + task.id + '\')" title="Ukryj dzisiaj">👁️‍🗨️</button>';
            } else {
                html += '<button onclick="removeFromHarmonogram(' + task.id + ')" title="Usuń z harmonogramu">❌</button>';
            }
            html += '</div>';

            html += '</div>';
            return html;
        };

        // Zmień status zadania (nowe, rozpoczete, zakonczone, anulowane)
        window.changeTaskStatus = function(taskId, newStatus) {
            $.post(ajaxurl, {
                action: 'zadaniomat_quick_update',
                nonce: nonce,
                id: taskId,
                field: 'status',
                value: newStatus
            }, function(response) {
                if (response.success) {
                    // Aktualizuj zadanie w tablicy harmonogramTasks
                    var task = harmonogramTasks.find(function(t) { return t.id == taskId; });
                    if (task) {
                        task.status = newStatus;
                    }

                    // Odśwież oba widoki
                    renderHarmonogram();
                    loadTasks();

                    var statusLabels = {
                        'nowe': 'Nowe',
                        'rozpoczete': 'Rozpoczęte',
                        'zakonczone': 'Zakończone',
                        'anulowane': 'Anulowane'
                    };
                    showToast('Status: ' + statusLabels[newStatus], 'success');

                    // Gamification - pokaż XP popup jeśli zadanie ukończone
                    if (newStatus === 'zakonczone' && response.data && response.data.gamification) {
                        showXpPopup(response.data.gamification);
                    }
                }
            });
        };

        // Przełącz status wykonania zadania (dla harmonogramu - checkbox)
        window.toggleTaskDone = function(taskId, isDone) {
            var newStatus = isDone ? 'zakonczone' : 'nowe';
            changeTaskStatus(taskId, newStatus);
        };

        // Alias dla harmonogramu
        window.toggleHarmonogramTaskDone = window.toggleTaskDone;

        // Kopiuj zadanie na inny dzień (z wyborem daty)
        window.copyTaskToDate = function(taskId) {
            var task = harmonogramTasks.find(function(t) { return t.id == taskId; });
            if (!task) {
                // Spróbuj znaleźć w głównej liście zadań (nie tylko harmonogramTasks)
                // Pobierz dane przez AJAX
            }

            var targetDate = prompt('Na jaki dzień skopiować zadanie?\n(format: RRRR-MM-DD)', addDays(today, 1));
            if (!targetDate) return;

            // Walidacja formatu daty
            if (!/^\d{4}-\d{2}-\d{2}$/.test(targetDate)) {
                alert('Nieprawidłowy format daty. Użyj formatu RRRR-MM-DD');
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_copy_task_to_date',
                nonce: nonce,
                id: taskId,
                target_date: targetDate
            }, function(response) {
                if (response.success) {
                    showToast('Zadanie skopiowane na ' + targetDate, 'success');
                    loadTasks();
                    loadCalendarDots();
                } else {
                    alert('Błąd podczas kopiowania: ' + (response.data || 'Nieznany błąd'));
                }
            });
        };

        // Aktualizuj godzinę zadania
        window.updateTaskTime = function(taskId, newTime, isStale) {
            if (isStale === '1') {
                // Dla stałych - zapisz w localStorage na dzisiaj
                var staleId = taskId.replace('stale-', '');
                var staleTask = harmonogramStale.find(function(s) { return s.id == staleId; });
                if (staleTask) {
                    staleTask.godzina_start = newTime + ':00';
                    // Przelicz godzinę końcową
                    if (staleTask.planowany_czas) {
                        var parts = newTime.split(':');
                        var endMinutes = parseInt(parts[0]) * 60 + parseInt(parts[1]) + parseInt(staleTask.planowany_czas);
                        var endHour = Math.floor(endMinutes / 60);
                        var endMin = endMinutes % 60;
                        staleTask.godzina_koniec = String(endHour).padStart(2, '0') + ':' + String(endMin).padStart(2, '0') + ':00';
                    }
                    saveStaleModifications();
                    renderHarmonogram();
                    showToast('Godzina stałego zadania zmieniona na dziś', 'success');
                }
            } else {
                // Dla zwykłych zadań - zapisz w bazie
                var task = harmonogramTasks.find(function(t) { return t.id == taskId; });
                if (task) {
                    var godzinaKoniec = null;
                    if (task.planowany_czas) {
                        var parts = newTime.split(':');
                        var endMinutes = parseInt(parts[0]) * 60 + parseInt(parts[1]) + parseInt(task.planowany_czas);
                        var endHour = Math.floor(endMinutes / 60);
                        var endMin = endMinutes % 60;
                        godzinaKoniec = String(endHour).padStart(2, '0') + ':' + String(endMin).padStart(2, '0');
                    }

                    $.post(ajaxurl, {
                        action: 'zadaniomat_update_harmonogram',
                        nonce: nonce,
                        id: taskId,
                        godzina_start: newTime,
                        godzina_koniec: godzinaKoniec
                    }, function(response) {
                        if (response.success) {
                            task.godzina_start = newTime + ':00';
                            task.godzina_koniec = godzinaKoniec ? godzinaKoniec + ':00' : null;
                            renderHarmonogram();
                            showToast('Godzina zmieniona', 'success');
                        }
                    });
                }
            }
        };

        // Ukryj stałe zadanie na wybrany dzień
        window.hideStaleForToday = function(staleId) {
            var hidden = JSON.parse(localStorage.getItem('zadaniomat_hidden_stale_' + harmonogramDate) || '[]');
            if (hidden.indexOf(staleId) === -1) {
                hidden.push(staleId);
                localStorage.setItem('zadaniomat_hidden_stale_' + harmonogramDate, JSON.stringify(hidden));
            }
            harmonogramStale = harmonogramStale.filter(function(s) { return s.id != staleId; });
            renderHarmonogram();
            showToast('Stałe zadanie ukryte na ten dzień', 'success');
        };

        // Zapisz modyfikacje stałych na wybrany dzień
        window.saveStaleModifications = function() {
            var mods = {};
            harmonogramStale.forEach(function(s) {
                mods[s.id] = {
                    godzina_start: s.godzina_start,
                    godzina_koniec: s.godzina_koniec
                };
            });
            localStorage.setItem('zadaniomat_stale_mods_' + harmonogramDate, JSON.stringify(mods));
        };

        // Załaduj modyfikacje stałych na dzisiaj
        window.loadStaleModifications = function() {
            var hidden = JSON.parse(localStorage.getItem('zadaniomat_hidden_stale_' + harmonogramDate) || '[]');
            var mods = JSON.parse(localStorage.getItem('zadaniomat_stale_mods_' + harmonogramDate) || '{}');

            // Filtruj ukryte
            harmonogramStale = harmonogramStale.filter(function(s) {
                return hidden.indexOf(String(s.id)) === -1;
            });

            // Zastosuj modyfikacje godzin
            harmonogramStale.forEach(function(s) {
                if (mods[s.id]) {
                    s.godzina_start = mods[s.id].godzina_start;
                    s.godzina_koniec = mods[s.id].godzina_koniec;
                }
            });
        };

        // Renderuj nieprzypisane zadania
        window.renderUnscheduledTasks = function() {
            var unscheduled = harmonogramTasks.filter(function(task) {
                return !task.godzina_start;
            });

            if (unscheduled.length === 0) {
                $('#unscheduled-tasks').hide();
                return;
            }

            $('#unscheduled-tasks').show();
            $('#unscheduled-count').text('(' + unscheduled.length + ')');

            var html = '';
            unscheduled.forEach(function(task) {
                html += '<div class="unscheduled-task ' + task.kategoria + '" draggable="true" ondragstart="handleDragStart(event, ' + task.id + ')" data-id="' + task.id + '">';
                html += '<strong>' + escapeHtml(task.zadanie) + '</strong>';
                if (task.planowany_czas) {
                    html += ' <span style="color:#888;">(' + task.planowany_czas + ' min)</span>';
                }
                html += '</div>';
            });

            $('#unscheduled-list').html(html);
        };

        // Drag & Drop
        window.handleDragStart = function(event, taskId) {
            draggedTask = taskId;
            event.target.classList.add('dragging');
            event.dataTransfer.setData('text/plain', taskId);
        };

        window.handleDragOver = function(event) {
            event.preventDefault();
            event.currentTarget.classList.add('dragover');
        };

        window.handleDragLeave = function(event) {
            event.currentTarget.classList.remove('dragover');
        };

        window.handleDrop = function(event, hour) {
            event.preventDefault();
            event.currentTarget.classList.remove('dragover');

            if (!draggedTask) return;

            var godzina = String(hour).padStart(2, '0') + ':00';
            var isStale = String(draggedTask).indexOf('stale-') === 0;

            if (isStale) {
                // Stałe zadanie - zapisz tylko lokalnie na dzisiaj
                var staleId = String(draggedTask).replace('stale-', '');
                var staleTask = harmonogramStale.find(function(s) { return s.id == staleId; });

                if (staleTask) {
                    staleTask.godzina_start = godzina + ':00';
                    // Oblicz godzinę końcową
                    if (staleTask.planowany_czas) {
                        var endMinutes = hour * 60 + parseInt(staleTask.planowany_czas);
                        var endHour = Math.floor(endMinutes / 60);
                        var endMin = endMinutes % 60;
                        staleTask.godzina_koniec = String(endHour).padStart(2, '0') + ':' + String(endMin).padStart(2, '0') + ':00';
                    }
                    saveStaleModifications();
                    renderHarmonogram();
                    showToast('Stałe zadanie przesunięte na ' + godzina, 'success');
                }
            } else {
                // Zwykłe zadanie
                var task = harmonogramTasks.find(function(t) { return t.id == draggedTask; });

                if (task) {
                    // Oblicz godzinę końcową
                    var godzinaKoniec = null;
                    if (task.planowany_czas) {
                        var endMinutes = hour * 60 + parseInt(task.planowany_czas);
                        var endHour = Math.floor(endMinutes / 60);
                        var endMin = endMinutes % 60;
                        godzinaKoniec = String(endHour).padStart(2, '0') + ':' + String(endMin).padStart(2, '0');
                    }

                    // Aktualizuj w bazie
                    $.post(ajaxurl, {
                        action: 'zadaniomat_update_harmonogram',
                        nonce: nonce,
                        id: draggedTask,
                        godzina_start: godzina,
                        godzina_koniec: godzinaKoniec
                    }, function(response) {
                        if (response.success) {
                            // Aktualizuj lokalnie
                            task.godzina_start = godzina;
                            task.godzina_koniec = godzinaKoniec;
                            renderHarmonogram();
                            showToast('Zadanie przypisane do ' + godzina, 'success');
                        }
                    });
                }
            }

            draggedTask = null;
            $('.dragging').removeClass('dragging');
        };

        // Usuń z harmonogramu
        window.removeFromHarmonogram = function(taskId) {
            $.post(ajaxurl, {
                action: 'zadaniomat_update_harmonogram',
                nonce: nonce,
                id: taskId,
                godzina_start: '',
                godzina_koniec: ''
            }, function(response) {
                if (response.success) {
                    var task = harmonogramTasks.find(function(t) { return t.id == taskId; });
                    if (task) {
                        task.godzina_start = null;
                        task.godzina_koniec = null;
                    }
                    renderHarmonogram();
                    showToast('Zadanie usunięte z harmonogramu', 'success');
                }
            });
        };

        // Toggle widok
        window.toggleHarmonogramView = function(view) {
            harmonogramView = view;
            $('.view-toggle button').removeClass('active');
            $('.view-toggle button[data-view="' + view + '"]').addClass('active');
            renderHarmonogram();
        };

        // Renderuj widok listy
        window.renderListView = function() {
            var allTasks = [];

            // Dodaj zwykłe zadania
            harmonogramTasks.forEach(function(task) {
                if (task.godzina_start) {
                    allTasks.push({ task: task, isStale: false, time: task.godzina_start });
                }
            });

            // Dodaj stałe zadania
            harmonogramStale.forEach(function(stale) {
                if (stale.godzina_start) {
                    allTasks.push({ task: stale, isStale: true, time: stale.godzina_start });
                }
            });

            // Sortuj po czasie
            allTasks.sort(function(a, b) {
                return a.time.localeCompare(b.time);
            });

            var html = '<div style="padding: 10px;">';
            allTasks.forEach(function(item) {
                html += renderHarmonogramTask(item.task, item.isStale);
            });

            if (allTasks.length === 0) {
                html += '<p style="color: #888; text-align: center; padding: 40px;">Przeciągnij zadania na timeline, aby ułożyć harmonogram</p>';
            }

            html += '</div>';
            $('#harmonogram-timeline').html(html);
        };

        // Aktualizuj linię aktualnego czasu
        window.updateCurrentTimeLine = function() {
            // Usuń poprzednią linię
            $('.timeline-current-time').remove();

            // Tylko dla dzisiaj pokazuj linię aktualnego czasu
            if (harmonogramView !== 'timeline' || harmonogramDate !== today) return;

            var now = new Date();
            var currentHour = now.getHours();
            var currentMinute = now.getMinutes();

            // Znajdź godzinę
            var $hourDiv = $('.timeline-hour[data-hour="' + currentHour + '"]');
            if ($hourDiv.length) {
                var percentInHour = (currentMinute / 60) * 100;
                var line = $('<div class="timeline-current-time"></div>');
                line.css('top', percentInHour + '%');
                $hourDiv.find('.timeline-hour-content').append(line);
            }
        };

        // Aktualizuj linię co minutę
        setInterval(updateCurrentTimeLine, 60000);

        // Modyfikuj selectDate żeby sprawdzić harmonogram
        var originalSelectDate = window.selectDate;
        window.selectDate = function(date) {
            selectedDate = date;
            $('.calendar-day').removeClass('selected');
            $('.calendar-day[data-date="' + date + '"]').addClass('selected');
            loadTasks();
            updateDateInfo();
            $('#task-date').val(date);
            checkShowHarmonogram();
        };

        // ==================== TIMER / CZASOMIERZ ====================
        var activeTimer = null; // { taskId, taskName, plannedTime, startTime, elapsedBefore, interval, notified }
        var timerAudio = null;
        var timerPopupWindow = null;
        var TIMER_STORAGE_KEY = 'zadaniomat_active_timer';
        var TIMER_AUTOSAVE_INTERVAL = 30; // Autosave do bazy co 30 sekund

        // Zapisz timer do localStorage
        window.saveTimerToStorage = function() {
            if (!activeTimer) {
                localStorage.removeItem(TIMER_STORAGE_KEY);
                return;
            }
            var timerData = {
                taskId: activeTimer.taskId,
                taskName: activeTimer.taskName,
                plannedTime: activeTimer.plannedTime,
                startTime: activeTimer.startTime,
                elapsedBefore: activeTimer.elapsedBefore,
                notified: activeTimer.notified,
                savedAt: Date.now()
            };
            localStorage.setItem(TIMER_STORAGE_KEY, JSON.stringify(timerData));
            // Synchronizuj z innymi oknami/zakładkami
            window.dispatchEvent(new StorageEvent('storage', {
                key: TIMER_STORAGE_KEY,
                newValue: JSON.stringify(timerData)
            }));
        };

        // Odzyskaj timer z localStorage
        window.restoreTimerFromStorage = function() {
            var saved = localStorage.getItem(TIMER_STORAGE_KEY);
            if (!saved) return false;

            try {
                var timerData = JSON.parse(saved);
                // Sprawdź czy dane są świeże (max 24h)
                if (Date.now() - timerData.savedAt > 24 * 60 * 60 * 1000) {
                    localStorage.removeItem(TIMER_STORAGE_KEY);
                    return false;
                }

                initTimerAudio();
                requestNotificationPermission();

                activeTimer = {
                    taskId: timerData.taskId,
                    taskName: timerData.taskName,
                    plannedTime: timerData.plannedTime,
                    startTime: timerData.startTime,
                    elapsedBefore: timerData.elapsedBefore,
                    interval: null,
                    notified: timerData.notified || false
                };

                activeTimer.interval = setInterval(updateTimerDisplay, 1000);
                updateTimerDisplay();
                renderFloatingTimer();

                var totalMinutes = formatMinutes(getTotalElapsed());
                showToast('⏱️ Timer wznowiony: ' + timerData.taskName + ' (' + totalMinutes + ' min)', 'success');
                return true;
            } catch (e) {
                localStorage.removeItem(TIMER_STORAGE_KEY);
                return false;
            }
        };

        // Usuń timer ze storage
        window.clearTimerStorage = function() {
            localStorage.removeItem(TIMER_STORAGE_KEY);
        };

        // Otwórz timer w osobnym oknie (popup)
        window.openTimerPopup = function() {
            if (!activeTimer) {
                showToast('Najpierw uruchom timer dla jakiegoś zadania', 'warning');
                return;
            }

            // Zamknij poprzednie okno jeśli istnieje
            if (timerPopupWindow && !timerPopupWindow.closed) {
                timerPopupWindow.focus();
                return;
            }

            var popupWidth = 300;
            var popupHeight = 200;
            var left = screen.width - popupWidth - 20;
            var top = screen.height - popupHeight - 100;

            timerPopupWindow = window.open('', 'zadaniomat_timer_popup',
                'width=' + popupWidth + ',height=' + popupHeight + ',left=' + left + ',top=' + top +
                ',resizable=yes,scrollbars=no,menubar=no,toolbar=no,location=no,status=no');

            if (!timerPopupWindow) {
                showToast('Popup został zablokowany. Odblokuj popup w przeglądarce.', 'warning');
                return;
            }

            updateTimerPopup();
        };

        // Aktualizuj zawartość popup'a
        window.updateTimerPopup = function() {
            if (!timerPopupWindow || timerPopupWindow.closed) {
                timerPopupWindow = null;
                return;
            }
            if (!activeTimer) {
                timerPopupWindow.close();
                timerPopupWindow = null;
                return;
            }

            var totalElapsed = getTotalElapsed();
            var remaining = activeTimer.plannedTime - totalElapsed;
            var isOvertime = remaining < 0;
            var timeStr = formatTime(Math.abs(remaining));
            if (isOvertime) timeStr = '+' + timeStr;

            var bgColor = isOvertime ? '#ffe6e6' : '#e6ffe6';
            var textColor = isOvertime ? '#cc0000' : '#006600';

            var html = '<!DOCTYPE html><html><head><title>Timer - ' + escapeHtml(activeTimer.taskName) + '</title>';
            html += '<style>';
            html += 'body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; ';
            html += 'margin: 0; padding: 15px; background: ' + bgColor + '; text-align: center; }';
            html += '.task-name { font-size: 12px; color: #666; margin-bottom: 10px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }';
            html += '.timer-display { font-size: 48px; font-weight: bold; color: ' + textColor + '; font-family: monospace; }';
            html += '.timer-label { font-size: 11px; color: #999; margin-top: 5px; }';
            html += '.buttons { margin-top: 15px; display: flex; gap: 10px; justify-content: center; }';
            html += '.btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; }';
            html += '.btn-done { background: #28a745; color: white; }';
            html += '.btn-stop { background: #dc3545; color: white; }';
            html += '</style></head><body>';
            html += '<div class="task-name">' + escapeHtml(activeTimer.taskName) + '</div>';
            html += '<div class="timer-display">' + timeStr + '</div>';
            html += '<div class="timer-label">' + (isOvertime ? 'PRZEKROCZONO CZAS' : 'pozostało') + '</div>';
            html += '<div class="buttons">';
            html += '<button class="btn btn-done" onclick="window.opener.stopTimer(true); window.close();">✓ Gotowe</button>';
            html += '<button class="btn btn-stop" onclick="window.opener.stopTimer(false); window.close();">⏹ Stop</button>';
            html += '</div>';
            html += '</body></html>';

            timerPopupWindow.document.open();
            timerPopupWindow.document.write(html);
            timerPopupWindow.document.close();
        };

        // Inicjalizacja dźwięku (generowany programowo) - alarm z auto-stop po 5 sekundach
        var alarmInterval = null;
        var alarmAutoStopTimeout = null;
        var alarmAudioContext = null;
        var alarmOscillator = null;
        var alarmGainNode = null;

        window.initTimerAudio = function() {
            if (timerAudio) return;
            var AudioContext = window.AudioContext || window.webkitAudioContext;
            if (!AudioContext) return;

            timerAudio = {
                playing: false,
                play: function() {
                    if (this.playing) return;
                    this.playing = true;
                    var self = this;

                    // Graj alarm w pętli co 1.5 sekundy
                    function playBeepPattern() {
                        if (!self.playing) return;

                        var ctx = new AudioContext();
                        var oscillator = ctx.createOscillator();
                        var gainNode = ctx.createGain();

                        oscillator.connect(gainNode);
                        gainNode.connect(ctx.destination);

                        oscillator.frequency.value = 800;
                        oscillator.type = 'sine';
                        gainNode.gain.value = 0.4;

                        oscillator.start();

                        // Beep pattern: 3 krótkie dźwięki
                        setTimeout(function() { gainNode.gain.value = 0; }, 150);
                        setTimeout(function() { if (self.playing) gainNode.gain.value = 0.4; }, 250);
                        setTimeout(function() { gainNode.gain.value = 0; }, 400);
                        setTimeout(function() { if (self.playing) gainNode.gain.value = 0.4; }, 500);
                        setTimeout(function() { gainNode.gain.value = 0; }, 650);
                        setTimeout(function() { oscillator.stop(); ctx.close(); }, 700);
                    }

                    // Odtwórz od razu i ustaw interwał
                    playBeepPattern();
                    alarmInterval = setInterval(playBeepPattern, 1500);

                    // Auto-stop po 5 sekundach
                    alarmAutoStopTimeout = setTimeout(function() {
                        self.stop();
                    }, 5000);
                },
                stop: function() {
                    this.playing = false;
                    if (alarmAutoStopTimeout) {
                        clearTimeout(alarmAutoStopTimeout);
                        alarmAutoStopTimeout = null;
                    }
                    if (alarmInterval) {
                        clearInterval(alarmInterval);
                        alarmInterval = null;
                    }
                }
            };
        };

        // Zatrzymaj alarm
        window.stopAlarm = function() {
            if (timerAudio) {
                timerAudio.stop();
            }
        };

        // Poproś o pozwolenie na powiadomienia
        window.requestNotificationPermission = function() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        };

        // Uruchom timer dla zadania
        // currentMinutes - już zapisany faktyczny_czas z bazy (kumulatywny stoper)
        window.startTimer = function(taskId, taskName, plannedMinutes, currentMinutes) {
            currentMinutes = currentMinutes || 0;

            // Jeśli już jest aktywny timer dla innego zadania
            if (activeTimer && activeTimer.taskId !== taskId) {
                if (!confirm('Masz już uruchomiony timer dla innego zadania. Czy chcesz go zatrzymać i uruchomić nowy?')) {
                    return;
                }
                stopTimer(false);
            }

            // Jeśli to kontynuacja tego samego zadania (timer już działa)
            var elapsedBefore = currentMinutes * 60; // Kumuluj z zapisanego czasu
            if (activeTimer && activeTimer.taskId === taskId) {
                elapsedBefore = activeTimer.elapsedBefore + getElapsedSeconds();
                clearInterval(activeTimer.interval);
            }

            initTimerAudio();
            requestNotificationPermission();

            activeTimer = {
                taskId: taskId,
                taskName: taskName,
                plannedTime: plannedMinutes * 60, // w sekundach
                startTime: Date.now(),
                elapsedBefore: elapsedBefore, // Teraz zawiera już zapisany czas
                interval: null,
                notified: false
            };

            activeTimer.interval = setInterval(updateTimerDisplay, 1000);
            activeTimer.lastAutosave = 0; // Licznik sekund do autosave
            updateTimerDisplay();
            renderFloatingTimer();
            saveTimerToStorage(); // Zapisz do localStorage

            var msg = currentMinutes > 0
                ? '⏱️ Timer uruchomiony: ' + taskName + ' (kontynuacja od ' + currentMinutes + ' min)'
                : '⏱️ Timer uruchomiony: ' + taskName;
            showToast(msg, 'success');
        };

        // Pobierz upływający czas w sekundach
        window.getElapsedSeconds = function() {
            if (!activeTimer) return 0;
            return Math.floor((Date.now() - activeTimer.startTime) / 1000);
        };

        // Pobierz całkowity czas (poprzedni + aktualny)
        window.getTotalElapsed = function() {
            if (!activeTimer) return 0;
            return activeTimer.elapsedBefore + getElapsedSeconds();
        };

        // Aktualizuj wyświetlanie timera
        window.updateTimerDisplay = function() {
            if (!activeTimer) return;

            var totalElapsed = getTotalElapsed();
            var remaining = activeTimer.plannedTime - totalElapsed;
            var isOvertime = remaining < 0;

            // Aktualizuj floating timer
            var timeStr = formatTime(Math.abs(remaining));
            if (isOvertime) timeStr = '+' + timeStr;

            $('.ft-time').text(timeStr);

            if (isOvertime) {
                $('#floating-timer-container .floating-timer').addClass('overtime');
            }

            // Sprawdź czy czas minął i pokaż powiadomienie
            if (remaining <= 0 && !activeTimer.notified) {
                activeTimer.notified = true;
                showTimerEndNotification();
            }

            // Aktualizuj popup jeśli otwarty
            updateTimerPopup();

            // Zapisz do localStorage co 5 sekund
            if (!activeTimer.lastStorageSave) activeTimer.lastStorageSave = 0;
            activeTimer.lastStorageSave++;
            if (activeTimer.lastStorageSave >= 5) {
                activeTimer.lastStorageSave = 0;
                saveTimerToStorage();
            }

            // Autosave do bazy co 30 sekund (backup na wypadek crashu)
            if (!activeTimer.lastAutosave) activeTimer.lastAutosave = 0;
            activeTimer.lastAutosave++;
            if (activeTimer.lastAutosave >= TIMER_AUTOSAVE_INTERVAL) {
                activeTimer.lastAutosave = 0;
                var totalMinutes = formatMinutes(getTotalElapsed());
                $.post(ajaxurl, {
                    action: 'zadaniomat_quick_update',
                    nonce: nonce,
                    id: activeTimer.taskId,
                    field: 'faktyczny_czas',
                    value: totalMinutes
                });
            }
        };

        // Formatuj czas (sekundy -> MM:SS)
        window.formatTime = function(seconds) {
            var mins = Math.floor(seconds / 60);
            var secs = seconds % 60;
            return String(mins).padStart(2, '0') + ':' + String(secs).padStart(2, '0');
        };

        // Formatuj czas w minutach
        window.formatMinutes = function(seconds) {
            return Math.round(seconds / 60);
        };

        // Renderuj floating timer
        window.renderFloatingTimer = function() {
            if (!activeTimer) {
                $('#floating-timer-container').html('');
                return;
            }

            var html = '<div class="floating-timer" onclick="showTimerModal()">';
            html += '<div class="ft-time">00:00</div>';
            html += '<div class="ft-task">' + escapeHtml(activeTimer.taskName) + '</div>';
            html += '<div class="ft-actions">';
            html += '<button onclick="event.stopPropagation(); openTimerPopup()" title="Otwórz popup" class="ft-popup-btn">⧉</button>';
            html += '<button onclick="event.stopPropagation(); stopTimer(true)" title="Zakończ">✓</button>';
            html += '<button onclick="event.stopPropagation(); cancelTimer()" title="Anuluj">✕</button>';
            html += '</div>';
            html += '</div>';

            $('#floating-timer-container').html(html);
        };

        // Pokaż powiadomienie o końcu czasu
        window.showTimerEndNotification = function() {
            // Dźwięk
            if (timerAudio) timerAudio.play();

            // Powiadomienie systemowe
            if ('Notification' in window && Notification.permission === 'granted') {
                new Notification('⏰ Czas upłynął!', {
                    body: activeTimer.taskName + ' - czas planowany minął',
                    icon: '⏰',
                    requireInteraction: true
                });
            }

            // Pokaż modal
            showTimerModal();
        };

        // Pokaż modal timera
        window.showTimerModal = function() {
            if (!activeTimer) return;

            var totalElapsed = getTotalElapsed();
            var remaining = activeTimer.plannedTime - totalElapsed;
            var isOvertime = remaining < 0;
            var timeStr = formatTime(Math.abs(isOvertime ? remaining : totalElapsed));

            var html = '<div class="timer-modal-overlay" onclick="closeTimerModal(event)">';
            html += '<div class="timer-modal" onclick="event.stopPropagation()">';

            if (isOvertime) {
                html += '<h2>⏰ Czas upłynął!</h2>';
            } else {
                html += '<h2>⏱️ Timer aktywny</h2>';
            }

            html += '<div class="task-name">' + escapeHtml(activeTimer.taskName) + '</div>';
            html += '<div class="timer-big' + (isOvertime ? ' overtime' : '') + '" id="modal-timer-display">';
            html += (isOvertime ? '+' : '') + timeStr;
            html += '</div>';
            html += '<div class="time-info">';
            html += 'Planowany czas: ' + formatMinutes(activeTimer.plannedTime) + ' min | ';
            html += 'Przepracowano: ' + formatMinutes(totalElapsed) + ' min';
            html += '</div>';

            html += '<div class="timer-modal-actions">';
            if (isOvertime && timerAudio && timerAudio.playing) {
                html += '<button class="btn-timer-mute" onclick="stopAlarm(); $(this).hide();" style="background:#dc3545; animation: pulse-alarm 1s infinite;">🔔 Wycisz alarm</button>';
            }
            html += '<button class="btn-timer-done" onclick="stopTimer(true)">✓ Zakończone</button>';
            if (isOvertime) {
                html += '<button class="btn-timer-extend" onclick="showExtendOptions()">+ Przedłuż</button>';
            }
            html += '<button class="btn-timer-stop" onclick="stopTimer(false)">⏹ Zatrzymaj</button>';
            html += '</div>';

            html += '<div class="extend-options" id="extend-options" style="display:none;">';
            html += '<button onclick="extendTimer(5)">+5 min</button>';
            html += '<button onclick="extendTimer(10)">+10 min</button>';
            html += '<button onclick="extendTimer(15)">+15 min</button>';
            html += '<button onclick="extendTimer(30)">+30 min</button>';
            html += '</div>';

            html += '</div></div>';

            $('#timer-modal-container').html(html);

            // Aktualizuj czas w modalu
            if (activeTimer) {
                var modalInterval = setInterval(function() {
                    if (!activeTimer) {
                        clearInterval(modalInterval);
                        return;
                    }
                    var elapsed = getTotalElapsed();
                    var rem = activeTimer.plannedTime - elapsed;
                    var over = rem < 0;
                    var ts = formatTime(Math.abs(over ? rem : elapsed));
                    $('#modal-timer-display').text((over ? '+' : '') + ts);
                    if (over) $('#modal-timer-display').addClass('overtime');
                }, 1000);
            }
        };

        window.closeTimerModal = function(event) {
            if (event && event.target !== event.currentTarget) return;
            $('#timer-modal-container').html('');
        };

        window.showExtendOptions = function() {
            $('#extend-options').show();
        };

        // Przedłuż timer
        window.extendTimer = function(minutes) {
            if (!activeTimer) return;

            stopAlarm(); // Zatrzymaj alarm
            activeTimer.plannedTime += minutes * 60;
            activeTimer.notified = false;
            saveTimerToStorage(); // Zapisz nowy czas

            closeTimerModal();
            $('#floating-timer-container .floating-timer').removeClass('overtime');
            showToast('Timer przedłużony o ' + minutes + ' minut', 'success');
        };

        // Zatrzymaj timer
        window.stopTimer = function(markAsComplete) {
            if (!activeTimer) return;

            stopAlarm(); // Zatrzymaj alarm
            clearInterval(activeTimer.interval);
            var totalMinutes = formatMinutes(getTotalElapsed());
            var taskId = activeTimer.taskId;

            // Zapisz rzeczywisty czas
            $.post(ajaxurl, {
                action: 'zadaniomat_quick_update',
                nonce: nonce,
                id: taskId,
                field: 'faktyczny_czas',
                value: totalMinutes
            }, function(response) {
                if (response.success) {
                    // Odśwież listę zadań
                    loadTasks();
                    if (selectedDate === today) loadHarmonogram();
                }
            });

            // Jeśli oznaczamy jako zakończone
            if (markAsComplete) {
                $.post(ajaxurl, {
                    action: 'zadaniomat_quick_update',
                    nonce: nonce,
                    id: taskId,
                    field: 'status',
                    value: 'zakonczone'
                }, function(response) {
                    if (response.success && response.data && response.data.gamification) {
                        showXpPopup(response.data.gamification);
                    }
                });
            }

            showToast('⏱️ Zapisano czas: ' + totalMinutes + ' min', 'success');

            activeTimer = null;
            clearTimerStorage(); // Usuń ze storage
            $('#floating-timer-container').html('');
            closeTimerModal();
            // Zamknij popup jeśli otwarty
            if (timerPopupWindow && !timerPopupWindow.closed) {
                timerPopupWindow.close();
            }
            timerPopupWindow = null;
        };

        // Anuluj timer bez zapisywania
        window.cancelTimer = function() {
            if (!activeTimer) return;
            if (!confirm('Anulować timer bez zapisywania czasu?')) return;

            stopAlarm(); // Zatrzymaj alarm
            clearInterval(activeTimer.interval);
            activeTimer = null;
            clearTimerStorage(); // Usuń ze storage
            $('#floating-timer-container').html('');
            closeTimerModal();
            // Zamknij popup jeśli otwarty
            if (timerPopupWindow && !timerPopupWindow.closed) {
                timerPopupWindow.close();
            }
            timerPopupWindow = null;
            showToast('Timer anulowany', 'warning');
        };

        // Uruchom kolejną sesję timera (dodatkowy czas)
        window.startAdditionalTimer = function(taskId, taskName, minutes) {
            // Pobierz aktualny faktyczny czas z bazy i dodaj do niego
            var currentFactical = 0;
            // Na razie uruchom normalnie - czas się zsumuje
            startTimer(taskId, taskName, minutes);
        };

        // Edytuj rzeczywisty czas ręcznie
        window.editFaktycznyCzas = function(taskId, currentValue) {
            var newValue = prompt('Podaj rzeczywisty czas w minutach:', currentValue || '0');
            if (newValue === null) return;

            var minutes = parseInt(newValue);
            if (isNaN(minutes) || minutes < 0) {
                alert('Podaj prawidłową liczbę minut');
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_quick_update',
                nonce: nonce,
                id: taskId,
                field: 'faktyczny_czas',
                value: minutes
            }, function(response) {
                if (response.success) {
                    loadTasks();
                    showToast('Czas zaktualizowany: ' + minutes + ' min', 'success');
                }
            });
        };

        // Ostrzeżenie przed zamknięciem strony gdy timer jest uruchomiony
        window.addEventListener('beforeunload', function(e) {
            if (activeTimer) {
                var message = 'Timer jest uruchomiony! Czy na pewno chcesz opuścić stronę? Czas zostanie zapisany automatycznie.';
                e.preventDefault();
                e.returnValue = message;
                return message;
            }
        });

        // Zapisz timer przy zmianie widoczności strony (np. przełączanie kart)
        document.addEventListener('visibilitychange', function() {
            if (activeTimer && document.visibilityState === 'hidden') {
                saveTimerToStorage();
            }
        });

    })(jQuery);
    </script>
    <?php
}

// =============================================
// STRONA USTAWIEŃ
// =============================================
function zadaniomat_page_settings() {
    global $wpdb;
    $table_roki = $wpdb->prefix . 'zadaniomat_roki';
    $table_okresy = $wpdb->prefix . 'zadaniomat_okresy';
    $table_cele_rok = $wpdb->prefix . 'zadaniomat_cele_rok';
    
    // Dodawanie roku
    if (isset($_POST['dodaj_rok']) && wp_verify_nonce($_POST['nonce'], 'zadaniomat_action')) {
        $wpdb->insert($table_roki, [
            'nazwa' => sanitize_text_field($_POST['nazwa']),
            'data_start' => sanitize_text_field($_POST['data_start']),
            'data_koniec' => sanitize_text_field($_POST['data_koniec'])
        ]);
        $new_rok_id = $wpdb->insert_id;

        // Wygeneruj zadania stałe dla nowego roku
        $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';
        $aktywne_stale = $wpdb->get_results("SELECT id FROM $table_stale WHERE aktywne = 1");
        $generated_count = 0;
        foreach ($aktywne_stale as $stale) {
            $generated_count += count(zadaniomat_generate_recurring_tasks($stale->id, $new_rok_id));
        }

        $msg = '✅ Rok dodany!';
        if ($generated_count > 0) {
            $msg .= " Wygenerowano $generated_count zadań cyklicznych.";
        }
        echo '<div class="notice notice-success"><p>' . $msg . '</p></div>';
    }

    // Dodawanie okresu
    if (isset($_POST['dodaj_okres']) && wp_verify_nonce($_POST['nonce'], 'zadaniomat_action')) {
        $rok_id = intval($_POST['rok_id']);
        $wpdb->insert($table_okresy, [
            'rok_id' => $rok_id,
            'nazwa' => sanitize_text_field($_POST['nazwa']),
            'data_start' => sanitize_text_field($_POST['data_start']),
            'data_koniec' => sanitize_text_field($_POST['data_koniec'])
        ]);

        // Wygeneruj zadania stałe dla nowego okresu (regeneruj dla całego roku)
        $table_stale = $wpdb->prefix . 'zadaniomat_stale_zadania';
        $aktywne_stale = $wpdb->get_results("SELECT id FROM $table_stale WHERE aktywne = 1");
        $generated_count = 0;
        foreach ($aktywne_stale as $stale) {
            // Regeneruj zadania żeby uwzględnić nowy okres
            $generated_count += count(zadaniomat_generate_recurring_tasks($stale->id, $rok_id));
        }

        $msg = '✅ Okres dodany!';
        if ($generated_count > 0) {
            $msg .= " Wygenerowano $generated_count zadań cyklicznych.";
        }
        echo '<div class="notice notice-success"><p>' . $msg . '</p></div>';
    }
    
    // Usuwanie roku (kaskadowo usuwa okresy i cele)
    if (isset($_GET['delete_rok']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_rok')) {
        $rok_id = intval($_GET['delete_rok']);
        // Usuń cele roku
        $wpdb->delete($table_cele_rok, ['rok_id' => $rok_id]);
        // Usuń cele okresów tego roku
        $okresy_ids = $wpdb->get_col($wpdb->prepare("SELECT id FROM $table_okresy WHERE rok_id = %d", $rok_id));
        if (!empty($okresy_ids)) {
            $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
            $wpdb->query("DELETE FROM $table_cele_okres WHERE okres_id IN (" . implode(',', array_map('intval', $okresy_ids)) . ")");
        }
        // Usuń okresy
        $wpdb->delete($table_okresy, ['rok_id' => $rok_id]);
        // Usuń rok
        $wpdb->delete($table_roki, ['id' => $rok_id]);
        echo '<div class="notice notice-success"><p>✅ Rok usunięty wraz z okresami!</p></div>';
    }
    if (isset($_GET['delete_okres']) && wp_verify_nonce($_GET['_wpnonce'], 'delete_okres')) {
        $okres_id = intval($_GET['delete_okres']);
        // Usuń cele okresu
        $table_cele_okres = $wpdb->prefix . 'zadaniomat_cele_okres';
        $wpdb->delete($table_cele_okres, ['okres_id' => $okres_id]);
        // Usuń okres
        $wpdb->delete($table_okresy, ['id' => $okres_id]);
        echo '<div class="notice notice-success"><p>✅ Okres usunięty!</p></div>';
    }
    
    // Pobierz osierocone okresy (bez roku lub z nieistniejącym rokiem)
    $orphaned_okresy = $wpdb->get_results(
        "SELECT o.* FROM $table_okresy o LEFT JOIN $table_roki r ON o.rok_id = r.id WHERE r.id IS NULL"
    );
    
    $roki = $wpdb->get_results("SELECT * FROM $table_roki ORDER BY data_start DESC");
    // Auto-select current rok (containing today's date) if no rok_id in URL
    $default_rok_id = null;
    if (!isset($_GET['rok_id'])) {
        $current_date = date('Y-m-d');
        $current_rok_data = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_roki WHERE %s BETWEEN data_start AND data_koniec LIMIT 1",
            $current_date
        ));
        $default_rok_id = $current_rok_data ? $current_rok_data->id : ($roki ? $roki[0]->id : null);
    }
    $selected_rok = isset($_GET['rok_id']) ? intval($_GET['rok_id']) : $default_rok_id;
    $current_rok = $selected_rok ? $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_roki WHERE id = %d", $selected_rok)) : null;
    
    $okresy = $selected_rok ? $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_okresy WHERE rok_id = %d ORDER BY data_start ASC", $selected_rok
    )) : [];
    
    $cele_rok = [];
    if ($selected_rok) {
        $cele_raw = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_cele_rok WHERE rok_id = %d", $selected_rok));
        foreach ($cele_raw as $c) {
            $cele_rok[$c->kategoria] = $c->cel;
        }
    }
    
    ?>
    <div class="wrap zadaniomat-wrap">
        <h1>⚙️ Ustawienia Zadaniomatu</h1>
        
        <?php if (!empty($orphaned_okresy)): ?>
        <div class="zadaniomat-card" style="background: #fff3cd; border-left: 4px solid #ffc107;">
            <h2>⚠️ Osierocone okresy (bez przypisanego roku)</h2>
            <p style="color: #856404;">Te okresy nie mają przypisanego roku. Możesz je usunąć.</p>
            <table class="settings-table">
                <thead><tr><th>Nazwa</th><th>Okres</th><th>Akcje</th></tr></thead>
                <tbody>
                    <?php foreach ($orphaned_okresy as $o): ?>
                        <tr>
                            <td><strong><?php echo esc_html($o->nazwa); ?></strong></td>
                            <td><?php echo date('d.m.Y', strtotime($o->data_start)); ?> - <?php echo date('d.m.Y', strtotime($o->data_koniec)); ?></td>
                            <td>
                                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zadaniomat-settings&delete_okres=' . $o->id), 'delete_okres'); ?>" 
                                   class="button button-small" style="background: #dc3545; color: #fff; border-color: #dc3545;"
                                   onclick="return confirm('Usunąć ten osierocony okres?');">🗑️ Usuń</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div class="settings-grid">
            <div class="zadaniomat-card">
                <h2>📅 Roki 90-dniowe</h2>
                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('zadaniomat_action', 'nonce'); ?>
                    <div class="form-grid">
                        <div class="form-group"><label>Nazwa</label><input type="text" name="nazwa" placeholder="np. ROK 1" required></div>
                        <div class="form-group"><label>Start</label><input type="date" name="data_start" required></div>
                        <div class="form-group"><label>Koniec</label><input type="date" name="data_koniec" required></div>
                    </div>
                    <button type="submit" name="dodaj_rok" class="button button-primary">➕ Dodaj rok</button>
                </form>
                <table class="settings-table">
                    <thead><tr><th>Nazwa</th><th>Okres</th><th>Akcje</th></tr></thead>
                    <tbody>
                        <?php foreach ($roki as $r): 
                            $is_current = (date('Y-m-d') >= $r->data_start && date('Y-m-d') <= $r->data_koniec);
                        ?>
                            <tr class="<?php echo $is_current ? 'status-partial' : ''; ?>">
                                <td><strong><?php echo esc_html($r->nazwa); ?></strong></td>
                                <td><?php echo date('d.m.Y', strtotime($r->data_start)); ?> - <?php echo date('d.m.Y', strtotime($r->data_koniec)); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=zadaniomat-settings&rok_id=' . $r->id); ?>" class="button button-small">Wybierz</a>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zadaniomat-settings&delete_rok=' . $r->id), 'delete_rok'); ?>" class="btn-delete" onclick="return confirm('Usunąć?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="zadaniomat-card">
                <h2>📆 Okresy 2-tygodniowe <?php echo $current_rok ? '(' . esc_html($current_rok->nazwa) . ')' : ''; ?></h2>
                <?php if ($selected_rok): ?>
                <form method="post" style="margin-bottom: 20px;">
                    <?php wp_nonce_field('zadaniomat_action', 'nonce'); ?>
                    <input type="hidden" name="rok_id" value="<?php echo $selected_rok; ?>">
                    <div class="form-grid">
                        <div class="form-group"><label>Nazwa</label><input type="text" name="nazwa" placeholder="np. Okres 1" required></div>
                        <div class="form-group"><label>Start</label><input type="date" name="data_start" required></div>
                        <div class="form-group"><label>Koniec</label><input type="date" name="data_koniec" required></div>
                    </div>
                    <button type="submit" name="dodaj_okres" class="button button-primary">➕ Dodaj okres</button>
                </form>
                <table class="settings-table">
                    <thead><tr><th>Nazwa</th><th>Okres</th><th>Status</th><th>Akcje</th></tr></thead>
                    <tbody>
                        <?php foreach ($okresy as $o): 
                            $is_current = (date('Y-m-d') >= $o->data_start && date('Y-m-d') <= $o->data_koniec);
                            $is_past = (date('Y-m-d') > $o->data_koniec);
                            $is_future = (date('Y-m-d') < $o->data_start);
                        ?>
                            <tr class="<?php echo $is_current ? 'status-partial' : ''; ?>">
                                <td><strong><?php echo esc_html($o->nazwa); ?></strong></td>
                                <td><?php echo date('d.m', strtotime($o->data_start)); ?> - <?php echo date('d.m.Y', strtotime($o->data_koniec)); ?></td>
                                <td>
                                    <?php if ($is_current): ?>
                                        <span style="color: #28a745;">🟢 Aktywny</span>
                                    <?php elseif ($is_past): ?>
                                        <span style="color: #6c757d;">✓ Zakończony</span>
                                    <?php else: ?>
                                        <span style="color: #17a2b8;">⏳ Przyszły</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="button button-small" onclick="openOkresModal(<?php echo $o->id; ?>)">📋 Cele</button>
                                    <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=zadaniomat-settings&rok_id=' . $selected_rok . '&delete_okres=' . $o->id), 'delete_okres'); ?>" class="btn-delete" onclick="return confirm('Usunąć?');">🗑️</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                    <p style="color: #666;">Najpierw dodaj i wybierz rok.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <?php if ($current_rok): ?>
        <div class="zadaniomat-card">
            <h2>🎯 Cele strategiczne na <?php echo esc_html($current_rok->nazwa); ?></h2>
            <div class="cele-grid">
                <?php foreach (ZADANIOMAT_KATEGORIE as $key => $label): ?>
                    <div class="cel-card <?php echo $key; ?>">
                        <h4><?php echo $label; ?></h4>
                        <textarea class="cel-rok-input" data-rok="<?php echo $selected_rok; ?>" data-kategoria="<?php echo $key; ?>" placeholder="Cel strategiczny..."><?php echo esc_textarea($cele_rok[$key] ?? ''); ?></textarea>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="zadaniomat-card">
            <h2>🔄 Stałe zadania (cykliczne)</h2>
            <p style="color: #666; margin-bottom: 15px;">Definiuj zadania, które powtarzają się regularnie. Zadania są automatycznie generowane na cały rok (90-dniowy okres) i pojawiają się jako normalne zadania na liście i w harmonogramie.</p>

            <!-- Formularz dodawania/edycji stałego zadania -->
            <div class="stale-zadania-form">
                <h4 style="margin-top: 0;" id="stale-form-title">➕ Dodaj stałe zadanie</h4>
                <input type="hidden" id="stale-edit-id" value="">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label>Nazwa zadania</label>
                        <input type="text" id="stale-nazwa" placeholder="np. Sprawdzenie maili">
                    </div>
                    <div class="form-group">
                        <label>Kategoria</label>
                        <select id="stale-kategoria">
                            <?php foreach (ZADANIOMAT_KATEGORIE_ZADANIA as $key => $label): ?>
                                <option value="<?php echo $key; ?>"><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>To do (opcjonalnie)</label>
                        <textarea id="stale-cel-todo" placeholder="Lista rzeczy do zrobienia..." style="width: 100%; min-height: 60px; resize: vertical;"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Cykliczność</label>
                        <select id="stale-typ" onchange="toggleStaleOptions()">
                            <option value="codziennie">Codziennie</option>
                            <option value="dni_tygodnia">Wybrane dni tygodnia</option>
                            <option value="dzien_miesiaca">Dzień miesiąca</option>
                            <option value="dni_przed_koncem_roku">Dni przed końcem roku (90-dni)</option>
                            <option value="dni_przed_koncem_okresu">Dni przed końcem okresu (2 tyg)</option>
                        </select>
                    </div>
                    <div class="form-group" id="stale-dni-wrap" style="display: none;">
                        <label>Dni tygodnia</label>
                        <div class="dni-tygodnia-checkboxes">
                            <label><input type="checkbox" value="pn"><span>Pn</span></label>
                            <label><input type="checkbox" value="wt"><span>Wt</span></label>
                            <label><input type="checkbox" value="sr"><span>Śr</span></label>
                            <label><input type="checkbox" value="cz"><span>Cz</span></label>
                            <label><input type="checkbox" value="pt"><span>Pt</span></label>
                            <label><input type="checkbox" value="so"><span>So</span></label>
                            <label><input type="checkbox" value="nd"><span>Nd</span></label>
                        </div>
                    </div>
                    <div class="form-group" id="stale-dzien-wrap" style="display: none;">
                        <label>Dzień miesiąca</label>
                        <input type="number" id="stale-dzien-miesiaca" min="1" max="31" placeholder="1-31" style="width: 80px;">
                    </div>
                    <div class="form-group" id="stale-dni-przed-rok-wrap" style="display: none;">
                        <label>Ile dni przed końcem roku</label>
                        <input type="number" id="stale-dni-przed-koncem-roku" min="1" max="90" placeholder="np. 7" style="width: 80px;">
                    </div>
                    <div class="form-group" id="stale-dni-przed-okres-wrap" style="display: none;">
                        <label>Ile dni przed końcem okresu</label>
                        <input type="number" id="stale-dni-przed-koncem-okresu" min="1" max="14" placeholder="np. 3" style="width: 80px;">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Godzina start (domyślna)</label>
                        <input type="time" id="stale-godzina-start">
                        <span style="font-size:11px;color:#888;">można nadpisać per okres</span>
                    </div>
                    <div class="form-group">
                        <label>Czas trwania (min)</label>
                        <input type="number" id="stale-czas" min="0" placeholder="30" style="width: 80px;">
                    </div>
                </div>
                <button type="button" class="button button-primary" id="stale-submit-btn" onclick="saveStaleZadanie()">➕ Dodaj stałe zadanie</button>
                <button type="button" class="button" id="stale-cancel-btn" onclick="cancelStaleEdit()" style="display: none; margin-left: 10px;">Anuluj</button>
                <span id="stale-save-info" style="margin-left: 15px; color: #28a745; font-size: 12px;"></span>
            </div>

            <!-- Lista stałych zadań -->
            <table class="stale-zadania-table" id="stale-zadania-table">
                <thead>
                    <tr>
                        <th>Aktywne</th>
                        <th>Nazwa</th>
                        <th>Kategoria</th>
                        <th>Powtarzanie</th>
                        <th>Godzina</th>
                        <th>Czas</th>
                        <th>Akcje</th>
                    </tr>
                </thead>
                <tbody id="stale-zadania-body">
                    <!-- Wypełniane przez JavaScript -->
                </tbody>
            </table>
        </div>

        <div class="zadaniomat-card">
            <h2>📁 Zarządzanie kategoriami</h2>
            <p style="color: #666; margin-bottom: 15px;">Edytuj kategorie celów i zadań. Kategorie celów to główne obszary strategiczne. Kategorie zadań to wszystkie dostępne kategorie przy dodawaniu zadań.</p>

            <div class="settings-grid">
                <div>
                    <h3 style="margin-top: 0;">Kategorie celów</h3>
                    <p style="font-size: 12px; color: #888;">Te kategorie są używane przy definiowaniu celów rocznych i 2-tygodniowych.</p>
                    <div id="kategorie-cele-list"></div>
                    <button type="button" class="button" onclick="addKategoriaCel()">➕ Dodaj kategorię</button>
                </div>
                <div>
                    <h3 style="margin-top: 0;">Kategorie zadań</h3>
                    <p style="font-size: 12px; color: #888;">Te kategorie są dostępne przy tworzeniu zadań (mogą zawierać dodatkowe).</p>
                    <div id="kategorie-zadania-list"></div>
                    <button type="button" class="button" onclick="addKategoriaZadanie()">➕ Dodaj kategorię</button>
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #eee;">
                <button type="button" class="button button-primary" onclick="saveKategorie()">💾 Zapisz kategorie</button>
                <button type="button" class="button" onclick="resetKategorie()" style="margin-left: 10px;">🔄 Przywróć domyślne</button>
                <span id="kategorie-save-status" style="margin-left: 15px; color: #28a745;"></span>
            </div>

            <!-- Skróty kategorii -->
            <div style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #007bff;">
                <h3>Skróty kategorii</h3>
                <p style="color: #666; margin-bottom: 15px;">Ustaw krótkie skróty (2-4 znaki) dla kategorii. Będą wyświetlane na publicznej stronie statystyk.</p>
                <div id="skroty-list" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 10px;"></div>
                <div style="margin-top: 15px;">
                    <button type="button" class="button button-primary" onclick="saveSkroty()">💾 Zapisz skróty</button>
                    <span id="skroty-save-status" style="margin-left: 15px; color: #28a745;"></span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var nonce = '<?php echo wp_create_nonce('zadaniomat_ajax'); ?>';
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        $('.cel-rok-input').on('change', function() {
            var $this = $(this);
            $.post(ajaxurl, {
                action: 'zadaniomat_save_cel_rok',
                nonce: nonce,
                rok_id: $this.data('rok'),
                kategoria: $this.data('kategoria'),
                cel: $this.val()
            }, function(response) {
                if (response.success) {
                    $this.css('background', '#d4edda');
                    setTimeout(function() { $this.css('background', ''); }, 500);
                }
            });
        });
        
        // Modal funkcje
        window.openOkresModal = function(okresId) {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_okres_cele',
                nonce: nonce,
                okres_id: okresId
            }, function(response) {
                if (response.success) {
                    renderOkresModal(response.data);
                }
            });
        };
        
        window.renderOkresModal = function(data) {
            var okres = data.okres;
            var cele = data.cele; // Teraz to tablica celów per kategoria
            var kategorie = data.kategorie;
            var today = new Date().toISOString().split('T')[0];
            var isPast = okres.data_koniec < today;

            var startDate = new Date(okres.data_start);
            var endDate = new Date(okres.data_koniec);
            var dateStr = startDate.getDate() + '.' + (startDate.getMonth()+1) + ' - ' + endDate.getDate() + '.' + (endDate.getMonth()+1) + '.' + endDate.getFullYear();

            var html = '<div class="modal-overlay" onclick="closeOkresModal(event)">';
            html += '<div class="modal okres-modal" onclick="event.stopPropagation()" style="max-width:900px;">';
            html += '<button class="modal-close" onclick="closeOkresModal()">&times;</button>';
            html += '<div class="okres-modal-header ' + (isPast ? 'past' : '') + '">';
            html += '<h3>' + (isPast ? '📊 Podsumowanie: ' : '🎯 Cele: ') + escapeHtml(okres.nazwa) + '</h3>';
            html += '<div class="dates">📅 ' + dateStr + '</div>';
            html += '</div>';

            for (var kat in kategorie) {
                var katCele = cele[kat] || [];
                var uwagi = '';

                // Zbierz uwagi z wszystkich celów kategorii
                katCele.forEach(function(c) { if (c.uwagi) uwagi = c.uwagi; });

                html += '<div class="cel-review-card ' + kat + '" data-kategoria="' + kat + '" data-okres="' + okres.id + '">';
                html += '<h4>' + kategorie[kat] + '</h4>';

                // Lista celów (wszystkie - aktywne i ukończone)
                html += '<div class="cele-lista" style="margin-bottom:10px;">';

                if (katCele.length === 0) {
                    html += '<div class="cel-item-empty" style="color:#999; font-size:12px; padding:8px;">Brak celów - dodaj poniżej</div>';
                } else {
                    katCele.forEach(function(cel) {
                        var isCompleted = cel.completed_at !== null;
                        var osiagniety = cel.osiagniety;
                        var statusClass = '';
                        if (osiagniety === '1' || osiagniety === 1 || parseInt(osiagniety) === 1) statusClass = 'osiagniety-yes';
                        else if (osiagniety === '0' || osiagniety === 0 || parseInt(osiagniety) === 0) statusClass = 'osiagniety-no';

                        html += '<div class="cel-item ' + statusClass + '" data-cel-id="' + cel.id + '" style="display:flex; gap:8px; align-items:flex-start; padding:8px; background:' + (isCompleted ? '#f8f9fa' : '#fff') + '; border:1px solid #ddd; border-radius:6px; margin-bottom:6px;">';

                        // Tekst celu (edytowalny)
                        html += '<div style="flex:1;">';
                        html += '<textarea class="cel-text-input" data-cel-id="' + cel.id + '" style="width:100%; min-height:40px; border:1px solid #e0e0e0; border-radius:4px; padding:6px; font-size:13px; resize:vertical;">' + escapeHtml(cel.cel || '') + '</textarea>';
                        html += '</div>';

                        // Status osiągnięcia (tylko Tak/Nie)
                        html += '<div style="display:flex; flex-direction:column; gap:4px; min-width:100px;">';
                        html += '<select class="cel-status-select" data-cel-id="' + cel.id + '" data-okres="' + okres.id + '" data-kategoria="' + kat + '" style="padding:4px; border-radius:4px; font-size:12px;">';
                        html += '<option value="">--</option>';
                        html += '<option value="1"' + (parseInt(osiagniety) === 1 ? ' selected' : '') + '>✅ Tak</option>';
                        html += '<option value="0"' + (parseInt(osiagniety) === 0 ? ' selected' : '') + '>❌ Nie</option>';
                        html += '</select>';

                        // Przycisk usunięcia (tylko jeśli cel jest pusty lub nieaktywny)
                        html += '<button class="btn-delete-cel" data-cel-id="' + cel.id + '" data-kategoria="' + kat + '" style="background:#dc3545; color:#fff; border:none; border-radius:4px; padding:4px 8px; cursor:pointer; font-size:11px;">🗑️</button>';
                        html += '</div>';

                        html += '</div>';
                    });
                }

                // Przycisk dodania nowego celu
                html += '<button class="btn-add-cel" data-okres="' + okres.id + '" data-kategoria="' + kat + '" style="width:100%; padding:8px; background:#28a745; color:#fff; border:none; border-radius:4px; cursor:pointer; font-size:12px; margin-top:4px;">➕ Dodaj cel</button>';
                html += '</div>';

                // Uwagi / wnioski (wspólne dla kategorii)
                html += '<div class="field" style="margin-top:10px;">';
                html += '<label style="font-size:12px; color:#666;">📝 Uwagi / wnioski</label>';
                html += '<textarea class="uwagi-input" data-okres="' + okres.id + '" data-kategoria="' + kat + '" placeholder="Co poszło dobrze? Co można poprawić?" style="width:100%; min-height:50px; border:1px solid #ddd; border-radius:4px; padding:6px; font-size:12px;">' + escapeHtml(uwagi) + '</textarea>';
                html += '</div>';

                html += '</div>';
            }

            html += '<div class="modal-buttons">';
            html += '<button class="button button-primary" onclick="closeOkresModal()">Zamknij</button>';
            html += '</div>';
            html += '</div></div>';

            $('body').append(html);

            // Bind events - zmiana tekstu celu
            $('.cel-text-input').on('change', function() {
                var $this = $(this);
                var celId = $this.data('cel-id');
                var newText = $this.val().trim();

                $.post(ajaxurl, {
                    action: 'zadaniomat_update_cel_text',
                    nonce: nonce,
                    cel_id: celId,
                    cel: newText
                }, function(response) {
                    if (response.success) {
                        $this.css('border-color', '#28a745');
                        setTimeout(function() { $this.css('border-color', '#e0e0e0'); }, 500);
                        // Odśwież dashboard jeśli jest widoczny
                        if (typeof loadAllGoalsSummaries === 'function') loadAllGoalsSummaries();
                    }
                });
            });

            // Bind events - zmiana statusu celu
            $('.cel-status-select').on('change', function() {
                var $this = $(this);
                var celId = $this.data('cel-id');
                var okresId = $this.data('okres');
                var kategoria = $this.data('kategoria');
                var $item = $this.closest('.cel-item');

                $.post(ajaxurl, {
                    action: 'zadaniomat_save_cel_podsumowanie',
                    nonce: nonce,
                    cel_id: celId,
                    okres_id: okresId,
                    kategoria: kategoria,
                    osiagniety: $this.val(),
                    uwagi: ''
                }, function(response) {
                    if (response.success) {
                        $item.removeClass('osiagniety-yes osiagniety-no');
                        if ($this.val() === '1') $item.addClass('osiagniety-yes');
                        else if ($this.val() === '0') $item.addClass('osiagniety-no');
                        // Odśwież dashboard
                        if (typeof loadAllGoalsSummaries === 'function') loadAllGoalsSummaries();
                        refreshDashboardGoals();
                    }
                });
            });

            // Bind events - dodawanie nowego celu
            $('.btn-add-cel').on('click', function() {
                var $btn = $(this);
                var okresId = $btn.data('okres');
                var kategoria = $btn.data('kategoria');

                $.post(ajaxurl, {
                    action: 'zadaniomat_add_next_goal',
                    nonce: nonce,
                    okres_id: okresId,
                    kategoria: kategoria,
                    cel: ''
                }, function(response) {
                    if (response.success) {
                        // Przeładuj modal
                        closeOkresModal();
                        openOkresModal(okresId);
                        // Odśwież dashboard
                        if (typeof loadAllGoalsSummaries === 'function') loadAllGoalsSummaries();
                    }
                });
            });

            // Bind events - usuwanie celu
            $('.btn-delete-cel').on('click', function() {
                var $btn = $(this);
                var celId = $btn.data('cel-id');
                var kategoria = $btn.data('kategoria');

                if (!confirm('Czy na pewno usunąć ten cel?')) return;

                $.post(ajaxurl, {
                    action: 'zadaniomat_delete_cel',
                    nonce: nonce,
                    cel_id: celId
                }, function(response) {
                    if (response.success) {
                        $btn.closest('.cel-item').fadeOut(200, function() { $(this).remove(); });
                        // Odśwież dashboard
                        if (typeof loadAllGoalsSummaries === 'function') loadAllGoalsSummaries();
                        refreshDashboardGoals();
                    }
                });
            });

            // Bind events - uwagi
            $('.uwagi-input').on('change', function() {
                var $this = $(this);
                var okresId = $this.data('okres');
                var kategoria = $this.data('kategoria');
                var $card = $this.closest('.cel-review-card');
                var $firstCel = $card.find('.cel-status-select').first();
                var celId = $firstCel.length ? $firstCel.data('cel-id') : 0;

                if (celId) {
                    $.post(ajaxurl, {
                        action: 'zadaniomat_save_cel_podsumowanie',
                        nonce: nonce,
                        cel_id: celId,
                        okres_id: okresId,
                        kategoria: kategoria,
                        osiagniety: $firstCel.val() || '',
                        uwagi: $this.val()
                    }, function(response) {
                        if (response.success) {
                            $this.css('border-color', '#28a745');
                            setTimeout(function() { $this.css('border-color', '#ddd'); }, 500);
                        }
                    });
                }
            });
        };

        // Funkcja do odświeżenia celów na dashboardzie
        window.refreshDashboardGoals = function() {
            // Przeładuj panel celów jeśli jesteśmy na dashboardzie
            if ($('.goals-panel').length) {
                location.reload();
            }
        };
        
        window.saveCelPodsumowanie = function(okresId, kategoria, osiagniety, uwagi, $card) {
            $.post(ajaxurl, {
                action: 'zadaniomat_save_cel_podsumowanie',
                nonce: nonce,
                okres_id: okresId,
                kategoria: kategoria,
                osiagniety: osiagniety,
                uwagi: uwagi
            }, function(response) {
                if (response.success) {
                    // Update card color
                    $card.removeClass('osiagniety-yes osiagniety-no osiagniety-partial');
                    if (osiagniety === '1') $card.addClass('osiagniety-yes');
                    else if (osiagniety === '0') $card.addClass('osiagniety-no');
                    else if (osiagniety === '2') $card.addClass('osiagniety-partial');
                    
                    // Flash effect
                    $card.css('box-shadow', '0 0 10px #28a745');
                    setTimeout(function() { $card.css('box-shadow', ''); }, 500);
                }
            });
        };
        
        window.closeOkresModal = function(event) {
            if (event && event.target !== event.currentTarget) return;
            $('.modal-overlay').remove();
        };
        
        window.escapeHtml = function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        };
        
        // Close modal on ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') closeOkresModal();
        });

        // ==================== ZARZĄDZANIE KATEGORIAMI ====================
        var kategorieCele = <?php echo json_encode(zadaniomat_get_kategorie()); ?>;
        var kategorieZadania = <?php echo json_encode(zadaniomat_get_kategorie_zadania()); ?>;

        function renderKategorieList() {
            var htmlCele = '';
            for (var key in kategorieCele) {
                htmlCele += '<div class="kategoria-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">';
                htmlCele += '<input type="text" class="kat-cel-key" value="' + escapeHtml(key) + '" placeholder="klucz" style="width: 120px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
                htmlCele += '<input type="text" class="kat-cel-label" value="' + escapeHtml(kategorieCele[key]) + '" placeholder="Nazwa" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
                htmlCele += '<button type="button" class="button button-small" onclick="removeKategoriaCel(this)" style="color: #dc3545;">✕</button>';
                htmlCele += '</div>';
            }
            $('#kategorie-cele-list').html(htmlCele);

            var htmlZadania = '';
            for (var key in kategorieZadania) {
                htmlZadania += '<div class="kategoria-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">';
                htmlZadania += '<input type="text" class="kat-zad-key" value="' + escapeHtml(key) + '" placeholder="klucz" style="width: 120px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
                htmlZadania += '<input type="text" class="kat-zad-label" value="' + escapeHtml(kategorieZadania[key]) + '" placeholder="Nazwa" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
                htmlZadania += '<button type="button" class="button button-small" onclick="removeKategoriaZadanie(this)" style="color: #dc3545;">✕</button>';
                htmlZadania += '</div>';
            }
            $('#kategorie-zadania-list').html(htmlZadania);
        }

        window.addKategoriaCel = function() {
            var html = '<div class="kategoria-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">';
            html += '<input type="text" class="kat-cel-key" value="" placeholder="klucz" style="width: 120px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
            html += '<input type="text" class="kat-cel-label" value="" placeholder="Nazwa" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
            html += '<button type="button" class="button button-small" onclick="removeKategoriaCel(this)" style="color: #dc3545;">✕</button>';
            html += '</div>';
            $('#kategorie-cele-list').append(html);
        };

        window.addKategoriaZadanie = function() {
            var html = '<div class="kategoria-row" style="display: flex; gap: 10px; margin-bottom: 8px; align-items: center;">';
            html += '<input type="text" class="kat-zad-key" value="" placeholder="klucz" style="width: 120px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
            html += '<input type="text" class="kat-zad-label" value="" placeholder="Nazwa" style="flex: 1; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px;">';
            html += '<button type="button" class="button button-small" onclick="removeKategoriaZadanie(this)" style="color: #dc3545;">✕</button>';
            html += '</div>';
            $('#kategorie-zadania-list').append(html);
        };

        window.removeKategoriaCel = function(btn) {
            $(btn).closest('.kategoria-row').remove();
        };

        window.removeKategoriaZadanie = function(btn) {
            $(btn).closest('.kategoria-row').remove();
        };

        window.saveKategorie = function() {
            var kategorie = [];
            var kategorieZad = [];

            $('#kategorie-cele-list .kategoria-row').each(function() {
                var key = $(this).find('.kat-cel-key').val().trim();
                var label = $(this).find('.kat-cel-label').val().trim();
                if (key && label) {
                    kategorie.push({key: key, label: label});
                }
            });

            $('#kategorie-zadania-list .kategoria-row').each(function() {
                var key = $(this).find('.kat-zad-key').val().trim();
                var label = $(this).find('.kat-zad-label').val().trim();
                if (key && label) {
                    kategorieZad.push({key: key, label: label});
                }
            });

            $.post(ajaxurl, {
                action: 'zadaniomat_save_kategorie',
                nonce: nonce,
                kategorie: kategorie,
                kategorie_zadania: kategorieZad
            }, function(response) {
                if (response.success) {
                    $('#kategorie-save-status').text('✓ Zapisano! Odśwież stronę, aby zobaczyć zmiany.').show();
                    setTimeout(function() { $('#kategorie-save-status').fadeOut(); }, 5000);
                } else {
                    alert('Błąd podczas zapisywania kategorii.');
                }
            });
        };

        window.resetKategorie = function() {
            if (!confirm('Czy na pewno chcesz przywrócić domyślne kategorie? Twoje zmiany zostaną utracone.')) {
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_reset_kategorie',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    kategorieCele = response.data.kategorie;
                    kategorieZadania = response.data.kategorie_zadania;
                    renderKategorieList();
                    $('#kategorie-save-status').text('✓ Przywrócono domyślne kategorie!').show();
                    setTimeout(function() { $('#kategorie-save-status').fadeOut(); }, 3000);
                }
            });
        };

        // ==================== SKRÓTY KATEGORII ====================
        var skroty = {};

        function loadSkroty() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_skroty',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    skroty = response.data.skroty || {};
                    renderSkrotyList();
                }
            });
        }

        function renderSkrotyList() {
            var html = '';
            for (var key in kategorieZadania) {
                var skrot = skroty[key] || '';
                html += '<div style="display: flex; gap: 10px; align-items: center; background: #f8f9fa; padding: 10px; border-radius: 6px;">';
                html += '<span style="min-width: 150px; font-weight: 500;">' + escapeHtml(kategorieZadania[key]) + '</span>';
                html += '<input type="text" class="skrot-input" data-kategoria="' + key + '" value="' + escapeHtml(skrot) + '" placeholder="np. ZAP" maxlength="6" style="width: 80px; padding: 6px 10px; border: 1px solid #ddd; border-radius: 4px; text-transform: uppercase; font-weight: bold;">';
                html += '</div>';
            }
            $('#skroty-list').html(html);
        }

        window.saveSkroty = function() {
            var skrotyData = {};
            $('.skrot-input').each(function() {
                var kat = $(this).data('kategoria');
                var val = $(this).val().trim().toUpperCase();
                if (kat) {
                    skrotyData[kat] = val;
                }
            });

            $.post(ajaxurl, {
                action: 'zadaniomat_save_skroty',
                nonce: nonce,
                skroty: skrotyData
            }, function(response) {
                if (response.success) {
                    skroty = skrotyData;
                    $('#skroty-save-status').text('✓ Skróty zapisane!').show();
                    setTimeout(function() { $('#skroty-save-status').fadeOut(); }, 3000);
                } else {
                    alert('Błąd podczas zapisywania skrótów.');
                }
            });
        };

        // Załaduj skróty przy starcie
        loadSkroty();

        // ==================== STAŁE ZADANIA ====================
        var staleZadania = [];

        window.toggleStaleOptions = function() {
            var typ = $('#stale-typ').val();
            $('#stale-dni-wrap').toggle(typ === 'dni_tygodnia');
            $('#stale-dzien-wrap').toggle(typ === 'dzien_miesiaca');
            $('#stale-dni-przed-rok-wrap').toggle(typ === 'dni_przed_koncem_roku');
            $('#stale-dni-przed-okres-wrap').toggle(typ === 'dni_przed_koncem_okresu');
        };

        window.loadStaleZadania = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_stale_zadania',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    staleZadania = response.data.stale_zadania;
                    renderStaleZadania();
                }
            });
        };

        window.renderStaleZadania = function() {
            var html = '';

            if (staleZadania.length === 0) {
                html = '<tr><td colspan="7" style="text-align: center; color: #888; padding: 30px;">Brak stałych zadań. Dodaj pierwsze zadanie powyżej.</td></tr>';
            } else {
                staleZadania.forEach(function(zadanie) {
                    var powtarzanie = '';
                    if (zadanie.typ_powtarzania === 'codziennie') {
                        powtarzanie = '📅 Codziennie';
                    } else if (zadanie.typ_powtarzania === 'dni_tygodnia') {
                        powtarzanie = '📆 ' + (zadanie.dni_tygodnia || '').toUpperCase().replace(/,/g, ', ');
                    } else if (zadanie.typ_powtarzania === 'dzien_miesiaca') {
                        powtarzanie = '🗓️ ' + zadanie.dzien_miesiaca + ' dnia miesiąca';
                    } else if (zadanie.typ_powtarzania === 'dni_przed_koncem_roku') {
                        powtarzanie = '🎯 ' + zadanie.dni_przed_koncem_roku + ' dni przed końcem roku';
                    } else if (zadanie.typ_powtarzania === 'dni_przed_koncem_okresu') {
                        powtarzanie = '📊 ' + zadanie.dni_przed_koncem_okresu + ' dni przed końcem okresu';
                    }

                    // Dodatkowe info
                    var extraInfo = [];
                    if (zadanie.dodaj_do_listy == 1) {
                        extraInfo.push('📋 Lista');
                    }

                    var godziny = '-';
                    if (zadanie.godzina_start) {
                        godziny = zadanie.godzina_start.substring(0, 5);
                        if (zadanie.godzina_koniec) {
                            godziny += ' - ' + zadanie.godzina_koniec.substring(0, 5);
                        }
                    }

                    html += '<tr data-id="' + zadanie.id + '">';
                    html += '<td>';
                    html += '<label class="toggle-switch">';
                    html += '<input type="checkbox" ' + (zadanie.aktywne == 1 ? 'checked' : '') + ' onchange="toggleStaleAktywne(' + zadanie.id + ', this.checked)">';
                    html += '<span class="toggle-slider"></span>';
                    html += '</label>';
                    html += '</td>';
                    html += '<td><strong>' + escapeHtml(zadanie.nazwa) + '</strong></td>';
                    html += '<td><span class="kategoria-badge ' + zadanie.kategoria + '">' + zadanie.kategoria_label + '</span></td>';
                    html += '<td>' + powtarzanie;
                    if (extraInfo.length > 0) {
                        html += '<br><span style="font-size:11px;color:#888;">' + extraInfo.join(' • ') + '</span>';
                    }
                    html += '</td>';
                    html += '<td>' + godziny + '</td>';
                    html += '<td>' + (zadanie.planowany_czas || '-') + ' min</td>';
                    html += '<td class="action-buttons">';
                    html += '<button class="btn-time" onclick="openStaleOverrides(' + zadanie.id + ', \'' + escapeHtml(zadanie.nazwa).replace(/'/g, "\\'") + '\')" title="Ustaw godziny per okres">⏰</button>';
                    html += '<button class="btn-edit" onclick="editStaleZadanie(' + zadanie.id + ')" title="Edytuj">✏️</button>';
                    html += '<button class="btn-delete" onclick="deleteStaleZadanie(' + zadanie.id + ')" title="Usuń">🗑️</button>';
                    html += '</td>';
                    html += '</tr>';
                });
            }

            $('#stale-zadania-body').html(html);
        };

        // Edytuj stałe zadanie - wypełnij formularz
        window.editStaleZadanie = function(id) {
            var zadanie = staleZadania.find(function(z) { return z.id == id; });
            if (!zadanie) return;

            // Wypełnij formularz danymi
            $('#stale-edit-id').val(zadanie.id);
            $('#stale-nazwa').val(zadanie.nazwa);
            $('#stale-kategoria').val(zadanie.kategoria);
            $('#stale-czas').val(zadanie.planowany_czas || '');
            $('#stale-cel-todo').val(zadanie.cel_todo || '');
            $('#stale-typ').val(zadanie.typ_powtarzania);
            $('#stale-godzina-start').val(zadanie.godzina_start ? zadanie.godzina_start.substring(0, 5) : '');
            $('#stale-dzien-miesiaca').val(zadanie.dzien_miesiaca || '');
            $('#stale-dni-przed-koncem-roku').val(zadanie.dni_przed_koncem_roku || '');
            $('#stale-dni-przed-koncem-okresu').val(zadanie.dni_przed_koncem_okresu || '');

            // Zaznacz dni tygodnia
            $('.dni-tygodnia-checkboxes input').prop('checked', false);
            if (zadanie.dni_tygodnia) {
                var dni = zadanie.dni_tygodnia.split(',');
                dni.forEach(function(dzien) {
                    $('.dni-tygodnia-checkboxes input[value="' + dzien + '"]').prop('checked', true);
                });
            }

            // Pokaż odpowiednie opcje
            toggleStaleOptions();

            // Zmień tytuł i przyciski
            $('#stale-form-title').text('✏️ Edytuj stałe zadanie');
            $('#stale-submit-btn').text('💾 Zapisz zmiany');
            $('#stale-cancel-btn').show();

            // Przewiń do formularza
            $('.stale-zadania-form')[0].scrollIntoView({ behavior: 'smooth' });
        };

        // Anuluj edycję
        window.cancelStaleEdit = function() {
            resetStaleForm();
        };

        // Reset formularza
        window.resetStaleForm = function() {
            $('#stale-edit-id').val('');
            $('#stale-nazwa').val('');
            $('#stale-czas').val('');
            $('#stale-cel-todo').val('');
            $('#stale-godzina-start').val('');
            $('#stale-typ').val('codziennie');
            $('#stale-kategoria').val($('#stale-kategoria option:first').val());
            toggleStaleOptions();
            $('.dni-tygodnia-checkboxes input').prop('checked', false);
            $('#stale-dzien-miesiaca').val('');
            $('#stale-dni-przed-koncem-roku').val('');
            $('#stale-dni-przed-koncem-okresu').val('');
            $('#stale-save-info').text('');

            // Przywróć tytuł i przyciski
            $('#stale-form-title').text('➕ Dodaj stałe zadanie');
            $('#stale-submit-btn').text('➕ Dodaj stałe zadanie');
            $('#stale-cancel-btn').hide();
        };

        // Zapisz stałe zadanie (dodaj lub edytuj)
        window.saveStaleZadanie = function() {
            var nazwa = $('#stale-nazwa').val().trim();
            if (!nazwa) {
                alert('Wpisz nazwę zadania!');
                return;
            }

            var editId = $('#stale-edit-id').val();
            var typ = $('#stale-typ').val();
            var dniTygodnia = '';
            if (typ === 'dni_tygodnia') {
                var selected = [];
                $('.dni-tygodnia-checkboxes input:checked').each(function() {
                    selected.push($(this).val());
                });
                dniTygodnia = selected.join(',');
            }

            // Oblicz godzinę końca z godziny startu + czas trwania
            var godzinaStart = $('#stale-godzina-start').val();
            var czasTrwania = parseInt($('#stale-czas').val()) || 0;
            var godzinaKoniec = '';

            // Auto-oblicz godzinę końca jeśli mamy start i czas
            if (godzinaStart && czasTrwania > 0) {
                var startParts = godzinaStart.split(':');
                var startMinutes = parseInt(startParts[0]) * 60 + parseInt(startParts[1]);
                var endMinutes = startMinutes + czasTrwania;
                var endHours = Math.floor(endMinutes / 60) % 24;
                var endMins = endMinutes % 60;
                godzinaKoniec = String(endHours).padStart(2, '0') + ':' + String(endMins).padStart(2, '0');
            }

            var data = {
                nonce: nonce,
                nazwa: nazwa,
                kategoria: $('#stale-kategoria').val(),
                cel_todo: $('#stale-cel-todo').val(),
                planowany_czas: czasTrwania,
                typ_powtarzania: typ,
                dni_tygodnia: dniTygodnia,
                dzien_miesiaca: $('#stale-dzien-miesiaca').val(),
                dni_przed_koncem_roku: $('#stale-dni-przed-koncem-roku').val(),
                dni_przed_koncem_okresu: $('#stale-dni-przed-koncem-okresu').val(),
                godzina_start: godzinaStart,
                godzina_koniec: godzinaKoniec
            };

            if (editId) {
                // Aktualizacja istniejącego
                data.action = 'zadaniomat_edit_stale_zadanie';
                data.id = editId;

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        // Zaktualizuj w tablicy
                        var index = staleZadania.findIndex(function(z) { return z.id == editId; });
                        if (index !== -1) {
                            staleZadania[index] = response.data.zadanie;
                        }
                        renderStaleZadania();
                        var msg = 'Zaktualizowano! ';
                        if (response.data.deleted_count > 0) msg += 'Usunięto ' + response.data.deleted_count + ' starych. ';
                        if (response.data.generated_count > 0) msg += 'Wygenerowano ' + response.data.generated_count + ' nowych zadań.';
                        $('#stale-save-info').text(msg);
                        resetStaleForm();
                        showToast('Stałe zadanie zaktualizowane!', 'success');
                    }
                });
            } else {
                // Dodanie nowego
                data.action = 'zadaniomat_add_stale_zadanie';

                $.post(ajaxurl, data, function(response) {
                    if (response.success) {
                        staleZadania.push(response.data.zadanie);
                        renderStaleZadania();
                        var msg = 'Dodano! Wygenerowano ' + response.data.generated_count + ' zadań na ten rok.';
                        $('#stale-save-info').text(msg);
                        resetStaleForm();
                        showToast('Stałe zadanie dodane!', 'success');
                    }
                });
            }
        };

        window.toggleStaleAktywne = function(id, aktywne) {
            $.post(ajaxurl, {
                action: 'zadaniomat_toggle_stale_zadanie',
                nonce: nonce,
                id: id,
                aktywne: aktywne ? 1 : 0
            }, function(response) {
                if (response.success) {
                    var zadanie = staleZadania.find(function(z) { return z.id == id; });
                    if (zadanie) {
                        zadanie.aktywne = aktywne ? 1 : 0;
                    }
                    showToast(aktywne ? 'Zadanie aktywowane' : 'Zadanie dezaktywowane', 'success');
                }
            });
        };

        window.deleteStaleZadanie = function(id) {
            // Najpierw sprawdź ile jest przyszłych zadań
            $.post(ajaxurl, {
                action: 'zadaniomat_count_future_tasks',
                nonce: nonce,
                template_id: id
            }, function(countResponse) {
                if (!countResponse.success) {
                    alert('Błąd przy sprawdzaniu zadań');
                    return;
                }

                var futureCount = countResponse.data.count;
                var message = 'Usunąć stałe zadanie?\n\n';

                if (futureCount > 0) {
                    message += 'Masz ' + futureCount + ' przyszłych zadań wygenerowanych z tego wzorca.\n\n';
                    message += 'Wybierz opcję:\n';
                    message += '- OK = Usuń wzorzec I przyszłe zadania\n';
                    message += '- Anuluj = Wyjdź bez usuwania\n\n';
                    message += '(Aby zachować przyszłe zadania jako zwykłe, najpierw dezaktywuj wzorzec)';
                }

                if (!confirm(message)) return;

                // Pytanie czy usunąć przyszłe zadania
                var deleteFuture = futureCount > 0 ? confirm('Czy usunąć też wszystkie ' + futureCount + ' przyszłych zadań?\n\nOK = Usuń wszystkie\nAnuluj = Zachowaj jako zwykłe zadania') : false;

                $.post(ajaxurl, {
                    action: 'zadaniomat_delete_stale_zadanie',
                    nonce: nonce,
                    id: id,
                    delete_future: deleteFuture ? 1 : 0
                }, function(response) {
                    if (response.success) {
                        staleZadania = staleZadania.filter(function(z) { return z.id != id; });
                        renderStaleZadania();
                        var msg = 'Stałe zadanie usunięte';
                        if (response.data.deleted_tasks > 0) {
                            msg += ' (usunięto też ' + response.data.deleted_tasks + ' przyszłych zadań)';
                        }
                        showToast(msg, 'success');
                    }
                });
            });
        };

        // Otwórz dialog nadpisań godzin per okres
        window.openStaleOverrides = function(staleId, staleName) {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_stale_overrides',
                nonce: nonce,
                stale_id: staleId
            }, function(response) {
                if (!response.success) return;

                var okresy = response.data.okresy;
                var overrides = response.data.overrides || {};

                // Znajdź domyślną godzinę z template'a
                var template = staleZadania.find(function(z) { return z.id == staleId; });
                var defaultTime = template && template.godzina_start ? template.godzina_start.substring(0, 5) : '';

                var html = '<div class="modal-overlay stale-overrides-modal" onclick="closeStaleOverridesModal(event)">';
                html += '<div class="modal" onclick="event.stopPropagation()" style="max-width:600px;">';
                html += '<button class="modal-close" onclick="closeStaleOverridesModal()">&times;</button>';
                html += '<h3>⏰ Godziny startu: ' + escapeHtml(staleName) + '</h3>';
                html += '<p style="color:#666;margin-bottom:15px;">Domyślna godzina: <strong>' + (defaultTime || 'brak') + '</strong>. Ustaw inne godziny dla poszczególnych okresów.</p>';

                if (okresy.length === 0) {
                    html += '<p style="color:#888;">Brak przyszłych okresów.</p>';
                } else {
                    html += '<table style="width:100%;border-collapse:collapse;">';
                    html += '<thead><tr style="background:#f8f9fa;"><th style="padding:8px;text-align:left;">Okres</th><th style="padding:8px;text-align:left;">Daty</th><th style="padding:8px;text-align:center;">Godzina start</th></tr></thead><tbody>';

                    okresy.forEach(function(okres) {
                        var currentOverride = overrides[okres.id] || '';
                        var startDate = new Date(okres.data_start);
                        var endDate = new Date(okres.data_koniec);
                        var dateStr = startDate.getDate() + '.' + (startDate.getMonth()+1) + ' - ' + endDate.getDate() + '.' + (endDate.getMonth()+1);

                        html += '<tr>';
                        html += '<td style="padding:8px;border-bottom:1px solid #eee;"><strong>' + escapeHtml(okres.nazwa) + '</strong></td>';
                        html += '<td style="padding:8px;border-bottom:1px solid #eee;font-size:12px;color:#666;">' + dateStr + '</td>';
                        html += '<td style="padding:8px;border-bottom:1px solid #eee;text-align:center;">';
                        html += '<input type="time" class="override-time-input" data-okres-id="' + okres.id + '" value="' + currentOverride + '" style="padding:4px;" placeholder="' + defaultTime + '">';
                        html += '</td>';
                        html += '</tr>';
                    });

                    html += '</tbody></table>';
                }

                html += '<div style="margin-top:20px;text-align:right;">';
                html += '<button class="button" onclick="closeStaleOverridesModal()">Anuluj</button> ';
                html += '<button class="button button-primary" onclick="saveStaleOverrides(' + staleId + ')">💾 Zapisz</button>';
                html += '</div>';
                html += '</div></div>';

                $('body').append(html);
            });
        };

        window.closeStaleOverridesModal = function(event) {
            if (event && event.target !== event.currentTarget) return;
            $('.stale-overrides-modal').remove();
        };

        window.saveStaleOverrides = function(staleId) {
            var inputs = $('.stale-overrides-modal .override-time-input');
            var savePromises = [];

            inputs.each(function() {
                var okresId = $(this).data('okres-id');
                var godzinaStart = $(this).val();

                savePromises.push($.post(ajaxurl, {
                    action: 'zadaniomat_save_stale_override',
                    nonce: nonce,
                    stale_id: staleId,
                    okres_id: okresId,
                    godzina_start: godzinaStart
                }));
            });

            $.when.apply($, savePromises).done(function() {
                closeStaleOverridesModal();
                showToast('Godziny zapisane i zadania zregenerowane', 'success');
            });
        };

        window.showToast = function(message, type) {
            var toast = $('<div style="position:fixed;bottom:20px;right:20px;background:' + (type === 'success' ? '#28a745' : '#dc3545') + ';color:#fff;padding:12px 20px;border-radius:8px;z-index:9999;">' + message + '</div>');
            $('body').append(toast);
            setTimeout(function() { toast.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        };

        // Inicjalizacja
        renderKategorieList();
        loadStaleZadania();
    });
    </script>
    <?php
}

// =============================================
// GAMIFICATION SETTINGS PAGE
// =============================================
function zadaniomat_page_gamification() {
    global $wpdb;
    $config = zadaniomat_get_gam_config();
    $achievements = zadaniomat_get_achievements();
    $challenges = zadaniomat_get_daily_challenges_config();
    $xp_log_table = $wpdb->prefix . 'zadaniomat_xp_log';

    ?>
    <div class="wrap zadaniomat-wrap">
        <h1>🎮 Ustawienia Gamifikacji</h1>

        <div class="gam-settings-tabs">
            <button class="gam-tab active" data-tab="xp-history">📊 Historia XP</button>
            <button class="gam-tab" data-tab="levels">📈 Poziomy</button>
            <button class="gam-tab" data-tab="xp-values">⭐ Wartości XP</button>
            <button class="gam-tab" data-tab="multipliers">✖️ Mnożniki</button>
            <button class="gam-tab" data-tab="challenges">🎯 Wyzwania</button>
            <button class="gam-tab" data-tab="achievements">🏆 Osiągnięcia</button>
            <button class="gam-tab" data-tab="abstract-goals">🎯 Cele abstrakcyjne</button>
            <button class="gam-tab" data-tab="settings">⚙️ Inne ustawienia</button>
        </div>

        <!-- XP History Tab -->
        <div class="gam-tab-content active" id="tab-xp-history">
            <div class="zadaniomat-card">
                <h2>📊 Historia przyznanych punktów XP</h2>
                <p style="color: #666; margin-bottom: 15px;">Tu możesz zobaczyć wszystkie przyznane punkty i usunąć błędne wpisy.</p>

                <div style="margin-bottom: 15px; display: flex; gap: 10px; align-items: center;">
                    <select id="xp-history-filter" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                        <option value="">Wszystkie typy</option>
                        <option value="task">Zadania</option>
                        <option value="goal">Cele</option>
                        <option value="streak">Streaki</option>
                        <option value="achievement">Osiągnięcia</option>
                        <option value="challenge">Wyzwania</option>
                        <option value="bonus">Bonusy</option>
                    </select>
                    <input type="date" id="xp-history-date" style="padding: 8px; border-radius: 4px; border: 1px solid #ddd;">
                    <button class="button" onclick="loadXPHistory()">🔍 Filtruj</button>
                    <button class="button" onclick="loadXPHistory(true)">🔄 Odśwież</button>
                </div>

                <table class="xp-history-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Data</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Typ</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Opis</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Warunek</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">XP Bazowe</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Mnożnik</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: right;">XP Końcowe</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: center;">Akcje</th>
                        </tr>
                    </thead>
                    <tbody id="xp-history-body">
                        <tr><td colspan="8" style="text-align: center; padding: 20px;">Ładowanie...</td></tr>
                    </tbody>
                </table>

                <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                    <div id="xp-history-pagination"></div>
                    <div id="xp-history-summary" style="font-weight: bold;"></div>
                </div>
            </div>
        </div>

        <!-- Levels Tab -->
        <div class="gam-tab-content" id="tab-levels">
            <div class="zadaniomat-card">
                <h2>📈 Konfiguracja poziomów</h2>
                <p style="color: #666; margin-bottom: 15px;">Ustaw progi XP, nazwy i ikony dla każdego poziomu.</p>

                <table class="levels-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 10px; border: 1px solid #ddd;">Poziom</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">XP (od)</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Nazwa</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Ikona</th>
                        </tr>
                    </thead>
                    <tbody id="levels-body">
                        <?php foreach ($config['levels'] as $level => $data): ?>
                        <tr>
                            <td style="padding: 10px; border: 1px solid #ddd; text-align: center; font-weight: bold;"><?php echo $level; ?></td>
                            <td style="padding: 10px; border: 1px solid #ddd;">
                                <input type="number" class="level-xp" data-level="<?php echo $level; ?>"
                                       value="<?php echo $data['xp']; ?>" min="0"
                                       style="width: 100px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                            </td>
                            <td style="padding: 10px; border: 1px solid #ddd;">
                                <input type="text" class="level-name" data-level="<?php echo $level; ?>"
                                       value="<?php echo esc_attr($data['name']); ?>"
                                       style="width: 150px; padding: 6px; border: 1px solid #ddd; border-radius: 4px;">
                            </td>
                            <td style="padding: 10px; border: 1px solid #ddd;">
                                <input type="text" class="level-icon" data-level="<?php echo $level; ?>"
                                       value="<?php echo esc_attr($data['icon']); ?>"
                                       style="width: 60px; padding: 6px; border: 1px solid #ddd; border-radius: 4px; text-align: center; font-size: 18px;">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 15px;">
                    <button class="button button-primary" onclick="saveLevelsConfig()">💾 Zapisz poziomy</button>
                    <button class="button" onclick="resetLevelsConfig()" style="margin-left: 10px;">🔄 Przywróć domyślne</button>
                    <span id="levels-save-status" style="margin-left: 15px; color: #28a745;"></span>
                </div>
            </div>
        </div>

        <!-- XP Values Tab -->
        <div class="gam-tab-content" id="tab-xp-values">
            <div class="zadaniomat-card">
                <h2>⭐ Wartości XP za akcje</h2>
                <p style="color: #666; margin-bottom: 15px;">Ustaw ile punktów XP jest przyznawane za poszczególne akcje.</p>

                <div class="xp-values-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <!-- Zadania -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-top: 0; color: #007bff;">📝 Zadania</h4>
                        <div class="xp-value-item">
                            <label>Ukończenie zadania:</label>
                            <input type="number" class="xp-value" data-key="task_complete" value="<?php echo $config['xp_values']['task_complete']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                        <div class="xp-value-item">
                            <label>Ukończenie zadania cyklicznego:</label>
                            <input type="number" class="xp-value" data-key="cyclic_task_complete" value="<?php echo $config['xp_values']['cyclic_task_complete']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                        <div class="xp-value-item">
                            <label>Bonus za kategorię celu:</label>
                            <input type="number" class="xp-value" data-key="goal_category_bonus" value="<?php echo $config['xp_values']['goal_category_bonus']; ?>" min="0">
                            <span class="xp-hint">XP (dodatkowo)</span>
                        </div>
                        <div class="xp-value-item">
                            <label>Wszystkie zadania dnia:</label>
                            <input type="number" class="xp-value" data-key="all_tasks_day" value="<?php echo $config['xp_values']['all_tasks_day']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                    </div>

                    <!-- Cele -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-top: 0; color: #28a745;">🎯 Cele</h4>
                        <div class="xp-value-item">
                            <label>Osiągnięcie celu (bazowe):</label>
                            <input type="number" class="xp-value" data-key="goal_achieved_base" value="<?php echo $config['xp_values']['goal_achieved_base']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                        <div class="xp-value-item">
                            <label>Bonus za chain (każdy kolejny):</label>
                            <input type="number" class="xp-value" data-key="goal_chain_increment" value="<?php echo $config['xp_values']['goal_chain_increment']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                        <div class="xp-value-item">
                            <label>Wszystkie cele okresu:</label>
                            <input type="number" class="xp-value" data-key="all_goals_period" value="<?php echo $config['xp_values']['all_goals_period']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                    </div>

                    <!-- Bonusy czasowe -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-top: 0; color: #ffc107;">⏰ Bonusy czasowe</h4>
                        <div class="xp-value-item">
                            <label>Wczesny start (przed 8:00):</label>
                            <input type="number" class="xp-value" data-key="early_start_bonus" value="<?php echo $config['xp_values']['early_start_bonus']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                        <div class="xp-value-item">
                            <label>Wczesne planowanie (przed 10:00):</label>
                            <input type="number" class="xp-value" data-key="early_planning_bonus" value="<?php echo $config['xp_values']['early_planning_bonus']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                        <div class="xp-value-item">
                            <label>Za każdą godzinę w kategorii:</label>
                            <input type="number" class="xp-value" data-key="category_hour" value="<?php echo $config['xp_values']['category_hour']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                    </div>

                    <!-- Pokrycie kategorii -->
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px;">
                        <h4 style="margin-top: 0; color: #17a2b8;">📁 Pokrycie kategorii</h4>
                        <div class="xp-value-item">
                            <label>3 kategorie w dniu:</label>
                            <input type="number" class="xp-value" data-key="coverage_3_categories" value="<?php echo $config['xp_values']['coverage_3_categories']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                        <div class="xp-value-item">
                            <label>4 kategorie w dniu:</label>
                            <input type="number" class="xp-value" data-key="coverage_4_categories" value="<?php echo $config['xp_values']['coverage_4_categories']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                        <div class="xp-value-item">
                            <label>5 kategorii w dniu:</label>
                            <input type="number" class="xp-value" data-key="coverage_5_categories" value="<?php echo $config['xp_values']['coverage_5_categories']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                        <div class="xp-value-item">
                            <label>6 kategorii w dniu:</label>
                            <input type="number" class="xp-value" data-key="coverage_6_categories" value="<?php echo $config['xp_values']['coverage_6_categories']; ?>" min="0">
                            <span class="xp-hint">XP</span>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button class="button button-primary" onclick="saveXPValues()">💾 Zapisz wartości XP</button>
                    <button class="button" onclick="resetXPValues()" style="margin-left: 10px;">🔄 Przywróć domyślne</button>
                    <span id="xp-values-save-status" style="margin-left: 15px; color: #28a745;"></span>
                </div>
            </div>
        </div>

        <!-- Multipliers Tab -->
        <div class="gam-tab-content" id="tab-multipliers">
            <div class="zadaniomat-card">
                <h2>✖️ Mnożniki</h2>
                <p style="color: #666; margin-bottom: 15px;">Ustaw mnożniki XP za streak i combo.</p>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">
                    <!-- Streak Multipliers -->
                    <div style="background: #fff3cd; padding: 20px; border-radius: 8px;">
                        <h3 style="margin-top: 0;">🔥 Mnożniki Streak</h3>
                        <p style="font-size: 12px; color: #856404;">Mnożnik zależy od długości aktualnego streaka (dni roboczych z rzędu).</p>

                        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                            <thead>
                                <tr style="background: rgba(0,0,0,0.1);">
                                    <th style="padding: 8px; text-align: left;">Min. dni</th>
                                    <th style="padding: 8px; text-align: left;">Mnożnik</th>
                                </tr>
                            </thead>
                            <tbody id="streak-multipliers-body">
                                <?php foreach ($config['streak_multipliers'] as $idx => $mult): ?>
                                <tr data-idx="<?php echo $idx; ?>">
                                    <td style="padding: 8px;">
                                        <input type="number" class="streak-mult-days" value="<?php echo $mult['min_days']; ?>" min="0" style="width: 60px; padding: 4px;">
                                    </td>
                                    <td style="padding: 8px;">
                                        <input type="number" class="streak-mult-value" value="<?php echo $mult['multiplier']; ?>" min="1" step="0.1" style="width: 60px; padding: 4px;"> x
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Combo Multipliers -->
                    <div style="background: #d4edda; padding: 20px; border-radius: 8px;">
                        <h3 style="margin-top: 0;">⚡ Mnożniki Combo</h3>
                        <p style="font-size: 12px; color: #155724;">Mnożnik za zadania ukończone bez długiej przerwy (domyślnie 2h).</p>

                        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
                            <thead>
                                <tr style="background: rgba(0,0,0,0.1);">
                                    <th style="padding: 8px; text-align: left;">Min. combo</th>
                                    <th style="padding: 8px; text-align: left;">Mnożnik</th>
                                </tr>
                            </thead>
                            <tbody id="combo-multipliers-body">
                                <?php foreach ($config['combo_multipliers'] as $idx => $mult): ?>
                                <tr data-idx="<?php echo $idx; ?>">
                                    <td style="padding: 8px;">
                                        <input type="number" class="combo-mult-count" value="<?php echo $mult['min_combo']; ?>" min="0" style="width: 60px; padding: 4px;">
                                    </td>
                                    <td style="padding: 8px;">
                                        <input type="number" class="combo-mult-value" value="<?php echo $mult['multiplier']; ?>" min="1" step="0.1" style="width: 60px; padding: 4px;"> x
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button class="button button-primary" onclick="saveMultipliers()">💾 Zapisz mnożniki</button>
                    <button class="button" onclick="resetMultipliers()" style="margin-left: 10px;">🔄 Przywróć domyślne</button>
                    <span id="multipliers-save-status" style="margin-left: 15px; color: #28a745;"></span>
                </div>
            </div>
        </div>

        <!-- Challenges Tab -->
        <div class="gam-tab-content" id="tab-challenges">
            <div class="zadaniomat-card">
                <h2>🎯 Wyzwania dnia</h2>
                <p style="color: #666; margin-bottom: 15px;">Konfiguruj dostępne wyzwania, ich warunki i nagrody XP. Te wyzwania będą losowane każdego dnia.</p>

                <table class="challenges-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 10px; border: 1px solid #ddd;">Klucz</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Opis (widoczny)</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">XP</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">Trudność</th>
                            <th style="padding: 10px; border: 1px solid #ddd;">ℹ️ Warunek (tooltip)</th>
                        </tr>
                    </thead>
                    <tbody id="challenges-body">
                        <?php foreach ($challenges as $key => $ch): ?>
                        <tr data-key="<?php echo esc_attr($key); ?>">
                            <td style="padding: 8px; border: 1px solid #ddd; font-family: monospace; font-size: 11px;"><?php echo esc_html($key); ?></td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <input type="text" class="challenge-desc" value="<?php echo esc_attr($ch['desc']); ?>" style="width: 100%; padding: 4px;">
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <input type="number" class="challenge-xp" value="<?php echo $ch['xp']; ?>" min="0" style="width: 60px; padding: 4px;">
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <select class="challenge-difficulty" style="padding: 4px;">
                                    <option value="easy" <?php echo ($ch['difficulty'] ?? 'easy') === 'easy' ? 'selected' : ''; ?>>🟢 Łatwy</option>
                                    <option value="medium" <?php echo ($ch['difficulty'] ?? 'easy') === 'medium' ? 'selected' : ''; ?>>🟡 Średni</option>
                                    <option value="hard" <?php echo ($ch['difficulty'] ?? 'easy') === 'hard' ? 'selected' : ''; ?>>🔴 Trudny</option>
                                </select>
                            </td>
                            <td style="padding: 8px; border: 1px solid #ddd;">
                                <input type="text" class="challenge-condition" value="<?php echo esc_attr($ch['condition'] ?? ''); ?>" style="width: 100%; padding: 4px;" placeholder="Warunek wyświetlany w tooltip...">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 20px;">
                    <button class="button button-primary" onclick="saveChallenges()">💾 Zapisz wyzwania</button>
                    <button class="button" onclick="resetChallenges()" style="margin-left: 10px;">🔄 Przywróć domyślne</button>
                    <span id="challenges-save-status" style="margin-left: 15px; color: #28a745;"></span>
                </div>
            </div>
        </div>

        <!-- Achievements Tab -->
        <div class="gam-tab-content" id="tab-achievements">
            <div class="zadaniomat-card">
                <h2>🏆 Osiągnięcia / Odznaki</h2>
                <p style="color: #666; margin-bottom: 15px;">Konfiguruj dostępne osiągnięcia, ich warunki i nagrody XP.</p>

                <div style="margin-bottom: 15px;">
                    <input type="text" id="achievements-search" placeholder="🔍 Szukaj osiągnięcia..." style="padding: 8px 12px; width: 300px; border: 1px solid #ddd; border-radius: 4px;">
                </div>

                <table class="achievements-table" style="width: 100%; border-collapse: collapse; font-size: 13px;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 8px; border: 1px solid #ddd;">Ikona</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">Nazwa</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">Opis</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">XP</th>
                            <th style="padding: 8px; border: 1px solid #ddd;">ℹ️ Warunek (tooltip)</th>
                        </tr>
                    </thead>
                    <tbody id="achievements-body">
                        <?php foreach ($achievements as $key => $ach): ?>
                        <tr data-key="<?php echo esc_attr($key); ?>">
                            <td style="padding: 6px; border: 1px solid #ddd; text-align: center;">
                                <input type="text" class="ach-icon" value="<?php echo esc_attr($ach['icon']); ?>" style="width: 40px; padding: 4px; text-align: center; font-size: 16px;">
                            </td>
                            <td style="padding: 6px; border: 1px solid #ddd;">
                                <input type="text" class="ach-name" value="<?php echo esc_attr($ach['name']); ?>" style="width: 100%; padding: 4px;">
                            </td>
                            <td style="padding: 6px; border: 1px solid #ddd;">
                                <input type="text" class="ach-desc" value="<?php echo esc_attr($ach['desc']); ?>" style="width: 100%; padding: 4px;">
                            </td>
                            <td style="padding: 6px; border: 1px solid #ddd;">
                                <input type="number" class="ach-xp" value="<?php echo $ach['xp']; ?>" min="0" style="width: 50px; padding: 4px;">
                            </td>
                            <td style="padding: 6px; border: 1px solid #ddd;">
                                <input type="text" class="ach-condition" value="<?php echo esc_attr($ach['condition'] ?? ''); ?>" style="width: 100%; padding: 4px;" placeholder="Warunek...">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div style="margin-top: 20px;">
                    <button class="button button-primary" onclick="saveAchievements()">💾 Zapisz osiągnięcia</button>
                    <button class="button" onclick="resetAchievements()" style="margin-left: 10px;">🔄 Przywróć domyślne</button>
                    <span id="achievements-save-status" style="margin-left: 15px; color: #28a745;"></span>
                </div>
            </div>
        </div>

        <!-- Abstract Goals Tab -->
        <div class="gam-tab-content" id="tab-abstract-goals">
            <div class="zadaniomat-card">
                <h2>🎯 Cele abstrakcyjne</h2>
                <p style="color: #666; margin-bottom: 15px;">
                    Twórz własne cele z niestandardową nagrodą XP. Te cele możesz później oznaczyć jako ukończone w głównym dashboardzie.
                </p>

                <!-- Form to add new abstract goal -->
                <div style="background: #e8f5e9; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="margin-top: 0;">➕ Dodaj nowy cel abstrakcyjny</h4>
                    <div style="display: grid; grid-template-columns: 1fr 2fr 100px 120px; gap: 10px; align-items: end;">
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Nazwa celu:</label>
                            <input type="text" id="abstract-goal-name" placeholder="np. Napisać raport" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">Opis (opcjonalnie):</label>
                            <input type="text" id="abstract-goal-desc" placeholder="Dodatkowy opis..." style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 12px; font-weight: 600; margin-bottom: 5px;">XP:</label>
                            <input type="number" id="abstract-goal-xp" value="100" min="1" max="1000" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <button class="button button-primary" onclick="addAbstractGoal()" style="width: 100%; height: 36px;">➕ Dodaj</button>
                        </div>
                    </div>
                </div>

                <!-- List of abstract goals -->
                <h4>📋 Twoje cele abstrakcyjne</h4>
                <table class="abstract-goals-table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa;">
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Nazwa</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: left;">Opis</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: center; width: 80px;">XP</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: center; width: 100px;">Status</th>
                            <th style="padding: 10px; border: 1px solid #ddd; text-align: center; width: 120px;">Akcje</th>
                        </tr>
                    </thead>
                    <tbody id="abstract-goals-body">
                        <tr><td colspan="5" style="text-align: center; padding: 20px; color: #888;">Ładowanie...</td></tr>
                    </tbody>
                </table>

                <div style="margin-top: 15px; color: #666; font-size: 12px;">
                    <p><strong>Jak to działa:</strong></p>
                    <ul style="margin: 5px 0 0 20px;">
                        <li>Dodaj cel abstrakcyjny z własną nazwą i wartością XP</li>
                        <li>Cel pojawi się w głównym dashboardzie w sekcji "Cele abstrakcyjne"</li>
                        <li>Kliknij "Wykonano" w dashboardzie aby odebrać XP</li>
                        <li>Ukończone cele są oznaczone jako zrealizowane</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Other Settings Tab -->
        <div class="gam-tab-content" id="tab-settings">
            <div class="zadaniomat-card">
                <h2>⚙️ Inne ustawienia gamifikacji</h2>

                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px;">
                    <!-- Time Settings -->
                    <div style="background: #e3f2fd; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-top: 0;">⏰ Ustawienia czasowe</h4>

                        <div class="setting-item">
                            <label>Wczesny start przed:</label>
                            <input type="time" id="time-early-start" value="<?php echo $config['time_settings']['early_start_before']; ?>">
                        </div>
                        <div class="setting-item">
                            <label>Wczesne planowanie przed:</label>
                            <input type="time" id="time-early-planning" value="<?php echo $config['time_settings']['early_planning_before']; ?>">
                        </div>
                        <div class="setting-item">
                            <label>Timeout combo (godziny):</label>
                            <input type="number" id="time-combo-timeout" value="<?php echo $config['time_settings']['combo_timeout_hours']; ?>" min="1" max="24">
                        </div>
                        <div class="setting-item">
                            <label>Poranna praca - godziny:</label>
                            <input type="number" id="time-morning-hours" value="<?php echo $config['time_settings']['morning_work_hours']; ?>" min="1" max="12">
                        </div>
                        <div class="setting-item">
                            <label>Poranna praca - deadline:</label>
                            <input type="time" id="time-morning-deadline" value="<?php echo $config['time_settings']['morning_work_deadline']; ?>">
                        </div>
                    </div>

                    <!-- Streak Conditions -->
                    <div style="background: #fff3cd; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-top: 0;">🔥 Warunki streaka</h4>
                        <p style="font-size: 12px; color: #856404;">Dzień jest zaliczony do streaka jeśli:</p>

                        <div class="setting-item">
                            <label>Min. zadań:</label>
                            <input type="number" id="streak-min-tasks" value="<?php echo $config['streak_conditions']['min_tasks']; ?>" min="1">
                        </div>
                        <div class="setting-item">
                            <label>Min. godzin:</label>
                            <input type="number" id="streak-min-hours" value="<?php echo $config['streak_conditions']['min_hours']; ?>" min="1">
                        </div>
                        <div class="setting-item">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="streak-or-all" <?php echo $config['streak_conditions']['or_all_tasks_done'] ? 'checked' : ''; ?>>
                                <span>Lub: wszystkie zadania dnia ukończone</span>
                            </label>
                        </div>
                    </div>

                    <!-- XP Confirmation -->
                    <div style="background: #f8d7da; padding: 20px; border-radius: 8px;">
                        <h4 style="margin-top: 0;">✅ Potwierdzenie XP</h4>
                        <p style="font-size: 12px; color: #721c24;">Czy wymagać potwierdzenia przed przyznaniem XP?</p>

                        <div class="setting-item">
                            <label style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" id="require-xp-confirmation" <?php echo $config['require_xp_confirmation'] ? 'checked' : ''; ?>>
                                <span>Wymagaj potwierdzenia XP</span>
                            </label>
                            <p style="font-size: 11px; color: #999; margin-top: 5px;">
                                Jeśli włączone, przed przyznaniem XP pojawi się popup z pytaniem o potwierdzenie.
                            </p>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <button class="button button-primary" onclick="saveOtherSettings()">💾 Zapisz ustawienia</button>
                    <button class="button" onclick="resetOtherSettings()" style="margin-left: 10px;">🔄 Przywróć domyślne</button>
                    <span id="other-save-status" style="margin-left: 15px; color: #28a745;"></span>
                </div>

                <!-- Ultimate Reset Section -->
                <div style="margin-top: 30px; padding: 20px; background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); border-radius: 8px; color: white;">
                    <h3 style="margin-top: 0; color: white;">💀 ULTIMATE RESET</h3>
                    <p style="margin-bottom: 15px; opacity: 0.9;">
                        Ta opcja całkowicie wyzeruje wszystkie dane gamifikacji:<br>
                        • Punkty XP → 0<br>
                        • Poziom → 1<br>
                        • Wszystkie osiągnięcia/odznaki<br>
                        • Wszystkie streaki i combo<br>
                        • Historia XP<br>
                        • Wyzwania dnia
                    </p>
                    <p style="font-weight: bold; margin-bottom: 15px;">⚠️ Tej operacji NIE MOŻNA cofnąć!</p>
                    <button class="button" style="background: white; color: #dc3545; border: none; font-weight: bold;" onclick="ultimateReset()">
                        🔥 Wyzeruj całą gamifikację
                    </button>
                </div>
            </div>
        </div>
    </div>

    <style>
        .gam-settings-tabs {
            display: flex;
            gap: 5px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .gam-tab {
            padding: 10px 15px;
            background: #f0f0f1;
            border: 1px solid #c3c4c7;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            font-size: 13px;
        }
        .gam-tab:hover {
            background: #e5e5e5;
        }
        .gam-tab.active {
            background: #fff;
            border-bottom-color: #fff;
            font-weight: bold;
        }
        .gam-tab-content {
            display: none;
        }
        .gam-tab-content.active {
            display: block;
        }
        .xp-value-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }
        .xp-value-item label {
            flex: 1;
            font-size: 13px;
        }
        .xp-value-item input {
            width: 70px;
            padding: 6px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: right;
        }
        .xp-hint {
            font-size: 11px;
            color: #666;
            width: 80px;
        }
        .setting-item {
            margin-bottom: 15px;
        }
        .setting-item label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
        }
        .setting-item input[type="time"],
        .setting-item input[type="number"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: 120px;
        }
        .xp-history-table tr:hover {
            background: #f8f9fa;
        }
        .btn-delete-xp {
            background: #dc3545;
            color: #fff;
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 11px;
        }
        .btn-delete-xp:hover {
            background: #c82333;
        }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var nonce = '<?php echo wp_create_nonce('zadaniomat_ajax'); ?>';
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var currentPage = 1;
        var perPage = 50;

        // Tab switching
        $('.gam-tab').on('click', function() {
            var tabId = $(this).data('tab');
            $('.gam-tab').removeClass('active');
            $(this).addClass('active');
            $('.gam-tab-content').removeClass('active');
            $('#tab-' + tabId).addClass('active');

            if (tabId === 'xp-history') {
                loadXPHistory();
            }
        });

        // Load XP History
        window.loadXPHistory = function(reset) {
            if (reset) currentPage = 1;
            var filter = $('#xp-history-filter').val();
            var date = $('#xp-history-date').val();

            $('#xp-history-body').html('<tr><td colspan="8" style="text-align:center;padding:20px;">Ładowanie...</td></tr>');

            $.post(ajaxurl, {
                action: 'zadaniomat_get_xp_history_advanced',
                nonce: nonce,
                page: currentPage,
                per_page: perPage,
                filter_type: filter,
                filter_date: date
            }, function(response) {
                if (response.success) {
                    renderXPHistory(response.data);
                } else {
                    $('#xp-history-body').html('<tr><td colspan="8" style="text-align:center;padding:20px;color:#dc3545;">Błąd: ' + (response.data || 'Nieznany błąd') + '</td></tr>');
                }
            }).fail(function(xhr, status, error) {
                $('#xp-history-body').html('<tr><td colspan="8" style="text-align:center;padding:20px;color:#dc3545;">Błąd połączenia: ' + error + '</td></tr>');
            });
        };

        function renderXPHistory(data) {
            var html = '';
            if (data.entries.length === 0) {
                html = '<tr><td colspan="8" style="text-align:center;padding:20px;color:#666;">Brak wpisów do wyświetlenia</td></tr>';
            } else {
                data.entries.forEach(function(entry) {
                    var dateStr = new Date(entry.earned_at).toLocaleString('pl-PL');
                    html += '<tr data-id="' + entry.id + '">';
                    html += '<td style="padding:8px;border:1px solid #ddd;">' + dateStr + '</td>';
                    html += '<td style="padding:8px;border:1px solid #ddd;"><span class="xp-type-badge type-' + entry.xp_type + '">' + escapeHtml(entry.xp_type) + '</span></td>';
                    html += '<td style="padding:8px;border:1px solid #ddd;">' + escapeHtml(entry.description || '-') + '</td>';
                    html += '<td style="padding:8px;border:1px solid #ddd;font-size:11px;color:#666;">' + escapeHtml(entry.condition_text || '-') + '</td>';
                    html += '<td style="padding:8px;border:1px solid #ddd;text-align:right;">' + entry.xp_amount + '</td>';
                    html += '<td style="padding:8px;border:1px solid #ddd;text-align:center;">' + (parseFloat(entry.multiplier) || 1).toFixed(1) + 'x</td>';
                    html += '<td style="padding:8px;border:1px solid #ddd;text-align:right;font-weight:bold;color:#28a745;">+' + entry.xp_final + '</td>';
                    html += '<td style="padding:8px;border:1px solid #ddd;text-align:center;"><button class="btn-delete-xp" onclick="deleteXPEntry(' + entry.id + ')">🗑️</button></td>';
                    html += '</tr>';
                });
            }
            $('#xp-history-body').html(html);

            // Summary
            $('#xp-history-summary').html('Łącznie: <span style="color:#28a745;">+' + data.total_xp + ' XP</span> (wpisów: ' + data.total_count + ')');

            // Pagination
            var totalPages = Math.ceil(data.total_count / perPage);
            var pagHtml = '';
            if (totalPages > 1) {
                for (var i = 1; i <= totalPages; i++) {
                    pagHtml += '<button class="button ' + (i === currentPage ? 'button-primary' : '') + '" onclick="goToXPPage(' + i + ')">' + i + '</button> ';
                }
            }
            $('#xp-history-pagination').html(pagHtml);
        }

        window.goToXPPage = function(page) {
            currentPage = page;
            loadXPHistory();
        };

        window.deleteXPEntry = function(id) {
            if (!confirm('Na pewno usunąć ten wpis XP? Punkty zostaną odjęte od konta.')) return;

            $.post(ajaxurl, {
                action: 'zadaniomat_delete_xp_entry',
                nonce: nonce,
                entry_id: id
            }, function(response) {
                if (response.success) {
                    showToast('Wpis XP usunięty, punkty odjęte', 'success');
                    loadXPHistory();
                } else {
                    showToast('Błąd: ' + (response.data || 'Nieznany błąd'), 'error');
                }
            });
        };

        // Save Levels
        window.saveLevelsConfig = function() {
            var levels = {};
            $('.level-xp').each(function() {
                var lvl = $(this).data('level');
                levels[lvl] = {
                    xp: parseInt($(this).val()) || 0,
                    name: $('.level-name[data-level="' + lvl + '"]').val(),
                    icon: $('.level-icon[data-level="' + lvl + '"]').val()
                };
            });

            $.post(ajaxurl, {
                action: 'zadaniomat_save_gam_config',
                nonce: nonce,
                config_key: 'levels',
                config_value: JSON.stringify(levels)
            }, function(response) {
                if (response.success) {
                    $('#levels-save-status').text('✅ Zapisano!').fadeIn().delay(2000).fadeOut();
                }
            });
        };

        window.resetLevelsConfig = function() {
            if (!confirm('Przywrócić domyślne poziomy?')) return;
            $.post(ajaxurl, {
                action: 'zadaniomat_reset_gam_config',
                nonce: nonce,
                config_key: 'levels'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        };

        // Save XP Values
        window.saveXPValues = function() {
            var xpValues = {};
            $('.xp-value').each(function() {
                xpValues[$(this).data('key')] = parseInt($(this).val()) || 0;
            });

            $.post(ajaxurl, {
                action: 'zadaniomat_save_gam_config',
                nonce: nonce,
                config_key: 'xp_values',
                config_value: JSON.stringify(xpValues)
            }, function(response) {
                if (response.success) {
                    $('#xp-values-save-status').text('✅ Zapisano!').fadeIn().delay(2000).fadeOut();
                }
            });
        };

        window.resetXPValues = function() {
            if (!confirm('Przywrócić domyślne wartości XP?')) return;
            $.post(ajaxurl, {
                action: 'zadaniomat_reset_gam_config',
                nonce: nonce,
                config_key: 'xp_values'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        };

        // Save Multipliers
        window.saveMultipliers = function() {
            var streakMults = [];
            $('#streak-multipliers-body tr').each(function() {
                streakMults.push({
                    min_days: parseInt($(this).find('.streak-mult-days').val()) || 0,
                    multiplier: parseFloat($(this).find('.streak-mult-value').val()) || 1.0
                });
            });

            var comboMults = [];
            $('#combo-multipliers-body tr').each(function() {
                comboMults.push({
                    min_combo: parseInt($(this).find('.combo-mult-count').val()) || 0,
                    multiplier: parseFloat($(this).find('.combo-mult-value').val()) || 1.0
                });
            });

            $.post(ajaxurl, {
                action: 'zadaniomat_save_gam_config',
                nonce: nonce,
                config_key: 'multipliers',
                streak_multipliers: JSON.stringify(streakMults),
                combo_multipliers: JSON.stringify(comboMults)
            }, function(response) {
                if (response.success) {
                    $('#multipliers-save-status').text('✅ Zapisano!').fadeIn().delay(2000).fadeOut();
                }
            });
        };

        window.resetMultipliers = function() {
            if (!confirm('Przywrócić domyślne mnożniki?')) return;
            $.post(ajaxurl, {
                action: 'zadaniomat_reset_gam_config',
                nonce: nonce,
                config_key: 'multipliers'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        };

        // Save Challenges
        window.saveChallenges = function() {
            var challenges = {};
            $('#challenges-body tr').each(function() {
                var key = $(this).data('key');
                challenges[key] = {
                    desc: $(this).find('.challenge-desc').val(),
                    xp: parseInt($(this).find('.challenge-xp').val()) || 0,
                    difficulty: $(this).find('.challenge-difficulty').val(),
                    condition: $(this).find('.challenge-condition').val()
                };
            });

            $.post(ajaxurl, {
                action: 'zadaniomat_save_challenges_config',
                nonce: nonce,
                challenges: JSON.stringify(challenges)
            }, function(response) {
                if (response.success) {
                    $('#challenges-save-status').text('✅ Zapisano!').fadeIn().delay(2000).fadeOut();
                }
            });
        };

        window.resetChallenges = function() {
            if (!confirm('Przywrócić domyślne wyzwania?')) return;
            $.post(ajaxurl, {
                action: 'zadaniomat_reset_challenges_config',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        };

        // Save Achievements
        window.saveAchievements = function() {
            var achievements = {};
            $('#achievements-body tr').each(function() {
                var key = $(this).data('key');
                achievements[key] = {
                    icon: $(this).find('.ach-icon').val(),
                    name: $(this).find('.ach-name').val(),
                    desc: $(this).find('.ach-desc').val(),
                    xp: parseInt($(this).find('.ach-xp').val()) || 0,
                    condition: $(this).find('.ach-condition').val()
                };
            });

            $.post(ajaxurl, {
                action: 'zadaniomat_save_achievements_config',
                nonce: nonce,
                achievements: JSON.stringify(achievements)
            }, function(response) {
                if (response.success) {
                    $('#achievements-save-status').text('✅ Zapisano!').fadeIn().delay(2000).fadeOut();
                }
            });
        };

        window.resetAchievements = function() {
            if (!confirm('Przywrócić domyślne osiągnięcia?')) return;
            $.post(ajaxurl, {
                action: 'zadaniomat_reset_achievements_config',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        };

        // Save Other Settings
        window.saveOtherSettings = function() {
            var timeSettings = {
                early_start_before: $('#time-early-start').val(),
                early_planning_before: $('#time-early-planning').val(),
                combo_timeout_hours: parseInt($('#time-combo-timeout').val()) || 2,
                morning_work_hours: parseInt($('#time-morning-hours').val()) || 3,
                morning_work_deadline: $('#time-morning-deadline').val()
            };

            var streakConditions = {
                min_tasks: parseInt($('#streak-min-tasks').val()) || 3,
                min_hours: parseInt($('#streak-min-hours').val()) || 4,
                or_all_tasks_done: $('#streak-or-all').is(':checked')
            };

            var requireConfirmation = $('#require-xp-confirmation').is(':checked');

            $.post(ajaxurl, {
                action: 'zadaniomat_save_gam_config',
                nonce: nonce,
                config_key: 'other',
                time_settings: JSON.stringify(timeSettings),
                streak_conditions: JSON.stringify(streakConditions),
                require_xp_confirmation: requireConfirmation ? 1 : 0
            }, function(response) {
                if (response.success) {
                    $('#other-save-status').text('✅ Zapisano!').fadeIn().delay(2000).fadeOut();
                }
            });
        };

        window.resetOtherSettings = function() {
            if (!confirm('Przywrócić domyślne ustawienia?')) return;
            $.post(ajaxurl, {
                action: 'zadaniomat_reset_gam_config',
                nonce: nonce,
                config_key: 'other'
            }, function(response) {
                if (response.success) {
                    location.reload();
                }
            });
        };

        // Ultimate reset - wyzeruj całą gamifikację
        window.ultimateReset = function() {
            var confirmed = prompt('Ta operacja USUNIE WSZYSTKIE dane gamifikacji!\n\nWpisz "RESET" aby potwierdzić:');
            if (confirmed !== 'RESET') {
                if (confirmed !== null) {
                    alert('Anulowano. Wpisz dokładnie "RESET" aby potwierdzić.');
                }
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_ultimate_reset',
                nonce: nonce
            }, function(response) {
                if (response.success) {
                    alert('✅ Gamifikacja została całkowicie zresetowana!\n\nStrona zostanie odświeżona.');
                    location.reload();
                } else {
                    alert('❌ Błąd podczas resetowania: ' + (response.data || 'Nieznany błąd'));
                }
            });
        };

        // =============================================
        // ABSTRACT GOALS MANAGEMENT
        // =============================================

        window.loadSettingsAbstractGoals = function() {
            $.post(ajaxurl, {
                action: 'zadaniomat_get_abstract_goals',
                nonce: nonce,
                include_completed: 'true'
            }, function(response) {
                if (response.success) {
                    renderSettingsAbstractGoals(response.data.goals || []);
                } else {
                    console.error('Error loading abstract goals:', response);
                    $('#abstract-goals-body').html('<tr><td colspan="5" style="text-align:center;padding:20px;color:#dc3545;">Błąd ładowania celów: ' + (response.data || 'Nieznany błąd') + '</td></tr>');
                }
            }).fail(function(xhr, status, error) {
                console.error('AJAX failed:', status, error);
                $('#abstract-goals-body').html('<tr><td colspan="5" style="text-align:center;padding:20px;color:#dc3545;">Błąd połączenia: ' + error + '</td></tr>');
            });
        };

        function renderSettingsAbstractGoals(goals) {
            var html = '';
            if (goals.length === 0) {
                html = '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #888;">Brak celów abstrakcyjnych. Dodaj pierwszy cel powyżej.</td></tr>';
            } else {
                goals.forEach(function(g) {
                    var isCompleted = g.completed == 1;
                    var statusBadge = isCompleted
                        ? '<span style="background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 4px; font-size: 11px;">✓ Ukończony</span>'
                        : '<span style="background: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 4px; font-size: 11px;">Aktywny</span>';

                    html += '<tr data-goal-id="' + g.id + '"' + (isCompleted ? ' style="opacity: 0.6;"' : '') + '>';
                    html += '<td style="padding: 10px; border: 1px solid #ddd;">';
                    html += '<input type="text" class="goal-edit-name" value="' + escapeHtml(g.nazwa) + '" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"' + (isCompleted ? ' disabled' : '') + '>';
                    html += '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #ddd;">';
                    html += '<input type="text" class="goal-edit-desc" value="' + escapeHtml(g.opis || '') + '" placeholder="-" style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;"' + (isCompleted ? ' disabled' : '') + '>';
                    html += '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">';
                    html += '<input type="number" class="goal-edit-xp" value="' + g.xp_reward + '" min="1" max="1000" style="width: 60px; padding: 5px; border: 1px solid #ddd; border-radius: 4px; text-align: center;"' + (isCompleted ? ' disabled' : '') + '>';
                    html += '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">' + statusBadge + '</td>';
                    html += '<td style="padding: 10px; border: 1px solid #ddd; text-align: center;">';
                    if (!isCompleted) {
                        html += '<button class="button" onclick="saveAbstractGoalEdit(' + g.id + ', this)" style="margin-right: 5px;" title="Zapisz zmiany">💾</button>';
                    }
                    html += '<button class="button" onclick="deleteAbstractGoalFromSettings(' + g.id + ')" style="color: #dc3545;" title="Usuń cel">🗑️</button>';
                    html += '</td>';
                    html += '</tr>';
                });
            }
            $('#abstract-goals-body').html(html);
        }

        window.addAbstractGoal = function() {
            var nazwa = $('#abstract-goal-name').val().trim();
            var opis = $('#abstract-goal-desc').val().trim();
            var xp = parseInt($('#abstract-goal-xp').val()) || 100;

            if (!nazwa) {
                alert('Podaj nazwę celu!');
                $('#abstract-goal-name').focus();
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_add_abstract_goal',
                nonce: nonce,
                nazwa: nazwa,
                opis: opis,
                xp_reward: xp
            }, function(response) {
                if (response.success) {
                    showToast('Cel abstrakcyjny dodany!', 'success');
                    $('#abstract-goal-name').val('');
                    $('#abstract-goal-desc').val('');
                    $('#abstract-goal-xp').val('100');
                    loadSettingsAbstractGoals();
                } else {
                    alert('Błąd: ' + (response.data || 'Nieznany błąd'));
                }
            });
        };

        window.saveAbstractGoalEdit = function(id, btn) {
            var $row = $(btn).closest('tr');
            var nazwa = $row.find('.goal-edit-name').val().trim();
            var opis = $row.find('.goal-edit-desc').val().trim();
            var xp = parseInt($row.find('.goal-edit-xp').val()) || 100;

            if (!nazwa) {
                alert('Podaj nazwę celu!');
                return;
            }

            $.post(ajaxurl, {
                action: 'zadaniomat_edit_abstract_goal',
                nonce: nonce,
                id: id,
                nazwa: nazwa,
                opis: opis,
                xp_reward: xp
            }, function(response) {
                if (response.success) {
                    showToast('Cel zaktualizowany!', 'success');
                    loadSettingsAbstractGoals();
                } else {
                    alert('Błąd: ' + (response.data || 'Nieznany błąd'));
                }
            });
        };

        window.deleteAbstractGoalFromSettings = function(id) {
            if (!confirm('Czy na pewno chcesz usunąć ten cel abstrakcyjny?')) return;

            $.post(ajaxurl, {
                action: 'zadaniomat_delete_abstract_goal',
                nonce: nonce,
                id: id
            }, function(response) {
                if (response.success) {
                    showToast('Cel usunięty!', 'success');
                    loadSettingsAbstractGoals();
                } else {
                    alert('Błąd: ' + (response.data || 'Nieznany błąd'));
                }
            });
        };

        // Search achievements
        $('#achievements-search').on('input', function() {
            var query = $(this).val().toLowerCase();
            $('#achievements-body tr').each(function() {
                var text = $(this).text().toLowerCase();
                $(this).toggle(text.indexOf(query) >= 0);
            });
        });

        // Toast helper
        window.showToast = function(message, type) {
            var toast = $('<div style="position:fixed;bottom:20px;right:20px;background:' + (type === 'success' ? '#28a745' : '#dc3545') + ';color:#fff;padding:12px 20px;border-radius:8px;z-index:9999;">' + message + '</div>');
            $('body').append(toast);
            setTimeout(function() { toast.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        };

        // Escape HTML
        window.escapeHtml = function(text) {
            if (!text) return '';
            return text.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        };

        // Initial load
        loadXPHistory();
        loadSettingsAbstractGoals();
    });
    </script>
    <?php
}

// =============================================
// PUBLICZNA STRONA - SHORTCODE
// =============================================
add_shortcode('zadaniomat_public', 'zadaniomat_public_page');

function zadaniomat_public_page($atts) {
    ob_start();
    ?>
    <style>
        .zadaniomat-public {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        .zadaniomat-public h2 { margin-top: 30px; color: #333; border-bottom: 2px solid #007bff; padding-bottom: 10px; }
        .zadaniomat-public .filters { background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px; display: flex; gap: 15px; flex-wrap: wrap; align-items: center; }
        .zadaniomat-public .filters select, .zadaniomat-public .filters input { padding: 10px 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; }
        .zadaniomat-public .filters select { min-width: 200px; }
        .zadaniomat-public .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .zadaniomat-public .stat-card { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); text-align: center; }
        .zadaniomat-public .stat-card .value { font-size: 32px; font-weight: bold; color: #007bff; }
        .zadaniomat-public .stat-card .label { font-size: 12px; color: #666; text-transform: uppercase; margin-top: 5px; }
        .zadaniomat-public .progress-bar { background: #e9ecef; border-radius: 10px; height: 20px; overflow: hidden; margin: 10px 0; }
        .zadaniomat-public .progress-bar .fill { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s; }
        .zadaniomat-public .goals-section { margin-top: 30px; }
        .zadaniomat-public .goal-card { background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 10px; border-left: 4px solid #007bff; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        .zadaniomat-public .goal-card.osiagniety { border-left-color: #28a745; background: #f0fff4; }
        .zadaniomat-public .goal-card.nie-osiagniety { border-left-color: #dc3545; background: #fff5f5; }
        .zadaniomat-public .goal-card .kategoria { font-size: 11px; color: #666; text-transform: uppercase; margin-bottom: 5px; }
        .zadaniomat-public .goal-card .cel-text { font-size: 14px; color: #333; }
        .zadaniomat-public .goal-card .status-badge { display: inline-block; padding: 3px 8px; border-radius: 12px; font-size: 11px; margin-left: 10px; }
        .zadaniomat-public .goal-card .status-badge.success { background: #d4edda; color: #155724; }
        .zadaniomat-public .goal-card .status-badge.danger { background: #f8d7da; color: #721c24; }
        .zadaniomat-public .goal-card .status-badge.pending { background: #fff3cd; color: #856404; }
        .zadaniomat-public .day-stats { margin-top: 30px; }
        .zadaniomat-public .day-stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }
        .zadaniomat-public .day-stat-card { background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
        .zadaniomat-public .day-stat-card .skrot { font-size: 24px; font-weight: bold; color: #007bff; }
        .zadaniomat-public .day-stat-card .details { font-size: 12px; color: #666; margin-top: 5px; }
        .zadaniomat-public .loading { text-align: center; padding: 40px; color: #666; }
        .zadaniomat-public .okres-section { margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .zadaniomat-public .okres-section h4 { margin: 0 0 15px 0; color: #495057; }
    </style>

    <div class="zadaniomat-public">
        <h1>Zadaniomat - Statystyki</h1>

        <div class="filters">
            <select id="zp-rok-filter">
                <option value="">-- Wybierz rok (90 dni) --</option>
            </select>
            <select id="zp-okres-filter">
                <option value="">-- Wybierz okres (2 tyg) --</option>
            </select>
            <input type="date" id="zp-day-filter" placeholder="Wybierz dzien">
            <button id="zp-load-btn" style="padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 6px; cursor: pointer;">Zaladuj</button>
        </div>

        <div id="zp-stats-container" class="loading">
            Wybierz rok lub okres, aby zaladowac statystyki...
        </div>

        <div id="zp-day-stats-container" style="display: none;">
            <h2>Statystyki dnia</h2>
            <div id="zp-day-stats-content"></div>
        </div>

        <div id="zp-goals-container" style="display: none;">
            <h2>Cele</h2>
            <div id="zp-goals-content"></div>
        </div>
    </div>

    <script>
    (function() {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var data = { roki: [], okresy: [], kategorie: {}, skroty: {} };

        // Zaladuj poczatkowe dane
        fetch(ajaxurl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=zadaniomat_public_get_data'
        })
        .then(function(r) { return r.json(); })
        .then(function(response) {
            if (response.success) {
                data = response.data;
                renderFilters();
            }
        });

        function renderFilters() {
            var rokSelect = document.getElementById('zp-rok-filter');
            var okresSelect = document.getElementById('zp-okres-filter');

            data.roki.forEach(function(rok) {
                var opt = document.createElement('option');
                opt.value = rok.id;
                opt.textContent = rok.nazwa + ' (' + formatDate(rok.data_start) + ' - ' + formatDate(rok.data_koniec) + ')';
                rokSelect.appendChild(opt);
            });

            rokSelect.addEventListener('change', function() {
                okresSelect.innerHTML = '<option value="">-- Wybierz okres (2 tyg) --</option>';
                var rokId = this.value;
                if (rokId) {
                    data.okresy.filter(function(o) { return o.rok_id == rokId; }).forEach(function(okres) {
                        var opt = document.createElement('option');
                        opt.value = okres.id;
                        opt.textContent = okres.nazwa + ' (' + formatDate(okres.data_start) + ' - ' + formatDate(okres.data_koniec) + ')';
                        okresSelect.appendChild(opt);
                    });
                }
            });
        }

        document.getElementById('zp-load-btn').addEventListener('click', function() {
            var rokId = document.getElementById('zp-rok-filter').value;
            var okresId = document.getElementById('zp-okres-filter').value;
            var dayDate = document.getElementById('zp-day-filter').value;

            var filterType = okresId ? 'okres' : (rokId ? 'rok' : '');
            var filterId = okresId || rokId;

            var params = 'action=zadaniomat_public_get_data';
            if (filterType) params += '&filter_type=' + filterType + '&filter_id=' + filterId;
            if (dayDate) params += '&filter_date=' + dayDate;

            document.getElementById('zp-stats-container').innerHTML = '<div class="loading">Ladowanie...</div>';

            fetch(ajaxurl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: params
            })
            .then(function(r) { return r.json(); })
            .then(function(response) {
                if (response.success) {
                    renderStats(response.data);
                    renderDayStats(response.data.day_stats);
                    renderGoals(response.data.cele);
                }
            });
        });

        function renderStats(d) {
            var container = document.getElementById('zp-stats-container');
            if (!d.stats) {
                container.innerHTML = '<div class="loading">Wybierz rok lub okres, aby zobaczyc statystyki.</div>';
                return;
            }

            var s = d.stats;
            var html = '<div class="stats-grid">';
            html += '<div class="stat-card"><div class="value">' + s.progress + '%</div><div class="label">Progres</div><div class="progress-bar"><div class="fill" style="width:' + s.progress + '%"></div></div></div>';
            html += '<div class="stat-card"><div class="value">' + s.liczba_zadan + '</div><div class="label">Liczba zadan</div></div>';
            html += '<div class="stat-card"><div class="value">' + (s.faktyczny_czas / 60).toFixed(1) + 'h</div><div class="label">Czas pracy</div></div>';
            html += '<div class="stat-card"><div class="value">' + (s.avg_start_time || '-') + '</div><div class="label">Srednia godz. startu</div></div>';
            html += '<div class="stat-card"><div class="value">' + s.dni_robocze + '</div><div class="label">Dni robocze</div></div>';
            html += '</div>';

            // Statystyki per kategoria
            if (s.by_kategoria && Object.keys(s.by_kategoria).length > 0) {
                html += '<h3>Statystyki per kategoria</h3><div class="stats-grid">';
                Object.keys(s.by_kategoria).forEach(function(kat) {
                    var ks = s.by_kategoria[kat];
                    var label = d.kategorie[kat] || kat;
                    var skrot = d.skroty[kat] || '';
                    html += '<div class="stat-card">';
                    if (skrot) html += '<div style="font-size:11px;color:#999;">' + skrot + '</div>';
                    html += '<div style="font-size:14px;font-weight:bold;">' + label + '</div>';
                    html += '<div class="value" style="font-size:20px;">' + ks.liczba_zadan + ' zadan</div>';
                    html += '<div class="label">' + (ks.faktyczny_czas / 60).toFixed(1) + 'h</div>';
                    html += '</div>';
                });
                html += '</div>';
            }

            container.innerHTML = html;
        }

        function renderDayStats(dayStats) {
            var container = document.getElementById('zp-day-stats-container');
            var content = document.getElementById('zp-day-stats-content');

            if (!dayStats || !dayStats.by_kategoria || Object.keys(dayStats.by_kategoria).length === 0) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'block';
            var html = '<p><strong>Data:</strong> ' + formatDate(dayStats.date);
            if (dayStats.start_time) html += ' | <strong>Start pracy:</strong> ' + dayStats.start_time;
            html += '</p>';

            html += '<div class="day-stats-grid">';
            Object.keys(dayStats.by_kategoria).forEach(function(kat) {
                var s = dayStats.by_kategoria[kat];
                html += '<div class="day-stat-card">';
                html += '<div class="skrot">' + (s.skrot || kat.substring(0,3).toUpperCase()) + '</div>';
                html += '<div style="font-size:12px;">' + s.kategoria_label + '</div>';
                html += '<div class="details">' + s.liczba_zadan + ' zadan | ' + (s.faktyczny_czas / 60).toFixed(1) + 'h</div>';
                html += '</div>';
            });
            html += '</div>';

            content.innerHTML = html;
        }

        function renderGoals(cele) {
            var container = document.getElementById('zp-goals-container');
            var content = document.getElementById('zp-goals-content');

            if (!cele || (Object.keys(cele).length === 0)) {
                container.style.display = 'none';
                return;
            }

            container.style.display = 'block';
            var html = '';

            // Cele roczne
            if (cele.rok && cele.rok.length > 0) {
                html += '<h3>Cele strategiczne (90 dni)</h3>';
                cele.rok.forEach(function(c) {
                    if (c.cel) {
                        html += '<div class="goal-card">';
                        html += '<div class="kategoria">' + c.kategoria_label + '</div>';
                        html += '<div class="cel-text">' + escapeHtml(c.cel) + '</div>';
                        html += '</div>';
                    }
                });
            }

            // Cele okresowe
            if (cele.okres && cele.okres.length > 0) {
                html += '<h3>Cele okresu (2 tygodnie)</h3>';
                cele.okres.forEach(function(c) {
                    if (c.cel) {
                        var statusClass = c.osiagniety === '1' || c.osiagniety === 1 ? 'osiagniety' : (c.osiagniety === '0' || c.osiagniety === 0 ? 'nie-osiagniety' : '');
                        var badgeClass = c.osiagniety === '1' || c.osiagniety === 1 ? 'success' : (c.osiagniety === '0' || c.osiagniety === 0 ? 'danger' : 'pending');
                        var badgeText = c.osiagniety === '1' || c.osiagniety === 1 ? 'Osiagniety' : (c.osiagniety === '0' || c.osiagniety === 0 ? 'Nie osiagniety' : 'Nieoznaczony');

                        html += '<div class="goal-card ' + statusClass + '">';
                        html += '<div class="kategoria">' + c.kategoria_label;
                        if (c.completed_at) html += ' <span class="status-badge success">Ukonczony</span>';
                        html += '<span class="status-badge ' + badgeClass + '">' + badgeText + '</span></div>';
                        html += '<div class="cel-text">' + escapeHtml(c.cel) + '</div>';
                        html += '</div>';
                    }
                });
            }

            // Cele w okresach (gdy filtrujemy rok)
            if (cele.okresy) {
                Object.keys(cele.okresy).forEach(function(okresId) {
                    var okresData = cele.okresy[okresId];
                    if (okresData.cele && okresData.cele.length > 0) {
                        html += '<div class="okres-section">';
                        html += '<h4>' + okresData.okres.nazwa + ' (' + formatDate(okresData.okres.data_start) + ' - ' + formatDate(okresData.okres.data_koniec) + ')</h4>';
                        okresData.cele.forEach(function(c) {
                            if (c.cel) {
                                var statusClass = c.osiagniety === '1' || c.osiagniety === 1 ? 'osiagniety' : (c.osiagniety === '0' || c.osiagniety === 0 ? 'nie-osiagniety' : '');
                                var badgeClass = c.osiagniety === '1' || c.osiagniety === 1 ? 'success' : (c.osiagniety === '0' || c.osiagniety === 0 ? 'danger' : 'pending');
                                var badgeText = c.osiagniety === '1' || c.osiagniety === 1 ? 'Osiagniety' : (c.osiagniety === '0' || c.osiagniety === 0 ? 'Nie osiagniety' : 'Nieoznaczony');

                                html += '<div class="goal-card ' + statusClass + '">';
                                html += '<div class="kategoria">' + c.kategoria_label;
                                if (c.completed_at) html += ' <span class="status-badge success">x' + (c.pozycja || 1) + '</span>';
                                html += '<span class="status-badge ' + badgeClass + '">' + badgeText + '</span></div>';
                                html += '<div class="cel-text">' + escapeHtml(c.cel) + '</div>';
                                html += '</div>';
                            }
                        });
                        html += '</div>';
                    }
                });
            }

            content.innerHTML = html || '<p>Brak celow do wyswietlenia.</p>';
        }

        function formatDate(dateStr) {
            if (!dateStr) return '';
            var parts = dateStr.split('-');
            return parts[2] + '.' + parts[1] + '.' + parts[0];
        }

        function escapeHtml(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    })();
    </script>
    <?php
    return ob_get_clean();
}
