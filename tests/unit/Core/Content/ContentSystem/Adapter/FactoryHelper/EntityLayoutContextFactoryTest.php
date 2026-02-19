<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ContentLayoutAssignmentInterface;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\LayoutResolutionResult;
use Shopware\Core\Content\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Helper\RequestDataExtractor;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Content\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(EntityLayoutContextFactory::class)]
class EntityLayoutContextFactoryTest extends TestCase
{
    private EntityLayoutResolver&Stub $layoutResolver;

    private EntityLayoutContextFactory $factory;

    protected function setUp(): void
    {
        $this->layoutResolver = static::createStub(EntityLayoutResolver::class);
        $this->factory = new EntityLayoutContextFactory(
            $this->layoutResolver,
            new RequestDataExtractor()
        );
    }

    #[TestDox('returns true when path matches definition prefix')]
    public function testSupportsReturnsTrueWhenPathMatchesPrefix(): void
    {
        $definition = $this->createDefinitionMock('/product/');

        static::assertTrue($this->factory->supports('/product/abc123', $definition));
    }

    #[TestDox('returns false when path does not match definition prefix')]
    public function testSupportsReturnsFalseWhenPathDoesNotMatchPrefix(): void
    {
        $definition = $this->createDefinitionMock('/product/');

        static::assertFalse($this->factory->supports('/category/abc123', $definition));
    }

    #[TestDox('resolves layout ID from resolver')]
    public function testResolveLayoutIdReturnsLayoutId(): void
    {
        $layoutId = Uuid::randomHex();
        $entityId = Uuid::randomHex();

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');

        $this->layoutResolver->method('findLayoutId')
            ->willReturn($layoutId);

        $repository = $this->createRepository();
        $context = Generator::generateSalesChannelContext();

        $result = $this->factory->resolveLayoutId('/product/' . $entityId, $context, $repository, $definition);

        static::assertSame($layoutId, $result);
    }

    #[TestDox('throws when no layout assignment found')]
    public function testResolveLayoutIdThrowsWhenNoAssignment(): void
    {
        $entityId = Uuid::randomHex();

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');

        $this->layoutResolver->method('findLayoutId')
            ->willReturn(null);

        $repository = $this->createRepository();
        $context = Generator::generateSalesChannelContext();

        static::expectExceptionObject(ContentSystemException::layoutAssignmentNotFound(
            'product',
            $entityId,
            $context->getSalesChannel()->getId()
        ));

        $this->factory->resolveLayoutId('/product/' . $entityId, $context, $repository, $definition);
    }

    #[TestDox('resolves specification data with empty requirements when no parameter bindings are configured')]
    public function testResolveSpecificationData(): void
    {
        $entityId = Uuid::randomHex();
        $placeholders = PlaceholderValues::from(['productId' => $entityId]);
        $assignment = static::createStub(ContentLayoutAssignmentInterface::class);
        $assignment->method('getParameterBindings')->willReturn(null);

        $this->layoutResolver->method('resolve')
            ->willReturn(new LayoutResolutionResult($assignment, $placeholders));

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getPageDataRequirements')->willReturn([]);

        $repository = $this->createRepository();
        $context = Generator::generateSalesChannelContext();

        $result = $this->factory->resolveSpecificationData(
            '/product/' . $entityId,
            new Request(),
            $context,
            $repository,
            $definition
        );

        static::assertSame($placeholders, $result->placeholderValues);
        static::assertSame([], $result->dataRequirements);
    }

    #[TestDox('returns element ID from request query when present')]
    public function testResolveTargetElementIdReturnsElementIdWhenPresent(): void
    {
        $request = new Request(['elementId' => 'elem-42']);

        static::assertSame('elem-42', $this->factory->resolveTargetElementId($request));
    }

    #[TestDox('returns null when no element ID in request')]
    public function testResolveTargetElementIdReturnsNullWhenMissing(): void
    {
        $request = new Request();

        static::assertNull($this->factory->resolveTargetElementId($request));
    }

    #[TestDox('resolves cache tags by delegating to definition')]
    public function testResolveCacheTagsDelegatesToDefinition(): void
    {
        $entityId = Uuid::randomHex();

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getCacheTags')->willReturn(['product-' . $entityId]);

        $result = $this->factory->resolveCacheTags('/product/' . $entityId, $definition);

        static::assertSame(['product-' . $entityId], $result);
    }

    #[TestDox('throws when path does not match expected route pattern')]
    public function testExtractEntityIdThrowsOnInvalidPath(): void
    {
        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getCacheTags')->willReturn([]);

        static::expectExceptionObject(ContentSystemException::invalidEntityPath(
            'product',
            '/completely/invalid',
            '/product/{productId}'
        ));

        $this->factory->resolveCacheTags('/completely/invalid', $definition);
    }

