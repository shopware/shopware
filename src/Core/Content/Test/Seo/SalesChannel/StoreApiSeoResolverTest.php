<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Seo\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Test\TestNavigationSeoUrlRoute;
use Shopware\Core\Content\Test\TestProductSeoUrlRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
class StoreApiSeoResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

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

        $this->getContainer()->get('category.repository')
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

        $this->getContainer()->get('product.repository')
            ->update($products, Context::createDefaultContext());
    }
}
