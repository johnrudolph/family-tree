<?php

use App\Support\RichTextSanitizer;

test('it preserves formatting the editor toolbar can produce', function () {
    $html = '<h2>Title</h2><p><strong>bold</strong> <em>italic</em> <u>underline</u> <s>strike</s></p><ul><li>one</li></ul><ol><li>two</li></ol><blockquote>quote</blockquote><p><a href="https://example.com" rel="noopener noreferrer" target="_blank">link</a></p>';

    expect(RichTextSanitizer::clean($html))->toBe($html);
});

test('it strips script tags and their content entirely', function () {
    $result = RichTextSanitizer::clean('<p>hello</p><script>alert("xss")</script>');

    expect($result)->not->toContain('script')
        ->and($result)->not->toContain('alert')
        ->and($result)->toContain('hello');
});

test('it strips the style tag itself, leaving no executable CSS behind', function () {
    $result = RichTextSanitizer::clean('<p>hello</p><style>body{display:none}</style>');

    expect($result)->not->toContain('<style')
        ->and($result)->toContain('hello');
});

test('it strips event handler attributes', function () {
    $result = RichTextSanitizer::clean('<p onclick="alert(1)">click me</p>');

    expect($result)->not->toContain('onclick')
        ->and($result)->not->toContain('alert')
        ->and($result)->toContain('click me');
});

test('it drops disallowed elements but keeps their text', function () {
    $result = RichTextSanitizer::clean('<div><span>kept text</span></div>');

    expect($result)->not->toContain('<div>')
        ->and($result)->not->toContain('<span>')
        ->and($result)->toContain('kept text');
});

test('it rejects javascript: link schemes', function () {
    $result = RichTextSanitizer::clean('<a href="javascript:alert(1)">click</a>');

    expect($result)->not->toContain('javascript:');
});

test('it forces safe rel and target on links', function () {
    $result = RichTextSanitizer::clean('<a href="https://example.com">link</a>');

    expect($result)->toContain('rel="noopener noreferrer"')
        ->and($result)->toContain('target="_blank"');
});

test('it returns an empty string for null or blank input', function () {
    expect(RichTextSanitizer::clean(null))->toBe('');
    expect(RichTextSanitizer::clean('   '))->toBe('');
});
