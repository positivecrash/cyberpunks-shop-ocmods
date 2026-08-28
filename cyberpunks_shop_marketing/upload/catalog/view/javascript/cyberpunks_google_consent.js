(function (w) {
	var CONFIG_ID = 'cyberpunks-consent-config';
	var BANNER_ID = 'cyberpunks-google-consent';
	var BUTTON_DATA_ATTR = 'data-google-consent';
	var GRANT_ACTION = 'grant';
	var CHOICE_GRANTED = 'granted';
	var CHOICE_DENIED = 'denied';

	var el = document.getElementById(CONFIG_ID);
	if (!el) return;

	var cfg;
	try {
		cfg = JSON.parse(el.textContent);
	} catch (e) {
		return;
	}
	if (!cfg || !cfg.waitForUpdate) {
		return;
	}

	cfg.expiryDays = cfg.expiryDays > 0 ? cfg.expiryDays : 30;
	cfg.storageKey = cfg.storageKey || '';

	var MS = 86400000;
	var buttonSelector = '[' + BUTTON_DATA_ATTR + ']';

	w.dataLayer = w.dataLayer || [];
	function gtag() { w.dataLayer.push(arguments); }
	if (!w.gtag) w.gtag = gtag;

	function consentValue(granted) {
		var v = granted ? CHOICE_GRANTED : CHOICE_DENIED;
		return {
			ad_storage: v,
			ad_user_data: v,
			ad_personalization: v,
			analytics_storage: v,
			functionality_storage: v,
			personalization_storage: v
		};
	}

	function readStoredChoice() {
		if (!cfg.storageKey) return '';

		try {
			var raw = localStorage.getItem(cfg.storageKey);
			if (!raw) return '';

			try {
				var item = JSON.parse(raw);
				if (item && (item.choice === CHOICE_GRANTED || item.choice === CHOICE_DENIED) && item.expires && Date.now() < item.expires) {
					return item.choice;
				}
				return '';
			} catch (err) {
				if (raw !== CHOICE_GRANTED && raw !== CHOICE_DENIED) return '';
				persistChoice(raw);
				return raw;
			}
		} catch (err) {
			return '';
		}
	}

	function readChoice() {
		if (w.__cyberpunksConsentChoice === CHOICE_GRANTED || w.__cyberpunksConsentChoice === CHOICE_DENIED) {
			return w.__cyberpunksConsentChoice;
		}
		return readStoredChoice();
	}

	function persistChoice(choice) {
		w.__cyberpunksConsentChoice = choice;
		if (!cfg.storageKey) return;

		try {
			localStorage.setItem(cfg.storageKey, JSON.stringify({
				choice: choice,
				expires: Date.now() + cfg.expiryDays * MS
			}));
		} catch (err) { /* localStorage blocked or full */ }
	}

	function consentUpdate(granted) {
		gtag('consent', 'update', consentValue(granted));
	}

	function applyChoice(grant) {
		consentUpdate(grant);
		persistChoice(grant ? CHOICE_GRANTED : CHOICE_DENIED);
	}

	gtag('consent', 'default', {
		ad_storage: CHOICE_DENIED,
		ad_user_data: CHOICE_DENIED,
		ad_personalization: CHOICE_DENIED,
		analytics_storage: CHOICE_DENIED,
		functionality_storage: CHOICE_DENIED,
		personalization_storage: CHOICE_DENIED,
		security_storage: CHOICE_GRANTED,
		wait_for_update: cfg.waitForUpdate
	});

	var stored = readChoice();
	if (stored) {
		consentUpdate(stored === CHOICE_GRANTED);
		w.__cyberpunksConsentReplayed = true;
	}

	function initBanner() {
		var banner = document.getElementById(BANNER_ID);
		if (!banner) return;

		var choice = readChoice();
		if (choice) {
			if (!w.__cyberpunksConsentReplayed) consentUpdate(choice === CHOICE_GRANTED);
			banner.hidden = true;
			return;
		}

		banner.hidden = false;
		banner.addEventListener('click', function (event) {
			var button = event.target.closest(buttonSelector);
			if (!button) return;
			applyChoice(button.getAttribute(BUTTON_DATA_ATTR) === GRANT_ACTION);
			banner.hidden = true;
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initBanner);
	} else {
		initBanner();
	}
})(window);
