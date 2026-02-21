<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Hydration\DataLoader\NavigationLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\NavigationAliasResolver;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\NavigationLoader\NavigationDataLoader;
use Shopware\Core\Content\ContentSystem\Hydration\DataLoader\NavigationLoader\NavigationLoaderConfig;
use Shopware\Core\Content\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Content\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NavigationDataLoader::class)]
class NavigationDataLoaderTest extends TestCase
{
    private NavigationLoaderInterface&MockObject $navigationLoader;

    private NavigationAliasResolver $aliasResolver;

    private NavigationDataLoader $dataLoader;

    protected function setUp(): void
    {
        $this->navigationLoader = $this->createMock(NavigationLoaderInterface::class);
        $this->aliasResolver = new NavigationAliasResolver();
        $this->dataLoader = new NavigationDataLoader($this->navigationLoader, $this->aliasResolver);
    }

    #[TestDox('returns navigation source type identifier')]
    public function testGetRequirementTypeReturnsNavigationString(): void
    {
        static::assertSame('navigation', NavigationDataLoader::getRequirementType());
    }

    #[TestDox('loads navigation tree with explicit rootId from config')]
    public function testLoadWithExplicitRootIdCallsNavigationLoader(): void
    {
        $rootId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['activeId' => $activeId]);
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateSalesChannelContext();

        $this->navigationLoader
            ->method('load')
            ->with($activeId, $context, $rootId, 2)
            ->willReturn($tree);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('resolves main-navigation alias to sales channel navigation category ID')]
    public function testLoadResolvesMainNavigationAliasToNavigationCategoryId(): void
    {
        $navCategoryId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['activeId' => $activeId]);
        $config = new NavigationLoaderConfig(rootId: 'main-navigation', depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId($navCategoryId);

        $this->navigationLoader
            ->method('load')
            ->with($activeId, $context, $navCategoryId, 2)
            ->willReturn($tree);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('reads active ID from custom activeProperty name')]
    public function testLoadReadsActiveIdFromCustomActiveProperty(): void
    {
        $rootId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['categoryId' => $activeId]);
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'categoryId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateSalesChannelContext();

        $this->navigationLoader
            ->method('load')
            ->with($activeId, $context, $rootId, 2)
            ->willReturn($tree);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('returns cachedExternally result with empty cache tags')]
    public function testLoadReturnsCachedExternallyResult(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new NavigationLoaderConfig(rootId: $rootId);
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateSalesChannelContext();

        $this->navigationLoader->method('load')->willReturn($tree);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('passes configured depth to navigation loader')]
    public function testLoadPassesConfiguredDepthToNavigationLoader(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 5);
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateSalesChannelContext();

        $this->navigationLoader
            ->expects($this->once())
            ->method('load')
            ->with($rootId, $context, $rootId, 5)
            ->willReturn($tree);

        $this->dataLoader->load($element, $requirement, $context, new Request());
    }

    #[TestDox('defaults to main-navigation alias when rootId is null in config')]
    public function testLoadDefaultsToMainNavigationWhenRootIdIsNull(): void
    {
        $navCategoryId = Uuid::randomHex();
        $activeId = Uuid::randomHex();
        $tree = new Tree(null, []);

        $element = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['activeId' => $activeId]);
        // rootId is null — defaults to 'main-navigation'
        $config = new NavigationLoaderConfig(rootId: null, depth: 3, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setNavigationCategoryId($navCategoryId);

        $this->navigationLoader
            ->method('load')
            ->with($activeId, $context, $navCategoryId, 3)
            ->willReturn($tree);

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertTrue($result->hasData());
        static::assertSame($tree, $result->data);
    }

    #[TestDox('uses rootId as activeId when element active property is missing')]
    public function testLoadUsesRootIdAsActiveIdWhenActivePropertyIsMissing(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateSalesChannelContext();

        $elementMissing = new ContentElement(id: Uuid::randomHex(), component: 'test');

        $this->navigationLoader
            ->method('load')
            ->with($rootId, $context, $rootId, 2)
            ->willReturn($tree);

        $resultMissing = $this->dataLoader->load($elementMissing, $requirement, $context, new Request());

        static::assertTrue($resultMissing->hasData());
        static::assertSame($tree, $resultMissing->data);
    }

    #[TestDox('uses rootId as activeId when element active property is an empty string')]
    public function testLoadUsesRootIdAsActiveIdWhenActivePropertyIsEmptyString(): void
    {
        $rootId = Uuid::randomHex();
        $tree = new Tree(null, []);
        $config = new NavigationLoaderConfig(rootId: $rootId, depth: 2, activeProperty: 'activeId');
        $requirement = new DataRequirement('navKey', 'navigation', $config);
        $context = Generator::generateSalesChannelContext();

        $elementEmpty = new ContentElement(id: Uuid::randomHex(), component: 'test', properties: ['activeId' => '']);

        $this->navigationLoader
            ->method('load')
            ->with($rootId, $context, $rootId, 2)
            ->willReturn($tree);

        $resultEmpty = $this->dataLoader->load($elementEmpty, $requirement, $context, new Request());

        static::assertTrue($resultEmpty->hasData());
        static::assertSame($tree, $resultEmpty->data);
    }

    #[TestDox('returns notFound result when config is not a NavigationLoaderConfig instance')]
    public function testLoadReturnNotFoundWhenConfigIsNotNavigationLoaderConfig(): void
    {
        $element = new ContentElement(id: Uuid::randomHex(), component: 'test');
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('navKey', 'navigation', $wrongConfig);
        $context = Generator::generateSalesChannelContext();

        $this->navigationLoader->expects($this->never())->method('load');

        $result = $this->dataLoader->load($element, $requirement, $context, new Request());

        static::assertFalse($result->hasData());
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('throws DecorationPatternException when getDecorated is called')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->expectExceptionMessage('The getDecorated() function of core class');

        $this->dataLoader->getDecorated();
    }
}
