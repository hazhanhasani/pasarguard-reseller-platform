<?php
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
Artisan::command('platform:tick',function () {
    if (DB::connection()->getDriverName() !== 'mysql') { $this->error('Tick requires MySQL locking'); return 1; }
    $name='platform:'.substr(hash('sha256',base_path()),0,40);
    $locked=DB::selectOne('SELECT GET_LOCK(?,0) AS acquired',[$name]);
    if((int)$locked->acquired !== 1) { $this->info('Another tick is running'); return 0; }
    try {
        DB::table('settings')->updateOrInsert(['key'=>'cron_last_run'],['value'=>now()->toIso8601String(),'updated_at'=>now()]);
        $this->call('queue:work',['--stop-when-empty'=>true,'--max-jobs'=>50,'--max-time'=>45,'--tries'=>3]);
    } finally { DB::selectOne('SELECT RELEASE_LOCK(?) AS released',[$name]); }
    return 0;
})->purpose('Run one bounded queue cycle; schedule is controlled by cPanel');
