<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group store-api
 */
class ProductSearchRouteTest extends TestCase
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
            '/store-api/search?search=Product-Test',
            [
            ]
        );

        $response = json_decode($this->browser->getResponse()->getContent(), true);
        static::assertSame(15, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(15, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testNotFindingProducts(): void
    {
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], $this->ids->context);

        $this->browser->request(
            'POST',
            '/store-api/search?search=YAYY',
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
            '/store-api/search',
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
     * @dataProvider searchTestCases
     */
    public function testProductSearch(array $productData, string $productNumber, array $searchTerms, IdsCollection $ids, ?string $languageId = null): void
    {
        $this->createGermanSalesChannelDomain();

        $productRepository = $this->getContainer()->get('product.repository');
        $productRepository->create([$productData], $ids->getContext());

        $searchRoute = $this->getContainer()->get(ProductSearchRoute::class);

        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            'token',
            Defaults::SALES_CHANNEL,
            [
                SalesChannelContextService::LANGUAGE_ID => $languageId ?? Defaults::LANGUAGE_SYSTEM,
            ]
        );

        foreach ($searchTerms as $searchTerm => $shouldBeFound) {
            $result = $searchRoute->load(
                new Request(['search' => $searchTerm]),
                $salesChannelContext,
                new Criteria()
            );

            static::assertEquals(
                $shouldBeFound,
                $result->getListingResult()->has($ids->get($productNumber)),
                sprintf(
                    'Product was%s found, but should%s be found for term "%s".',
                    $result->getListingResult()->has($ids->get($productNumber)) ? '' : ' not',
                    $shouldBeFound ? '' : ' not',
                    $searchTerm
                )
            );
        }
    }

    public function searchTestCases(): array
    {
        $idsCollection = new IdsCollection();

        return [
            'test it finds product' => [
                (new ProductBuilder($idsCollection, '1000'))
                    ->price(10)
                    ->name('Lorem ipsum')
                    ->translation($this->getDeDeLanguageId(), 'name', 'dolor sit amet')
                    ->visibility()
                    ->manufacturer('manufacturer', [$this->getDeDeLanguageId() => ['name' => 'Hersteller']])
                    ->build(),
                '1000',
                [
                    '1000' => true, // productNumber
                    'Lorem' => true, // part of name
                    'ipsum' => true, // part of name
                    'Lorem ipsum' => true, // full name
                    'manufacturer' => true, // manufacturer
                    'dolor sit amet' => false, // full name but different language
                    'Hersteller' => false, // manufacturer but different language
                ],
                $idsCollection,
            ],
            'test it finds product by translation' => [
                (new ProductBuilder($idsCollection, '1000'))
                    ->price(10)
                    ->name('Lorem ipsum')
                    ->translation($this->getDeDeLanguageId(), 'name', 'dolor sit amet')
                    ->visibility()
                    ->manufacturer('manufacturer', [$this->getDeDeLanguageId() => ['name' => 'Hersteller']])
                    ->build(),
                '1000',
                [
                    '1000' => true, // productNumber
                    'dolor' => true, // part of name
                    'sit' => true, // part of name
                    'amet' => true, // part of name
                    'dolor sit amet' => true, // full name
                    'Hersteller' => true, // manufacturer
                    'Lorem ipsum' => false, // full name but different language
                    'manufacturer' => false, // manufacturer but different language
                ],
                $idsCollection,
                $this->getDeDeLanguageId(),
            ],
            'test it finds product by fallback translations' => [
                (new ProductBuilder($idsCollection, '1000'))
                    ->price(10)
                    ->name('Lorem ipsum')
                    ->visibility()
                    ->manufacturer('manufacturer')
                    ->build(),
                '1000',
                [
                    '1000' => true, // productNumber
                    'Lorem' => true, // part of name
                    'ipsum' => true, // part of name
                    'Lorem ipsum' => true, // full name
                    'manufacturer' => true, // manufacturer
                ],
                $idsCollection,
                $this->getDeDeLanguageId(),
            ],
            'test it finds variant product' => [
                (new ProductBuilder($idsCollection, '1001'))
                    ->name('consectetur adipiscing')
                    ->translation($this->getDeDeLanguageId(), 'name', 'Suspendisse in')
                    ->price(5)
                    ->visibility()
                    ->manufacturer('varius', [$this->getDeDeLanguageId() => ['name' => 'Vestibulum']])
                    ->variant(
                        (new ProductBuilder($idsCollection, '1000'))
                            ->price(10)
                            ->name('Lorem ipsum')
                            ->translation($this->getDeDeLanguageId(), 'name', 'dolor sit amet')
                            ->visibility()
                            ->manufacturer('manufacturer', [$this->getDeDeLanguageId() => ['name' => 'Hersteller']])
                            ->build()
                    )
                    ->build(),
                '1000',
                [
                    '1000' => true, // productNumber
                    'Lorem' => true, // part of name
                    'ipsum' => true, // part of name
                    'Lorem ipsum' => true, // full name
                    'manufacturer' => true, // manufacturer
                    'dolor sit amet' => false, // full name but different language
                    'Hersteller' => false, // manufacturer but different language
                    'consectetur adipiscing' => false, // full name but of parent language
                    'Suspendisse in' => false, // full name but of parent & different language
                    'varius' => false, // manufacturer but of parent
                    'Vestibulum' => false, // manufacturer but of parent & different language
                ],
                $idsCollection,
            ],
            'test it finds variant product by translation' => [
                (new ProductBuilder($idsCollection, '1001'))
                    ->name('consectetur adipiscing')
                    ->translation($this->getDeDeLanguageId(), 'name', 'Suspendisse in')
                    ->price(5)
                    ->visibility()
                    ->manufacturer('varius', [$this->getDeDeLanguageId() => ['name' => 'Vestibulum']])
                    ->variant(
                        (new ProductBuilder($idsCollection, '1000'))
                            ->price(10)
                            ->name('Lorem ipsum')
                            ->translation($this->getDeDeLanguageId(), 'name', 'dolor sit amet')
                            ->visibility()
                            ->manufacturer('manufacturer', [$this->getDeDeLanguageId() => ['name' => 'Hersteller']])
                            ->build()
                    )
                    ->build(),
                '1000',
                [
                    '1000' => true, // productNumber
                    'dolor' => true, // part of name
                    'sit' => true, // part of name
                    'amet' => true, // part of name
                    'dolor sit amet' => true, // full name
                    'Hersteller' => true, // manufacturer
                    'Lorem ipsum' => false, // full name but different language
                    'manufacturer' => false, // manufacturer but different language
                    'consectetur adipiscing' => false, // full name but of parent language
                    'Suspendisse in' => false, // full name but of parent & different language
                    'varius' => false, // manufacturer but of parent
                    'Vestibulum' => false, // manufacturer but of parent & different language
                ],
                $idsCollection,
                $this->getDeDeLanguageId(),
            ],
            'test it finds variant product by parent translation' => [
                (new ProductBuilder($idsCollection, '1001'))
                    ->name('consectetur adipiscing')
                    ->translation($this->getDeDeLanguageId(), 'name', 'Suspendisse in')
                    ->price(5)
                    ->visibility()
                    ->manufacturer('varius', [$this->getDeDeLanguageId() => ['name' => 'Vestibulum']])
                    ->variant(
                        (new ProductBuilder($idsCollection, '1000'))
                            ->price(10)
                            ->name('Lorem ipsum')
                            ->visibility()
                            ->manufacturer('manufacturer')
                            ->build()
                    )
                    ->build(),
                '1000',
                [
                    '1000' => true, // productNumber
                    'Suspendisse' => true, // part of parent name
                    'Suspendisse in' => true, // full parent name
                    'manufacturer' => true, // manufacturer
                    'Lorem ipsum' => false, // full name but different language
                    'consectetur adipiscing' => false, // full name but of parent language
                    'varius' => false, // manufacturer but of parent & different language
                    'Vestibulum' => false, // manufacturer but of parent
                ],
                $idsCollection,
                $this->getDeDeLanguageId(),
            ],
            'test it finds variant product with inherited data' => [
                (new ProductBuilder($idsCollection, '1001'))
                    ->name('consectetur adipiscing')
                    ->translation($this->getDeDeLanguageId(), 'name', 'Suspendisse in')
                    ->price(5)
                    ->visibility()
                    ->manufacturer('varius', [$this->getDeDeLanguageId() => ['name' => 'Vestibulum']])
                    ->variant(
                        (new ProductBuilder($idsCollection, '1000'))
                            ->name(null)
                            ->build()
                    )
                    ->build(),
                '1000',
                [
                    '1000' => true, // productNumber
                    'consectetur' => true, // part of parent name
                    'adipiscing' => true, // part of parent name
                    'consectetur adipiscing' => true, // full parent name
                    'varius' => true, // parent manufacturer
                    'Suspendisse in' => false, // full name but different language
                    'Vestibulum' => false, // manufacturer but different language
                ],
                $idsCollection,
            ],
        ];
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

    private function createGermanSalesChannelDomain(): void
    {
        $this->getContainer()->get('language.repository')->upsert([
            [
                'id' => $this->getDeDeLanguageId(),
                'salesChannelDomains' => [
                    [
                        'salesChannelId' => Defaults::SALES_CHANNEL,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                        'url' => $_SERVER['APP_URL'] . '/de',
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
