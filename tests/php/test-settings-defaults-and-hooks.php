<?php
require __DIR__ . '/bootstrap.php';

/**
 * ccb_get() (includes/ccb-settings.php), renderowanie strony ustawień,
 * renderowanie szablonu bannera (templates/banner.php) i pozostałe hooki
 * z cookie-consent-banner.php — WSZYSTKO w scenariuszu DOMYŚLNYM (brak
 * zapisanych opcji -> ccb_get() zwraca same wartości z ccb_defaults()).
 *
 * Ten plik NIE wywołuje update_option('ccb_options', ...) przed pierwszym
 * ccb_get() — celowo, żeby przetestować ścieżkę "świeży install". Druga
 * sekcja demonstruje WPROST udokumentowany cache statyczny ccb_get() (patrz
 * bootstrap.php): zmiana opcji PO pierwszym odczycie nie jest widoczna.
 */

// ═══════════════════════════════════════════════════════════════════════════
//  ccb_get() — merge z domyślnymi + PUŁAPKA: statyczny cache w procesie
// ═══════════════════════════════════════════════════════════════════════════

ccb_test_section('ccb_get() — świeży install (brak zapisanej opcji) zwraca wartości domyślne');

// Pierwsze wywołanie ccb_get() w tym procesie — ustala cache na resztę pliku.
assert_equal('', ccb_get('gtm_id'), 'gtm_id domyślnie puste');
assert_equal(90, ccb_get('expiry_accepted'), 'expiry_accepted domyślnie 90 (int, nie string)');
assert_equal('left', ccb_get('banner_position'), 'banner_position domyślnie "left"');
assert_equal(null, ccb_get('klucz_ktory_nie_istnieje'), 'nieznany klucz -> null (fallback ?? null)');

ccb_test_section('ccb_get() — UDOKUMENTOWANA PUŁAPKA: cache statyczny ignoruje późniejsze update_option()');

update_option('ccb_options', ['gtm_id' => 'GTM-PODMIENIONY9']);
assert_equal(
    '',
    ccb_get('gtm_id'),
    'mimo update_option() z nowym gtm_id, ccb_get() nadal zwraca WCZEŚNIEJ scache\'owaną wartość ("") — '
        . 'to prawdziwe zachowanie źródła (static $options w ccb_get()), nie błąd testu. '
        . 'W realnym WP request ccb_get() jest wołane wielokrotnie w ramach JEDNEGO requestu, '
        . 'więc ten cache jest nieszkodliwy tam, gdzie opcje nie zmieniają się w trakcie tego samego requestu '
        . '(np. strona ustawień zapisuje i przekierowuje -> nowy request, nowy proces, świeży cache).'
);

// Od teraz w tym procesie ccb_get() zawsze zwróci opcje z PIERWSZEGO wywołania
// powyżej (czyli same domyślne) — reszta pliku świadomie z tym pracuje.

// ═══════════════════════════════════════════════════════════════════════════
//  ccb_render_settings_page()
// ═══════════════════════════════════════════════════════════════════════════

ccb_test_section('ccb_render_settings_page() — brak uprawnień -> brak outputu');

$GLOBALS['__wp_current_user_can'] = false;
ob_start();
ccb_render_settings_page();
$no_access = ob_get_clean();
assert_equal('', $no_access, 'current_user_can("manage_options")=false -> funkcja nic nie renderuje');
$GLOBALS['__wp_current_user_can'] = true;

ccb_test_section('ccb_render_settings_page() — z uprawnieniami renderuje formularz ustawień');

ob_start();
ccb_render_settings_page();
$html = ob_get_clean();
assert_true(str_contains($html, '<form method="post" action="options.php">'), 'formularz kieruje do options.php (standard WP Settings API)');
assert_true(str_contains($html, 'name="ccb_options[gtm_id]"'), 'pole GTM ID obecne w formularzu');
assert_true(str_contains($html, 'Reset to defaults'), 'przycisk resetu obecny');
assert_true(str_contains($html, 'WP Consent API is not installed'), 'bez wtyczki WP Consent API -> komunikat ostrzegawczy + link instalacji');
assert_true(str_contains($html, 'plugin-install.php?s=wp-consent-api'), 'link do instalacji WP Consent API obecny');

