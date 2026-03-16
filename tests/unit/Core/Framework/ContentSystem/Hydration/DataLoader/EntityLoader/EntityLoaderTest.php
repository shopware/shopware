<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Cache\EntityCacheTagResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
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
#[CoversClass(EntityLoader::class)]
class EntityLoaderTest extends TestCase
{
    #[TestDox('returns entity as requirement type identifier')]
    public function testGetRequirementTypeReturnsEntityString(): void
    {
        static::assertSame('entity', EntityLoader::getRequirementType());
    }

    #[TestDox('returns Entity wildcard as provided data type')]
    public function testGetProvidedDataReturnsEntityWildcard(): void
    {
        $descriptor = EntityLoader::getProvidedData();

        static::assertSame(Entity::class, $descriptor->className);
        static::assertSame([], $descriptor->genericParameters);
    }

    #[TestDox('returns cached result with cache tag when entity is loaded via sales channel repository')]
    public function testLoadReturnsCachedResultViaSalesChannelRepository(): void
    {
        $productId = Uuid::randomHex();
        $config = new EntityLoaderConfig('product', 'productId', []);
        $requirement = new DataRequirement('product', 'entity', $config);
        $element = ContentElementBuilder::create('product-detail')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $entity = $this->createEntityWithId($productId);
        $collection = new EntityCollection([$entity]);

        $scRepo = new StaticSalesChannelRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn('product-' . $productId);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame(['product-' . $productId], $result->getCacheTags());
    }

    #[TestDox('falls back to plain DAL repository when sales channel repository is not found')]
    public function testLoadFallsBackToPlainRepositoryWhenSalesChannelRepoNotFound(): void
    {
        $categoryId = Uuid::randomHex();
        $config = new EntityLoaderConfig('category', 'categoryId', []);
        $requirement = new DataRequirement('category', 'entity', $config);
        $element = ContentElementBuilder::create('category-detail')
            ->withProperty('categoryId', $categoryId)
            ->build();
        $context = Generator::generateSalesChannelContext();

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
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame(['category-route-' . $categoryId], $result->getCacheTags());
    }

    #[TestDox('returns uncacheable result when cache tag resolver returns null')]
    public function testLoadReturnsUncacheableWhenCacheTagResolverReturnsNull(): void
    {
        $productId = Uuid::randomHex();
        $config = new EntityLoaderConfig('product', 'productId', []);
        $requirement = new DataRequirement('product', 'entity', $config);
        $element = ContentElementBuilder::create('product-detail')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $entity = $this->createEntityWithId($productId);
        $collection = new EntityCollection([$entity]);

        $scRepo = new StaticSalesChannelRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn(null);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertSame($entity, $result->data);
        static::assertFalse($result->isCacheAware());
    }

    #[TestDox('lowercases entity ID before passing it to the repository')]
    public function testLoadLowercasesEntityId(): void
    {
        $productId = Uuid::randomHex();
        $upperCaseId = strtoupper($productId);
        $config = new EntityLoaderConfig('product', 'productId', []);
        $requirement = new DataRequirement('product', 'entity', $config);
        $element = ContentElementBuilder::create('product-detail')
            ->withProperty('productId', $upperCaseId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

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

        $loader = new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame([$productId], $capturedCriteria->getIds());
    }

    #[TestDox('adds associations from config to criteria when loading entity')]
    public function testLoadAddsAssociationsToCriteria(): void
    {
        $productId = Uuid::randomHex();
        $config = new EntityLoaderConfig('product', 'productId', ['manufacturer', 'cover']);
        $requirement = new DataRequirement('product', 'entity', $config);
        $element = ContentElementBuilder::create('product-detail')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;

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

        $loader = new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertNotEmpty($capturedCriteria->getAssociations());
    }

    #[TestDox('uses property name from config to look up element property')]
    public function testLoadUsesPropertyNameFromConfigToLookUpElementProperty(): void
    {
        $productId = Uuid::randomHex();
        $config = new EntityLoaderConfig('product', 'customPropName', []);
        $requirement = new DataRequirement('product', 'entity', $config);
        $element = ContentElementBuilder::create('product-detail')
            ->withProperty('customPropName', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $entity = $this->createEntityWithId($productId);
        $collection = new EntityCollection([$entity]);

        $scRepo = new StaticSalesChannelRepository([$collection]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);
        $cacheTagResolver->method('resolve')->willReturn('product-' . $productId);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertSame($entity, $result->data);
        static::assertTrue($result->isCacheAware());
    }

    #[TestDox('returns notFound result when config is not EntityLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $requirement = new DataRequirement('product', 'entity', new StubLoaderConfig());
        $element = ContentElementBuilder::create('product-detail')->build();
        $context = Generator::generateSalesChannelContext();

        $loader = $this->createMinimalLoader();
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when element property is not a string')]
    public function testLoadReturnsNotFoundWhenPropertyIsNotString(): void
    {
        $config = new EntityLoaderConfig('product', 'productId', []);
        $requirement = new DataRequirement('product', 'entity', $config);
        $element = ContentElementBuilder::create('product-detail')
            ->withProperty('productId', 42)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $loader = $this->createMinimalLoader();
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when entity is not found in repository')]
    public function testLoadReturnsNotFoundWhenEntityNotFoundInRepository(): void
    {
        $productId = Uuid::randomHex();
        $config = new EntityLoaderConfig('product', 'productId', []);
        $requirement = new DataRequirement('product', 'entity', $config);
        $element = ContentElementBuilder::create('product-detail')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $scRepo = new StaticSalesChannelRepository([new EntityCollection()]);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product');

        $cacheTagResolver = static::createStub(EntityCacheTagResolver::class);

        $scDefRegistry = static::createStub(SalesChannelDefinitionInstanceRegistry::class);
        $scDefRegistry->method('getSalesChannelRepository')->willReturn($scRepo);

        $defRegistry = static::createStub(DefinitionInstanceRegistry::class);
        $defRegistry->method('getByEntityName')->willReturn($definition);

        $loader = new EntityLoader($scDefRegistry, $defRegistry, $cacheTagResolver);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
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
