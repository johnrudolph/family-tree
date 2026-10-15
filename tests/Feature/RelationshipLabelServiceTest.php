<?php

use App\Models\Person;
use App\Models\Relationship;
use App\Services\RelationshipLabelService;

function linkParentChild(Person $parent, Person $child): void
{
    Relationship::factory()->parentChild()->create(['person_a_id' => $parent->id, 'person_b_id' => $child->id]);
}

function marrySpouses(Person $a, Person $b): void
{
    Relationship::factory()->create(['person_a_id' => $a->id, 'person_b_id' => $b->id, 'type' => 'spouse']);
}

test('it returns null for the same person', function () {
    $person = Person::factory()->create();

    expect(app(RelationshipLabelService::class)->label($person, $person))->toBeNull();
});

test('it returns null for two unrelated people', function () {
    $a = Person::factory()->create();
    $b = Person::factory()->create();

    expect(app(RelationshipLabelService::class)->label($a, $b))->toBeNull();
});

test('it labels a parent and child', function () {
    $parent = Person::factory()->create();
    $child = Person::factory()->create();
    linkParentChild($parent, $child);

    expect(app(RelationshipLabelService::class)->label($child, $parent))->toBe('your parent');
    expect(app(RelationshipLabelService::class)->label($parent, $child))->toBe('your child');
});

test('it labels grandparents and great-grandparents', function () {
    $great = Person::factory()->create();
    $grand = Person::factory()->create();
    $parent = Person::factory()->create();
    $me = Person::factory()->create();
    linkParentChild($great, $grand);
    linkParentChild($grand, $parent);
    linkParentChild($parent, $me);

    expect(app(RelationshipLabelService::class)->label($me, $grand))->toBe('your grandparent');
    expect(app(RelationshipLabelService::class)->label($me, $great))->toBe('your great-grandparent');
    expect(app(RelationshipLabelService::class)->label($great, $me))->toBe('your great-grandchild');
});

test('it labels full siblings and half-siblings', function () {
    $mom = Person::factory()->create();
    $dad = Person::factory()->create();
    $fullSibling = Person::factory()->create();
    $me = Person::factory()->create();
    $halfSibling = Person::factory()->create();
    linkParentChild($mom, $me);
    linkParentChild($dad, $me);
    linkParentChild($mom, $fullSibling);
    linkParentChild($dad, $fullSibling);
    linkParentChild($mom, $halfSibling);

    expect(app(RelationshipLabelService::class)->label($me, $fullSibling))->toBe('your sibling');
    expect(app(RelationshipLabelService::class)->label($me, $halfSibling))->toBe('your half-sibling');
});

test('it labels aunts/uncles and nieces/nephews, including great-', function () {
    $grandparent = Person::factory()->create();
    $parent = Person::factory()->create();
    $parentsSibling = Person::factory()->create();
    $me = Person::factory()->create();
    linkParentChild($grandparent, $parent);
    linkParentChild($grandparent, $parentsSibling);
    linkParentChild($parent, $me);

    expect(app(RelationshipLabelService::class)->label($me, $parentsSibling))->toBe('your aunt/uncle');
    expect(app(RelationshipLabelService::class)->label($parentsSibling, $me))->toBe('your niece/nephew');

    $grandparentsSibling = Person::factory()->create();
    $greatGrandparent = Person::factory()->create();
    linkParentChild($greatGrandparent, $grandparent);
    linkParentChild($greatGrandparent, $grandparentsSibling);

    expect(app(RelationshipLabelService::class)->label($me, $grandparentsSibling))->toBe('your great-aunt/uncle');
    expect(app(RelationshipLabelService::class)->label($grandparentsSibling, $me))->toBe('your great-niece/nephew');
});

test('it labels first and second cousins, with "removed" for a generation gap', function () {
    $grandparent = Person::factory()->create();
    $parentA = Person::factory()->create();
    $parentB = Person::factory()->create();
    $me = Person::factory()->create();
    $firstCousin = Person::factory()->create();
    linkParentChild($grandparent, $parentA);
    linkParentChild($grandparent, $parentB);
    linkParentChild($parentA, $me);
    linkParentChild($parentB, $firstCousin);

    expect(app(RelationshipLabelService::class)->label($me, $firstCousin))->toBe('your first cousin');

    $firstCousinsChild = Person::factory()->create();
    linkParentChild($firstCousin, $firstCousinsChild);

    expect(app(RelationshipLabelService::class)->label($me, $firstCousinsChild))->toBe('your first cousin, once removed');

    $greatGrandparent = Person::factory()->create();
    $grandparentB = Person::factory()->create();
    $parentC = Person::factory()->create();
    $secondCousin = Person::factory()->create();
    linkParentChild($greatGrandparent, $grandparent);
    linkParentChild($greatGrandparent, $grandparentB);
    linkParentChild($grandparentB, $parentC);
    linkParentChild($parentC, $secondCousin);

    expect(app(RelationshipLabelService::class)->label($me, $secondCousin))->toBe('your second cousin');
});

test('it labels a spouse', function () {
    $a = Person::factory()->create();
    $b = Person::factory()->create();
    marrySpouses($a, $b);

    expect(app(RelationshipLabelService::class)->label($a, $b))->toBe('your spouse');
});

