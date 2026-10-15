<?php

use App\Models\AudioRecording;
use App\Models\ChangeLogEntry;
use App\Models\Invite;
use App\Models\Location;
use App\Models\PageEditor;
use App\Models\Person;
use App\Models\Photo;
use App\Models\Relationship;
use App\Models\Revision;
use App\Models\Story;
use App\Models\Suggestion;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Read-only model API whitelist
    |--------------------------------------------------------------------------
    |
    | Maps a short, URL-safe key to the Eloquent model it exposes through
    | the read-only token API (routes/api.php). Only models listed here are
    | reachable — anything else 404s, regardless of whether the class
    | exists. `relations` whitelists which ?with= values are honored for
    | that model; anything not listed is silently ignored rather than
    | erroring, so a typo just means "no eager load" rather than a 500.
    |
    | Sensitive columns (User's password, 2FA secrets, etc.) don't need
    | listing here — they're already excluded from array/JSON serialization
    | via each model's own #[Hidden] attribute, same as every other place
    | in the app that serializes these models.
    |
    | Passkeys are deliberately not listed — pure credential data with no
    | business value for queries, so it's simplest to leave access to
    | this out entirely.
    |
    | `writable` opts a model into PATCH /api/v1/models/{key}/{id} — still
    | no create, no delete, and still restricted to that model's own
    | fillable columns. Defaults to false; only set it on models where
    | "correct a genealogy fact via the API" is a real, intended use case.
    | Users, invites, page-editors, etc. are deliberately never writable
    | here, even though they're readable — this isn't a general admin API.
    |
    */
    'models' => [
        'people' => [
            'model' => Person::class,
            'relations' => ['birthLocation', 'deathLocation', 'user', 'creator', 'avatarPhoto', 'photos', 'stories'],
            'writable' => true,
        ],
        'stories' => [
            'model' => Story::class,
            'relations' => ['location', 'people', 'photos', 'featuredPhoto', 'audioRecording', 'creator'],
        ],
        'relationships' => [
            'model' => Relationship::class,
            'relations' => ['personA', 'personB'],
            'writable' => true,
        ],
        'locations' => [
            'model' => Location::class,
            'relations' => [],
            'writable' => true,
        ],
        'photos' => [
            'model' => Photo::class,
            'relations' => ['people', 'stories', 'location', 'uploader'],
        ],
        'audio-recordings' => [
            'model' => AudioRecording::class,
            'relations' => ['stories', 'uploader'],
        ],
        'users' => [
            'model' => User::class,
            'relations' => ['person'],
        ],
        'invites' => [
            'model' => Invite::class,
            'relations' => ['person', 'inviter'],
        ],
        'page-editors' => [
            'model' => PageEditor::class,
            'relations' => ['editable', 'user'],
        ],
        'revisions' => [
            'model' => Revision::class,
            'relations' => ['revisable', 'user'],
        ],
        'suggestions' => [
            'model' => Suggestion::class,
            'relations' => ['suggestable', 'user', 'reviewer'],
        ],
        'change-log-entries' => [
            'model' => ChangeLogEntry::class,
            'relations' => [],
        ],
    ],

];
