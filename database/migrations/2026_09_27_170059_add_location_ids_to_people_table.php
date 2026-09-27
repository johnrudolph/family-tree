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
        Schema::table('people', function (Blueprint $table) {
            $table->foreignId('birth_location_id')->nullable()->after('birth_city')->constrained('locations')->nullOnDelete();
            $table->foreignId('death_location_id')->nullable()->after('death_city')->constrained('locations')->nullOnDelete();
        });

        // Very little data exists behind these free-text columns — dropped
        // rather than migrated, the handful of records get re-geocoded by hand.
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['birth_city', 'death_city']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->string('birth_city')->nullable();
            $table->string('death_city')->nullable();
        });

        Schema::table('people', function (Blueprint $table) {
            $table->dropConstrainedForeignId('birth_location_id');
            $table->dropConstrainedForeignId('death_location_id');
        });
    }
};
