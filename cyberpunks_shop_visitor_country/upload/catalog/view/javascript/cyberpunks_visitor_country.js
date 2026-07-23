(function() {
  var KEY = 'cyberpunks_visitor_country';
  var pending = null;

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

  function lookupInBrowser() {
    // Fresh lookup for current public IP (VPN on/off). Do not keep a stale localStorage country.
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
      if (forced) return Promise.resolve(write(forced));
    } catch (e) { /* ignore */ }

    // Own shop endpoint sees current IP (no IP in localStorage).
    // Same IP → server session, no geojs. IP changed → server geojs once.
    return fetch('index.php?route=extension/module/cyberpunks_visitor_country', {
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
      cache: 'no-store'
    })
      .then(function(response) { return response.ok ? response.json() : null; })
      .then(function(json) {
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
    if (!pending) pending = fetchCountry().finally(function() { pending = null; });
    return pending;
  }

  window.CyberpunksVisitorCountry = {
    get: read,
    ready: ready
  };

  ready();
})();
