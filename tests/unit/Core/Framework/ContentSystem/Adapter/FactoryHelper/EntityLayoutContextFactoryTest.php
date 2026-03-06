<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\LayoutResolutionResult;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
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

        $this->expectExceptionObject(ContentSystemException::layoutAssignmentNotFound(
            'product',
            $entityId,
            $context->getSalesChannel()->getId()
        ));

        $this->factory->resolveLayoutId('/product/' . $entityId, $context, $repository, $definition);
    }

    #[TestDox('resolves specification data with requirements from definition')]
    public function testReturnsSpecificationDataFromDefinition(): void
    {
        $entityId = Uuid::randomHex();
        $placeholders = PlaceholderValues::from(['productId' => $entityId]);

        $this->layoutResolver->method('resolve')
            ->willReturn(new LayoutResolutionResult(static::createStub(\Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignmentEntity::class), $placeholders));

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

    #[TestDox('returns cache tags derived from entity ID in path')]
    public function testResolveCacheTagsReturnsDerivedTagsFromPath(): void
    {
        $entityId = Uuid::randomHex();

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getCacheTags')->willReturn(['product-' . $entityId]);

        $result = $this->factory->resolveCacheTags('/product/' . $entityId, $definition);

        static::assertSame(['product-' . $entityId], $result);
    }

    #[TestDox('throws when path does not match expected route pattern')]
    public function testResolveCacheTagsThrowsWhenPathDoesNotMatchRoutePattern(): void
    {
        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getCacheTags')->willReturn([]);

        $this->expectExceptionObject(ContentSystemException::invalidEntityPath(
            'product',
            '/completely/invalid',
            '/product/{productId}'
        ));

        $this->factory->resolveCacheTags('/completely/invalid', $definition);
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
