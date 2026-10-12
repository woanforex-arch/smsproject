<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('registration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_type_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 32)->unique();
            $table->string('attendee_name');
            $table->string('attendee_email')->nullable();
            $table->string('attendee_phone', 32)->nullable();
            $table->string('status')->default('valid')->index(); // valid|checked_in|cancelled
            $table->timestampTz('checked_in_at')->nullable();
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('check_in_method')->nullable(); // manual|qr
            $table->timestamps();

            $table->index(['event_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};