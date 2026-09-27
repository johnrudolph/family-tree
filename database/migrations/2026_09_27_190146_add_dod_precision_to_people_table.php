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
            // Defaults to 'exact' (unlike dob_precision, which defaults to
            // 'unknown') — every dod already on record predates this column
            // and was always treated as exact, so this preserves that
            // behavior for existing rows without a backfill.
            $table->string('dod_precision')->default('exact')->after('dod');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('people', function (Blueprint $table) {
            $table->dropColumn('dod_precision');
        });
    }
};
