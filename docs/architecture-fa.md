# معماری و نقشهٔ فایل‌های افزونهٔ «اتصال به ایتا — میهن وب۲۴»

> Text Domain: `mw24-eitaa` · پیشوند کلاس‌ها: `MW24_Eitaa_` · پیشوند توابع/آپشن‌ها/متاها: `mw24_eitaa_`
> حداقل‌ها: WordPress 6.0+ · PHP 7.4+ (آزمایش‌شده تا 8.3) · ووکامرس اختیاری

## ۱. نقشهٔ فایل‌ها

```text
mw24-eitaa/
├── mw24-eitaa.php                 بوت‌استرپ: هدر افزونه، ثابت‌ها، بررسی حداقل‌ها، autoloader، هوک فعال‌سازی
├── uninstall.php                  حذف داده‌ها فقط اگر «حذف همهٔ داده‌ها» روشن باشد (پشتیبانی Multisite)
├── readme.txt / CHANGELOG.md / LICENSE (GPLv2)
├── includes/
│   ├── Core.php                   کانتینر اصلی؛ ساخت ماژول‌ها و ثبت هوک‌ها (singleton)
│   ├── Settings.php               اسکیمای تنظیمات، پیش‌فرض‌ها، sanitize، توکن رمزنگاری‌شده، state
│   ├── Crypto.php                 AES-256-GCM برای توکن (کلید از MW24_EITAA_ENCRYPTION_KEY یا salt سایت)
│   ├── Validator.php              اعتبارسنجی امضای initData و پاسخ requestContact (الگوریتم رسمی ایتا)
│   ├── Phone_Normalizer.php       نرمال‌سازی به 09xxxxxxxxx، ارقام فارسی/عربی، ۵ واریانت، قالب‌ها، ماسک
│   ├── Phone_Mapper.php           موتور نقشهٔ شماره: جست‌وجو در همهٔ نقشه‌ها، نوشتن، تداخل، Dry-run
│   ├── Mapping/Meta.php           نقشهٔ wp_usermeta + کشف متاکلیدهای واقعی (DISTINCT + نمونهٔ ماسک‌شده)
│   ├── Mapping/Table.php          نقشهٔ جدول سفارشی (سفیدفهرست از SHOW TABLES/DESCRIBE، فقط‌خواندنی)
│   ├── Mapping/Profiles.php       بارگذاری profiles/*.json + فیلتر mw24_eitaa_phone_profiles + تشخیص
│   ├── Auth.php                   ورود/ثبت‌نام/اتصال، کنترل نقش‌های پرقدرت، ضدتکرار، خطای یکسان
│   ├── Rest.php                   REST عمومی: /session /contact /link /unlink /route /status
│   ├── Rate_Limiter.php           محدودیت نرخ per-IP و per-eitaa_id + تشخیص IP پشت CDN
│   ├── Frontend.php               تشخیص حالت مینی‌اپ، SDK، هدرهای کش/فریم، اسکریپت و پیکربندی، مسیریابی
│   ├── Login_Button.php           شورت‌کد، بلوک گوتنبرگ، دکمه در wp-login و فرم ورود ووکامرس
│   ├── Template.php               لودر قالب با امکان override در پوستهٔ سایت
│   ├── Messenger.php              کلاینت ایتایار: sendMessage و verify (بدون sslverify=false)
│   ├── Queue.php                  صف پیام (جدول اختصاصی) + Action Scheduler/WP-Cron + ارسال گروهی
│   ├── Notifications.php          اعلان‌های رویدادی حساب و ووکامرس، جای‌گذارها، سبد رهاشده
│   ├── Backfill.php               «همگام‌سازی گذشته» دسته‌ای در پس‌زمینه
│   ├── Logger.php                 لاگ در جدول اختصاصی (شماره ماسک، توکن هرگز)
│   ├── Installer.php              dbDelta، نسخهٔ اسکیما، کران، Multisite، حذف داده
│   ├── functions.php              API عمومی: mw24_eitaa_send() و …
│   ├── Compat/WooCommerce.php     سبد، URLها، فرگمنت شمارندهٔ سبد، اعلام سازگاری HPOS/Blocks
│   ├── Compat/Digits.php          فراخوانی digits_update_mobile() پس از تأیید شماره
│   ├── Compat/Cache.php           ثبت کوکی مینی‌اپ در WP Rocket / WP Super Cache
│   ├── Admin/Menu.php             منو، assets، فرم‌ها (admin-post + nonce + capability)، اعلان‌ها
│   ├── Admin/Page*.php            صفحه‌ها: داشبورد، اتصال، ظاهر، ورود و همگام‌سازی، اعلان‌ها، کاربران، لاگ، ابزارها، راهنما
│   ├── Admin/Fields.php           رندر فیلدها از روی اسکیما (همه خروجی‌ها escape)
│   ├── Admin/Users_Table.php      جدول کاربران متصل (جست‌وجو با نام/موبایل/شناسهٔ ایتا)
│   ├── Admin/Log_Table.php        جدول لاگ با فیلتر سطح/رویداد
│   ├── Admin/User_Profile.php     ستون «ایتا» در فهرست کاربران و متاباکس پروفایل
│   ├── Admin/Rest.php             REST مدیریتی (متاکلیدها، جداول، ستون‌ها، Dry-run، همگام‌سازی، پیام آزمایشی)
│   └── License/Manager.php        ماژول لایسنس جدا و قابل حذف (فقط به‌روزرسانی را قطع می‌کند)
├── assets/js/eitaa-app.js         اسکریپت مینی‌اپ (vanilla ES5، بدون jQuery اجباری)
├── assets/js/admin.js             رابط مدیریت (ویزارد نقشه، Dry-run، همگام‌سازی، ارسال پیام)
├── assets/css/{eitaa-app,login-button,admin}.css
├── blocks/login-button/           block.json + index.js (بلوک داینامیک بدون build)
├── templates/                     login-button.php و phone-bar.php (قابل override در پوسته)
├── profiles/*.json                پروفایل‌های آمادهٔ افزونه‌های ورود
├── languages/                     mw24-eitaa.pot + mw24-eitaa-fa_IR.po/.mo
├── tests/                         PHPUnit (WP test lib) + تست رفتاری JS (jsdom)
├── bin/install-wp-tests.sh        نصب WordPress و کتابخانهٔ تست از گیت‌هاب
├── phpunit.xml.dist / .phpcs.xml.dist / composer.json (فقط dev) / .distignore
```

