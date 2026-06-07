(function () {
  var ids = window.FREECLID_IDS || ['gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid'];
  var query = new URLSearchParams(window.location.search);
  var maxAge = window.FREECLID_TTL || 60 * 60 * 24 * 90;

  ids.forEach(function (key) {
    var value = query.get(key);

    if (!value) {
      return;
    }

    document.cookie = 'fcl_' + key + '=' + encodeURIComponent(value)
      + ';path=/;max-age=' + maxAge
      + ';SameSite=Lax'
      + (window.location.protocol === 'https:' ? ';Secure' : '');
  });
})();
