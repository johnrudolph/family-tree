<?php

namespace App\Services;

use App\Models\Person;
use App\Models\Story;
use Illuminate\Support\Facades\Storage;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

/**
 * Composites the 1200x630 preview image used for link previews (Slack,
 * iMessage, etc) when a story or person is shared — the page's photo on one
 * side, its title and the site name on the other.
 */
class OgImageService
{
    private const WIDTH = 1200;

    private const HEIGHT = 630;

    private const PADDING = 64;

    private const PHOTO_WIDTH = 520;

    private const BACKGROUND = '#18181b';

    private const DIVIDER = '#3f3f46';

    private const ACCENT = '#3b82f6';

    private const SITE_NAME_COLOR = '#a1a1aa';

    private const TITLE_COLOR = '#fafafa';

    public function forPerson(Person $person): string
    {
        return $this->render($person->fullName(), $person->featuredPhoto());
    }

    public function forStory(Story $story): string
    {
        return $this->render($story->title, $story->featuredImage());
    }

    private function render(string $title, ?Media $photo): string
    {
        $canvas = new Imagick;
        $canvas->newImage(self::WIDTH, self::HEIGHT, new ImagickPixel(self::BACKGROUND));
        $canvas->setImageFormat('png');

        $accentBar = new ImagickDraw;
        $accentBar->setFillColor(new ImagickPixel(self::ACCENT));
        $accentBar->rectangle(0, 0, self::WIDTH, 6);
        $canvas->drawImage($accentBar);

        $textWidth = self::WIDTH - self::PADDING * 2;
        $photoImage = $photo ? $this->readPhoto($photo) : null;

        if ($photoImage) {
            $photoImage->cropThumbnailImage(self::PHOTO_WIDTH, self::HEIGHT);
            $canvas->compositeImage($photoImage, Imagick::COMPOSITE_OVER, self::WIDTH - self::PHOTO_WIDTH, 0);
            $photoImage->destroy();

            $divider = new ImagickDraw;
            $divider->setFillColor(new ImagickPixel(self::DIVIDER));
            $divider->rectangle(self::WIDTH - self::PHOTO_WIDTH - 1, 0, self::WIDTH - self::PHOTO_WIDTH + 1, self::HEIGHT);
            $canvas->drawImage($divider);

            $textWidth = self::WIDTH - self::PHOTO_WIDTH - self::PADDING * 2;
        }

        $this->drawSiteName($canvas);
        $this->drawTitle($canvas, $title, $textWidth);

        $blob = $canvas->getImageBlob();
        $canvas->destroy();

        return $blob;
    }

    private function readPhoto(Media $media): ?Imagick
    {
        try {
            $image = new Imagick;
            $image->readImageBlob(Storage::disk($media->disk)->get($media->getPathRelativeToRoot()));
            $image->autoOrient();

            return $image;
        } catch (Throwable) {
            return null;
        }
    }

    private function drawSiteName(Imagick $canvas): void
    {
        $this->drawTreeMark($canvas, self::PADDING, self::PADDING - 4);

        $text = new ImagickDraw;
        $text->setFont(resource_path('fonts/InstrumentSans-SemiBold.ttf'));
        $text->setFontSize(22);
        $text->setFillColor(new ImagickPixel(self::SITE_NAME_COLOR));
        $text->annotation(self::PADDING + 68, self::PADDING + 36, 'BELZ FAMILY TREE');
        $canvas->drawImage($text);
    }

    /**
     * A scaled-up redraw of the app's tree logo mark (see
     * resources/views/components/app-logo-icon.blade.php), so the preview
     * image carries the same branding as the app itself.
     */
    private function drawTreeMark(Imagick $canvas, float $x, float $y): void
    {
        $scale = 1.35;
        $point = fn (float $px, float $py): array => [$x + $px * $scale, $y + $py * $scale];

        $draw = new ImagickDraw;
        $draw->setStrokeColor(new ImagickPixel(self::SITE_NAME_COLOR));
        $draw->setStrokeWidth(2.2);
        $draw->setFillColor(new ImagickPixel('none'));

        $lines = [
            [[20, 33.5], [20, 25]],
            [[20, 25], [9, 15]],
            [[20, 25], [31, 15]],
            [[9, 15], [9, 7]],
            [[9, 15], [3, 9]],
            [[9, 15], [15, 9]],
            [[31, 15], [31, 7]],
            [[31, 15], [25, 9]],
            [[31, 15], [37, 9]],
        ];

        foreach ($lines as [$from, $to]) {
            [$x1, $y1] = $point(...$from);
            [$x2, $y2] = $point(...$to);
            $draw->line($x1, $y1, $x2, $y2);
        }

        $draw->setStrokeColor(new ImagickPixel('none'));
        $draw->setFillColor(new ImagickPixel(self::SITE_NAME_COLOR));

        $circles = [[20, 37, 4], [9, 19, 3.25], [31, 19, 3.25], [3, 5, 2.5], [15, 5, 2.5], [25, 5, 2.5], [37, 5, 2.5]];

        foreach ($circles as [$cx, $cy, $r]) {
            [$px, $py] = $point($cx, $cy);
            $draw->circle($px, $py, $px + $r * $scale, $py);
        }

        $canvas->drawImage($draw);
    }

    private function drawTitle(Imagick $canvas, string $title, int $maxWidth): void
    {
        $font = resource_path('fonts/InstrumentSans-Bold.ttf');
        $fontSize = 58;
        $lineHeight = 70;

        $draw = new ImagickDraw;
        $draw->setFont($font);
        $draw->setFontSize($fontSize);
        $draw->setFillColor(new ImagickPixel(self::TITLE_COLOR));

        $y = 260;
        foreach ($this->wrapText($canvas, $title, $font, $fontSize, $maxWidth, maxLines: 3) as $line) {
            $draw->annotation(self::PADDING, $y, $line);
            $y += $lineHeight;
        }

        $canvas->drawImage($draw);
    }

    /**
     * Greedily wraps $text to fit within $maxWidth, capping at $maxLines
     * and ellipsizing the last line if words remain unplaced.
     *
     * @return array<int, string>
     */
    private function wrapText(Imagick $canvas, string $text, string $font, int $fontSize, int $maxWidth, int $maxLines): array
    {
        $metrics = new ImagickDraw;
        $metrics->setFont($font);
        $metrics->setFontSize($fontSize);

        $words = preg_split('/\s+/', trim($text)) ?: [];
        $lineCount = count($words);
        $lines = [];
        $current = '';
        $i = 0;

        while ($i < $lineCount && count($lines) < $maxLines) {
            $candidate = $current === '' ? $words[$i] : "{$current} {$words[$i]}";
            $width = $canvas->queryFontMetrics($metrics, $candidate)['textWidth'];

            if ($width > $maxWidth && $current !== '') {
                $lines[] = $current;
                $current = '';

                continue;
            }

            $current = $candidate;
            $i++;
        }

        if ($current !== '' && count($lines) < $maxLines) {
            $lines[] = $current;
        }

        if ($i < $lineCount && $lines !== []) {
            $lines[count($lines) - 1] = rtrim($lines[count($lines) - 1]).'…';
        }

        return $lines;
    }
}
