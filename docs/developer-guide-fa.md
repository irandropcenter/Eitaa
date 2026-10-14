# راهنمای توسعه‌دهنده — «اتصال به ایتا — میهن وب۲۴»

همهٔ نام‌ها پیشوند `mw24_eitaa_` (توابع/هوک‌ها/آپشن‌ها) و `MW24_Eitaa_` (کلاس‌ها) دارند.
کانتینر افزونه با `mw24_eitaa()` در دسترس است (`->settings`، `->auth`، `->mapper`، `->messenger`، `->queue`،
`->notifications`، `->frontend` و …).

## ۱. توابع عمومی

```php
// ارسال پیام ایتا. گیرنده: WP_User، شناسهٔ کاربر وردپرس (حساب ایتای متصل) یا chat_id خام ایتا.
$result = mw24_eitaa_send( $user_id, 'سلام {name}! سفارش شما آماده است.' );
$result = mw24_eitaa_send( '279058397', 'متن', array( 'type' => 'chat' ) );   // رفع ابهام: chat_id
$result = mw24_eitaa_send( $user_id, 'متن', array( 'queue' => true ) );        // در صف (شناسهٔ ردیف برمی‌گردد)
if ( is_wp_error( $result ) ) {
	error_log( $result->get_error_message() );
}

mw24_eitaa_get_chat_id( $user_id );        // شناسهٔ ایتای کاربر یا ''
mw24_eitaa_is_mini_app();                  // آیا درخواست فعلی داخل مینی‌اپ است؟
mw24_eitaa_normalize_phone( '+98 912…' );  // '09121234567' یا ''
mw24_eitaa_get_user_phone( $user_id );     // بهترین شمارهٔ شناخته‌شده در همهٔ نقشه‌ها
mw24_eitaa_find_user_by_phone( $phone );   // شناسهٔ کاربر یا 0 (همهٔ نقشه‌ها و ۵ واریانت)
echo mw24_eitaa_login_button( array( 'label' => 'ورود سریع' ) );
```

> متن ارسالی با `mw24_eitaa_send()` از جای‌گذارهای عمومی (`{name}`، `{site}`، `{link}`، `{date}`…) و فیلتر
> `mw24_eitaa_notification_text` عبور می‌کند.

## ۲. اکشن‌ها

| اکشن | آرگومان‌ها | زمان اجرا |
|---|---|---|
| `mw24_eitaa_loaded` | `$core` | پس از آماده شدن ماژول‌ها |
| `mw24_eitaa_user_linked` | `$user_id, $eitaa_id, $eitaa_user` | اتصال حساب وردپرس به ایتا |
| `mw24_eitaa_user_unlinked` | `$user_id, $eitaa_id, $reason` | قطع اتصال |
| `mw24_eitaa_user_registered` | `$user_id, $phone, $eitaa_user` | ثبت‌نام جدید با ایتا |
| `mw24_eitaa_user_logged_in` | `$user_id, $method` (`eitaa_id`/`phone`/`register`) | پس از ورود با ایتا (بعد از `wp_login`) |
| `mw24_eitaa_phone_verified` | `$user_id, $phone, $is_new` | ذخیرهٔ شمارهٔ تأییدشده |
| `mw24_eitaa_phone_written` | `$user_id, $phone, $report` | پس از نوشتن شماره در همهٔ نقشه‌ها |
| `mw24_eitaa_message_sent` | `$chat_id, $text, $context` | تحویل پیام به ایتایار |
| `mw24_eitaa_message_failed` | `$chat_id, $text, $error, $context` | خطای ارسال |
| `mw24_eitaa_logged` | `$level, $event, $message, $context` | ثبت هر رویداد در لاگ |

نمونه — امتیاز خوش‌آمد به کاربر جدید ایتا:

```php
add_action( 'mw24_eitaa_user_registered', function ( $user_id, $phone ) {
	update_user_meta( $user_id, 'my_points', 100 );
}, 10, 2 );
```

## ۳. فیلترها

