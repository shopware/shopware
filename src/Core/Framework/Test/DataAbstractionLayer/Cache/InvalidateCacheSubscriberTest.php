<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheClearer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\InvalidateCacheSubscriber;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class InvalidateCacheSubscriberTest extends TestCase
{
    use KernelTestBehaviour;

    public function testInvalidate(): void
    {
        $id = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $events = new NestedEventCollection([
            new EntityWrittenEvent(
                $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                [
                    new EntityWriteResult(
                        $id,
                        ['name' => 'test', 'id' => $id, 'stock' => 15, 'manufacturerId' => $id],
                        $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                        EntityWriteResult::OPERATION_INSERT,
                        new EntityExistence($this->getContainer()->get(ProductDefinition::class)->getEntityName(), ['id' => $id], true, false, false, [])
                    ),
                ],
                $context
            ),
            new EntityWrittenEvent(
                $this->getContainer()->get(ProductManufacturerDefinition::class)->getEntityName(),
                [
                    new EntityWriteResult(
                        $id,
                        ['name' => 'test', 'id' => $id, 'active' => true],
                        $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                        EntityWriteResult::OPERATION_INSERT,
                        new EntityExistence($this->getContainer()->get(ProductDefinition::class)->getEntityName(), ['id' => $id], true, false, false, [])
                    ),
                ],
                $context
            ),
            new EntityWrittenEvent(
                $this->getContainer()->get(ProductCategoryDefinition::class)->getEntityName(),
                [
                    new EntityWriteResult(
                        $id,
                        ['productId' => $id, 'categoryId' => $id],
                        $this->getContainer()->get(ProductDefinition::class)->getEntityName(),
                        EntityWriteResult::OPERATION_INSERT,
                        new EntityExistence($this->getContainer()->get(ProductDefinition::class)->getEntityName(), ['id' => $id], true, false, false, [])
                    ),
                ],
                $context
            ),
        ]);

        $event = new EntityWrittenContainerEvent($context, $events, []);

        $tags = [
            'product-' . $id,
            'product.name',
            'product.id',
            'product.stock',

            'product_manufacturer-' . $id,
            'product.product_manufacturer_id',
            'product_manufacturer.name',
            'product_manufacturer.id',

            'product_category-' . $id,
            'product_category.product_id',
            'category-' . $id,
            'product_category.category_id',
        ];

        $cache = $this->createMock(CacheClearer::class);
        $cache->expects(static::once())
            ->method('invalidateTags')
            ->with($tags);

        $generator = new EntityCacheKeyGenerator(
            $this->getContainer()->getParameter('kernel.cache.hash')
        );
        $registry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        $subscriber = new InvalidateCacheSubscriber($cache, $generator, $registry);

        $subscriber->entitiesWritten($event);
    }
}
