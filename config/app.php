<?php
return [
'name'=>env('APP_NAME','پنل نمایندگان'), 'env'=>env('APP_ENV','production'),
'debug'=>(bool)env('APP_DEBUG',false),'url'=>env('APP_URL','https://localhost'),
'timezone'=>'Asia/Tehran','locale'=>'fa','fallback_locale'=>'en',
'key'=>env('APP_KEY'),'cipher'=>'AES-256-CBC',
'maintenance'=>['driver'=>'file'],
];
