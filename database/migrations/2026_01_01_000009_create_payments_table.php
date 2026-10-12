<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ticket types can opt out of payment (free events, sponsor passes).
        Schema::table('ticket_types', function (Blueprint $table) {
            $table->boolean('requires_payment')
                ->default(true)
                ->after('price')
                ->comment('Free ticket types (price = 0) never require payment regardless of this flag.');
        });

        Schema::table('registrations', function (Blueprint $table) {
            $table->string('payment_status')
                ->default('not_required')
                ->after('status')
                ->index();
            $table->timestampTz('paid_at')->nullable()->after('payment_status');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('provider')->default('flutterwave')->index();
            $table->string('tx_ref', 64)->unique();

            $table->string('transaction_id', 64)->nullable()->index();
            $table->string('flw_ref', 64)->nullable();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('KES');

            $table->string('status')->default('pending')->index();
            $table->string('provider_status')->nullable();
            $table->string('payment_type')->nullable();

            $table->string('checkout_url')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);

            $table->string('failure_reason')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->string('verified_by')->nullable();

            $table->json('meta')->nullable();
            $table->json('raw_payload')->nullable();

            $table->timestamps();

            $table->index(['registration_id', 'status']);
            $table->index(['provider', 'tx_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('registrations', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'paid_at']);
        });

        Schema::table('ticket_types', function (Blueprint $table) {
            $table->dropColumn('requires_payment');
        });
    }
};