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
        Schema::table('stories', function (Blueprint $table) {
            $table->date('start_date')->after('body');
            $table->string('start_date_precision')->default('exact')->after('start_date');
            $table->date('end_date')->nullable()->after('start_date_precision');
            $table->string('end_date_precision')->nullable()->after('end_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'start_date_precision', 'end_date', 'end_date_precision']);
        });
    }
};
