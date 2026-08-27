<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfig;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
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

    #[TestDox('uses rootId as activeId when the active property resolves to an empty string')]
    public function testLoadUsesRootIdAsActiveIdWhenActivePropertyIsEmptyString(): void
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
