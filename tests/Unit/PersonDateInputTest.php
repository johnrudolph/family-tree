<?php

use App\Support\PersonDateInput;

test('year precision with a year builds a Jan 1 placeholder date', function () {
    expect(PersonDateInput::resolve('year', null, '1954'))->toBe(['1954-01-01', 'year']);
});

test('exact precision with a date returns it as-is', function () {
    expect(PersonDateInput::resolve('exact', '1954-03-03', null))->toBe(['1954-03-03', 'exact']);
});

test('year precision without a year resolves to nothing known', function () {
    expect(PersonDateInput::resolve('year', null, null))->toBe([null, 'unknown']);
});

test('exact precision without a date resolves to nothing known', function () {
    expect(PersonDateInput::resolve('exact', null, null))->toBe([null, 'unknown']);
});

test('a stray value in the inactive mode is ignored', function () {
    expect(PersonDateInput::resolve('year', '1954-03-03', null))->toBe([null, 'unknown']);
    expect(PersonDateInput::resolve('exact', null, '1954'))->toBe([null, 'unknown']);
});
