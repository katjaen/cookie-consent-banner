<?php
require __DIR__ . '/bootstrap.php';

/**
 * includes/ccb-consent-api.php:
 *  A) integracja z WP Consent API — ccb_has_consent_api(), ccb_read_our_cookies()
 *     (czyta bezpośrednio $_COOKIE, bez ccb_get()) i filtry wp_consent_api_*.
 *  B) filtr YouTube nocookie — ccb_youtube_nocookie() ORAZ jego spięcie z
 *     'the_content' / 'widget_text' / 'the_excerpt' / 'render_block'.
 *
 * Sekcja B zależy od ccb_get('yt_nocookie'), które w includes/ccb-settings.php
 * jest cache'owane w `static $options` — RAZ odczytane w tym procesie już się
 * nie zmienia (patrz komentarz w bootstrap.php). Dlatego CAŁY ten plik trzyma
 * się JEDNEGO scenariusza opcji: yt_nocookie WŁĄCZONE, ustawionego update_option()
 * PRZED pierwszym wywołaniem ccb_get() w tym procesie.
 */

// ═══════════════════════════════════════════════════════════════════════════
//  A) WP CONSENT API
// ═══════════════════════════════════════════════════════════════════════════

ccb_test_section('ccb_has_consent_api() — zależy tylko od function_exists("wp_has_consent")');

ccb_test_reset_state();
assert_true(!ccb_has_consent_api(), 'domyślnie (bez wtyczki WP Consent API) -> false');

ccb_test_section('CCB_CONSENT_MAP — mapowanie naszych kategorii na kategorie WP Consent API');

assert_equal('functional', CCB_CONSENT_MAP['functional'], 'functional -> functional');
assert_equal('statistics', CCB_CONSENT_MAP['analytics'], 'analytics -> statistics');
assert_equal('marketing', CCB_CONSENT_MAP['marketing'], 'marketing -> marketing');

ccb_test_section('filtr wp_consent_api_registered_plugin_name — rejestruje naszą wtyczkę');

ccb_test_reset_state();
$plugins = apply_filters('wp_consent_api_registered_plugin_name', ['inny-plugin/inny-plugin.php']);
assert_count(2, $plugins, 'lista pluginów rośnie o jeden wpis');
assert_true(str_contains($plugins[1], 'cookie-consent-banner.php'), 'dopisany wpis wskazuje na cookie-consent-banner.php');

ccb_test_section('ccb_read_our_cookies() — brak cookies -> null');

ccb_test_reset_state();
assert_equal(null, ccb_read_our_cookies(), 'pusty $_COOKIE -> null (użytkownik jeszcze nie wybrał)');

ccb_test_section('ccb_read_our_cookies() — częściowe cookies: tylko klucze faktycznie obecne w $_COOKIE');

ccb_test_reset_state();
$_COOKIE['cookieConsent-functional'] = 'true';
$result = ccb_read_our_cookies();
assert_equal(['functional' => true], $result, 'tylko "functional" jest w $_COOKIE -> tylko ten klucz w wyniku');

ccb_test_section('ccb_read_our_cookies() — komplet cookies, wartości inne niż literalne "true" -> false');

ccb_test_reset_state();
$_COOKIE['cookieConsent-functional'] = 'true';
$_COOKIE['cookieConsent-analytics']  = 'false';
$_COOKIE['cookieConsent-marketing']  = '1'; // nie "true" -> traktowane jako false
$result2 = ccb_read_our_cookies();
assert_equal(['functional' => true, 'analytics' => false, 'marketing' => false], $result2, 'tylko dosłowny string "true" daje bool(true), reszta -> false');

ccb_test_section('filtr wp_consent_api_consent_value — brak naszych cookies -> zawsze false');

ccb_test_reset_state();
assert_equal(false, apply_filters('wp_consent_api_consent_value', true, 'statistics'), 'brak zgód zapisanych -> false niezależnie od wejściowej wartości');

