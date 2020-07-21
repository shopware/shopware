<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\SalesChannel\AbstractCategoryRoute;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\PlatformRequest;

class CategoryRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var AbstractCategoryRoute
     */
    private $route;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    protected function setUp(): void
    {
        $this->route = $this->getContainer()->get(CategoryRoute::class);

        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $this->setVisibilities();
    }

    public function testCmsPageResolved(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/v' . PlatformRequest::API_VERSION . '/category/' . $this->ids->get('category')
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals($this->ids->get('category'), $response['id']);
        static::assertIsArray($response['cmsPage']);

        static::assertEquals($this->ids->get('cms-page'), $response['cmsPage']['id']);
        static::assertCount(1, $response['cmsPage']['sections']);

        static::assertCount(1, $response['cmsPage']['sections'][0]['blocks']);

        $block = $response['cmsPage']['sections'][0]['blocks'][0];

        static::assertEquals('product-listing', $block['type']);

        static::assertCount(1, $block['slots']);

        $slot = $block['slots'][0];
        static::assertEquals('product-listing', $slot['type']);

        static::assertArrayHasKey('listing', $slot['data']);

        $listing = $slot['data']['listing'];

        static::assertArrayHasKey('sortings', $listing);
        static::assertArrayHasKey('aggregations', $listing);
        static::assertArrayHasKey('elements', $listing);
    }

    public function testIncludesConsidered(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/v' . PlatformRequest::API_VERSION . '/category/' . $this->ids->get('category'),
            [
                'includes' => [
                    'product_manufacturer' => ['id', 'name', 'options'],
                    'product' => ['id', 'name', 'manufacturer', 'tax'],
                    'product_listing' => ['aggregations', 'elements'],
                    'tax' => ['id', 'name'],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $listing = $response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing'];

        static::assertArrayNotHasKey('sortings', $listing);
        static::assertArrayNotHasKey('page', $listing);
        static::assertArrayNotHasKey('limit', $listing);

        static::assertArrayHasKey('manufacturer', $listing['aggregations']);
        $manufacturers = $listing['aggregations']['manufacturer'];

        foreach ($manufacturers['entities'] as $manufacturer) {
            static::assertEquals(['name', 'id', 'apiAlias'], array_keys($manufacturer));
        }

        $products = $listing['elements'];
        foreach ($products as $product) {
            static::assertEquals(['name', 'tax', 'manufacturer', 'id', 'apiAlias'], array_keys($product));
            static::assertEquals(['name', 'id', 'apiAlias'], array_keys($product['tax']));
        }
    }

    public function testFilterConsidered(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/v' . PlatformRequest::API_VERSION . '/category/' . $this->ids->get('category'),
            [
                'manufacturer' => $this->ids->get('manufacturer-2'),
                'reduce-aggregations' => true,
                'includes' => [
                    'product_manufacturer' => ['id', 'name', 'options'],
                    'product' => ['id', 'name', 'tax', 'manufacturerId'],
                    'product_listing' => ['aggregations', 'elements', 'total'],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        $listing = $response['cmsPage']['sections'][0]['blocks'][0]['slots'][0]['data']['listing'];
        static::assertCount(1, $listing['elements']);
        static::assertEquals($this->ids->get('manufacturer-2'), $listing['elements'][0]['manufacturerId']);
        static::assertCount(1, $listing['aggregations']['manufacturer']['entities']);
        static::assertEquals($this->ids->get('manufacturer-2'), $listing['aggregations']['manufacturer']['entities'][0]['id']);
    }

    public function testHome(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/v' . PlatformRequest::API_VERSION . '/category/home',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertEquals($this->ids->get('category'), $response['id']);
        static::assertIsArray($response['cmsPage']);

        static::assertEquals($this->ids->get('cms-page'), $response['cmsPage']['id']);
        static::assertCount(1, $response['cmsPage']['sections']);

        static::assertCount(1, $response['cmsPage']['sections'][0]['blocks']);

        $block = $response['cmsPage']['sections'][0]['blocks'][0];

        static::assertEquals('product-listing', $block['type']);

        static::assertCount(1, $block['slots']);

        $slot = $block['slots'][0];
        static::assertEquals('product-listing', $slot['type']);

        static::assertArrayHasKey('listing', $slot['data']);

        $listing = $slot['data']['listing'];

        static::assertArrayHasKey('sortings', $listing);
        static::assertArrayHasKey('aggregations', $listing);
        static::assertArrayHasKey('elements', $listing);
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
        ];

        $this->getContainer()->get('category.repository')
            ->create([$data], $this->ids->context);
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
            ->update($products, $this->ids->context);
    }
}
