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
        Schema::create('service_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->integer('day_of_week'); // 0-6, где 0=воскресенье, 1=понедельник, ...
            $table->time('start_time'); // 08:00
            $table->time('end_time'); // 20:00
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['service_id', 'day_of_week']);
            $table->index(['service_id', 'day_of_week']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_schedules');
    }
};
