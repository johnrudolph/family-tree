<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Support\FamilyTreeSerializer;

test('the tree page renders for an authenticated member', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('tree.index'))
        ->assertOk()
        ->assertSee('family-tree-chart', false);
});

test('the serializer produces family-chart compatible parent/child/spouse rels', function () {
    $parent = Person::factory()->create();
    $spouse = Person::factory()->create();
    $child = Person::factory()->create();

    Relationship::factory()->parentChild()->create(['person_a_id' => $parent->id, 'person_b_id' => $child->id]);
    Relationship::factory()->create(['person_a_id' => $parent->id, 'person_b_id' => $spouse->id]);

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $child->id]['rels']['parents'])->toBe([(string) $parent->id]);
    expect($data[(string) $parent->id]['rels']['children'])->toBe([(string) $child->id]);
    expect($data[(string) $parent->id]['rels']['spouses'])->toBe([(string) $spouse->id]);
    expect($data[(string) $spouse->id]['rels']['spouses'])->toBe([(string) $parent->id]);
});

test('a living person without consent has no avatar in the tree data', function () {
    $person = Person::factory()->create();

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $person->id]['data']['avatar'])->toBeNull();
});
