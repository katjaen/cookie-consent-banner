<?php

/**
 * Cookie Consent Banner – Strona ustawień  v2.5.0
 */

if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_options_page(
        'Cookie Consent Banner',
        'Cookie Banner',
        'manage_options',
        'ccb-settings',
        'ccb_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting(
        'ccb_settings_group',
        'ccb_options',
        [
            'sanitize_callback' => 'ccb_sanitize_options',
            'default'           => ccb_defaults(),
        ]
    );
});

// ==========================
// WARTOŚCI DOMYŚLNE
// ==========================

function ccb_defaults(): array
{
    return [
        'gtm_id'           => '',
        'show_analytics'   => '1',
        'show_marketing'   => '',
        'yt_nocookie'      => '',
        'expiry_accepted'  => 90,
        'expiry_rejected'  => 1,
        'toggle_position'  => 'left',
        'banner_position'  => 'left',
        'banner_max_width' => '64ch',
        'banner_desc'      => 'Ta strona korzysta z plików cookie w celu poprawy komfortu korzystania: zapamiętywania preferencji, analizy ruchu (Google Analytics – tylko za zgodą) oraz ochrony formularzy kontaktowych. Możesz zaakceptować wszystkie, odrzucić lub dostosować ustawienia.',
        'desc_technical'   => 'Umożliwiają podstawowe funkcje strony: nawigację, sesje, ochronę formularzy. Nie można ich wyłączyć.',
        'desc_functional'  => 'Zapamiętują Twoje preferencje: język, tryb ciemny, układ strony.',
        'desc_analytics'   => 'Zbierają anonimowe dane o ruchu – pomagają ulepszać stronę (Google Analytics).',
        'desc_marketing'   => 'Personalizacja reklam i mierzenie ich skuteczności.',
    ];
}

// ==========================
// SANITIZE
// ==========================

function ccb_sanitize_options(array $raw): array
{
    $defaults = ccb_defaults();

    $gtm_raw = sanitize_text_field($raw['gtm_id'] ?? '');
    $gtm_id  = preg_match('/^GTM-[A-Z0-9]{4,}$/', $gtm_raw) ? $gtm_raw : '';

    if (!empty($raw['gtm_id']) && empty($gtm_id)) {
        add_settings_error(
            'ccb_options',
            'gtm_id_invalid',
            'GTM ID ma nieprawidłowy format. Powinno wyglądać jak GTM-XXXXXXX.',
            'error'
        );
    }

    $raw_width        = sanitize_text_field($raw['banner_max_width'] ?? $defaults['banner_max_width']);
    $banner_max_width = preg_match('/^\d+(ch|px|rem|em|vw|%)$/', $raw_width)
        ? $raw_width
        : $defaults['banner_max_width'];

    $allowed_tags = [
        'a'      => ['href' => [], 'target' => [], 'rel' => []],
        'strong' => [],
        'em'     => [],
        'br'     => [],
    ];

    $clean = [
        'gtm_id'           => $gtm_id,
        'show_marketing'   => !empty($raw['show_marketing']) ? '1' : '',
        'yt_nocookie'      => !empty($raw['yt_nocookie'])    ? '1' : '',
        'expiry_accepted'  => max(1, min(365, (int) ($raw['expiry_accepted'] ?? $defaults['expiry_accepted']))),
        'expiry_rejected'  => max(1, min(365, (int) ($raw['expiry_rejected'] ?? $defaults['expiry_rejected']))),
        'toggle_position'  => in_array($raw['toggle_position'] ?? '', ['left', 'right'], true)
            ? $raw['toggle_position'] : 'left',
        'banner_position'  => in_array($raw['banner_position'] ?? '', ['left', 'center', 'right'], true)
            ? $raw['banner_position'] : 'left',
        'banner_max_width' => $banner_max_width,
        'banner_desc'      => wp_kses($raw['banner_desc']     ?? $defaults['banner_desc'],     $allowed_tags),
        'desc_technical'   => wp_kses($raw['desc_technical']  ?? $defaults['desc_technical'],  $allowed_tags),
        'desc_functional'  => wp_kses($raw['desc_functional'] ?? $defaults['desc_functional'], $allowed_tags),
        'desc_analytics'   => wp_kses($raw['desc_analytics']  ?? $defaults['desc_analytics'],  $allowed_tags),
        'desc_marketing'   => wp_kses($raw['desc_marketing']  ?? $defaults['desc_marketing'],  $allowed_tags),
    ];

    $clean['show_analytics'] = (!empty($gtm_id) || !empty($raw['show_analytics'])) ? '1' : '';

    return $clean;
}

// ==========================
// HELPER: pobierz opcję
// ==========================

function ccb_get(string $key)
{
    static $options = null;
    if ($options === null) {
        $options = wp_parse_args(get_option('ccb_options', []), ccb_defaults());
    }
    return $options[$key] ?? null;
}

