<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Thumbnail\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\MediaType\MediaType;
use Shopware\Core\Content\Media\Thumbnail\Processor\GdImageThumbnailProcessor;
use Shopware\Core\Content\Media\Thumbnail\Processor\ThumbnailProcessorInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(GdImageThumbnailProcessor::class)]
class GdImageThumbnailProcessorTest extends TestCase
{
    private ThumbnailProcessorInterface $processor;

    private object $image;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new GdImageThumbnailProcessor();
        $this->image = $this->processor->createImageFromString((string) file_get_contents(__DIR__ . '/../shopware-logo.png'));
        static::assertSame(266, $this->processor->getHeight($this->image));
        static::assertSame(499, $this->processor->getWidth($this->image));
    }

    public function testItRotates(): void
    {
        $image = $this->processor->rotate($this->image, 90);

        static::assertSame(499, $this->processor->getHeight($image));
        static::assertSame(266, $this->processor->getWidth($image));
    }

    #[DataProvider('mediaTypeDataProvider')]
    public function testItCreatesNewImages(MediaType $mediaType): void
    {
        $image = $this->processor->createNewImage(
            $this->image,
            $mediaType,
            ['width' => 499, 'height' => 266],
            ['width' => 250, 'height' => 133]
        );

        static::assertSame(133, $this->processor->getHeight($image));
        static::assertSame(250, $this->processor->getWidth($image));
    }

    public static function mediaTypeDataProvider(): \Generator
    {
        yield 'image' => [new ImageType()];
        yield 'image transparent' => [(new ImageType())->addFlag(ImageType::TRANSPARENT)];
    }

    /**
     * @param list<string> $expectedMimeTypes
     */
    #[DataProvider('mimeTypeDataProvider')]
    public function testItConvertsImage(string $mimeType, array $expectedMimeTypes): void
    {
        $binary = $this->processor->convertImage(
            $this->image,
            $mimeType,
            50
        );

        $stream = fopen('php://memory', 'r+');
        static::assertIsResource($stream);
        fwrite($stream, $binary);
        rewind($stream);

        static::assertContains(mime_content_type($stream), $expectedMimeTypes);
    }

    public static function mimeTypeDataProvider(): \Generator
    {
        yield 'image/png' => ['image/png', ['image/png']];
        yield 'image/gif' => ['image/gif', ['image/gif']];
        yield 'image/jpg' => ['image/jpg', ['image/jpg', 'image/jpeg']];
        yield 'image/jpeg' => ['image/jpeg', ['image/jpg', 'image/jpeg']];
        yield 'image/webp' => ['image/webp', ['image/webp']];
        yield 'image/avif' => ['image/avif', ['image/avif']];
    }
}
