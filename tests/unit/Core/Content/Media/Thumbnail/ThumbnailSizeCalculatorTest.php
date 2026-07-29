<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Thumbnail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailSizeCalculator;

/**
 * @internal
 *
 * @phpstan-import-type ImageSize from ThumbnailService
 */
#[CoversClass(ThumbnailSizeCalculator::class)]
class ThumbnailSizeCalculatorTest extends TestCase
{
    /**
     * @param ImageSize $imageSize
     * @param ImageSize $preferredThumbnailSize
     * @param ImageSize $expectedSize
     */
    #[DataProvider('thumbnailSizeProvider')]
    public function testCalculateSize(array $imageSize, array $preferredThumbnailSize, array $expectedSize): void
    {
        $thumbnailSizeEntity = new MediaThumbnailSizeEntity();
        $thumbnailSizeEntity->setWidth($preferredThumbnailSize['width']);
        $thumbnailSizeEntity->setHeight($preferredThumbnailSize['height']);

        $thumbnailSizeCalculator = new ThumbnailSizeCalculator();
        $calculatedSize = $thumbnailSizeCalculator->calculate($imageSize, $thumbnailSizeEntity);

        static::assertSame($expectedSize, $calculatedSize);
    }

    /**
     * @return iterable<string, array{0: ImageSize, 1: ImageSize, 2: ImageSize}>
     */
    public static function thumbnailSizeProvider(): iterable
    {
        yield 'landscape image scales to preferred width' => [['width' => 2000, 'height' => 1000], ['width' => 800, 'height' => 600], ['width' => 800, 'height' => 400]];
        yield 'landscape image is constrained by preferred width' => [['width' => 2000, 'height' => 1000], ['width' => 600, 'height' => 800], ['width' => 600, 'height' => 300]];
        yield 'landscape image is constrained by square preferred size' => [['width' => 2000, 'height' => 1000], ['width' => 800, 'height' => 800], ['width' => 800, 'height' => 400]];
        yield 'portrait image is constrained by preferred height' => [['width' => 1000, 'height' => 2000], ['width' => 800, 'height' => 600], ['width' => 300, 'height' => 600]];
        yield 'portrait image scales to preferred height' => [['width' => 1000, 'height' => 2000], ['width' => 600, 'height' => 800], ['width' => 400, 'height' => 800]];
        yield 'portrait image is constrained by square preferred size' => [['width' => 1000, 'height' => 2000], ['width' => 800, 'height' => 800], ['width' => 400, 'height' => 800]];
        yield 'square image is constrained by preferred height' => [['width' => 1000, 'height' => 1000], ['width' => 800, 'height' => 600], ['width' => 600, 'height' => 600]];
        yield 'square image is constrained by preferred width' => [['width' => 1000, 'height' => 1000], ['width' => 600, 'height' => 800], ['width' => 600, 'height' => 600]];
        yield 'square image scales to square preferred size' => [['width' => 1000, 'height' => 1000], ['width' => 800, 'height' => 800], ['width' => 800, 'height' => 800]];
        yield 'wide image is constrained by preferred height' => [['width' => 1200, 'height' => 1000], ['width' => 800, 'height' => 600], ['width' => 720, 'height' => 600]];
        yield 'wide image is constrained by preferred width' => [['width' => 1200, 'height' => 1000], ['width' => 600, 'height' => 800], ['width' => 600, 'height' => 500]];
        yield 'wide image is constrained by square preferred size' => [['width' => 1200, 'height' => 1000], ['width' => 800, 'height' => 800], ['width' => 800, 'height' => 667]];
        yield 'tall image is constrained by preferred height' => [['width' => 1000, 'height' => 1200], ['width' => 800, 'height' => 600], ['width' => 500, 'height' => 600]];
        yield 'tall image is constrained by preferred width' => [['width' => 1000, 'height' => 1200], ['width' => 600, 'height' => 800], ['width' => 600, 'height' => 720]];
        yield 'tall image is constrained by square preferred size' => [['width' => 1000, 'height' => 1200], ['width' => 800, 'height' => 800], ['width' => 667, 'height' => 800]];
        yield 'panorama image is constrained by preferred width' => [['width' => 1560, 'height' => 723], ['width' => 730, 'height' => 500], ['width' => 730, 'height' => 338]];
        yield 'portrait panorama image is constrained by preferred height' => [['width' => 723, 'height' => 1560], ['width' => 730, 'height' => 500], ['width' => 232, 'height' => 500]];
    }
}
