/**
 * Testy modułów Storage / Toggles / buildPreferences / loadToToggles
 * z assets/cookie.js — parsowanie/serializacja wartości zgody jako osobne
 * cookies HTTP, sprawdzanie czy kategoria jest zaakceptowana, budowanie
 * obiektu preferencji na podstawie DOM.
 *
 * cookie.js to IIFE, która na końcu pliku (patrz ostatni blok przed `})();`)
 * eksportuje swoje wewnętrzne moduły przez `module.exports`, TYLKO gdy
 * "module" istnieje (czyli w Node/Jest — w przeglądarce ten blok jest
 * nieszkodliwym no-opem). require() wykonuje też init() z dołu pliku (bo
 * document.readyState w jsdom jest już "complete"), dlatego DOM banera musi
 * być gotowy PRZED require(), inaczej init() nie znajdzie elementów.
 */

function clearAllCookies() {
	document.cookie.split(";").forEach(part => {
		const name = part.split("=")[0].trim();
		if (!name) return;
		document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
	});
}

function setupBannerDom() {
	document.body.innerHTML = `
		<div id="cookie-banner" class="cookie-banner display--none" role="dialog">
			<div class="cookie-banner__inner">
				<div id="cookie-banner-main" class="cookie-banner__main">
					<button id="accept-all-cookies-btn" type="button">Accept all</button>
					<button id="deny-all-cookies-btn" type="button">Reject all</button>
					<button id="customize-cookie-preferences-btn" type="button">Customize settings</button>
				</div>
				<div id="cookie-options" class="cookie-options display--none">
					<button class="cookie-toggle" type="button" role="switch" aria-checked="true" data-cookie-type="functional"></button>
					<button class="cookie-toggle" type="button" role="switch" aria-checked="false" data-cookie-type="analytics"></button>
					<button class="cookie-toggle" type="button" role="switch" aria-checked="false" data-cookie-type="marketing"></button>
					<div class="cookie-options__footer">
						<button id="save-cookie-preferences-btn" type="button">Save settings</button>
					</div>
				</div>
			</div>
		</div>
		<button id="cookie-banner-toggle-btn" class="cookie-banner-toggle display--none" type="button" aria-expanded="false"></button>
	`;
}

function loadCookieJsFresh() {
	jest.resetModules();
	return require("../assets/cookie.js");
}

beforeEach(() => {
	clearAllCookies();
	delete window.cookieConfig;
});

describe("Storage", () => {
	test("getAll() zwraca null dla każdej kategorii, gdy brak zapisanych cookies (świeża wizyta)", () => {
		setupBannerDom();
		const { Storage } = loadCookieJsFresh();
		expect(Storage.getAll()).toEqual({
			functional: null,
			analytics: null,
			marketing: null,
		});
	});

	test("hasConsent() to false, dopóki żadna kategoria nie ma zapisanej wartości", () => {
		setupBannerDom();
		const { Storage } = loadCookieJsFresh();
		expect(Storage.hasConsent()).toBe(false);
	});

	test("save() serializuje preferencje jako osobne cookies HTTP, odczytywalne z powrotem przez getAll()", () => {
		setupBannerDom();
		const { Storage } = loadCookieJsFresh();
		Storage.save({ functional: true, analytics: false, marketing: true });
		expect(Storage.getAll()).toEqual({
			functional: true,
			analytics: false,
			marketing: true,
		});
	});

	test("save() zapisuje TYLKO klucze przekazane w preferencjach, resztę zostawia nietkniętą (null)", () => {
		setupBannerDom();
		const { Storage } = loadCookieJsFresh();
		Storage.save({ functional: true });
		const all = Storage.getAll();
		expect(all.functional).toBe(true);
		expect(all.analytics).toBeNull();
		expect(all.marketing).toBeNull();
	});

	test("hasConsent() to true nawet gdy WSZYSTKIE kategorie odrzucone — liczy się fakt wyboru, nie jego wynik", () => {
		setupBannerDom();
		const { Storage } = loadCookieJsFresh();
		Storage.save({ functional: true, analytics: false, marketing: false });
		expect(Storage.hasConsent()).toBe(true);
	});

	test("kolejne save() nadpisuje tylko przekazane kategorie, nie resetuje pozostałych", () => {
		setupBannerDom();
		const { Storage } = loadCookieJsFresh();
		Storage.save({ functional: true, analytics: true, marketing: false });
		Storage.save({ analytics: false });
		expect(Storage.getAll()).toEqual({
			functional: true,
			analytics: false,
			marketing: false,
		});
	});
});

describe("Toggles", () => {
	test("getAll() odczytuje bieżący stan aria-checked z DOM (domyślny stan HTML: functional=true, reszta false)", () => {
		setupBannerDom();
		const { Toggles } = loadCookieJsFresh();
		expect(Toggles.getAll()).toEqual({
			functional: true,
			analytics: false,
			marketing: false,
		});
	});

	test("setAll() nadpisuje aria-checked TYLKO dla przekazanych typów", () => {
		setupBannerDom();
		const { Toggles } = loadCookieJsFresh();
		Toggles.setAll({ analytics: true });
		expect(Toggles.getAll()).toEqual({
			functional: true,
			analytics: true,
			marketing: false,
		});
	});

	test("init() (odpalone automatycznie przy require) podpina listener klikania, który przełącza aria-checked", () => {
		setupBannerDom();
		loadCookieJsFresh();
		const btn = document.querySelector('[data-cookie-type="analytics"]');
		btn.click();
		expect(btn.getAttribute("aria-checked")).toBe("true");
		btn.click();
		expect(btn.getAttribute("aria-checked")).toBe("false");
	});
});

describe("buildPreferences()", () => {
	test("zwraca preferencje TYLKO dla typów faktycznie obecnych w DOM (renderowanych przez ccb_get('show_analytics'/'show_marketing'))", () => {
		setupBannerDom();
		const { buildPreferences } = loadCookieJsFresh();
		expect(buildPreferences(true)).toEqual({
			functional: true,
			analytics: true,
			marketing: true,
		});
		expect(buildPreferences(false)).toEqual({
			functional: false,
			analytics: false,
			marketing: false,
		});
	});

	test("gdy sekcja marketingu nie jest wyrenderowana w DOM (show_marketing wyłączone), buildPreferences() jej nie dodaje", () => {
		setupBannerDom();
		document.querySelector('[data-cookie-type="marketing"]').remove();
		const { buildPreferences } = loadCookieJsFresh();
		expect(buildPreferences(true)).toEqual({
			functional: true,
			analytics: true,
		});
	});
});

describe("loadToToggles()", () => {
	test("kopiuje zapisany stan z cookies do toggles, ale NIE nadpisuje kategorii bez zapisanej wartości", () => {
		setupBannerDom();
		const { Storage, Toggles, loadToToggles } = loadCookieJsFresh();

		Storage.save({ analytics: true }); // tylko analytics ma zapisaną wartość
		// zmieniamy widoczny stan innych togglów, żeby było widać że loadToToggles ich NIE dotyka
		Toggles.setAll({ functional: false, marketing: true });

		loadToToggles();

		expect(Toggles.getAll()).toEqual({
			functional: false, // bez zmian — brak zapisanego cookie dla tej kategorii
			analytics: true, // nadpisane zapisaną wartością
			marketing: true, // bez zmian — brak zapisanego cookie
		});
	});
});
