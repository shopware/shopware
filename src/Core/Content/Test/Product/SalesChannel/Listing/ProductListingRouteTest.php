<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Listing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 * @group store-api
 */
class ProductListingRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private SalesChannelContext $salesChannelContext;

    private string $productId;

    /**
     * @var array<string>
     */
    private array $groupIds;

    /**
     * @var array<string>
     */
    private array $optionIds;

    /**
     * @var array<string>
     */
    private array $variantIds;

    private EntityRepositoryInterface $categoryRepository;

    private EntityRepositoryInterface $productRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->salesChannelContext = $this->createSalesChannelContext(['id' => $this->ids->create('sales-channel')]);

        /** @var EntityRepositoryInterface $categoryRepository */
        $categoryRepository = $this->getContainer()->get('category.repository');
        $this->categoryRepository = $categoryRepository;

        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->productRepository = $productRepository;
    }

    public function testLoadProducts(): void
    {
        $this->createData();

        $this->browser->request(
            'POST',
            '/store-api/product-listing/' . $this->ids->get('category')
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('product_listing', $response['apiAlias']);
        static::assertCount(6, $response['elements']);
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
        static::assertCount(6, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testLoadProductsUsingDynamicGroupWithProductStream(): void
    {
        $this->createData('product_stream', $this->ids->create('productStream'));

        $this->browser->request(
            'POST',
            '/store-api/product-listing/' . $this->ids->get('category')
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('product_listing', $response['apiAlias']);
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
        static::assertContains($response['elements'][0]['id'], [$this->variantIds['redL'], $this->variantIds['redXl']]);
    }

    public function testLoadProductsUsingDynamicGroupWithProductStreamAndMainVariant(): void
    {
        $this->createData('product_stream', $this->ids->create('productStream'), 'greenL');

        $this->browser->request(
            'POST',
            '/store-api/product-listing/' . $this->ids->get('category')
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('product_listing', $response['apiAlias']);
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
        static::assertSame($this->variantIds['greenL'], $response['elements'][0]['id']);
    }

    public function testIncludes(): void
    {
        $this->createData();

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

    private function createData(string $productAssignmentType = 'product', ?string $productStreamId = null, ?string $mainVariant = null): void
    {
        $this->productId = Uuid::randomHex();

        $this->optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'xl' => Uuid::randomHex(),
            'l' => Uuid::randomHex(),
        ];

        $this->variantIds = [
            'redXl' => Uuid::randomHex(),
            'greenXl' => Uuid::randomHex(),
            'redL' => Uuid::randomHex(),
            'greenL' => Uuid::randomHex(),
        ];

        $this->groupIds = [
            'color' => Uuid::randomHex(),
            'size' => Uuid::randomHex(),
        ];

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

        $product['id'] = $this->productId;
        $product['configuratorSettings'] = [
            [
                'option' => [
                    'id' => $this->optionIds['red'],
                    'name' => 'Red',
                    'group' => [
                        'id' => $this->groupIds['color'],
                        'name' => 'Color',
                    ],
                ],
            ],
            [
                'option' => [
                    'id' => $this->optionIds['green'],
                    'name' => 'Green',
                    'group' => [
                        'id' => $this->groupIds['color'],
                        'name' => 'Color',
                    ],
                ],
            ],
            [
                'option' => [
                    'id' => $this->optionIds['xl'],
                    'name' => 'XL',
                    'group' => [
                        'id' => $this->groupIds['size'],
                        'name' => 'size',
                    ],
                ],
            ],
            [
                'option' => [
                    'id' => $this->optionIds['l'],
                    'name' => 'L',
                    'group' => [
                        'id' => $this->groupIds['size'],
                        'name' => 'size',
                    ],
                ],
            ],
        ];
        $product['children'] = [
            [
                'id' => $this->variantIds['redXl'],
                'productNumber' => 'a.1',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['xl']],
                ],
            ],
            [
                'id' => $this->variantIds['greenXl'],
                'productNumber' => 'a.3',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['xl']],
                ],
            ],
            [
                'id' => $this->variantIds['redL'],
                'productNumber' => 'a.5',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
            [
                'id' => $this->variantIds['greenL'],
                'productNumber' => 'a.7',
                'stock' => 10,
                'active' => true,
                'parentId' => $this->productId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
        ];
        $product['productNumber'] = $this->productId;
        $products[] = $product;

        $data = [
            'id' => $this->ids->create('category'),
            'name' => 'Test',
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

        $this->getContainer()->get('product_stream.repository')->create([[
            'id' => $this->ids->create('productStream'),
            'name' => 'test',
            'filters' => [[
                'type' => 'equals',
                'field' => 'options.id',
                'value' => $this->optionIds['red'],
            ]],
        ]], Context::createDefaultContext());

        $this->categoryRepository->upsert([$data], Context::createDefaultContext());
        $this->categoryRepository->upsert([[
            'id' => $this->ids->get('category'),
            'productStreamId' => $productStreamId,
        ]], Context::createDefaultContext());

        if ($mainVariant) {
            $this->productRepository->upsert([['id' => $this->productId, 'mainVariantId' => $this->variantIds['greenL']]], Context::createDefaultContext());
        }

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $this->setVisibilities($products);
    }

    private function setVisibilities($createdProducts): void
    {
        $products = [];
        foreach ($createdProducts as $created) {
            $products[] = [
                'id' => $created['id'],
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        $this->productRepository->update($products, Context::createDefaultContext());
    }
}