| فیلتر | کاربرد |
|---|---|
| `mw24_eitaa_notification_text( $text, $event, $vars, $context )` | تغییر متن هر پیام (رویدادها: `welcome`, `login`, `password`, `order_status`, `admin_new_order`, `customer_note`, `tracking`, `abandoned_cart`, `admin_register`, `bulk`, `api`, `manual`) |
| `mw24_eitaa_phone_profiles( $profiles )` | افزودن/به‌روزرسانی پروفایل افزونه‌های ورود |
| `mw24_eitaa_phone_maps( $maps )` | تغییر نقشه‌های فعال متای کاربر |
| `mw24_eitaa_resolve_user( $user_id, $matches, $phone )` | تصمیم نهایی در صورت تداخل چند کاربر |
| `mw24_eitaa_normalize_phone( $result, $raw )` | نرمال‌سازی سفارشی (مثلاً شماره‌های خارجی) |
| `mw24_eitaa_can_login( true, $user )` | مسدودکردن ورود برخی کاربران (بازگرداندن `false` یا `WP_Error`) |
| `mw24_eitaa_privileged_caps( array( 'edit_posts', 'manage_options' ) )` | قابلیت‌هایی که ورود با ایتا را می‌بندند |
| `mw24_eitaa_new_user_data( $userdata, $phone, $eitaa, $contact )` | دادهٔ ساخت کاربر جدید |
| `mw24_eitaa_is_mini_app( $is_app )` | تغییر تشخیص حالت مینی‌اپ |
| `mw24_eitaa_js_config( $config )` | پیکربندی ارسالی به اسکریپت مینی‌اپ |
| `mw24_eitaa_start_param_url( $url, $param )` | مسیریابی پارامترهای شروع دلخواه |
| `mw24_eitaa_rate_limits( $limits )` | محدودیت نرخ (`ip`, `eitaa`, `route`) |
| `mw24_eitaa_client_ip( $ip )` | تشخیص IP |
| `mw24_eitaa_admin_chat_ids( $ids )` | گیرندگان اعلان‌های مدیریتی |
| `mw24_eitaa_http_args( $args, $method )` | آرگومان‌های درخواست به ایتایار (مثلاً پراکسی) |
| `mw24_eitaa_template( $file, $name, $args )` | مسیر قالب |
| `mw24_eitaa_capability( 'manage_options' )` | قابلیت لازم برای صفحه‌های مدیریت |
| `mw24_eitaa_license_providers` / `mw24_eitaa_license_parse_response` | ماژول لایسنس |

نمونه — متن اختصاصی برای وضعیت «تکمیل‌شده»:

```php
add_filter( 'mw24_eitaa_notification_text', function ( $text, $event, $vars, $context ) {
	if ( 'order_status' === $event && 'completed' === ( $context['status'] ?? '' ) ) {
		$text .= "\nبرای نظر دادن: https://example.com/review";
	}
	return $text;
}, 10, 4 );
```

نمونه — مسدودکردن ورود کاربران بن‌شده:

```php
add_filter( 'mw24_eitaa_can_login', function ( $allowed, $user ) {
	return get_user_meta( $user->ID, 'is_banned', true ) ? false : $allowed;
}, 10, 2 );
```

## ۴. پروفایل‌های شماره (JSON)

پروفایل‌ها در `profiles/*.json` هستند و با فیلتر هم قابل افزودن‌اند:

```json
{
	"id": "my-login",
	"name": "My Login Plugin",
	"version": "1.0.0",
	"verified": true,
	"priority": 60,
	"detect": { "plugins": [ "my-login" ], "functions": [ "my_login_init" ], "classes": [], "constants": [] },
	"fields": [
		{ "meta_key": "my_mobile", "format": "0", "lookup": true, "write": true },
		{ "meta_key": "my_country", "value": "+98", "write": true },
		{ "meta_key_like": "my_form_phone_%", "lookup": true }
	],
	"suggest_like": [ "%phone%" ],
	"notes": { "en": "…", "fa": "…" }
}
```

- `format`: یکی از `0` (09…)، `9`، `98`، `+98`، `0098` یا `custom` همراه `pattern` با `{cc}`، `{national}`، `{local}`.
- `value`: فیلد کمکی با مقدار ثابت (فقط نوشتن).
- `meta_key_like`: الگوی LIKE فقط برای جست‌وجو.
- پروفایل شناسایی‌شده به‌طور پیش‌فرض فعال است؛ مدیر می‌تواند غیرفعالش کند.

```php
add_filter( 'mw24_eitaa_phone_profiles', function ( $profiles ) {
	$profiles['my-login'] = array(
		'id'     => 'my-login',
		'name'   => 'My Login Plugin',
		'detect' => array( 'functions' => array( 'my_login_init' ) ),
		'fields' => array( array( 'meta_key' => 'my_mobile', 'format' => '0' ) ),
	);
	return $profiles;
} );
```

## ۵. REST API (`/wp-json/mw24-eitaa/v1`)

