<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductCollection;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(EntityCollectionLoader::class)]
class EntityCollectionLoaderTest extends TestCase
{
    #[TestDox('declares the sales-channel collection class and entity generic for an entity with a sales-channel definition')]
    public function testProducibleTypesDeclaresSalesChannelCollectionForEntityWithVariant(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createSalesChannelDefinitionRegistry(new SalesChannelProductDefinition()),
            $this->createDefinitionRegistry(new ProductDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(SalesChannelProductCollection::class, $capabilities[0]->producedType);
        static::assertSame([SalesChannelProductEntity::class], $capabilities[0]->genericParameters);
        static::assertSame(['entity' => 'product'], $capabilities[0]->configTemplate);
    }

    #[TestDox('declares the base collection class for an entity without a sales-channel definition')]
    public function testProducibleTypesDeclaresBaseCollectionForEntityWithoutVariant(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createSalesChannelDefinitionRegistry(),
            $this->createDefinitionRegistry(new MediaDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaCollection::class, $capabilities[0]->producedType);
        static::assertSame([MediaEntity::class], $capabilities[0]->genericParameters);
    }

    #[TestDox('skips bare EntityCollection definitions but keeps enumerating the rest')]
    public function testProducibleTypesSkipsBareEntityCollectionAndContinues(): void
    {
        // Bare-collection definition first, valid definition second: a continue→break regression would
        // drop the valid type and leave an empty result.
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([
            'bare' => $this->definitionStub(EntityCollection::class, MediaEntity::class, 'bare'),
            'media' => $this->definitionStub(MediaCollection::class, MediaEntity::class, 'media'),
        ]);

        $scRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scRegistry->method('getSalesChannelDefinitions')->willReturn([]);

        $loader = new EntityCollectionLoader($scRegistry, $registry, static::createStub(EntityCacheTagResolver::class));
        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaCollection::class, $capabilities[0]->producedType);
    }

    #[TestDox('skips mapping definitions but keeps enumerating the rest')]
    public function testProducibleTypesSkipsMappingDefinitionsAndContinues(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([
            'product_category' => static::createStub(MappingEntityDefinition::class),
            'media' => $this->definitionStub(MediaCollection::class, MediaEntity::class, 'media'),
        ]);

        $scRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scRegistry->method('getSalesChannelDefinitions')->willReturn([]);

        $loader = new EntityCollectionLoader($scRegistry, $registry, static::createStub(EntityCacheTagResolver::class));
        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaCollection::class, $capabilities[0]->producedType);
    }

    #[TestDox('resolves the sales-channel collection class for a config naming an entity with a variant')]
    public function testResolveProducedTypeReturnsSalesChannelCollection(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createSalesChannelDefinitionRegistry(new SalesChannelProductDefinition()),
            $this->createDefinitionRegistry(new ProductDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        static::assertSame(
            SalesChannelProductCollection::class,
            $loader->resolveProducedType(new EntityLoaderConfig('product', 'productIds', [])),
        );
    }

    #[TestDox('throws when resolving a config that names an unknown entity')]
    public function testResolveProducedTypeThrowsForUnknownEntity(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createSalesChannelDefinitionRegistry(),
            $this->createDefinitionRegistry(),
            static::createStub(EntityCacheTagResolver::class),
        );

        $this->expectExceptionObject(ContentSystemException::unknownLoaderEntity('ghost'));

        $loader->resolveProducedType(new EntityLoaderConfig('ghost', 'ghostIds', []));
    }

    #[TestDox('throws when resolving produced type for a config that is not an EntityLoaderConfig')]
    public function testResolveProducedTypeThrowsForWrongConfigType(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, StubLoaderConfig::class),
        );

        $this->createMinimalLoader()->resolveProducedType(new StubLoaderConfig());
    }

    #[TestDox('returns the declared sales-channel collection class when no IDs are provided')]
    public function testEmptyCollectionPathReturnsDeclaredProducedType(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createSalesChannelDefinitionRegistry(new SalesChannelProductDefinition()),
            $this->createDefinitionRegistry(new ProductDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        $result = $loader->load(
            ContentElementBuilder::create('product-grid')->build(),
            new DataRequirement('products', 'entity_collection', new EntityLoaderConfig('product', 'productIds', [])),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(SalesChannelProductCollection::class, $result->data);
        static::assertSame($loader->producibleTypes()[0]->producedType, $result->data::class);
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
        $defRegistry->method('has')->willReturn(true);
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

    #[DataProvider('returnsNullDataProvider')]
    #[TestDox('returns null data when $_dataName')]
    public function testLoadReturnsNullData(DataRequirement $requirement, ContentElement $element): void
    {
        $loader = $this->createMinimalLoader();
        $context = Generator::generateSalesChannelContext();

        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound instead of throwing when the configured entity is not registered')]
    public function testLoadReturnsNotFoundForUnregisteredEntity(): void
    {
        // has() defaults to false on the stub registry, so the loader must short-circuit to notFound
        // rather than letting getByEntityName()/getRepository() throw (loaders must never throw).
        $loader = new EntityCollectionLoader(
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(EntityCacheTagResolver::class),
        );

        $result = $loader->load(
            ContentElementBuilder::create('product-grid')->withProperty('ghostIds', ['id-1'])->build(),
            new DataRequirement('ghosts', 'entity_collection', new EntityLoaderConfig('ghost', 'ghostIds', [])),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @return iterable<string, array{DataRequirement, ContentElement}>
     */
    public static function returnsNullDataProvider(): iterable
    {
        yield 'config is not an EntityLoaderConfig' => [
            new DataRequirement('products', 'entity_collection', new StubLoaderConfig()),
            ContentElementBuilder::create('product-grid')->build(),
        ];

        yield 'property value is not an array' => [
            new DataRequirement('products', 'entity_collection', new EntityLoaderConfig('product', 'productIds', [])),
            ContentElementBuilder::create('product-grid')->withProperty('productIds', 'not-an-array')->build(),
        ];
    }

    /**
     * @param class-string<EntityCollection<Entity>> $collectionClass
     */
    private function createLoaderWithDefinition(string $entityName, string $collectionClass): EntityCollectionLoader
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getCollectionClass')->willReturn($collectionClass);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
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
        $defRegistry->method('has')->willReturn(true);
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
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        return new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createMinimalLoader(): EntityCollectionLoader
    {
        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);

        return new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createDefinitionRegistry(EntityDefinition ...$definitions): DefinitionInstanceRegistry
    {
        $registry = new DefinitionInstanceRegistry(new Container(), [], []);
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }

        return $registry;
    }

    private function createSalesChannelDefinitionRegistry(EntityDefinition ...$definitions): SalesChannelDefinitionInstanceRegistry
    {
        $registry = new SalesChannelDefinitionInstanceRegistry('sales_channel_definition.', new Container(), [], []);
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }

        return $registry;
    }

    /**
     * @param class-string $collectionClass
     * @param class-string<Entity> $entityClass
     */
    private function definitionStub(string $collectionClass, string $entityClass, string $entityName): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getCollectionClass')->willReturn($collectionClass);
        $definition->method('getEntityClass')->willReturn($entityClass);
        $definition->method('getEntityName')->willReturn($entityName);

        return $definition;
    }

    private function createEntityWithId(string $id): Entity
    {
        $entity = new Entity();
        $entity->setUniqueIdentifier($id);

        return $entity;
    }
}