test('it labels in-laws one hop through marriage', function () {
    $me = Person::factory()->create();
    $spouse = Person::factory()->create();
    marrySpouses($me, $spouse);

    $spousesParent = Person::factory()->create();
    linkParentChild($spousesParent, $spouse);
    expect(app(RelationshipLabelService::class)->label($me, $spousesParent))->toBe('your parent-in-law');

    $spousesSibling = Person::factory()->create();
    linkParentChild($spousesParent, $spousesSibling);
    expect(app(RelationshipLabelService::class)->label($me, $spousesSibling))->toBe('your sibling-in-law');

    $myChild = Person::factory()->create();
    linkParentChild($me, $myChild);
    $childsSpouse = Person::factory()->create();
    marrySpouses($myChild, $childsSpouse);
    expect(app(RelationshipLabelService::class)->label($me, $childsSpouse))->toBe('your child-in-law');
});

test('it falls back to a possessive for a spouse of a more distant relative', function () {
    $grandparent = Person::factory()->create();
    $parentA = Person::factory()->create();
    $parentB = Person::factory()->create();
    $me = Person::factory()->create();
    $firstCousin = Person::factory()->create();
    linkParentChild($grandparent, $parentA);
    linkParentChild($grandparent, $parentB);
    linkParentChild($parentA, $me);
    linkParentChild($parentB, $firstCousin);

    $cousinsSpouse = Person::factory()->create();
    marrySpouses($firstCousin, $cousinsSpouse);

    expect(app(RelationshipLabelService::class)->label($me, $cousinsSpouse))->toBe("your first cousin's spouse");
});

test('it labels a parent and child by sex when known', function () {
    $father = Person::factory()->create(['sex' => 'male']);
    $mother = Person::factory()->create(['sex' => 'female']);
    $son = Person::factory()->create(['sex' => 'male']);
    $daughter = Person::factory()->create(['sex' => 'female']);
    linkParentChild($father, $son);
    linkParentChild($mother, $daughter);

    expect(app(RelationshipLabelService::class)->label($son, $father))->toBe('your father');
    expect(app(RelationshipLabelService::class)->label($father, $son))->toBe('your son');
    expect(app(RelationshipLabelService::class)->label($daughter, $mother))->toBe('your mother');
    expect(app(RelationshipLabelService::class)->label($mother, $daughter))->toBe('your daughter');
});

test('a person with no recorded sex still gets the neutral term', function () {
    $parent = Person::factory()->create(['sex' => null]);
    $child = Person::factory()->create(['sex' => null]);
    linkParentChild($parent, $child);

    expect(app(RelationshipLabelService::class)->label($child, $parent))->toBe('your parent');
    expect(app(RelationshipLabelService::class)->label($parent, $child))->toBe('your child');
});

test('it genders grandparents, great-grandparents, siblings, aunts/uncles, and nieces/nephews', function () {
    $great = Person::factory()->create(['sex' => 'female']);
    $grand = Person::factory()->create(['sex' => 'male']);
    $parent = Person::factory()->create();
    $me = Person::factory()->create(['sex' => 'female']);
    $aunt = Person::factory()->create(['sex' => 'female']);
    linkParentChild($great, $grand);
    linkParentChild($grand, $parent);
    linkParentChild($grand, $aunt);
    linkParentChild($parent, $me);

    expect(app(RelationshipLabelService::class)->label($me, $grand))->toBe('your grandfather');
    expect(app(RelationshipLabelService::class)->label($me, $great))->toBe('your great-grandmother');
    expect(app(RelationshipLabelService::class)->label($great, $me))->toBe('your great-granddaughter');
    expect(app(RelationshipLabelService::class)->label($me, $aunt))->toBe('your aunt');
    expect(app(RelationshipLabelService::class)->label($aunt, $me))->toBe('your niece');
});

test('it genders a spouse as husband or wife', function () {
    $husband = Person::factory()->create(['sex' => 'male']);
    $wife = Person::factory()->create(['sex' => 'female']);
    marrySpouses($husband, $wife);

    expect(app(RelationshipLabelService::class)->label($wife, $husband))->toBe('your husband');
    expect(app(RelationshipLabelService::class)->label($husband, $wife))->toBe('your wife');
});

test('it genders in-laws by the in-law\'s own sex', function () {
    $me = Person::factory()->create();
    $spouse = Person::factory()->create();
    marrySpouses($me, $spouse);

    $spousesFather = Person::factory()->create(['sex' => 'male']);
    linkParentChild($spousesFather, $spouse);
    expect(app(RelationshipLabelService::class)->label($me, $spousesFather))->toBe('your father-in-law');

    $spousesSister = Person::factory()->create(['sex' => 'female']);
    linkParentChild($spousesFather, $spousesSister);
    expect(app(RelationshipLabelService::class)->label($me, $spousesSister))->toBe('your sister-in-law');
});

test('it genders both halves of a more distant relative\'s spouse', function () {
    $grandparent = Person::factory()->create();
    $parentA = Person::factory()->create();
    $parentB = Person::factory()->create();
    $me = Person::factory()->create();
    $uncle = Person::factory()->create(['sex' => 'male']);
    linkParentChild($grandparent, $parentA);
    linkParentChild($grandparent, $parentB);
    linkParentChild($parentA, $me);
    linkParentChild($grandparent, $uncle);

    $uncleWife = Person::factory()->create(['sex' => 'female']);
    marrySpouses($uncle, $uncleWife);

    expect(app(RelationshipLabelService::class)->label($me, $uncleWife))->toBe("your uncle's wife");
});

test('it returns null rather than throwing for a person with no relationships at all', function () {
    $me = Person::factory()->create();
    $stranger = Person::factory()->create();

    expect(app(RelationshipLabelService::class)->label($me, $stranger))->toBeNull();
});
