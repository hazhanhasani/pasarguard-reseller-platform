<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $t) {
            $t->id();
            $t->string('type', 24);
            $t->string('status', 24)->default('creating');
            $t->string('disk_path', 500);
            $t->unsignedBigInteger('size_bytes')->default(0);
            $t->char('sha256', 64)->nullable();
            $t->string('app_version', 64)->nullable();
            $t->string('migration_level', 191)->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->longText('metadata')->nullable();
            $t->text('failure_reason')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('completed_at')->nullable();
            $t->index(['status', 'created_at']);
            $t->index(['type', 'created_at']);
        });

        Schema::create('update_history', function (Blueprint $t) {
            $t->id();
            $t->string('version', 64);
            $t->string('channel', 32)->default('stable');
            $t->string('package_name', 191);
            $t->string('package_path', 500);
            $t->char('package_sha256', 64)->nullable();
            $t->string('status', 32)->default('uploaded');
            $t->longText('manifest')->nullable();
            $t->foreignId('backup_id')->nullable()->constrained('backups')->nullOnDelete();
            $t->string('rollback_status', 40)->nullable();
            $t->text('error')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->index(['status', 'created_at']);
            $t->index(['version', 'created_at']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Production backup/update migration is forward-only; restore a validated backup instead.');
    }
};
