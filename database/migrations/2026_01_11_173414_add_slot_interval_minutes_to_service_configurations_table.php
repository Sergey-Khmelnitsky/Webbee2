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
        Schema::table('service_configurations', function (Blueprint $table) {
            $table->integer('slot_interval_minutes')->nullable()->after('break_between_minutes')
                ->comment('Interval between slot start times (e.g., 10 for slots every 10 minutes). If null, uses duration_minutes + break_between_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_configurations', function (Blueprint $table) {
            $table->dropColumn('slot_interval_minutes');
        });
    }
};
