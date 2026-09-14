<?php

namespace App\Support;

use Jfcherng\Diff\DiffHelper;

class SuggestionDiffer
{
    /**
     * Word-level inline HTML diff for a single field's before/after text.
     * Pair with the "diff-wrapper" CSS from resources/views/partials/diff-styles.blade.php.
     */
    public static function fieldDiffHtml(mixed $old, mixed $new): string
    {
        $oldText = self::toDiffableString($old);
        $newText = self::toDiffableString($new);

        if ($oldText === $newText) {
            return '';
        }

        return DiffHelper::calculate(
            $oldText,
            $newText,
            'Inline',
            ['context' => 3],
            ['detailLevel' => 'word', 'lineNumbers' => false, 'showHeader' => false],
        );
    }

    private static function toDiffableString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if (is_array($value)) {
            return implode("\n", $value);
        }

        return (string) $value;
    }
}
