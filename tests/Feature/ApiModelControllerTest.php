<?php

use App\Models\ApiToken;
use App\Models\Location;
use App\Models\Person;
use App\Models\Story;
use App\Models\User;

beforeEach(function () {
    $this->token = ApiToken::issue('test');
    // UserFactory auto-creates a linked Person for every User (and vice
    // versa, via Person's `created_by`), so pin created_by to one existing
    // user throughout — otherwise each bare Person::factory()->create()
    // silently creates a second "invariant" person via its linked user.
    $this->creator = User::factory()->create();
});

test('the models index lists every whitelisted model with a count', function () {
    // +1 for the person the beforeEach creator's own UserFactory invariant creates.
    Person::factory()->count(3)->create(['created_by' => $this->creator->id]);

    $response = $this->withToken($this->token)->getJson('/api/v1/models')->assertOk();

    $people = collect($response->json())->firstWhere('key', 'people');
    expect($people['count'])->toBe(4);
});

test('an unknown model key 404s', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/models/passkeys')
        ->assertNotFound();
});

test('listing a model returns paginated records', function () {
    Person::factory()->count(3)->create(['created_by' => $this->creator->id]);

    $response = $this->withToken($this->token)->getJson('/api/v1/models/people')->assertOk();

    expect($response->json('total'))->toBe(4);
});

test('filtering by a fillable column narrows the results', function () {
    Person::factory()->create(['first_name' => 'Jean', 'created_by' => $this->creator->id]);
    Person::factory()->create(['first_name' => 'Bob', 'created_by' => $this->creator->id]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/models/people?'.http_build_query(['filter' => ['first_name' => 'Jean']]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.first_name'))->toBe('Jean');
});

test('filtering by a non-whitelisted column is silently ignored rather than erroring', function () {
    Person::factory()->count(2)->create(['created_by' => $this->creator->id]);

    $this->withToken($this->token)
        ->getJson('/api/v1/models/people?'.http_build_query(['filter' => ['password' => 'x']]))
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('showing a single record returns it', function () {
    $person = Person::factory()->create(['first_name' => 'Jean', 'created_by' => $this->creator->id]);

    $this->withToken($this->token)
        ->getJson("/api/v1/models/people/{$person->id}")
        ->assertOk()
        ->assertJsonPath('first_name', 'Jean');
});

test('a missing record 404s', function () {
    $this->withToken($this->token)
        ->getJson('/api/v1/models/people/999')
        ->assertNotFound();
});

test('only whitelisted relations can be eager-loaded via ?with=', function () {
    $location = Location::factory()->create(['city' => 'Portland']);
    $person = Person::factory()->create(['birth_location_id' => $location->id, 'created_by' => $this->creator->id]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/models/people?with=birthLocation')
        ->assertOk();

    $data = collect($response->json('data'))->firstWhere('id', $person->id);
    expect($data['birth_location']['city'])->toBe('Portland');
});

test('a user\'s password and two-factor secrets never appear in the api response', function () {
    User::factory()->withTwoFactor()->create();

    $response = $this->withToken($this->token)->getJson('/api/v1/models/users')->assertOk();

    expect($response->json('data.0'))->not->toHaveKeys(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token']);
});

test('per_page is capped at 100', function () {
    Person::factory()->count(5)->create(['created_by' => $this->creator->id]);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/models/people?per_page=1000')
        ->assertOk();

    expect($response->json('per_page'))->toBe(100);
});

test('stories can be queried and filtered too, proving this isn\'t people-specific', function () {
    Story::factory()->create(['title' => 'A Summer Trip']);
    Story::factory()->create(['title' => 'Another One']);

    $response = $this->withToken($this->token)
        ->getJson('/api/v1/models/stories?'.http_build_query(['filter' => ['title' => 'A Summer Trip']]))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
});
