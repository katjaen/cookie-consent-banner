<?php
require __DIR__ . '/bootstrap.php';

/**
 * ccb_defaults() i ccb_sanitize_options() — funkcje CZYSTE (biorą tablicę,
 * zwracają tablicę), zero zależności od get_option()/ccb_get(), więc nie
 * dotyczy ich pułapka ze statycznym cache'em opisana w bootstrap.php.
 * Najbezpieczniejszy i najbardziej wartościowy cel testów w tej wtyczce.
 */

ccb_test_section('ccb_defaults() — kształt i wartości domyślne');

ccb_test_reset_state();
$defaults = ccb_defaults();
assert_equal('', $defaults['gtm_id'], 'domyślnie brak GTM ID');
assert_equal('1', $defaults['show_analytics'], 'domyślnie sekcja analytics widoczna');
assert_equal('', $defaults['show_marketing'], 'domyślnie sekcja marketing ukryta');
assert_equal('', $defaults['yt_nocookie'], 'domyślnie youtube nocookie wyłączone');
assert_equal(90, $defaults['expiry_accepted'], 'domyślna ważność po akceptacji: 90 dni');
assert_equal(1, $defaults['expiry_rejected'], 'domyślna ważność po odrzuceniu: 1 dzień');
assert_equal('left', $defaults['toggle_position'], 'domyślna pozycja przycisku: left');
assert_equal('left', $defaults['banner_position'], 'domyślna pozycja bannera: left');
assert_equal('64ch', $defaults['banner_max_width'], 'domyślna maks. szerokość: 64ch');
assert_true(!empty($defaults['banner_desc']), 'domyślny opis główny nie jest pusty');
assert_true(!empty($defaults['desc_technical']), 'domyślny opis technicznych nie jest pusty');

ccb_test_section('ccb_sanitize_options() — reset do domyślnych');

ccb_test_reset_state();
$reset = ccb_sanitize_options(['reset' => '1', 'gtm_id' => 'GTM-JUNK']);
assert_equal(ccb_defaults(), $reset, 'reset=1 zwraca dokładnie ccb_defaults(), ignorując resztę inputu');
assert_count(1, $GLOBALS['__wp_settings_errors'], 'reset rejestruje jeden komunikat w add_settings_error()');
assert_equal('ccb_reset', $GLOBALS['__wp_settings_errors'][0]['code'], 'kod komunikatu resetu to "ccb_reset"');
assert_equal('updated', $GLOBALS['__wp_settings_errors'][0]['type'], 'typ komunikatu resetu to "updated" (nie error)');

ccb_test_section('ccb_sanitize_options() — walidacja formatu GTM ID');

ccb_test_reset_state();
$valid = ccb_sanitize_options(['gtm_id' => 'GTM-ABCD1234']);
assert_equal('GTM-ABCD1234', $valid['gtm_id'], 'poprawny format GTM-XXXXXXXX przechodzi bez zmian');
assert_count(0, $GLOBALS['__wp_settings_errors'], 'poprawny GTM ID nie generuje błędu');

ccb_test_reset_state();
$invalid = ccb_sanitize_options(['gtm_id' => 'GTM-AB']); // za krótki sufiks (min. 4 znaki)
assert_equal('', $invalid['gtm_id'], 'za krótki sufiks GTM -> odrzucony, zapisane jako puste');
assert_count(1, $GLOBALS['__wp_settings_errors'], 'niepoprawny GTM ID generuje jeden błąd');
assert_equal('gtm_id_invalid', $GLOBALS['__wp_settings_errors'][0]['code'], 'kod błędu to "gtm_id_invalid"');
assert_equal('error', $GLOBALS['__wp_settings_errors'][0]['type'], 'typ błędu to "error"');

ccb_test_reset_state();
$garbage = ccb_sanitize_options(['gtm_id' => 'nie-jest-to-gtm']);
assert_equal('', $garbage['gtm_id'], 'zupełnie zły format GTM -> odrzucony');
assert_count(1, $GLOBALS['__wp_settings_errors'], 'zły format generuje błąd walidacji');

ccb_test_reset_state();
$empty_gtm = ccb_sanitize_options(['gtm_id' => '']);
assert_equal('', $empty_gtm['gtm_id'], 'pusty GTM ID zostaje pusty');
assert_count(0, $GLOBALS['__wp_settings_errors'], 'pusty GTM ID (świadomie nieustawiony) NIE generuje błędu walidacji');

ccb_test_section('ccb_sanitize_options() — banner_max_width: dozwolone jednostki CSS');

foreach (['64ch', '50px', '2rem', '10vw', '80%', '12em', '1ch'] as $unit) {
    ccb_test_reset_state();
    $r = ccb_sanitize_options(['banner_max_width' => $unit]);
    assert_equal($unit, $r['banner_max_width'], "jednostka '{$unit}' jest akceptowana bez zmian");
}

ccb_test_section('ccb_sanitize_options() — banner_max_width: niepoprawne wartości -> fallback do domyślnej');

foreach (['50xyz', 'abc', 'px50', '50', ''] as $bad) {
    ccb_test_reset_state();
    $r = ccb_sanitize_options(['banner_max_width' => $bad]);
    assert_equal('64ch', $r['banner_max_width'], "niepoprawna wartość '{$bad}' -> fallback do '64ch'");
}

ccb_test_section('ccb_sanitize_options() — checkboxy show_marketing / yt_nocookie: koercja do "1"/""');

ccb_test_reset_state();
$on = ccb_sanitize_options(['show_marketing' => '1', 'yt_nocookie' => 'on']);
assert_equal('1', $on['show_marketing'], "dowolna prawdziwa wartość show_marketing -> '1'");
assert_equal('1', $on['yt_nocookie'], "dowolna prawdziwa wartość yt_nocookie -> '1'");

