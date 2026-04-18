<?php

/**
 * Cookie Consent Banner – Integracje  v2.5.0
 *
 * 1. WP Consent API
 *    Rejestruje kategorie cookies i synchronizuje stan zgód
 *    z API standardu WordPress. Pluginy takie jak Embed Privacy
 *    słuchają tego API i automatycznie blokują iframy.
 *
 * 2. YouTube nocookie
 *    Opcjonalny filtr the_content – podmienia youtube.com
 *    na youtube-nocookie.com w embedach. Eliminuje cookies
 *    śledzące YT bez blokowania iframe w ogóle.
 */

if (!defined('ABSPATH')) exit;

// ==========================
// 1. WP CONSENT API
// ==========================

/**
 * Czy WP Consent API jest dostępne?
 * Plugin: https://wordpress.org/plugins/wp-consent-api/
 */
function ccb_has_consent_api(): bool
{
    return function_exists('wp_has_consent');
}

/**
 * Rejestracja pluginu w WP Consent API.
 * Wymagane żeby API wiedziało że nasz plugin zarządza zgodami.
 */
add_filter('wp_consent_api_registered_plugin_name', function (array $plugins): array {
    $plugins[] = plugin_basename(CCB_PATH . 'cookie-consent-banner.php');
    return $plugins;
});

/**
 * Mapowanie naszych kategorii na kategorie WP Consent API.
 *
 * Nasze          → WP Consent API
 * functional     → functional
 * analytics      → statistics        (anonimowe = statistics, śledzące = statistics-anonymous)
 * marketing      → marketing
 *
 * Uwaga: WP Consent API używa innych nazw kategorii niż GDPR potocznie.
 * Pełna lista: https://github.com/rlankhorst/wp-consent-level-api
 */
const CCB_CONSENT_MAP = [
    'functional' => 'functional',
    'analytics'  => 'statistics',
    'marketing'  => 'marketing',
];

/**
 * Synchronizuj zgody z WP Consent API po załadowaniu strony.
 * API czyta stan ze swoich cookies – nasz plugin musi je ustawić.
 *
 * WP Consent API używa własnych cookies w formacie:
 *   wp_consent_type = "optin" | "optout"
 * i sprawdza je przez wp_has_consent($category).
 *
 * Najprostsze podejście: ustawiamy cookie wp_consent_type
 * na podstawie naszych zapisanych zgód.
 */
add_action('wp_footer', function (): void {
    if (!ccb_has_consent_api()) return;

    // Pobierz nasze zapisane zgody z cookies HTTP
    $our_consent = ccb_read_our_cookies();
    if ($our_consent === null) return; // użytkownik jeszcze nie wybrał

    // Ustal typ zgody dla WP Consent API
    // 'optin'  – użytkownik aktywnie wyraził zgodę
    // 'optout' – brak zgody (lub odrzucenie)
    $any_accepted = in_array(true, $our_consent, true);
    $consent_type = $any_accepted ? 'optin' : 'optout';

    // Wyślij do WP Consent API przez JS (API ma też JS-ową warstwę)
    // wp_set_consent() nie istnieje w PHP – API działa przez JS i cookies
    $consent_json = wp_json_encode($our_consent);
?>
    <script>
        (function() {
            // WP Consent API JS – ustaw kategorie jeśli API dostępne
            if (typeof wp_set_consent === 'function') {
                var consent = <?php echo $consent_json; ?>;
                var map = <?php echo wp_json_encode(CCB_CONSENT_MAP); ?>;
                for (var type in map) {
                    if (consent.hasOwnProperty(type)) {
                        wp_set_consent(map[type], consent[type] ? 'allow' : 'deny');
                    }
                }
            }
        })();
    </script>
<?php
}, 99);

/**
 * Odczytaj nasze cookies HTTP po stronie PHP.
 * Zwraca array ['functional' => bool, ...] lub null gdy brak zgód.
 */
function ccb_read_our_cookies(): ?array
{
    $types = [
        'functional' => 'cookieConsent-functional',
        'analytics'  => 'cookieConsent-analytics',
        'marketing'  => 'cookieConsent-marketing',
    ];

    $result   = [];
    $has_any  = false;

    foreach ($types as $type => $cookie_name) {
        if (!isset($_COOKIE[$cookie_name])) continue;
        $has_any      = true;
        $result[$type] = $_COOKIE[$cookie_name] === 'true';
    }

    return $has_any ? $result : null;
}

/**
 * Filtr dla pluginów używających WP Consent API.
 * Gdy plugin pyta `wp_has_consent('statistics')` – odpowiadamy
 * na podstawie naszych zapisanych cookies.
 *
 * Wymaga WP Consent API >= 1.0.0
 */
add_filter('wp_consent_api_consent_value', function (bool $has_consent, string $category): bool {
    $consent = ccb_read_our_cookies();
    if ($consent === null) return false; // brak zgody = domyślnie false

    // Odwróć mapowanie: WP category → nasza category
    $reverse_map = array_flip(CCB_CONSENT_MAP);
    $our_type    = $reverse_map[$category] ?? null;

    if ($our_type === null) return $has_consent; // nieznana kategoria – nie zmieniaj
    return $consent[$our_type] ?? false;
}, 10, 2);


// ==========================
// 2. YOUTUBE NOCOOKIE
// ==========================

/**
 * Filtr the_content – podmienia youtube.com na youtube-nocookie.com
 * w atrybutach src i data-src iframe'ów.
 *
 * Włączany przez opcję 'yt_nocookie' w ustawieniach.
 * Działa na poziomie PHP – zero kosztu JS, zero opóźnienia.
 *
 * Obsługuje formaty:
 *   https://www.youtube.com/embed/VIDEO_ID
 *   https://youtube.com/embed/VIDEO_ID
 */
add_filter('the_content', 'ccb_youtube_nocookie', 20);
add_filter('widget_text',  'ccb_youtube_nocookie', 20); // widgety tekstowe
add_filter('the_excerpt',  'ccb_youtube_nocookie', 20); // excerpty

function ccb_youtube_nocookie(string $content): string
{
    if (!ccb_get('yt_nocookie')) return $content;

    return preg_replace(
        '~(src|data-src)=["\']https?://(?:www\.)?youtube\.com/(embed/[^"\'?\s]+)~i',
        '$1="https://www.youtube-nocookie.com/$2',
        $content
    );
}

/**
 * Obsługa bloków Gutenberg (block editor zapisuje inaczej).
 * Filtr render_block działa na poziomie renderowania bloku,
 * więc łapie też bloki core/embed z YouTube.
 */
add_filter('render_block', function (string $block_content, array $block): string {
    if (!ccb_get('yt_nocookie')) return $block_content;

    // Tylko bloki embed z YouTube
    if (($block['blockName'] ?? '') !== 'core/embed') return $block_content;
    $provider = $block['attrs']['providerNameSlug'] ?? '';
    if ($provider !== 'youtube') return $block_content;

    return ccb_youtube_nocookie($block_content);
}, 10, 2);
