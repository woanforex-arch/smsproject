<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('venue')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->dateTimeTz('starts_at');
            $table->dateTimeTz('ends_at');
            $table->dateTimeTz('registration_deadline')->nullable();
            $table->string('status')->default('draft')->index(); // draft|published|closed|cancelled
            $table->unsignedInteger('capacity')->nullable();
            $table->string('banner_path')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};