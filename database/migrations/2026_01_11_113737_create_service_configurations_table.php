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
        Schema::create('service_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->onDelete('cascade');
            $table->integer('duration_minutes'); // длительность встречи
            $table->integer('break_between_minutes'); // пауза между встречами
            $table->integer('max_concurrent_clients')->default(1); // сколько клиентов одновременно
            $table->integer('booking_advance_days'); // на сколько дней вперед можно бронировать
            $table->timestamps();

            $table->unique('service_id');
            $table->index('service_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_configurations');
    }
};
