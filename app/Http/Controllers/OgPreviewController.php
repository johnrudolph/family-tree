<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Story;
use Illuminate\View\View;

/**
 * A public, unauthenticated page that exists purely so link-unfurling bots
 * (Slack, iMessage, etc) can read Open Graph tags for content that
 * otherwise sits behind this invite-only app's login wall. A human landing
 * here is immediately bounced to the real, authenticated page.
 */
class OgPreviewController extends Controller
{
    public function person(Person $person): View
    {
        return view('og.preview', [
            'title' => $person->fullName(),
            'imageUrl' => $person->ogImageUrl(),
            'redirectUrl' => route('people.show', $person),
        ]);
    }

    public function story(Story $story): View
    {
        return view('og.preview', [
            'title' => $story->title,
            'imageUrl' => $story->ogImageUrl(),
            'redirectUrl' => route('stories.show', $story),
        ]);
    }
}
