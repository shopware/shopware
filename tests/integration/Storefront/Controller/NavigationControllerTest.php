<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedHook;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoadedHook;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;

/**
 * @internal
 */
class NavigationControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();
    }

    public function testNavigationPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(NavigationPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testNavigationPageLoadedHookScriptsAreExecutedForCategory(): void
    {
        $response = $this->request('GET', '/my-navigation/', []);

        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(NavigationPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testMenuOffcanvasPageletLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/widgets/menu/offcanvas', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(MenuOffcanvasPageletLoadedHook::HOOK_NAME, $traces);
    }

    public function testStorefrontRedirectsOutOfRangePaginationTo301(): void
    {
        // 5 products, default limit 24 → lastPage = 1; p=99 must redirect.
        $seoPath = $this->createCategoryWithProducts(5);

        $response = $this->request('GET', $seoPath . '?p=99', []);

        static::assertSame(301, $response->getStatusCode());

        $location = $response->headers->get('Location');
        static::assertNotNull($location);
        static::assertStringNotContainsString('p=99', $location);
        static::assertStringNotContainsString('p=', $location);
    }

    public function testStorefrontPreservesOtherQueryParamsOnRedirect(): void
    {
        $seoPath = $this->createCategoryWithProducts(5);

        $response = $this->request('GET', $seoPath . '?p=99&order=price-asc', []);

        static::assertSame(301, $response->getStatusCode());

        $location = $response->headers->get('Location');
        static::assertNotNull($location);
        static::assertStringContainsString('order=price-asc', $location);
        static::assertStringNotContainsString('p=99', $location);
        static::assertStringNotContainsString('p=', $location);
    }

    public function testStorefrontInRangePaginationStillReturns200(): void
    {
        // 6 products, limit=2 → lastPage = 3; p=2 must succeed.
        $seoPath = $this->createCategoryWithProducts(6);

        $response = $this->request('GET', $seoPath . '?p=2&limit=2', []);

        static::assertSame(200, $response->getStatusCode());
    }

    private function createData(): void
    {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = static::getContainer()->get('sales_channel.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->first();

        $category = [
            'id' => $this->ids->create('category'),
            'name' => 'my-navigation',
            'type' => 'landing_page',
            'parentId' => $salesChannel->getNavigationCategoryId(),
        ];

        static::getContainer()->get('category.repository')->create([$category], Context::createDefaultContext());
    }

    /**
     * Creates a category with a product-listing CMS page and $count simple products.
     * Returns the SEO path for the category (e.g. `listing-test-category/`).
     *
     * The SEO URL is queried from the database after creation so the path is always accurate.
     */
    private function createCategoryWithProducts(int $count): string
    {
        $salesChannelId = $this->getSalesChannelId();

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = static::getContainer()->get('sales_channel.repository')->search(
            new Criteria([$salesChannelId]),
            Context::createDefaultContext()
        )->first();

        $categoryId = $this->ids->create('out-of-range-category');

        $products = [];
        for ($i = 0; $i < $count; ++$i) {
            $products[] = [
                'id' => $this->ids->create('out-of-range-product-' . $i),
                'productNumber' => $this->ids->get('out-of-range-product-' . $i),
                'name' => 'Test product ' . $i,
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => ['name' => 'tax-' . $i, 'taxRate' => 15],
                'manufacturer' => ['id' => $this->ids->create('out-of-range-manufacturer-' . $i), 'name' => 'manu-' . $i],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $salesChannelId, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
                'categories' => [['id' => $categoryId]],
            ];
        }

        static::getContainer()->get('category.repository')->create([[
            'id' => $categoryId,
            'name' => 'Out-of-range test category',
            'type' => 'page',
            'parentId' => $salesChannel->getNavigationCategoryId(),
            'cmsPage' => [
                'id' => $this->ids->create('out-of-range-cms-page'),
                'type' => 'product_list',
                'sections' => [[
                    'position' => 0,
                    'type' => 'sidebar',
                    'blocks' => [[
                        'type' => 'product-listing',
                        'position' => 1,
                        'slots' => [
                            ['type' => 'product-listing', 'slot' => 'content'],
                        ],
                    ]],
                ]],
            ],
        ]], Context::createDefaultContext());

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        // Retrieve the SEO URL generated for this category so requests bypass
        // the technical-URL → SEO-URL redirect and land directly on the listing.
        /** @var SeoUrlEntity|null $seoUrl */
        $seoUrl = static::getContainer()->get('seo_url.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('foreignKey', $categoryId),
                new EqualsFilter('salesChannelId', $salesChannelId),
                new EqualsFilter('isCanonical', true)
            ),
            Context::createDefaultContext()
        )->first();

        static::assertNotNull(
            $seoUrl,
            \sprintf('SEO URL for category %s was not generated; cannot exercise the storefront listing flow.', $categoryId)
        );

        return ltrim($seoUrl->getSeoPathInfo(), '/');
    }
}
