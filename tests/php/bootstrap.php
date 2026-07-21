<?php

/**
 * Bootstrap testowy dla wtyczki "Cookie Consent Banner".
 *
 * To NIE jest instalacja WordPressa (brak bazy danych, brak wp-env, brak
 * MySQL) — to lekkie stuby funkcji WP, których cookie-consent-banner.php /
 * includes/*.php / templates/banner.php faktycznie używają na etapie
 * ładowania pliku i wykonywania zarejestrowanych hooków, wystarczające żeby
 * wczytać PRAWDZIWY kod wtyczki w gołym PHP CLI i asertować na jego
 * rzeczywistym zachowaniu.
 *
 * add_action()/add_filter() tylko REJESTRUJĄ callbacki (jak w WP), nie
 * wykonują ich automatycznie:
 *  - apply_filters() jest REALNY (odpala zarejestrowane filtry) — dzięki
 *    temu np. ccb_youtube_nocookie() spięty przez add_filter('the_content', ...)
 *    da się przetestować dokładnie tak jak w prawdziwym WP.
 *  - do_action() NIE istnieje w tym bootstrapie. Zamiast tego jest
 *    ccb_test_fire($tag, ...$args), które odpala WSZYSTKIE callbacki
 *    zarejestrowane dla danego taga, w kolejności rejestracji (bez
 *    sortowania po priorytecie — w tej wtyczce nie ma dwóch akcji na tym
 *    samym tagu, których kolejność miałaby wpływ na wynik testowanych
 *    zachowań, patrz komentarze w plikach testowych).
 *
 * WAŻNA PUŁAPKA: ccb_get() (includes/ccb-settings.php) cache'uje opcje w
 * `static $options = null;` wewnątrz funkcji — RAZ odczytane w danym
 * procesie PHP już się nie zmieniają, nawet jeśli update_option() zostanie
 * wywołane ponownie. To PRAWDZIWE zachowanie źródłowego kodu (nie błąd
 * bootstrapa) i pliki testowe są celowo zaprojektowane tak, żeby każdy
 * plik *.php uruchamiany jako osobny proces CLI ustawiał opcje PRZED
 * pierwszym wywołaniem ccb_get() i trzymał się jednego scenariusza opcji
 * przez cały plik. Jeden z testów (test-settings-defaults-and-hooks.php)
 * demonstruje ten cache wprost jako udokumentowane zachowanie.
 */

error_reporting(E_ALL & ~E_DEPRECATED);

define('ABSPATH', __DIR__ . '/');

// Prawdziwa ścieżka do katalogu wtyczki — tests/php/ -> tests/ -> cookie-consent-banner/
define('CCB_TEST_PLUGIN_DIR', dirname(__DIR__, 2) . '/');

// ── "opcje" WordPressa jako zwykła zmienna globalna w pamięci ──────────────
$GLOBALS['__wp_options'] = [];

function get_option(string $key, $default = false)
{
    return $GLOBALS['__wp_options'][$key] ?? $default;
}

function update_option(string $key, $value): bool
{
    $GLOBALS['__wp_options'][$key] = $value;
    return true;
}

// ── hooki: rejestrujemy naprawdę, nie wykonujemy automatycznie ─────────────
$GLOBALS['__wp_actions'] = [];
$GLOBALS['__wp_filters'] = [];

function add_action(string $tag, callable $cb, int $priority = 10, int $accepted_args = 1): void
{
    $GLOBALS['__wp_actions'][$tag][] = $cb;
}

function add_filter(string $tag, callable $cb, int $priority = 10, int $accepted_args = 1): void
{
    $GLOBALS['__wp_filters'][$tag][] = $cb;
}

/**
 * apply_filters() jest realny (odpala callbacki zarejestrowane przez
 * add_filter powyżej), w kolejności rejestracji.
 */
function apply_filters(string $tag, $value, ...$args)
{
    foreach ($GLOBALS['__wp_filters'][$tag] ?? [] as $cb) {
        $value = $cb($value, ...$args);
    }
    return $value;
}

/**
 * Odpala WSZYSTKIE callbacki zarejestrowane dla danego hooka (akcji), w
 * kolejności rejestracji — jak prawdziwe do_action(), ale bez sortowania
 * po priorytecie (patrz komentarz na górze pliku).
 */
