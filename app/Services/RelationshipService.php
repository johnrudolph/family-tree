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
     * Create (or reuse) an explicit, removable sibling relationship. Unlike
     * parent/child or spouse, "sibling" has no inherent direction, so unlike
     * Relationship::create() elsewhere this checks both (a,b) and (b,a)
     * before inserting — the table's unique constraint alone wouldn't catch
     * a reversed duplicate.
     */
    public function addSibling(Person $a, Person $b): Relationship
    {
        $existing = Relationship::query()
            ->where('type', 'sibling')
            ->where(function ($query) use ($a, $b) {
                $query->where(['person_a_id' => $a->id, 'person_b_id' => $b->id])
                    ->orWhere(['person_a_id' => $b->id, 'person_b_id' => $a->id]);
            })
            ->first();

        return $existing ?? Relationship::create([
            'person_a_id' => $a->id,
            'person_b_id' => $b->id,
            'type' => 'sibling',
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
     * Mark two people as spouses if they aren't already linked one way or the
     * other — used for the "we'll mark them as married unless you say
     * otherwise" suggestion when adding a second parent to a child, so
     * re-selecting an already-linked couple is a no-op, not a duplicate-key
     * error.
     */
    public function linkSpouseIfMissing(Person $a, Person $b, string $status = 'married'): void
    {
        $exists = Relationship::query()
            ->where('type', 'spouse')
            ->where(function ($query) use ($a, $b) {
                $query->where(['person_a_id' => $a->id, 'person_b_id' => $b->id])
                    ->orWhere(['person_a_id' => $b->id, 'person_b_id' => $a->id]);
            })
            ->exists();

        if (! $exists) {
            $this->addSpouse($a, $b, $status);
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
     * People already related to this person in any way — directly (parent, child,
     * or spouse) or derived (siblings via shared parents) — excluded from "pick an
     * existing person" pickers, since picking someone already related would either
     * duplicate or contradict that relationship (e.g. a recorded parent can't also
     * be picked as a new sibling, and an existing sibling can't be picked again).
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

        // ->map() on an Eloquent collection stays an Eloquent collection even once
        // it holds plain ints, and Eloquent Collection::merge() assumes models
        // (it calls getKey()) — collect() first to get a plain Support Collection.
        $excludedIds = collect($relatedIds->all())
            ->push($person->id)
            ->merge($person->siblings()->pluck('id'));

        return Person::query()
            ->whereNotIn('id', $excludedIds)
            ->orderBy('first_name')
            ->get();
    }

    /**
     * A person's "direct relatives", for the People/Timeline/Map "only show
     * my direct relatives" filter: everyone connected by blood (ancestors,
     * descendants, and siblings, at any distance), plus each blood
     * relative's own directly-married spouse — but not that spouse's own
     * family. That boundary is the point: it keeps an uncle's wife, but
     * drops her parents and siblings, who aren't this person's relatives at
     * all, just in-laws of an in-law.
     *
     * @return Collection<int, int>
     */
    public function directRelativeIds(Person $person): Collection
    {
        $bloodAdjacency = [];
        $spouseAdjacency = [];

        Relationship::query()->get(['person_a_id', 'person_b_id', 'type'])->each(function (Relationship $relationship) use (&$bloodAdjacency, &$spouseAdjacency) {
            if ($relationship->type === 'parent_child' || $relationship->type === 'sibling') {
                $bloodAdjacency[$relationship->person_a_id][] = $relationship->person_b_id;
                $bloodAdjacency[$relationship->person_b_id][] = $relationship->person_a_id;
            } elseif ($relationship->type === 'spouse') {
                $spouseAdjacency[$relationship->person_a_id][] = $relationship->person_b_id;
                $spouseAdjacency[$relationship->person_b_id][] = $relationship->person_a_id;
            }
        });

        $bloodIds = [$person->id => true];
        $queue = [$person->id];

        while ($queue !== []) {
            $currentId = array_pop($queue);

            foreach ($bloodAdjacency[$currentId] ?? [] as $neighborId) {
                if (! isset($bloodIds[$neighborId])) {
                    $bloodIds[$neighborId] = true;
                    $queue[] = $neighborId;
                }
            }
        }

        $allIds = $bloodIds;

        foreach (array_keys($bloodIds) as $bloodId) {
            foreach ($spouseAdjacency[$bloodId] ?? [] as $spouseId) {
                $allIds[$spouseId] = true;
            }
        }

        return collect(array_keys($allIds));
    }
}
