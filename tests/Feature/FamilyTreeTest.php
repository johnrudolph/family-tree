<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;
use App\Support\FamilyTreeSerializer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

test('a divorced spouse is flagged on both sides so the tree can draw a dashed line', function () {
    $person = Person::factory()->create();
    $ex = Person::factory()->create();
    $current = Person::factory()->create();

    Relationship::factory()->create(['person_a_id' => $person->id, 'person_b_id' => $ex->id, 'status' => 'divorced']);
    Relationship::factory()->create(['person_a_id' => $person->id, 'person_b_id' => $current->id, 'status' => 'married']);

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $person->id]['data']['divorced_spouse_ids'])->toBe([(string) $ex->id]);
    expect($data[(string) $ex->id]['data']['divorced_spouse_ids'])->toBe([(string) $person->id]);
    expect($data[(string) $current->id]['data']['divorced_spouse_ids'])->toBe([]);
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

test('a deceased person\'s memorial photo shows on the tree without consent', function () {
    Storage::fake('public');

    $person = Person::factory()->create(['is_living' => false]);
    $person->addMediaFromString('fake-image-bytes')->usingFileName('a.jpg')->preservingOriginal()->toMediaCollection('photo');

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $person->id]['data']['avatar'])->not->toBeNull();
});

test('serializing the tree does not N+1 query per person for accounts or photos', function () {
    User::factory()->withTwoFactor()->create();
    Person::factory()->consented()->count(10)->create();

    DB::enableQueryLog();
    FamilyTreeSerializer::toChartData();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A small constant number of queries regardless of person count — people,
    // relationships, plus eager-loaded user/media — not one per person.
    expect($queryCount)->toBeLessThan(10);
});

test('the tree data flags whether each person has a linked user account', function () {
    $withAccount = User::factory()->withTwoFactor()->create()->person;
    $without = Person::factory()->create();

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $withAccount->id]['data']['has_account'])->toBeTrue();
    expect($data[(string) $without->id]['data']['has_account'])->toBeFalse();
});

test('a living person\'s card shows only their birth year', function () {
    $person = Person::factory()->create(['dob' => '1954-03-03', 'is_living' => true]);

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $person->id]['data']['birthday'])->toBe('1954');
});

test('a deceased person with both dates known shows a birth–death year range', function () {
    $person = Person::factory()->create(['dob' => '1954-03-03', 'is_living' => false, 'dod' => '2020-01-15']);

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $person->id]['data']['birthday'])->toBe('1954–2020');
});

test('a deceased person with only a death year known still shows it', function () {
    $person = Person::factory()->create(['dob' => null, 'is_living' => false, 'dod' => '2020-01-15']);

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $person->id]['data']['birthday'])->toBe('d. 2020');
});

test('a deceased person with no death date recorded just shows their birth year', function () {
    $person = Person::factory()->create(['dob' => '1954-03-03', 'is_living' => false, 'dod' => null]);

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $person->id]['data']['birthday'])->toBe('1954');
});

test('sex is passed to family-chart as "M"/"F", and unset sex renders genderless', function () {
    $male = Person::factory()->create(['sex' => 'male']);
    $female = Person::factory()->create(['sex' => 'female']);
    $unset = Person::factory()->create(['sex' => null]);

    $data = collect(FamilyTreeSerializer::toChartData())->keyBy('id');

    expect($data[(string) $male->id]['data']['gender'])->toBe('M');
    expect($data[(string) $female->id]['data']['gender'])->toBe('F');
    expect($data[(string) $unset->id]['data']['gender'])->toBeNull();
});
