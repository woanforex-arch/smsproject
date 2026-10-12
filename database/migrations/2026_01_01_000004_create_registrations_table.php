<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->unique();
            $table->string('name');
            $table->string('email')->index();
            $table->string('phone', 32)->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->string('status')->default('pending_otp')->index(); // pending_otp|verified|cancelled
            $table->unsignedTinyInteger('otp_attempts')->default(0);
            $table->timestampTz('otp_verified_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['event_id', 'status']);
            $table->index(['email', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};