<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('KES');
            $table->unsignedInteger('quantity_total')->default(0);
            $table->unsignedInteger('quantity_sold')->default(0);
            $table->unsignedInteger('quantity_per_order_max')->default(10);
            $table->dateTimeTz('sales_start_at')->nullable();
            $table->dateTimeTz('sales_end_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_types');
    }
};