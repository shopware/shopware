<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypesResolvedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(EntityCollectionLoader::class)]
class EntityCollectionLoaderTest extends TestCase
{
    #[TestDox('populates event types with all registered entity collection classes')]
    public function testOnTypesResolvedPopulatesAllCollections(): void
    {
        $productDef = static::createStub(EntityDefinition::class);
        $productDef->method('getCollectionClass')->willReturn(ProductCollection::class);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$productDef]);

        $loader = new EntityCollectionLoader(
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            $registry,
            static::createStub(EntityCacheTagResolver::class),
        );

        $event = new ContentSystemDataLoaderTypesResolvedEvent('entity_collection', []);
        $loader->onTypesResolved($event);

        static::assertCount(1, $event->types);
        static::assertSame(ProductCollection::class, $event->types[0]->className);
    }

    #[TestDox('excludes bare EntityCollection from resolved event types')]
    public function testOnTypesResolvedExcludesBareEntityCollection(): void
    {
        $def = static::createStub(EntityDefinition::class);
        $def->method('getCollectionClass')->willReturn(EntityCollection::class);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$def]);

        $loader = new EntityCollectionLoader(
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            $registry,
            static::createStub(EntityCacheTagResolver::class),
        );

        $event = new ContentSystemDataLoaderTypesResolvedEvent('entity_collection', []);
        $loader->onTypesResolved($event);

        static::assertSame([], $event->types);
    }

    #[TestDox('returns cached collection with resolved tags for all loaded entities')]
    public function testLoadReturnsCachedCollectionWithResolvedTagsForAllEntities(): void
    {
        $id1 = 'product-one';
        $id2 = 'product-two';

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')
            ->willReturnCallback(static fn (EntityDefinition $def, string $id) => 'tag-' . $id);

        $loader = $this->createLoaderWithSalesChannelRepo(
            'product',
            new EntityCollection([$this->createEntityWithId($id1), $this->createEntityWithId($id2)]),
            $cacheTagResolver,
        );

        $result = $loader->load(
            ContentElementBuilder::create('product-grid')->withProperty('productIds', [$id1, $id2])->build(),
            new DataRequirement('products', 'entity_collection', new EntityLoaderConfig('product', 'productIds', [])),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertTrue($result->isCacheAware());
        static::assertInstanceOf(EntityCollection::class, $result->data);
        static::assertContains('tag-' . $id1, $result->getCacheTags());
        static::assertContains('tag-' . $id2, $result->getCacheTags());
        static::assertCount(2, $result->getCacheTags());
    }

    #[TestDox('returns uncacheable result when cache tag resolver returns null for an entity')]
    public function testLoadReturnsUncacheableWhenCacheTagResolverReturnsNull(): void
    {
        $productId = 'uncacheable-product';

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $loader = $this->createLoaderWithSalesChannelRepo(
            'product',
            new EntityCollection([$this->createEntityWithId($productId)]),
            $cacheTagResolver,
        );

        $result = $loader->load(
            ContentElementBuilder::create('product-grid')->withProperty('productIds', [$productId])->build(),
            new DataRequirement('products', 'entity_collection', new EntityLoaderConfig('product', 'productIds', [])),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertFalse($result->isCacheAware());
        static::assertInstanceOf(EntityCollection::class, $result->data);
    }

    #[TestDox('falls back to plain repository when sales channel repository is not found')]
    public function testLoadFallsBackToPlainRepositoryWhenSalesChannelRepoNotFound(): void
    {
        $categoryId = 'category-id';
        $entity = $this->createEntityWithId($categoryId);
        $collection = new EntityCollection([$entity]);

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
        $result = $loader->load(
            ContentElementBuilder::create('category-grid')->withProperty('categoryIds', [$categoryId])->build(),
            new DataRequirement('categories', 'entity_collection', new EntityLoaderConfig('category', 'categoryIds', [])),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertTrue($result->isCacheAware());
        static::assertSame(['category-route-' . $categoryId], $result->getCacheTags());
        static::assertInstanceOf(EntityCollection::class, $result->data);
    }

    #[TestDox('lowercases entity IDs before loading')]
    public function testLoadLowercasesEntityIds(): void
    {
        $productId = Uuid::randomHex();
        $upperCaseId = strtoupper($productId);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('product', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $loader->load(
            ContentElementBuilder::create('product-grid')->withProperty('productIds', [$upperCaseId])->build(),
            new DataRequirement('products', 'entity_collection', new EntityLoaderConfig('product', 'productIds', [])),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame([$productId], $capturedCriteria->getIds());
    }

    #[TestDox('adds associations from config to criteria when loading entities')]
    public function testLoadAddsAssociationsToCriteria(): void
    {
        $productId = Uuid::randomHex();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('product', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $loader->load(
            ContentElementBuilder::create('product-grid')->withProperty('productIds', [$productId])->build(),
            new DataRequirement('products', 'entity_collection', new EntityLoaderConfig('product', 'productIds', ['manufacturer', 'cover'])),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertCount(2, $capturedCriteria->getAssociations());
    }

    #[TestDox('returns cached empty collection when property is null on element')]
    public function testLoadReturnsCachedEmptyWhenPropertyIsNull(): void
    {
        $config = new EntityLoaderConfig('product', 'productIds', []);
        $requirement = new DataRequirement('products', 'entity_collection', $config);
        // element has no 'productIds' property → getProperty returns null
        $element = ContentElementBuilder::create('product-grid')->build();
        $context = Generator::generateSalesChannelContext();

        $loader = $this->createLoaderWithDefinition('product', EntityCollection::class);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(EntityCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns cached empty collection when entity IDs contain no valid strings')]
    public function testLoadReturnsCachedEmptyWhenEntityIdsContainNoStrings(): void
    {
        $config = new EntityLoaderConfig('product', 'productIds', []);
        $requirement = new DataRequirement('products', 'entity_collection', $config);
        // non-string values get filtered out
        $element = ContentElementBuilder::create('product-grid')
            ->withProperty('productIds', [123, null, true])
            ->build();
        $context = Generator::generateSalesChannelContext();

        $loader = $this->createLoaderWithDefinition('product', EntityCollection::class);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(EntityCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns null data when config is not EntityLoaderConfig')]
    public function testLoadReturnsNullDataWhenConfigIsWrongType(): void
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

    #[TestDox('returns null data when property value is not an array')]
    public function testLoadReturnsNullDataWhenPropertyIsNotArray(): void
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

    /**
     * @param class-string<EntityCollection<Entity>> $collectionClass
     */
    private function createLoaderWithDefinition(string $entityName, string $collectionClass): EntityCollectionLoader
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getCollectionClass')->willReturn($collectionClass);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        return new EntityCollectionLoader(
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            $defRegistry,
            static::createStub(EntityCacheTagResolver::class),
        );
    }

    /**
     * @param EntityCollection<Entity> $collection
     */
    private function createLoaderWithSalesChannelRepo(
        string $entityName,
        EntityCollection $collection,
        EntityCacheTagResolver $cacheTagResolver,
    ): EntityCollectionLoader {
        $scRepo = new StaticSalesChannelRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        return new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createLoaderWithCallableRepo(string $entityName, callable $callback): EntityCollectionLoader
    {
        $scRepo = new StaticSalesChannelRepository([$callback]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        return new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
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
