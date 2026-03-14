<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductEntity::class)]
class ProductEntityTest extends TestCase
{
    public function testStringify(): void
    {
        $entity = new ProductEntity();
        $entity->setId('fooId');

        static::assertSame('', (string) $entity);

        $entity->setName('foo');

        static::assertSame('foo', (string) $entity);

        $entity->setTranslated([
            'name' => 'translated foo',
        ]);

        static::assertSame('translated foo', (string) $entity);
    }

    public function testOpenGraphFields(): void
    {
        $entity = new ProductEntity();
        $mediaId = Uuid::randomHex();

        static::assertNull($entity->getOpenGraphMediaId());
        static::assertNull($entity->getOpenGraphMedia());

        $entity->setOpenGraphMediaId($mediaId);
        static::assertSame($mediaId, $entity->getOpenGraphMediaId());

        $media = new MediaEntity();
        $media->setId($mediaId);
        $entity->setOpenGraphMedia($media);
        static::assertSame($media, $entity->getOpenGraphMedia());

        $entity->setOpenGraphMediaId(null);
        static::assertNull($entity->getOpenGraphMediaId());
    }
}
