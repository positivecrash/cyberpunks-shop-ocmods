(function (w) {
	var CONFIG_ID = 'cyberpunks-consent-config';
	var BANNER_ID = 'cyberpunks-google-consent';
	var BUTTON_DATA_ATTR = 'data-google-consent';
	var GRANT_ACTION = 'grant';
	var DENY_ACTION = 'deny';
	var CONFIGURE_ACTION = 'configure';
	var SAVE_ACTION = 'save';
	var BACK_ACTION = 'back';
	var CHOICE_GRANTED = 'granted';
	var CHOICE_DENIED = 'denied';
	var CHOICE_CUSTOM = 'custom';

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
	cfg.cookieName = cfg.cookieName || 'cyberpunks_ad_storage';

	var MS = 86400000;
	var buttonSelector = '[' + BUTTON_DATA_ATTR + ']';

	w.dataLayer = w.dataLayer || [];
	function gtag() { w.dataLayer.push(arguments); }
	if (!w.gtag) w.gtag = gtag;

	function deniedMap() {
		return {
			ad_storage: CHOICE_DENIED,
			ad_user_data: CHOICE_DENIED,
			ad_personalization: CHOICE_DENIED,
			analytics_storage: CHOICE_DENIED,
			functionality_storage: CHOICE_DENIED,
			personalization_storage: CHOICE_DENIED
		};
	}

	function grantedMap() {
		return {
			ad_storage: CHOICE_GRANTED,
			ad_user_data: CHOICE_GRANTED,
			ad_personalization: CHOICE_GRANTED,
			analytics_storage: CHOICE_GRANTED,
			functionality_storage: CHOICE_GRANTED,
			personalization_storage: CHOICE_GRANTED
		};
	}

	function customMap(analyticsOn, adsOn) {
		var analytics = analyticsOn ? CHOICE_GRANTED : CHOICE_DENIED;
		var ads = adsOn ? CHOICE_GRANTED : CHOICE_DENIED;
		return {
			ad_storage: ads,
			ad_user_data: ads,
			ad_personalization: ads,
			analytics_storage: analytics,
			functionality_storage: analytics,
			personalization_storage: analytics
		};
	}

	function adsGrantedFromMap(map) {
		return map && map.ad_storage === CHOICE_GRANTED;
	}

	/** Mirror ad_storage to a first-party cookie so server-side Meta CAPI can gate on it. */
	function syncConsentCookie(adsGranted) {
		if (!cfg.cookieName) return;
		try {
			var maxAge = cfg.expiryDays * 86400;
			var secure = (w.location && w.location.protocol === 'https:') ? '; Secure' : '';
			var value = adsGranted ? CHOICE_GRANTED : CHOICE_DENIED;
			document.cookie = cfg.cookieName + '=' + encodeURIComponent(value)
				+ '; Path=/; Max-Age=' + maxAge + '; SameSite=Lax' + secure;
		} catch (err) { /* ignore */ }
	}

	function readStoredRecord() {
		if (!cfg.storageKey) return null;

		try {
			var raw = localStorage.getItem(cfg.storageKey);
			if (!raw) return null;

			try {
				var item = JSON.parse(raw);
				if (!item || !item.expires || Date.now() >= item.expires) return null;
				if (item.choice === CHOICE_GRANTED || item.choice === CHOICE_DENIED || item.choice === CHOICE_CUSTOM) {
					return item;
				}
				return null;
			} catch (err) {
				if (raw !== CHOICE_GRANTED && raw !== CHOICE_DENIED) return null;
				var legacy = { choice: raw, map: raw === CHOICE_GRANTED ? grantedMap() : deniedMap() };
				persistRecord(legacy.choice, legacy.map);
				return legacy;
			}
		} catch (err) {
			return null;
		}
	}

	function persistRecord(choice, map) {
		w.__cyberpunksConsentChoice = choice;
		w.__cyberpunksConsentMap = map;
		syncConsentCookie(adsGrantedFromMap(map));
		if (!cfg.storageKey) return;

		try {
			localStorage.setItem(cfg.storageKey, JSON.stringify({
				choice: choice,
				map: map,
				expires: Date.now() + cfg.expiryDays * MS
			}));
		} catch (err) { /* localStorage blocked or full */ }
	}

	function consentUpdate(map) {
		gtag('consent', 'update', map);
	}

	/**
	 * User-driven consent only (Accept / Reject / Save). Replay of a stored choice
	 * must not push this — returning visitors already get a granted page_view.
	 * GTM: GA4 page_view on cookie_consent_update when analytics_storage = granted.
	 */
	function notifyConsentUpdate(map) {
		if (!map) return;
		w.dataLayer.push({
			event: 'cookie_consent_update',
			analytics_storage: map.analytics_storage,
			ad_storage: map.ad_storage
		});
	}

	function applyMap(choice, map) {
		consentUpdate(map);
		persistRecord(choice, map);
		notifyConsentUpdate(map);
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

	var stored = readStoredRecord();
	if (stored) {
		var replayMap = stored.map;
		if (!replayMap) {
			replayMap = stored.choice === CHOICE_GRANTED ? grantedMap() : deniedMap();
		}
		consentUpdate(replayMap);
		syncConsentCookie(adsGrantedFromMap(replayMap));
		w.__cyberpunksConsentReplayed = true;
		w.__cyberpunksConsentChoice = stored.choice;
		w.__cyberpunksConsentMap = replayMap;
	}

	function showView(banner, name) {
		var views = banner.querySelectorAll('[data-consent-view]');
		for (var i = 0; i < views.length; i++) {
			views[i].hidden = views[i].getAttribute('data-consent-view') !== name;
		}
	}

	function readConfigureOptions(banner) {
		var analytics = banner.querySelector('[data-consent-option="analytics"]');
		var ads = banner.querySelector('[data-consent-option="ads"]');
		return {
			analytics: !!(analytics && analytics.checked),
			ads: !!(ads && ads.checked)
		};
	}

	function hideBanner(banner) {
		banner.hidden = true;
	}

	function initBanner() {
		var banner = document.getElementById(BANNER_ID);
		if (!banner) return;

		if (readStoredRecord() || w.__cyberpunksConsentChoice) {
			if (!w.__cyberpunksConsentReplayed && w.__cyberpunksConsentMap) {
				consentUpdate(w.__cyberpunksConsentMap);
				syncConsentCookie(adsGrantedFromMap(w.__cyberpunksConsentMap));
			}
			hideBanner(banner);
			return;
		}

		banner.hidden = false;
		showView(banner, 'main');

		banner.addEventListener('click', function (event) {
			var button = event.target.closest(buttonSelector);
			if (!button) return;

			var action = button.getAttribute(BUTTON_DATA_ATTR);
			if (action === CONFIGURE_ACTION) {
				showView(banner, 'configure');
				return;
			}
			if (action === BACK_ACTION) {
				showView(banner, 'main');
				return;
			}
			if (action === GRANT_ACTION) {
				applyMap(CHOICE_GRANTED, grantedMap());
				hideBanner(banner);
				return;
			}
			if (action === DENY_ACTION) {
				applyMap(CHOICE_DENIED, deniedMap());
				hideBanner(banner);
				return;
			}
			if (action === SAVE_ACTION) {
				var opts = readConfigureOptions(banner);
				if (!opts.analytics && !opts.ads) {
					applyMap(CHOICE_DENIED, deniedMap());
				} else if (opts.analytics && opts.ads) {
					applyMap(CHOICE_GRANTED, grantedMap());
				} else {
					applyMap(CHOICE_CUSTOM, customMap(opts.analytics, opts.ads));
				}
				hideBanner(banner);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initBanner);
	} else {
		initBanner();
	}
})(window);
