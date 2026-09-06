<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_gateway_settings', function (Blueprint $t) {
            $t->string('gateway', 40)->primary();
            $t->boolean('enabled')->default(false);
            $t->longText('credentials')->nullable();
            $t->string('health', 24)->default('unknown');
            $t->timestamp('last_success_at')->nullable();
            $t->timestamp('last_failure_at')->nullable();
            $t->string('last_error', 120)->nullable();
            $t->timestamps();
        });

        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->ulid('public_id')->unique();
            $t->foreignId('reseller_id')->constrained()->restrictOnDelete();
            $t->string('gateway', 40)->default('blupal');
            $t->string('reference_id', 80)->unique();
            $t->unsignedBigInteger('amount');
            $t->unsignedBigInteger('final_amount')->nullable();
            $t->string('gateway_invoice_id', 80)->nullable();
            $t->string('gateway_transaction_id', 80)->nullable();
            $t->string('gateway_mode', 20)->nullable();
            $t->string('status', 24)->default('creating');
            $t->text('payment_url')->nullable();
            $t->string('error_code', 120)->nullable();
            $t->timestamp('last_verified_at')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->unique(['gateway','gateway_invoice_id']);
            $t->unique(['gateway','gateway_transaction_id']);
            $t->index(['reseller_id','created_at']);
            $t->index(['status','last_verified_at']);
        });
    }

    public function down(): void
    {
        throw new LogicException('Production payment migration is forward-only; restore a validated backup instead.');
    }
};
