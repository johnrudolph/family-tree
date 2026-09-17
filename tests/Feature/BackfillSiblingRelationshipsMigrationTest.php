<?php

use App\Models\Person;
use App\Models\Relationship;

test('the sibling backfill migration creates one relationship per shared-parent pair without duplicating existing ones', function () {
    $mom = Person::factory()->create();
    $childA = Person::factory()->create();
    $childB = Person::factory()->create();
    $childC = Person::factory()->create();
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $childA->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $childB->id]);
    Relationship::factory()->parentChild()->create(['person_a_id' => $mom->id, 'person_b_id' => $childC->id]);
    // A already has an explicit sibling row with B, in the reverse direction —
    // the migration must not create a duplicate for this pair.
    Relationship::factory()->sibling()->create(['person_a_id' => $childB->id, 'person_b_id' => $childA->id]);

    $migration = require database_path('migrations/2026_09_17_124858_backfill_explicit_sibling_relationships.php');
    $migration->up();

    expect(Relationship::query()->where('type', 'sibling')->count())->toBe(3);
    expect($childA->fresh()->siblings()->pluck('id'))->toContain($childB->id, $childC->id);
    expect($childB->fresh()->siblings()->pluck('id'))->toContain($childA->id, $childC->id);

    // Running it again must be idempotent — no new rows created.
    $migration->up();
    expect(Relationship::query()->where('type', 'sibling')->count())->toBe(3);
});
