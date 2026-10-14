# dev-tools

ابزارهای توسعه (داخل بستهٔ افزونه قرار نمی‌گیرند).

| فایل | کاربرد |
|---|---|
| `make-pot.php` | استخراج همهٔ رشته‌های ترجمه از کد (بدون نیاز به WP-CLI) ← `mw24-eitaa/languages/mw24-eitaa.pot` و `i18n/strings.json` |
| `i18n/fa_IR.part*.json` | ترجمهٔ فارسی هر رشته (کلید = متن انگلیسی؛ رشته‌های دارای context به شکل `context\u0004msgid`) |
| `build-translations.php` | بررسی کامل‌بودن ترجمه و حفظ جای‌گذارها/تگ‌ها، سپس ساخت `mw24-eitaa-fa_IR.po` و `.mo` |
| `build-zip.sh` | ساخت `dist/mw24-eitaa-<version>.zip` (با رعایت `.distignore`) و بستهٔ دانلودی همراه مستندات |

گردش کار پس از تغییر رشته‌ها:

```bash
php dev-tools/make-pot.php
# ترجمهٔ رشته‌های جدید را به یکی از فایل‌های dev-tools/i18n/fa_IR.part*.json اضافه کنید
php dev-tools/build-translations.php   # در صورت نبود ترجمه یا خرابی جای‌گذار با خطا متوقف می‌شود
bash dev-tools/build-zip.sh
```
