<?php

use App\Models\ApiToken;

test('a request with no bearer token is rejected', function () {
    $this->getJson('/api/v1/models')->assertUnauthorized();
});

test('a request with an invalid bearer token is rejected', function () {
    $this->withToken('not-a-real-token')
        ->getJson('/api/v1/models')
        ->assertUnauthorized();
});

test('a request with a valid bearer token is accepted', function () {
    $plaintext = ApiToken::issue('test');

    $this->withToken($plaintext)
        ->getJson('/api/v1/models')
        ->assertOk();
});

test('issuing a token never stores or exposes the plaintext again', function () {
    $plaintext = ApiToken::issue('test');

    $token = ApiToken::first();

    expect($token->token_hash)->not->toBe($plaintext);
    expect($token->toArray())->not->toHaveKey('token_hash');
});

test('using a token updates its last_used_at timestamp', function () {
    $plaintext = ApiToken::issue('test');
    $token = ApiToken::first();

    expect($token->last_used_at)->toBeNull();

    $this->withToken($plaintext)->getJson('/api/v1/models');

    expect($token->fresh()->last_used_at)->not->toBeNull();
});

test('a revoked token no longer works', function () {
    $plaintext = ApiToken::issue('test');
    ApiToken::first()->delete();

    $this->withToken($plaintext)
        ->getJson('/api/v1/models')
        ->assertUnauthorized();
});