function ccb_test_fire(string $tag, ...$args): void
{
    foreach ($GLOBALS['__wp_actions'][$tag] ?? [] as $cb) {
        $cb(...$args);
    }
}

// ── sanitizery / walidatory (uproszczone, ale zgodne z zachowaniem WP) ─────
function sanitize_text_field($str): string
{
    return trim(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string) $str)));
}

function wp_parse_args($args, $defaults = [])
{
    if (is_object($args)) {
        $args = get_object_vars($args);
    } elseif (!is_array($args)) {
        parse_str((string) $args, $args);
    }
    return array_merge($defaults, $args);
}

/**
 * Uproszczona wersja WP wp_kses(): usuwa wszystkie tagi HTML, które nie są
 * na liście dozwolonych (klucze $allowed_html), zostawia dozwolone tagi
 * "as-is" razem z atrybutami (prawdziwy wp_kses() dodatkowo filtrowałby
 * pojedyncze atrybuty — tutaj to uproszczenie, bo żaden test w tym
 * repozytorium nie zależy od filtrowania atrybutów, tylko od tego, które
 * TAGI przechodzą). Tekst poza tagami zawsze zostaje.
 */
function wp_kses($string, $allowed_html)
{
    $string = (string) $string;
    if (!is_array($allowed_html)) {
        $allowed_html = [];
    }
    return preg_replace_callback('/<\/?([a-zA-Z0-9]+)([^>]*)>/', function ($m) use ($allowed_html) {
        $tag = strtolower($m[1]);
        return array_key_exists($tag, $allowed_html) ? $m[0] : '';
    }, $string);
}

// ── i18n / output — passthrough, bez faktycznego tłumaczenia ───────────────
function __(string $text, string $domain = 'default'): string
{
    return $text;
}

function esc_html__(string $text, string $domain = 'default'): string
{
    return htmlspecialchars($text, ENT_QUOTES);
}

function esc_html_e(string $text, string $domain = 'default'): void
{
    echo htmlspecialchars($text, ENT_QUOTES);
}

function esc_attr_e(string $text, string $domain = 'default'): void
{
    echo htmlspecialchars($text, ENT_QUOTES);
}

