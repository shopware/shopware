<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Garan\GaranLabelInlineImage;
use Shopware\Core\Content\Product\Garan\GaranLabelProductValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelInlineImage::class)]
class GaranLabelInlineImageTest extends TestCase
{
    public function testEveryValidDurationRendersItsOwnLabel(): void
    {
        $inlineImage = new GaranLabelInlineImage();
        $durationFields = [];

        foreach (self::validDurations() as $months) {
            $name = $inlineImage->getName($months);
            static::assertSame(\sprintf('garan-label-nested-%d.png', $months), $name);

            $png = $inlineImage->render($name);
            static::assertIsString($png, 'No label for ' . $months . ' months');

            $size = getimagesizefromstring($png);
            static::assertIsArray($size);
            static::assertSame(
                [390, 60, \IMAGETYPE_PNG],
                [$size[0], $size[1], $size[2]],
                'The label is rendered at twice the 195x30 the mail displays'
            );

            $durationFields[$months] = $this->readDurationField($png);
        }

        static::assertCount(
            \count($durationFields),
            array_unique($durationFields),
            'Every duration has to end up with its own number in the label, so no two rows of the sprite may be shared'
        );
    }

    public function testRenderComposesTheDurationIntoTheEmptyField(): void
    {
        $inlineImage = new GaranLabelInlineImage();

        $label = $inlineImage->render('garan-label-nested-36.png');
        static::assertIsString($label);

        $base = file_get_contents(GaranLabelInlineImage::DIRECTORY . '/nested-label-base.png');
        static::assertIsString($base);

        static::assertNotSame(
            $this->readDurationField($base),
            $this->readDurationField($label),
            'The duration field of the base artwork is empty and has to be filled with the duration'
        );
        static::assertSame(
            $this->readArtwork($base),
            $this->readArtwork($label),
            'Everything outside the duration field has to stay untouched'
        );
    }

    /**
     * @return \Generator<string, array{int}>
     */
    public static function invalidDurationProvider(): \Generator
    {
        yield 'below the minimum' => [24];
        yield 'above the maximum' => [606];
        yield 'not a half year' => [33];
        yield 'zero' => [0];
        yield 'negative' => [-36];
    }

    #[DataProvider('invalidDurationProvider')]
    public function testGetNameReturnsNullForDurationsWithoutLabel(int $months): void
    {
        static::assertNull((new GaranLabelInlineImage())->getName($months));
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function invalidNameProvider(): \Generator
    {
        yield 'path traversal' => ['garan-label-nested-../../../../../composer.json'];
        yield 'foreign file' => ['composer.json'];
        yield 'duration without label' => ['garan-label-nested-999.png'];
        yield 'other extension' => ['garan-label-nested-36.svg'];
    }

    #[DataProvider('invalidNameProvider')]
    public function testRenderRejectsInvalidNames(string $name): void
    {
        static::assertNull((new GaranLabelInlineImage())->render($name));
    }

    public function testRenderCachesTheComposedImage(): void
    {
        $inlineImage = new GaranLabelInlineImage();

        $first = $inlineImage->render('garan-label-nested-36.png');
        static::assertIsString($first);
        static::assertSame($first, $inlineImage->render('garan-label-nested-36.png'));
    }

    public function testRenderReturnsNullWithoutArtwork(): void
    {
        static::assertNull((new GaranLabelInlineImage(__DIR__ . '/does-not-exist'))->render('garan-label-nested-36.png'));
    }

    public function testFindReferencedNamesReturnsEachLabelOnce(): void
    {
        $html = '<img src="cid:garan-label-nested-36.png"><img src="cid:garan-label-nested-30.png">'
            . '<img src="cid:garan-label-nested-36.png"><img src="cid:logo.png"><img src="data:image/png;base64,AA==">';

        static::assertSame(
            ['garan-label-nested-36.png', 'garan-label-nested-30.png'],
            (new GaranLabelInlineImage())->findReferencedNames($html)
        );
    }

    /**
     * @return list<int>
     */
    private static function validDurations(): array
    {
        return range(
            GaranLabelProductValidator::MINIMUM_MONTHS,
            GaranLabelProductValidator::MAXIMUM_MONTHS,
            GaranLabelProductValidator::STEP_MONTHS
        );
    }

    private function readDurationField(string $png): string
    {
        return $this->crop($png, 10, 65);
    }

    private function readArtwork(string $png): string
    {
        return $this->crop($png, 75, 315);
    }

    private function crop(string $png, int $x, int $width): string
    {
        $image = imagecreatefromstring($png);
        static::assertNotFalse($image);

        $pixels = '';
        for ($column = $x; $column < $x + $width; ++$column) {
            for ($row = 0; $row < 60; ++$row) {
                $pixels .= \chr(imagecolorat($image, $column, $row) & 0xFF);
            }
        }

        return md5($pixels);
    }
}
