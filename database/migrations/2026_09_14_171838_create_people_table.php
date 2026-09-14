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
        Schema::create('people', function (Blueprint $table) {
            $table->id();

            // Core facts — conservative defaults, editable via the wiki suggestion/editor workflow.
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('preferred_name')->nullable();
            $table->date('dob')->nullable();
            $table->string('dob_precision')->default('unknown'); // exact | approx | unknown
            $table->date('dod')->nullable();
            $table->boolean('is_living')->default(true);
            $table->longText('bio')->nullable();

            // Self-enrichment — nullable, and only ever writable by the linked user themself.
            $table->timestamp('consented_at')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->json('social_links')->nullable();

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('people');
    }
};
