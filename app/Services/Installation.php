<?php
declare(strict_types=1);
namespace App\Services;

final class Installation
{
    public static function environment(array $input): string
    {
        $quote = static function (string $value): string {
            if (preg_match('/[\x00-\x1F\x7F]/', $value)) throw new \InvalidArgumentException('Control character in configuration');
            // Single quotes prevent dotenv variable interpolation.
            if (str_contains($value,"'")) throw new \InvalidArgumentException('Single quote is not supported in installer configuration');
            return "'".$value."'";
        };
        $values = [
            'APP_NAME'=>'پنل نمایندگان','APP_ENV'=>'production','APP_DEBUG'=>'false',
            'APP_URL'=>$input['url'],'APP_KEY'=>$input['key'],
            'DB_CONNECTION'=>'mysql','DB_HOST'=>$input['host'],'DB_PORT'=>$input['port'],
            'DB_DATABASE'=>$input['database'],'DB_USERNAME'=>$input['username'],'DB_PASSWORD'=>$input['password'],
            'SESSION_DRIVER'=>'database','SESSION_SECURE_COOKIE'=>'true','CACHE_STORE'=>'database','QUEUE_CONNECTION'=>'database',
        ];
        return implode("\n", array_map(static fn($k,$v)=>$k.'='.$quote((string)$v), array_keys($values),array_values($values)))."\n";
    }

    public static function requirements(string $root): array
    {
        $checks=['PHP 8.3+'=>version_compare(PHP_VERSION,'8.3','>='),'64-bit PHP'=>PHP_INT_SIZE===8];
        foreach(['pdo_mysql','mbstring','openssl','tokenizer','xml','ctype','curl','dom','fileinfo','filter','hash','session','zip'] as $ext) $checks[$ext]=extension_loaded($ext);
        foreach(['storage','bootstrap/cache'] as $dir) $checks[$dir]=is_dir($root.'/'.$dir)&&is_writable($root.'/'.$dir);
        $checks['application directory writable']=is_writable($root);
        $checks['Composer dependencies']=is_file($root.'/vendor/autoload.php');
        return $checks;
    }
}
