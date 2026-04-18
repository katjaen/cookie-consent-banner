<?php

/**
 * Plugin Name: Cookie Consent Banner
 * Plugin URI:  https://github.com/katjaen/cookie-consent-banner
 * Description: GDPR-compliant cookie consent banner – lightweight, accessible, cookie-based storage. No external dependencies.
 * Version:     2.5.0
 * Author:      Katarzyna Niklas
 * Author URI:  https://niklassmolen.pl
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cookie-consent-banner
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) exit;

define('CCB_PATH', plugin_dir_path(__FILE__));
define('CCB_URL',  plugin_dir_url(__FILE__));

define('CCB_ICON_SVG', '
<svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 40 40" fill="none" aria-hidden="true" focusable="false">
  <g clip-path="url(#ccb-clip)">
    <path d="M17.3637 0C16.427 0.121475 15.525 0.312364 14.623 0.555315C19.9136 3.22777 24.7878 6.71583 29.0029 10.9501C33.2874 15.2364 36.774 20.1128 39.4106 25.3536C39.6534 24.4338 39.8442 23.4967 39.9656 22.5423C37.3811 17.9089 34.1374 13.5879 30.2692 9.70065C26.4531 5.84816 22.0819 2.60304 17.3637 0Z" fill="currentColor"/>
    <path d="M10.9627 29.067C6.73027 24.8328 3.20902 19.9564 0.555074 14.6636C0.312229 15.5486 0.121422 16.451 0 17.3707C2.60191 22.0909 5.84562 26.464 9.69644 30.2991C13.5473 34.1516 17.9185 37.3968 22.6366 39.9998C23.5559 39.8783 24.4579 39.7048 25.3252 39.4618C20.052 36.7894 15.1778 33.3013 10.9627 29.067Z" fill="currentColor"/>
    <path d="M31.2926 3.26249C29.3498 1.94362 27.1642 0.989172 24.8398 0.416504C27.6846 2.42952 30.3732 4.65078 32.8711 7.14969C35.3516 9.63125 37.5892 12.3211 39.584 15.1497C39.0116 12.8243 38.0575 10.6378 36.7566 8.71152C35.9066 7.75707 35.0393 6.80262 34.1373 5.90024C33.2353 4.99785 32.2813 4.11282 31.3273 3.26249H31.2926Z" fill="currentColor"/>
    <path d="M7.14605 32.8504C4.64822 30.3515 2.41058 27.6443 0.398438 24.7983C0.988203 27.1931 1.97693 29.4317 3.32992 31.4101C4.14519 32.3298 4.99514 33.2495 5.87979 34.1346C6.76444 35.0196 7.66643 35.8526 8.60312 36.6855C10.5806 38.0391 12.8009 39.0283 15.1946 39.6183C12.3499 37.6053 9.64388 35.3667 7.14605 32.8678V32.8504Z" fill="currentColor"/>
    <path d="M3.55672 8.3125C3.14041 8.90252 2.7588 9.5099 2.39453 10.1346C4.99644 16.2431 8.70849 21.7615 13.4787 26.5338C18.2488 31.306 23.7475 35.0023 29.836 37.6227C30.4778 37.2583 31.1023 36.8591 31.692 36.4253C25.3434 33.8917 19.6365 30.1607 14.7449 25.2669C9.85333 20.3732 6.08924 14.6639 3.55672 8.3125Z" fill="currentColor"/>
    <path d="M26.4883 13.4663V13.4828H26.5048L26.4883 13.4663Z" fill="currentColor"/>
    <path d="M22.7227 17.2843C18.7678 13.3277 14.2057 10.152 9.17539 7.84393C8.62032 6.57712 8.13463 5.29295 7.70098 4.00879C7.1806 4.40792 6.69491 4.82441 6.22656 5.2756C6.66021 6.49035 7.1459 7.7051 7.66629 8.88514H7.68363C10.008 14.0218 13.2344 18.6899 17.276 22.7333C21.3176 26.7767 25.7756 29.8656 30.8059 32.1737C31.361 33.4231 31.8467 34.7073 32.2803 35.9914C32.7834 35.6097 33.2691 35.1932 33.7374 34.7593C31.4477 28.3038 27.7357 22.3168 22.7227 17.3016V17.2843ZM29.557 29.5706C25.5154 27.4535 21.8207 24.7463 18.5423 21.4665C15.2639 18.1867 12.5579 14.4904 10.4417 10.447C14.4833 12.5641 18.178 15.2713 21.4564 18.5511C24.7521 21.8483 27.4581 25.5619 29.557 29.5706Z" fill="currentColor"/>
    <path d="M10.1292 2.39502C9.48743 2.75944 8.86297 3.15858 8.25586 3.59242C14.6219 6.12604 20.3287 9.85706 25.2376 14.7681C30.1466 19.6792 33.9107 25.4753 36.4085 31.7052C36.8248 31.1152 37.2238 30.5078 37.588 29.8657C35.0035 23.844 31.2914 18.2735 26.4866 13.484C21.7164 8.71172 16.2177 5.01541 10.1292 2.39502Z" fill="currentColor"/>
  </g>
  <defs>
    <clipPath id="ccb-clip">
      <rect width="40" height="40" fill="white"/>
    </clipPath>
  </defs>
</svg>
');

// ==========================
// INCLUDES
// ==========================

require_once CCB_PATH . 'includes/ccb-settings.php';
require_once CCB_PATH . 'includes/ccb-consent-api.php';

// ==========================
// TEXTDOMAIN
// ==========================

add_action('init', function () {
  load_plugin_textdomain(
    'cookie-consent-banner',
    false,
    dirname(plugin_basename(__FILE__)) . '/languages'
  );
});

// ==========================
// SETTINGS LINK
// ==========================

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function (array $links): array {
  $settings_link = sprintf(
    '<a href="%s">%s</a>',
    esc_url(admin_url('options-general.php?page=ccb-settings')),
    __('Settings', 'cookie-consent-banner')
  );
  array_unshift($links, $settings_link);
  return $links;
});

// ==========================
// PRECONNECT
// ==========================

add_action('wp_head', function () {
  if (!ccb_get('gtm_id')) return;
  echo '<link rel="preconnect" href="https://www.googletagmanager.com">' . "\n";
  echo '<link rel="dns-prefetch" href="https://www.googletagmanager.com">' . "\n";
}, 1);

// ==========================
// ENQUEUE
// ==========================

add_action('wp_enqueue_scripts', function () {
  wp_enqueue_style(
    'ccb-style',
    CCB_URL . 'assets/cookie.css',
    [],
    '2.5.0'
  );

  $max_width       = esc_attr(ccb_get('banner_max_width') ?: '64ch');
  $banner_position = ccb_get('banner_position') ?: 'left';

  $position_css = match ($banner_position) {
    'center' => 'left: 50%; transform: translateX(-50%); right: auto;',
    'right'  => 'left: auto; right: var(--ccb-gutter, 1rem);',
    default  => 'left: var(--ccb-gutter, 1rem); right: auto;',
  };

  wp_add_inline_style('ccb-style', "
        :root { --ccb-banner-max-width: {$max_width}; }
        .cookie-banner { {$position_css} }
    ");

  wp_enqueue_script(
    'ccb-script',
    CCB_URL . 'assets/cookie.js',
    [],
    '2.5.0',
    true
  );

  add_filter('script_loader_tag', function (string $tag, string $handle): string {
    if ($handle === 'ccb-script') {
      return str_replace(' src=', ' defer src=', $tag);
    }
    return $tag;
  }, 10, 2);

  wp_localize_script('ccb-script', 'cookieConfig', [
    'gtmId'          => ccb_get('gtm_id'),
    'expiryAccepted' => (int) ccb_get('expiry_accepted'),
    'expiryRejected' => (int) ccb_get('expiry_rejected'),
  ]);
});

// ==========================
// RENDER BANNER
// ==========================

add_action('wp_footer', function () {
  include CCB_PATH . 'templates/banner.php';
});
