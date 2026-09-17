<?php

namespace App\Support;

use App\Models\Person;
use App\Models\Story;
use Illuminate\Support\Collection;

class StoryBodyParser
{
    private const PATTERN = '/\[\[(.+?)\]\]/u';

    /**
     * Resolve [[Person Name]] tags in a sanitized story body into links to
     * that person's page. A tag that doesn't match a real person (typo, or
     * someone since renamed) renders as the original bracketed text rather
     * than a broken link — the body is user-typed and can't be trusted to
     * always resolve, and leaving the brackets visible signals to an editor
     * that the tag needs fixing.
     */
    public static function render(string $html): string
    {
        $people = self::peopleByName();

        $result = preg_replace_callback(self::PATTERN, function (array $matches) use ($people) {
            $person = $people->get(self::normalize($matches[1]));

            if (! $person) {
                return $matches[0];
            }

            return '<a href="'.e(route('people.show', $person)).'" wire:navigate class="font-medium text-blue-600 hover:underline dark:text-blue-400">'.e($person->fullName()).'</a>';
        }, $html);

        return $result ?? $html;
    }

    /**
     * @return Collection<int, int> unique person IDs tagged in the body, in first-appearance order
     */
    public static function taggedPersonIds(string $html): Collection
    {
        $people = self::peopleByName();

        preg_match_all(self::PATTERN, $html, $matches);

        return collect($matches[1])
            ->map(fn (string $name) => $people->get(self::normalize($name)))
            ->filter()
            ->map(fn (Person $person) => $person->id)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, Story>
     */
    public static function storiesTagging(Person $person): Collection
    {
        return Story::query()->get()
            ->filter(fn (Story $story) => self::taggedPersonIds($story->body ?? '')->contains($person->id))
            ->values();
    }

    private static function normalize(string $name): string
    {
        return mb_strtolower(trim(html_entity_decode($name, ENT_QUOTES)));
    }

    /**
     * @return Collection<string, Person> keyed by lowercase full name
     */
    private static function peopleByName(): Collection
    {
        return Person::query()->get()->keyBy(fn (Person $person) => mb_strtolower($person->fullName()));
    }
}
