<?php
declare(strict_types=1);
// This entry point runs before Laravel requires APP_KEY or database sessions.
$root=dirname(__DIR__);
require $root.'/app/Services/Installation.php';
use App\Services\Installation;
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
$escape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$lockPath=$root.'/storage/installed.lock';
$secretPath=$root.'/storage/setup.key';
$installed=is_file($lockPath);
if($installed){http_response_code(404);exit('نصب‌کننده بسته است.');}
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off'){http_response_code(400);exit('برای نصب از HTTPS استفاده کنید.');}
ini_set('session.use_strict_mode','1');
session_name('platform_installer');
session_set_cookie_params(['secure'=>true,'httponly'=>true,'samesite'=>'Strict','path'=>'/']);
session_start();
$_SESSION['csrf']??=bin2hex(random_bytes(32));
$checks=Installation::requirements($root);
$error=null;$success=false;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $mutex=null;
    try {
        if(!hash_equals($_SESSION['csrf'],(string)($_POST['csrf']??''))) throw new RuntimeException('درخواست نامعتبر است.');
        if(!is_file($secretPath)||strlen(trim((string)file_get_contents($secretPath)))<32||!hash_equals(trim((string)file_get_contents($secretPath)),(string)($_POST['setup_key']??''))) throw new RuntimeException('کلید نصب صحیح نیست.');
        if(in_array(false,$checks,true)) throw new RuntimeException('پیش‌نیازها کامل نیستند.');
        $mutex=fopen($root.'/storage/install.mutex','c');
        if(!$mutex||!flock($mutex,LOCK_EX|LOCK_NB)) throw new RuntimeException('نصب دیگری در حال اجراست.');
        if(is_file($lockPath)||is_file($root.'/.env')) throw new RuntimeException('پیکربندی قبلاً ایجاد شده؛ نصب مجدد مجاز نیست.');
        $input=[];
        foreach(['url','host','port','database','username','password','admin_name','admin_email','admin_password'] as $field) {
            $input[$field]=(string)($_POST[$field]??'');
            if(strlen($input[$field])>1024) throw new RuntimeException('ورودی بیش از اندازه طولانی است.');
        }
        if(!filter_var($input['url'],FILTER_VALIDATE_URL)||parse_url($input['url'],PHP_URL_SCHEME)!=='https'||parse_url($input['url'],PHP_URL_QUERY)||parse_url($input['url'],PHP_URL_USER)||!in_array(parse_url($input['url'],PHP_URL_PATH),[null,'','/'],true)) throw new RuntimeException('دامنهٔ اصلی باید HTTPS و بدون مسیر باشد.');
        if(!preg_match('/^[a-zA-Z0-9_.-]+$/',$input['host'])||!ctype_digit($input['port'])||(int)$input['port']<1||(int)$input['port']>65535||!preg_match('/^[a-zA-Z0-9_]+$/',$input['database'])) throw new RuntimeException('مشخصات دیتابیس صحیح نیست.');
        if(!filter_var($input['admin_email'],FILTER_VALIDATE_EMAIL)||strlen($input['admin_email'])>254||strlen($input['admin_name'])<1||strlen($input['admin_name'])>200||strlen($input['admin_password'])<12||strlen($input['admin_password'])>72) throw new RuntimeException('نام، ایمیل و رمز مدیر را بررسی کنید؛ رمز بین ۱۲ تا ۷۲ بایت باشد.');
        $pdo=new PDO('mysql:host='.$input['host'].';port='.$input['port'].';dbname='.$input['database'].';charset=utf8mb4',$input['username'],$input['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        if($pdo->query('SHOW TABLES')->fetch()) throw new RuntimeException('برای نصب اولیه یک دیتابیس خالی و مستقل انتخاب کنید.');
        $input['key']='base64:'.base64_encode(random_bytes(32));
        $environment=Installation::environment($input);
        $env=fopen($root.'/.env','x');
        if(!$env) throw new RuntimeException('امکان ساخت پیکربندی نیست.');
        chmod($root.'/.env',0600);
        if(fwrite($env,$environment)!==strlen($environment)){fclose($env);throw new RuntimeException('نوشتن پیکربندی کامل نشد.');}fclose($env);
        require $root.'/vendor/autoload.php';
        $app=require $root.'/bootstrap/app.php';
        $kernel=$app->make(Illuminate\Contracts\Console\Kernel::class);
        $kernel->bootstrap();
        if($kernel->call('migrate',['--force'=>true])!==0) throw new RuntimeException('اجرای Migration موفق نشد.');
        Illuminate\Support\Facades\DB::transaction(function() use($input){
            $admin=new App\Models\User();$admin->name=$input['admin_name'];$admin->email=$input['admin_email'];$admin->password=$input['admin_password'];$admin->role='super_admin';$admin->save();
            Illuminate\Support\Facades\DB::table('settings')->insert(['key'=>'installed_at','value'=>date(DATE_ATOM),'updated_at'=>date('Y-m-d H:i:s')]);
        });
        if(file_put_contents($lockPath,date(DATE_ATOM),LOCK_EX)===false) throw new RuntimeException('ثبت قفل نصب موفق نشد.');
        chmod($lockPath,0600);unlink($secretPath);session_destroy();$success=true;
    } catch(Throwable $e){
        // Database and framework exception messages can contain credentials: never render them.
        $error=$e instanceof RuntimeException && !($e instanceof PDOException) ? $e->getMessage() : 'نصب کامل نشد؛ مشخصات و دسترسی‌ها را بررسی کنید.';
        if(!in_array(get_class($e),[RuntimeException::class,InvalidArgumentException::class],true))$error='نصب کامل نشد؛ مشخصات و دسترسی‌ها را بررسی کنید.';
    } finally {if(is_resource($mutex)){flock($mutex,LOCK_UN);fclose($mutex);}}
}
?><!doctype html><html lang="fa" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>راه‌اندازی پنل</title><link rel="stylesheet" href="/app.css"><body><main class="auth"><div class="mark">◈</div><h1>راه‌اندازی پنل</h1>
<?php if($success): ?><p>نصب انجام شد. اکنون Cron را در cPanel اضافه کنید.</p><p dir="ltr">php /home/USER/platform/artisan platform:tick</p><a href="/login">ورود به مدیریت</a>
<?php else: ?><p>دیتابیس مستقل، دامنهٔ اصلی و حساب مدیر را تنظیم کنید.</p><ul><?php foreach($checks as $label=>$ok): ?><li><?= $escape($label) ?>: <?= $ok?'✓':'✗' ?></li><?php endforeach ?></ul>
<?php if(!is_file($secretPath)): ?><p>برای حفاظت از نصب، فایل storage/setup.key را خارج از public با یک کلید تصادفی طولانی ایجاد کنید و همان کلید را وارد کنید.</p><?php endif ?>
<?php if($error): ?><p role="alert" class="error"><?= $escape($error) ?></p><?php endif ?>
<form method="post"><input type="hidden" name="csrf" value="<?= $escape($_SESSION['csrf']) ?>">
<?php foreach(['setup_key'=>'کلید یک‌بارمصرف نصب','url'=>'آدرس اصلی HTTPS','host'=>'میزبان دیتابیس','port'=>'پورت دیتابیس','database'=>'نام دیتابیس','username'=>'کاربر دیتابیس','password'=>'رمز دیتابیس','admin_name'=>'نام مدیر','admin_email'=>'ایمیل مدیر','admin_password'=>'رمز مدیر'] as $field=>$label): ?>
<label><?= $escape($label) ?><input name="<?= $field ?>" type="<?= in_array($field,['setup_key','password','admin_password'])?'password':'text' ?>" dir="ltr" required value="<?= $field==='port'?'3306':($field==='host'?'localhost':'') ?>"></label>
<?php endforeach ?><button>نصب و ایجاد حساب مدیر</button></form><?php endif ?></main></body></html>
