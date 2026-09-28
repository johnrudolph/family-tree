<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Services\RelationshipService;

test('direct relatives include the full blood line and spouses, but not a spouse\'s own family', function () {
    $viewer = Person::factory()->create(['first_name' => 'Viewer']);
    $mom = Person::factory()->create(['first_name' => 'Mom']);
    $dad = Person::factory()->create(['first_name' => 'Dad']);
    $grandpaMom = Person::factory()->create(['first_name' => 'GrandpaMom']);
    $grandmaMom = Person::factory()->create(['first_name' => 'GrandmaMom']);
    $auntOnMomsSide = Person::factory()->create(['first_name' => 'AuntOnMomsSide']);
    $grandpaDad = Person::factory()->create(['first_name' => 'GrandpaDad']);
    $grandmaDad = Person::factory()->create(['first_name' => 'GrandmaDad']);
    $uncleOnDadsSide = Person::factory()->create(['first_name' => 'UncleOnDadsSide']);
    $uncleWife = Person::factory()->create(['first_name' => 'UncleWife']);
    $uncleWifesParent = Person::factory()->create(['first_name' => 'UncleWifesParent']);
    $unrelated = Person::factory()->create(['first_name' => 'Unrelated']);

    Relationship::create(['person_a_id' => $mom->id, 'person_b_id' => $viewer->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $dad->id, 'person_b_id' => $viewer->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $grandpaMom->id, 'person_b_id' => $mom->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $grandmaMom->id, 'person_b_id' => $mom->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $grandpaMom->id, 'person_b_id' => $auntOnMomsSide->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $grandmaMom->id, 'person_b_id' => $auntOnMomsSide->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $grandpaDad->id, 'person_b_id' => $dad->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $grandmaDad->id, 'person_b_id' => $dad->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $grandpaDad->id, 'person_b_id' => $uncleOnDadsSide->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $grandmaDad->id, 'person_b_id' => $uncleOnDadsSide->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $uncleOnDadsSide->id, 'person_b_id' => $uncleWife->id, 'type' => 'spouse', 'status' => 'married']);
    Relationship::create(['person_a_id' => $uncleWifesParent->id, 'person_b_id' => $uncleWife->id, 'type' => 'parent_child']);
    Relationship::create(['person_a_id' => $mom->id, 'person_b_id' => $dad->id, 'type' => 'spouse', 'status' => 'married']);

    $ids = app(RelationshipService::class)->directRelativeIds($viewer);

    expect($ids)->toContain($viewer->id, $mom->id, $dad->id, $grandpaMom->id, $grandmaMom->id, $auntOnMomsSide->id, $grandpaDad->id, $grandmaDad->id, $uncleOnDadsSide->id, $uncleWife->id);
    expect($ids)->not->toContain($uncleWifesParent->id, $unrelated->id);
});

test('a lone person with no recorded relationships is only their own direct relative', function () {
    $person = Person::factory()->create();

    $ids = app(RelationshipService::class)->directRelativeIds($person);

    expect($ids->all())->toBe([$person->id]);
});
