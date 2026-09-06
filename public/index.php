<?php
use Illuminate\Http\Request;
define('LARAVEL_START', microtime(true));
if (!is_file(__DIR__.'/../.env')) { header('Location: /install.php'); exit; }
require __DIR__.'/../vendor/autoload.php';
(require_once __DIR__.'/../bootstrap/app.php')->handleRequest(Request::capture());
