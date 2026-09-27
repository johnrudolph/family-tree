<?php

namespace App\Support;

class PersonDateInput
{
    /**
     * Resolves a precision-toggle date input (an "exact date" vs "year
     * only" radio, paired with a date field and a year field) into a
     * [date, precision] pair ready to persist. Year-only stores a Jan 1
     * placeholder, matching how stories already store imprecise dates.
     * Nothing entered in the active mode resolves to no date at all,
     * rather than forcing a value — birth/death dates are optional.
     *
     * @return array{0: ?string, 1: string}
     */
    public static function resolve(string $precision, ?string $exactDate, ?string $year): array
    {
        if ($precision === 'year' && $year) {
            return ["{$year}-01-01", 'year'];
        }

        if ($precision === 'exact' && $exactDate) {
            return [$exactDate, 'exact'];
        }

        return [null, 'unknown'];
    }
}
