<?php

use App\Models\ApiToken;
use App\Models\Location;
use App\Models\Person;
use App\Models\Relationship;
use App\Models\User;

beforeEach(function () {
    $this->token = ApiToken::issue('test');
});

test('a writable model can be created', function () {
    $creator = User::factory()->create();

    $response = $this->withToken($this->token)
        ->postJson('/api/v1/models/people', [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'is_living' => false,
            'created_by' => $creator->id,
        ])
        ->assertCreated();

    expect(Person::query()->where('first_name', 'Ada')->exists())->toBeTrue();
    expect($response->json('first_name'))->toBe('Ada');
});

test('creating a writable model only accepts its own fillable columns', function () {
    $creator = User::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/models/people', [
            'first_name' => 'Ada',
            'created_by' => $creator->id,
            'password' => 'should-be-ignored', // not fillable on Person at all
        ])
        ->assertCreated();

    $person = Person::query()->where('first_name', 'Ada')->firstOrFail();
    expect($person->getAttributes())->not->toHaveKey('password');
});

test('a non-writable model cannot be created', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/models/stories', ['title' => 'Nope'])
        ->assertForbidden();
});

test('a non-writable model cannot be updated', function () {
    $user = User::factory()->create();

    $this->withToken($this->token)
        ->patchJson("/api/v1/models/users/{$user->id}", ['is_admin' => true])
        ->assertForbidden();

    expect($user->fresh()->is_admin)->toBeFalse();
});

test('updating a writable model only touches the fields provided, via its own fillable columns', function () {
    $person = Person::factory()->create(['first_name' => 'Jon', 'last_name' => 'Smith']);

    $this->withToken($this->token)
        ->patchJson("/api/v1/models/people/{$person->id}", ['first_name' => 'John'])
        ->assertOk()
        ->assertJsonPath('first_name', 'John');

    expect($person->fresh()->last_name)->toBe('Smith');
});

test('updating an unwritable field on a writable model is silently ignored', function () {
    $person = Person::factory()->create();

    $this->withToken($this->token)
        ->patchJson("/api/v1/models/people/{$person->id}", ['id' => 999999])
        ->assertOk();

    expect(Person::query()->find($person->id))->not->toBeNull();
});

test('locations and relationships are writable, for linking imported people into the tree', function () {
    $location = $this->withToken($this->token)
        ->postJson('/api/v1/models/locations', [
            'formatted_address' => 'Lorch, Württemberg, Germany',
            'precision' => 'city',
            'source' => 'manual',
        ])
        ->assertCreated()
        ->json();

    expect(Location::find($location['id']))->not->toBeNull();

    $parent = Person::factory()->create();
    $child = Person::factory()->create();

    $this->withToken($this->token)
        ->postJson('/api/v1/models/relationships', [
            'person_a_id' => $parent->id,
            'person_b_id' => $child->id,
            'type' => 'parent_child',
        ])
        ->assertCreated();

    expect(Relationship::query()->where('person_a_id', $parent->id)->where('person_b_id', $child->id)->exists())->toBeTrue();
});

test('updating a missing record on a writable model 404s', function () {
    $this->withToken($this->token)
        ->patchJson('/api/v1/models/people/999999', ['first_name' => 'x'])
        ->assertNotFound();
});

test('updating a missing record on a non-writable model 403s, not 404s — the writable check runs first', function () {
    $this->withToken($this->token)
        ->patchJson('/api/v1/models/stories/999999', ['title' => 'x'])
        ->assertForbidden();
});
