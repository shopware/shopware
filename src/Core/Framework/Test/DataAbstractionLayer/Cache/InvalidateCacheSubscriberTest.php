<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\InvalidateCacheSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Struct\Uuid;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class InvalidateCacheSubscriberTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
    }

    public function testInvalidate()
    {
        $id = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext();

        $events = new NestedEventCollection([
            new EntityWrittenEvent(
                ProductDefinition::class,
                [new EntityWriteResult(
                    $id,
                    ['name' => 'test', 'id' => $id, 'stock' => 15, 'manufacturerId' => $id],
                    new EntityExistence(ProductDefinition::class, ['id' => $id], true, false, false, [])
                )],
                $context
            ),
            new EntityWrittenEvent(
                ProductManufacturerDefinition::class,
                [new EntityWriteResult(
                    $id,
                    ['name' => 'test', 'id' => $id, 'active' => true],
                    new EntityExistence(ProductDefinition::class, ['id' => $id], true, false, false, [])
                )],
                $context
            ),
            new EntityWrittenEvent(
                ProductCategoryDefinition::class,
                [new EntityWriteResult(
                    $id,
                    ['productId' => $id, 'categoryId' => $id],
                    new EntityExistence(ProductDefinition::class, ['id' => $id], true, false, false, [])
                )],
                $context
            ),
        ]);

        $event = new EntityWrittenContainerEvent($context, $events, []);

        $tags = [
            'product-' . $id,
            'product.name',
            'product.id',
            'product.stock',
            'product.product_manufacturer_id',

            'product_manufacturer-' . $id,
            'product_manufacturer.name',
            'product_manufacturer.id',

            'product_category-' . $id,
            'product_category.product_id',
            'product_category.category_id',
        ];

        $cache = $this->createMock(TagAwareAdapter::class);
        $cache->expects(static::exactly(1))
            ->method('invalidateTags')
            ->with($tags);

        $generator = new EntityCacheKeyGenerator();
        $subscriber = new InvalidateCacheSubscriber($cache, $generator);

        $subscriber->entitiesWritten($event);
    }
}
