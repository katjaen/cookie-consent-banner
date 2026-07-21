/**
 * Testy przepływu banera zgody z assets/cookie.js — dwie warstwy:
 *
 *  1) wywołania eksportowanych handlerów WPROST (handleAcceptAll,
 *     handleDenyAll, handleCustomize, handleSave) — sprawdzają samą logikę
 *     bez pośrednictwa symulowanego kliknięcia.
 *  2) prawdziwe .click() na przyciskach banera — dokładnie ten sam wzorzec
 *     co tests/admin.copyButton.test.js w katjaen-global-fields: budujemy
 *     fałszywy DOM, require()-ujemy cały plik (odpala się init() i realnie
 *     podpina listenery), klikamy i sprawdzamy efekt uboczny w DOM/cookies.
 *     Dowodzi, że listenery z init() faktycznie wołają te same handlery.
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

function isHidden(id) {
	return document.getElementById(id).classList.contains("display--none");
}

beforeEach(() => {
	clearAllCookies();
	delete window.cookieConfig;
	setupBannerDom();
});

describe("handlery wywołane wprost (handleAcceptAll/handleDenyAll/handleCustomize/handleSave)", () => {
	test("handleAcceptAll() zapisuje zgodę na wszystkie kategorie i chowa baner, pokazując pływający toggle", () => {
		const mod = loadCookieJsFresh();
		mod.handleAcceptAll();

		expect(mod.Storage.getAll()).toEqual({
			functional: true,
			analytics: true,
			marketing: true,
		});
		expect(isHidden("cookie-banner")).toBe(true);
		expect(isHidden("cookie-banner-toggle-btn")).toBe(false);
	});

	test("handleDenyAll() zostawia functional=true (zawsze aktywne z założenia), resztę odrzuca", () => {
		const mod = loadCookieJsFresh();
		mod.handleDenyAll();

		expect(mod.Storage.getAll()).toEqual({
			functional: true,
			analytics: false,
			marketing: false,
		});
		expect(isHidden("cookie-banner")).toBe(true);
	});

	test("handleCustomize() (pierwsza wizyta) pokazuje panel opcji zamiast głównego widoku, nie zapisuje jeszcze niczego", () => {
		const mod = loadCookieJsFresh();
		mod.handleCustomize();

		expect(isHidden("cookie-options")).toBe(false);
		expect(isHidden("cookie-banner-main")).toBe(true);
		expect(mod.Storage.hasConsent()).toBe(false);
	});

	test("handleSave() zapisuje DOKŁADNIE to, co widać w toggles w momencie kliknięcia", () => {
		const mod = loadCookieJsFresh();
		document
			.querySelector('[data-cookie-type="analytics"]')
			.setAttribute("aria-checked", "true");

		mod.handleSave();

		expect(mod.Storage.getAll()).toEqual({
			functional: true,
			analytics: true,
			marketing: false,
		});
		expect(isHidden("cookie-banner")).toBe(true);
	});

	test("handleCustomize() PO wcześniejszej decyzji przywraca zapisany stan do toggles (powrót użytkownika)", () => {
		const mod = loadCookieJsFresh();
		mod.Storage.save({ functional: true, analytics: true, marketing: false });
		// symulujemy że widoczny stan toggli jest inny niż zapisany (np. świeży DOM)
		document
			.querySelector('[data-cookie-type="analytics"]')
			.setAttribute("aria-checked", "false");

		mod.handleCustomize();

		expect(
			document
				.querySelector('[data-cookie-type="analytics"]')
				.getAttribute("aria-checked"),
		).toBe("true");
		expect(isHidden("cookie-options")).toBe(false);
	});
});

describe("prawdziwe kliknięcia na przyciskach banera (listenery podpięte przez init())", () => {
	test("kliknięcie 'Accept all' zapisuje cookies zgody i chowa baner", () => {
		loadCookieJsFresh();

		document.getElementById("accept-all-cookies-btn").click();

		expect(document.cookie).toContain("cookieConsent-functional=true");
		expect(document.cookie).toContain("cookieConsent-analytics=true");
		expect(document.cookie).toContain("cookieConsent-marketing=true");
		expect(isHidden("cookie-banner")).toBe(true);
	});

	test("kliknięcie 'Reject all' zapisuje cookies z analytics/marketing odrzuconymi", () => {
		loadCookieJsFresh();

		document.getElementById("deny-all-cookies-btn").click();

		expect(document.cookie).toContain("cookieConsent-functional=true");
		expect(document.cookie).toContain("cookieConsent-analytics=false");
		expect(document.cookie).toContain("cookieConsent-marketing=false");
	});

	test("kliknięcie przełącznika analytics + kliknięcie 'Save settings' zapisuje ustawiony stan", () => {
		loadCookieJsFresh();

		document.querySelector('[data-cookie-type="analytics"]').click();
		document.getElementById("save-cookie-preferences-btn").click();

		expect(document.cookie).toContain("cookieConsent-analytics=true");
		expect(isHidden("cookie-banner")).toBe(true);
	});

	test("kliknięcie 'Customize settings' pokazuje panel opcji bez zapisywania niczego", () => {
		loadCookieJsFresh();

		document.getElementById("customize-cookie-preferences-btn").click();

		expect(isHidden("cookie-options")).toBe(false);
		expect(document.cookie).not.toContain("cookieConsent-analytics=");
	});

	test("kliknięcie pływającego przycisku toggle PO wcześniejszej zgodzie otwiera z powrotem panel opcji", () => {
		loadCookieJsFresh();

		document.getElementById("accept-all-cookies-btn").click(); // ustala zgodę + chowa baner
		expect(isHidden("cookie-banner")).toBe(true);

		document.getElementById("cookie-banner-toggle-btn").click();

		expect(isHidden("cookie-banner")).toBe(false);
		expect(isHidden("cookie-options")).toBe(false);
	});
});

describe("klawisz Escape", () => {
	test("Escape NIE chowa banera, dopóki użytkownik nie podjął jeszcze żadnej decyzji", () => {
		loadCookieJsFresh();
		document.getElementById("cookie-banner").classList.remove("display--none");

		document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));

		expect(isHidden("cookie-banner")).toBe(false);
	});

	test("Escape chowa baner, gdy użytkownik już wcześniej wybrał (hasConsent=true)", () => {
		loadCookieJsFresh();
		document.getElementById("accept-all-cookies-btn").click(); // ustala zgodę
		document.getElementById("cookie-banner").classList.remove("display--none"); // pokaż baner z powrotem (np. przez toggle), żeby wyizolować efekt Escape

		document.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape" }));

		expect(isHidden("cookie-banner")).toBe(true);
	});
});
