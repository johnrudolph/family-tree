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
        Schema::create('page_editors', function (Blueprint $table) {
            $table->id();
            $table->morphs('editable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role'); // owner | co_editor
            $table->timestamps();

            $table->unique(['editable_type', 'editable_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_editors');
    }
};
