<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\SalesChannel\SeoResolverData;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoResolverData::class)]
class SeoResolverDataTest extends TestCase
{
    private SeoResolverData $seoResolverData;

    protected function setUp(): void
    {
        $this->seoResolverData = new SeoResolverData();
    }

    public function testGetEntitiesReturnsEmptyArrayInitially(): void
    {
        static::assertSame([], $this->seoResolverData->getEntities());
    }

    public function testAddSingleEntityAndGetEntities(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('product', $entity);

        static::assertSame(['product'], $this->seoResolverData->getEntities());
    }

    public function testAddMultipleEntitiesWithDifferentNames(): void
    {
        $productEntity = $this->createMockEntity('product-id-1');
        $categoryEntity = $this->createMockEntity('category-id-1');

        $this->seoResolverData->add('product', $productEntity);
        $this->seoResolverData->add('category', $categoryEntity);

        $entities = $this->seoResolverData->getEntities();
        static::assertCount(2, $entities);
        static::assertContains('product', $entities);
        static::assertContains('category', $entities);
    }

    public function testAddSameEntityTwiceDoesNotDuplicateInEntityList(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('product', $entity);
        $this->seoResolverData->add('product', $entity);

        static::assertSame(['product'], $this->seoResolverData->getEntities());
    }

    public function testGetIdsReturnsSingleIdAfterAddingEntity(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('product', $entity);

        static::assertSame(['entity-id-1'], $this->seoResolverData->getIds('product'));
    }

    public function testGetIdsReturnsMultipleIdsForSameEntityType(): void
    {
        $entity1 = $this->createMockEntity('entity-id-1');
        $entity2 = $this->createMockEntity('entity-id-2');

        $this->seoResolverData->add('product', $entity1);
        $this->seoResolverData->add('product', $entity2);

        $ids = $this->seoResolverData->getIds('product');
        static::assertCount(2, $ids);
        static::assertContains('entity-id-1', $ids);
        static::assertContains('entity-id-2', $ids);
    }

    public function testGetIdsDoesNotReturnDuplicateIdsForSameEntity(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('product', $entity);
        $this->seoResolverData->add('product', $entity);

        static::assertSame(['entity-id-1'], $this->seoResolverData->getIds('product'));
    }

    public function testGetAllReturnsSingleEntityForGivenEntityNameAndId(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('product', $entity);

        $entities = $this->seoResolverData->getAll('product', 'entity-id-1');
        static::assertCount(1, $entities);
        static::assertSame($entity, $entities[array_key_first($entities)]);
    }

    public function testGetAllReturnsMultipleEntitiesForSameIdButDifferentObjects(): void
    {
        $entity1 = $this->createMockEntity('entity-id-1');
        $entity2 = $this->createMockEntity('entity-id-1'); // Same ID, different object

        $this->seoResolverData->add('product', $entity1);
        $this->seoResolverData->add('product', $entity2);

        $entities = $this->seoResolverData->getAll('product', 'entity-id-1');
        static::assertCount(2, $entities);
        static::assertContains($entity1, $entities);
        static::assertContains($entity2, $entities);
    }

    public function testGetAllReturnsSameEntityOnlyOnceWhenAddedMultipleTimes(): void
    {
        $entity = $this->createMockEntity('entity-id-1');

        $this->seoResolverData->add('product', $entity);
        $this->seoResolverData->add('product', $entity);
        $this->seoResolverData->add('product', $entity);

        $entities = $this->seoResolverData->getAll('product', 'entity-id-1');
        static::assertCount(1, $entities);
        static::assertSame($entity, $entities[array_key_first($entities)]);
    }

    public function testComplexScenarioWithMultipleEntitiesAndTypes(): void
    {
        $product1 = $this->createMockEntity('product-id-1');
        $product2 = $this->createMockEntity('product-id-2');
        $product3 = $this->createMockEntity('product-id-1'); // Same ID as product1, different object
        $category1 = $this->createMockEntity('category-id-1');

        $this->seoResolverData->add('product', $product1);
        $this->seoResolverData->add('product', $product2);
        $this->seoResolverData->add('product', $product3);
        $this->seoResolverData->add('category', $category1);

        $entities = $this->seoResolverData->getEntities();
        static::assertCount(2, $entities);
        static::assertContains('product', $entities);
        static::assertContains('category', $entities);

        $productIds = $this->seoResolverData->getIds('product');
        static::assertCount(2, $productIds);
        static::assertContains('product-id-1', $productIds);
        static::assertContains('product-id-2', $productIds);

        $categoryIds = $this->seoResolverData->getIds('category');
        static::assertSame(['category-id-1'], $categoryIds);

        $entitiesForProduct1 = $this->seoResolverData->getAll('product', 'product-id-1');
        static::assertCount(2, $entitiesForProduct1);
        static::assertContains($product1, $entitiesForProduct1);
        static::assertContains($product3, $entitiesForProduct1);

        $entitiesForProduct2 = $this->seoResolverData->getAll('product', 'product-id-2');
        static::assertCount(1, $entitiesForProduct2);
        static::assertContains($product2, $entitiesForProduct2);

        $entitiesForCategory1 = $this->seoResolverData->getAll('category', 'category-id-1');
        static::assertCount(1, $entitiesForCategory1);
        static::assertContains($category1, $entitiesForCategory1);
    }

    private function createMockEntity(string $uniqueIdentifier): Entity
    {
        $entity = new SalesChannelProductEntity();
        $entity->setUniqueIdentifier($uniqueIdentifier);

        return $entity;
    }
}