// ==========================
// HELPER WIDOKU: textarea
// ==========================

function ccb_textarea_field(string $key, string $label, string $hint, array $opts): void
{
    $id = 'ccb_' . $key;
?>
    <tr>
        <th scope="row"><label for="<?php echo esc_attr($id); ?>"><?php echo esc_html($label); ?></label></th>
        <td>
            <textarea
                id="<?php echo esc_attr($id); ?>"
                name="ccb_options[<?php echo esc_attr($key); ?>]"
                rows="2"
                class="large-text"><?php echo esc_textarea($opts[$key]); ?></textarea>
            <?php if ($hint) : ?>
                <p class="description"><?php echo esc_html($hint); ?></p>
            <?php endif; ?>
        </td>
    </tr>
<?php
}

// ==========================
// RENDER
// ==========================

function ccb_render_settings_page(): void
{
    if (!current_user_can('manage_options')) return;
    $opts             = wp_parse_args(get_option('ccb_options', []), ccb_defaults());
    $has_consent_api  = function_exists('wp_has_consent');
    $allowed_tags_note = 'Dozwolone tagi: <code>&lt;a&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;br&gt;</code>.';
?>
    <div class="wrap">
        <h1>Cookie Consent Banner – Ustawienia</h1>

        <?php settings_errors('ccb_options'); ?>

        <form method="post" action="options.php">
            <?php settings_fields('ccb_settings_group'); ?>

            <!-- ==================== ŚLEDZENIE ==================== -->
            <h2 class="title">Śledzenie</h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row"><label for="ccb_gtm_id">Google Tag Manager ID</label></th>
                    <td>
                        <input
                            type="text"
                            id="ccb_gtm_id"
                            name="ccb_options[gtm_id]"
                            value="<?php echo esc_attr($opts['gtm_id']); ?>"
                            placeholder="GTM-XXXXXXX"
                            class="regular-text"
                            pattern="GTM-[A-Z0-9]{4,}">
                        <p class="description">
                            Format: <code>GTM-XXXXXXX</code>. Jeśli wpisane – analityczne cookies włączają się automatycznie.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Analityczne cookies</th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="ccb_options[show_analytics]"
                                value="1"
                                <?php checked('1', $opts['show_analytics']); ?>
                                <?php disabled(!empty($opts['gtm_id']), true); ?>>
                            Pokaż sekcję analitycznych cookies w banerze
                        </label>
                        <?php if (!empty($opts['gtm_id'])) : ?>
                            <p class="description">Włączone automatycznie – GTM ID jest wpisane.</p>
                        <?php else : ?>
                            <p class="description">
                                Zgodnie z GDPR toggle w banerze zawsze startuje jako <strong>wyłączony</strong>.
                                To pole decyduje tylko czy sekcja jest widoczna.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Marketingowe cookies</th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="ccb_options[show_marketing]"
                                value="1"
                                <?php checked('1', $opts['show_marketing']); ?>>
                            Pokaż sekcję marketingowych cookies w banerze
                        </label>
                        <p class="description">Włącz gdy korzystasz z reklam (Meta Ads, Google Ads itp.).</p>
                    </td>
                </tr>

            </table>

            <!-- ==================== INTEGRACJE ==================== -->
            <h2 class="title">Integracje</h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row">WP Consent API</th>
                    <td>
                        <?php if ($has_consent_api) : ?>
                            <p style="color: #46b450;">
                                ✓ WP Consent API jest aktywne. Zgody są automatycznie synchronizowane
                                z pluginami obsługującymi ten standard (np. Embed Privacy).
                            </p>
                        <?php else : ?>
                            <p>
                                WP Consent API nie jest zainstalowane. Bez niego pluginy blokujące
                                iframy (YouTube, Instagram, Facebook) nie będą reagować na zgody z tego banera.
                            </p>
                            <a
                                href="<?php echo esc_url(admin_url('plugin-install.php?s=wp-consent-api&tab=search&type=term')); ?>"
                                class="button">
                                Zainstaluj WP Consent API
                            </a>
                            <p class="description" style="margin-top: .5rem;">
                                Po instalacji możesz użyć pluginu
                                <a href="https://wordpress.org/plugins/embed-privacy/" target="_blank" rel="noopener">Embed Privacy</a>
                                do blokowania embeddedów YT / IG / FB do czasu wyrażenia zgody.
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row">YouTube nocookie</th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="ccb_options[yt_nocookie]"
                                value="1"
                                <?php checked('1', $opts['yt_nocookie']); ?>>
                            Zastąp <code>youtube.com</code> przez <code>youtube-nocookie.com</code> w embedach
                        </label>
                        <p class="description">
                            Eliminuje cookies śledzące YouTube bez blokowania iframe.
                            Działa w treści stron, postów, widgetów i blokach Gutenberg.
                            Nie zastępuje blokowania embeddedów – to dodatkowe zabezpieczenie.
                        </p>
                    </td>
                </tr>

            </table>

            <!-- ==================== TREŚĆ ==================== -->
            <h2 class="title">Treść banera</h2>
            <p class="description" style="margin: 0 0 1rem;"><?php echo $allowed_tags_note; ?></p>
            <table class="form-table" role="presentation">

                <?php ccb_textarea_field('banner_desc', 'Opis główny', 'Tekst widoczny przy pierwszej wizycie przed rozwinięciem opcji.', $opts); ?>

                <tr>
                    <td colspan="2">
                        <hr>
                    </td>
                </tr>
                <tr>
                    <th colspan="2">
                        <h3 style="margin: 0; font-size: 1rem;">Opisy sekcji cookies</h3>
                    </th>
                </tr>

                <?php ccb_textarea_field('desc_technical',  'Techniczne',   'Sekcja zawsze widoczna, toggle zablokowany.', $opts); ?>
                <?php ccb_textarea_field('desc_functional', 'Funkcjonalne', '', $opts); ?>
                <?php ccb_textarea_field('desc_analytics',  'Analityczne',  'Widoczne tylko gdy sekcja analityczna jest włączona.', $opts); ?>
                <?php ccb_textarea_field('desc_marketing',  'Marketingowe', 'Widoczne tylko gdy sekcja marketingowa jest włączona.', $opts); ?>

            </table>

            <!-- ==================== WAŻNOŚĆ COOKIES ==================== -->
            <h2 class="title">Ważność cookies</h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row"><label for="ccb_expiry_accepted">Po akceptacji (dni)</label></th>
                    <td>
                        <input
                            type="number"
                            id="ccb_expiry_accepted"
                            name="ccb_options[expiry_accepted]"
                            value="<?php echo esc_attr($opts['expiry_accepted']); ?>"
                            min="1" max="365"
                            class="small-text">
                        <p class="description">Domyślnie 90 dni. Zalecane: 90–180.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="ccb_expiry_rejected">Po odrzuceniu (dni)</label></th>
                    <td>
                        <input
                            type="number"
                            id="ccb_expiry_rejected"
                            name="ccb_options[expiry_rejected]"
                            value="<?php echo esc_attr($opts['expiry_rejected']); ?>"
                            min="1" max="365"
                            class="small-text">
                        <p class="description">
                            Domyślnie 1 dzień. Ustaw wyżej jeśli nie chcesz zbyt często pokazywać banera.
                        </p>
                    </td>
                </tr>

            </table>

            <!-- ==================== INTERFEJS ==================== -->
            <h2 class="title">Interfejs</h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row">Pozycja banera (poziomo)</th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">Pozycja banera</legend>
                            <?php foreach (['left' => 'Lewy', 'center' => 'Środek', 'right' => 'Prawy'] as $val => $label) : ?>
                                <label style="margin-right: 1.5rem;">
                                    <input
                                        type="radio"
                                        name="ccb_options[banner_position]"
                                        value="<?php echo esc_attr($val); ?>"
                                        <?php checked($val, $opts['banner_position']); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description">Baner przykleja się do dolnej krawędzi, pozycja tylko pozioma.</p>
                        </fieldset>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="ccb_banner_max_width">Maksymalna szerokość banera</label></th>
                    <td>
                        <input
                            type="text"
                            id="ccb_banner_max_width"
                            name="ccb_options[banner_max_width]"
                            value="<?php echo esc_attr($opts['banner_max_width']); ?>"
                            class="small-text"
                            placeholder="64ch"
                            pattern="\d+(ch|px|rem|em|vw|%)">
                        <p class="description">
                            Jednostki: <code>ch</code>, <code>px</code>, <code>rem</code>, <code>em</code>, <code>vw</code>, <code>%</code>.
                            Domyślnie <code>64ch</code>. Nigdy nie przekroczy 100%.
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Pozycja przycisku przywracania</th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text">Pozycja przycisku przywracania banera</legend>
                            <label style="margin-right: 1.5rem;">
                                <input
                                    type="radio"
                                    name="ccb_options[toggle_position]"
                                    value="left"
                                    <?php checked('left', $opts['toggle_position']); ?>>
                                Lewy dolny róg
                            </label>
                            <label>
                                <input
                                    type="radio"
                                    name="ccb_options[toggle_position]"
                                    value="right"
                                    <?php checked('right', $opts['toggle_position']); ?>>
                                Prawy dolny róg
                            </label>
                        </fieldset>
                    </td>
                </tr>

            </table>

            <?php submit_button('Zapisz ustawienia'); ?>
        </form>
    </div>
<?php
}
