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

        $specification = $registry->all()['core:Sw:Navigation:Tree'] ?? null;
        static::assertInstanceOf(BindingSpecification::class, $specification);
        static::assertSame('Sw:Navigation:Tree', $specification->type());

        $binding = $specification->resolves()['navigationTree'] ?? null;
        static::assertInstanceOf(LoaderBinding::class, $binding);
        static::assertSame('navigation', $binding->loader);
        static::assertSame('main-navigation', $binding->config['rootId'] ?? null);
    }

    /**
     * The type ships the synthesized default and nothing else. A binding is an entry an editor picks
     * from a list, so one exists to serve a use case, not to expose a root the loader happens to
     * accept — the service and footer roots feed the footer's own column and link-row layouts, which
     * this component does not produce.
     */
    public function testTheTypeShipsNoBindingBeyondTheSynthesizedDefault(): void
    {
        $registry = static::getContainer()->get(ContentSystemBindingSpecificationRegistry::class);
        static::assertInstanceOf(AbstractContentSystemBindingSpecificationRegistry::class, $registry);

        $ownBindings = array_keys(array_filter(
            $registry->all(),
            static fn (BindingSpecification $specification): bool => $specification->type() === 'Sw:Navigation:Tree'
        ));

        static::assertSame(['core:Sw:Navigation:Tree'], $ownBindings);
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

        // Studio derives a control from the property type, so a primitive is always shown and this
        // field cannot be hidden declaratively yet. The help text is what keeps an editor from
        // "correcting" the placeholder it displays.
        static::assertNotNull($activeId['adminUI']['helpText'] ?? null);
    }

    /**
     * Depth belongs to the sales channel. Exposing it per element cannot be done honestly: Studio
     * substitutes a number control's minimum for an unset value, so the field would display a cap it
     * does not apply, and there is no way back to "inherit" once it is touched. The render limit
     * stays a component prop for template callers.
     */
    public function testDepthIsLeftToTheSalesChannel(): void
    {
        $types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $types);

        static::assertArrayNotHasKey('navigationMaxDepth', $types->get('Sw:Navigation:Tree')->properties());

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
     * The Studio ships no code for this control, so a dropped enum would render an empty select
     * while every render test here kept passing.
     */
    public function testDisplayTypeReachesTheSchemaWithItsOptions(): void
    {
        $types = static::getContainer()->get(ContentSystemElementTypeRegistry::class);
        static::assertInstanceOf(AbstractContentSystemElementTypeRegistry::class, $types);

        $displayType = $types->get('Sw:Navigation:Tree')->properties()['displayType']->toSchema();

        static::assertSame('static', $displayType['default']);
        static::assertSame(['static', 'collapse'], $displayType['enum']);
        static::assertSame('select', $displayType['adminUI']['component'] ?? null);
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

    public function testStaticModeRendersNoInteractiveMarkup(): void
    {
        $child = $this->treeItem('Shirts', '/shirts');
        $parent = $this->treeItem('Clothing', '/clothing', children: [$child]);

        $html = $this->render([
            'navigationTree' => [$parent],
            'expandAll' => true,
        ]);

        static::assertStringNotContainsString('<button', $html);
        static::assertStringNotContainsString('aria-expanded', $html);
        static::assertStringNotContainsString('data-bs-toggle', $html);
        static::assertStringNotContainsString('is--collapse', $html);
    }

    public function testCollapseModeWiresTheToggleToThePanelItControls(): void
    {
        $child = $this->treeItem('Shirts', '/shirts');
        $parent = $this->treeItem('Clothing', '/clothing', children: [$child]);

        $html = $this->render([
            'navigationTree' => [$parent],
            'displayType' => 'collapse',
        ]);

        static::assertStringContainsString('data-bs-toggle="collapse"', $html);
        static::assertSame(1, preg_match('/aria-controls="([^"]+)"/', $html, $matches));
        static::assertStringContainsString('data-bs-target="#' . $matches[1] . '"', $html);
        static::assertStringContainsString('id="' . $matches[1] . '"', $html);
        static::assertStringContainsString('href="/clothing"', $html);
    }

    /**
     * Sw:Icon renders nothing at all when the icon file is missing, so the button would ship empty
     * with every other assertion here still passing.
     */
    public function testCollapseModeRendersThePlusAndMinusIcons(): void
    {
        $child = $this->treeItem('Shirts', '/shirts');
        $parent = $this->treeItem('Clothing', '/clothing', children: [$child]);

        $html = $this->render([
            'navigationTree' => [$parent],
            'displayType' => 'collapse',
        ]);

        static::assertStringContainsString('sw-icon--default-plus', $html);
        static::assertStringContainsString('sw-icon--default-minus', $html);
        static::assertStringNotContainsString('arrow-head', $html);
        static::assertSame(2, substr_count($html, '<svg'));
    }

    public function testCollapseModeGivesTheToggleANameOfItsOwn(): void
    {
        $child = $this->treeItem('Shirts', '/shirts');
        $parent = $this->treeItem('Clothing', '/clothing', children: [$child]);

        $html = $this->render([
            'navigationTree' => [$parent],
            'displayType' => 'collapse',
        ]);

        // Naming it after the category alone would give the row two controls with one name.
        static::assertMatchesRegularExpression('/aria-label="[^"]*Clothing[^"]*"/', $html);
        static::assertStringNotContainsString('aria-label="Clothing"', $html);
    }

    public function testCollapseModeStartsTheActiveBranchOpenAndLeavesSiblingsClosed(): void
    {
        $activeChild = $this->treeItem('ActiveChild', '/active-child');
        $activeBranch = $this->treeItem('ActiveBranch', '/active-branch', children: [$activeChild]);

        $siblingChild = $this->treeItem('SiblingChild', '/sibling-child');
        $siblingBranch = $this->treeItem('SiblingBranch', '/sibling-branch', children: [$siblingChild]);

        $html = $this->render([
            'navigationTree' => [$activeBranch, $siblingBranch],
            'displayType' => 'collapse',
            'activePath' => [$activeBranch->getCategory()->getId()],
        ]);

        // Both branches are in the DOM — that is what makes the closed one expandable at all.
        static::assertStringContainsString('ActiveChild', $html);
        static::assertStringContainsString('SiblingChild', $html);

        static::assertSame(1, substr_count($html, 'aria-expanded="true"'));
        static::assertSame(1, substr_count($html, 'aria-expanded="false"'));
        static::assertSame(1, substr_count($html, 'collapse show'));
    }

    public function testCollapseModeUsesExpandAllAsTheInitialState(): void
    {
        $child = $this->treeItem('HiddenChild', '/hidden-child');
        $branch = $this->treeItem('Branch', '/branch', children: [$child]);

        $closed = $this->render(['navigationTree' => [$branch], 'displayType' => 'collapse']);
        $open = $this->render(['navigationTree' => [$branch], 'displayType' => 'collapse', 'expandAll' => true]);

        static::assertStringContainsString('HiddenChild', $closed);
        static::assertStringContainsString('HiddenChild', $open);

        static::assertStringContainsString('aria-expanded="false"', $closed);
        static::assertStringContainsString('aria-expanded="true"', $open);
    }

    public function testCollapseModeRendersNoToggleWhenTheDepthLimitDropsTheChildren(): void
    {
        $level3 = $this->treeItem('Level3', '/level-3');
        $level2 = $this->treeItem('Level2', '/level-2', children: [$level3]);
        $level1 = $this->treeItem('Level1', '/level-1', children: [$level2]);

        $html = $this->render([
            'navigationTree' => [$level1],
            'navigationMaxDepth' => 2,
            'displayType' => 'collapse',
        ]);

        static::assertStringContainsString('Level2', $html);
        static::assertStringNotContainsString('Level3', $html);
        static::assertSame(1, substr_count($html, 'data-bs-toggle="collapse"'));
    }

    public function testCollapseModeKeepsTheActivePathBeyondTheRenderDepth(): void
    {
        $active = $this->treeItem('Sneakers', '/sneakers');
        $middle = $this->treeItem('Shoes', '/shoes', children: [$active]);
        $top = $this->treeItem('Clothing', '/clothing', children: [$middle]);

        $html = $this->render([
            'navigationTree' => [$top],
            'navigationMaxDepth' => 2,
            'displayType' => 'collapse',
            'activeId' => $active->getCategory()->getId(),
            'activePath' => [$top->getCategory()->getId(), $middle->getCategory()->getId()],
        ]);

        static::assertStringContainsString('Sneakers', $html);
        static::assertSame(1, substr_count($html, 'aria-current="page"'));
        static::assertSame(2, substr_count($html, 'aria-expanded="true"'));
        static::assertSame(0, substr_count($html, 'aria-expanded="false"'));
    }

    /**
     * Assistive technology ignores aria-expanded on a span, so the toggle has to be its own
     * control rather than the folder itself.
     */
    public function testCollapseModeKeepsAFolderASpanAndPutsTheToggleBesideIt(): void
    {
        $child = $this->treeItem('Shirts', '/shirts');
        $folder = $this->treeItem('Structure', null, CategoryDefinition::TYPE_FOLDER, children: [$child]);

        $html = $this->render([
            'navigationTree' => [$folder],
            'displayType' => 'collapse',
        ]);

        static::assertStringContainsString('sw-navigation-tree-items__folder nav-link', $html);
        static::assertStringContainsString('sw-navigation-tree-items__toggle', $html);
        static::assertStringContainsString('aria-expanded="false"', $html);
        static::assertStringNotContainsString('<span class="sw-navigation-tree-items__folder nav-link" aria-expanded', $html);
    }

    public function testCollapseModeKeepsAChildlessFolderNonInteractive(): void
    {
        $folder = $this->treeItem('Structure', null, CategoryDefinition::TYPE_FOLDER);

        $html = $this->render([
            'navigationTree' => [$folder],
            'displayType' => 'collapse',
        ]);

        static::assertStringContainsString('sw-navigation-tree-items__folder nav-link', $html);
        static::assertStringNotContainsString('<button', $html);
        static::assertStringNotContainsString('aria-expanded', $html);
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
     * (`shopware.navigation`, `context.salesChannel`) are never evaluated. The translator is the
     * exception. Collapse mode names its toggle from `general.toggleSubcategories`, so a missing
     * snippet makes those tests fail on the name rather than on what they assert.
     *
     * @param array<string, mixed> $props
     */
    private function render(array $props): string
    {
        $twig = static::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $props = \array_merge([
            'navigationMaxDepth' => 3,
            'displayType' => 'static',
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
