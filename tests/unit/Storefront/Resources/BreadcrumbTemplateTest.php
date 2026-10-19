<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Resources;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Framework\Twig\Extension\IconCacheTwigFilter;
use Shopware\Storefront\Framework\Twig\IconExtension;
use Shopware\Storefront\Storefront;
use Symfony\Component\DomCrawler\Crawler;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * Renders `@Storefront/storefront/layout/breadcrumb.html.twig` through an extending template that overrides
 * `layout_breadcrumb_list_item`, to pin the variables an extension can rely on in each breadcrumb mode.
 *
 * The Twig environment is built per test and does not use the shared kernel.
 *
 * @internal
 */
#[Package('framework')]
#[CoversNothing]
class BreadcrumbTemplateTest extends TestCase
{
    private const TEMPLATE = '@BreadcrumbTest/storefront/layout/breadcrumb.html.twig';

    private const CANONICAL_ITEM_VARIABLES = [
        'breadcrumb',
        'breadcrumbCollection',
        'activeId',
        'categoryId',
        'type',
        'name',
        'categoryUrl',
        'shouldOpenInNewTab',
        'isActive',
    ];

    private const LEGACY_ITEM_VARIABLES = [
        'breadcrumbCategory',
        'breadcrumbCategories',
        'breadcrumbKeys',
        'key',
    ];

    private Environment $twig;

    protected function setUp(): void
    {
        $this->twig = $this->createTwig();
    }

    public function testMajorPathExposesOnlyTheCanonicalVariables(): void
    {
        $items = $this->renderItems(['breadcrumb' => $this->createBreadcrumbs()]);

        static::assertCount(2, $items);
        foreach ($items as $item) {
            foreach (self::CANONICAL_ITEM_VARIABLES as $variable) {
                static::assertContains($variable, $item['variables'], \sprintf('Variable "%s" is missing.', $variable));
            }

            foreach (self::LEGACY_ITEM_VARIABLES as $variable) {
                static::assertNotContains($variable, $item['variables'], \sprintf('Legacy variable "%s" is still set.', $variable));
            }
        }

        static::assertSame(
            [
                ['name' => 'Home', 'categoryId' => 'home-id', 'activeId' => 'shoes-id', 'isActive' => 'false', 'url' => '/navigation/home-id'],
                ['name' => 'Shoes', 'categoryId' => 'shoes-id', 'activeId' => 'shoes-id', 'isActive' => 'true', 'url' => '/navigation/shoes-id'],
            ],
            array_map(static fn (array $item): array => array_diff_key($item, ['variables' => true]), $items),
        );
    }

    public function testMajorPathRendersTheCssSeparatorInsteadOfThePlaceholderBlock(): void
    {
        $html = $this->twig->render(self::TEMPLATE, ['breadcrumb' => $this->createBreadcrumbs()]);

        static::assertStringContainsString('--bs-breadcrumb-divider:', $html);
        static::assertStringNotContainsString('breadcrumb-placeholder', $html);
    }

    public function testMajorPathIgnoresTheBreadcrumbCollectionInput(): void
    {
        $html = $this->twig->render(self::TEMPLATE, ['breadcrumbCollection' => $this->createBreadcrumbs()]);

        static::assertSame('', trim($html));
    }

