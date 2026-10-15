<?php

namespace App\Services;

use App\Models\Person;
use Throwable;

/**
 * Computes a plain-English kinship label describing how one person relates
 * to another — "grandparent", "half-sibling", "second cousin, once removed",
 * "sibling-in-law", etc. — by finding their nearest common blood ancestor
 * and, failing that, checking one hop through marriage on either side.
 *
 * Terms are computed neutrally throughout ("aunt/uncle", "spouse") and only
 * gendered at the very end, based on the sex of whichever person the term
 * actually describes — never guessed. Anyone with no recorded sex keeps the
 * neutral term (e.g. "aunt/uncle") rather than defaulting to one or the
 * other. Cousin terms have no gendered English equivalent, so they're left
 * alone either way.
 */
class RelationshipLabelService
{
    /**
     * @return string|null a label like "your second cousin", or null if
     *                     no relationship could be determined (including
     *                     on any unexpected error — this is a nice-to-have
     *                     display, never worth a broken page over).
     */
    public function label(Person $viewer, Person $target): ?string
    {
        if ($viewer->id === $target->id) {
            return null;
        }

        try {
            $term = $this->computeTerm($viewer, $target);
        } catch (Throwable) {
            return null;
        }

        return $term ? "your {$term}" : null;
    }

    private function computeTerm(Person $viewer, Person $target): ?string
    {
        $blood = $this->bloodTerm($viewer, $target);
        if ($blood !== null) {
            return $this->gendered($blood, $target->sex);
        }

        if ($this->areSpouses($viewer, $target)) {
            return $this->gendered('spouse', $target->sex);
        }

        // Married into a blood relative's family: e.g. target is the spouse
        // of the viewer's sibling, or of the viewer's cousin.
        foreach ($target->spouses() as $targetSpouse) {
            $rel = $this->bloodTerm($viewer, $targetSpouse);
            if ($rel !== null) {
                return $this->inLawTerm($rel, $target, $targetSpouse);
            }
        }

        // The mirror case: the viewer married into the target's family.
        foreach ($viewer->spouses() as $viewerSpouse) {
            $rel = $this->bloodTerm($viewerSpouse, $target);
            if ($rel !== null) {
                return $this->inLawTerm($rel, $target, $viewerSpouse);
            }
        }

        return null;
    }

    private function areSpouses(Person $a, Person $b): bool
    {
        return $a->spouses()->contains('id', $b->id);
    }

    /**
     * Nearest common blood ancestor between $a and $b, translated into a
     * kinship term from $a's point of view — null if they share none.
     */
    private function bloodTerm(Person $a, Person $b): ?string
    {
        if ($a->id === $b->id) {
            return null;
        }

        $aAncestors = $this->ancestorDepths($a);
        $bAncestors = $this->ancestorDepths($b);

        $bestTotal = null;
        $bestUp = null;
        $bestDown = null;

        foreach ($aAncestors as $id => $up) {
            if (! isset($bAncestors[$id])) {
                continue;
            }

            $down = $bAncestors[$id];
            $total = $up + $down;

            if ($bestTotal === null || $total < $bestTotal) {
                $bestTotal = $total;
                $bestUp = $up;
                $bestDown = $down;
            }
        }

        if ($bestTotal === null) {
            return null;
        }

        return $this->termForDistance($bestUp, $bestDown, $a, $b);
    }

    /**
     * @return array<int, int> ancestor person id => generations up (0 = self)
     */
    private function ancestorDepths(Person $person): array
    {
        $depths = [$person->id => 0];
        $queue = [[$person, 0]];

        while ($queue !== []) {
            [$current, $depth] = array_shift($queue);

            foreach ($current->parents() as $parent) {
                if (! isset($depths[$parent->id])) {
                    $depths[$parent->id] = $depth + 1;
                    $queue[] = [$parent, $depth + 1];
                }
            }
        }

        return $depths;
    }

    private function termForDistance(int $up, int $down, Person $a, Person $b): string
    {
        if ($down === 0) {
            return $this->ancestorTerm($up);
        }

        if ($up === 0) {
            return $this->descendantTerm($down);
        }

        if ($up === 1 && $down === 1) {
            return $this->siblingTerm($a, $b);
        }

        if ($up === 1 && $down >= 2) {
            return $this->greatPrefix($down - 2).'niece/nephew';
        }

        if ($down === 1 && $up >= 2) {
            return $this->greatPrefix($up - 2).'aunt/uncle';
        }

        $degree = min($up, $down) - 1;
        $removed = abs($up - $down);

        return $this->cousinTerm($degree, $removed);
    }