ccb_test_section('filtr wp_consent_api_consent_value — odwrotne mapowanie kategorii WP -> nasza kategoria');

ccb_test_reset_state();
$_COOKIE['cookieConsent-analytics'] = 'true';
$_COOKIE['cookieConsent-marketing'] = 'false';
assert_equal(true, apply_filters('wp_consent_api_consent_value', false, 'statistics'), '"statistics" (WP) mapuje na naszą "analytics" -> true');
assert_equal(false, apply_filters('wp_consent_api_consent_value', true, 'marketing'), '"marketing" -> nasza "marketing" -> false');

ccb_test_section('filtr wp_consent_api_consent_value — nieznana kategoria WP -> wartość wejściowa bez zmian');

ccb_test_reset_state();
$_COOKIE['cookieConsent-functional'] = 'true';
assert_equal(true, apply_filters('wp_consent_api_consent_value', true, 'nieznana-kategoria'), 'nieznana kategoria + wejście true -> zostaje true');
assert_equal(false, apply_filters('wp_consent_api_consent_value', false, 'nieznana-kategoria'), 'nieznana kategoria + wejście false -> zostaje false');

ccb_test_section('wp_footer: synchronizacja z WP Consent API — brak API zainstalowanego -> brak outputu');

ccb_test_reset_state();
$_GET['bricks'] = '1'; // wyłącza render bannera, izolujemy sam callback consent-api
ob_start();
ccb_test_fire('wp_footer');
$out = ob_get_clean();
assert_equal('', $out, 'wp_has_consent nieistniejące -> ccb_has_consent_api()=false -> callback nic nie drukuje');

ccb_test_section('wp_footer: synchronizacja z WP Consent API — API "zainstalowane" (symulacja), ale brak cookies -> nadal brak outputu');

// Definiujemy wp_has_consent() TYLKO w tym procesie/pliku — nie wpływa na
// pozostałe pliki testowe (każdy uruchamiany jest jako osobny proces php).
//
// UWAGA: deklaracja funkcji na najwyższym poziomie pliku PHP jest
// "hoistowana" (rejestrowana już przy kompilacji, przed wykonaniem
// jakiejkolwiek linii kodu) — gdyby zadeklarować ją tutaj wprost, byłaby
// dostępna od SAMEGO POCZĄTKU skryptu i zafałszowała test wyżej ("brak API
// -> false"). Owinięcie jej w blok warunkowy wymusza rejestrację dopiero
// w momencie wykonania tej linii (deklaracje wewnątrz bloków warunkowych
// NIE są hoistowane).
if (!function_exists('wp_has_consent')) {
    function wp_has_consent(string $category): bool
    {
        return true;
    }
}

ccb_test_reset_state();
$_GET['bricks'] = '1';
ob_start();
ccb_test_fire('wp_footer');
$out2 = ob_get_clean();
assert_true(ccb_has_consent_api(), 'po zdefiniowaniu wp_has_consent() -> ccb_has_consent_api() zwraca true');
assert_equal('', $out2, 'API "zainstalowane", ale użytkownik jeszcze nic nie wybrał (brak cookies) -> nadal brak outputu');

ccb_test_section('wp_footer: synchronizacja z WP Consent API — API zainstalowane + zapisane zgody -> drukuje skrypt wp_set_consent');

ccb_test_reset_state();
$_GET['bricks'] = '1';
$_COOKIE['cookieConsent-functional'] = 'true';
$_COOKIE['cookieConsent-analytics']  = 'false';
$_COOKIE['cookieConsent-marketing']  = 'false';
ob_start();
ccb_test_fire('wp_footer');
$out3 = ob_get_clean();
assert_true(str_contains($out3, 'wp_set_consent'), 'output zawiera wywołanie wp_set_consent() po stronie JS');
assert_true(str_contains($out3, '"functional":true'), 'JSON zgód zawiera functional:true');
assert_true(str_contains($out3, '"analytics":false'), 'JSON zgód zawiera analytics:false');
assert_true(str_contains($out3, '"marketing":false'), 'JSON zgód zawiera marketing:false');
assert_true(str_contains($out3, '"analytics":"statistics"'), 'JSON mapy kategorii zawiera analytics -> statistics');

