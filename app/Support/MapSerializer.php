<?php

namespace App\Support;

class MapSerializer
{
    /**
     * Every birth, death, and story that has a geocoded location — built on
     * top of TimelineSerializer so the title/date/url/photo logic for each
     * event type only lives in one place.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function points(): array
    {
        return collect(TimelineSerializer::events())
            ->filter(fn (array $event) => $event['location'] !== null)
            ->map(fn (array $event) => [
                'type' => $event['type'],
                'title' => $event['title'],
                'date' => $event['date'],
                'date_precision' => $event['date_precision'],
                'latitude' => $event['location']['latitude'],
                'longitude' => $event['location']['longitude'],
                'precision' => $event['location']['precision'],
                'location_label' => $event['location']['label'],
                'url' => $event['url'],
                'avatar_url' => $event['avatar_url'],
                'featured_image_url' => $event['featured_image_url'],
            ])
            ->values()
            ->all();
    }
}