خارج از پوشهٔ افزونه: `docs/` (راهنماها) و `dev-tools/` (ساخت POT/MO و بستهٔ انتشار).

## ۲. تصمیم‌های معماری

1. **بدون namespace و با پیشوند** (طبق خواست شما) و یک autoloader سادهٔ PSR-4 مانند:
   `MW24_Eitaa_Admin_Users_Table` → `includes/Admin/Users_Table.php`.
2. **رشته‌های منبع انگلیسی + ترجمهٔ کامل فارسی (fa_IR)**: استاندارد وردپرس است، رابط در سایت فارسی کاملاً
   فارسی است و افزونه برای بازارهای دیگر هم قابل ترجمه می‌ماند. پیش‌فرض‌های متنی (مثل قالب پیام‌ها) در زمان
   اجرا ترجمه می‌شوند تا زبان سایت را دنبال کنند و `__()` هیچ‌وقت پیش از `init` صدا زده نمی‌شود.
3. **«غیرفعال‌سازی ایمن»**: تا زمانی که اتصال روشن و توکن معتبر نباشد (`is_active()`)، هیچ اسکریپت، هدر، کوکی
   یا اعلانی اضافه نمی‌شود. SDK فقط در حالت مینی‌اپ و فقط از نشانی رسمی چاپ می‌شود.
4. **عدم ریدایرکت در نشانی ورودی**: ایتا می‌گوید نشانی برنامک نباید ریدایرکت شود؛ بنابراین مسیریابی پارامتر شروع
   در مرورگر (پس از بارگذاری SDK) انجام می‌شود، نه با ریدایرکت سرور.
5. **initData فقط در هدر** `X-Eitaa-Init-Data`؛ درخواست حاوی initData در query string با ۴۰۰ رد می‌شود.
   initData در sessionStorage کش می‌شود تا صفحه‌های بعدی همان نشست هم احراز شوند.
