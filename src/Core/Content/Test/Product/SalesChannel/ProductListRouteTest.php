<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;

/**
 * @group store-api
 */
class ProductListRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

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
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $this->setVisibilities();
    }

    public function testListingProducts(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/product',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(15, $response['total']);
        static::assertCount(15, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    /**
     * @group slow
     */
    public function testListingProductsLimit(): void
    {
        $this->browser->request(
            'GET',
            '/store-api/product?limit=1',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testListingProductsIds(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/product',
            [
                'ids' => [
                    $this->ids->get('product1'),
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(1, $response['total']);
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testListingProductsIncludes(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/product',
            [
                'includes' => [
                    'product' => ['id'],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(15, $response['total']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
        static::assertArrayHasKey('id', $response['elements'][0]);
        static::assertArrayHasKey('apiAlias', $response['elements'][0]);
        static::assertArrayNotHasKey('name', $response['elements'][0]);
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
        for ($i = 0; $i < 15; ++$i) {
            $products[] = array_merge(
                [
                    'id' => $this->ids->create('product' . $i),
                    'active' => true,
                    'manufacturer' => ['id' => $this->ids->create('manufacturer-' . $i), 'name' => 'test-' . $i],
                    'productNumber' => $this->ids->get('product' . $i),
                    'name' => 'Test-Product',
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
        for ($i = 0; $i < 15; ++$i) {
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
