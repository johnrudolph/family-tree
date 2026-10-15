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
        Schema::table('relationships', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('status')->constrained('locations')->nullOnDelete();
            $table->string('started_at_precision')->nullable()->after('started_at');
            $table->string('ended_at_precision')->nullable()->after('ended_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('relationships', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['started_at_precision', 'ended_at_precision']);
        });
    }
};
