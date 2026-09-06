<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('providers', function (Blueprint $t) {
            $t->string('adapter', 40)->default('pasarguard')->after('name');
            $t->boolean('ready')->default(false)->after('mode');
            $t->unsignedTinyInteger('health_score')->default(0)->after('health');
            $t->timestamp('last_tested_at')->nullable()->after('last_failed_sync');
            $t->timestamp('capability_verified_at')->nullable()->after('last_tested_at');
            $t->index(['mode','ready','health']);
        });

        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->char('dedup_key', 64)->unique();
            $t->string('event_key', 100);
            $t->string('audience_type', 24)->default('super_admin');
            $t->foreignId('reseller_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('severity', 16)->default('info');
            $t->string('title', 191);
            $t->text('message')->nullable();
            $t->unsignedInteger('occurrence_count')->default(1);
            $t->timestamp('first_seen_at');
            $t->timestamp('last_seen_at');
            $t->timestamp('read_at')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
            $t->index(['audience_type','resolved_at','last_seen_at']);
            $t->index(['reseller_id','resolved_at','last_seen_at']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Production provider/notification migration is forward-only; restore a validated backup instead.');
    }
};
