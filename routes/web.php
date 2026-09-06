<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
Route::redirect('/','/login');
Route::middleware('guest')->group(function () {
Route::get('/login',[AuthController::class,'show'])->name('login');
Route::post('/login',[AuthController::class,'login'])->middleware('throttle:20,1');
});
Route::post('/logout',[AuthController::class,'logout'])->middleware('auth');
Route::get('/admin',fn()=>view('dashboard',['title'=>'مدیریت کل']))->middleware(['auth','role:super_admin']);
Route::get('/reseller',fn()=>view('dashboard',['title'=>'پنل نماینده']))->middleware(['auth','role:reseller']);
