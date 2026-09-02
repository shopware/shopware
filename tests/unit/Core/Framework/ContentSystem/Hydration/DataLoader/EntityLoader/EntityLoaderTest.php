<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
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
use Shopware\Core\Framework\Struct\ArrayEntity;
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
#[CoversClass(EntityLoader::class)]
class EntityLoaderTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    /**
     * @return iterable<string, array{list<EntityDefinition>, EntityDefinition, class-string, array<string, mixed>}>
     */
    public static function declaresProducibleTypeProvider(): iterable
    {
        yield 'an entity with a sales-channel variant' => [[new SalesChannelProductDefinition()], new ProductDefinition(), SalesChannelProductEntity::class, ['entity' => 'product']];
        yield 'an entity without a sales-channel variant' => [[], new MediaDefinition(), MediaEntity::class, ['entity' => 'media']];
    }

    /**
     * @param list<EntityDefinition> $salesChannelDefinitions
     * @param class-string $expectedProducedType
     * @param array<string, mixed> $expectedConfigTemplate
     */
    #[DataProvider('declaresProducibleTypeProvider')]
    #[TestDox('declares the producible type for $_dataName')]
    public function testProducibleTypesDeclaresExpectedProducedType(
        array $salesChannelDefinitions,
        EntityDefinition $definition,
        string $expectedProducedType,
        array $expectedConfigTemplate,
    ): void {
        $loader = new EntityLoader(
            $this->createSalesChannelDefinitionRegistry(...$salesChannelDefinitions),
            $this->createDefinitionRegistry($definition),
            static::createStub(EntityCacheTagResolver::class),
        );

        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame($expectedProducedType, $capabilities[0]->producedType);
        static::assertSame($expectedConfigTemplate, $capabilities[0]->configTemplate);
    }

    #[TestDox('skips ArrayEntity definitions but keeps enumerating the rest')]
    public function testProducibleTypesSkipsArrayEntityAndContinues(): void
    {
        // ArrayEntity definition first, valid definition second: a continue→break regression would
        // drop the valid type and leave an empty result.
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([
            'array' => $this->definitionStub(ArrayEntity::class, 'array'),
            'media' => $this->definitionStub(MediaEntity::class, 'media'),
        ]);

        $scRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scRegistry->method('getSalesChannelDefinitions')->willReturn([]);

        $loader = new EntityLoader($scRegistry, $registry, static::createStub(EntityCacheTagResolver::class));
        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaEntity::class, $capabilities[0]->producedType);
    }

    #[TestDox('skips mapping definitions but keeps enumerating the rest')]
    public function testProducibleTypesSkipsMappingDefinitionsAndContinues(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([
            'product_category' => static::createStub(MappingEntityDefinition::class),
            'media' => $this->definitionStub(MediaEntity::class, 'media'),
        ]);

        $scRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scRegistry->method('getSalesChannelDefinitions')->willReturn([]);

        $loader = new EntityLoader($scRegistry, $registry, static::createStub(EntityCacheTagResolver::class));
        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaEntity::class, $capabilities[0]->producedType);
    }

    #[TestDox('resolves the sales-channel entity class for a config naming an entity with a variant')]
    public function testResolveProducedTypeReturnsSalesChannelClass(): void
    {
        $loader = $this->productEntityLoader();

        static::assertSame(
            SalesChannelProductEntity::class,
            $loader->resolveProducedType(new EntityLoaderConfig('product', 'productId', [])),
        );
    }

    #[TestDox('resolves the base entity class for a config naming an entity without a variant')]
    public function testResolveProducedTypeReturnsBaseClass(): void
    {
        $loader = new EntityLoader(
            $this->createSalesChannelDefinitionRegistry(),
            $this->createDefinitionRegistry(new MediaDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        static::assertSame(
            MediaEntity::class,
            $loader->resolveProducedType(new EntityLoaderConfig('media', 'mediaId', [])),
        );
    }

    #[TestDox('throws when resolving a config that names an unknown entity')]
    public function testResolveProducedTypeThrowsForUnknownEntity(): void
    {
        $loader = new EntityLoader(
            $this->createSalesChannelDefinitionRegistry(),
            $this->createDefinitionRegistry(),
            static::createStub(EntityCacheTagResolver::class),
        );

        $this->expectExceptionObject(ContentSystemException::unknownLoaderEntity('ghost'));

        $loader->resolveProducedType(new EntityLoaderConfig('ghost', 'ghostId', []));
    }

    #[TestDox('throws when resolving produced type for a config that is not an EntityLoaderConfig')]
    public function testResolveProducedTypeThrowsForWrongConfigType(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::invalidFieldValueType('config', EntityLoaderConfig::class, StubLoaderConfig::class),
        );

        $this->createMinimalLoader()->resolveProducedType(new StubLoaderConfig());
    }

    #[TestDox('declares exactly the required config keys the serializer needs to decode a config (drift guard)')]
    public function testConfigSpecificationRequiredKeysMatchSerializerRequiredKeys(): void
    {
        $loader = $this->productEntityLoader();

        $requiredKeys = $loader->configSpecification()->requiredKeys();
        sort($requiredKeys);

        static::assertSame(['entity', 'property'], $requiredKeys);

        // Drive decode() purely from the keys the specification declares required: if the specification drops a
        // key the serializer requires (or decode() gains a new required key), decode() throws and this fails.
        // EntityLoaderConfigSerializerTest pins necessity (decode rejects either key's absence).
        $input = [];
        foreach ($requiredKeys as $key) {
            $input[$key] = 'product';
        }

        (new EntityLoaderConfigSerializer())->decode($input);
    }

    #[TestDox('returns cached result with cache tag when entity is loaded via sales channel repository')]
    public function testLoadReturnsCachedResultViaSalesChannelRepository(): void
    {
        $productId = $this->ids->get('product');
        $entity = $this->createEntityWithId($productId);

        $loader = $this->createLoaderWithSalesChannelRepo('product', new EntityCollection([$entity]), new EntityCacheTagResolver());
        $result = $this->loadEntity($loader, 'product', $productId);

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame(['product-' . $productId], $result->getCacheTags());
    }

    #[TestDox('falls back to context repository when sales channel repository is unavailable')]
    public function testLoadFallsBackToContextRepositoryWhenSalesChannelRepoUnavailable(): void
    {
        $categoryId = $this->ids->get('category');
        $entity = $this->createEntityWithId($categoryId);
        $collection = new EntityCollection([$entity]);

        $plainRepo = new StaticEntityRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('category');

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')
            ->willThrowException(new SalesChannelRepositoryNotFoundException('category'));

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getRepository')->willReturn($plainRepo);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityLoader($scDefRegistry, $defRegistry, new EntityCacheTagResolver());
        $result = $this->loadEntity($loader, 'category', $categoryId);

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame(['category-route-' . $categoryId], $result->getCacheTags());
    }

    #[TestDox('returns uncacheable result when cache tag resolver returns null')]
    public function testLoadReturnsUncacheableWhenCacheTagResolverReturnsNull(): void
    {
        $productId = $this->ids->get('product');
        $entity = $this->createEntityWithId($productId);

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $loader = $this->createLoaderWithSalesChannelRepo('product', new EntityCollection([$entity]), $cacheTagResolver);
        $result = $this->loadEntity($loader, 'product', $productId);

        static::assertSame($entity, $result->data);
        static::assertFalse($result->isCacheAware());
    }

    #[TestDox('lowercases entity ID before passing it to the repository')]
    public function testLoadLowercasesEntityId(): void
    {
        $productId = $this->ids->get('product');
        $upperCaseId = strtoupper($productId);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('product', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $this->loadEntity($loader, 'product', $upperCaseId);

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame([$productId], $capturedCriteria->getIds());
    }

    #[TestDox('adds associations from config to criteria when loading entity')]
    public function testLoadAddsAssociationsToCriteria(): void
    {
        $productId = $this->ids->get('product');

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('product', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $inputs = new LoaderInputs([
            'entity' => 'product',
            'property' => $productId,
            'associations' => ['manufacturer', 'cover'],
        ]);

        $loader->load($inputs, self::requirement(), Generator::generateSalesChannelContext(), new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertCount(2, $capturedCriteria->getAssociations());
    }

    #[TestDox('returns notFound result when the property input is unresolved')]
    public function testLoadReturnsNotFoundWhenPropertyInputIsUnresolved(): void
    {
        $inputs = new LoaderInputs(['entity' => 'product', 'property' => null, 'associations' => []]);

        $result = $this->createMinimalLoader()->load(
            $inputs,
            self::requirement(),
            Generator::generateSalesChannelContext(),
            new Request(),
        );

        $this->assertNotFoundResult($result);
    }

    #[TestDox('returns notFound result when entity is not found in repository')]
    public function testLoadReturnsNotFoundWhenEntityNotFoundInRepository(): void
    {
        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $loader = $this->createLoaderWithSalesChannelRepo('product', new EntityCollection(), $cacheTagResolver);
        $result = $this->loadEntity($loader, 'product', $this->ids->get('product'));

        $this->assertNotFoundResult($result);
    }

    #[TestDox('returns notFound instead of throwing when the configured entity is not registered')]
    public function testLoadReturnsNotFoundForUnregisteredEntity(): void
    {
        // has() defaults to false on the stub registry, so the loader must short-circuit to notFound
        // rather than letting getByEntityName()/getRepository() throw (loaders must never throw).
        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $loader = new EntityLoader($scDefRegistry, $defRegistry, static::createStub(EntityCacheTagResolver::class));

        // A valid uuid, so the entity-registration short-circuit is the only thing this can be proving.
        $result = $this->loadEntity($loader, 'ghost', $this->ids->get('ghost'));

        $this->assertNotFoundResult($result);
    }

    #[TestDox('returns notFound result without reaching a repository when the resolved property is not a valid uuid')]
    public function testLoadReturnsNotFoundWhenPropertyIsNotValidUuid(): void
    {
        $scDefRegistry = $this->createMock(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->expects($this->never())->method('getSalesChannelRepository');

        $defRegistry = $this->createMock(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->expects($this->never())->method('getRepository');

        $loader = new EntityLoader($scDefRegistry, $defRegistry, static::createStub(EntityCacheTagResolver::class));

        $result = $this->loadEntity($loader, 'product', '{{productId}}');

        $this->assertNotFoundResult($result);
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

        $loader = new EntityLoader($scDefRegistry, $defRegistry, static::createStub(EntityCacheTagResolver::class));

        $result = $this->loadEntity($loader, 'product', $productId);

        $this->assertNotFoundResult($result);
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the repository throws the Shopware exception $_dataName')]
    public function testLoadReturnsNotFoundWhenRepositoryThrows(\Throwable $exception): void
    {
        $loader = $this->createLoaderWithCallableRepo('product', static function () use ($exception): never {
            throw $exception;
        });

        $result = $this->loadEntity($loader, 'product', $this->ids->get('product'));

        $this->assertNotFoundResult($result);
    }

    #[TestDox('lets a TypeError from the repository propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideShopwareHttpExceptionPropagate(): void
    {
        $typeError = new \TypeError('Argument #1 ($criteria) must be of type Criteria, null given');

        $loader = $this->createLoaderWithCallableRepo('product', static function () use ($typeError): never {
            throw $typeError;
        });

        try {
            $this->loadEntity($loader, 'product', $this->ids->get('product'));

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
        // EntityDefinitionQueryHelper::addIdCondition() converts every criteria id with
        // Uuid::fromHexToBytes() (src/Core/Framework/DataAbstractionLayer/Dbal/EntityDefinitionQueryHelper.php:612).
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
            new DecorationPatternException(EntityLoader::class),
        ];
    }

    private function assertNotFoundResult(ContentDataLoaderResult $result): void
    {
        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @param non-empty-string $entityName
     */
    private function loadEntity(
        EntityLoader $loader,
        string $entityName,
        string $entityId,
    ): ContentDataLoaderResult {
        $inputs = new LoaderInputs(['entity' => $entityName, 'property' => $entityId, 'associations' => []]);

        return $loader->load($inputs, self::requirement(), Generator::generateSalesChannelContext(), new Request());
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('product', 'entity', new EntityLoaderConfig('product', 'productId', []));
    }

    /**
     * @param EntityCollection<Entity> $collection
     */
    private function createLoaderWithSalesChannelRepo(
        string $entityName,
        EntityCollection $collection,
        EntityCacheTagResolver $cacheTagResolver,
    ): EntityLoader {
        $scRepo = new StaticSalesChannelRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($entityName);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        return new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createLoaderWithCallableRepo(string $entityName, callable $callback): EntityLoader
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

        return new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createMinimalLoader(): EntityLoader
    {
        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('has')->willReturn(true);
        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);

        return new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
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

    private function productEntityLoader(): EntityLoader
    {
        return new EntityLoader(
            $this->createSalesChannelDefinitionRegistry(new SalesChannelProductDefinition()),
            $this->createDefinitionRegistry(new ProductDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );
    }

    /**
     * @param class-string<Entity> $entityClass
     */
    private function definitionStub(string $entityClass, string $entityName): EntityDefinition
    {
        $definition = static::createStub(EntityDefinition::class);
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