ccb_test_section('ccb_render_settings_page() — z "zainstalowanym" WP Consent API pokazuje komunikat sukcesu');

if (!function_exists('wp_has_consent')) {
    function wp_has_consent(string $category): bool
    {
        return true;
    }
}
ob_start();
ccb_render_settings_page();
$html_with_api = ob_get_clean();
assert_true(str_contains($html_with_api, 'WP Consent API is active'), 'z wp_has_consent() zdefiniowanym -> komunikat "aktywne", nie "brak instalacji"');
assert_true(!str_contains($html_with_api, 'WP Consent API is not installed'), 'komunikat o braku instalacji znika');

ccb_test_section('ccb_textarea_field() — renderuje pojedyncze pole textarea z opisem');

ob_start();
ccb_textarea_field('banner_desc', 'Główny opis', 'Podpowiedź testowa', ['banner_desc' => 'Treść <b>testowa</b>']);
$field = ob_get_clean();
assert_true(str_contains($field, 'id="ccb_banner_desc"'), 'id pola zgodny z konwencją ccb_<klucz>');
assert_true(str_contains($field, 'name="ccb_options[banner_desc]"'), 'name pola zgodny z konwencją ccb_options[<klucz>]');
assert_true(str_contains($field, 'Treść &lt;b&gt;testowa&lt;/b&gt;'), 'wartość pola przechodzi przez esc_textarea() (encodowana)');
assert_true(str_contains($field, 'Podpowiedź testowa'), 'podpowiedź (hint) wyświetlona, gdy niepusta');

ob_start();
ccb_textarea_field('desc_functional', 'Funkcjonalne', '', ['desc_functional' => '']);
$field_no_hint = ob_get_clean();
assert_true(!str_contains($field_no_hint, 'class="description"'), 'pusty hint -> brak elementu <p class="description">');

// ═══════════════════════════════════════════════════════════════════════════
//  HOOKI: wp_head / wp_enqueue_scripts / wp_footer / plugin_action_links
// ═══════════════════════════════════════════════════════════════════════════

ccb_test_section('wp_head — bez GTM ID żaden z dwóch callbacków nic nie drukuje');

ob_start();
ccb_test_fire('wp_head');
$head_out = ob_get_clean();
assert_equal('', $head_out, 'preconnect + Consent Mode default -> oba wymagają ccb_get("gtm_id"), tu puste -> brak outputu');

ccb_test_section('wp_enqueue_scripts — rejestruje styl/skrypt, buduje inline CSS i cookieConfig zgodnie z domyślnymi opcjami');

ccb_test_fire('wp_enqueue_scripts');

assert_count(1, $GLOBALS['__ccb_enqueued_styles'], 'dokładnie jeden styl zarejestrowany');
assert_equal('ccb-style', $GLOBALS['__ccb_enqueued_styles'][0][0], 'handle stylu to "ccb-style"');
assert_equal(CCB_VERSION, $GLOBALS['__ccb_enqueued_styles'][0][3], 'wersja assetu zgodna z CCB_VERSION (cache-busting)');

assert_count(1, $GLOBALS['__ccb_enqueued_scripts'], 'dokładnie jeden skrypt zarejestrowany');
assert_equal('ccb-script', $GLOBALS['__ccb_enqueued_scripts'][0][0], 'handle skryptu to "ccb-script"');
assert_equal(true, $GLOBALS['__ccb_enqueued_scripts'][0][4], 'skrypt ładowany w stopce (in_footer=true)');

$inline_css = $GLOBALS['__ccb_inline_styles']['ccb-style'][0];
assert_true(str_contains($inline_css, '--ccb-banner-max-width: 64ch'), 'inline CSS zawiera domyślną maks. szerokość bannera (64ch)');
assert_true(str_contains($inline_css, 'left: var(--ccb-gutter, 1rem); right: auto;'), 'domyślna pozycja "left" generuje właściwy CSS (nie "center"/"right")');

