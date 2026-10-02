<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\RootContextMapper;
use Shopware\Core\Framework\ContentSystem\PlaceholderValues;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityLayoutContextFactory::class)]
class EntityLayoutContextFactoryTest extends TestCase
{
    private const DEFAULT_LAYOUT_CONFIG_KEY = 'core.content_system.default_product_content_layout';

    private EntityLayoutResolver&Stub $layoutResolver;

    private EntityLayoutContextFactory $factory;

    private StaticSystemConfigService $systemConfigService;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->layoutResolver = static::createStub(EntityLayoutResolver::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->factory = $this->createFactory(static::createStub(CacheTagCollector::class));
        $this->ids = new IdsCollection();
    }

    #[DataProvider('supportsProvider')]
    #[TestDox('reports whether $_dataName is supported')]
    public function testSupports(string $path, bool $expected): void
    {
        $definition = $this->createDefinitionMock('/product/');

        static::assertSame($expected, $this->factory->supports($path, $definition));
    }

    #[TestDox('resolves layout ID from resolver')]
    public function testResolveLayoutIdReturnsLayoutId(): void
    {
        $layoutId = $this->ids->get('layout');
        $entityId = $this->ids->get('entity');

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');

        $this->layoutResolver->method('findLayoutId')
            ->willReturn($layoutId);

        $repository = $this->createRepository();
        $context = Generator::generateSalesChannelContext();

        $result = $this->factory->resolveLayoutId('/product/' . $entityId, $context, $repository, $definition);

        static::assertSame($layoutId, $result);
    }

    #[TestDox('prefers the explicit layout assignment over the default layout of the entity type')]
    public function testResolveLayoutIdPrefersExplicitAssignmentOverDefault(): void
    {
        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getContentLayoutDefaultConfigKey')->willReturn(self::DEFAULT_LAYOUT_CONFIG_KEY);
        $this->systemConfigService->set(self::DEFAULT_LAYOUT_CONFIG_KEY, $this->ids->get('default-layout'));

        $this->layoutResolver->method('findLayoutId')
            ->willReturn($this->ids->get('explicit-layout'));

        $result = $this->factory->resolveLayoutId(
            '/product/' . $this->ids->get('entity'),
            Generator::generateSalesChannelContext(),
            $this->createRepository(),
            $definition,
        );

        static::assertSame($this->ids->get('explicit-layout'), $result);
    }

    #[TestDox('uses the assignment of the first fallback entity with one before the default layout')]
    public function testResolveLayoutIdTriesFallbackEntitiesBeforeDefault(): void
    {
        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getContentLayoutDefaultConfigKey')->willReturn(self::DEFAULT_LAYOUT_CONFIG_KEY);
        $this->systemConfigService->set(self::DEFAULT_LAYOUT_CONFIG_KEY, $this->ids->get('default-layout'));

        $this->layoutResolver->method('findLayoutId')
            ->willReturnCallback(fn (string $field, string $entityId) => $entityId === $this->ids->get('parent')
                ? $this->ids->get('parent-layout')
                : null);

        $result = $this->factory->resolveLayoutId(
            '/product/' . $this->ids->get('entity'),
            Generator::generateSalesChannelContext(),
            $this->createRepository(),
            $definition,
            [$this->ids->get('parent')],
        );

        static::assertSame($this->ids->get('parent-layout'), $result);
    }

    #[TestDox('falls back to the default layout of the entity type when no explicit assignment exists')]
    public function testResolveLayoutIdFallsBackToDefaultLayout(): void
    {
        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getContentLayoutDefaultConfigKey')->willReturn(self::DEFAULT_LAYOUT_CONFIG_KEY);
        $this->systemConfigService->set(self::DEFAULT_LAYOUT_CONFIG_KEY, $this->ids->get('default-layout'));

        $this->layoutResolver->method('findLayoutId')
            ->willReturn(null);

        $result = $this->factory->resolveLayoutId(
            '/product/' . $this->ids->get('entity'),
            Generator::generateSalesChannelContext(),
            $this->createRepository(),
            $definition,
        );

        static::assertSame($this->ids->get('default-layout'), $result);
    }

    #[TestDox('prefers the sales channel default layout over the global default layout')]
    public function testResolveLayoutIdPrefersSalesChannelDefaultLayout(): void
    {
        $context = Generator::generateSalesChannelContext();

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getContentLayoutDefaultConfigKey')->willReturn(self::DEFAULT_LAYOUT_CONFIG_KEY);
        $this->systemConfigService->set(self::DEFAULT_LAYOUT_CONFIG_KEY, $this->ids->get('global-default-layout'));
        $this->systemConfigService->set(self::DEFAULT_LAYOUT_CONFIG_KEY, $this->ids->get('channel-default-layout'), $context->getSalesChannelId());

        $this->layoutResolver->method('findLayoutId')
            ->willReturn(null);

        $result = $this->factory->resolveLayoutId(
            '/product/' . $this->ids->get('entity'),
            $context,
            $this->createRepository(),
            $definition,
        );

        static::assertSame($this->ids->get('channel-default-layout'), $result);
    }