    private function ancestorTerm(int $up): string
    {
        return match (true) {
            $up === 1 => 'parent',
            $up === 2 => 'grandparent',
            default => $this->greatPrefix($up - 2).'grandparent',
        };
    }

    private function descendantTerm(int $down): string
    {
        return match (true) {
            $down === 1 => 'child',
            $down === 2 => 'grandchild',
            default => $this->greatPrefix($down - 2).'grandchild',
        };
    }

    private function greatPrefix(int $n): string
    {
        return $n > 0 ? str_repeat('great-', $n) : '';
    }

    private function siblingTerm(Person $a, Person $b): string
    {
        $shared = $a->parents()->pluck('id')->intersect($b->parents()->pluck('id'))->count();

        return $shared >= 2 ? 'sibling' : 'half-sibling';
    }

    private function cousinTerm(int $degree, int $removed): string
    {
        $ordinal = match ($degree) {
            1 => 'first',
            2 => 'second',
            3 => 'third',
            4 => 'fourth',
            5 => 'fifth',
            default => $degree.'th',
        };

        $label = "{$ordinal} cousin";

        if ($removed > 0) {
            $removedWord = match ($removed) {
                1 => 'once',
                2 => 'twice',
                3 => 'three times',
                default => "{$removed} times",
            };
            $label .= ", {$removedWord} removed";
        }

        return $label;
    }

    /**
     * $inLaw is the person the label is actually about (their own sex
     * genders "sibling-in-law" itself); $bloodRelative is the blood
     * relative they married into — e.g. for "uncle's wife", $bloodRelative
     * (the uncle/aunt) genders "aunt/uncle" and $inLaw (the wife/husband)
     * genders "spouse".
     */
    private function inLawTerm(string $bloodTerm, Person $inLaw, Person $bloodRelative): string
    {
        $neutral = match ($bloodTerm) {
            'parent', 'child', 'sibling' => "{$bloodTerm}-in-law",
            'half-sibling' => 'sibling-in-law',
            default => null,
        };

        if ($neutral !== null) {
            return $this->gendered($neutral, $inLaw->sex);
        }

        return $this->gendered($bloodTerm, $bloodRelative->sex)."'s ".$this->gendered('spouse', $inLaw->sex);
    }

    /**
     * Swaps a neutral kinship term for its gendered form when $sex is
     * known, preserving any "great-" prefix already baked into $term.
     * Unknown/null sex (or a term with no gendered equivalent, like
     * "cousin") is returned unchanged.
     */
    private function gendered(string $term, ?string $sex): string
    {
        if ($sex !== 'male' && $sex !== 'female') {
            return $term;
        }

        // Longest/most specific suffix first — "grandparent" must be
        // checked before "parent", since it ends with "parent" too.
        $forms = [
            'parent-in-law' => ['male' => 'father-in-law', 'female' => 'mother-in-law'],
            'child-in-law' => ['male' => 'son-in-law', 'female' => 'daughter-in-law'],
            'sibling-in-law' => ['male' => 'brother-in-law', 'female' => 'sister-in-law'],
            'grandparent' => ['male' => 'grandfather', 'female' => 'grandmother'],
            'grandchild' => ['male' => 'grandson', 'female' => 'granddaughter'],
            'half-sibling' => ['male' => 'half-brother', 'female' => 'half-sister'],
            'aunt/uncle' => ['male' => 'uncle', 'female' => 'aunt'],
            'niece/nephew' => ['male' => 'nephew', 'female' => 'niece'],
            'parent' => ['male' => 'father', 'female' => 'mother'],
            'child' => ['male' => 'son', 'female' => 'daughter'],
            'sibling' => ['male' => 'brother', 'female' => 'sister'],
            'spouse' => ['male' => 'husband', 'female' => 'wife'],
        ];

        foreach ($forms as $neutral => $gendered) {
            if (str_ends_with($term, $neutral)) {
                return substr($term, 0, -strlen($neutral)).$gendered[$sex];
            }
        }

        return $term;
    }
}