// ═══════════════════════════════════════════════════════════════════════════
//  B) YOUTUBE NOCOOKIE — scenariusz z yt_nocookie WŁĄCZONYM (ustawione PRZED
//     pierwszym wywołaniem ccb_get() w tym procesie)
// ═══════════════════════════════════════════════════════════════════════════

update_option('ccb_options', ['yt_nocookie' => '1']);

ccb_test_section('ccb_youtube_nocookie() — podmienia youtube.com na youtube-nocookie.com w src (z www i bez)');

$with_www = ccb_youtube_nocookie('<iframe src="https://www.youtube.com/embed/abc123"></iframe>');
assert_true(str_contains($with_www, 'https://www.youtube-nocookie.com/embed/abc123'), 'wariant z "www." podmieniony poprawnie');

$without_www = ccb_youtube_nocookie('<iframe src="https://youtube.com/embed/abc123"></iframe>');
assert_true(str_contains($without_www, 'https://www.youtube-nocookie.com/embed/abc123'), 'wariant bez "www." też podmieniony (i dostaje "www.")');

ccb_test_section('ccb_youtube_nocookie() — obsługuje też atrybut data-src (lazy-load)');

$data_src = ccb_youtube_nocookie('<iframe data-src="https://www.youtube.com/embed/xyz789"></iframe>');
assert_true(str_contains($data_src, 'data-src="https://www.youtube-nocookie.com/embed/xyz789'), 'data-src również podmieniany');

ccb_test_section('ccb_youtube_nocookie() — treść bez YouTube zostaje bez zmian');

$plain = ccb_youtube_nocookie('<p>Zwykły akapit bez embedów.</p>');
assert_equal('<p>Zwykły akapit bez embedów.</p>', $plain, 'treść bez YouTube niezmieniona');

$vimeo = ccb_youtube_nocookie('<iframe src="https://player.vimeo.com/video/123"></iframe>');
assert_true(str_contains($vimeo, 'vimeo.com'), 'embed z innego serwisu (Vimeo) nie jest ruszany');

ccb_test_section('filtry the_content / widget_text / the_excerpt — wszystkie spięte z ccb_youtube_nocookie()');

$src = '<iframe src="https://www.youtube.com/embed/hook-test"></iframe>';
$expected = ccb_youtube_nocookie($src);
assert_equal($expected, apply_filters('the_content', $src), "filtr 'the_content' daje ten sam wynik co bezpośrednie wywołanie");
assert_equal($expected, apply_filters('widget_text', $src), "filtr 'widget_text' daje ten sam wynik");
assert_equal($expected, apply_filters('the_excerpt', $src), "filtr 'the_excerpt' daje ten sam wynik");

ccb_test_section('filtr render_block — tylko blok core/embed z providerem "youtube"');

$block_html = '<figure><iframe src="https://www.youtube.com/embed/block-test"></iframe></figure>';

$transformed = apply_filters('render_block', $block_html, [
    'blockName' => 'core/embed',
    'attrs'     => ['providerNameSlug' => 'youtube'],
]);
assert_true(str_contains($transformed, 'youtube-nocookie.com'), 'core/embed + providerNameSlug=youtube -> podmienione');

$vimeo_block = apply_filters('render_block', $block_html, [
    'blockName' => 'core/embed',
    'attrs'     => ['providerNameSlug' => 'vimeo'],
]);
assert_equal($block_html, $vimeo_block, 'core/embed z innym providerem (vimeo) -> BEZ zmian');

$other_block = apply_filters('render_block', $block_html, [
    'blockName' => 'core/paragraph',
]);
assert_equal($block_html, $other_block, 'blok inny niż core/embed -> BEZ zmian, nawet z YouTube URL w treści');

$exit_code = ccb_test_summary();
exit($exit_code);
