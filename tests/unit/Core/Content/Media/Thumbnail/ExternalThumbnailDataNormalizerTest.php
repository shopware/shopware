<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Thumbnail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailData;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailDataNormalizer;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ExternalThumbnailDataNormalizer::class)]
class ExternalThumbnailDataNormalizerTest extends TestCase
{
    private ExternalThumbnailDataNormalizer $denormalizer;

    private NormalizerInterface&MockObject $innerNormalizer;

    protected function setUp(): void
    {
        $this->innerNormalizer = $this->createMock(NormalizerInterface::class);

        $this->denormalizer = new ExternalThumbnailDataNormalizer();
        $this->denormalizer->setNormalizer($this->innerNormalizer);
    }

    public function testDenormalize(): void
    {
        $data = [
            'url' => 'http://localhost:8000/thumb-200.jpg',
            'width' => 200,
            'height' => 150,
        ];

        $result = $this->denormalizer->denormalize($data, ExternalThumbnailData::class);

        static::assertSame('http://localhost:8000/thumb-200.jpg', $result->url);
        static::assertSame(200, $result->width);
        static::assertSame(150, $result->height);
    }

    public function testDenormalizeConvertsStringDimensionsToInt(): void
    {
        $data = [
            'url' => 'http://localhost:8000/thumb.jpg',
            'width' => '800',
            'height' => '600',
        ];

        $result = $this->denormalizer->denormalize($data, ExternalThumbnailData::class);

        static::assertSame(800, $result->width);
        static::assertSame(600, $result->height);
    }

    public function testDenormalizeThrowsOnNonArray(): void
    {
        $this->expectExceptionObject(
            MediaException::invalidThumbnailData(
                'Thumbnail data must be an object with "url", "width" and "height" fields'
            )
        );

        $this->denormalizer->denormalize('not-an-array', ExternalThumbnailData::class);
    }

    public function testDenormalizeThrowsOnMissingUrl(): void
    {
        $this->expectExceptionObject(
            MediaException::invalidThumbnailData('Each thumbnail must have "url", "width" and "height" fields')
        );

        $this->denormalizer->denormalize(['width' => 200, 'height' => 200], ExternalThumbnailData::class);
    }

    public function testDenormalizeThrowsOnMissingWidth(): void
    {
        $this->expectExceptionObject(
            MediaException::invalidThumbnailData('Each thumbnail must have "url", "width" and "height" fields')
        );

        $this->denormalizer->denormalize(['url' => 'http://localhost:8000/thumb.jpg', 'height' => 200], ExternalThumbnailData::class);
    }

    public function testDenormalizeThrowsOnMissingHeight(): void
    {
        $this->expectExceptionObject(
            MediaException::invalidThumbnailData('Each thumbnail must have "url", "width" and "height" fields')
        );

        $this->denormalizer->denormalize(['url' => 'http://localhost:8000/thumb.jpg', 'width' => 200], ExternalThumbnailData::class);
    }

    public function testDenormalizeThrowsOnInvalidWidth(): void
    {
        $data = [
            'url' => 'http://localhost:8000/thumb.jpg',
            'width' => '0',
            'height' => '600',
        ];

        $this->expectExceptionObject(MediaException::invalidDimension('width', 0));

        $this->denormalizer->denormalize($data, ExternalThumbnailData::class);
    }

    public function testDenormalizeThrowsOnInvalidHeight(): void
    {
        $data = [
            'url' => 'http://localhost:8000/thumb.jpg',
            'width' => '800',
            'height' => '0',
        ];

        $this->expectExceptionObject(MediaException::invalidDimension('height', 0));

        $this->denormalizer->denormalize($data, ExternalThumbnailData::class);
    }

    public function testNormalizeDelegatesToInnerNormalizer(): void
    {
        $data = new ExternalThumbnailData('http://localhost:8000/thumb.jpg', 100, 100);
        $expected = ['url' => 'http://localhost:8000/thumb.jpg', 'width' => 100, 'height' => 100];

        $this->innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($data, null, static::arrayHasKey(ExternalThumbnailDataNormalizer::class . '::NORMALIZE_ALREADY_CALLED'))
            ->willReturn($expected);

        $result = $this->denormalizer->normalize($data);

        static::assertSame($expected, $result);
    }

    public function testSupportsDenormalizationReturnsTrueForExternalThumbnailData(): void
    {
        static::assertTrue($this->denormalizer->supportsDenormalization([], ExternalThumbnailData::class));
    }

    public function testSupportsDenormalizationReturnsFalseForOtherTypes(): void
    {
        static::assertFalse($this->denormalizer->supportsDenormalization([], \stdClass::class));
        static::assertFalse($this->denormalizer->supportsDenormalization([], 'string'));
    }

    public function testSupportsNormalizationReturnsTrueForExternalThumbnailData(): void
    {
        $data = new ExternalThumbnailData('http://localhost:8000/thumb.jpg', 100, 100);
        static::assertTrue($this->denormalizer->supportsNormalization($data));
    }

    public function testSupportsNormalizationReturnsFalseWhenAlreadyCalled(): void
    {
        $data = new ExternalThumbnailData('http://localhost:8000/thumb.jpg', 100, 100);
        $context = [ExternalThumbnailDataNormalizer::class . '::NORMALIZE_ALREADY_CALLED' => true];
        static::assertFalse($this->denormalizer->supportsNormalization($data, null, $context));
    }

    public function testSupportsNormalizationReturnsFalseForOtherTypes(): void
    {
        static::assertFalse($this->denormalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypesReturnsCacheableEntry(): void
    {
        $types = $this->denormalizer->getSupportedTypes(null);

        static::assertArrayHasKey(ExternalThumbnailData::class, $types);
        static::assertTrue($types[ExternalThumbnailData::class]);
    }
}
