<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfig;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(NavigationDataLoader::class)]
class NavigationDataLoaderTest extends TestCase
{
    private NavigationAliasResolver $aliasResolver;

    private NavigationDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->aliasResolver = new NavigationAliasResolver();
        $this->dataLoader = new NavigationDataLoader(
            static::createStub(NavigationLoaderInterface::class),
            $this->aliasResolver,
        );
    }

    #[TestDox('returns navigation source type identifier')]
    public function testGetRequirementTypeReturnsNavigationString(): void
    {
        static::assertSame('navigation', NavigationDataLoader::getRequirementType());
    }

    #[TestDox('declares Tree as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->dataLoader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(Tree::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('loads navigation tree with explicit rootId')]
    public function testLoadWithExplicitRootIdCallsNavigationLoader(): void
    {
        $rootId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => $rootId, 'depth' => 2, 'activeProperty' => $activeId]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('lowercases an uppercase configured rootId instead of rejecting it')]
    public function testLoadLowercasesUppercaseRootIdBeforeCallingNavigationLoader(): void
    {
        // A literal fixture, not Uuid::randomHex(): a random hex value can consist only of digits, in which
        // case uppercasing it is a no-op and the test would pass even with the normalization removed. This
        // value is guaranteed to contain alphabetic hex nibbles.
        $rootId = '0123456789abcdef0123456789abcdef';
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();

        // Uuid::fromHexToBytes() calls @hex2bin(), which accepts uppercase hex, so an uppercase configured
        // rootId reached the database before any guard existed. Uuid::VALID_PATTERN is lowercase-only, so a
        // guard on the raw value would degrade an id that works.
        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => strtoupper($rootId), 'depth' => 2, 'activeProperty' => $activeId]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertSame($tree, $result->data);
    }

    #[TestDox('lowercases an uppercase active ID instead of falling back to the root')]
    public function testLoadLowercasesUppercaseActiveIdBeforeCallingNavigationLoader(): void
    {
        // Literal, deterministically distinct fixtures, not independently generated Uuid::randomHex() values:
        // two random ids could coincide, which would make the assertion that the active id (rather than the
        // root) reached the collaborator hold vacuously. $activeId also needs an alphabetic hex nibble, since a
        // digits-only value would make uppercasing it a no-op and pass even with the normalization removed.
        $rootId = '0123456789abcdef0123456789abcdef';
        $activeId = 'fedcba9876543210fedcba9876543210';
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();

        // LoaderInputResolver::dereference() returns the stored string unchanged, so an uppercase stored
        // activeId arrives raw. Guarding it before the lowercase silently swaps the active category for the
        // root, which the rootId argument below distinguishes from a legitimate fallback.
        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $inputs = $this->resolve(
            new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'activeId'),
            ['activeId' => strtoupper($activeId)],
        );

        $result = $dataLoader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($tree, $result->data);
    }

    #[TestDox('resolves main-navigation alias to sales channel navigation category ID')]
    public function testLoadResolvesMainNavigationAliasToNavigationCategoryId(): void
    {
        $navCategoryId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId($navCategoryId);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $navCategoryId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => 'main-navigation', 'depth' => 2, 'activeProperty' => $activeId]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('returns cachedExternally result with empty cache tags')]
    public function testLoadReturnsCachedExternallyResult(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();

        $navigationLoader = static::createStub(NavigationLoaderInterface::class);
        $navigationLoader->method('load')->willReturn($tree);
        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);

        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => $rootId, 'depth' => 2, 'activeProperty' => null]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @param non-empty-string $alias
     * @param callable(SalesChannelEntity, string): void $assignRootToSalesChannel
     */
    #[TestDox('resolves the optional service and footer roots to their sales channel category')]
    #[DataProvider('optionalRootProvider')]
    public function testLoadResolvesTheOptionalSalesChannelRoots(string $alias, callable $assignRootToSalesChannel): void
    {
        $rootId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();
        $assignRootToSalesChannel($context->getSalesChannel(), $rootId);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => $alias, 'depth' => 2, 'activeProperty' => $activeId]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    /**
     * @return iterable<string, array{non-empty-string, callable(SalesChannelEntity, string): void}>
     */
    public static function optionalRootProvider(): iterable
    {
        yield 'service navigation' => ['service-navigation', static function (SalesChannelEntity $salesChannel, string $rootId): void {
            $salesChannel->setServiceCategoryId($rootId);
        }];

        yield 'footer navigation' => ['footer-navigation', static function (SalesChannelEntity $salesChannel, string $rootId): void {
            $salesChannel->setFooterCategoryId($rootId);
        }];
    }

    #[TestDox('reads the active ID from the element property the config names')]
    public function testLoadReadsActiveIdFromCustomActiveProperty(): void
    {
        $rootId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $inputs = $this->resolve(
            new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'categoryId'),
            ['categoryId' => $activeId],
        );

        $result = $dataLoader->load($inputs, self::requirement(), $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('passes configured depth to navigation loader')]
    public function testLoadPassesConfiguredDepthToNavigationLoader(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 5)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $dataLoader->load(
            new LoaderInputs(['rootId' => $rootId, 'depth' => 5, 'activeProperty' => null]),
            self::requirement(),
            $context,
            new Request(),
        );
    }

    #[TestDox('resolves an unset rootId to the declared main-navigation default')]
    public function testUnsetRootIdResolvesToDeclaredMainNavigationDefault(): void
    {
        $navCategoryId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId($navCategoryId);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($activeId, $context, $navCategoryId, 3)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $inputs = $this->resolve(
            new NavigationLoaderConfig(rootId: null, depth: 3, activeProperty: 'activeId'),
            ['activeId' => $activeId],
        );

        $result = $dataLoader->load($inputs, self::requirement(), $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('falls back to the sales channel navigation depth when the config declares none')]
    public function testLoadFallsBackToSalesChannelDepthWhenConfigDeclaresNone(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryDepth(4);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 4)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $inputs = $this->resolve(new NavigationLoaderConfig(rootId: $rootId), []);

        $result = $dataLoader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($tree, $result->data);
    }

    #[TestDox('an explicitly configured depth still wins over the sales channel setting')]
    public function testLoadPrefersConfiguredDepthOverSalesChannelDepth(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryDepth(4);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 1)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $inputs = $this->resolve(new NavigationLoaderConfig(rootId: $rootId, depth: 1), []);

        $result = $dataLoader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($tree, $result->data);
    }

    #[TestDox('does not resolve an uppercase built-in alias')]
    public function testLoadDoesNotResolveUppercaseAliasAsBuiltinAlias(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId('0123456789abcdef0123456789abcdef');

        // NavigationAliasResolver::resolve() matches its alias constants case-sensitively (a `match` against
        // the lowercase literal 'main-navigation'), so 'MAIN-NAVIGATION' falls through its default arm
        // unchanged. The value is then normalized to lowercase, which is not a valid uuid, so the loader
        // degrades to notFound() without reaching the navigation loader. Normalizing before alias resolution
        // would instead resolve the sales channel's navigation category set above and call the loader.
        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader->expects($this->never())->method('load');

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => 'MAIN-NAVIGATION']),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('uses rootId as activeId when the active property is unresolved')]
    public function testLoadUsesRootIdAsActiveIdWhenActivePropertyIsUnresolved(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);
        $context = Generator::generateSalesChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => $rootId, 'depth' => 2, 'activeProperty' => null]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('uses rootId as activeId when the active property resolves to something other than a UUID')]
    public function testLoadUsesRootIdAsActiveIdWhenActivePropertyIsNotAUuid(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);
        $context = Generator::generateSalesChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => $rootId, 'depth' => 2, 'activeProperty' => '']),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('falls back to the root when the activeId placeholder was not resolved')]
    public function testLoadFallsBackToRootWhenActiveIdIsAnUnresolvedPlaceholder(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);
        $context = Generator::generateSalesChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 2)
            ->willReturn($tree);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        // A layout that is not rooted on a category leaves "{{categoryId}}" in place.
        $inputs = $this->resolve(
            new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'activeId'),
            ['activeId' => '{{categoryId}}'],
        );

        $result = $dataLoader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($tree, $result->data);
    }

    #[TestDox('returns notFound result when an alias does not resolve because the sales channel has no such category')]
    public function testLoadReturnsNotFoundWhenAliasDoesNotResolve(): void
    {
        $context = Generator::generateSalesChannelContext();
        static::assertNull($context->getSalesChannel()->getServiceCategoryId());

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader->expects($this->never())->method('load');

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => 'service-navigation', 'depth' => 2, 'activeProperty' => null]),
            self::requirement(),
            $context,
            new Request(),
        );

        // All three together pin notFound() rather than uncacheable(), which differs only in its tags.
        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the navigation loader throws the Shopware exception $_dataName')]
    public function testLoadReturnsNotFoundWhenNavigationLoaderThrows(\Throwable $exception): void
    {
        $rootId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => $rootId, 'depth' => 2, 'activeProperty' => null]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertFalse($result->hasData());
        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * Sample domain exceptions, not one row per catch arm: the loader catches the single covering ancestor
     * `ShopwareHttpException`, so no row maps to a clause of its own.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        // NavigationLoader delegates to AbstractNavigationRoute, whose TreeBuildingNavigationRoute decorator
        // reaches NavigationRoute, which throws this. The factory is CategoryException::categoryNotFound(),
        // but the class it returns is CategoryNotFoundException, which extends ShopwareHttpException directly
        // and not CategoryException.
        yield 'active category missing from the navigation tree' => [
            CategoryException::categoryNotFound('category-missing'),
        ];

        // Not a reachability claim: this row pins the clause to the ancestor rather than to the chain's own
        // classes, using a class the navigation chain does not produce.
        yield 'a class outside the chain that extends ShopwareHttpException directly' => [
            new DecorationPatternException(NavigationLoaderInterface::class),
        ];
    }

    #[TestDox('lets a TypeError from the navigation loader propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideShopwareHttpExceptionPropagate(): void
    {
        $rootId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();

        $typeError = new \TypeError('Argument #4 ($depth) must be of type int, null given');

        $navigationLoader = static::createStub(NavigationLoaderInterface::class);
        $navigationLoader
            ->method('load')
            ->willThrowException($typeError);

        $dataLoader = new NavigationDataLoader($navigationLoader, $this->aliasResolver);

        try {
            $dataLoader->load(
                new LoaderInputs(['rootId' => $rootId, 'depth' => 2, 'activeProperty' => null]),
                self::requirement(),
                $context,
                new Request(),
            );

            static::fail('Expected the TypeError to propagate out of load() instead of degrading to notFound');
        } catch (\TypeError $caught) {
            static::assertSame($typeError, $caught);
        }
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolve(NavigationLoaderConfig $config, array $properties): LoaderInputs
    {
        return (new LoaderInputResolver())->resolve($this->dataLoader->configSpecification(), $config, $properties);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('navKey', 'navigation', new NavigationLoaderConfig());
    }
}
