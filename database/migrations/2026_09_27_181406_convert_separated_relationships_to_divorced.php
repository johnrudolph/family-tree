<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "Separated" is being dropped as a spouse status — distinct from
     * "divorced" wasn't pulling its weight. Any relationship recorded that
     * way becomes divorced instead, rather than being left invalid.
     */
    public function up(): void
    {
        DB::table('relationships')->where('status', 'separated')->update(['status' => 'divorced']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // The original "separated" vs "divorced" distinction isn't recoverable.
    }
};
