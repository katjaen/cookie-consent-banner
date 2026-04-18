<?php if (!defined('ABSPATH')) exit; ?>

<?php
/**
 * Cookie Consent Banner Template  v2.5.0
 * Wszystkie teksty i opcje pobierane przez ccb_get() z bazy danych.
 */

$ccb_show_analytics  = (bool) ccb_get('show_analytics');
$ccb_show_marketing  = (bool) ccb_get('show_marketing');
$ccb_toggle_position = ccb_get('toggle_position') === 'right' ? 'right' : 'left';

$allowed_tags = [
    'a'      => ['href' => [], 'target' => [], 'rel' => []],
    'strong' => [],
    'em'     => [],
    'br'     => [],
];
?>

<!-- ===================== COOKIE BANNER ===================== -->
<div
    id="cookie-banner"
    class="cookie-banner display--none"
    role="dialog"
    aria-labelledby="cookie-banner-heading"
    aria-modal="true">
    <div class="cookie-banner__inner">

        <!-- INTRO -->
        <div class="cookie-banner__intro">
            <span class="cookie-banner__icon" aria-hidden="true">
                <?php echo CCB_ICON_SVG; ?>
            </span>
            <h2 id="cookie-banner-heading" class="cookie-banner__heading">
                <?php esc_html_e('Cookie information', 'cookie-consent-banner'); ?>
            </h2>
        </div>

        <!-- SEKCJA GŁÓWNA – stan BANNER (pierwsza wizyta) -->
        <div id="cookie-banner-main" class="cookie-banner__main">
            <p class="cookie-banner__description">
                <?php echo wp_kses(ccb_get('banner_desc'), $allowed_tags); ?>
            </p>
            <div class="cookie-banner__actions">
                <button id="accept-all-cookies-btn" class="btn btn--primary" type="button">
                    <?php esc_html_e('Accept all', 'cookie-consent-banner'); ?>
                </button>
                <button id="deny-all-cookies-btn" class="btn btn--outline" type="button">
                    <?php esc_html_e('Reject all', 'cookie-consent-banner'); ?>
                </button>
                <button id="customize-cookie-preferences-btn" class="btn btn--ghost" type="button">
                    <?php esc_html_e('Customize settings', 'cookie-consent-banner'); ?>
                </button>
            </div>
        </div>

        <!-- OPCJE SZCZEGÓŁOWE – stan OPTIONS -->
        <div id="cookie-options" class="cookie-options display--none" aria-labelledby="cookie-options-heading">
            <h3 id="cookie-options-heading" class="cookie-options__heading">
                <?php esc_html_e('Customize cookie settings', 'cookie-consent-banner'); ?>
            </h3>

            <!-- TECHNICZNE -->
            <div class="cookie-option">
                <div class="cookie-option__row">
                    <span class="cookie-option__label"><?php esc_html_e('Technical cookies', 'cookie-consent-banner'); ?></span>
                    <span class="cookie-option__always-on"><?php esc_html_e('Always active', 'cookie-consent-banner'); ?></span>
                </div>
                <p class="cookie-option__description">
                    <?php echo wp_kses(ccb_get('desc_technical'), $allowed_tags); ?>
                </p>
            </div>

            <!-- FUNKCJONALNE -->
            <div class="cookie-option">
                <div class="cookie-option__row">
                    <label class="cookie-option__label" for="toggle-functional">
                        <?php esc_html_e('Functional cookies', 'cookie-consent-banner'); ?>
                    </label>
                    <button
                        id="toggle-functional"
                        class="cookie-toggle"
                        type="button"
                        role="switch"
                        aria-checked="false"
                        data-cookie-type="functional"><span class="cookie-toggle__thumb"></span><span class="sr-only"><?php esc_html_e('Enable or disable functional cookies', 'cookie-consent-banner'); ?></span></button>
                </div>
                <p class="cookie-option__description">
                    <?php echo wp_kses(ccb_get('desc_functional'), $allowed_tags); ?>
                </p>
            </div>

            <?php if ($ccb_show_analytics) : ?>
                <!-- ANALITYCZNE -->
                <div class="cookie-option">
                    <div class="cookie-option__row">
                        <label class="cookie-option__label" for="toggle-analytics">
                            <?php esc_html_e('Analytics cookies', 'cookie-consent-banner'); ?>
                        </label>
                        <button
                            id="toggle-analytics"
                            class="cookie-toggle"
                            type="button"
                            role="switch"
                            aria-checked="false"
                            data-cookie-type="analytics"><span class="cookie-toggle__thumb"></span><span class="sr-only"><?php esc_html_e('Enable or disable analytics cookies', 'cookie-consent-banner'); ?></span></button>
                    </div>
                    <p class="cookie-option__description">
                        <?php echo wp_kses(ccb_get('desc_analytics'), $allowed_tags); ?>
                    </p>
                </div>
            <?php endif; ?>

            <?php if ($ccb_show_marketing) : ?>
                <!-- MARKETINGOWE -->
                <div class="cookie-option">
                    <div class="cookie-option__row">
                        <label class="cookie-option__label" for="toggle-marketing">
                            <?php esc_html_e('Marketing cookies', 'cookie-consent-banner'); ?>
                        </label>
                        <button
                            id="toggle-marketing"
                            class="cookie-toggle"
                            type="button"
                            role="switch"
                            aria-checked="false"
                            data-cookie-type="marketing"><span class="cookie-toggle__thumb"></span><span class="sr-only"><?php esc_html_e('Enable or disable marketing cookies', 'cookie-consent-banner'); ?></span></button>
                    </div>
                    <p class="cookie-option__description">
                        <?php echo wp_kses(ccb_get('desc_marketing'), $allowed_tags); ?>
                    </p>
                </div>
            <?php endif; ?>

            <div class="cookie-options__footer">
                <button id="save-cookie-preferences-btn" class="btn btn--primary" type="button">
                    <?php esc_html_e('Save settings', 'cookie-consent-banner'); ?>
                </button>
            </div>
        </div><!-- /#cookie-options -->

    </div><!-- /.cookie-banner__inner -->
</div><!-- /#cookie-banner -->


<!-- ===================== PŁYWAJĄCY TOGGLE ===================== -->
<button
    id="cookie-banner-toggle-btn"
    class="cookie-banner-toggle cookie-banner-toggle--<?php echo esc_attr($ccb_toggle_position); ?> display--none"
    type="button"
    aria-label="<?php esc_attr_e('Open cookie settings', 'cookie-consent-banner'); ?>"
    aria-haspopup="dialog"
    aria-expanded="false"
    aria-controls="cookie-banner">
    <span class="cookie-banner-toggle__icon" aria-hidden="true">
        <?php echo CCB_ICON_SVG; ?>
    </span>
</button>