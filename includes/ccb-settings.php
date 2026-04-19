<?php

/**
 * Cookie Consent Banner – Strona ustawień  v2.5.0
 */

if (!defined('ABSPATH')) exit;

// ==========================
// REJESTRACJA
// ==========================

add_action('admin_menu', function () {
    add_options_page(
        __('Cookie Consent Banner', 'cookie-consent-banner'),
        __('Cookie Banner', 'cookie-consent-banner'),
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
        /* translators: Main banner description shown to visitors on first visit */
        'banner_desc'      => __('This website uses cookies to improve your experience: remembering preferences, analyzing traffic (Google Analytics – only with consent), and protecting contact forms. You can accept all, reject all, or customize your settings.', 'cookie-consent-banner'),
        /* translators: Description for technical/necessary cookies section */
        'desc_technical'   => __('Enable core site features: navigation, sessions, and form protection. These cannot be disabled.', 'cookie-consent-banner'),
        /* translators: Description for functional cookies section */
        'desc_functional'  => __('Remember your preferences: language, dark mode, and page layout.', 'cookie-consent-banner'),
        /* translators: Description for analytics cookies section */
        'desc_analytics'   => __('Collect anonymous traffic data to help improve the site (Google Analytics).', 'cookie-consent-banner'),
        /* translators: Description for marketing cookies section */
        'desc_marketing'   => __('Personalize ads and measure their effectiveness.', 'cookie-consent-banner'),
    ];
}

// ==========================
// SANITIZE
// ==========================

function ccb_sanitize_options(array $raw): array
{
    $defaults = ccb_defaults();

    // Reset do domyślnych
    if (!empty($raw['reset'])) {
        add_settings_error(
            'ccb_options',
            'ccb_reset',
            __('Settings have been reset to defaults.', 'cookie-consent-banner'),
            'updated'
        );
        return $defaults;
    }

    $gtm_raw = sanitize_text_field($raw['gtm_id'] ?? '');
    $gtm_id  = preg_match('/^GTM-[A-Z0-9]{4,}$/', $gtm_raw) ? $gtm_raw : '';

    if (!empty($raw['gtm_id']) && empty($gtm_id)) {
        add_settings_error(
            'ccb_options',
            'gtm_id_invalid',
            /* translators: Error message when GTM ID format is wrong */
            __('GTM ID format is invalid. It should look like GTM-XXXXXXX.', 'cookie-consent-banner'),
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
    $opts            = wp_parse_args(get_option('ccb_options', []), ccb_defaults());
    $has_consent_api = function_exists('wp_has_consent');
?>
    <div class="wrap">
        <h1><?php esc_html_e('Cookie Consent Banner – Settings', 'cookie-consent-banner'); ?></h1>

        <?php settings_errors('ccb_options'); ?>

        <form method="post" action="options.php">
            <?php settings_fields('ccb_settings_group'); ?>

            <!-- ==================== ŚLEDZENIE ==================== -->
            <h2 class="title"><?php esc_html_e('Tracking', 'cookie-consent-banner'); ?></h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row"><label for="ccb_gtm_id"><?php esc_html_e('Google Tag Manager ID', 'cookie-consent-banner'); ?></label></th>
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
                            <?php
                            /* translators: %s: example GTM ID format */
                            printf(
                                esc_html__('Format: %s. If set, analytics cookies are enabled automatically.', 'cookie-consent-banner'),
                                '<code>GTM-XXXXXXX</code>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Analytics cookies', 'cookie-consent-banner'); ?></th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="ccb_options[show_analytics]"
                                value="1"
                                <?php checked('1', $opts['show_analytics']); ?>
                                <?php disabled(!empty($opts['gtm_id']), true); ?>>
                            <?php esc_html_e('Show analytics cookies section in banner', 'cookie-consent-banner'); ?>
                        </label>
                        <?php if (!empty($opts['gtm_id'])) : ?>
                            <p class="description"><?php esc_html_e('Enabled automatically – GTM ID is set.', 'cookie-consent-banner'); ?></p>
                        <?php else : ?>
                            <p class="description">
                                <?php esc_html_e('Per GDPR, the toggle in the banner always starts as disabled regardless of this setting. This field only controls whether the section is visible.', 'cookie-consent-banner'); ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Marketing cookies', 'cookie-consent-banner'); ?></th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="ccb_options[show_marketing]"
                                value="1"
                                <?php checked('1', $opts['show_marketing']); ?>>
                            <?php esc_html_e('Show marketing cookies section in banner', 'cookie-consent-banner'); ?>
                        </label>
                        <p class="description"><?php esc_html_e('Enable when using ads (Meta Ads, Google Ads, etc.).', 'cookie-consent-banner'); ?></p>
                    </td>
                </tr>

            </table>

            <!-- ==================== INTEGRACJE ==================== -->
            <h2 class="title"><?php esc_html_e('Integrations', 'cookie-consent-banner'); ?></h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row"><?php esc_html_e('WP Consent API', 'cookie-consent-banner'); ?></th>
                    <td>
                        <?php if ($has_consent_api) : ?>
                            <p style="color: #46b450;">
                                ✓ <?php esc_html_e('WP Consent API is active. Consent is automatically synchronized with compatible plugins (e.g. Embed Privacy).', 'cookie-consent-banner'); ?>
                            </p>
                        <?php else : ?>
                            <p><?php esc_html_e('WP Consent API is not installed. Without it, plugins that block iframes (YouTube, Instagram, Facebook) will not respond to consent from this banner.', 'cookie-consent-banner'); ?></p>
                            <a href="<?php echo esc_url(admin_url('plugin-install.php?s=wp-consent-api&tab=search&type=term')); ?>" class="button">
                                <?php esc_html_e('Install WP Consent API', 'cookie-consent-banner'); ?>
                            </a>
                            <p class="description" style="margin-top: .5rem;">
                                <?php
                                printf(
                                    /* translators: %s: link to Embed Privacy plugin */
                                    esc_html__('After installation you can use %s to block YouTube / Instagram / Facebook embeds until consent is given.', 'cookie-consent-banner'),
                                    '<a href="https://wordpress.org/plugins/embed-privacy/" target="_blank" rel="noopener">Embed Privacy</a>'
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('YouTube nocookie', 'cookie-consent-banner'); ?></th>
                    <td>
                        <label>
                            <input
                                type="checkbox"
                                name="ccb_options[yt_nocookie]"
                                value="1"
                                <?php checked('1', $opts['yt_nocookie']); ?>>
                            <?php
                            printf(
                                /* translators: %1$s: youtube.com, %2$s: youtube-nocookie.com */
                                esc_html__('Replace %1$s with %2$s in embeds', 'cookie-consent-banner'),
                                '<code>youtube.com</code>',
                                '<code>youtube-nocookie.com</code>'
                            );
                            ?>
                        </label>
                        <p class="description"><?php esc_html_e('Eliminates YouTube tracking cookies without blocking the iframe. Works in post content, widgets, and Gutenberg blocks.', 'cookie-consent-banner'); ?></p>
                    </td>
                </tr>

            </table>

            <!-- ==================== TREŚĆ ==================== -->
            <h2 class="title"><?php esc_html_e('Banner content', 'cookie-consent-banner'); ?></h2>
            <p class="description" style="margin: 0 0 1rem;">
                <?php
                printf(
                    /* translators: list of allowed HTML tags */
                    esc_html__('Allowed tags: %s', 'cookie-consent-banner'),
                    '<code>&lt;a&gt;</code>, <code>&lt;strong&gt;</code>, <code>&lt;em&gt;</code>, <code>&lt;br&gt;</code>'
                );
                ?>
            </p>
            <table class="form-table" role="presentation">

                <?php ccb_textarea_field('banner_desc', __('Main description', 'cookie-consent-banner'), __('Text shown on first visit before options are expanded.', 'cookie-consent-banner'), $opts); ?>

                <tr>
                    <td colspan="2">
                        <hr>
                    </td>
                </tr>
                <tr>
                    <th colspan="2">
                        <h3 style="margin: 0; font-size: 1rem;"><?php esc_html_e('Cookie section descriptions', 'cookie-consent-banner'); ?></h3>
                    </th>
                </tr>

                <?php ccb_textarea_field('desc_technical',  __('Technical',   'cookie-consent-banner'), __('Always visible, toggle disabled.', 'cookie-consent-banner'), $opts); ?>
                <?php ccb_textarea_field('desc_functional', __('Functional',  'cookie-consent-banner'), '', $opts); ?>
                <?php ccb_textarea_field('desc_analytics',  __('Analytics',   'cookie-consent-banner'), __('Visible only when analytics section is enabled.', 'cookie-consent-banner'), $opts); ?>
                <?php ccb_textarea_field('desc_marketing',  __('Marketing',   'cookie-consent-banner'), __('Visible only when marketing section is enabled.', 'cookie-consent-banner'), $opts); ?>

            </table>

            <!-- ==================== WAŻNOŚĆ COOKIES ==================== -->
            <h2 class="title"><?php esc_html_e('Cookie expiry', 'cookie-consent-banner'); ?></h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row"><label for="ccb_expiry_accepted"><?php esc_html_e('After acceptance (days)', 'cookie-consent-banner'); ?></label></th>
                    <td>
                        <input type="number" id="ccb_expiry_accepted" name="ccb_options[expiry_accepted]"
                            value="<?php echo esc_attr($opts['expiry_accepted']); ?>" min="1" max="365" class="small-text">
                        <p class="description"><?php esc_html_e('Default: 90 days. Recommended: 90–180.', 'cookie-consent-banner'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="ccb_expiry_rejected"><?php esc_html_e('After rejection (days)', 'cookie-consent-banner'); ?></label></th>
                    <td>
                        <input type="number" id="ccb_expiry_rejected" name="ccb_options[expiry_rejected]"
                            value="<?php echo esc_attr($opts['expiry_rejected']); ?>" min="1" max="365" class="small-text">
                        <p class="description"><?php esc_html_e('Default: 1 day. Increase if you don\'t want to show the banner too often.', 'cookie-consent-banner'); ?></p>
                    </td>
                </tr>

            </table>

            <!-- ==================== INTERFEJS ==================== -->
            <h2 class="title"><?php esc_html_e('Interface', 'cookie-consent-banner'); ?></h2>
            <table class="form-table" role="presentation">

                <tr>
                    <th scope="row"><?php esc_html_e('Banner position (horizontal)', 'cookie-consent-banner'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Banner position', 'cookie-consent-banner'); ?></legend>
                            <?php
                            $positions = [
                                'left'   => __('Left', 'cookie-consent-banner'),
                                'center' => __('Center', 'cookie-consent-banner'),
                                'right'  => __('Right', 'cookie-consent-banner'),
                            ];
                            foreach ($positions as $val => $label) : ?>
                                <label style="margin-right: 1.5rem;">
                                    <input type="radio" name="ccb_options[banner_position]"
                                        value="<?php echo esc_attr($val); ?>"
                                        <?php checked($val, $opts['banner_position']); ?>>
                                    <?php echo esc_html($label); ?>
                                </label>
                            <?php endforeach; ?>
                            <p class="description"><?php esc_html_e('Banner sticks to the bottom edge, horizontal position only.', 'cookie-consent-banner'); ?></p>
                        </fieldset>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="ccb_banner_max_width"><?php esc_html_e('Maximum banner width', 'cookie-consent-banner'); ?></label></th>
                    <td>
                        <input type="text" id="ccb_banner_max_width" name="ccb_options[banner_max_width]"
                            value="<?php echo esc_attr($opts['banner_max_width']); ?>"
                            class="small-text" placeholder="64ch" pattern="\d+(ch|px|rem|em|vw|%)">
                        <p class="description">
                            <?php
                            printf(
                                /* translators: %s: list of allowed CSS units */
                                esc_html__('Units: %s. Default: 64ch. Never exceeds 100%%.', 'cookie-consent-banner'),
                                '<code>ch</code>, <code>px</code>, <code>rem</code>, <code>em</code>, <code>vw</code>, <code>%</code>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><?php esc_html_e('Restore button position', 'cookie-consent-banner'); ?></th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><?php esc_html_e('Restore button position', 'cookie-consent-banner'); ?></legend>
                            <label style="margin-right: 1.5rem;">
                                <input type="radio" name="ccb_options[toggle_position]" value="left"
                                    <?php checked('left', $opts['toggle_position']); ?>>
                                <?php esc_html_e('Bottom left', 'cookie-consent-banner'); ?>
                            </label>
                            <label>
                                <input type="radio" name="ccb_options[toggle_position]" value="right"
                                    <?php checked('right', $opts['toggle_position']); ?>>
                                <?php esc_html_e('Bottom right', 'cookie-consent-banner'); ?>
                            </label>
                        </fieldset>
                    </td>
                </tr>

            </table>

            <p class="submit">
                <?php submit_button(__('Save settings', 'cookie-consent-banner'), 'primary', 'submit', false); ?>
                &nbsp;
                <button
                    type="submit"
                    name="ccb_options[reset]"
                    value="1"
                    class="button button-secondary"
                    onclick="return confirm('<?php esc_attr_e('Reset all settings to defaults? This cannot be undone.', 'cookie-consent-banner'); ?>')">
                    <?php esc_html_e('Reset to defaults', 'cookie-consent-banner'); ?>
                </button>
            </p>

        </form>
    </div>
<?php
}
