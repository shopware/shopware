<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaEntity;
use Shopware\Core\Content\Product\ContentSystem\Mapping\ProductMediaToMediaProjection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductMediaToMediaProjection::class)]
class ProductMediaToMediaProjectionTest extends TestCase
{
    public function testDeclaresTheTypesItBridges(): void
    {
        $projection = new ProductMediaToMediaProjection();

        static::assertSame('product_media_to_media', $projection->name());
        static::assertSame(ProductMediaEntity::class, $projection->inputType());
        static::assertSame(MediaEntity::class, $projection->outputType());
    }

    public function testUnwrapsTheAssignmentToItsPicture(): void
    {
        $media = new MediaEntity();
        $media->setUniqueIdentifier(Uuid::randomHex());

        $assignment = new ProductMediaEntity();
        $assignment->setUniqueIdentifier(Uuid::randomHex());
        $assignment->setMedia($media);

        static::assertSame($media, (new ProductMediaToMediaProjection())->project($assignment));
    }

    /**
     * Read by the delivery layer as "resolved to nothing", which falls back to the picture the author chose —
     * the same outcome an absent cover produces.
     */
    public function testAnUnloadedPictureYieldsNull(): void
    {
        $assignment = new ProductMediaEntity();
        $assignment->setUniqueIdentifier(Uuid::randomHex());

        static::assertNull((new ProductMediaToMediaProjection())->project($assignment));
    }
}
