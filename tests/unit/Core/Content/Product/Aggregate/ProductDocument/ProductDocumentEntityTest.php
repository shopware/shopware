<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentEntity::class)]
class ProductDocumentEntityTest extends TestCase
{
    public function testAccessors(): void
    {
        $product = new ProductEntity();
        $media = new MediaEntity();

        $entity = new ProductDocumentEntity();
        $entity->setProductId('product-id');
        $entity->setProductVersionId('product-version-id');
        $entity->setMediaId('media-id');
        $entity->setTitle('Document title');
        $entity->setPosition(3);
        $entity->setProduct($product);
        $entity->setMedia($media);

        static::assertSame('product-id', $entity->getProductId());
        static::assertSame('product-version-id', $entity->getProductVersionId());
        static::assertSame('media-id', $entity->getMediaId());
        static::assertSame('Document title', $entity->getTitle());
        static::assertSame(3, $entity->getPosition());
        static::assertSame($product, $entity->getProduct());
        static::assertSame($media, $entity->getMedia());

        $entity->setTitle(null);

        static::assertNull($entity->getTitle());
    }
}
