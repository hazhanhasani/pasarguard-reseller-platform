# پلتفرم نمایندگان — در حال توسعه

این مخزن هنوز محصول قابل فروش یا بستهٔ نصب نهایی نیست.

هستهٔ محاسبهٔ مصرف و هزینه و سیاست تعلیق در GitHub Actions با موفقیت اجرا شده است:
https://github.com/hazhanhasani/pasarguard-reseller-platform/actions/runs/34042113533

ساختار Laravel، ورود فارسی، تفکیک نقش‌ها، Migrationهای اولیه و فرمان محدود Cron اضافه شده‌اند. تست جدید MySQL و ورود باید در Actions بررسی شود. نصب‌کنندهٔ وب و تمام فازهای عملیاتی بعدی هنوز تکمیل نشده‌اند.

اجرای توسعه: composer install، کپی .env.example به .env، تنظیم MySQL آزمایشی، php artisan key:generate، php artisan migrate و composer test.
ریشهٔ وب فقط public باشد. فرمان php artisan platform:tick یک چرخهٔ محدود صف اجرا می‌کند؛ همگام‌سازی Provider هنوز اضافه نشده است. برنامه هیچ زمان‌بندی داخلی ندارد.

مبنای محاسبه GB ده‌دهی و ریال است. تغییرات schema فقط رو به جلو هستند؛ down مخرب عمداً اجرا نمی‌شود.
