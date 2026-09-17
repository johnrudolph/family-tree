<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Siblings used to be purely derived from shared parents, with no stored
     * row — which meant they couldn't be removed and didn't show up in
     * relationship lists. Now that "sibling" is a real, removable
     * relationship type, backfill one for every pair that already shares a
     * recorded parent, so existing data doesn't lose the fix.
     */
    public function up(): void
    {
        $childrenByParent = [];

        DB::table('relationships')
            ->where('type', 'parent_child')
            ->select('person_a_id', 'person_b_id')
            ->orderBy('person_b_id')
            ->get()
            ->each(function ($row) use (&$childrenByParent) {
                $childrenByParent[$row->person_a_id][] = $row->person_b_id;
            });

        $existingSiblingPairs = DB::table('relationships')
            ->where('type', 'sibling')
            ->select('person_a_id', 'person_b_id')
            ->get()
            ->map(fn ($row) => $this->pairKey($row->person_a_id, $row->person_b_id))
            ->all();

        $toInsert = [];
        $now = now();

        foreach ($childrenByParent as $children) {
            $children = array_values(array_unique($children));

            for ($i = 0; $i < count($children); $i++) {
                for ($j = $i + 1; $j < count($children); $j++) {
                    $key = $this->pairKey($children[$i], $children[$j]);

                    if (in_array($key, $existingSiblingPairs, true)) {
                        continue;
                    }

                    $existingSiblingPairs[] = $key;

                    $toInsert[] = [
                        'person_a_id' => $children[$i],
                        'person_b_id' => $children[$j],
                        'type' => 'sibling',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
        }

        foreach (array_chunk($toInsert, 200) as $chunk) {
            DB::table('relationships')->insert($chunk);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('relationships')->where('type', 'sibling')->delete();
    }

    private function pairKey(int $a, int $b): string
    {
        return implode('-', $a < $b ? [$a, $b] : [$b, $a]);
    }
};