ccb_test_reset_state();
$off = ccb_sanitize_options([]);
assert_equal('', $off['show_marketing'], 'brak show_marketing w inpucie -> pusty string (checkbox odznaczony)');
assert_equal('', $off['yt_nocookie'], 'brak yt_nocookie w inpucie -> pusty string');

ccb_test_section('ccb_sanitize_options() — show_analytics: zależy od gtm_id ORAZ checkboxa');

ccb_test_reset_state();
$a1 = ccb_sanitize_options(['gtm_id' => 'GTM-ABCD1234', 'show_analytics' => '']);
assert_equal('1', $a1['show_analytics'], 'GTM ID ustawiony -> show_analytics wymuszone na "1", nawet gdy checkbox odznaczony');

ccb_test_reset_state();
$a2 = ccb_sanitize_options(['gtm_id' => '', 'show_analytics' => '1']);
assert_equal('1', $a2['show_analytics'], 'brak GTM ID, ale checkbox zaznaczony -> show_analytics = "1"');

ccb_test_reset_state();
$a3 = ccb_sanitize_options(['gtm_id' => '', 'show_analytics' => '']);
assert_equal('', $a3['show_analytics'], 'brak GTM ID i checkbox odznaczony -> show_analytics = ""');

ccb_test_section('ccb_sanitize_options() — expiry_accepted / expiry_rejected: przycinane do [1, 365]');

ccb_test_reset_state();
$e1 = ccb_sanitize_options(['expiry_accepted' => '-5', 'expiry_rejected' => '0']);
assert_equal(1, $e1['expiry_accepted'], 'wartość ujemna -> przycięta do minimum 1');
assert_equal(1, $e1['expiry_rejected'], 'zero -> przycięte do minimum 1');

ccb_test_reset_state();
$e2 = ccb_sanitize_options(['expiry_accepted' => '9999', 'expiry_rejected' => '400']);
assert_equal(365, $e2['expiry_accepted'], 'wartość powyżej maksimum -> przycięta do 365');
assert_equal(365, $e2['expiry_rejected'], 'wartość powyżej maksimum -> przycięta do 365');

ccb_test_reset_state();
$e3 = ccb_sanitize_options([]);
assert_equal(90, $e3['expiry_accepted'], 'brak klucza w inpucie -> wartość domyślna (90)');
assert_equal(1, $e3['expiry_rejected'], 'brak klucza w inpucie -> wartość domyślna (1)');

ccb_test_reset_state();
$e4 = ccb_sanitize_options(['expiry_accepted' => '45.9']);
assert_equal(45, $e4['expiry_accepted'], 'wartość z ułamkiem jest rzutowana na int (obcięcie, nie zaokrąglenie)');

ccb_test_section('ccb_sanitize_options() — toggle_position / banner_position: tylko dozwolone wartości');

ccb_test_reset_state();
$p1 = ccb_sanitize_options(['toggle_position' => 'right', 'banner_position' => 'center']);
assert_equal('right', $p1['toggle_position'], 'poprawna wartość toggle_position zachowana');
assert_equal('center', $p1['banner_position'], 'poprawna wartość banner_position zachowana');

ccb_test_reset_state();
$p2 = ccb_sanitize_options(['toggle_position' => 'srodek', 'banner_position' => 'gora']);
assert_equal('left', $p2['toggle_position'], 'niepoprawna wartość toggle_position -> fallback "left"');
assert_equal('left', $p2['banner_position'], 'niepoprawna wartość banner_position -> fallback "left" (nie "center"/"right")');

ccb_test_reset_state();
$p3 = ccb_sanitize_options([]);
assert_equal('left', $p3['toggle_position'], 'brak wartości w inpucie -> fallback "left"');
assert_equal('left', $p3['banner_position'], 'brak wartości w inpucie -> fallback "left"');

ccb_test_section('ccb_sanitize_options() — treści tekstowe: wp_kses z ograniczoną listą tagów');

ccb_test_reset_state();
$xss = ccb_sanitize_options([
    'banner_desc' => 'Tekst <strong>ważny</strong> <script>alert(1)</script> <img src=x onerror=alert(1)>',
]);
assert_true(str_contains($xss['banner_desc'], '<strong>ważny</strong>'), 'dozwolony tag <strong> przechodzi');
assert_true(!str_contains($xss['banner_desc'], '<script>'), 'niedozwolony tag <script> jest usuwany');
assert_true(!str_contains($xss['banner_desc'], '<img'), 'niedozwolony tag <img> jest usuwany');
assert_true(str_contains($xss['banner_desc'], 'alert(1)'), 'sam TEKST wewnątrz usuniętych tagów zostaje (wp_kses usuwa tylko znaczniki)');

ccb_test_reset_state();
$link = ccb_sanitize_options(['desc_functional' => 'Zobacz <a href="https://example.test">link</a>.']);
assert_true(str_contains($link['desc_functional'], '<a href="https://example.test">link</a>'), 'dozwolony tag <a> z atrybutami przechodzi bez zmian');

ccb_test_reset_state();
$fallback_text = ccb_sanitize_options([]);
assert_equal(ccb_defaults()['banner_desc'], $fallback_text['banner_desc'], 'brak banner_desc w inpucie -> fallback do domyślnego opisu');
assert_equal(ccb_defaults()['desc_marketing'], $fallback_text['desc_marketing'], 'brak desc_marketing w inpucie -> fallback do domyślnego opisu');

$exit_code = ccb_test_summary();
exit($exit_code);
