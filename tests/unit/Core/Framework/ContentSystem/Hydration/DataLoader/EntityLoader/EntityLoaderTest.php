<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
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
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(EntityLoader::class)]
class EntityLoaderTest extends TestCase
{
    #[TestDox('returns entity as requirement type identifier')]
    public function testGetRequirementTypeReturnsEntityString(): void
    {
        static::assertSame('entity', EntityLoader::getRequirementType());
    }

    #[TestDox('overrides provided types with all registered entity classes')]
    public function testOverrideProvidedTypesReturnsAllEntities(): void
    {
        $productDef = static::createStub(EntityDefinition::class);
        $productDef->method('getEntityClass')->willReturn(ProductEntity::class);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$productDef]);

        $loader = new EntityLoader(
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            $registry,
            static::createStub(EntityCacheTagResolver::class),
        );

        $types = $loader->overrideProvidedTypes([]);

        static::assertCount(1, $types);
        static::assertSame(ProductEntity::class, $types[0]->className);
    }

    #[TestDox('excludes ArrayEntity from overridden types')]
    public function testOverrideProvidedTypesExcludesArrayEntity(): void
    {
        $arrayDef = static::createStub(EntityDefinition::class);
        $arrayDef->method('getEntityClass')->willReturn(ArrayEntity::class);

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$arrayDef]);

        $loader = new EntityLoader(
            static::createStub(SalesChannelDefinitionInstanceRegistry::class),
            $registry,
            static::createStub(EntityCacheTagResolver::class),
        );

        $types = $loader->overrideProvidedTypes([]);

        static::assertSame([], $types);
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
        $defRegistry->method('getByEntityName')->willReturn($definition);

        return new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createMinimalLoader(): EntityLoader
    {
        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);

        return new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
    }

    private function createEntityWithId(string $id): Entity
    {
        $entity = new Entity();
        $entity->setUniqueIdentifier($id);

        return $entity;
    }
}
