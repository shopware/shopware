<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @group slow
 * @group store-api
 */
class ProductSuggestRouteTest extends TestCase
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

    /**
     * @var SearchKeywordUpdater
     */
    private $searchKeywordUpdater;

    /**
     * @var EntityRepositoryInterface
     */
    private $productSearchConfigRepository;

    /**
     * @var string
     */
    private $productSearchConfigId;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->searchKeywordUpdater = $this->getContainer()->get(SearchKeywordUpdater::class);
        $this->productSearchConfigRepository = $this->getContainer()->get('product_search_config.repository');
        $this->productSearchConfigId = $this->getProductSearchConfigId();

        $this->resetSearchKeywordUpdaterConfig();

        $this->createData();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $this->setVisibilities();
        $this->setupProductsForImplementSearch();
    }

    public function testFindingProductsByTerm(): void
    {
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], $this->ids->context);

        $this->browser->request(
            'POST',
            '/store-api/search-suggest?search=Product-Test',
            [
                'total-count-mode' => Criteria::TOTAL_COUNT_MODE_EXACT,
                'limit' => 10,
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(15, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(10, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testNotFindingProducts(): void
    {
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], $this->ids->context);

        $this->browser->request(
            'POST',
            '/store-api/search-suggest?search=YAYY',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame(0, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        static::assertCount(0, $response['elements']);
    }

    public function testMissingSearchTerm(): void
    {
        $this->browser->request(
            'POST',
            '/store-api/search-suggest',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code']);
    }

    /**
     * @dataProvider searchOrCases
     */
    public function testSearchOr(string $term, array $expected): void
    {
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], $this->ids->context);

        $this->proceedTestSearch($term, $expected);
    }

    public function testFindingProductAlreadyHaveVariantsWithCustomSearchKeywords(): void
    {
        $productRepository = $this->getContainer()->get('product.repository');

        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], $this->ids->context);

        $parentProductData = $this->generateProductData();
        $products = [$parentProductData];
        for ($i = 0; $i < 3; ++$i) {
            $products[] = $this->generateProductData($parentProductData['id']);
        }

        $productRepository->create($products, $this->ids->context);
        $productRepository->update([
            ['id' => $parentProductData['id'], 'customSearchKeywords' => ['bmw']],
        ], $this->ids->context);

        $this->browser->request(
            'POST',
            '/store-api/search?search=bmw',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(1, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testFindingProductWhenAddedVariantsAfterSettingCustomSearchKeywords(): void
    {
        $productRepository = $this->getContainer()->get('product.repository');

        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], $this->ids->context);

        $parentProductData = $this->generateProductData();
        $productRepository->create([$parentProductData], $this->ids->context);
        $productRepository->update([
            ['id' => $parentProductData['id'], 'customSearchKeywords' => ['bmw']],
        ], $this->ids->context);

        $products = [];
        for ($i = 0; $i < 3; ++$i) {
            $products[] = $this->generateProductData($parentProductData['id']);
        }

        $productRepository->create($products, $this->ids->context);

        $this->browser->request(
            'POST',
            '/store-api/search?search=bmw',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(1, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testFindingProductAlreadySetCustomSearchKeywordsWhenRemovedVariants(): void
    {
        $productRepository = $this->getContainer()->get('product.repository');

        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], $this->ids->context);

        $parentProductData = $this->generateProductData();
        $products = [$parentProductData];
        for ($i = 0; $i < 3; ++$i) {
            $products[] = $this->generateProductData($parentProductData['id']);
        }

        $productRepository->create($products, $this->ids->context);
        $productRepository->update([
            ['id' => $parentProductData['id'], 'customSearchKeywords' => ['bmw']],
        ], $this->ids->context);

        $this->browser->request(
            'POST',
            '/store-api/search?search=bmw',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(1, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);

        $products = array_filter($products, fn ($product) => $product['parentId']);
        $products = array_map(fn ($product) => ['id' => $product['id']], $products);

        $productRepository->delete([$products], $this->ids->context);

        $this->browser->request(
            'POST',
            '/store-api/search?search=bmw',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(1, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testFindingProductWithVariantsHaveDifferentKeyword(): void
    {
        $productRepository = $this->getContainer()->get('product.repository');

        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], $this->ids->context);

        $parentProductData = $this->generateProductData();
        $products = [$parentProductData];
        for ($i = 0; $i < 3; ++$i) {
            $products[] = $this->generateProductData($parentProductData['id']);
        }

        $productRepository->create($products, $this->ids->context);
        $productRepository->update([
            ['id' => $parentProductData['id'], 'customSearchKeywords' => ['bmw']],
        ], $this->ids->context);

        $productRepository->update([
            ['id' => $products[1]['id'], 'customSearchKeywords' => ['mercedes']],
        ], $this->ids->context);

        $this->browser->request(
            'POST',
            '/store-api/search?search=bmw',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(1, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);

        $this->browser->request(
            'POST',
            '/store-api/search?search=mercedes',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(1, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    /**
     * @dataProvider searchAndCases
     */
    public function testSearchAnd(string $term, array $expected): void
    {
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => true],
        ], $this->ids->context);

        $this->proceedTestSearch($term, $expected);
    }

    public function searchAndCases(): array
    {
        return [
            [
                'Incredible Plastic Duoflex',
                ['Incredible Plastic Duoflex'],
            ],
            [
                'Incredible Plastic',
                ['Incredible Plastic Duoflex'],
            ],
            [
                'Incredible-%Plastic     ',
                ['Incredible Plastic Duoflex'],
            ],
            [
                'Incredible$^%&%$&$Plastic     ',
                ['Incredible Plastic Duoflex'],
            ],
            [
                '(๑★ .̫ ★๑)Incredible$^%&%$&$Plastic(๑★ .̫ ★๑)     ',
                ['Incredible Plastic Duoflex'],
            ],
            [
                '‰€€Incredible$^%&%$&$Plastic‰€€     ',
                ['Incredible Plastic Duoflex'],
            ],
            [
                '³²¼¼³¬½{¬]Incredible³²¼¼³¬½{¬]$^%&%$&$Plastic     ',
                ['Incredible Plastic Duoflex'],
            ],
            [
                'astic Concrete',
                ['Fantastic Concrete Comveyer'],
            ],
            [
                'astic cop',
                ['Rustic Copper Drastic Plastic', 'Fantastic Copper Ginger Vitro'],
            ],
            [
                '9095345345',
                [
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                'a b c d',
                [],
            ],
            [
                '@#%%#$ #$#@$ f@#$#$',
                [],
            ],
        ];
    }

    public function searchOrCases(): array
    {
        return [
            [
                'astic',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Concrete Comveyer',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                'Incredible Copper Vitro',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                'Incredible-Copper-Vitro',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                'Incredible%$^$%^Copper%$^$^$%^Vitro',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                '(๑★ .̫ ★๑)Incredible%$^$%^Copper%$^$^$%^Vitro‰€€',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                '‰€€Incredible%$^$%^Copper%$^$^$%^Vitro‰€€',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                '³²¼¼³¬½{¬]Incredible%$^$%^Copper%$^$^$%^Vitro‰€€³²¼¼³¬½{¬]',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                '        Fantastic            ',
                [
                    'Fantastic Concrete Comveyer',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                '9095345345',
                [
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                'a b c d',
                [],
            ],
        ];
    }

    private function proceedTestSearch(string $term, array $expected): void
    {
        $this->browser->request(
            'POST',
            '/store-api/search?search=' . $term,
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        /** @var array $entites */
        $entites = $response['elements'];
        $resultProductName = array_map(function ($product) {
            return $product['name'];
        }, $entites);

        sort($expected);
        sort($resultProductName);

        static::assertEquals($expected, array_values($resultProductName));
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

    private function setupProductsForImplementSearch(): void
    {
        /** @var EntityRepositoryInterface $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $productIds = [];
        $productsName = [
            'Rustic Copper Drastic Plastic',
            'Incredible Plastic Duoflex',
            'Fantastic Concrete Comveyer',
            'Fantastic Copper Ginger Vitro',
        ];
        $productsNumber = [
            '123123123',
            '765752342',
            '834157484',
            '9095345345',
        ];

        foreach ($productsName as $index => $name) {
            $productId = Uuid::randomHex();
            $productIds[] = $productId;

            $product = [
                'id' => $productId,
                'name' => $name,
                'productNumber' => $productsNumber[$index],
                'stock' => 1,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 19.99, 'net' => 10, 'linked' => false],
                ],
                'manufacturer' => ['id' => $productId, 'name' => 'shopware AG'],
                'tax' => ['id' => $this->getValidTaxId(), 'name' => 'testTaxRate', 'taxRate' => 15],
                'categories' => [
                    ['id' => $productId, 'name' => 'Random category'],
                ],
                'visibilities' => [
                    [
                        'id' => $productId,
                        'salesChannelId' => $this->ids->get('sales-channel'),
                        'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ];

            $productRepository->create([$product], $this->ids->context);
        }
        $this->searchKeywordUpdater->update($productIds, $this->ids->context);

        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'minSearchLength' => 3],
        ], $this->ids->context);
    }

    private function getProductSearchConfigId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('languageId', $this->ids->getContext()->getLanguageId())
        );

        return $this->productSearchConfigRepository->searchIds($criteria, $this->ids->context)->firstId();
    }

    private function resetSearchKeywordUpdaterConfig(): void
    {
        $class = new \ReflectionClass($this->searchKeywordUpdater);
        $property = $class->getProperty('decorated');
        $property->setAccessible(true);
        $searchKeywordUpdaterInner = $property->getValue($this->searchKeywordUpdater);

        $class = new \ReflectionClass($searchKeywordUpdaterInner);
        $property = $class->getProperty('config');
        $property->setAccessible(true);
        $property->setValue(
            $searchKeywordUpdaterInner,
            []
        );
    }

    private function generateProductData(?string $parentId = null): array
    {
        return [
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'name' => 'Car',
            'active' => true,
            'stock' => 10,
            'parentId' => $parentId,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false],
            ],
            'tax' => ['name' => 'Car Tax', 'taxRate' => 15],
            'manufacturer' => ['name' => 'Car Manufacture'],
            'visibilities' => [
                ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
    }
}
