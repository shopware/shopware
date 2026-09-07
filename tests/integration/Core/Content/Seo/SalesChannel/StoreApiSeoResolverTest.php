<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Seo\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\TestNavigationSeoUrlRoute;
use Shopware\Core\Content\Test\TestProductSeoUrlRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
class StoreApiSeoResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $this->setVisibilities();
    }

    public function testDisabledState(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/category/home',
            [
            ]
        );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['seoUrls']);
        static::assertNull($response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing']['elements'][0]['seoUrls']);
    }

    public function testEnabled(): void
    {
        $this->browser->setServerParameter('HTTP_sw-include-seo-urls', '1');

        $this->browser->request(
            'POST',
            '/store-api/category/home',
            [],
            [],
            []
        );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('seoUrls', $response);
        static::assertCount(1, $response['seoUrls']);
        static::assertSame(TestNavigationSeoUrlRoute::ROUTE_NAME, $response['seoUrls'][0]['routeName']);
        static::assertSame($this->ids->get('category'), $response['seoUrls'][0]['foreignKey']);
        static::assertSame('foo', $response['seoUrls'][0]['pathInfo']);
    }

    public function testEnabledSalesChannelProducts(): void
    {
        $this->browser->setServerParameter('HTTP_sw-include-seo-urls', '1');

        $this->browser->request(
            'POST',
            '/store-api/category/home',
            [],
            [],
            []
        );

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('seoUrls', $response);
        static::assertCount(1, $response['seoUrls']);
        static::assertSame(TestNavigationSeoUrlRoute::ROUTE_NAME, $response['seoUrls'][0]['routeName']);
        static::assertSame($this->ids->get('category'), $response['seoUrls'][0]['foreignKey']);
        static::assertSame('foo', $response['seoUrls'][0]['pathInfo']);

        static::assertIsArray($response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing']['elements'][0]['seoUrls']);
    }

    public function testEnabledNoAuthentication(): void
    {
        $this->browser->setServerParameter('HTTP_sw-include-seo-urls', '1');

        $this->browser->request('GET', '/store-api/test/store-api-seo-resolver/no-auth-required', ['sales-channel-id' => $this->ids->get('sales-channel')]);

        $content = $this->browser->getResponse()->getContent();
        static::assertIsString($content);

        $response = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('seoUrls', $response);
        static::assertNull($response['seoUrls']);

        static::assertNull($response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing']['elements'][0]['seoUrls']);
    }

    public function testContentRouteEnrichesSeoUrlsOnASeoAwareEntityHeldByARenderedElementProperty(): void
    {
        $this->createContentLayoutConsumingTheCategory();

        $this->browser->setServerParameter('HTTP_sw-include-seo-urls', '1');

        $full = $this->contentRouteJson('content');

        // Presence first: the seo-aware category really is the value of the root's `category` property, so the
        // enrichment asserted below is read off the entity a rendered element carries and not off some other
        // part of the body. `StoreApiSeoResolver` reaches it only by descending `RenderedElement`, because the
        // response's own struct is a `ContentPage` whose sole other vars are the layout's id, name and version.
        static::assertIsArray($full['elements'][0]['properties'] ?? null);
        $fullCategory = $full['elements'][0]['properties']['category'] ?? null;
        static::assertIsArray($fullCategory);
        static::assertSame($this->ids->get('category'), $fullCategory['id'] ?? null);

        static::assertIsArray($fullCategory['seoUrls'] ?? null);
        static::assertCount(1, $fullCategory['seoUrls']);
        static::assertSame(TestNavigationSeoUrlRoute::ROUTE_NAME, $fullCategory['seoUrls'][0]['routeName']);
        static::assertSame($this->ids->get('category'), $fullCategory['seoUrls'][0]['foreignKey']);
        static::assertSame('foo', $fullCategory['seoUrls'][0]['pathInfo']);

        $decomposed = $this->contentRouteJson('content-decomposed');

        // The decomposed format serves the same entity through a ref rather than inline, so it is reached the
        // way a client reaches it: the element's assignment entry names a ref, and the ref keys the data map.
        static::assertIsArray($decomposed['assignments'][$this->ids->get('content-root')] ?? null);
        $ref = $decomposed['assignments'][$this->ids->get('content-root')]['category'] ?? null;
        static::assertIsString($ref);

        static::assertIsArray($decomposed['data'] ?? null);
        static::assertArrayHasKey($ref, $decomposed['data']);
        $decomposedCategory = $decomposed['data'][$ref];
        static::assertIsArray($decomposedCategory);
        static::assertSame($this->ids->get('category'), $decomposedCategory['id'] ?? null);

        static::assertIsArray($decomposedCategory['seoUrls'] ?? null);
        static::assertCount(1, $decomposedCategory['seoUrls']);
        static::assertSame(TestNavigationSeoUrlRoute::ROUTE_NAME, $decomposedCategory['seoUrls'][0]['routeName']);
        static::assertSame($this->ids->get('category'), $decomposedCategory['seoUrls'][0]['foreignKey']);
        static::assertSame('foo', $decomposedCategory['seoUrls'][0]['pathInfo']);
    }

    /**
     * A single-element layout assigned to the fixture category, whose root consumes the category root source's
     * root-ambient `category` context through a root-scoped consumer, undotted and unaliased. An unaliased
     * consumer takes the delivered value as it stands, so the hydrated, seo-aware `CategoryEntity` becomes the value of the root's `category`
     * property rather than a scalar off it. No shipped element type declares an entity-typed property that a
     * data loader could fill with a product, so the page-level context is the only shipped way to put a
     * seo-aware entity on a rendered property.
     */
    private function createContentLayoutConsumingTheCategory(): void
    {
        $context = Context::createDefaultContext();

        static::getContainer()->get('content_layout.repository')->create([[
            'id' => $this->ids->create('content-layout'),
            'name' => 'store-api-seo-resolver',
            'version' => '1.0.0',
            'rootSource' => 'category',
            'layout' => [[
                'id' => $this->ids->create('content-root'),
                'component' => 'Sw:Grid:Container',
                'properties' => [],
                'acceptsContext' => [
                    'category' => ['type' => 'single', 'required' => false, 'scope' => 'root'],
                ],
            ]],
        ]], $context);

        static::getContainer()->get('category_content_layout.repository')->create([[
            'id' => $this->ids->create('content-assignment'),
            'categoryId' => $this->ids->get('category'),
            'salesChannelId' => null,
            'contentLayoutId' => $this->ids->get('content-layout'),
        ]], $context);
    }

    /**
     * @return array<string, mixed>
     */
    private function contentRouteJson(string $format): array
    {
        $this->browser->request('GET', '/store-api/' . $format . '/category/' . $this->ids->get('category'));

        $response = $this->browser->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $content);

        $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($body);

        return $body;
    }

    private function createData(): void
    {
        $product = [
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'active' => true,
            'seoUrls' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => TestProductSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => 'foo',
                    'seoPathInfo' => 'foo',
                    'isCanonical' => true,
                ],
            ],
        ];

        $products = [];
        for ($i = 0; $i < 5; ++$i) {
            $products[] = array_merge(
                [
                    'id' => $this->ids->create('product' . $i),
                    'manufacturer' => ['id' => $this->ids->create('manufacturer-' . $i), 'name' => 'test-' . $i],
                    'productNumber' => $this->ids->get('product' . $i),
                ],
                $product
            );
        }

        $data = [
            'id' => $this->ids->create('category'),
            'name' => 'Test',
            'cmsPage' => [
                'id' => $this->ids->create('cms-page'),
                'type' => 'product_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'product-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'product-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'products' => $products,
            'seoUrls' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'routeName' => TestNavigationSeoUrlRoute::ROUTE_NAME,
                    'pathInfo' => 'foo',
                    'seoPathInfo' => 'foo',
                    'isCanonical' => true,
                ],
            ],
        ];

        static::getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function setVisibilities(): void
    {
        $products = [];
        for ($i = 0; $i < 5; ++$i) {
            $products[] = [
                'id' => $this->ids->get('product' . $i),
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        static::getContainer()->get('product.repository')
            ->update($products, Context::createDefaultContext());
    }
}
