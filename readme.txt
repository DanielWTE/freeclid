=== FreeCLID ===
Contributors: freeclid
Tags: google ads, offline conversions, gclid, elementor, tracking
Requires at least: 6.4
Tested up to: 6.4
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Free, open-source offline conversion tracking for WordPress. Captures ad click IDs, stores leads locally, and exposes a Google Ads CSV feed protected by HTTP Basic Auth.

== Description ==

FreeCLID captures click IDs from landing page URLs and keeps them in first-party cookies for the configured lookback window. On Elementor Pro form submission, the plugin reads the click ID cookie server-side and stores one local lead row in the WordPress database.

The plugin exposes a Google Ads compatible CSV feed at:

`https://example.com/freeclid-feed.csv`

There is also a REST fallback for sites without pretty permalinks:

`https://example.com/wp-json/freeclid/v1/feed.csv`

All data stays in the site's own database. There are no license servers, no external HTTP calls, and no telemetry.

== Supported click IDs ==

FreeCLID captures these IDs:

* `gclid`
* `gbraid`
* `wbraid`
* `fbclid`
* `msclkid`

Submission priority is:

`gclid > gbraid > wbraid > fbclid > msclkid`

Version 1 stores one primary click ID per form submission. If additional valid click IDs are present, they are stored in row meta for future feed integrations.

The Google CSV feed emits only Google-compatible IDs (`gclid`, `gbraid`, `wbraid`). Email and phone are stored only for admin debugging and deduplication; they are never written to the CSV feed.

Note: `gbraid` and `wbraid` are emitted in the same `Google Click ID` column in version 1. A future release can add Google's newer separate braid columns.

== Installation ==

1. Upload the `freeclid` folder to `/wp-content/plugins/`.
2. Activate FreeCLID in WordPress.
3. Go to Settings > FreeCLID.
4. Set the feed username and password.
5. Set the conversion name, default conversion value, currency, and lookback window.
6. Copy the feed URL shown on the settings page.

== Google Ads setup ==

1. In Google Ads, enable auto-tagging first: Settings > Account settings.
2. Go to Tools > Data Manager.
3. Create a conversion action of type Import > Other data sources > Conversions from clicks.
4. Name the conversion action exactly the same as the FreeCLID conversion name setting.
5. Add a scheduled upload source over HTTPS.
6. Use the FreeCLID feed URL and the Basic Auth username/password from Settings > FreeCLID.
7. Set a schedule, usually daily.
8. Test with a real ad click before scaling spend.

== Apache and PHP-FPM Authorization header ==

Apache with PHP-FPM often does not pass the `Authorization` header to PHP by default. If the feed always returns `401 Unauthorized` even with the right credentials, add this to `.htaccess`:

`
RewriteEngine On
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]
`

FreeCLID also checks `REDIRECT_HTTP_AUTHORIZATION`, which helps on many FastCGI setups.

== CSV feed format ==

The feed header is:

`Google Click ID,Conversion Name,Conversion Time,Conversion Value,Conversion Currency`

Rows use UTC with an explicit `+0000` offset:

`TEST123,Lead,2026-06-07 12:30:00+0000,0,EUR`

The conversion name must match the Google Ads Import conversion action name exactly.

== Security and privacy ==

* Feed access fails closed when either credential is empty.
* Feed credentials are compared with `hash_equals`.
* The feed password is stored as a shared machine secret in WordPress options, not hashed. Use HTTPS and a strong random password.
* Click IDs are validated before storage.
* Database writes use WordPress database APIs with format specifiers.
* No PII is written to the Google CSV feed.
* Cookies use `SameSite=Lax` and add `Secure` on HTTPS.
* There are no external HTTP calls.
* By default, uninstall keeps data. Enable "Delete data on uninstall" in Settings > FreeCLID if you want the plugin to drop its table and delete settings when uninstalled.

== Extensibility notes ==

Meta (`fbclid`) does not use a pulled CSV feed. It uses the Conversions API with server-side POSTs and hashed PII. FreeCLID captures `fbclid` rows now so a future Meta integration can use existing historical data.

Microsoft Ads (`msclkid`) supports offline conversion CSV uploads similar to Google's format. A future Microsoft feed can reuse the same table and insert path with a different endpoint and `MSCLKID` column.

Additional form builders can be added by hooking their submit action and calling `Freeclid_DB::insert()` with the same row shape used by the Elementor integration.

== Frequently Asked Questions ==

= Does this require hidden Elementor fields? =

No. FreeCLID reads first-party cookies from the server-side Elementor AJAX request. The browser sends the cookies automatically.

= Does this send data to a SaaS service? =

No. FreeCLID stores data locally and serves only your authenticated CSV feed.

= Can I use it without pretty permalinks? =

Use the REST fallback URL shown in Settings > FreeCLID: `/wp-json/freeclid/v1/feed.csv`.

== Changelog ==

= 1.0.0 =
* Initial release with click ID cookies, Elementor Pro capture, Basic Auth Google CSV feed, settings page, and local database storage.
