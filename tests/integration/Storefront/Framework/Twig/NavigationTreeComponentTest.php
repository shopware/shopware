<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Category\Tree\TreeItem;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Twig\Environment;

/**
 * @internal
 */
#[Package('discovery')]
class NavigationTreeComponentTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The canonicity test only validates bindings that exist, so dropping `resolvedBy` would pass it
     * while leaving the element unwired.
     */
    public function testElementTypeBindsTheNavigationLoader(): void
    {
        $registry = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

        $expectedRoots = [
            'core:Sw:Navigation:Tree' => 'main-navigation',
            'core:service-navigation' => 'service-navigation',
            'core:footer-navigation' => 'footer-navigation',
        ];

        foreach ($expectedRoots as $qualifiedId => $expectedRoot) {
            $specification = $registry->all()[$qualifiedId] ?? null;
            static::assertInstanceOf(BindingSpecification::class, $specification, $qualifiedId);
            static::assertSame('Sw:Navigation:Tree', $specification->type(), $qualifiedId);

            $binding = $specification->resolves()['navigationTree'] ?? null;
            static::assertInstanceOf(LoaderBinding::class, $binding, $qualifiedId);
            static::assertSame('navigation', $binding->loader, $qualifiedId);
            static::assertSame($expectedRoot, $binding->config['rootId'] ?? null, $qualifiedId);
        }
    }

    /**
     * A page carrying two of these trees would announce both landmarks as "Categories", which is
     * what the label exists to prevent. The two non-default bindings therefore seed their own.
     */
    public function testTheNonDefaultBindingsSeedTheirOwnLabel(): void
    {
        $registry = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

        $expectedLabels = [
            'core:service-navigation' => 'Service navigation',
            'core:footer-navigation' => 'Footer navigation',
        ];

        foreach ($expectedLabels as $qualifiedId => $expectedLabel) {
            $specification = $registry->all()[$qualifiedId] ?? null;
            static::assertInstanceOf(BindingSpecification::class, $specification, $qualifiedId);
            static::assertSame($expectedLabel, $specification->inputs()['ariaLabel']->default ?? null, $qualifiedId);
        }
    }

    /**
     * One layout serves every category page, so a stored id would be right on one and wrong on all
     * the others.
     */
    public function testActiveCategoryFollowsThePageInsteadOfBeingConfigured(): void
    {
        $types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $types);

        $activeId = $types->get('Sw:Navigation:Tree')->properties()['activeId']->toSchema();

        static::assertSame('{{categoryId}}', $activeId['default']);
        static::assertNull($activeId['adminUI'], 'The editor must not be asked for a per-request value.');
    }

    /**
     * Pinning a depth or an enum reintroduces a ceiling the shop's own configured depth cannot pass.
     */
    public function testDepthIsLeftToTheSalesChannel(): void
    {
        $types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $types);

        $renderDepth = $types->get('Sw:Navigation:Tree')->properties()['navigationMaxDepth']->toSchema();
        static::assertNull($renderDepth['default'], 'A stored default would override the sales channel.');
        static::assertNull($renderDepth['enum'], 'An enum would cap the shop below its own configured depth.');

        $bindings = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $bindings);

        foreach ($bindings->all() as $qualifiedId => $specification) {
            if ($specification->type() !== 'Sw:Navigation:Tree') {
                continue;
            }

            static::assertArrayNotHasKey('depth', $specification->resolves()['navigationTree']->config, $qualifiedId);
        }
    }

    /**
     * A landmark with an empty name is worse than an unnamed one: screen readers announce it without
     * any hint of what it contains. A stored empty value must therefore not beat the snippet default.
     */
    public function testFallsBackToTheSnippetWhenTheLabelIsEmpty(): void
    {
        $html = $this->render([
            'navigationTree' => [$this->treeItem('Clothing', '/clothing')],
            'ariaLabel' => '',
        ]);

        static::assertStringNotContainsString('aria-label=""', $html);
    }

    public function testRendersNestedListsWithSingleNavLandmark(): void
    {
        $child = $this->treeItem('Shirts', '/shirts');
        $parent = $this->treeItem('Clothing', '/clothing', children: [$child]);

        $html = $this->render([
            'navigationTree' => [$parent],
            'expandAll' => true,
        ]);

        static::assertStringContainsString('<nav', $html);
        static::assertSame(1, substr_count($html, '<nav'));
        static::assertSame(1, substr_count($html, 'is--root'));
        static::assertSame(2, substr_count($html, '<ul'));
        static::assertStringContainsString('href="/clothing"', $html);
        static::assertStringContainsString('href="/shirts"', $html);

        // Indentation is driven entirely by this property, so a typo in it would go unnoticed.
        static::assertStringContainsString('--sw-navigation-tree-items-level: 1;', $html);
        static::assertStringContainsString('--sw-navigation-tree-items-level: 2;', $html);
    }

    public function testMarksActiveItemOnlyOnce(): void
    {
        $active = $this->treeItem('Shirts', '/shirts');
        $other = $this->treeItem('Shoes', '/shoes');

        $html = $this->render([
            'navigationTree' => [$active, $other],
            'activeId' => $active->getCategory()->getId(),
        ]);

        static::assertSame(1, substr_count($html, 'aria-current="page"'));
        static::assertSame(1, substr_count($html, ' active'));
        static::assertStringContainsString('href="/shirts"', $html);
    }

    public function testRendersFolderAsNonInteractiveText(): void
    {
        $folder = $this->treeItem('Structure', null, CategoryDefinition::TYPE_FOLDER);

        $html = $this->render(['navigationTree' => [$folder]]);

        static::assertStringContainsString('sw-navigation-tree-items__folder', $html);
        static::assertStringContainsString('Structure', $html);
        static::assertStringNotContainsString('<a', $html);
    }

    public function testRendersMisconfiguredLinkAsNonInteractiveText(): void
    {
        $brokenLink = $this->treeItem('Broken', null, CategoryDefinition::TYPE_LINK);

        $html = $this->render(['navigationTree' => [$brokenLink]]);

        static::assertStringContainsString('sw-navigation-tree-items__folder', $html);
        static::assertStringNotContainsString('<a', $html);
        static::assertStringNotContainsString('href=""', $html);
    }

    public function testStopsRecursionAtMaxDepth(): void
    {
        $level3 = $this->treeItem('Level3', '/level-3');
        $level2 = $this->treeItem('Level2', '/level-2', children: [$level3]);
        $level1 = $this->treeItem('Level1', '/level-1', children: [$level2]);

        $html = $this->render([
            'navigationTree' => [$level1],
            'navigationMaxDepth' => 2,
            'expandAll' => true,
        ]);

        static::assertStringContainsString('Level1', $html);
        static::assertStringContainsString('Level2', $html);
        static::assertStringNotContainsString('Level3', $html);
    }

    /**
     * The route loads the active path beyond the requested depth on purpose, and the predecessor
     * recursed it without a limit. Clipping it would drop the only item carrying aria-current.
     */
    public function testTheActivePathIsNotClippedByTheRenderDepth(): void
    {
        $active = $this->treeItem('Sneakers', '/sneakers');
        $middle = $this->treeItem('Shoes', '/shoes', children: [$active]);
        $top = $this->treeItem('Clothing', '/clothing', children: [$middle]);

        $html = $this->render([
            'navigationTree' => [$top],
            'navigationMaxDepth' => 2,
            'activeId' => $active->getCategory()->getId(),
            'activePath' => [$top->getCategory()->getId(), $middle->getCategory()->getId()],
        ]);

        static::assertStringContainsString('Sneakers', $html);
        static::assertSame(1, substr_count($html, 'aria-current="page"'));
    }

    public function testRendersChildrenOnlyForBranchOnActivePath(): void
    {
        $activeChild = $this->treeItem('ActiveChild', '/active-child');
        $activeBranch = $this->treeItem('ActiveBranch', '/active-branch', children: [$activeChild]);

        $collapsedChild = $this->treeItem('CollapsedChild', '/collapsed-child');
        $collapsedBranch = $this->treeItem('CollapsedBranch', '/collapsed-branch', children: [$collapsedChild]);

        $html = $this->render([
            'navigationTree' => [$activeBranch, $collapsedBranch],
            'activePath' => [$activeBranch->getCategory()->getId()],
        ]);

        static::assertStringContainsString('ActiveChild', $html);
        static::assertStringNotContainsString('CollapsedChild', $html);
        static::assertStringContainsString('is--in-path', $html);
    }

    public function testExpandAllRendersCollapsedBranches(): void
    {
        $child = $this->treeItem('HiddenChild', '/hidden-child');
        $branch = $this->treeItem('Branch', '/branch', children: [$child]);

        $collapsed = $this->render(['navigationTree' => [$branch]]);
        $expanded = $this->render(['navigationTree' => [$branch], 'expandAll' => true]);

        static::assertStringNotContainsString('HiddenChild', $collapsed);
        static::assertStringContainsString('HiddenChild', $expanded);
    }

    public function testAcceptsTreeStructAsWellAsItemList(): void
    {
        $item = $this->treeItem('Clothing', '/clothing');

        $fromList = $this->render(['navigationTree' => [$item]]);
        $fromTree = $this->render(['navigationTree' => new Tree(null, [$item])]);

        static::assertStringContainsString('href="/clothing"', $fromList);
        static::assertSame($fromList, $fromTree);
    }

    public function testRendersNothingForEmptyTree(): void
    {
        $html = $this->render(['navigationTree' => []]);

        static::assertStringNotContainsString('<nav', $html);
        static::assertStringNotContainsString('<ul', $html);
    }

    /**
     * Degrading to empty output instead of failing the render matches Sw:Media:Image and
     * Sw:Product:Listing.
     */
    public function testRendersNothingWhenNavigationTreeIsMissing(): void
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $html = $twig
            ->createTemplate('{{ component(\'Sw:Navigation:Tree\', { ariaLabel: \'Categories\', activeId: \'\', activePath: [], navigationMaxDepth: 3 }) }}')
            ->render();

        static::assertStringNotContainsString('<nav', $html);
        static::assertStringNotContainsString('<ul', $html);
    }

    public function testOpensLinkCategoryInNewTab(): void
    {
        $item = $this->treeItem(
            'External',
            'https://example.com',
            CategoryDefinition::TYPE_LINK,
            ['linkNewTab' => true]
        );

        $html = $this->render(['navigationTree' => [$item]]);

        static::assertStringContainsString('target="_blank"', $html);
        static::assertStringContainsString('href="https://example.com"', $html);
    }

    /**
     * Every prop is passed explicitly so the component's global-reading defaults
     * (`shopware.navigation`, `context.salesChannel`, the translator) are never evaluated.
     *
     * @param array<string, mixed> $props
     */
    private function render(array $props): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $props = \array_merge([
            'navigationMaxDepth' => 3,
            'expandAll' => false,
            'activeId' => Uuid::randomHex(),
            'activePath' => [],
            'ariaLabel' => 'Categories',
        ], $props);

        return $twig
            ->createTemplate('{{ component(\'Sw:Navigation:Tree\', props) }}')
            ->render(['props' => $props]);
    }

    /**
     * @param TreeItem[] $children
     * @param array<string, mixed> $translated
     */
    private function treeItem(
        string $name,
        ?string $seoUrl,
        string $type = CategoryDefinition::TYPE_PAGE,
        array $translated = [],
        array $children = []
    ): TreeItem {
        $category = new SalesChannelCategoryEntity();
        $category->setId(Uuid::randomHex());
        $category->setType($type);
        $category->setTranslated(\array_merge(['name' => $name], $translated));

        if ($seoUrl !== null) {
            $category->setSeoUrl($seoUrl);
        }

        return new TreeItem($category, $children);
    }
}
