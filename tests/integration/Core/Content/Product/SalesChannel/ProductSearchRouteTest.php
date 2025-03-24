<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRoute;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Group('store-api')]
class ProductSearchRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private static KernelBrowser $browser;

    private static IdsCollection $ids;

    private static bool $initialized = false;

    private string $productSearchConfigId;

    private EntityRepository $productSearchConfigRepository;

    private SearchKeywordUpdater $searchKeywordUpdater;

    protected function setUp(): void
    {
        $this->searchKeywordUpdater = static::getContainer()->get(SearchKeywordUpdater::class);
        $this->productSearchConfigRepository = static::getContainer()->get('product_search_config.repository');
        $this->productSearchConfigId = $this->getProductSearchConfigId();
        if (self::$initialized === false) {
            $this->initializeIndexing();
            self::$initialized = true;
        }
    }

    #[BeforeClass]
    public static function startTransactionBefore(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->beginTransaction();
    }

    #[AfterClass]
    public static function stopTransactionAfter(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->rollBack();
    }

    public function testFindingProductsByTerm(): void
    {
        $browser = self::$browser;
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=Test-Product',
            [
            ]
        );
        static::assertIsString($browser->getResponse()->getContent());
        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(15, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(15, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testNotFindingProducts(): void
    {
        $browser = self::$browser;
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=YAYY',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(0, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        static::assertCount(0, $response['elements']);
    }

    public function testMissingSearchTerm(): void
    {
        $browser = self::$browser;
        $browser->request(
            'POST',
            '/store-api/search',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code']);

        $browser->request(
            'POST',
            '/store-api/search-suggest',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($response);
        static::assertArrayHasKey('errors', $response);
        static::assertSame('FRAMEWORK__MISSING_REQUEST_PARAMETER', $response['errors'][0]['code']);
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('searchOrCases')]
    public function testSearchOr(string $term, array $expected): void
    {
        $browser = self::$browser;
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $this->proceedTestSearch($browser, $term, $expected);
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('searchAndCases')]
    public function testSearchAnd(string $term, array $expected): void
    {
        $browser = self::$browser;
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => true],
        ], Context::createDefaultContext());

        $this->proceedTestSearch($browser, $term, $expected);
    }

    public function testFindingProductAlreadyHaveVariantsWithCustomSearchKeywords(): void
    {
        $browser = self::$browser;
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=bmw',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(2, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(2, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);

        $browser->request(
            'POST',
            '/store-api/search-suggest?search=bmw',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(2, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(2, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testFindingProductWhenAddedVariantsAfterSettingCustomSearchKeywords(): void
    {
        $browser = self::$browser;
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=volvo',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);

        $browser->request(
            'POST',
            '/store-api/search-suggest?search=volvo',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testFindingProductAlreadySetCustomSearchKeywordsWhenRemovedVariants(): void
    {
        $browser = self::$browser;
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=audi',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(2, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(2, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    public function testFindingProductWithVariantsHaveDifferentKeyword(): void
    {
        $browser = self::$browser;
        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=bmw',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(2, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(2, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);

        $browser->request(
            'POST',
            '/store-api/search?search=mercedes',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $response['total']);
        static::assertSame('product_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('product', $response['elements'][0]['apiAlias']);
    }

    /**
     * @param array<string, bool> $searchTerms
     */
    #[DataProvider('searchTestCases')]
    public function testProductSearch(string $productNumber, array $searchTerms, ?string $languageId): void
    {
        $ids = self::$ids;
        if ($languageId === 'de-DE') {
            $languageId = $this->getDeDeLanguageId();
        }

        $searchRoute = static::getContainer()->get(ProductSearchRoute::class);
        $suggestRoute = static::getContainer()->get(ProductSuggestRoute::class);

        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            'token',
            $ids->get('sales-channel'),
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
                \sprintf(
                    'Product was%s found, but should%s be found for term "%s".',
                    $result->getListingResult()->has($ids->get($productNumber)) ? '' : ' not',
                    $shouldBeFound ? '' : ' not',
                    $searchTerm
                )
            );

            $result = $suggestRoute->load(
                new Request(['search' => $searchTerm]),
                $salesChannelContext,
                new Criteria()
            );

            static::assertEquals(
                $shouldBeFound,
                $result->getListingResult()->has($ids->get($productNumber)),
                \sprintf(
                    'Product was%s found, but should%s be found for term "%s".',
                    $result->getListingResult()->has($ids->get($productNumber)) ? '' : ' not',
                    $shouldBeFound ? '' : ' not',
                    $searchTerm
                )
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function searchTestCases(): array
    {
        return [
            'test it finds product' => [
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
                null,
            ],
            'test it finds product by translation' => [
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
                'de-DE',
            ],
            'test it finds product by fallback translations' => [
                '1002',
                [
                    '1002' => true, // productNumber
                    'Latin' => true, // part of name
                    'literature' => true, // part of name
                    'latin literature' => true, // full name
                ],
                'de-DE',
            ],
            'test it finds variant product' => [
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
                null,
            ],
            'test it finds variant product by translation' => [
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
                'de-DE',
            ],
            'test it finds variant product by parent translation' => [
                '1001.1',
                [
                    '1001' => true, // productNumber
                    'Suspendisse' => true, // part of parent name
                    'Suspendisse in' => true, // full parent name
                    'Vestibulum' => true, // manufacturer
                    'Lorem ipsum' => false, // full name but different language
                    'consectetur adipiscing' => false, // full name but of parent language
                    'varius' => false, // manufacturer but of parent & different language
                ],
                'de-DE',
            ],
            'test it finds variant product with inherited data' => [
                '1001.1',
                [
                    '1001' => true, // productNumber
                    'consectetur' => true, // part of parent name
                    'adipiscing' => true, // part of parent name
                    'consectetur adipiscing' => true, // full parent name
                    'varius' => true, // parent manufacturer
                    'Suspendisse in' => false, // full name but different language
                    'Vestibulum' => false, // manufacturer but different language
                ],
                null,
            ],
        ];
    }

    /**
     * @return list<list<string>|mixed>
     */
    public static function searchAndCases(): array
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
                'Incredible-%Plastic',
                ['Incredible Plastic Duoflex'],
            ],
            [
                'Incredible$^%&%$&$Plastic',
                ['Incredible Plastic Duoflex'],
            ],
            [
                '(๑★ .̫ ★๑)Incredible$^%&%$&$Plastic(๑★ .̫ ★๑)',
                ['Incredible Plastic Duoflex'],
            ],
            [
                '‰€€Incredible$^%&%$&$Plastic‰€€',
                ['Incredible Plastic Duoflex'],
            ],
            [
                '³²¼¼³¬½{¬]Incredible³²¼¼³¬½{¬]$^%&%$&$Plastic',
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

    /**
     * @return list<list<string>|mixed>
     */
    public static function searchOrCases(): array
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
                [],
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
                'Fantastic',
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

    protected function initializeIndexing(): void
    {
        self::$ids = new IdsCollection();
        $ids = self::$ids;
        $this->createNavigationCategory($ids);

        self::$browser = $this->createCustomSalesChannelBrowser([
            'id' => $ids->create('sales-channel'),
            'navigationCategoryId' => $ids->get('category'),
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM], ['id' => $this->getDeDeLanguageId()]],
        ]);

        $this->createGermanSalesChannelDomain($ids);

        $this->setupProductsForImplementSearch($ids);
    }

    /**
     * @param array<string, mixed> $expected
     */
    private function proceedTestSearch(KernelBrowser $browser, string $term, array $expected): void
    {
        $browser->request(
            'POST',
            '/store-api/search?search=' . $term,
            [
            ]
        );

        static::assertIsString($browser->getResponse()->getContent());
        $response = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $entities = $response['elements'];
        $resultProductName = array_column($entities, 'name');

        sort($expected);
        sort($resultProductName);

        static::assertEquals($expected, $resultProductName);
    }

    private function createNavigationCategory(IdsCollection $ids): void
    {
        $data = [
            'id' => $ids->create('category'),
            'name' => 'Test',
        ];

        static::getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function setupProductsForImplementSearch(IdsCollection $ids): void
    {
        /** @var EntityRepository $productRepository */
        $productRepository = static::getContainer()->get('product.repository');
        $productIds = [];
        $productsNames = [
            'Rustic Copper Drastic Plastic' => '123123123',
            'Incredible Plastic Duoflex' => '765752342',
            'Fantastic Concrete Comveyer' => '834157484',
            'Fantastic Copper Ginger Vitro' => '9095345345',
        ];

        $products = [
            (new ProductBuilder($ids, 'bmw'))
                ->name(Uuid::randomHex())
                ->visibility($ids->get('sales-channel'))
                ->price(10, 9)
                ->manufacturer('shopware AG')
                ->add('customSearchKeywords', ['bmw'])
                ->variant(
                    (new ProductBuilder($ids, 'bmw.1'))
                        ->visibility($ids->get('sales-channel'))
                        ->build()
                )
                ->build(),
            // same as above, but has mercedes as variant
            (new ProductBuilder($ids, 'mercedes'))
                ->name(Uuid::randomHex())
                ->visibility($ids->get('sales-channel'))
                ->price(10, 9)
                ->manufacturer('shopware AG')
                ->add('customSearchKeywords', ['bmw'])
                ->variant(
                    (new ProductBuilder($ids, 'mercedes.1'))
                        ->visibility($ids->get('sales-channel'))
                        ->add('customSearchKeywords', ['bmw'])
                        ->build()
                )
                ->build(),
            // Add to a product later variants
            (new ProductBuilder($ids, 'volvo'))
                ->name(Uuid::randomHex())
                ->visibility($ids->get('sales-channel'))
                ->price(10, 9)
                ->manufacturer('shopware AG')
                ->add('customSearchKeywords', ['volvo'])
                ->build(),
            (new ProductBuilder($ids, 'audi'))
                ->name(Uuid::randomHex())
                ->visibility($ids->get('sales-channel'))
                ->price(10, 9)
                ->manufacturer('shopware AG')
                ->add('customSearchKeywords', ['audi'])
                ->variant(
                    (new ProductBuilder($ids, 'audi.1'))
                        ->visibility($ids->get('sales-channel'))
                        ->build(),
                )
                ->variant(
                    (new ProductBuilder($ids, 'audi.2'))
                        ->visibility($ids->get('sales-channel'))
                        ->build()
                )
                ->variant(
                    (new ProductBuilder($ids, 'audi.3'))
                        ->visibility($ids->get('sales-channel'))
                        ->build()
                )
                ->build(),

            // search by term
            (new ProductBuilder($ids, '1000'))
                ->price(10)
                ->name('Lorem ipsum')
                ->translation($this->getDeDeLanguageId(), 'name', 'dolor sit amet')
                ->visibility($ids->get('sales-channel'))
                ->manufacturer('manufacturer', [$this->getDeDeLanguageId() => ['name' => 'Hersteller']])
                ->build(),

            (new ProductBuilder($ids, '1001'))
                ->name('consectetur adipiscing')
                ->translation($this->getDeDeLanguageId(), 'name', 'Suspendisse in')
                ->price(5)
                ->visibility($ids->get('sales-channel'))
                ->manufacturer('varius', [$this->getDeDeLanguageId() => ['name' => 'Vestibulum']])
                ->variant(
                    (new ProductBuilder($ids, '1001.1'))
                        ->price(10)
                        ->name(null)
                        ->visibility($ids->get('sales-channel'))
                        ->build()
                )
                ->build(),

            (new ProductBuilder($ids, '1002'))
                ->price(10)
                ->name('Latin literature')
                ->visibility($ids->get('sales-channel'))
                ->build(),
        ];

        foreach ($productsNames as $name => $number) {
            $products[] = (new ProductBuilder($ids, $number))
                ->name($name)
                ->stock(1)
                ->price(19.99, 10)
                ->manufacturer('shopware AG')
                ->tax('15', 15)
                ->category('random cat')
                ->visibility($ids->get('sales-channel'))
                ->build();
        }

        for ($i = 1; $i <= 15; ++$i) {
            $products[] = (new ProductBuilder($ids, 'product' . $i))
                ->name('Test-Product')
                ->manufacturer('test-' . $i)
                ->active(true)
                ->price(15, 10)
                ->tax('test', 15)
                ->visibility($ids->get('sales-channel'))
                ->build();
        }

        $productRepository->create($products, Context::createDefaultContext());

        $this->searchKeywordUpdater->update($productIds, Context::createDefaultContext());

        $this->productSearchConfigRepository->update([
            ['id' => $this->productSearchConfigId, 'minSearchLength' => 3],
        ], Context::createDefaultContext());

        $productRepository->create([
            (new ProductBuilder($ids, 'volvo.1'))
                ->visibility($ids->get('sales-channel'))
                ->parent('volvo')
                ->build(),
        ], Context::createDefaultContext());
    }

    private function getProductSearchConfigId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('languageId', Context::createDefaultContext()->getLanguageId())
        );

        $firstId = $this->productSearchConfigRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        static::assertIsString($firstId);

        return $firstId;
    }

    private function createGermanSalesChannelDomain(IdsCollection $ids): void
    {
        static::getContainer()->get('language.repository')->upsert([
            [
                'id' => $this->getDeDeLanguageId(),
                'salesChannelDomains' => [
                    [
                        'salesChannelId' => $ids->get('sales-channel'),
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                        'url' => $_SERVER['APP_URL'] . '/de',
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
