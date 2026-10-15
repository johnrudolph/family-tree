<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each of these tables already has a composite unique index starting
     * with the *other* column (e.g. relationships' is
     * (person_a_id, person_b_id, type)) — that only speeds up lookups that
     * filter on the first column. The methods that filter on the second
     * column alone (Person::parents(), Person::photos(), Person::stories())
     * were doing a full table scan.
     */
    public function up(): void
    {
        Schema::table('relationships', function (Blueprint $table) {
            $table->index('person_b_id');
        });

        Schema::table('photo_person', function (Blueprint $table) {
            $table->index('person_id');
        });

        Schema::table('story_person', function (Blueprint $table) {
            $table->index('person_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('relationships', function (Blueprint $table) {
            $table->dropIndex(['person_b_id']);
        });

        Schema::table('photo_person', function (Blueprint $table) {
            $table->dropIndex(['person_id']);
        });

        Schema::table('story_person', function (Blueprint $table) {
            $table->dropIndex(['person_id']);
        });
    }
};
