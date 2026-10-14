# اتصال به ایتا — میهن وب۲۴ (`mw24-eitaa`)

افزونهٔ وردپرس/ووکامرس که سایت شما را به‌صورت **مینی‌اپ داخل پیام‌رسان ایتا** باز می‌کند، کاربر را با
**شمارهٔ موبایل امضاشدهٔ ایتا** خودکار وارد/ثبت‌نام می‌کند، شماره را با **هر افزونهٔ ورود/ثبت‌نامی** (Digits،
ووکامرس، ملی پیامک، پپرو، WP SMS، فیلدها و جداول سفارشی…) همگام می‌کند و **پیام ایتا** (اعلان رویدادی و ارسال
گروهی) می‌فرستد.

- وردپرس 6.0+ · PHP 7.4 تا 8.3 · ووکامرس اختیاری (HPOS و بلوک‌های سبد/تسویه سازگار)
- رابط کاملاً فارسی و راست‌چین (رشته‌های منبع انگلیسی + ترجمهٔ کامل fa_IR)
- مجوز GPL-2.0-or-later · بدون کد مبهم · بدون call-home پنهان
- **ایتا API پرداخت درون‌برنامه‌ای ندارد؛ پرداخت با درگاه سایت شما انجام می‌شود.**

## ساختار مخزن

| مسیر | محتوا |
|---|---|
| [`mw24-eitaa/`](mw24-eitaa) | کد کامل افزونه (همین پوشه zip و نصب می‌شود) |
| [`docs/architecture-fa.md`](docs/architecture-fa.md) | نقشهٔ فایل‌ها و تصمیم‌های معماری |
| [`docs/user-guide-fa.md`](docs/user-guide-fa.md) | راهنمای نصب و استفاده برای کاربر غیرفنی |
| [`docs/developer-guide-fa.md`](docs/developer-guide-fa.md) | توابع، هوک‌ها، فیلترها، REST، پروفایل‌های JSON |
| [`docs/release-checklist-fa.md`](docs/release-checklist-fa.md) | چک‌لیست انتشار ژاکت/راست‌چین |
| [`docs/privacy-policy-fa.md`](docs/privacy-policy-fa.md) | سیاست حریم خصوصی |
| [`dev-tools/`](dev-tools) | ساخت POT/PO/MO و بستهٔ انتشار |

## شروع سریع

1. `bash dev-tools/build-zip.sh` ← فایل `dist/mw24-eitaa-1.0.0.zip` ساخته می‌شود (یا پوشهٔ `mw24-eitaa` را مستقیم
   در `wp-content/plugins` کپی کنید).
2. افزونه را نصب و فعال کنید؛ در منوی **اتصال به ایتا** مراحل داشبورد را دنبال کنید.
3. جزئیات: [راهنمای کاربر](docs/user-guide-fa.md).

## کیفیت و آزمون

| بررسی | نتیجه |
|---|---|
| PHPUnit (کتابخانهٔ تست رسمی وردپرس) روی PHP 7.4 / 8.1 / 8.3 + WordPress 7.1.3 | ✅ 98 تست، 204 assertion |
| آزمون دود (REST، فرانت، رندر همهٔ صفحه‌های مدیریت، ذخیرهٔ تنظیمات، ترجمه) | ✅ روی WordPress 6.0.11، 7.0.7 و 7.1.3 بدون هیچ notice |
| تست رفتاری اسکریپت مینی‌اپ (jsdom + SDK شبیه‌سازی‌شدهٔ ایتا) | ✅ 44 تست |
| PHPCS (WordPress Coding Standards 3 + PHPCompatibilityWP) | ✅ 0 خطا، 0 هشدار |
| بردار تست رسمی مستندات ایتا (HMAC) | ✅ |

اجرای آزمون‌ها:

```bash
cd mw24-eitaa
composer install
bin/install-wp-tests.sh wp_tests root '' 127.0.0.1 latest
vendor/bin/phpunit
vendor/bin/phpcs
cd tests/js && npm install && npm test
```

## منابع فنی

پیاده‌سازی بر اساس مستندات رسمی ایتا: [github.com/eitaa/Documentation](https://github.com/eitaa/Documentation)
(کیت توسعه `eitaa-web-app.js`، اعتبارسنجی hash، API‌های `verify` و `sendMessage` ایتایار، دریافت شماره با
`requestContact`) و نمونه‌های رسمی [github.com/eitaa/front-samples](https://github.com/eitaa/front-samples).
