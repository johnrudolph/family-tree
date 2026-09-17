<?php

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class RichTextSanitizer
{
    /**
     * Sanitize HTML from Flux's rich text editor (bios, stories) down to the
     * exact set of tags its toolbar can produce. This is the server-side
     * safety net — the editor's own output is trusted for formatting, but
     * never for what actually reaches the database, since ~100 members can
     * author this content and a request can always be forged client-side.
     */
    public static function clean(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        $config = (new HtmlSanitizerConfig)
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('code')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('a', ['href'])
            ->allowLinkSchemes(['https', 'mailto'])
            ->allowRelativeLinks(false)
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->forceAttribute('a', 'target', '_blank')
            ->dropElement('script')
            ->dropElement('style');

        return (new HtmlSanitizer($config))->sanitize($html);
    }
}
