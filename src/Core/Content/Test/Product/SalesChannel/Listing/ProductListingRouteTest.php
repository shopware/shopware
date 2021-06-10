<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Listing;

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
class ProductListingRouteTest extends TestCase
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

    public function testLoadProducts(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/product-listing/' . $this->ids->get('category')
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('product_listing', $response['apiAlias']);
        static::assertCount(5, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testLoadProductsUsingDynamicGroupWithEmptyProductStreamId(): void
    {
        $this->createData('product_stream');

        $this->browser->request(
            'POST',
            '/store-api/product-listing/' . $this->ids->get('category')
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('product_listing', $response['apiAlias']);
        static::assertCount(5, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testIncludes(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/product-listing/' . $this->ids->get('category'),
            [
                'includes' => [
                    'product_listing' => ['total'],
                ],
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('product_listing', $response['apiAlias']);
        static::assertArrayNotHasKey('elements', $response);
        static::assertArrayHasKey('total', $response);
    }

    private function createData(string $productAssignmentType = 'product', ?string $productStreamId = null): void
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
            'productStreamId' => $productStreamId,
            'productAssignmentType' => $productAssignmentType,
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
            ->upsert([$data], $this->ids->context);
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
