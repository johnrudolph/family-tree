<?php

namespace App\Services;

use App\Models\Person;
use App\Models\Relationship;
use Illuminate\Support\Collection;

class RelationshipService
{
    public function addParentChild(Person $parent, Person $child): Relationship
    {
        return Relationship::create([
            'person_a_id' => $parent->id,
            'person_b_id' => $child->id,
            'type' => 'parent_child',
        ]);
    }

    public function addSpouse(Person $a, Person $b, ?string $status = 'married'): Relationship
    {
        return Relationship::create([
            'person_a_id' => $a->id,
            'person_b_id' => $b->id,
            'type' => 'spouse',
            'status' => $status,
        ]);
    }

    /**
     * Link a parent to a child if that link doesn't already exist — used for the
     * "also mark as parent" suggestions, where re-selecting an already-linked
     * person should just be a no-op rather than a duplicate-key error.
     */
    public function linkParentChildIfMissing(Person $parent, Person $child): void
    {
        $exists = Relationship::query()
            ->where('person_a_id', $parent->id)
            ->where('person_b_id', $child->id)
            ->where('type', 'parent_child')
            ->exists();

        if (! $exists) {
            $this->addParentChild($parent, $child);
        }
    }

    /**
     * A person's existing children — offered as "also mark as their child" pills
     * when adding a new spouse, so a step-parent doesn't need a second trip.
     *
     * @return Collection<int, Person>
     */
    public function candidateStepchildren(Person $person): Collection
    {
        return $person->children();
    }

    /**
     * A person's existing spouses — offered as "also mark as parent" pills when
     * adding a new child, so both parents get linked in one step.
     *
     * @return Collection<int, Person>
     */
    public function candidateCoParents(Person $person): Collection
    {
        return $person->spouses();
    }

    /**
     * A person's existing recorded parents — used for the "sibling" relationship
     * shortcut, which links a new person to all of them at once.
     *
     * @return Collection<int, Person>
     */
    public function existingParents(Person $person): Collection
    {
        return $person->parents();
    }

    /**
     * People already directly related to this person in any way (parent, child,
     * or spouse) — excluded from "pick an existing person" pickers, since picking
     * someone already related would either duplicate or contradict that relationship
     * (e.g. a recorded parent can't also be picked as a new sibling).
     *
     * @return Collection<int, Person>
     */
    public function candidatesFor(Person $person): Collection
    {
        $relatedIds = Relationship::query()
            ->where('person_a_id', $person->id)
            ->orWhere('person_b_id', $person->id)
            ->get()
            ->map(fn (Relationship $r) => $r->person_a_id === $person->id ? $r->person_b_id : $r->person_a_id);

        return Person::query()
            ->whereNotIn('id', $relatedIds->push($person->id))
            ->orderBy('first_name')
            ->get();
    }
}