| مسیر | روش | دسترسی | توضیح |
|---|---|---|---|
| `/session` | POST | عمومی (امضای ایتا) | ورود حساب متصل، اتصال کاربر لاگین‌شده یا `need_phone` |
| `/contact` | POST `{contact}` | عمومی (دو امضا) | ورود/ثبت‌نام با شمارهٔ تأییدشده |
| `/link` | POST `{contact?}` | کاربر لاگین‌شده | اتصال حساب |
| `/unlink` | POST | کاربر لاگین‌شده | قطع اتصال |
| `/route?start=` | GET | عمومی | تبدیل پارامتر شروع به نشانی سفیدفهرست |
| `/status` | GET | مدیر | وضعیت (بدون افشای توکن) |
| `/admin/*` | GET/POST | مدیر + nonce | ابزارهای پیشخوان |

initData فقط در هدر **`X-Eitaa-Init-Data`** پذیرفته می‌شود. پاسخ‌های موفق: `{"status":"ok","reload":true}`،
`{"status":"need_phone"}`. خطاها: `mw24_eitaa_auth_failed` (401)، `mw24_eitaa_rate_limited` (429)،
`mw24_eitaa_phone_invalid` (422)، `mw24_eitaa_registration_disabled` (403)، `mw24_eitaa_conflict` (409)،
`mw24_eitaa_disabled` (503).

## ۶. API جاوااسکریپت (داخل مینی‌اپ)

```js
document.addEventListener( 'mw24-eitaa:ready', function () {
	// window.MW24EitaaApp.requestPhone()  باز کردن پنجرهٔ اشتراک شماره
	// window.MW24EitaaApp.login()         شروع ورود (مثل کلیک روی دکمهٔ ورود)
} );
document.addEventListener( 'mw24-eitaa:login', function ( e ) { console.log( e.detail ); } );
```

هر المانی با ویژگی `data-mw24-eitaa-login` (و اختیاری `data-redirect`) دکمهٔ ورود است.

## ۷. قالب‌ها

قالب‌ها را با کپی در `wp-content/themes/پوستهٔ-شما/mw24-eitaa/` بازنویسی کنید:
`login-button.php` و `phone-bar.php` (شناسه و ویژگی‌های `data-mw24-eitaa-*` را حفظ کنید).

## ۸. ثابت‌های wp-config.php

| ثابت | کاربرد |
|---|---|
| `MW24_EITAA_APP_TOKEN` | تعریف توکن خارج از دیتابیس |
| `MW24_EITAA_ENCRYPTION_KEY` | کلید رمزنگاری توکن (پیش‌فرض: salt سایت) |
| `MW24_EITAA_ALLOW_HTTP` | فقط محیط توسعهٔ محلی: نادیده‌گرفتن الزام HTTPS |
| `MW24_EITAA_DISABLE_LICENSE` | غیرفعال‌کردن ماژول لایسنس |
| `MW24_EITAA_ZHAKET_VERIFY_URL` / `_UPDATE_URL`، `MW24_EITAA_RTL_VERIFY_URL` / `_UPDATE_URL` | نشانی سرویس لایسنس/به‌روزرسانی مارکت |

## ۹. داده‌ها

- آپشن‌ها: `mw24_eitaa_settings`، `mw24_eitaa_app_token` (رمزنگاری)، `mw24_eitaa_state`، `mw24_eitaa_mapping`،
  `mw24_eitaa_db_version`، `mw24_eitaa_last_send`، `mw24_eitaa_batches`، `mw24_eitaa_backfill`، `mw24_eitaa_license`.
- متای کاربر: `mw24_eitaa_user_id`، `mw24_eitaa_allows_pm`، `mw24_eitaa_first_name`، `mw24_eitaa_linked_at`،
  `mw24_eitaa_phone`، `mw24_eitaa_phone_verified_at`، `mw24_eitaa_language`، `mw24_eitaa_last_seen`.
- جداول: `{prefix}mw24_eitaa_log` و `{prefix}mw24_eitaa_queue`.
- کران: `mw24_eitaa_hourly`، `mw24_eitaa_daily`، `mw24_eitaa_process_queue`، `mw24_eitaa_backfill_batch`.

## ۱۰. توسعه و تست

```bash
cd mw24-eitaa
composer install
bin/install-wp-tests.sh wp_tests root '' 127.0.0.1 latest
vendor/bin/phpunit                 # 98 تست PHPUnit
vendor/bin/phpcs                   # استاندارد کدنویسی وردپرس
cd tests/js && npm install && npm test   # 44 تست رفتاری اسکریپت مینی‌اپ
```

ساخت فایل‌های ترجمه و بستهٔ انتشار (از ریشهٔ مخزن):

```bash
php dev-tools/make-pot.php && php dev-tools/build-translations.php
bash dev-tools/build-zip.sh
```
