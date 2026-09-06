<?php
namespace Tests\Feature;
use App\Models\Provider;
use App\Providers\Adapters\PasarGuardProviderAdapter;
use App\Providers\Adapters\ProviderException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
class ProviderTest extends TestCase {
use DatabaseTransactions;
private function adapter(): PasarGuardProviderAdapter {return new PasarGuardProviderAdapter('https://1.1.1.1','test-secret');}
public function test_credentials_are_encrypted_and_hidden(): void {
$p=Provider::create(['name'=>'P','api_url'=>'https://1.1.1.1','credentials'=>['key'=>'test-secret'],'group_ids'=>[1]]);
$this->assertStringNotContainsString('test-secret',DB::table('providers')->where('id',$p->id)->value('credentials'));
$this->assertArrayNotHasKey('credentials',$p->toArray());$this->assertSame('test-secret',$p->fresh()->credentials['key']);
}
public function test_create_uses_documented_payload(): void {
Http::fake(['*'=>Http::response(['id'=>1],201)]);$this->adapter()->createUser('test_user',[1],0,0);
Http::assertSent(fn($r)=>$r->url()==='https://1.1.1.1/api/user'&&$r->method()==='POST'&&$r['data_limit']===0&&$r['expire']===0&&$r->hasHeader('X-Api-Key','test-secret'));
}
public function test_configs_preserve_duplicates_and_broken_entries(): void {
$config=[['remarks'=>'same'],['remarks'=>'same'],['invalid'=>true]];
Http::fake(['*'=>Http::response($config)]);$this->assertSame($config,$this->adapter()->configurations(1));
}
public function test_errors_do_not_disclose_provider_response(): void {
Http::fake(['*'=>Http::response(['error'=>'test-secret'],401)]);
try{$this->adapter()->readUser('test_user');$this->fail();}catch(ProviderException $e){$this->assertSame('auth_or_permission_failed',$e->reason);$this->assertStringNotContainsString('test-secret',$e->getMessage());}
}
public function test_private_address_is_blocked(): void {
Http::fake();$this->expectException(ProviderException::class);(new PasarGuardProviderAdapter('https://127.0.0.1','key'))->readUser('test_user');
}
public function test_disable_uses_boolean(): void {
Http::fake(['*'=>Http::response(['id'=>1])]);$this->adapter()->setDisabled('test_user',true);
Http::assertSent(fn($r)=>$r->method()==='PUT'&&$r['disabled']===true&&str_ends_with($r->url(),'/disabled'));
}
}
