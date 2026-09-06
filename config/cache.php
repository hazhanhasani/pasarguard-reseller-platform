<?php
return ['default'=>env('CACHE_STORE','database'),'stores'=>['database'=>['driver'=>'database','table'=>'cache','lock_table'=>'cache_locks','connection'=>null,'lock_connection'=>null],'array'=>['driver'=>'array','serialize'=>false],'file'=>['driver'=>'file','path'=>storage_path('framework/cache/data')]],'prefix'=>'reseller_cache_'];
