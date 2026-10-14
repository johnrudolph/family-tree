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
            $table->foreignId('featured_photo_id')->nullable()->after('location_id')->constrained('photos')->nullOnDelete();
            $table->foreignId('audio_recording_id')->nullable()->after('featured_photo_id')->constrained('audio_recordings')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('featured_photo_id');
            $table->dropConstrainedForeignId('audio_recording_id');
        });
    }
};
