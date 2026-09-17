<?php

namespace App\Support;

use App\Models\Person;
use App\Models\Relationship;

class FamilyTreeSerializer
{
    /**
     * Serialize the whole tree into family-chart's Datum[] shape:
     * https://github.com/donatso/family-chart — {id, data, rels: {parents, spouses, children}}.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toChartData(): array
    {
        // Eager-load both — hasAccount()/photoUrl() are called once per
        // person below, and without this each one is a fresh N+1 query.
        $people = Person::query()->with(['user', 'media'])->get()->keyBy('id');

        $parentsOf = [];
        $childrenOf = [];
        $spousesOf = [];

        Relationship::query()->get()->each(function (Relationship $relationship) use (&$parentsOf, &$childrenOf, &$spousesOf) {
            if ($relationship->type === 'parent_child') {
                $parentsOf[$relationship->person_b_id][] = (string) $relationship->person_a_id;
                $childrenOf[$relationship->person_a_id][] = (string) $relationship->person_b_id;
            } elseif ($relationship->type === 'spouse') {
                $spousesOf[$relationship->person_a_id][] = (string) $relationship->person_b_id;
                $spousesOf[$relationship->person_b_id][] = (string) $relationship->person_a_id;
            }
        });

        return $people->map(fn ($person) => [
            'id' => (string) $person->id,
            'data' => [
                'first name' => $person->use_preferred_name_everywhere && $person->preferred_name
                    ? $person->preferred_name
                    : $person->first_name,
                'last name' => $person->use_preferred_name_everywhere && $person->preferred_name
                    ? ''
                    : ($person->last_name ?? ''),
                'birthday' => $person->dob?->format('Y'),
                'dob_sort' => $person->dob?->format('Y-m-d'),
                'avatar' => $person->hasConsented() ? $person->photoUrl() : null,
                'has_account' => $person->hasAccount(),
                'url' => $person->wikiShowUrl(),
                'living' => $person->is_living,
            ],
            'rels' => [
                'parents' => $parentsOf[$person->id] ?? [],
                'spouses' => $spousesOf[$person->id] ?? [],
                'children' => $childrenOf[$person->id] ?? [],
            ],
        ])->values()->all();
    }

    /**
     * The best single starting point for "zoomed out, show everyone": the
     * root ancestor (no recorded parents) of the largest connected group of
     * people — connected via either a parent/child or spouse link. family-
     * chart only ever renders ancestry+progeny+spouses reachable from one
     * main_id, so centering on the viewer's own lineage can miss whole
     * branches (in-laws' families, people connected only through marriage,
     * etc.) that this instead surfaces by picking the widest group outright.
     */
    public static function widestRootPersonId(): ?int
    {
        $peopleIds = Person::query()->pluck('id')->all();

        if ($peopleIds === []) {
            return null;
        }

        $adjacency = array_fill_keys($peopleIds, []);

        Relationship::query()->get(['person_a_id', 'person_b_id'])->each(function (Relationship $relationship) use (&$adjacency) {
            $adjacency[$relationship->person_a_id][] = $relationship->person_b_id;
            $adjacency[$relationship->person_b_id][] = $relationship->person_a_id;
        });

        $visited = [];
        $components = [];

        foreach ($peopleIds as $id) {
            if (isset($visited[$id])) {
                continue;
            }

            $component = [];
            $stack = [$id];
            $visited[$id] = true;

            while ($stack !== []) {
                $current = array_pop($stack);
                $component[] = $current;

                foreach ($adjacency[$current] as $neighborId) {
                    if (! isset($visited[$neighborId])) {
                        $visited[$neighborId] = true;
                        $stack[] = $neighborId;
                    }
                }
            }

            $components[] = $component;
        }

        usort($components, fn (array $a, array $b) => count($b) <=> count($a));
        $largest = $components[0];

        $hasRecordedParent = Relationship::query()
            ->where('type', 'parent_child')
            ->pluck('person_b_id')
            ->flip();

        foreach ($largest as $id) {
            if (! $hasRecordedParent->has($id)) {
                return $id;
            }
        }

        return $largest[0];
    }
}
