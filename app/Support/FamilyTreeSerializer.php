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
        $people = Person::query()->get()->keyBy('id');

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
                'first name' => $person->preferred_name ?: $person->first_name,
                'last name' => $person->last_name ?? '',
                'birthday' => $person->dob?->format('Y'),
                'avatar' => $person->hasConsented() ? $person->photoUrl() : null,
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
}
