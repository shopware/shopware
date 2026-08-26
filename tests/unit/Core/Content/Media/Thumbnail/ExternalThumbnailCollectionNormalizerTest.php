<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Thumbnail;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailCollection;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailCollectionNormalizer;
use Shopware\Core\Content\Media\Thumbnail\ExternalThumbnailData;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ExternalThumbnailCollectionNormalizer::class)]
class ExternalThumbnailCollectionNormalizerTest extends TestCase
{
    private DenormalizerInterface&Stub $innerDenormalizer;

    private NormalizerInterface&Stub $innerNormalizer;

    private ExternalThumbnailCollectionNormalizer $externalThumbnailCollectionNormalizer;

    protected function setUp(): void
    {
        $this->innerDenormalizer = static::createStub(DenormalizerInterface::class);
        $this->innerNormalizer = static::createStub(NormalizerInterface::class);

        $this->externalThumbnailCollectionNormalizer = new ExternalThumbnailCollectionNormalizer();
        $this->externalThumbnailCollectionNormalizer->setDenormalizer($this->innerDenormalizer);
        $this->externalThumbnailCollectionNormalizer->setNormalizer($this->innerNormalizer);
    }

    public function testDenormalizeBuildsCollectionFromArray(): void
    {
        $thumbnail1 = new ExternalThumbnailData('http://localhost:8000/thumb-200.jpg', 200, 200);
        $thumbnail2 = new ExternalThumbnailData('http://localhost:8000/thumb-400.jpg', 400, 400);

        $innerDenormalizer = $this->createMock(DenormalizerInterface::class);
        $innerDenormalizer->expects($this->exactly(2))
            ->method('denormalize')
            ->willReturnOnConsecutiveCalls($thumbnail1, $thumbnail2);
        $this->externalThumbnailCollectionNormalizer->setDenormalizer($innerDenormalizer);

        $result = $this->externalThumbnailCollectionNormalizer->denormalize(
            [
                ['url' => 'http://localhost:8000/thumb-200.jpg', 'width' => 200, 'height' => 200],
                ['url' => 'http://localhost:8000/thumb-400.jpg', 'width' => 400, 'height' => 400],
            ],
            ExternalThumbnailCollection::class
        );

        static::assertCount(2, $result);
        static::assertSame($thumbnail1, $result->get(0));
        static::assertSame($thumbnail2, $result->get(1));
    }

    public function testDenormalizeReturnsEmptyCollectionForEmptyArray(): void
    {
        $innerDenormalizer = $this->createMock(DenormalizerInterface::class);
        $innerDenormalizer->expects($this->never())->method('denormalize');
        $this->externalThumbnailCollectionNormalizer->setDenormalizer($innerDenormalizer);

        $result = $this->externalThumbnailCollectionNormalizer->denormalize([], ExternalThumbnailCollection::class);

        static::assertCount(0, $result);
    }

    public function testDenormalizeReturnsEmptyCollectionForNonArray(): void
    {
        $innerDenormalizer = $this->createMock(DenormalizerInterface::class);
        $innerDenormalizer->expects($this->never())->method('denormalize');
        $this->externalThumbnailCollectionNormalizer->setDenormalizer($innerDenormalizer);

        $result = $this->externalThumbnailCollectionNormalizer->denormalize('not-an-array', ExternalThumbnailCollection::class);

        static::assertCount(0, $result);
    }

    public function testSupportsDenormalizationReturnsTrueForExternalThumbnailCollection(): void
    {
        static::assertTrue($this->externalThumbnailCollectionNormalizer->supportsDenormalization([], ExternalThumbnailCollection::class));
    }

    public function testSupportsDenormalizationReturnsFalseForOtherTypes(): void
    {
        static::assertFalse($this->externalThumbnailCollectionNormalizer->supportsDenormalization([], \stdClass::class));
    }

    public function testSupportsDenormalizationReturnsFalseWhenAlreadyCalled(): void
    {
        $context = [ExternalThumbnailCollectionNormalizer::class . '::DENORMALIZE_ALREADY_CALLED' => true];

        static::assertFalse($this->externalThumbnailCollectionNormalizer->supportsDenormalization([], ExternalThumbnailCollection::class, null, $context));
    }

    public function testNormalizeDelegatesToInnerNormalizer(): void
    {
        $collection = new ExternalThumbnailCollection();
        $expected = [['url' => 'http://localhost:8000/thumb.jpg', 'width' => 100, 'height' => 100]];

        $innerNormalizer = $this->createMock(NormalizerInterface::class);
        $innerNormalizer->expects($this->once())
            ->method('normalize')
            ->with($collection, null, static::arrayHasKey(ExternalThumbnailCollectionNormalizer::class . '::NORMALIZE_ALREADY_CALLED'))
            ->willReturn($expected);
        $this->externalThumbnailCollectionNormalizer->setNormalizer($innerNormalizer);

        $result = $this->externalThumbnailCollectionNormalizer->normalize($collection);

        static::assertSame($expected, $result);
    }

    public function testSupportsNormalizationReturnsTrueForExternalThumbnailCollection(): void
    {
        static::assertTrue($this->externalThumbnailCollectionNormalizer->supportsNormalization(new ExternalThumbnailCollection()));
    }

    public function testSupportsNormalizationReturnsFalseWhenAlreadyCalled(): void
    {
        $context = [ExternalThumbnailCollectionNormalizer::class . '::NORMALIZE_ALREADY_CALLED' => true];

        static::assertFalse($this->externalThumbnailCollectionNormalizer->supportsNormalization(new ExternalThumbnailCollection(), null, $context));
    }

    public function testSupportsNormalizationReturnsFalseForOtherTypes(): void
    {
        static::assertFalse($this->externalThumbnailCollectionNormalizer->supportsNormalization(new \stdClass()));
    }

    public function testGetSupportedTypesReturnsNotCacheable(): void
    {
        $types = $this->externalThumbnailCollectionNormalizer->getSupportedTypes(null);

        static::assertArrayHasKey(ExternalThumbnailCollection::class, $types);
        static::assertFalse($types[ExternalThumbnailCollection::class]);
    }
}
