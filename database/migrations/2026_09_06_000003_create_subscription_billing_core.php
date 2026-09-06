<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('master_subscriptions', function (Blueprint $t) {
            $t->ulid('master_subscription_id')->primary();
            $t->foreignId('reseller_id')->constrained()->restrictOnDelete();
            $t->foreignId('store_id')->constrained()->restrictOnDelete();
            $t->string('name', 120);
            $t->char('public_token_hash', 64)->unique();
            $t->text('public_token_encrypted');
            $t->unsignedInteger('token_version')->default(1);
            $t->timestamp('token_revoked_at')->nullable();
            $t->unsignedBigInteger('quota_bytes')->default(0);
            $t->unsignedBigInteger('used_bytes')->default(0);
            $t->unsignedBigInteger('duration_seconds')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->boolean('manual_suspended')->default(false);
            $t->string('desired_state', 32)->default('active');
            $t->unsignedBigInteger('state_version')->default(1);
            $t->timestamp('last_state_change_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
            $t->index(['reseller_id', 'desired_state']);
            $t->index(['store_id', 'desired_state']);
            $t->index(['expires_at', 'desired_state']);
        });

        Schema::create('provider_user_mappings', function (Blueprint $t) {
            $t->id();
            $t->ulid('master_subscription_id');
            $t->foreign('master_subscription_id')->references('master_subscription_id')->on('master_subscriptions')->restrictOnDelete();
            $t->foreignId('provider_id')->constrained()->restrictOnDelete();
            $t->string('provider_user_id', 191)->nullable();
            $t->string('provider_username', 32);
            $t->text('provider_subscription_token')->nullable();
            $t->string('sync_status', 24)->default('pending');
            $t->string('actual_state', 32)->nullable();
            $t->unsignedBigInteger('last_usage_bytes')->default(0);
            $t->string('usage_epoch', 64)->default('initial');
            $t->longText('last_valid_output')->nullable();
            $t->timestamp('last_valid_output_at')->nullable();
            $t->timestamp('last_success_at')->nullable();
            $t->timestamp('last_error_at')->nullable();
            $t->text('last_error')->nullable();
            $t->unsignedInteger('retry_count')->default(0);
            $t->timestamp('last_reconciled_at')->nullable();
            $t->timestamps();
            $t->unique(['master_subscription_id', 'provider_id']);
            $t->unique(['provider_id', 'provider_username']);
            $t->index(['sync_status', 'last_error_at']);
        });

        Schema::create('provider_operations', function (Blueprint $t) {
            $t->id();
            $t->ulid('master_subscription_id');
            $t->foreign('master_subscription_id')->references('master_subscription_id')->on('master_subscriptions')->restrictOnDelete();
            $t->foreignId('provider_id')->constrained()->restrictOnDelete();
            $t->string('operation', 32);
            $t->char('idempotency_key', 64)->unique();
            $t->unsignedBigInteger('desired_version')->default(1);
            $t->string('status', 24)->default('pending');
            $t->longText('payload')->nullable();
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at')->nullable();
            $t->timestamp('last_attempt_at')->nullable();
            $t->text('last_error')->nullable();
            $t->timestamps();
            $t->index(['status', 'available_at']);
            $t->index(['master_subscription_id', 'status']);
        });

        Schema::create('usage_snapshots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('mapping_id')->constrained('provider_user_mappings')->restrictOnDelete();
            $t->ulid('master_subscription_id');
            $t->foreign('master_subscription_id')->references('master_subscription_id')->on('master_subscriptions')->restrictOnDelete();
            $t->foreignId('provider_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('previous_bytes');
            $t->unsignedBigInteger('observed_bytes');
            $t->unsignedBigInteger('delta_bytes')->default(0);
            $t->string('epoch', 64);
            $t->string('status', 24)->default('valid');
            $t->char('idempotency_key', 64)->unique();
            $t->timestamp('observed_at');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['master_subscription_id', 'observed_at']);
            $t->index(['provider_id', 'observed_at']);
        });

        Schema::create('price_history', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('price_minor_per_gb');
            $t->timestamp('effective_from')->unique();
            $t->timestamp('effective_to')->nullable()->index();
            $t->timestamp('created_at')->useCurrent();
        });

        Schema::create('usage_charges', function (Blueprint $t) {
            $t->id();
            $t->foreignId('usage_snapshot_id')->unique()->constrained('usage_snapshots')->restrictOnDelete();
            $t->ulid('master_subscription_id');
            $t->foreign('master_subscription_id')->references('master_subscription_id')->on('master_subscriptions')->restrictOnDelete();
            $t->foreignId('reseller_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('usage_bytes');
            $t->unsignedBigInteger('rate_minor_per_gb');
            $t->unsignedBigInteger('charged_minor');
            $t->unsignedBigInteger('fractional_remainder_before')->default(0);
            $t->unsignedBigInteger('fractional_remainder_after')->default(0);
            $t->unsignedBigInteger('wallet_ledger_id')->nullable()->unique();
            $t->foreign('wallet_ledger_id')->references('id')->on('wallet_ledger')->restrictOnDelete();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['reseller_id', 'created_at']);
            $t->index(['master_subscription_id', 'created_at']);
        });

        Schema::create('subscription_events', function (Blueprint $t) {
            $t->id();
            $t->ulid('master_subscription_id')->nullable();
            $t->foreign('master_subscription_id')->references('master_subscription_id')->on('master_subscriptions')->restrictOnDelete();
            $t->foreignId('reseller_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('store_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('event_name', 80);
            $t->string('actor_type', 40)->nullable();
            $t->string('actor_id', 191)->nullable();
            $t->longText('payload')->nullable();
            $t->timestamp('created_at')->useCurrent();
            $t->index(['master_subscription_id', 'created_at']);
            $t->index(['reseller_id', 'created_at']);
        });

        Schema::create('audit_logs', function (Blueprint $t) {
            $t->id();
            $t->string('actor_type', 40)->nullable();
            $t->string('actor_id', 191)->nullable();
            $t->string('action', 100);
            $t->string('entity_type', 80);
            $t->string('entity_id', 191)->nullable();
            $t->longText('before_data')->nullable();
            $t->longText('after_data')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->text('user_agent')->nullable();
            $t->string('result', 24)->default('success');
            $t->timestamp('created_at')->useCurrent();
            $t->index(['entity_type', 'entity_id', 'created_at']);
            $t->index(['actor_type', 'actor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Production subscription and billing migration is forward-only; restore a validated backup instead.');
    }
};
