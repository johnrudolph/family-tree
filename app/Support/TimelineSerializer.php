<?php

namespace App\Support;

use App\Models\Person;
use App\Models\Story;

class TimelineSerializer
{
    /**
     * Unified birth/death/story events for the timeline view, sorted
     * chronologically. Dates are plain 'Y-m-d' strings and precision is
     * passed through as-is ('exact'/'year'/'unknown' etc.) — the client
     * decides how to label and place imprecise dates, this just assembles
     * the data.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function events(): array
    {
        $events = [...self::birthEvents(), ...self::deathEvents(), ...self::storyEvents()];

        usort($events, fn (array $a, array $b) => $a['date'] <=> $b['date']);

        return $events;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function birthEvents(): array
    {
        return Person::query()->whereNotNull('dob')->get()->map(fn (Person $person): array => [
            'type' => 'birth',
            'date' => $person->dob->toDateString(),
            'date_precision' => $person->dob_precision,
            'end_date' => null,
            'end_date_precision' => null,
            'title' => __(':name is born', ['name' => $person->fullName()]).($person->birth_city ? ' '.__('in :city', ['city' => $person->birth_city]) : ''),
            'person_id' => $person->id,
            'story_id' => null,
            'avatar_url' => $person->photoUrl(),
            'featured_image_url' => null,
            'body_html' => null,
            'url' => route('people.show', $person),
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function deathEvents(): array
    {
        return Person::query()->whereNotNull('dod')->get()->map(fn (Person $person): array => [
            'type' => 'death',
            'date' => $person->dod->toDateString(),
            'date_precision' => 'exact',
            'end_date' => null,
            'end_date_precision' => null,
            'title' => __(':name dies', ['name' => $person->fullName()]).($person->death_city ? ' '.__('in :city', ['city' => $person->death_city]) : ''),
            'person_id' => $person->id,
            'story_id' => null,
            'avatar_url' => $person->photoUrl(),
            'featured_image_url' => null,
            'body_html' => null,
            'url' => route('people.show', $person),
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function storyEvents(): array
    {
        return Story::query()->get()->map(function (Story $story): array {
            $featured = $story->featuredImage();

            return [
                'type' => 'story',
                'date' => $story->start_date->toDateString(),
                'date_precision' => $story->start_date_precision,
                'end_date' => $story->end_date?->toDateString(),
                'end_date_precision' => $story->end_date_precision,
                'title' => $story->title,
                'person_id' => null,
                'story_id' => $story->id,
                'avatar_url' => null,
                'featured_image_url' => $featured?->getTemporaryUrl(now()->addHour()),
                'body_html' => StoryBodyParser::render($story->body ?? ''),
                'url' => route('stories.show', $story),
            ];
        })->all();
    }
}
