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
        Schema::create('relationships', function (Blueprint $table) {
            $table->id();

            // For type=parent_child, person_a is the parent and person_b is the child.
            $table->foreignId('person_a_id')->constrained('people')->cascadeOnDelete();
            $table->foreignId('person_b_id')->constrained('people')->cascadeOnDelete();
            $table->string('type'); // spouse | parent_child
            $table->string('status')->nullable(); // married | divorced | separated (spouse only)
            $table->date('started_at')->nullable();
            $table->date('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['person_a_id', 'person_b_id', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('relationships');
    }
};
