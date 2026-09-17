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

test('the tree shows a goes-by name only when the use-everywhere toggle is on', function () {
    $withToggle = Person::factory()->create(['first_name' => 'Jonathan', 'last_name' => 'Smith', 'preferred_name' => 'Johnny', 'use_preferred_name_everywhere' => true]);
    $without = Person::factory()->create(['first_name' => 'Robert', 'last_name' => 'Jones', 'preferred_name' => 'Bob']);

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $withToggle->id]['data']['first name'])->toBe('Johnny');
    expect($data[(string) $withToggle->id]['data']['last name'])->toBe('');
    expect($data[(string) $without->id]['data']['first name'])->toBe('Robert');
    expect($data[(string) $without->id]['data']['last name'])->toBe('Jones');
});

test('the serializer includes a sortable ISO date of birth for stable birth-order sorting', function () {
    $person = Person::factory()->create(['dob' => '1990-05-12']);
    $unknownDob = Person::factory()->create(['dob' => null]);

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $person->id]['data']['dob_sort'])->toBe('1990-05-12');
    expect($data[(string) $unknownDob->id]['data']['dob_sort'])->toBeNull();
});

test('widestRootPersonId picks the root of the largest connected group, spouse links included', function () {
    $small = Person::factory()->create();

    $bigRoot = Person::factory()->create();
    $bigSpouse = Person::factory()->create();
    $bigChild = Person::factory()->create();
    Relationship::factory()->create(['person_a_id' => $bigRoot->id, 'person_b_id' => $bigSpouse->id, 'type' => 'spouse']);
    Relationship::factory()->parentChild()->create(['person_a_id' => $bigRoot->id, 'person_b_id' => $bigChild->id]);

    expect(FamilyTreeSerializer::widestRootPersonId())->toBe($bigRoot->id);
    expect(FamilyTreeSerializer::widestRootPersonId())->not->toBe($small->id);
});

test('widestRootPersonId returns null when there are no people at all', function () {
    expect(FamilyTreeSerializer::widestRootPersonId())->toBeNull();
});

test('a living person without consent has no avatar in the tree data', function () {
    $person = Person::factory()->create();

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $person->id]['data']['avatar'])->toBeNull();
});
