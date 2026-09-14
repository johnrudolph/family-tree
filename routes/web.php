<?php

use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::livewire('invites/{token}', 'pages::invites.accept')->name('invites.accept');

Route::livewire('onboarding/security', 'pages::onboarding.security')
    ->middleware(['auth'])
    ->name('onboarding.security');

Route::middleware(['auth', 'verified', 'two-factor.enabled'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::livewire('tree', 'pages::tree.index')->name('tree.index');

    Route::livewire('admin/invites', 'pages::invites.invite-person')->name('invites.create');

    Route::livewire('people', 'pages::people.index')->name('people.index');
    Route::livewire('people/{person}/edit', 'pages::people.edit')->name('people.edit');
    Route::livewire('people/{person}/suggest', 'pages::people.suggest')->name('people.suggest');
    Route::livewire('people/{person}/suggestions', 'pages::people.suggestions')->name('people.suggestions');
    Route::livewire('people/{person}/history', 'pages::people.history')->name('people.history');
    Route::livewire('people/{person}/enrich', 'pages::people.enrich')->name('people.enrich');
    Route::livewire('people/{person}', 'pages::people.show')->name('people.show');

    Route::livewire('stories/create', 'pages::stories.create')->name('stories.create');
    Route::livewire('stories/{story:slug}/edit', 'pages::stories.edit')->name('stories.edit');
    Route::livewire('stories/{story:slug}/suggest', 'pages::stories.suggest')->name('stories.suggest');
    Route::livewire('stories/{story:slug}/suggestions', 'pages::stories.suggestions')->name('stories.suggestions');
    Route::livewire('stories/{story:slug}/history', 'pages::stories.history')->name('stories.history');
    Route::livewire('stories/{story:slug}', 'pages::stories.show')->name('stories.show');
    Route::livewire('stories', 'pages::stories.index')->name('stories.index');
});

require __DIR__.'/settings.php';
