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
        Schema::table('photo_person', function (Blueprint $table) {
            // Percentages (0-100) of the photo's width/height, not pixels —
            // resolution-independent and unaffected by thumbnail conversions.
            $table->decimal('x', 5, 2)->nullable()->after('person_id');
            $table->decimal('y', 5, 2)->nullable()->after('x');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('photo_person', function (Blueprint $table) {
            $table->dropColumn(['x', 'y']);
        });
    }
};
