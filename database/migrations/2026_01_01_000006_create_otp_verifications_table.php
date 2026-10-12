<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 32)->index();
            $table->string('code_hash', 64);
            $table->string('purpose')->default('registration')->index(); // registration|login
            $table->string('context')->nullable(); // e.g. registration reference
            $table->unsignedTinyInteger('attempts')->default(0);
            // Nullable is load-bearing: on MySQL/MariaDB with
            // explicit_defaults_for_timestamp=OFF, the first NOT NULL TIMESTAMP
            // column silently gains "ON UPDATE CURRENT_TIMESTAMP", which would
            // reset the expiry to now() on every row update and break OTP
            // verification entirely. Declaring it NULL opts out of that.
            $table->timestampTz('expires_at')->nullable();
            $table->timestampTz('verified_at')->nullable();
            $table->timestampTz('last_sent_at')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['phone', 'purpose', 'verified_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('otp_verifications');
    }
};