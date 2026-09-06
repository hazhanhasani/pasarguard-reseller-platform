<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
public function up(): void {
Schema::create('resellers',function(Blueprint $t){$t->id();$t->string('name');$t->timestamps();$t->softDeletes();});
Schema::create('users',function(Blueprint $t){$t->id();$t->foreignId('reseller_id')->nullable()->constrained()->restrictOnDelete();$t->string('name');$t->string('email')->unique();$t->string('password');$t->string('role')->default('reseller');$t->rememberToken();$t->timestamps();$t->softDeletes();});
Schema::create('stores',function(Blueprint $t){$t->id();$t->foreignId('reseller_id')->constrained()->restrictOnDelete();$t->string('name');$t->string('logo_path')->nullable();$t->string('brand_color',7)->default('#1483c9');$t->timestamps();$t->softDeletes();$t->unique(['id','reseller_id']);});
Schema::create('wallets',function(Blueprint $t){$t->id();$t->foreignId('reseller_id')->unique()->constrained()->restrictOnDelete();$t->bigInteger('balance')->default(0);$t->unsignedBigInteger('fractional_numerator')->default(0);$t->timestamps();});
Schema::create('wallet_ledger',function(Blueprint $t){$t->id();$t->foreignId('reseller_id')->constrained()->restrictOnDelete();$t->bigInteger('amount');$t->string('type',40);$t->string('reference',191)->unique();$t->text('description')->nullable();$t->bigInteger('balance_after');$t->timestamp('created_at');$t->index(['reseller_id','created_at']);});
Schema::create('sessions',function(Blueprint $t){$t->string('id')->primary();$t->foreignId('user_id')->nullable()->index();$t->string('ip_address',45)->nullable();$t->text('user_agent')->nullable();$t->longText('payload');$t->integer('last_activity')->index();});
Schema::create('cache',function(Blueprint $t){$t->string('key')->primary();$t->mediumText('value');$t->integer('expiration')->index();});
Schema::create('cache_locks',function(Blueprint $t){$t->string('key')->primary();$t->string('owner');$t->integer('expiration');});
Schema::create('jobs',function(Blueprint $t){$t->id();$t->string('queue')->index();$t->longText('payload');$t->unsignedTinyInteger('attempts');$t->unsignedInteger('reserved_at')->nullable();$t->unsignedInteger('available_at');$t->unsignedInteger('created_at');});
Schema::create('failed_jobs',function(Blueprint $t){$t->id();$t->string('uuid')->unique();$t->text('connection');$t->text('queue');$t->longText('payload');$t->longText('exception');$t->timestamp('failed_at')->useCurrent();});
Schema::create('settings',function(Blueprint $t){$t->string('key')->primary();$t->text('value')->nullable();$t->timestamp('updated_at')->nullable();});
}
public function down(): void { throw new LogicException('Production core migration is forward-only; restore a validated backup instead.'); }
};
