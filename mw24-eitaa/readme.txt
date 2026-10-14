=== Eitaa Connect — Mihanweb24 ===
Contributors: mihanweb24
Tags: eitaa, mini-app, login, woocommerce, otp
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Open your WordPress or WooCommerce site as an Eitaa mini-app, log users in with their Eitaa-verified mobile number, sync it with any login plugin and send Eitaa messages.

== Description ==

**Eitaa Connect** turns any WordPress site (with or without WooCommerce) into an app-like **mini-app inside the Eitaa messenger**.

When a visitor opens your mini-app, Eitaa passes a signed data string (initData). The plugin verifies its HMAC-SHA256 signature on your server and:

* logs the visitor in instantly when the Eitaa account is already linked;
* otherwise asks for the mobile number with Eitaa's native dialog. The number is **signed by Eitaa**, so no SMS code is needed;
* finds the existing user with that number in **any** login/registration plugin (Digits, WooCommerce, Melipayamak, PeproDev, WP SMS, Ultimate Member, custom fields, custom tables…) or creates a new account;
* writes the verified number back to every configured field so all your plugins recognize the same user.

= Mini-app experience =

* Official SDK loaded only from developer.eitaa.com, as the first script in `<head>`, and only in mini-app mode.
* Expand, full screen, safe-area padding, vertical swipe control, closing confirmation.
* Eitaa Back button, Main button (smart WooCommerce "Cart (n)" / "Continue to checkout"), Secondary button and Settings menu item.
* Header / background / bottom bar colors (or the user's Eitaa theme), haptic feedback, dark/light body classes.
* Hide any element inside the mini-app with the `mw24-hide-in-eitaa` CSS class.
* Start parameters: `p123`, `cart`, `checkout`, `account`, `home`, `u:slug`.

= Login & security =

* Signature validation exactly as documented by Eitaa, plus optional second check with eitaayar.ir/api/app/verify.
* initData is accepted only in the `X-Eitaa-Init-Data` header, never in the URL.
* Replay protection, per-IP and per-account rate limits, generic error messages.
* Administrators and editors can never log in through Eitaa (unless you explicitly allow it).
* "Log in with Eitaa" shortcode `[mw24_eitaa_login]`, Gutenberg block and buttons on wp-login.php / WooCommerce.
* Trusted device (long "remember me" cookie).

= Phone sync engine =

1. **Ready-made profiles** – detected automatically, you only confirm them.
2. **Manual user-meta map** – choose from meta keys that really exist in your database, with masked samples, and pick the storage format (09…, 9…, 98…, +98…, 0098… or a custom pattern).
3. **Custom table map** – choose a table and its phone / user ID columns (white-listed through SHOW TABLES / DESCRIBE), optional read-only mode.

Dry-run test tool, conflict detection and a background "sync the past" tool for existing users.

= Eitaa messages =

* Event notifications: welcome, new registration (admins), new login, password change, WooCommerce order status, new order (admins), customer note, tracking code, abandoned cart.
* Bulk messages to connected users with filters, background queue (Action Scheduler or WP-Cron) and delivery reports.
* Test message from the admin, message from the user profile screen.
* Developer API: `mw24_eitaa_send()`, `mw24_eitaa_user_linked` action, `mw24_eitaa_notification_text` filter and many more.

= Payments =

Eitaa has **no in-app payment API**. Customers pay through your own site payment gateway inside the mini-app, exactly like in a browser.

= Privacy =

The app token is stored encrypted on your site only (or in wp-config.php) and is sent only to eitaayar.ir. Phone numbers are masked in logs. The plugin makes no hidden external requests; see docs/privacy-policy-fa.md in the package.

== Installation ==

1. Upload the plugin and activate it.
2. Make sure your site uses HTTPS.
3. In eitaayar.ir create an app and set its destination address to the entry URL shown in **Eitaa Connect › Connection** (by default `https://your-site/?mw24_eitaa=1`).
4. Copy the app token into **Eitaa Connect › Connection**, tick “Registered in Eitaayar”, enable the connection and save.
5. Open your app in Eitaa while logged in as administrator, then send a test message from **Tools**.
6. Review **Login & phone sync** and **Notifications**.

For security you can define the token in wp-config.php instead: `define( 'MW24_EITAA_APP_TOKEN', '...' );`

== Frequently Asked Questions ==

= Does it work without WooCommerce? =

Yes. All non-shop features work on any WordPress site.

= Which Eitaa apps support mini-apps? =

Eitaa currently runs mini-apps on Android and desktop (Windows). Your site keeps working normally for every other visitor.

= My cache plugin serves the wrong page inside Eitaa. =

Mini-app pages are sent with private/no-cache headers and DONOTCACHEPAGE. If your cache still serves them, exclude the cookie `mw24_eitaa_app` and URLs containing `mw24_eitaa`.

= Can administrators log in with Eitaa? =

Not by default. Accounts that can edit posts or manage options are always refused unless you enable the option explicitly.

= Messages are not delivered. =

Eitaa delivers only to users who opened your mini-app and allowed messages. Check your message credit in Eitaayar and the plugin's Log page.

= What happens when I disable the plugin? =

With the connection switched off nothing is added to your pages. Users keep their accounts and phone numbers.

== Screenshots ==

1. Dashboard with setup progress and next step.
2. Connection settings.
3. Phone sync wizard with an auto-detected profile.
4. Mini-app inside Eitaa with the phone sharing bar.
5. Notifications and bulk send.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
