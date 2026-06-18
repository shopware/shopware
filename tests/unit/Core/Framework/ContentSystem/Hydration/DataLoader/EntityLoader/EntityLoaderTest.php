<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader;

use PHPUnit\Framework\Attributes\CoversClass;
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
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayEntity;
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
#[CoversClass(EntityLoader::class)]
class EntityLoaderTest extends TestCase
{
    #[TestDox('declares the sales-channel entity class for an entity that has a sales-channel definition')]
    public function testProducibleTypesDeclaresSalesChannelClassForEntityWithVariant(): void
    {
        $loader = new EntityLoader(
            $this->createSalesChannelDefinitionRegistry(new SalesChannelProductDefinition()),
            $this->createDefinitionRegistry(new ProductDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(SalesChannelProductEntity::class, $capabilities[0]->producedType);
        static::assertSame(['entity' => 'product'], $capabilities[0]->configTemplate);
        static::assertSame(['property'], $capabilities[0]->requiredConfigKeys);
    }

    #[TestDox('declares the base entity class for an entity without a sales-channel definition')]
    public function testProducibleTypesDeclaresBaseClassForEntityWithoutVariant(): void
    {
        $loader = new EntityLoader(
            $this->createSalesChannelDefinitionRegistry(),
            $this->createDefinitionRegistry(new MediaDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        $capabilities = $loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(MediaEntity::class, $capabilities[0]->producedType);
        static::assertSame(['entity' => 'media'], $capabilities[0]->configTemplate);
        static::assertSame(['property'], $capabilities[0]->requiredConfigKeys);
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
        $loader = new EntityLoader(
            $this->createSalesChannelDefinitionRegistry(new SalesChannelProductDefinition()),
            $this->createDefinitionRegistry(new ProductDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

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

    #[TestDox('declares exactly the config keys EntityLoaderConfigSerializer::decode() requires (drift guard)')]
    public function testProducibleTypesConfigKeysMatchSerializerRequiredKeys(): void
    {
        $loader = new EntityLoader(
            $this->createSalesChannelDefinitionRegistry(new SalesChannelProductDefinition()),
            $this->createDefinitionRegistry(new ProductDefinition()),
            static::createStub(EntityCacheTagResolver::class),
        );

        $capability = $loader->producibleTypes()[0];
        $declaredKeys = [...array_keys($capability->configTemplate), ...$capability->requiredConfigKeys];
        sort($declaredKeys);

        static::assertSame(['entity', 'property'], $declaredKeys);

        // Drive decode() purely from the keys the capability declares: if the capability drops a key the
        // serializer requires (or decode() gains a new required key), decode() throws and this fails.
        // EntityLoaderConfigSerializerTest pins necessity (decode rejects either key's absence).
        $input = [];
        foreach ($declaredKeys as $key) {
            $input[$key] = 'product';
        }

        static::assertInstanceOf(EntityLoaderConfig::class, (new EntityLoaderConfigSerializer())->decode($input));
    }

    #[TestDox('returns cached result with cache tag when entity is loaded via sales channel repository')]
    public function testLoadReturnsCachedResultViaSalesChannelRepository(): void
    {
        $productId = Uuid::randomHex();
        $entity = $this->createEntityWithId($productId);

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn('product-' . $productId);

        $loader = $this->createLoaderWithSalesChannelRepo('product', new EntityCollection([$entity]), $cacheTagResolver);
        $result = $this->loadEntity($loader, 'product', 'productId', $productId);

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame(['product-' . $productId], $result->getCacheTags());
    }

    #[TestDox('falls back to context repository when sales channel repository is unavailable')]
    public function testLoadFallsBackToContextRepositoryWhenSalesChannelRepoUnavailable(): void
    {
        $categoryId = Uuid::randomHex();
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

        $loader = new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $this->loadEntity($loader, 'category', 'categoryId', $categoryId);

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame(['category-route-' . $categoryId], $result->getCacheTags());
    }

    #[TestDox('returns uncacheable result when cache tag resolver returns null')]
    public function testLoadReturnsUncacheableWhenCacheTagResolverReturnsNull(): void
    {
        $productId = Uuid::randomHex();
        $entity = $this->createEntityWithId($productId);

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $loader = $this->createLoaderWithSalesChannelRepo('product', new EntityCollection([$entity]), $cacheTagResolver);
        $result = $this->loadEntity($loader, 'product', 'productId', $productId);

        static::assertSame($entity, $result->data);
        static::assertFalse($result->isCacheAware());
    }

    #[TestDox('lowercases entity ID before passing it to the repository')]
    public function testLoadLowercasesEntityId(): void
    {
        $productId = Uuid::randomHex();
        $upperCaseId = strtoupper($productId);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('product', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $this->loadEntity($loader, 'product', 'productId', $upperCaseId);

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame([$productId], $capturedCriteria->getIds());
    }

    #[TestDox('adds associations from config to criteria when loading entity')]
    public function testLoadAddsAssociationsToCriteria(): void
    {
        $productId = Uuid::randomHex();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

        $loader = $this->createLoaderWithCallableRepo('product', static function (Criteria $criteria) use (&$capturedCriteria): EntityCollection {
            $capturedCriteria = $criteria;

            return new EntityCollection();
        });

        $config = new EntityLoaderConfig('product', 'productId', ['manufacturer', 'cover']);
        $requirement = new DataRequirement('product', 'entity', $config);
        $element = ContentElementBuilder::create('product-detail')
            ->withProperty('productId', $productId)
            ->build();

        $loader->load($element, $requirement, Generator::generateSalesChannelContext(), new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertCount(2, $capturedCriteria->getAssociations());
    }

    #[TestDox('uses property name from config to look up element property')]
    public function testLoadUsesPropertyNameFromConfigToLookUpElementProperty(): void
    {
        $productId = Uuid::randomHex();
        $entity = $this->createEntityWithId($productId);

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn('product-' . $productId);

        $loader = $this->createLoaderWithSalesChannelRepo('product', new EntityCollection([$entity]), $cacheTagResolver);

        $config = new EntityLoaderConfig('product', 'customPropName', []);
        $requirement = new DataRequirement('product', 'entity', $config);
        $element = ContentElementBuilder::create('product-detail')
            ->withProperty('customPropName', $productId)
            ->build();

        $result = $loader->load($element, $requirement, Generator::generateSalesChannelContext(), new Request());

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
    }

    #[TestDox('returns notFound result when config is not EntityLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $requirement = new DataRequirement('product', 'entity', new StubLoaderConfig());
        $element = ContentElementBuilder::create('product-detail')->build();

        $result = $this->createMinimalLoader()->load($element, $requirement, Generator::generateSalesChannelContext(), new Request());

        $this->assertNotFoundResult($result);
    }

    #[TestDox('returns notFound result when element property is not a string')]
    public function testLoadReturnsNotFoundWhenPropertyIsNotString(): void
    {
        $config = new EntityLoaderConfig('product', 'productId', []);
        $requirement = new DataRequirement('productId', 'entity', $config);
        $element = ContentElementBuilder::create('product-detail')
            ->withProperty('productId', 42)
            ->build();

        $result = $this->createMinimalLoader()->load($element, $requirement, Generator::generateSalesChannelContext(), new Request());

        $this->assertNotFoundResult($result);
    }

    #[TestDox('returns notFound result when entity is not found in repository')]
    public function testLoadReturnsNotFoundWhenEntityNotFoundInRepository(): void
    {
        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $loader = $this->createLoaderWithSalesChannelRepo('product', new EntityCollection(), $cacheTagResolver);
        $result = $this->loadEntity($loader, 'product', 'productId', 'product-id');

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

        $result = $this->loadEntity($loader, 'ghost', 'ghostId', 'some-id');

        $this->assertNotFoundResult($result);
    }

    private function assertNotFoundResult(ContentDataLoaderResult $result): void
    {
        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @param non-empty-string $entityName
     * @param non-empty-string $propertyName
     */
    private function loadEntity(
        EntityLoader $loader,
        string $entityName,
        string $propertyName,
        string $propertyValue,
    ): ContentDataLoaderResult {
        $config = new EntityLoaderConfig($entityName, $propertyName, []);
        $requirement = new DataRequirement($propertyName, 'entity', $config);
        $element = ContentElementBuilder::create($entityName . '-detail')
            ->withProperty($propertyName, $propertyValue)
            ->build();

        return $loader->load($element, $requirement, Generator::generateSalesChannelContext(), new Request());
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