$cookie_config = $GLOBALS['__ccb_localized']['ccb-script']['cookieConfig'];
assert_equal('', $cookie_config['gtmId'], 'localized cookieConfig.gtmId zgodny z ccb_get("gtm_id") (puste)');
assert_equal(90, $cookie_config['expiryAccepted'], 'localized cookieConfig.expiryAccepted = 90 (rzutowane na int)');
assert_equal(1, $cookie_config['expiryRejected'], 'localized cookieConfig.expiryRejected = 1 (rzutowane na int)');

ccb_test_section('wp_footer — render bannera: sekcje warunkowe zgodne z domyślnymi opcjami (analytics widoczne, marketing ukryte)');

$_GET = [];
$_COOKIE = [];
ob_start();
ccb_test_fire('wp_footer');
$banner_html = ob_get_clean();
assert_true(str_contains($banner_html, 'id="cookie-banner"'), 'kontener bannera obecny w wyjściu');
assert_true(str_contains($banner_html, 'Accept all'), 'przycisk "Accept all" obecny (i18n passthrough w bootstrapie)');
assert_true(str_contains($banner_html, 'id="toggle-functional"'), 'sekcja funkcjonalnych zawsze obecna');
assert_true(str_contains($banner_html, 'id="toggle-analytics"'), 'show_analytics domyślnie "1" -> sekcja analityczna WIDOCZNA');
assert_true(!str_contains($banner_html, 'id="toggle-marketing"'), 'show_marketing domyślnie "" -> sekcja marketingowa UKRYTA');
assert_true(str_contains($banner_html, 'id="cookie-banner-toggle-btn"'), 'pływający przycisk toggle obecny w markupie');

ccb_test_section('wp_footer — parametr ?bricks pomija render bannera całkowicie');

$_GET['bricks'] = '1';
ob_start();
ccb_test_fire('wp_footer');
$bricks_out = ob_get_clean();
assert_equal('', $bricks_out, '?bricks obecny w $_GET -> szablon bannera w ogóle nie jest includowany');
$_GET = [];

ccb_test_section('filtr plugin_action_links — dokłada link "Settings" na początku listy');

$links = apply_filters(
    'plugin_action_links_' . plugin_basename(CCB_TEST_PLUGIN_DIR . 'cookie-consent-banner.php'),
    ['deactivate' => '<a href="#">Deactivate</a>']
);
assert_count(2, $links, 'lista linków rośnie o jeden');
$link_values = array_values($links);
assert_true(str_contains($link_values[0], 'options-general.php?page=ccb-settings'), 'nowy link wskazuje na stronę ustawień wtyczki');
assert_true(str_contains($link_values[0], 'Settings'), 'nowy link ma tekst "Settings"');
assert_true(str_contains($link_values[1], 'Deactivate'), 'oryginalny link "Deactivate" zachowany jako drugi element (array_unshift)');

ccb_test_section('admin_menu — rejestruje podstronę ustawień w menu "Settings"');

ccb_test_fire('admin_menu');
assert_count(1, $GLOBALS['__ccb_admin_pages'], 'dokładnie jedna podstrona zarejestrowana');
assert_equal('ccb-settings', $GLOBALS['__ccb_admin_pages'][0]['menu_slug'], 'slug podstrony to "ccb-settings"');
assert_equal('manage_options', $GLOBALS['__ccb_admin_pages'][0]['capability'], 'wymagane uprawnienie to "manage_options"');
assert_equal('ccb_render_settings_page', $GLOBALS['__ccb_admin_pages'][0]['callback'], 'callback renderujący to ccb_render_settings_page');

ccb_test_section('admin_init — rejestruje ustawienie z poprawnym sanitize_callback i domyślnymi wartościami');

ccb_test_fire('admin_init');
$registered = $GLOBALS['__ccb_registered_settings']['ccb_options'];
assert_equal('ccb_settings_group', $registered['option_group'], 'grupa ustawień to "ccb_settings_group"');
assert_equal('ccb_sanitize_options', $registered['args']['sanitize_callback'], 'sanitize_callback to ccb_sanitize_options');
assert_equal(ccb_defaults(), $registered['args']['default'], 'wartość domyślna rejestracji = ccb_defaults()');

$exit_code = ccb_test_summary();
exit($exit_code);
