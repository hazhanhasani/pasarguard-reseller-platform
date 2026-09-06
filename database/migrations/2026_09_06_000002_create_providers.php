<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {Schema::create('providers',function(Blueprint $t){
        $t->id();$t->string('name');$t->string('api_url');$t->text('credentials');$t->json('group_ids');
        $t->string('mode')->default('disabled');$t->string('health')->default('unknown');
        $t->json('capabilities')->nullable();$t->string('api_version')->nullable();
        $t->unsignedInteger('latency_ms')->nullable();$t->unsignedInteger('error_counter')->default(0);
        $t->timestamp('last_successful_sync')->nullable();$t->timestamp('last_failed_sync')->nullable();
        $t->string('last_error')->nullable();$t->timestamps();$t->softDeletes();
    });}
    public function down(): void {throw new LogicException('Forward-only migration');}
};