    #[TestDox('throws when the default layout key is configured but empty')]
    public function testResolveLayoutIdThrowsWhenDefaultLayoutIsEmpty(): void
    {
        $entityId = $this->ids->get('entity');
        $context = Generator::generateSalesChannelContext();

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getContentLayoutDefaultConfigKey')->willReturn(self::DEFAULT_LAYOUT_CONFIG_KEY);
        $this->systemConfigService->set(self::DEFAULT_LAYOUT_CONFIG_KEY, '');

        $this->layoutResolver->method('findLayoutId')
            ->willReturn(null);

        $this->expectExceptionObject(ContentSystemException::layoutAssignmentNotFound(
            'product',
            $entityId,
            $context->getSalesChannel()->getId()
        ));

        $this->factory->resolveLayoutId('/product/' . $entityId, $context, $this->createRepository(), $definition);
    }

    #[TestDox('resolves specification data without requiring a layout assignment')]
    public function testReturnsSpecificationDataFromDefinition(): void
    {
        $entityId = $this->ids->get('entity');
        $placeholders = PlaceholderValues::from(['productId' => $entityId]);

        $this->layoutResolver->method('resolvePlaceholders')
            ->willReturn($placeholders);

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getPageDataRequirements')->willReturn([]);

        $context = Generator::generateSalesChannelContext();

        $result = $this->factory->resolveSpecificationData(
            '/product/' . $entityId,
            new Request(),
            $context,
            $definition
        );

        static::assertSame($placeholders, $result->placeholderValues);
        static::assertSame([], $result->dataRequirements);
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('resolveTargetElementIdProvider')]
    #[TestDox('returns the target element id for $_dataName')]
    public function testResolveTargetElementId(array $query, ?string $expected): void
    {
        static::assertSame($expected, $this->factory->resolveTargetElementId(new Request($query)));
    }

    #[TestDox('returns cache tags derived from entity ID in path')]
    public function testResolveCacheTagsReturnsDerivedTagsFromPath(): void
    {
        $entityId = $this->ids->get('entity');

        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getCacheTags')->willReturn(['product-' . $entityId]);

        $result = $this->factory->resolveCacheTags('/product/' . $entityId, $definition);

        static::assertSame(['product-' . $entityId], $result);
    }

    /**
     * The tag is added even when no default is set, so a legacy CMS page cached in that state is invalidated once a
     * default layout is configured.
     */
    #[TestDox('tags the request with the default layout config key when falling back to the default layout')]
    public function testResolveLayoutIdTagsDefaultLayoutConfigKeyOnFallback(): void
    {
        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getContentLayoutDefaultConfigKey')->willReturn(self::DEFAULT_LAYOUT_CONFIG_KEY);

        $this->layoutResolver->method('findLayoutId')
            ->willReturn(null);

        $cacheTagCollector = static::createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with('config.' . self::DEFAULT_LAYOUT_CONFIG_KEY);

        $this->expectException(ContentSystemException::class);

        $this->createFactory($cacheTagCollector)->resolveLayoutId(
            '/product/' . $this->ids->get('entity'),
            Generator::generateSalesChannelContext(),
            $this->createRepository(),
            $definition,
        );
    }

    #[TestDox('does not tag the request with the default layout config key when an explicit assignment exists')]
    public function testResolveLayoutIdDoesNotTagDefaultLayoutConfigKeyForExplicitAssignment(): void
    {
        $definition = $this->createDefinitionMock('/product/', 'product', 'productId', '{productId}');
        $definition->method('getContentLayoutDefaultConfigKey')->willReturn(self::DEFAULT_LAYOUT_CONFIG_KEY);

        $this->layoutResolver->method('findLayoutId')
            ->willReturn($this->ids->get('explicit-layout'));

        $cacheTagCollector = static::createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->never())
            ->method('addTag');

        $this->createFactory($cacheTagCollector)->resolveLayoutId(
            '/product/' . $this->ids->get('entity'),
            Generator::generateSalesChannelContext(),
            $this->createRepository(),
            $definition,
        );
    }

    #[TestDox('throws when no layout assignment found')]
    public function testResolveLayoutIdThrowsWhenNoAssignment(): void
    {
        $entityId = $this->ids->get('entity');

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
     * @return iterable<string, array{string, bool}>
     */
    public static function supportsProvider(): iterable
    {
        yield 'a path matching the definition prefix' => ['/product/abc123', true];
        yield 'a path not matching the definition prefix' => ['/category/abc123', false];
    }

    /**
     * @return iterable<string, array{array<string, string>, ?string}>
     */
    public static function resolveTargetElementIdProvider(): iterable
    {
        yield 'an element id present in the request query' => [['elementId' => 'elem-42'], 'elem-42'];
        yield 'no element id in the request query' => [[], null];
    }

    private function createFactory(CacheTagCollector $cacheTagCollector): EntityLayoutContextFactory
    {
        return new EntityLayoutContextFactory(
            $this->layoutResolver,
            static::createStub(RootContextMapper::class),
            $this->systemConfigService,
            $cacheTagCollector,
        );
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