    public function testMajorPathRendersNothingWithoutBreadcrumbs(): void
    {
        $html = $this->twig->render(self::TEMPLATE, ['breadcrumb' => new BreadcrumbCollection()]);

        static::assertSame('', trim($html));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the BREADCRUMB_REWORK flag
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testReworkPathKeepsTheBreadcrumbCategoryAlias(): void
    {
        $items = $this->renderItems(['breadcrumb' => $this->createBreadcrumbs()]);

        static::assertCount(2, $items);
        foreach ($items as $item) {
            foreach (self::CANONICAL_ITEM_VARIABLES as $variable) {
                static::assertContains($variable, $item['variables'], \sprintf('Variable "%s" is missing.', $variable));
            }

            static::assertContains('breadcrumbCategory', $item['variables']);
            static::assertNotContains('breadcrumbCategories', $item['variables']);
            static::assertNotContains('breadcrumbKeys', $item['variables']);
            static::assertNotContains('key', $item['variables']);
        }

        static::assertSame('shoes-id', $items[1]['categoryId']);
        static::assertSame('true', $items[1]['isActive']);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the BREADCRUMB_REWORK flag
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testReworkPathStillAcceptsTheBreadcrumbCollectionInput(): void
    {
        $items = $this->renderItems(['breadcrumbCollection' => $this->createBreadcrumbs()]);

        static::assertSame(['Home', 'Shoes'], array_column($items, 'name'));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the BREADCRUMB_REWORK flag
     */
    #[DisabledFeatures(['v6.8.0.0', 'BREADCRUMB_REWORK'])]
    public function testLegacyPathKeepsTheLegacyVariablesAndAddsActiveId(): void
    {
        $items = $this->renderItems(['categoryId' => 'shoes-id']);

        static::assertCount(2, $items);
        foreach ($items as $item) {
            foreach (['breadcrumbCategory', 'breadcrumbCategories', 'breadcrumbKeys', 'key', 'activeId', 'breadcrumb'] as $variable) {
                static::assertContains($variable, $item['variables'], \sprintf('Variable "%s" is missing.', $variable));
            }

            // On the legacy path `categoryId` still holds the active category, not the current item.
            static::assertSame('shoes-id', $item['categoryId']);
            static::assertSame('shoes-id', $item['activeId']);
        }

        static::assertSame(['Home', 'Shoes'], array_column($items, 'name'));
        static::assertSame(['false', 'true'], array_column($items, 'isActive'));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the BREADCRUMB_REWORK flag
     */
    #[DisabledFeatures(['v6.8.0.0', 'BREADCRUMB_REWORK'])]
    public function testLegacyPathRendersThePlaceholderBetweenItems(): void
    {
        $html = $this->twig->render(self::TEMPLATE, ['categoryId' => 'shoes-id']);

        static::assertSame(1, substr_count($html, 'breadcrumb-placeholder'));
        static::assertStringNotContainsString('--bs-breadcrumb-divider:', $html);
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return list<array{variables: list<string>, name: string, categoryId: string, activeId: string, isActive: string, url: string}>
     */
    private function renderItems(array $context): array
    {
        $crawler = new Crawler($this->twig->render(self::TEMPLATE, $context));

        return array_values($crawler->filterXPath('//li[@class="test-breadcrumb-item"]')->each(
            static fn (Crawler $item): array => [
                'variables' => explode(',', (string) $item->attr('data-variables')),
                'name' => (string) $item->attr('data-name'),
                'categoryId' => (string) $item->attr('data-category-id'),
                'activeId' => (string) $item->attr('data-active-id'),
                'isActive' => (string) $item->attr('data-is-active'),
                'url' => (string) $item->attr('data-url'),
            ]
        ));
    }

    private function createBreadcrumbs(): BreadcrumbCollection
    {
        return new BreadcrumbCollection([
            new Breadcrumb(name: 'Home', categoryId: 'home-id', type: 'page'),
            new Breadcrumb(name: 'Shoes', categoryId: 'shoes-id', type: 'page'),
        ]);
    }

    /**
     * @return array<string, CategoryEntity>
     */
    private static function createLegacyCategories(): array
    {
        $categories = [];
        foreach (['home-id' => 'Home', 'shoes-id' => 'Shoes'] as $id => $name) {
            $category = new CategoryEntity();
            $category->setId($id);
            $category->setType('page');
            $category->setTranslated(['name' => $name]);
            $categories[$id] = $category;
        }

        return $categories;
    }

    private function createTwig(): Environment
    {
        $storefrontPath = (new Storefront())->getPath();

        $loader = new FilesystemLoader();
        $loader->addPath($storefrontPath . '/Resources/views');
        $loader->addPath($storefrontPath . '/Resources/views', 'Storefront');
        $loader->addPath($storefrontPath . '/Resources/app/storefront/dist', 'Storefront');
        $loader->addPath(__DIR__ . '/_fixtures/BreadcrumbTemplate/Resources/views', 'BreadcrumbTest');

        $twig = new Environment($loader, ['cache' => false]);

        $hierarchyBuilder = static::createStub(BundleHierarchyBuilder::class);
        $hierarchyBuilder->method('buildNamespaceHierarchy')->willReturn(['Storefront' => 0]);

        $scopeDetector = static::createStub(TemplateScopeDetector::class);
        $scopeDetector->method('getScopes')->willReturn([TemplateScopeDetector::DEFAULT_SCOPE]);

        $templateFinder = new TemplateFinder(
            $twig,
            $loader,
            sys_get_temp_dir() . '/' . uniqid('breadcrumb_template_test_', true),
            new NamespaceHierarchyBuilder([$hierarchyBuilder]),
            $scopeDetector,
        );

        $twig->addExtension(new NodeExtension($templateFinder, $scopeDetector));
        $twig->addExtension(new IconExtension());
        $twig->addExtension(new IconCacheTwigFilter());
        $twig->addExtension(new BreadcrumbTemplateTestExtension(self::createLegacyCategories()));

        return $twig;
    }
}

/**
 * Replaces the Twig functions the breadcrumb template needs from the full application.
 *
 * @internal
 */
class BreadcrumbTemplateTestExtension extends AbstractExtension
{
    /**
     * @param array<string, CategoryEntity> $legacyCategories
     */
    public function __construct(private readonly array $legacyCategories)
    {
    }

    public function getFunctions(): array
    {
        return [
            // The unit test runner drives flags through the environment and clears the feature registry,
            // so the core `feature()` function would report every flag as unknown and inactive.
            new TwigFunction('feature', static fn (string $flag): bool => Feature::isActive($flag)),
            new TwigFunction('seoUrl', static fn (string $name, array $parameters = []): string => '/navigation/' . $parameters['navigationId']),
            new TwigFunction('sw_breadcrumb_full', fn (): array => $this->legacyCategories),
            new TwigFunction('sw_breadcrumb_full_by_id', fn (): array => $this->legacyCategories),
            new TwigFunction(
                'test_context_variables',
                static fn (array $context): string => implode(',', array_keys($context)),
                ['needs_context' => true],
            ),
        ];
    }
}
