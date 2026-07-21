/**
 * Testy modułu Tracking z assets/cookie.js — Google Consent Mode, warunkowe
 * wstrzykiwanie GTM i czyszczenie ciasteczek śledzących po odrzuceniu zgody.
 *
 * Uwaga: rzeczywiste wywołanie sieciowe (pobranie gtm.js) nigdy się nie
 * dzieje w jsdom — sprawdzamy tylko że kod tworzy poprawny <script>/<iframe>
 * z docelowym src, tak jak zrobiłby to prawdziwy DOM przed wysłaniem żądania.
 */

function clearAllCookies() {
	document.cookie.split(";").forEach(part => {
		const name = part.split("=")[0].trim();
		if (!name) return;
		document.cookie = `${name}=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
	});
}

function loadCookieJsFresh() {
	jest.resetModules();
	return require("../assets/cookie.js");
}

beforeEach(() => {
	clearAllCookies();
	document.body.innerHTML = "";
	document.head.querySelectorAll("#gtm-script").forEach(n => n.remove());
	delete window.cookieConfig;
	delete window.gtag;
	delete window.dataLayer;
});

describe("Tracking.enable()", () => {
	test("z gtmId skonfigurowanym i choć jedną zaakceptowaną kategorią trackingową -> wstrzykuje skrypt i noscript GTM", () => {
		window.cookieConfig = { gtmId: "GTM-ABC1234" };
		const { Tracking } = loadCookieJsFresh();

		Tracking.enable({ functional: true, analytics: true, marketing: false });

		const script = document.getElementById("gtm-script");
		expect(script).not.toBeNull();
		expect(script.src).toContain("googletagmanager.com/gtm.js?id=GTM-ABC1234");
		expect(document.getElementById("gtm-noscript")).not.toBeNull();
	});

	test("gdy ŻADNA kategoria trackingowa nie jest zaakceptowana, GTM NIE jest wstrzykiwany mimo ustawionego gtmId", () => {
		window.cookieConfig = { gtmId: "GTM-ABC1234" };
		const { Tracking } = loadCookieJsFresh();

		Tracking.enable({ functional: true, analytics: false, marketing: false });

		expect(document.getElementById("gtm-script")).toBeNull();
		expect(document.getElementById("gtm-noscript")).toBeNull();
	});

	test("gdy brak gtmId w konfiguracji, GTM nie jest wstrzykiwany nawet z zaakceptowanym trackingiem", () => {
		window.cookieConfig = {};
		const { Tracking } = loadCookieJsFresh();

		Tracking.enable({ functional: true, analytics: true, marketing: true });

		expect(document.getElementById("gtm-script")).toBeNull();
	});

	test("nie duplikuje skryptu GTM przy kolejnym enable() (sprawdza istniejące #gtm-script)", () => {
		window.cookieConfig = { gtmId: "GTM-ABC1234" };
		const { Tracking } = loadCookieJsFresh();

		Tracking.enable({ analytics: true });
		Tracking.enable({ analytics: true });

		expect(document.querySelectorAll("#gtm-script").length).toBe(1);
	});

	test("woła window.gtag('consent','update', ...) z poprawnymi flagami granted/denied wg preferencji", () => {
		window.cookieConfig = {};
		window.gtag = jest.fn();
		const { Tracking } = loadCookieJsFresh();

		Tracking.enable({ functional: true, analytics: true, marketing: false });

		expect(window.gtag).toHaveBeenCalledWith("consent", "update", {
			ad_storage: "denied",
			ad_user_data: "denied",
			ad_personalization: "denied",
			analytics_storage: "granted",
		});
	});

	test("marketing=true -> flagi reklamowe 'granted' w gtag update", () => {
		window.cookieConfig = {};
		window.gtag = jest.fn();
		const { Tracking } = loadCookieJsFresh();

		Tracking.enable({ functional: true, analytics: false, marketing: true });

		expect(window.gtag).toHaveBeenCalledWith("consent", "update", {
			ad_storage: "granted",
			ad_user_data: "granted",
			ad_personalization: "granted",
			analytics_storage: "denied",
		});
	});

	test("pushuje zdarzenie 'cookieConsentUpdate' do window.dataLayer z flagami zgodnymi z preferencjami", () => {
		window.cookieConfig = {};
		const { Tracking } = loadCookieJsFresh();

		Tracking.enable({ functional: true, analytics: true, marketing: false });

		const event = window.dataLayer.find(e => e.event === "cookieConsentUpdate");
		expect(event).toMatchObject({
			cookieAnalytics: true,
			cookieMarketing: false,
			cookieFunctional: true,
		});
	});

	test("nie wywala się (brak window.gtag) — typeof-check chroni przed brakiem funkcji gtag", () => {
		window.cookieConfig = {};
		const { Tracking } = loadCookieJsFresh();

		expect(() => Tracking.enable({ analytics: true })).not.toThrow();
	});
});

describe("Tracking.disable()", () => {
	test("woła gtag('consent','update', ...) ze wszystkimi flagami 'denied'", () => {
		window.gtag = jest.fn();
		const { Tracking } = loadCookieJsFresh();

		Tracking.disable();

		expect(window.gtag).toHaveBeenCalledWith("consent", "update", {
			ad_storage: "denied",
			ad_user_data: "denied",
			ad_personalization: "denied",
			analytics_storage: "denied",
		});
	});

	test("czyści statyczne cookies śledzące (_ga, _gid, _fbp...)", () => {
		document.cookie = "_ga=GA1.2.111; path=/";
		document.cookie = "_gid=GID.222; path=/";
		document.cookie = "_fbp=fb.1.333; path=/";
		const { Tracking } = loadCookieJsFresh();

		Tracking.disable();

		expect(document.cookie).not.toContain("_ga=GA1.2.111");
		expect(document.cookie).not.toContain("_gid=GID.222");
		expect(document.cookie).not.toContain("_fbp=fb.1.333");
	});

	test("czyści też dynamiczne cookies GA4 w formacie _ga_XXXXXXX", () => {
		document.cookie = "_ga_ABC1234=GS1.1.999; path=/";
		const { Tracking } = loadCookieJsFresh();

		Tracking.disable();

		expect(document.cookie).not.toContain("_ga_ABC1234=GS1.1.999");
	});

	test("nie rusza cookies zgody bannera (cookieConsent-*) — czyści tylko listę śledzących", () => {
		document.cookie = "cookieConsent-functional=true; path=/";
		const { Tracking } = loadCookieJsFresh();

		Tracking.disable();

		expect(document.cookie).toContain("cookieConsent-functional=true");
	});
});
