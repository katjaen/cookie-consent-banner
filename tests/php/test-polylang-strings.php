<?php
require __DIR__ . '/bootstrap.php';

/**
 * Polylang (darmowy): teksty widoczne dla odwiedzającego są rejestrowane jako
 * stringi i tłumaczone przez pll__() w ccb_get(). Stuby pll_* definiujemy
 * PRZED pierwszym użyciem ccb_get() (osobny proces = osobny cache).
 */

$GLOBALS['__pll_registered'] = [];

function pll_register_string(string $name, string $string, string $context, bool $multiline): void
{
    $GLOBALS['__pll_registered'][$name] = [$string, $context, $multiline];
}

function pll__(string $string): string
{
    return 'EN: ' . $string;
}

update_option('ccb_options', ['banner_desc' => 'Własny opis', 'gtm_id' => 'GTM-TEST123']);

ccb_test_section('init — rejestruje 5 tekstów jako stringi Polylanga (tekst źródłowy, multiline)');

ccb_test_fire('init');
assert_count(5, $GLOBALS['__pll_registered'], 'zarejestrowano 5 stringów');
assert_equal('Własny opis', $GLOBALS['__pll_registered']['banner_desc'][0], 'banner_desc zarejestrowany ze źródłowym tekstem z opcji');
assert_true($GLOBALS['__pll_registered']['desc_analytics'][2], 'stringi są multiline');

ccb_test_section('ccb_get() — tłumaczy tylko teksty widoczne, reszta bez zmian');

assert_equal('EN: Własny opis', ccb_get('banner_desc'), 'banner_desc przechodzi przez pll__()');
assert_equal('GTM-TEST123', ccb_get('gtm_id'), 'gtm_id NIE jest tłumaczone');

exit(ccb_test_summary());
