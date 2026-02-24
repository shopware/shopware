<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\ContentElementBuilder;
use Shopware\Tests\Unit\Core\Content\ContentSystem\_helper\StubLoaderConfig;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(EntityCollectionLoader::class)]
class EntityCollectionLoaderTest extends TestCase
{
    #[TestDox('returns entity_collection as requirement type')]
    public function testGetRequirementTypeReturnsEntityCollection(): void
    {
        static::assertSame('entity_collection', EntityCollectionLoader::getRequirementType());
    }

    #[TestDox('returns cached collection with resolved tags when entities are loaded via sales channel repository')]
    public function testLoadReturnsCachedCollectionWithTagsViaSalesChannelRepository(): void
    {
        $productId = Uuid::randomHex();
        $config = new EntityLoaderConfig('product', 'productIds', []);
        $requirement = new DataRequirement('products', 'entity_collection', $config);
        $element = ContentElementBuilder::create('product-grid')
            ->withProperty('productIds', [$productId])
            ->build();
        $context = Generator::generateSalesChannelContext();

        $entity = $this->createEntityWithId($productId);
        $collection = new EntityCollection([$entity]);

        /** @var StaticSalesChannelRepository<EntityCollection<Entity>> $scRepo */
        $scRepo = new StaticSalesChannelRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn('product-' . $productId);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->isCacheAware());
        static::assertSame(['product-' . $productId], $result->getCacheTags());
        static::assertInstanceOf(EntityCollection::class, $result->data);
    }

    #[TestDox('returns cached collection with multiple tags for multiple entities')]
    public function testLoadReturnsCachedCollectionWithMultipleTagsForMultipleEntities(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $config = new EntityLoaderConfig('product', 'productIds', []);
        $requirement = new DataRequirement('products', 'entity_collection', $config);
        $element = ContentElementBuilder::create('product-grid')
            ->withProperty('productIds', [$id1, $id2])
            ->build();
        $context = Generator::generateSalesChannelContext();

        $entity1 = $this->createEntityWithId($id1);
        $entity2 = $this->createEntityWithId($id2);
        $collection = new EntityCollection([$entity1, $entity2]);

        /** @var StaticSalesChannelRepository<EntityCollection<Entity>> $scRepo */
        $scRepo = new StaticSalesChannelRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')
            ->willReturnCallback(static fn (EntityDefinition $def, string $id) => 'product-' . $id);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->isCacheAware());
        static::assertContains('product-' . $id1, $result->getCacheTags());
        static::assertContains('product-' . $id2, $result->getCacheTags());
        static::assertCount(2, $result->getCacheTags());
    }

    #[TestDox('returns uncacheable result when cache tag resolver returns null for an entity')]
    public function testLoadReturnsUncacheableWhenCacheTagResolverReturnsNull(): void
    {
        $productId = Uuid::randomHex();
        $config = new EntityLoaderConfig('product', 'productIds', []);
        $requirement = new DataRequirement('products', 'entity_collection', $config);
        $element = ContentElementBuilder::create('product-grid')
            ->withProperty('productIds', [$productId])
            ->build();
        $context = Generator::generateSalesChannelContext();

        $entity = $this->createEntityWithId($productId);
        $collection = new EntityCollection([$entity]);

        /** @var StaticSalesChannelRepository<EntityCollection<Entity>> $scRepo */
        $scRepo = new StaticSalesChannelRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertFalse($result->isCacheAware());
        static::assertInstanceOf(EntityCollection::class, $result->data);
    }

    #[TestDox('falls back to plain repository when sales channel repository is not found')]
    public function testLoadFallsBackToPlainRepositoryWhenSalesChannelRepoNotFound(): void
    {
        $categoryId = Uuid::randomHex();
        $config = new EntityLoaderConfig('category', 'categoryIds', []);
        $requirement = new DataRequirement('categories', 'entity_collection', $config);
        $element = ContentElementBuilder::create('category-grid')
            ->withProperty('categoryIds', [$categoryId])
            ->build();
        $context = Generator::generateSalesChannelContext();

        $entity = $this->createEntityWithId($categoryId);
        $collection = new EntityCollection([$entity]);

        /** @var StaticEntityRepository<EntityCollection<Entity>> $plainRepo */
        $plainRepo = new StaticEntityRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('category');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn('category-route-' . $categoryId);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')
            ->willThrowException(new SalesChannelRepositoryNotFoundException('category'));

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getRepository')->willReturn($plainRepo);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->isCacheAware());
        static::assertSame(['category-route-' . $categoryId], $result->getCacheTags());
        static::assertInstanceOf(EntityCollection::class, $result->data);
    }

    #[TestDox('lowercases entity IDs before loading')]
    public function testLoadLowercasesEntityIds(): void
    {
        $productId = Uuid::randomHex();
        $upperCaseId = strtoupper($productId);
        $config = new EntityLoaderConfig('product', 'productIds', []);
        $requirement = new DataRequirement('products', 'entity_collection', $config);
        $element = ContentElementBuilder::create('product-grid')
            ->withProperty('productIds', [$upperCaseId])
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        /** @var StaticSalesChannelRepository<EntityCollection<Entity>> $scRepo */
        $scRepo = new StaticSalesChannelRepository([
            static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
                $capturedCriteria = $criteria;

                return new EntityCollection();
            },
        ]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame([$productId], $capturedCriteria->getIds());
    }

    #[TestDox('adds associations from config to criteria when loading entities')]
    public function testLoadAddsAssociationsToCriteria(): void
    {
        $productId = Uuid::randomHex();
        $config = new EntityLoaderConfig('product', 'productIds', ['manufacturer', 'cover']);
        $requirement = new DataRequirement('products', 'entity_collection', $config);
        $element = ContentElementBuilder::create('product-grid')
            ->withProperty('productIds', [$productId])
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        /** @var StaticSalesChannelRepository<EntityCollection<Entity>> $scRepo */
        $scRepo = new StaticSalesChannelRepository([
            static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
                $capturedCriteria = $criteria;

                return new EntityCollection();
            },
        ]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertNotEmpty($capturedCriteria->getAssociations());
    }

    #[TestDox('returns cached empty array when property is null on element')]
    public function testLoadReturnsCachedEmptyWhenPropertyIsNull(): void
    {
        $config = new EntityLoaderConfig('product', 'productIds', []);
        $requirement = new DataRequirement('products', 'entity_collection', $config);
        // element has no 'productIds' property → getProperty returns null
        $element = ContentElementBuilder::create('product-grid')->build();
        $context = Generator::generateSalesChannelContext();

        $loader = $this->createMinimalLoader();
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertSame([], $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns cached empty array when entity IDs contain no valid strings')]
    public function testLoadReturnsCachedEmptyWhenEntityIdsContainNoStrings(): void
    {
        $config = new EntityLoaderConfig('product', 'productIds', []);
        $requirement = new DataRequirement('products', 'entity_collection', $config);
        // non-string values get filtered out
        $element = ContentElementBuilder::create('product-grid')
            ->withProperty('productIds', [123, null, true])
            ->build();
        $context = Generator::generateSalesChannelContext();

        $loader = $this->createMinimalLoader();
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertSame([], $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when config is not EntityLoaderConfig')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $requirement = new DataRequirement('products', 'entity_collection', new StubLoaderConfig());
        $element = ContentElementBuilder::create('product-grid')->build();
        $context = Generator::generateSalesChannelContext();

        $loader = $this->createMinimalLoader();
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when property value is not an array')]
    public function testLoadReturnsNotFoundWhenPropertyIsNotArray(): void
    {
        $config = new EntityLoaderConfig('product', 'productIds', []);
        $requirement = new DataRequirement('products', 'entity_collection', $config);
        $element = ContentElementBuilder::create('product-grid')
            ->withProperty('productIds', 'not-an-array')
            ->build();
        $context = Generator::generateSalesChannelContext();

        $loader = $this->createMinimalLoader();
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $loader = $this->createMinimalLoader();

        $this->expectExceptionObject(new DecorationPatternException(EntityCollectionLoader::class));

        $loader->getDecorated();
    }

    private function createMinimalLoader(): EntityCollectionLoader
    {
        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);

        return new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createEntityWithId(string $id): Entity
    {
        $entity = new Entity();
        $entity->setUniqueIdentifier($id);

        return $entity;
    }
}
