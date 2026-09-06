<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
class AuthController {
    public function show() { return view('login'); }
    public function login(Request $request) {
        $data=$request->validate(['email'=>'required|email|max:254','password'=>'required|string|max:1024']);
        $key='login:'.hash('sha256',strtolower($data['email']).'|'.$request->ip());
        if(RateLimiter::tooManyAttempts($key,5)) throw ValidationException::withMessages(['email'=>'تعداد تلاش‌ها زیاد است؛ کمی بعد دوباره امتحان کنید.']);
        if(!Auth::attempt($data)) { RateLimiter::hit($key,300); throw ValidationException::withMessages(['email'=>'اطلاعات ورود صحیح نیست.']); }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        return redirect($request->user()->role === 'super_admin' ? '/admin' : '/reseller');
    }
    public function logout(Request $request) {
        Auth::logout();$request->session()->invalidate();$request->session()->regenerateToken();
        return redirect('/login');
    }
}
