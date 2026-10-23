<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Aggregate\ProductDocument;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentEntity;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationCollection;
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
        $translations = new ProductDocumentTranslationCollection();

        $entity = new ProductDocumentEntity();
        $entity->setProductId('product-id');
        $entity->setProductVersionId('product-version-id');
        $entity->setPosition(3);
        $entity->setName('Datasheet');
        $entity->setMediaId('media-id');
        $entity->setCustomFields(['foo' => 'bar']);
        $entity->setProduct($product);
        $entity->setMedia($media);
        $entity->setTranslations($translations);

        static::assertSame('product-id', $entity->getProductId());
        static::assertSame('product-version-id', $entity->getProductVersionId());
        static::assertSame(3, $entity->getPosition());
        static::assertSame('Datasheet', $entity->getName());
        static::assertSame('media-id', $entity->getMediaId());
        static::assertSame(['foo' => 'bar'], $entity->getCustomFields());
        static::assertSame($product, $entity->getProduct());
        static::assertSame($media, $entity->getMedia());
        static::assertSame($translations, $entity->getTranslations());
    }

    public function testNameAndFileAreEmptyUntilLoaded(): void
    {
        $entity = new ProductDocumentEntity();

        static::assertNull($entity->getName());
        static::assertNull($entity->getMediaId());
        static::assertNull($entity->getMedia());
        static::assertNull($entity->getTranslations());
    }
}