    #[TestDox('remaps EntityLoaderConfig property when parameter bindings define a placeholder')]
    public function testTransformDataRequirements(): void
    {
        $entityId = Uuid::randomHex();
        $placeholders = PlaceholderValues::from(['productId' => $entityId]);

        $assignment = static::createStub(ContentLayoutAssignmentInterface::class);
        $assignment->method('getParameterBindings')->willReturn([
            'productId' => new ParameterBinding('productId', 'product_id'),
        ]);

        $this->layoutResolver->method('resolve')
            ->willReturn(new LayoutResolutionResult($assignment, $placeholders));

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getPageDataRequirements')->willReturn([
            new DataRequirement('product', EntityLoader::SOURCE, new EntityLoaderConfig('product', 'productId', [])),
        ]);

        $repository = $this->createRepository();
        $context = Generator::generateSalesChannelContext();

        $result = $this->factory->resolveSpecificationData(
            '/product/' . $entityId,
            new Request(),
            $context,
            $repository,
            $definition
        );

        static::assertCount(1, $result->dataRequirements);
        $config = $result->dataRequirements[0]->config;
        static::assertInstanceOf(EntityLoaderConfig::class, $config);
        static::assertSame('product_id', $config->property);
    }

    #[TestDox('passes through non-entity-loader requirements unchanged when bindings are present')]
    public function testTransformDataRequirementsPassthrough(): void
    {
        $entityId = Uuid::randomHex();
        $placeholders = PlaceholderValues::from(['productId' => $entityId]);

        $assignment = static::createStub(ContentLayoutAssignmentInterface::class);
        $assignment->method('getParameterBindings')->willReturn([
            'productId' => new ParameterBinding('productId', 'product_id'),
        ]);

        $this->layoutResolver->method('resolve')
            ->willReturn(new LayoutResolutionResult($assignment, $placeholders));

        $navConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getPageDataRequirements')->willReturn([
            new DataRequirement('nav', 'navigation', $navConfig),
        ]);

        $repository = $this->createRepository();
        $context = Generator::generateSalesChannelContext();

        $result = $this->factory->resolveSpecificationData(
            '/product/' . $entityId,
            new Request(),
            $context,
            $repository,
            $definition
        );

        static::assertCount(1, $result->dataRequirements);
        static::assertSame('navigation', $result->dataRequirements[0]->source);
        static::assertSame($navConfig, $result->dataRequirements[0]->config);
    }

    #[TestDox('skips remapping when binding placeholder is empty string')]
    public function testTransformDataRequirementsEmptyBinding(): void
    {
        $entityId = Uuid::randomHex();
        $placeholders = PlaceholderValues::from(['productId' => $entityId]);

        $assignment = static::createStub(ContentLayoutAssignmentInterface::class);
        $assignment->method('getParameterBindings')->willReturn([
            'productId' => new ParameterBinding('productId', ''),
        ]);

        $this->layoutResolver->method('resolve')
            ->willReturn(new LayoutResolutionResult($assignment, $placeholders));

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getPageDataRequirements')->willReturn([
            new DataRequirement('product', EntityLoader::SOURCE, new EntityLoaderConfig('product', 'productId', [])),
        ]);

        $repository = $this->createRepository();
        $context = Generator::generateSalesChannelContext();

        $result = $this->factory->resolveSpecificationData(
            '/product/' . $entityId,
            new Request(),
            $context,
            $repository,
            $definition
        );

        static::assertCount(1, $result->dataRequirements);
        $config = $result->dataRequirements[0]->config;
        static::assertInstanceOf(EntityLoaderConfig::class, $config);
        static::assertSame('productId', $config->property);
    }

    /**
     * @return StaticEntityRepository<ProductContentLayoutCollection>
     */
    private function createRepository(): StaticEntityRepository
    {
        /** @var StaticEntityRepository<ProductContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([]);

        return $repository;
    }

    private function createDefinitionMock(
        string $pathPrefix = '/product/',
        string $entityType = 'product',
        string $entityIdField = 'productId',
        string $routePattern = '{productId}'
    ): AbstractContentLayoutAssignableDefinition&Stub {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutPathPrefix')->willReturn($pathPrefix);
        $definition->method('getContentLayoutEntityType')->willReturn($entityType);
        $definition->method('getContentLayoutEntityIdField')->willReturn($entityIdField);
        $definition->method('getContentLayoutRoutePattern')->willReturn($routePattern);

        return $definition;
    }
}