function esc_html($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function esc_attr($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function esc_textarea($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}

function esc_url($url): string
{
    return htmlspecialchars((string) $url, ENT_QUOTES);
}

function checked($checked, $current = true, bool $echo = true): string
{
    $result = ((string) $checked === (string) $current) ? ' checked="checked"' : '';
    if ($echo) {
        echo $result;
    }
    return $result;
}

function disabled($disabled, $current = true, bool $echo = true): string
{
    $result = ((string) $disabled === (string) $current) ? ' disabled="disabled"' : '';
    if ($echo) {
        echo $result;
    }
    return $result;
}

function current_user_can(string $cap): bool
{
    return $GLOBALS['__wp_current_user_can'] ?? true;
}

// ── ścieżki / metadane pliku wtyczki ────────────────────────────────────────
function plugin_dir_path(string $file): string
{
    return CCB_TEST_PLUGIN_DIR;
}

function plugin_dir_url(string $file): string
{
    return 'http://example.test/wp-content/plugins/cookie-consent-banner/';
}

function plugin_basename(string $file): string
{
    return basename(dirname($file)) . '/' . basename($file);
}

function admin_url(string $path = ''): string
{
    return 'http://example.test/wp-admin/' . $path;
}

function wp_json_encode($data, int $flags = 0)
{
    return json_encode($data, $flags);
}

// ── rejestry admina / ustawień — nagrywane, żeby dało się je asertować ─────
$GLOBALS['__ccb_admin_pages']        = [];
$GLOBALS['__ccb_registered_settings'] = [];
$GLOBALS['__wp_settings_errors']     = [];

function add_options_page(...$args): void
{
    $GLOBALS['__ccb_admin_pages'][] = [
        'page_title' => $args[0] ?? '',
        'menu_title' => $args[1] ?? '',
        'capability' => $args[2] ?? '',
        'menu_slug'  => $args[3] ?? '',
        'callback'   => $args[4] ?? null,
    ];
}

function register_setting(string $option_group, string $option_name, array $args = []): void
{
    $GLOBALS['__ccb_registered_settings'][$option_name] = compact('option_group', 'args');
}

function add_settings_error(string $setting, string $code, string $message, string $type = 'error'): void
{
    $GLOBALS['__wp_settings_errors'][] = compact('setting', 'code', 'message', 'type');
}

function settings_errors(string $setting = ''): void {}

function settings_fields(string $option_group): void {}

function submit_button(string $text = '', string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null): void
{
    echo '<button type="submit" name="' . esc_attr($name) . '" class="button button-' . esc_attr($type) . '">' . esc_html($text) . '</button>';
}

function load_plugin_textdomain(...$args): void {}

// ── enqueue / inline assets — nagrywane, żeby dało się przetestować "config bannera" ─
$GLOBALS['__ccb_enqueued_styles']  = [];
$GLOBALS['__ccb_enqueued_scripts'] = [];
$GLOBALS['__ccb_inline_styles']    = [];
$GLOBALS['__ccb_localized']        = [];

function wp_enqueue_style(...$args): void
{
    $GLOBALS['__ccb_enqueued_styles'][] = $args;
}

function wp_enqueue_script(...$args): void
{
    $GLOBALS['__ccb_enqueued_scripts'][] = $args;
}

function wp_add_inline_style(string $handle, string $css): void
{
    $GLOBALS['__ccb_inline_styles'][$handle][] = $css;
}

function wp_localize_script(string $handle, string $object_name, array $data): void
{
    $GLOBALS['__ccb_localized'][$handle][$object_name] = $data;
}

// ── wczytanie prawdziwego kodu wtyczki ──────────────────────────────────────
require CCB_TEST_PLUGIN_DIR . 'cookie-consent-banner.php';

// ═══════════════════════════════════════════════════════════════════════════
//  MIKRO-FRAMEWORK ASERCJI (identyczny kontrakt jak w katjaen-global-fields
//  i event-calendar)
// ═══════════════════════════════════════════════════════════════════════════

$GLOBALS['__ccb_test_pass'] = 0;
$GLOBALS['__ccb_test_fail'] = 0;

/**
 * Resetuje stan MIĘDZY sekcjami w ramach jednego pliku testowego —
 * UWAGA: nie resetuje statycznego cache'a wewnątrz ccb_get() (to
 * niemożliwe z zewnątrz, patrz komentarz na górze pliku), więc nie wołaj
 * tego licząc na to, że kolejne ccb_get() zobaczy nowe opcje.
 */
function ccb_test_reset_state(): void
{
    $GLOBALS['__wp_options']              = [];
    $GLOBALS['__wp_current_user_can']     = true;
    $GLOBALS['__ccb_admin_pages']         = [];
    $GLOBALS['__ccb_registered_settings'] = [];
    $GLOBALS['__wp_settings_errors']      = [];
    $GLOBALS['__ccb_enqueued_styles']     = [];
    $GLOBALS['__ccb_enqueued_scripts']    = [];
    $GLOBALS['__ccb_inline_styles']       = [];
    $GLOBALS['__ccb_localized']           = [];
    $_GET    = [];
    $_COOKIE = [];
}

function assert_true($cond, string $msg): void
{
    if ($cond) {
        $GLOBALS['__ccb_test_pass']++;
    } else {
        $GLOBALS['__ccb_test_fail']++;
        echo "  ✗ FAIL: {$msg}\n";
    }
}

function assert_equal($expected, $actual, string $msg): void
{
    $ok = $expected === $actual;
    if (!$ok) {
        $msg .= ' (oczekiwano ' . var_export($expected, true) . ', otrzymano ' . var_export($actual, true) . ')';
    }
    assert_true($ok, $msg);
}

function assert_count(int $expected, $arrayOrCountable, string $msg): void
{
    assert_equal($expected, count($arrayOrCountable), $msg);
}

function ccb_test_section(string $title): void
{
    echo "\n== {$title} ==\n";
}

function ccb_test_summary(): int
{
    $pass = $GLOBALS['__ccb_test_pass'];
    $fail = $GLOBALS['__ccb_test_fail'];
    echo "\n" . str_repeat('─', 60) . "\n";
    echo "WYNIK: {$pass} passed, {$fail} failed (razem " . ($pass + $fail) . ")\n";
    return $fail === 0 ? 0 : 1;
}
