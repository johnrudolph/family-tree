<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\Story;
use App\Services\OgImageService;
use Illuminate\Http\Response;

class OgImageController extends Controller
{
    public function __construct(private readonly OgImageService $images)
    {
        //
    }

    public function person(Person $person): Response
    {
        return $this->respond($this->images->forPerson($person));
    }

    public function story(Story $story): Response
    {
        return $this->respond($this->images->forStory($story));
    }

    private function respond(string $png): Response
    {
        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
