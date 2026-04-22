/**
 * Cookie Consent Banner – cookie.js  v2.5.0
 *
 * Moduły:
 *  UI       – stany banera (BANNER / OPTIONS / HIDDEN)
 *  Storage  – zapis/odczyt zgód jako osobne cookies HTTP
 *  Tracking – GTM i czyszczenie ciasteczek śledzących
 *  Toggles  – przełączniki role="switch"
 *  Focus    – trapFocus (a11y)
 */

/* global window, document */

(function () {
	"use strict";

	// ==========================
	// KONFIGURACJA
	// ==========================

	const CONFIG = window.cookieConfig || {};

	const COOKIE_TYPES = {
		functional: "cookieConsent-functional",
		analytics: "cookieConsent-analytics",
		marketing: "cookieConsent-marketing",
	};

	const TRACKING_TYPES = ["analytics", "marketing"];

	// Czas ważności z PHP (wp_localize_script) lub wartości domyślne
	const EXPIRY = {
		accepted: CONFIG.expiryAccepted || 90,
		rejected: CONFIG.expiryRejected || 1,
	};

	// ==========================
	// ELEMENTY DOM
	// ==========================

	const el = {
		banner: () => document.getElementById("cookie-banner"),
		main: () => document.getElementById("cookie-banner-main"),
		options: () => document.getElementById("cookie-options"),
		toggleBtn: () => document.getElementById("cookie-banner-toggle-btn"),
		acceptAll: () => document.getElementById("accept-all-cookies-btn"),
		denyAll: () => document.getElementById("deny-all-cookies-btn"),
		customize: () =>
			document.getElementById("customize-cookie-preferences-btn"),
		save: () => document.getElementById("save-cookie-preferences-btn"),
		toggles: () =>
			document.querySelectorAll(".cookie-toggle[data-cookie-type]"),
	};

	// ==========================
	// HELPERS
	// ==========================

	function show(...els) {
		els.forEach(e => e?.classList.remove("display--none"));
	}
	function hide(...els) {
		els.forEach(e => e?.classList.add("display--none"));
	}

	// ==========================
	// MODUŁ: UI
	// ==========================

	const UI = {
		BANNER: "BANNER",
		OPTIONS: "OPTIONS",
		HIDDEN: "HIDDEN",

		set(state) {
			const banner = el.banner();
			const main = el.main();
			const options = el.options();
			const toggleBtn = el.toggleBtn();

			switch (state) {
				// Pierwsza wizyta: opis + 3 przyciski, opcje ukryte
				case UI.BANNER:
					show(banner, main);
					hide(options, toggleBtn);
					toggleBtn?.setAttribute("aria-expanded", "false");
					setTimeout(() => {
						Focus.trap(banner);
						el.acceptAll()?.focus();
					}, 100);
					break;

				// Dostosuj / powrót: opcje widoczne, opis + przyciski ukryte
				case UI.OPTIONS:
					show(banner, options);
					hide(main, toggleBtn);
					toggleBtn?.setAttribute("aria-expanded", "false");
					setTimeout(() => {
						Focus.trap(banner);
						banner?.querySelector(".cookie-toggle")?.focus();
					}, 50);
					break;

				// Po zapisaniu: baner ukryty, toggle widoczny
				case UI.HIDDEN:
					hide(banner);
					show(toggleBtn);
					toggleBtn?.setAttribute("aria-expanded", "false");
					Focus.release(banner);
					setTimeout(() => el.toggleBtn()?.focus(), 50);
					break;
			}
		},
	};

	// ==========================
	// MODUŁ: STORAGE
	// ==========================

	const Storage = {
		getAll() {
			const result = {};
			for (const type in COOKIE_TYPES) {
				result[type] = this._get(COOKIE_TYPES[type]);
			}
			return result;
		},

		save(preferences) {
			const anyAccepted = Object.values(preferences).includes(true);
			const days = anyAccepted ? EXPIRY.accepted : EXPIRY.rejected;
			for (const type in COOKIE_TYPES) {
				if (type in preferences) {
					this._set(COOKIE_TYPES[type], preferences[type] ?? false, days);
				}
			}
		},

		hasConsent() {
			return Object.values(this.getAll()).some(v => v !== null);
		},

		_get(name) {
			const match = document.cookie.match(
				new RegExp("(?:^| )" + name + "=([^;]+)"),
			);
			if (!match) return null;
			return match[1] === "true";
		},

		_set(name, value, days) {
			try {
				const expires = new Date();
				expires.setDate(expires.getDate() + days);
				document.cookie = `${name}=${value}; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
			} catch (e) {
				console.warn("[CCB] Nie udało się ustawić cookie:", name, e);
			}
		},
	};

	// ==========================
	// MODUŁ: TRACKING
	// ==========================

	const Tracking = {
		enable(preferences) {
			const gtmId = CONFIG.gtmId;
			const allowed = TRACKING_TYPES.some(t => preferences[t]);

			if (allowed && gtmId) this._loadGTM(gtmId);

			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({
				event: "cookieConsentUpdate",
				cookieAnalytics: preferences.analytics || false,
				cookieMarketing: preferences.marketing || false,
				cookieFunctional: preferences.functional || false,
			});
		},

		disable() {
			this._clearTrackingCookies();
		},

		_loadGTM(id) {
			if (document.getElementById("gtm-script")) return;

			window.dataLayer = window.dataLayer || [];
			window.dataLayer.push({
				"gtm.start": new Date().getTime(),
				event: "gtm.js",
			});

			const script = document.createElement("script");
			script.id = "gtm-script";
			script.async = true;
			script.src = `https://www.googletagmanager.com/gtm.js?id=${id}`;
			document.head.appendChild(script);

			if (!document.getElementById("gtm-noscript")) {
				const ns = document.createElement("iframe");
				ns.id = "gtm-noscript";
				ns.src = `https://www.googletagmanager.com/ns.html?id=${id}`;
				ns.height = "0";
				ns.width = "0";
				ns.style.cssText = "display:none;visibility:hidden";
				document.body.prepend(ns);
			}
		},

		_clearTrackingCookies() {
			const staticList = ["_ga", "_gid", "_gat", "_fbp", "_gcl_au"];
			const ga4Cookies = document.cookie
				.split("; ")
				.map(c => c.split("=")[0])
				.filter(n => n.startsWith("_ga_"));

			const all = [...staticList, ...ga4Cookies];
			const domains = location.hostname
				.split(".")
				.map((_, i, a) => "." + a.slice(i).join("."))
				.slice(0, -1);

			all.forEach(name => {
				domains.forEach(d => {
					document.cookie = `${name}=; Max-Age=0; path=/; domain=${d}; SameSite=Lax`;
				});
				document.cookie = `${name}=; Max-Age=0; path=/;`;
			});
		},
	};

	// ==========================
	// MODUŁ: TOGGLES
	// ==========================

	const Toggles = {
		init() {
			el.toggles().forEach(btn => {
				btn.addEventListener("click", () => {
					const current = btn.getAttribute("aria-checked") === "true";
					btn.setAttribute("aria-checked", current ? "false" : "true");
				});
			});
		},

		setAll(preferences) {
			el.toggles().forEach(btn => {
				const type = btn.dataset.cookieType;
				if (type in preferences) {
					btn.setAttribute(
						"aria-checked",
						preferences[type] ? "true" : "false",
					);
				}
			});
		},

		getAll() {
			const result = {};
			el.toggles().forEach(btn => {
				result[btn.dataset.cookieType] =
					btn.getAttribute("aria-checked") === "true";
			});
			return result;
		},
	};

	// ==========================
	// MODUŁ: FOCUS (a11y)
	// ==========================

	const Focus = {
		_handlers: new WeakMap(),

		FOCUSABLE: [
			"a[href]",
			"button:not([disabled])",
			"input:not([disabled]):not([tabindex='-1'])",
			"select:not([disabled])",
			"textarea:not([disabled])",
			"[tabindex]:not([tabindex='-1'])",
		].join(", "),

		trap(modal) {
			if (!modal) return;
			this.release(modal);

			const getVisible = () =>
				Array.from(modal.querySelectorAll(this.FOCUSABLE)).filter(
					el => el.offsetParent !== null && !el.closest(".display--none"),
				);

			const handler = e => {
				if (e.key !== "Tab") return;
				const visible = getVisible();
				if (!visible.length) return;
				const first = visible[0];
				const last = visible[visible.length - 1];

				if (e.shiftKey && document.activeElement === first) {
					e.preventDefault();
					last.focus();
				} else if (!e.shiftKey && document.activeElement === last) {
					e.preventDefault();
					first.focus();
				}
			};

			modal.addEventListener("keydown", handler);
			this._handlers.set(modal, handler);
		},

		release(modal) {
			if (!modal) return;
			const handler = this._handlers.get(modal);
			if (handler) {
				modal.removeEventListener("keydown", handler);
				this._handlers.delete(modal);
			}
		},
	};

	// ==========================
	// LOGIKA ZAPISU
	// ==========================

	function applyAndClose(preferences) {
		Storage.save(preferences);
		Toggles.setAll(preferences);
		const anyTracking = TRACKING_TYPES.some(t => preferences[t]);
		anyTracking ? Tracking.enable(preferences) : Tracking.disable();
		UI.set(UI.HIDDEN);
	}

	/** Buduje preferencje tylko dla typów obecnych w DOM */
	function buildPreferences(value) {
		const prefs = {};
		el.toggles().forEach(btn => {
			prefs[btn.dataset.cookieType] = value;
		});
		return prefs;
	}

	/** Ładuje zapisany stan z cookies do toggles */
	function loadToToggles() {
		const status = Storage.getAll();
		Toggles.setAll(
			Object.keys(COOKIE_TYPES).reduce((acc, t) => {
				// jeśli cookie nie istnieje (null) – użyj domyślnego stanu z HTML
				// czyli nie nadpisuj, zostaw co jest w aria-checked
				if (status[t] !== null) {
					acc[t] = status[t] === true;
				}
				return acc;
			}, {}),
		);
	}

	// ==========================
	// HANDLERY
	// ==========================

	function handleAcceptAll() {
		applyAndClose(buildPreferences(true));
	}
	function handleDenyAll() {
		const prefs = {};
		el.toggles().forEach(btn => {
			const type = btn.dataset.cookieType;
			// funkcjonalne zostają true, reszta false
			prefs[type] = !TRACKING_TYPES.includes(type);
		});
		applyAndClose(prefs);
	}
	function handleSave() {
		applyAndClose(Toggles.getAll());
	}

	function handleCustomize() {
		if (Storage.hasConsent()) {
			loadToToggles(); // wróć do zapisanych – powrót użytkownika
		}
		// przy pierwszej wizycie – zostaw stan z HTML (funkcjonalne=true z aria-checked)
		UI.set(UI.OPTIONS);
	}

	/** Powrót przez toggle button → od razu OPTIONS */
	function handleToggleBtn() {
		loadToToggles();
		UI.set(UI.OPTIONS);
	}

	// ==========================
	// INIT
	// ==========================

	function init() {
		Toggles.init();

		el.acceptAll()?.addEventListener("click", handleAcceptAll);
		el.denyAll()?.addEventListener("click", handleDenyAll);
		el.customize()?.addEventListener("click", handleCustomize);
		el.save()?.addEventListener("click", handleSave);
		el.toggleBtn()?.addEventListener("click", handleToggleBtn);

		// Escape zamyka baner jeśli użytkownik już raz wybrał
		document.addEventListener("keydown", e => {
			if (e.key !== "Escape") return;
			const banner = el.banner();
			if (
				banner &&
				!banner.classList.contains("display--none") &&
				Storage.hasConsent()
			) {
				UI.set(UI.HIDDEN);
			}
		});

		// Stan startowy
		if (!Storage.hasConsent()) {
			UI.set(UI.BANNER);
		} else {
			const status = Storage.getAll();
			const prefs = Object.keys(COOKIE_TYPES).reduce((acc, t) => {
				acc[t] = status[t] === true;
				return acc;
			}, {});
			if (TRACKING_TYPES.some(t => prefs[t])) Tracking.enable(prefs);
			UI.set(UI.HIDDEN);
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", init);
	} else {
		init();
	}
})();
