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
            // Distinct from a merely-unfilled field: these mark that someone
            // deliberately confirmed the fact is unknowable, so the data-gaps
            // admin view can stop surfacing it as an open gap to fill in.
            $table->boolean('dob_unknown')->default(false)->after('dob_precision');
            $table->boolean('birth_location_unknown')->default(false)->after('birth_location_id');
            $table->boolean('dod_unknown')->default(false)->after('dod');
            $table->boolean('death_location_unknown')->default(false)->after('death_location_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn(['dob_unknown', 'birth_location_unknown', 'dod_unknown', 'death_location_unknown']);
        });
    }
};
