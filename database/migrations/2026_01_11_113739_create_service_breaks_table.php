<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_breaks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->string('name')->nullable(); // Lunch break, Coffee break
            $table->time('start_time'); // 12:00
            $table->time('end_time'); // 13:00
            $table->integer('day_of_week')->nullable(); // 0-6, если null, то применяется ко всем дням
            $table->boolean('is_recurring')->default(true);
            $table->timestamps();

            $table->index(['service_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_breaks');
    }
};
