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
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\UuidException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Shopware\Core\System\SalesChannel\Exception\SalesChannelRepositoryNotFoundException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\StubLoaderConfig;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityCollectionLoader::class)]
class EntityCollectionLoaderTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

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

    #[TestDox('returns cached collection with resolved tags for all loaded entities')]
    public function testLoadReturnsCachedCollectionWithResolvedTagsForAllEntities(): void
    {
        $id1 = $this->ids->get('product-one');
        $id2 = $this->ids->get('product-two');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')
            ->willReturnCallback(static fn (EntityDefinition $def, string $id) => 'tag-' . $id);

        $loader = $this->createLoaderWithSalesChannelRepo(
            'product',
            new EntityCollection([$this->createEntityWithId($id1), $this->createEntityWithId($id2)]),
            $cacheTagResolver,
        );

        $result = $loader->load(
            self::inputs('product', [$id1, $id2]),
            self::requirement(),
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
        $categoryId = $this->ids->get('category');
        $entity = $this->createEntityWithId($categoryId);
        $collection = new EntityCollection([$entity]);

        $plainRepo = new StaticEntityRepository([$collection]);

        $container = new Container();
        $container->set('category.repository', $plainRepo);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('category');

        $defRegistry = new DefinitionInstanceRegistry($container, [], []);
        $defRegistry->register($definition);

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn('category-route-' . $categoryId);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')
            ->willThrowException(new SalesChannelRepositoryNotFoundException('category'));

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $loader->load(
            self::inputs('category', [$categoryId]),
            self::requirement(),
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
        $productId = $this->ids->get('product');
        $upperCaseId = strtoupper($productId);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('product', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $loader->load(
            self::inputs('product', [$upperCaseId]),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame([$productId], $capturedCriteria->getIds());
    }

    #[TestDox('adds associations from config to criteria when loading entities')]
    public function testLoadAddsAssociationsToCriteria(): void
    {
        $productId = $this->ids->get('product');

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('product', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $loader->load(
            self::inputs('product', [$productId], ['manufacturer', 'cover']),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertCount(2, $capturedCriteria->getAssociations());
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

    #[TestDox('returns the declared sales-channel collection class when no IDs are provided')]
    public function testEmptyCollectionPathReturnsDeclaredProducedType(): void
    {
        $loader = new EntityCollectionLoader(
            $this->createSalesChannelDefinitionRegistry(new SalesChannelProductDefinition()),
            $this->createDefinitionRegistry(new ProductDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        $result = $loader->load(
            self::inputs('product', null),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(SalesChannelProductCollection::class, $result->data);
        static::assertSame($loader->producibleTypes()[0]->producedType, $result->data::class);
    }

    /**
     * @param list<string>|null $entityIds
     */
    #[DataProvider('emptyCollectionProvider')]
    #[TestDox('returns a cached empty collection when $_dataName')]
    public function testLoadReturnsCachedEmptyCollection(?array $entityIds): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');
        $definition->method('getCollectionClass')->willReturn(EntityCollection::class);

        $loader = $this->createLoaderWithDefinition($definition);

        $result = $loader->load(
            self::inputs('product', $entityIds),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertInstanceOf(EntityCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns uncacheable result when cache tag resolver returns null for an entity')]
    public function testLoadReturnsUncacheableWhenCacheTagResolverReturnsNull(): void
    {
        $productId = $this->ids->get('uncacheable-product');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $loader = $this->createLoaderWithSalesChannelRepo(
            'product',
            new EntityCollection([$this->createEntityWithId($productId)]),
            $cacheTagResolver,
        );

        $result = $loader->load(
            self::inputs('product', [$productId]),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertFalse($result->isCacheAware());
        static::assertInstanceOf(EntityCollection::class, $result->data);
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

        // A valid uuid, so the entity-registration short-circuit is the only thing this can be proving.
        $result = $loader->load(
            self::inputs('ghost', [$this->ids->get('ghost')]),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('degrades the whole element to notFound without reaching a repository when one ID in the list is not a valid uuid')]
    public function testLoadReturnsNotFoundWhenOneEntityIdIsNotValidUuid(): void
    {
        $scDefRegistry = $this->createMock(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->expects($this->never())->method('getSalesChannelRepository');

        $defRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->expects($this->never())->method('getRepository');

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, static::createStub(EntityCacheTagResolver::class));

        // Two of the three entries are ids the DAL would accept. Filtering the bad one out and loading the
        // remainder would reach a repository, so the never() expectations are what pin whole-element
        // degradation rather than a shortened collection.
        $result = $loader->load(
            self::inputs('product', [$this->ids->get('product-one'), '{{productId}}', $this->ids->get('product-two')]),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('degrades to notFound when the post-load definition lookup throws')]
    public function testLoadReturnsNotFoundWhenDefinitionLookupThrows(): void
    {
        $productId = $this->ids->get('product');
        $scRepo = new StaticSalesChannelRepository([
            new EntityCollection([$this->createEntityWithId($productId)]),
        ]);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        // has() is an isset on the registry's entity-name map, so it stays true while the mapped definition
        // service is absent from the container, which is the state getByEntityName() reports by throwing.
        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getByEntityName')
            ->willThrowException(DataAbstractionLayerException::definitionNotFound('product'));

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, static::createStub(EntityCacheTagResolver::class));

        $result = $loader->load(
            self::inputs('product', [$productId]),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('degrades the empty-list path to notFound when the sales-channel definition lookup throws')]
    public function testEmptyCollectionPathDegradesWhenSalesChannelDefinitionLookupThrows(): void
    {
        // resolveDefinition() prefers the sales-channel registry when its has() answers true, and that
        // getByEntityName() throws when the mapped definition service is absent from the container.
        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('has')->willReturn(true);
        $scDefRegistry->method('getByEntityName')
            ->willThrowException(DataAbstractionLayerException::definitionNotFound('product'));

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);

        $loader = new EntityCollectionLoader($scDefRegistry, $defRegistry, static::createStub(EntityCacheTagResolver::class));

        $result = $loader->load(
            self::inputs('product', null),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('degrades the empty-list path to notFound when the base definition lookup rethrows an unknown entity')]
    public function testEmptyCollectionPathDegradesWhenBaseDefinitionLookupRethrows(): void
    {
        // The base-registry miss inside resolveDefinition() is rethrown as
        // ContentSystemException::unknownLoaderEntity(), which extends HttpException and therefore
        // ShopwareHttpException. has() answering true is what carries execution past the registration
        // short-circuit and into the empty-list path.
        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getByEntityName')
            ->willThrowException(DataAbstractionLayerException::definitionNotFound('product'));

        $loader = new EntityCollectionLoader(
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            $defRegistry,
            static::createStub(EntityCacheTagResolver::class),
        );

        $result = $loader->load(
            self::inputs('product', []),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the repository throws the Shopware exception $_dataName')]
    public function testLoadReturnsNotFoundWhenRepositoryThrows(\Throwable $exception): void
    {
        $loader = $this->createLoaderWithCallableRepo('product', static function () use ($exception): never {
            throw $exception;
        });

        $result = $loader->load(
            self::inputs('product', [$this->ids->get('product')]),
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lets a TypeError from the repository propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideShopwareHttpExceptionPropagate(): void
    {
        $typeError = new \TypeError('Argument #1 ($criteria) must be of type Criteria, null given');

        $loader = $this->createLoaderWithCallableRepo('product', static function () use ($typeError): never {
            throw $typeError;
        });

        try {
            $loader->load(
                self::inputs('product', [$this->ids->get('product')]),
                self::requirement(),
                Generator::generateSalesChannelContext(),
                new Request(),
            );

            static::fail('Expected the TypeError to propagate out of load() instead of degrading to notFound');
        } catch (\TypeError $caught) {
            static::assertSame($typeError, $caught);
        }
    }

    #[TestDox('lets a TypeError from the empty-path definition lookup propagate instead of degrading')]
    public function testEmptyCollectionPathLetsThrowableOutsideShopwareHttpExceptionPropagate(): void
    {
        // Enters through the empty path (no entity ids), not the non-empty repository path the sibling
        // propagation test above uses: emptyCollectionResult() has its own catch (ShopwareHttpException) site,
        // separate from the one around loadEntities(), and only this path proves that catch also lets a
        // non-ShopwareHttpException through instead of degrading.
        $typeError = new \TypeError('Argument #1 ($entityName) must be of type string, null given');

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getByEntityName')->willThrowException($typeError);

        $loader = new EntityCollectionLoader(
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            $defRegistry,
            static::createStub(EntityCacheTagResolver::class),
        );

        try {
            $loader->load(
                self::inputs('product', null),
                self::requirement(),
                Generator::generateSalesChannelContext(),
                new Request(),
            );

            static::fail('Expected the TypeError to propagate out of load() instead of degrading to notFound');
        } catch (\TypeError $caught) {
            static::assertSame($typeError, $caught);
        }
    }

    /**
     * Sample domain exceptions, not one row per catch arm: the loader catches the single covering ancestor
     * `ShopwareHttpException`, so no row maps to a clause of its own. This loader searches an arbitrary
     * registered entity, so the reachable set cannot be enumerated from the loader at all.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        // EntityDefinitionQueryHelper::addIdCondition() converts every criteria id with Uuid::fromHexToBytes().
        // The guard above keeps a malformed id away from it; this row states that a repository reaching it
        // anyway still degrades. InvalidUuidException extends ShopwareHttpException directly.
        yield 'an id the DAL rejects when building the criteria condition' => [
            UuidException::invalidUuid('not-a-uuid'),
        ];

        // DataAbstractionLayerException extends HttpException, which extends ShopwareHttpException.
        yield 'a DAL failure reached through HttpException' => [
            DataAbstractionLayerException::invalidCriteriaIds(['bad-id'], 'reason'),
        ];

        // Not a reachability claim: this row pins the clause to the ancestor rather than to any one branch of
        // the inheritance line.
        yield 'a class outside the chain that extends ShopwareHttpException directly' => [
            new DecorationPatternException(EntityCollectionLoader::class),
        ];
    }

    /**
     * @return iterable<string, array{list<string>|null}>
     */
    public static function emptyCollectionProvider(): iterable
    {
        yield 'the property input is unresolved' => [null];
        yield 'the property input resolves to an empty list' => [[]];
    }

    /**
     * @param list<string>|null $entityIds
     * @param list<string> $associations
     */
    private static function inputs(string $entityName, ?array $entityIds, array $associations = []): LoaderInputs
    {
        return new LoaderInputs([
            'entity' => $entityName,
            'property' => $entityIds,
            'associations' => $associations,
        ]);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('products', 'entity_collection', new EntityLoaderConfig('product', 'productIds', []));
    }

    /**
     * The registry genuinely depends on $definition's own getEntityName(): has()/getByEntityName() only
     * resolve the entity name it was registered under, so a mis-read entity name fails has() -> notFound
     * rather than being masked by an unconditional stub.
     */
    private function createLoaderWithDefinition(EntityDefinition $definition): EntityCollectionLoader
    {
        return new EntityCollectionLoader(
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            $this->createDefinitionRegistry($definition),
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

        return new EntityCollectionLoader($scDefRegistry, $this->createDefinitionRegistry($definition), $cacheTagResolver);
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

        return new EntityCollectionLoader($scDefRegistry, $this->createDefinitionRegistry($definition), $cacheTagResolver);
    }

    private function createMinimalLoader(): EntityCollectionLoader
    {
        return new EntityCollectionLoader(
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(EntityCacheTagResolver::class),
        );
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
