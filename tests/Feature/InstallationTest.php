<?php
namespace Tests\Feature;
use App\Services\Installation;
use PHPUnit\Framework\TestCase;
class InstallationTest extends TestCase {
    private function input(): array {return ['url'=>'https://example.test','key'=>'base64:abc','host'=>'localhost','port'=>'3306','database'=>'platform','username'=>'platform','password'=>'x${APP_KEY}#y'];}
    public function test_password_is_literal(): void {
        $env=Installation::environment($this->input());
        $parsed=\Dotenv\Dotenv::parse($env);
        $this->assertSame('x${APP_KEY}#y',$parsed['DB_PASSWORD']);
        $this->assertSame('false',$parsed['APP_DEBUG']);
    }
    public function test_newline_injection_is_rejected(): void {
        $input=$this->input();$input['password']="test\nAPP_DEBUG=true";
        $this->expectException(\InvalidArgumentException::class);Installation::environment($input);
    }
    public function test_quote_injection_is_rejected(): void {
        $input=$this->input();$input['password']="'bad'";
        $this->expectException(\InvalidArgumentException::class);Installation::environment($input);
    }
}