6. **پارسر اختصاصی querystring** به‌جای `parse_str`: همان رمزگشایی، اما بدون دست‌کاری کلیدها (`.` و `[]`) و
   با رد کلیدهای تکراری؛ مقایسه با `hash_equals`؛ بررسی `auth_date` (آینده‌دار نبودن با ۵ دقیقه تلورانس).
7. **ضدتکرار** روی hash پاسخ تماس (transient بیست‌دقیقه‌ای) و تطبیق اجباری `user_id` تماس با `user.id`.
8. **خطای یکسان** (`mw24_eitaa_auth_failed`) برای همهٔ دلایل امنیتی؛ دلیل واقعی فقط در لاگ.
9. **نقش‌های پرقدرت** (`edit_posts` یا `manage_options`، قابل تغییر با فیلتر) هرگز با ایتا وارد نمی‌شوند؛ اتصال
   حساب مدیر مجاز است تا اعلان‌های مدیریتی دریافت کند.
10. **موتور نقشهٔ شماره چندمنبعی**: داخلی ← پروفایل‌ها ← نقشهٔ دستی ← جدول سفارشی ← user_login/user_email.
    جست‌وجو با ۵ واریانت + قالب سفارشی و با حذف فاصله/خط تیره در SQL؛ نوشتن در همهٔ نقشه‌های قابل نوشتن، با
    جلوگیری از تکراری‌شدن شماره و بدون بازنویسی شمارهٔ متفاوت در «همگام‌سازی گذشته».
11. **امنیت SQL**: همهٔ مقادیر با `$wpdb->prepare`؛ نام جدول/ستون فقط از فهرست سفید `SHOW TABLES`/`DESCRIBE`.
12. **صف پیام** در جدول اختصاصی با «claim» اتمیک برای جلوگیری از ارسال دوباره، تلاش مجدد برای خطای شبکه و
    سرعت قابل تنظیم؛ اجرا با Action Scheduler (در صورت وجود) یا WP-Cron.
13. **توکن** رمزنگاری‌شده (AES-256-GCM، hex نه base64 برای شفافیت) یا تعریف در `wp-config.php`؛ هرگز در HTML،
    REST یا لاگ نمایش داده نمی‌شود.
14. **کش**: در حالت مینی‌اپ `nocache_headers()` (شامل `Cache-Control: private`)، `DONOTCACHEPAGE`،
    `Vary: Cookie` و API لایت‌اسپید؛ کوکی مینی‌اپ در WP Rocket/WP Super Cache مستثنا می‌شود.
15. **iframe (اختیاری)**: حذف `X-Frame-Options` و ارسال `frame-ancestors` برای دامنه‌های ایتا، و در صورت نیاز
    کوکی‌های ورود/ووکامرس با `SameSite=None; Secure`.
16. **Multisite**: نصب per-site (حتی برای سایت‌های جدید در حالت Network Activate)، حذف جداول هنگام حذف سایت.
17. **لایسنس** ماژولی جدا و قابل حذف؛ نبود/انقضای لایسنس هیچ قابلیتی را از کار نمی‌اندازد.

## ۳. جریان ورود

```text
باز شدن مینی‌اپ ─► SDK رسمی initData را می‌دهد ─► POST /session (هدر X-Eitaa-Init-Data)
   ├─ امضا/زمان نامعتبر ────────────► 401 خطای یکسان
   ├─ کاربر لاگین است ──────────────► اتصال حساب (بدون reload)
   ├─ eitaa_id متصل به کاربری است ──► wp_set_auth_cookie ─► {status:"ok", reload:true}
   └─ ناشناخته ─────────────────────► {status:"need_phone"} ─► نوار پایین / دکمهٔ ورود
                                         └─► requestContact ─► POST /contact {contact}
                                               ├─ امضای initData + امضای contact + ضدتکرار + تطبیق user_id
                                               ├─ نرمال‌سازی شماره ─► جست‌وجو در همهٔ نقشه‌ها
                                               ├─ پیدا شد ─► ورود ؛ پیدا نشد ─► ساخت کاربر (98XXXXXXXXXX)
                                               └─ نوشتن شماره در همهٔ نقشه‌ها ─► reload
```
