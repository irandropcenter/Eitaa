# Changelog

All notable changes to **Eitaa Connect — Mihanweb24** (`mw24-eitaa`) are documented here.
The project follows [Semantic Versioning](https://semver.org/) and the
[Keep a Changelog](https://keepachangelog.com/) format.

## [1.0.0] - 2026-10-14

### Added
- Mini-app mode: official SDK as the first `<head>` script (only in mini-app mode), entry URL
  `?mw24_eitaa=1` with session cookie, optional User-Agent/Referer heuristics.
- App-like behaviour: expand, full screen, safe-area padding, vertical swipe control, closing
  confirmation, Back/Main/Secondary/Settings buttons, smart WooCommerce cart button, theme colors,
  haptic feedback, dark/light classes, `mw24-hide-in-eitaa` / `mw24-show-in-eitaa` helpers.
- Start-parameter routing (`p<ID>`, `cart`, `checkout`, `account`, `home`, `u:<slug>`), once per session.
- REST API `mw24-eitaa/v1`: `/session`, `/contact`, `/link`, `/unlink`, `/route`, `/status` and
  admin routes; initData only in the `X-Eitaa-Init-Data` header.
- HMAC-SHA256 validation of initData and requestContact responses (official algorithm), auth_date
  checks, optional verification with eitaayar.ir, replay protection, rate limits, generic errors.
- Automatic login/registration with Eitaa-verified phone numbers; privileged roles always refused
  by default; trusted-device cookie; login shortcode, Gutenberg block, wp-login.php and WooCommerce
  login buttons.
- Phone sync engine: ready-made JSON profiles (Digits, WooCommerce, Melipayamak, FarazSMS,
  PeproDev UPS, Login with phone number, WP SMS, Ultimate Member, User Registration, MihanPanel,
  Pro Login, Elementor templates), manual user-meta maps with real meta-key discovery, custom table
  maps (SHOW TABLES/DESCRIBE white list), dry-run tool and background "sync the past" tool.
- Eitaa messaging: event notifications, WooCommerce notifications, abandoned cart reminders, bulk
  sending with filters and reports, background queue (Action Scheduler / WP-Cron), test message.
- Admin: dashboard with readiness score and next step, connection, appearance, login & phone sync
  wizard, notifications, connected users, log, tools, help; "Eitaa" users column and profile box.
- Encrypted token storage (AES-256-GCM) or `MW24_EITAA_APP_TOKEN` constant.
- Log table and message queue table (dbDelta, schema versioning), multisite support, uninstall
  with optional data removal.
- Optional, removable marketplace license module that never blocks features.
- Persian (fa_IR) translation, POT file.
- PHPUnit suite (WordPress test library), JS behavioural tests (jsdom), PHPCS configuration.

### Fixed during development
- Storage formats `+98` and `0098` were matched as `98` by PHP's loose `switch` comparison;
  formatting now uses strict comparisons (covered by a regression test).
