(function() {
  var KEY = 'cyberpunks_visitor_country';
  var MANUAL_KEY = 'cyberpunks_visitor_country_manual';
  var pending = null;
  var resolvedIso = null;

  function normalize(value) {
    var iso = String(value || '').trim().toUpperCase();
    return /^[A-Z]{2}$/.test(iso) && iso !== 'XX' && iso !== 'T1' ? iso : '';
  }

  function read() {
    try {
      return normalize(localStorage.getItem(KEY));
    } catch (e) {
      return '';
    }
  }

  function isManual() {
    try {
      return localStorage.getItem(MANUAL_KEY) === '1';
    } catch (e) {
      return false;
    }
  }

  function setManualFlag(on) {
    try {
      if (on) localStorage.setItem(MANUAL_KEY, '1');
      else localStorage.removeItem(MANUAL_KEY);
    } catch (e) { /* ignore */ }
  }

  function write(iso) {
    var value = normalize(iso);
    if (!value) return '';
    try {
      localStorage.setItem(KEY, value);
    } catch (e) { /* ignore */ }
    return value;
  }

  function clear() {
    try {
      localStorage.removeItem(KEY);
    } catch (e) { /* ignore */ }
    return '';
  }

  /**
   * Persist country. options.manual = true keeps this choice across reloads
   * (geo/IP lookup will not overwrite it).
   */
  function set(iso, options) {
    var value = write(iso);
    if (!value) return '';
    if (options && options.manual) {
      setManualFlag(true);
    }
    resolvedIso = value;
    return value;
  }

  function clearManual() {
    setManualFlag(false);
    resolvedIso = null;
  }

  function lookupInBrowser() {
    // Fresh lookup for current public IP (VPN on/off). Do not keep a stale geo country.
    return fetch('https://get.geojs.io/v1/ip/country.json', {
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    })
    .then(function(response) { return response.ok ? response.json() : null; })
    .then(function(json) {
      var iso = normalize(json && json.country);
      return iso ? write(iso) : clear();
    })
    .catch(function() { return clear(); });
  }

  function fetchCountry() {
    try {
      var forced = normalize(new URLSearchParams(window.location.search).get('visitor_country'));
      if (forced) {
        setManualFlag(false);
        return Promise.resolve(write(forced));
      }
    } catch (e) { /* ignore */ }

    // User picked a country in the header — keep it (like manual currency).
    if (isManual()) {
      var manual = read();
      if (manual) return Promise.resolve(manual);
      setManualFlag(false);
    }

    // Own shop endpoint sees current IP (no IP in localStorage).
    // Same IP → server session, no geojs. IP changed → server geojs once.
    return fetch('index.php?route=extension/module/cyberpunks_visitor_country', {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function(response) { return response.ok ? response.json() : null; })
      .then(function(json) {
        // Manual choice may have been set while this request was in flight.
        if (isManual()) {
          var kept = read();
          if (kept) return kept;
        }

        var iso = normalize(json && json.iso_code_2);
        if (iso) return write(iso);

        // Docker / private IP: always re-ask geojs in the browser (current VPN state).
        if (json && json.source === 'local') {
          return lookupInBrowser();
        }

        return read();
      })
      .catch(function() { return read(); });
  }

  function ready() {
    if (resolvedIso !== null) return Promise.resolve(resolvedIso);
    if (!pending) {
      pending = fetchCountry().then(function(iso) {
        resolvedIso = iso || '';
        return resolvedIso;
      }).finally(function() {
        pending = null;
      });
    }
    return pending;
  }

  window.CyberpunksVisitorCountry = {
    get: read,
    set: set,
    ready: ready,
    clearManual: clearManual,
    isManual: isManual
  };

  ready();
})();
