# FreeCLID

FreeCLID is a free, open-source WordPress plugin for offline conversion tracking. It captures ad click IDs on landing pages, ties them to Elementor Pro form submissions, stores everything in the site's own database, and serves a Google Ads ready CSV feed over HTTPS with HTTP Basic Auth.

No SaaS, no license server, no phone-home, no Composer dependencies, and no build step.

Author: [WGST](https://wgst.at)

## Requirements

- PHP 8.1+
- WordPress 6.4+
- Elementor Pro for v1 form capture
- HTTPS for the scheduled Google Ads feed

## What v1 Does

- Captures `gclid`, `gbraid`, `wbraid`, `fbclid`, and `msclkid` from URL parameters.
- Stores click IDs in first-party cookies with a configurable lookback window.
- Reads click ID cookies server-side during Elementor Pro form submissions.
- Stores captured leads in `{$wpdb->prefix}freeclid_leads`.
- Exposes `/freeclid-feed.csv` for Google Ads Data Manager scheduled uploads.
- Protects the feed with HTTP Basic Auth.
- Provides a Settings > FreeCLID admin page with feed credentials, conversion settings, click-ID toggles, feed URL display, uninstall behavior, and the last 10 captured leads.

## Install

1. Copy the `freeclid` folder into `wp-content/plugins/`.
2. Activate **FreeCLID** in WordPress.
3. Go to **Settings > FreeCLID**.
4. Set a feed username and password.
5. Set the conversion name, value, currency, and lookback window.
6. Copy the feed URL.

## Google Ads Setup

1. In Google Ads, enable auto-tagging first under **Settings > Account settings**.
2. Go to **Tools > Data Manager**.
3. Create a conversion action: **Import > Other data sources > Conversions from clicks**.
4. Name it exactly the same as the FreeCLID conversion name setting.
5. Add a scheduled HTTPS upload source pointing at:

   ```text
   https://example.com/freeclid-feed.csv
   ```

6. Use the Basic Auth username and password from **Settings > FreeCLID**.
7. Schedule the upload, usually daily.
8. Test with a real ad click before scaling spend.

## CSV Format

The feed emits Google-compatible click conversion rows:

```csv
Google Click ID,Conversion Name,Conversion Time,Conversion Value,Conversion Currency
TEST123,Lead,2026-06-07 12:30:00+0000,0,EUR
```

Rows are emitted in UTC with a `+0000` offset. Email and phone are never included in the CSV.

## Click ID Priority

FreeCLID stores one primary click ID per form submission using this priority:

```text
gclid > gbraid > wbraid > fbclid > msclkid
```

Additional valid click IDs present at submission time are stored in row meta for future integrations. The Google CSV feed emits only `gclid`, `gbraid`, and `wbraid` rows.

## Apache / PHP-FPM Authorization Header

If the feed returns `401 Unauthorized` under Apache with PHP-FPM even when credentials are correct, add this to `.htaccess` so the Authorization header reaches PHP:

```apache
RewriteEngine On
RewriteCond %{HTTP:Authorization} ^(.*)
RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]
```

## REST Fallback

If pretty permalinks are disabled, use:

```text
https://example.com/wp-json/freeclid/v1/feed.csv
```

## Extending

Future form integrations should call the same `Freeclid_DB::insert()` path from their form-submit hook.

Future ad destinations can reuse the existing stored rows:

- Meta: `fbclid` requires Conversions API, not a pulled CSV.
- Microsoft Ads: `msclkid` can use a CSV feed similar to Google's format.

## License

GPLv2 or later.
