<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->default('ticket_reference')->index(); // otp|ticket_reference
            $table->string('to_phone', 32)->index();
            $table->text('body');
            $table->string('provider_sid')->nullable()->index();
            $table->string('status')->default('queued')->index(); // queued|sent|delivered|failed|undelivered
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
    }
};