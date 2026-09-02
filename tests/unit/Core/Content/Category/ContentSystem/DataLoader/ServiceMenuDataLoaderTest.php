<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\ServiceMenuDataLoader;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\ServiceMenuLoaderConfig;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceMenuDataLoader::class)]
class ServiceMenuDataLoaderTest extends TestCase
{
    private NavigationLoaderInterface&Stub $navigationLoader;

    private ServiceMenuDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->navigationLoader = static::createStub(NavigationLoaderInterface::class);
        $this->dataLoader = new ServiceMenuDataLoader($this->navigationLoader, new NavigationAliasResolver());
    }

    #[TestDox('returns service_menu source type identifier')]
    public function testGetRequirementTypeReturnsServiceMenuString(): void
    {
        static::assertSame('service_menu', ServiceMenuDataLoader::getRequirementType());
    }

    #[TestDox('declares CategoryCollection as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->dataLoader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(CategoryCollection::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('loads service menu categories flattened from navigation tree')]
    public function testLoadReturnsFlattenedCategoryCollection(): void
    {
        $serviceCategoryId = Uuid::randomHex();
        $categoryA = new CategoryEntity();
        $categoryA->setId('category-alice');
        $categoryA->setUniqueIdentifier('category-alice');
        $categoryB = new CategoryEntity();
        $categoryB->setId('category-bob');
        $categoryB->setUniqueIdentifier('category-bob');

        $tree = new Tree(null, [
            new TreeItem($categoryA, []),
            new TreeItem($categoryB, []),
        ]);

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setServiceCategoryId($serviceCategoryId);

        $navigationLoader = static::createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($serviceCategoryId, $context, $serviceCategoryId, 1)
            ->willReturn($tree);

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => 'service-navigation']),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(2, $result->data);
        static::assertSame($categoryA, $result->data->first());
        static::assertSame($categoryB, $result->data->last());
    }

    #[TestDox('uses explicit rootId input instead of the service-navigation alias')]
    public function testLoadUsesExplicitRootIdInput(): void
    {
        $rootId = Uuid::randomHex();
        $category = new CategoryEntity();
        $category->setId('category-alice');
        $category->setUniqueIdentifier('category-alice');

        $tree = new Tree(null, [new TreeItem($category, [])]);

        $context = Generator::generateSalesChannelContext();

        $navigationLoader = static::createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 1)
            ->willReturn($tree);

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => $rootId]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(1, $result->data);
    }

    #[TestDox('lowercases an uppercase configured rootId instead of rejecting it')]
    public function testLoadLowercasesUppercaseRootIdBeforeCallingNavigationLoader(): void
    {
        // A literal fixture, not Uuid::randomHex(): a random hex value can consist only of digits, in which
        // case uppercasing it is a no-op and the test would pass even with the normalization removed. This
        // value is guaranteed to contain alphabetic hex nibbles.
        $rootId = '0123456789abcdef0123456789abcdef';
        $category = new CategoryEntity();
        $category->setId('category-alice');
        $category->setUniqueIdentifier('category-alice');

        $tree = new Tree(null, [new TreeItem($category, [])]);

        $context = Generator::generateSalesChannelContext();

        // Uuid::fromHexToBytes() calls @hex2bin(), which accepts uppercase hex, so an uppercase configured
        // rootId reached NavigationRoute and worked. Uuid::VALID_PATTERN is lowercase-only, so a guard on the
        // raw value would degrade an id that works.
        $navigationLoader = static::createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 1)
            ->willReturn($tree);

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => strtoupper($rootId)]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(1, $result->data);
    }

    #[TestDox('treats an uppercase rendering of the built-in service-navigation alias as unrecognized, not as the alias')]
    public function testLoadDoesNotResolveUppercaseAliasAsBuiltinAlias(): void
    {
        $context = Generator::generateSalesChannelContext();
        static::assertNull($context->getSalesChannel()->getServiceCategoryId());

        // NavigationAliasResolver::resolve() matches its alias constants case-sensitively (a `match` against
        // the lowercase literal 'service-navigation'), so 'SERVICE-NAVIGATION' falls through its default arm
        // unchanged instead of resolving to the sales channel's service category. The early return above the
        // normalization call also requires the raw configured value to equal the lowercase literal
        // 'service-navigation', so it does not fire either. The value is then normalized to lowercase, which
        // is not a valid uuid, so the loader degrades to notFound() without ever reaching the navigation
        // loader. Normalizing before alias resolution would instead turn this into the recognized alias and
        // take the early-return branch.
        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader->expects($this->never())->method('load');

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => 'SERVICE-NAVIGATION']),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns empty cached category collection when tree has no items')]
    public function testLoadReturnsEmptyCachedCollectionWhenTreeHasNoItems(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setServiceCategoryId(Uuid::randomHex());

        $this->navigationLoader->method('load')->willReturn(new Tree(null, []));

        $result = $this->dataLoader->load(
            new LoaderInputs(['rootId' => 'service-navigation']),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns an empty cached CategoryCollection when the service category is not configured, an unset rootId input included')]
    public function testLoadReturnsEmptyCollectionWhenServiceCategoryNotConfigured(): void
    {
        $context = Generator::generateSalesChannelContext();

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader->expects($this->never())->method('load');

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());
        $inputs = (new LoaderInputResolver())->resolve(
            $dataLoader->configSpecification(),
            new ServiceMenuLoaderConfig(),
            [],
        );

        $result = $dataLoader->load($inputs, self::requirement(), $context, new Request());

        static::assertTrue($result->hasData());
        static::assertInstanceOf(CategoryCollection::class, $result->data);
        static::assertCount(0, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @param non-empty-string $rootId
     */
    #[DataProvider('nonUuidRootIdProvider')]
    #[TestDox('returns notFound result when the resolved rootId is not a valid uuid because $_dataName')]
    public function testLoadReturnsNotFoundWhenResolvedRootIdIsNotValidUuid(string $rootId): void
    {
        $context = Generator::generateSalesChannelContext();
        static::assertNull($context->getSalesChannel()->getFooterCategoryId());

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader->expects($this->never())->method('load');

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => $rootId]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * Both rows sit outside the `service-navigation` early return, which is what makes them the guard's own
     * cases rather than that branch's.
     *
     * @return iterable<string, array{non-empty-string}>
     */
    public static function nonUuidRootIdProvider(): iterable
    {
        // NavigationAliasResolver hands the footer alias back unchanged when the sales channel has no footer
        // category (src/Core/Framework/ContentSystem/Adapter/FactoryHelper/NavigationAliasResolver.php:36).
        yield 'the optional footer alias resolves to itself' => ['footer-navigation'];

        // An unrecognized literal is returned unchanged by the resolver's default arm (:37).
        yield 'an unrecognized configured literal passes through' => ['not-a-navigation-alias'];
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the navigation loader throws the Shopware exception $_dataName')]
    public function testLoadReturnsNotFoundWhenNavigationLoaderThrows(\Throwable $exception): void
    {
        $serviceCategoryId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setServiceCategoryId($serviceCategoryId);

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());
        $result = $dataLoader->load(
            new LoaderInputs(['rootId' => 'service-navigation']),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lets a TypeError from the navigation loader propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideShopwareHttpExceptionPropagate(): void
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setServiceCategoryId(Uuid::randomHex());

        $typeError = new \TypeError('Argument #4 ($depth) must be of type int, null given');

        $navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $navigationLoader
            ->expects($this->once())
            ->method('load')
            ->willThrowException($typeError);

        $dataLoader = new ServiceMenuDataLoader($navigationLoader, new NavigationAliasResolver());

        try {
            $dataLoader->load(
                new LoaderInputs(['rootId' => 'service-navigation']),
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
     * Sample domain exceptions, not one row per catch arm: the loader catches the single covering ancestor
     * `ShopwareHttpException`, so no row maps to a clause of its own.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        // NavigationLoader delegates to AbstractNavigationRoute, whose TreeBuildingNavigationRoute decorator
        // reaches NavigationRoute, and that throws this at
        // src/Core/Content/Category/SalesChannel/NavigationRoute.php:166, :183 and :203. The factory is
        // CategoryException::categoryNotFound(), but the class it returns is CategoryNotFoundException, which
        // extends ShopwareHttpException directly and not CategoryException.
        yield 'the root category is missing from the navigation tree' => [
            CategoryException::categoryNotFound('category-missing'),
        ];

        // Not a reachability claim: this row pins the clause to the ancestor rather than to the chain's own
        // classes, using a class the navigation chain does not produce.
        yield 'a class outside the chain that extends ShopwareHttpException directly' => [
            new DecorationPatternException(NavigationLoaderInterface::class),
        ];
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('serviceMenu', 'service_menu', new ServiceMenuLoaderConfig());
    }
}
