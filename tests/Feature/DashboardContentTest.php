<?php

use App\Models\Location;
use App\Models\Person;
use App\Models\Revision;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');
});

test('the dashboard greets the user and links to their own person page', function () {
    $user = User::factory()->withTwoFactor()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($user->name)
        ->assertSee($user->person->fullName())
        ->assertSee(route('people.show', $user->person), false);
});

test('a profile with missing core data prompts the viewer to complete it', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->person->update(['sex' => null, 'sex_unknown' => false, 'birth_location_id' => null, 'birth_location_unknown' => false]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Add your sex')
        ->assertSee('Add your place of birth')
        ->assertSee('Add a profile photo');
});

test('a complete profile does not prompt the viewer', function () {
    $user = User::factory()->withTwoFactor()->create();
    $user->person->update([
        'sex' => 'female',
        'birth_location_id' => Location::factory()->create()->id,
    ]);

    Livewire::actingAs($user)->test('pages::people.enrich', ['person' => $user->person])
        ->set('photo', UploadedFile::fake()->image('me.jpg'))
        ->call('save')
        ->assertHasNoErrors();

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertDontSee('Finish setting up your profile');
});

test('the dashboard shows recent activity for created people and stories', function () {
    $user = User::factory()->withTwoFactor()->create();
    Person::factory()->create(['first_name' => 'Cindy', 'last_name' => 'Crawford', 'created_by' => $user->id]);
    Story::factory()->create(['title' => 'A Family Reunion', 'created_by' => $user->id]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSeeInOrder([$user->name, 'created', 'Cindy Crawford'])
        ->assertSeeInOrder([$user->name, 'added the story', 'A Family Reunion']);
});

test('the dashboard shows recent activity for updated people', function () {
    $user = User::factory()->withTwoFactor()->create();
    $editor = User::factory()->withTwoFactor()->create(['name' => 'Max Belz']);
    $person = Person::factory()->create(['first_name' => 'Sara', 'last_name' => 'Belz']);
    Revision::create([
        'revisable_type' => Person::class,
        'revisable_id' => $person->id,
        'user_id' => $editor->id,
        'data' => ['first_name' => 'Sara'],
        'created_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSeeInOrder(['Max Belz', 'updated', 'Sara Belz']);
});

test('the dashboard lists recently added stories', function () {
    $user = User::factory()->withTwoFactor()->create();
    Story::factory()->create(['title' => 'The Big Move']);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('The Big Move');
});

test('the dashboard lists recently joined members', function () {
    $user = User::factory()->withTwoFactor()->create();
    User::factory()->withTwoFactor()->create(['name' => 'Brand New Person']);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee('Brand New Person');
});
