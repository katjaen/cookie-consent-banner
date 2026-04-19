# Cookie Consent Banner

A lightweight, accessible, GDPR-compliant cookie consent plugin for WordPress — built without external dependencies, bloat, or tracking of its own.

> Built for real projects. No jQuery, no external scripts, no upsells.

---

## Why this plugin

Most cookie consent plugins are either too heavy (loading external scripts, fonts, tracking SDKs before consent) or too simple (no accessibility, no real GDPR compliance). This one is different:

- **~5kb JS**, loaded deferred in footer — does not block rendering
- **Zero external dependencies** — no jQuery, no lodash, no third-party APIs
- **Fully accessible** — keyboard navigation, screen reader support, focus management (see [Accessibility](#accessibility))
- **GDPR-compliant** — consent stored as separate HTTP cookies per type, GTM loads only after consent, functional cookies default to `true` (legitimate interest), analytics/marketing default to `false`
- **GTM-based** — GA4, Facebook Pixel, Hotjar and any other tool configured in GTM loads automatically after consent, no extra code needed
- **WP Consent API** integration — works with Embed Privacy and other consent-aware plugins
- **Modular PHP** — settings, integrations, and template are separate files

---

## Features

- Cookie banner with accept all / reject all / customize options
- Toggle switches (`button[role="switch"]`) — no hidden checkboxes
- Separate cookie sections: Technical, Functional, Analytics, Marketing
- Analytics and Marketing sections are optional — rendered conditionally via PHP
- GTM loaded on consent, with `preconnect` hint when GTM ID is set
- YouTube `nocookie` domain substitution (optional, zero JS cost)
- WP Consent API integration — signals consent to other plugins
- Full settings page in WP Admin → Settings → Cookie Banner
- Dark mode support via CSS custom properties
- Works without Automatic.css — full button fallbacks included

---

## Accessibility

Cookie consent banners are often an accessibility afterthought — missing keyboard support, broken focus management, and toggle switches that screen readers can't interpret. This plugin treats accessibility as a first-class requirement.

### Dialog semantics

The banner uses `role="dialog"` with `aria-modal="true"` and `aria-labelledby` pointing to the visible heading. This tells screen readers they are inside a modal dialog and should treat it accordingly.

```html
<div
	role="dialog"
	aria-modal="true"
	aria-labelledby="cookie-banner-heading"></div>
```

### Focus trap

When the banner is open, keyboard focus is trapped inside it — Tab and Shift+Tab cycle through focusable elements within the banner only. Focus does not escape to the page behind. When the banner closes, focus moves to the floating toggle button so keyboard users always know where they are.

### Toggle switches

Toggle switches are implemented as `<button role="switch">` with `aria-checked="true|false"` — the standard ARIA pattern for binary on/off controls. This is correctly announced by screen readers as a switch with its current state.

This is a deliberate departure from the common pattern of a visually hidden `<input type="checkbox">` with a CSS slider overlay. That pattern works visually but creates unnecessary DOM complexity and inconsistent screen reader announcements across browsers.

```html
<button role="switch" aria-checked="false" data-cookie-type="analytics">
	<span class="cookie-toggle__thumb"></span>
	<span class="sr-only">Enable or disable analytics cookies</span>
</button>
```

### Keyboard support

- **Tab / Shift+Tab** — navigate between all interactive elements
- **Space / Enter** — activate buttons and toggle switches
- **Escape** — close the banner (only if the user has previously made a choice)

### Focus visibility

All interactive elements have visible `:focus-visible` styles using CSS custom properties that inherit from the active theme. No focus styles are suppressed.

```css
.cookie-toggle:focus-visible {
	outline: var(--ccb-focus-width) solid var(--ccb-focus-color);
	outline-offset: var(--ccb-focus-offset);
}
```

### Screen reader text

Descriptive labels that are visually redundant but meaningful for screen readers are included via `.sr-only` — for example, each toggle button carries a full description of what it controls.

### Floating toggle button

The floating button that reopens the banner has `aria-label`, `aria-haspopup="dialog"`, `aria-expanded`, and `aria-controls` — so screen reader users know it opens a dialog and can track its state.

### SVG icon

The decorative SVG fingerprint icon carries `aria-hidden="true"` and `focusable="false"` to prevent it from appearing in the accessibility tree or receiving focus in older browsers.

---

## Settings

All options available in **WP Admin → Settings → Cookie Banner**:

| Setting                | Description                                                        |
| ---------------------- | ------------------------------------------------------------------ |
| GTM ID                 | Google Tag Manager ID (format: `GTM-XXXXXXX`)                      |
| Show analytics         | Toggle analytics section in banner (auto-enabled when GTM ID set)  |
| Show marketing         | Toggle marketing section in banner                                 |
| YouTube nocookie       | Replace `youtube.com` with `youtube-nocookie.com` in embeds        |
| Banner description     | Main description text (supports `<a>`, `<strong>`, `<em>`, `<br>`) |
| Section descriptions   | Individual descriptions for each cookie type                       |
| Expiry after accept    | Days until consent cookie expires (default: 90)                    |
| Expiry after reject    | Days until consent cookie expires (default: 1)                     |
| Banner position        | Left / Center / Right (horizontal only)                            |
| Banner max width       | CSS value: `ch`, `px`, `rem`, `em`, `vw`, `%` (default: `64ch`)    |
| Toggle button position | Left or right corner of screen                                     |

---

## Installation

1. Download or clone this repository
2. Place the `cookie-consent-banner` folder in `/wp-content/plugins/`
3. Activate in **WP Admin → Plugins**
4. Go to **Settings → Cookie Banner** and configure

### File structure

```
cookie-consent-banner/
├── cookie-consent-banner.php   # Main plugin file
├── includes/
│   ├── ccb-settings.php        # Settings page
│   └── ccb-consent-api.php     # WP Consent API + YouTube nocookie
├── assets/
│   ├── cookie.js               # Banner logic (modular, deferred)
│   └── cookie.css              # Styles with CSS custom properties
└── templates/
    └── banner.php              # Banner HTML template
```

---

## CSS Custom Properties

The plugin uses its own design tokens that inherit from your theme when available. Override any of these in your theme CSS:

```css
:root {
    --ccb-primary           /* accent color — defaults to --primary or --accent */
    --ccb-text              /* text color */
    --ccb-bg                /* banner background */
    --ccb-border            /* border color */
    --ccb-toggle-off        /* toggle inactive state */
    --ccb-radius            /* border radius */
    --ccb-gap               /* spacing */
    --ccb-banner-max-width  /* max width of banner content */
}
```

Works with [Automatic.css](https://automaticcss.com/) out of the box — all tokens fall back to ACSS variables when available.

---

## WP Consent API

If [WP Consent API](https://wordpress.org/plugins/wp-consent-api/) is installed, this plugin will:

- Register itself as a consent manager
- Signal consent levels to other plugins via `wp_set_consent()`
- Respond to `wp_has_consent()` calls from other plugins

This enables plugins like [Embed Privacy](https://wordpress.org/plugins/embed-privacy/) to automatically block YouTube, Instagram, and Facebook embeds until the user consents.

**Category mapping:**

| This plugin | WP Consent API |
| ----------- | -------------- |
| functional  | functional     |
| analytics   | statistics     |
| marketing   | marketing      |

---

## GDPR notes

- Consent is stored as separate HTTP cookies per type (`cookieConsent-functional`, `cookieConsent-analytics`, `cookieConsent-marketing`)
- GTM is loaded only after analytics or marketing consent
- All tools configured inside GTM (GA4, Facebook Pixel, Hotjar etc.) inherit this consent gate automatically — no extra code needed
- GA/tracking cookies are cleared on rejection
- Functional cookies default to `true` (legitimate interest basis)
- Analytics and marketing default to `false` (require active consent)
- Consent expires after 90 days (accepted) or 1 day (rejected) — configurable

> This plugin is a technical tool. It does not constitute legal advice. Consult a lawyer for full GDPR compliance assessment of your site.

---

## Requirements

- WordPress 6.0+
- PHP 8.1+ (uses `match` expression)
- No jQuery required

---

## Internationalization (i18n)

All static UI strings (button labels, headings, ARIA labels) are wrapped in `__()` and ready for translation via `.po`/`.mo` files. The plugin includes a `languages/` directory and registers its text domain on `plugins_loaded`.

A `.pot` template file is included in `languages/cookie-consent-banner.pot` — you don't need to scan the source code yourself.

**Included translations:**

| Language | Locale  | Status     |
| -------- | ------- | ---------- |
| Polish   | `pl_PL` | ✓ Complete |

**To add a translation:**

1. Open `languages/cookie-consent-banner.pot` in [Poedit](https://poedit.net/) or [Loco Translate](https://wordpress.org/plugins/loco-translate/)
2. Translate the strings
3. Save as `cookie-consent-banner-{locale}.po` and compile to `.mo` (e.g. `cookie-consent-banner-de_DE.po`)
4. Place both files in the `languages/` folder

**Note:** Dynamic texts configured in the settings page (banner description, cookie section descriptions) are stored in the database as entered by the admin. These are not translatable via `.po` files. For multilingual sites, manage these texts through your multilingual plugin (WPML, Polylang) or enter them directly in the language of the site.

---

## License

GPL-2.0-or-later — the standard WordPress plugin license.

---

_Built with care for clean code, accessibility, and real GDPR compliance._
