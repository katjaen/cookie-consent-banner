<?php
require __DIR__ . '/bootstrap.php';

/**
 * Drugi scenariusz opcji (osobny proces PHP -> osobny cache ccb_get(), patrz
 * bootstrap.php): GTM ID USTAWIONY i banner_position != "left". Testuje
 * gałęzie kodu, których scenariusz "świeży install" (test-settings-defaults-
 * and-hooks.php) nie dotyka:
 *  - wp_head: preconnect + Google Consent Mode default (wymagają gtm_id)
 *  - wp_enqueue_scripts: banner_position "center"/"right" -> inny CSS
 *  - cookieConfig.gtmId faktycznie przekazany do JS
 */

update_option('ccb_options', [
    'gtm_id'          => 'GTM-TESTOWY99',
    'banner_position' => 'center',
]);

ccb_test_section('ccb_get() — scenariusz z GTM ID: pierwsze wywołanie ustala cache na resztę pliku');

assert_equal('GTM-TESTOWY99', ccb_get('gtm_id'), 'gtm_id odczytany zgodnie z zapisaną opcją');
assert_equal('center', ccb_get('banner_position'), 'banner_position odczytany zgodnie z zapisaną opcją');

ccb_test_section('wp_head — z GTM ID: preconnect + Google Consent Mode default są drukowane');

ob_start();
ccb_test_fire('wp_head');
$head_out = ob_get_clean();
assert_true(str_contains($head_out, 'rel="preconnect" href="https://www.googletagmanager.com"'), 'link preconnect do GTM obecny');
assert_true(str_contains($head_out, 'rel="dns-prefetch" href="https://www.googletagmanager.com"'), 'link dns-prefetch do GTM obecny');
assert_true(str_contains($head_out, "gtag('consent', 'default'"), 'skrypt Consent Mode default obecny');
assert_true(str_contains($head_out, "'ad_storage': 'denied'"), 'wszystkie sygnały Consent Mode startują jako "denied"');

ccb_test_section('wp_enqueue_scripts — banner_position="center" generuje CSS wyśrodkowania, nie domyślny "left"');

ccb_test_fire('wp_enqueue_scripts');
$inline_css = $GLOBALS['__ccb_inline_styles']['ccb-style'][0];
assert_true(str_contains($inline_css, 'left: 50%; transform: translateX(-50%); right: auto;'), 'CSS wyśrodkowania obecny dla banner_position="center"');
assert_true(!str_contains($inline_css, 'left: var(--ccb-gutter'), 'CSS domyślnej pozycji "left" NIE jest użyty, skoro ustawiono "center"');

ccb_test_section('wp_enqueue_scripts — cookieConfig.gtmId przekazuje faktyczny GTM ID do JS');

$cookie_config = $GLOBALS['__ccb_localized']['ccb-script']['cookieConfig'];
assert_equal('GTM-TESTOWY99', $cookie_config['gtmId'], 'cookieConfig.gtmId zgodny z ustawioną opcją');

$exit_code = ccb_test_summary();
exit($exit_code);
