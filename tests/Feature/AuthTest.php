<?php
namespace Tests\Feature;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
class AuthTest extends TestCase {
use DatabaseTransactions;
public function test_guest_cannot_enter_admin(): void { $this->get('/admin')->assertRedirect('/login'); }
public function test_reseller_cannot_enter_admin(): void {
$u=User::create(['name'=>'Test','email'=>'reseller@example.test','password'=>'a-long-test-password']);
$this->actingAs($u)->get('/admin')->assertForbidden();
}
public function test_admin_cannot_enter_reseller_context(): void {
$u=User::create(['name'=>'Test','email'=>'admin@example.test','password'=>'a-long-test-password']);
$u->role='super_admin';$u->save();
$this->actingAs($u)->get('/reseller')->assertForbidden();
$this->actingAs($u)->get('/admin')->assertOk();
}
public function test_login_and_logout(): void {
User::create(['name'=>'Test','email'=>'login@example.test','password'=>'a-long-test-password']);
$this->post('/login',['email'=>'login@example.test','password'=>'a-long-test-password'])->assertRedirect('/reseller');
$this->assertAuthenticated();$this->post('/logout')->assertRedirect('/login');$this->assertGuest();
}
public function test_archived_user_cannot_login(): void {
$u=User::create(['name'=>'Test','email'=>'archived@example.test','password'=>'a-long-test-password']);$u->delete();
$this->post('/login',['email'=>'archived@example.test','password'=>'a-long-test-password'])->assertSessionHasErrors('email');$this->assertGuest();
}
}
