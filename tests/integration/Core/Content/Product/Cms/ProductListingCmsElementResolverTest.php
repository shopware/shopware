<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\Cms;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductListingCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ProductListingCmsElementResolver $productListingCMSElementResolver;

    private SalesChannelContext $salesChannelContext;

    private Connection $connection;

    /**
     * @var array<string|int, mixed>
     */
    private array $productSortings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productListingCMSElementResolver = $this->getContainer()->get(ProductListingCmsElementResolver::class);
        $this->salesChannelContext = $this->createSalesChannelContext();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->productSortings = $this->getProductSortings();
    }

    public function testSortings(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->productSortings['price-desc'] => 1,
                    $this->productSortings['name-asc'] => 0,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request([], ['order' => 'name-asc'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(ProductListingResult::class, $listing);

        static::assertSame('name-asc', $listing->getSorting());

        foreach ($listing->getAvailableSortings() as $availableSorting) {
            static::assertArrayHasKey($availableSorting->getId(), $availableSortings);
        }
    }

    public function testDefaultSorting(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->productSortings['price-desc'] => 1,
                    $this->productSortings['name-asc'] => 0,
                ],
            ],
            'defaultSorting' => ['value' => $this->productSortings['name-asc']],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request()
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(ProductListingResult::class, $listing);

        static::assertSame('name-asc', $listing->getSorting());

        foreach ($listing->getAvailableSortings() as $availableSorting) {
            static::assertArrayHasKey($availableSorting->getId(), $availableSortings);
        }
    }

    /**
     * @deprecated tag:v6.7.0 - This test should be removed in v6.7.0.0
     */
    public function testSortingsByName(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    'price-desc' => 1,
                    'name-asc' => 0,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request([], ['order' => 'name-asc'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(ProductListingResult::class, $listing);

        static::assertSame('name-asc', $listing->getSorting());

        foreach ($listing->getAvailableSortings() as $availableSorting) {
            static::assertArrayHasKey($availableSorting->getKey(), $availableSortings);
        }
    }

    /**
     * @deprecated tag:v6.7.0 - This test should be removed in v6.7.0.0
     */
    public function testDefaultSortingByName(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    'price-desc' => 1,
                    'name-asc' => 0,
                ],
            ],
            'defaultSorting' => ['value' => 'name-asc'],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request()
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(ProductListingResult::class, $listing);

        static::assertSame('name-asc', $listing->getSorting());

        foreach ($listing->getAvailableSortings() as $availableSorting) {
            static::assertArrayHasKey($availableSorting->getKey(), $availableSortings);
        }
    }

    public function testUnavailableSortingThrowsNoException(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->productSortings['price-desc'] => 1,
                    $this->productSortings['name-asc'] => 0,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request([], ['order' => 'unavailable-order'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        static::assertEquals(new ElementDataCollection(), $result);
    }

    public function testOnlyRestrictedSortingsAreAvailable(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->productSortings['price-desc'] => 1,
                    $this->productSortings['price-asc'] => 0,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request([], ['order' => 'price-desc'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(ProductListingResult::class, $listing);

        $actualSortings = $listing->getAvailableSortings()->map(fn (ProductSortingEntity $actualSorting) => $actualSorting->getId());

        $availableSortings = array_keys($availableSortings);

        sort($actualSortings);
        sort($availableSortings);

        static::assertSame($availableSortings, $actualSortings);
    }

    /**
     * @deprecated tag:v6.7.0 - This test should be removed in v6.7.0.0
     */
    public function testOnlyRestrictedSortingsAreAvailableByName(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    'price-desc' => 1,
                    'price-asc' => 0,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request([], ['order' => 'price-desc'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(ProductListingResult::class, $listing);

        $actualSortings = $listing->getAvailableSortings()->map(fn (ProductSortingEntity $actualSorting) => $actualSorting->getKey());

        $availableSortings = array_keys($availableSortings);

        sort($actualSortings);
        sort($availableSortings);

        static::assertSame($availableSortings, $actualSortings);
    }

    public function testAvailableSortingsPriority(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->productSortings['price-desc'] => 1,
                    $this->productSortings['price-asc'] => 100,
                    $this->productSortings['name-asc'] => 77,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request([], ['order' => 'price-desc'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(ProductListingResult::class, $listing);

        $actualSortings = $listing->getAvailableSortings()->map(fn (ProductSortingEntity $actualSorting) => $actualSorting->getId());

        $actualSortings = array_values($actualSortings);

        arsort($availableSortings, \SORT_DESC | \SORT_NUMERIC);
        $availableSortings = array_keys($availableSortings);

        static::assertSame($availableSortings, $actualSortings);
    }

    /**
     * @deprecated tag:v6.7.0 - This test should be removed in v6.7.0.0
     */
    public function testAvailableSortingsPriorityByName(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    'price-desc' => 1,
                    'price-asc' => 100,
                    'name-asc' => 77,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request([], ['order' => 'price-desc'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(ProductListingResult::class, $listing);

        $actualSortings = $listing->getAvailableSortings()->map(fn (ProductSortingEntity $actualSorting) => $actualSorting->getKey());

        $actualSortings = array_values($actualSortings);

        arsort($availableSortings, \SORT_DESC | \SORT_NUMERIC);
        $availableSortings = array_keys($availableSortings);

        static::assertSame($availableSortings, $actualSortings);
    }

    public function testHighestPrioritySortingIsDefaultSorting(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->productSortings['price-desc'] => 1,
                    $this->productSortings['price-asc'] => 100,
                    $this->productSortings['name-asc'] => 77,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request()
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(ProductListingResult::class, $listing);

        $sorting = $listing->getSorting();

        static::assertSame('price-asc', $sorting);
    }

    /**
     * @deprecated tag:v6.7.0 - This test should be removed in v6.7.0.0
     */
    public function testHighestPrioritySortingIsDefaultSortingByName(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    'price-desc' => 1,
                    'price-asc' => 100,
                    'name-asc' => 77,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request()
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(ProductListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(ProductListingResult::class, $listing);

        $sorting = $listing->getSorting();

        static::assertSame('price-asc', $sorting);
    }

    /**
     * @param array<string, mixed> $expectations
     * @param array<string, mixed> $slotConfig
     */
    #[DataProvider('filtersProvider')]
    public function testFiltersAndPropertyWhitelist(array $expectations, array $slotConfig): void
    {
        $resolverContext = new ResolverContext(
            $this->salesChannelContext,
            new Request()
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('product-listing');
        $slot->setConfig($slotConfig);
        $slot->addTranslated('config', $slotConfig);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, new ElementDataCollection());

        $request = $resolverContext->getRequest();

        foreach ($expectations as $field => $expected) {
            if ($field === 'property-whitelist') {
                $value = $request->request->all($field);
            } else {
                $value = $request->request->get($field, true);
            }

            static::assertSame($expected, $value);
        }
    }

    /**
     * @return array<list<array<string, mixed>>>
     */
    public static function filtersProvider(): array
    {
        $sizeId = Uuid::randomHex();
        $textileId = Uuid::randomHex();

        return [
            [
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    'property-whitelist' => [],
                ],
                [
                    'filters' => [
                        'value' => null,
                    ],
                    'propertyWhitelist' => null,
                ],
            ],
            [
                [
                    'manufacturer-filter' => false,
                    'price-filter' => false,
                    'rating-filter' => false,
                    'shipping-free-filter' => false,
                    'property-filter' => false,
                    'property-whitelist' => [],
                ],
                [
                    'filters' => [
                        'value' => 'invalid-filter',
                    ],
                    'propertyWhitelist' => null,
                ],
            ],
            [
                [
                    'manufacturer-filter' => true,
                    'price-filter' => false,
                    'rating-filter' => true,
                    'shipping-free-filter' => false,
                    'property-filter' => false,
                    'property-whitelist' => [],
                ],
                [
                    'filters' => [
                        'value' => 'invalid-filter,manufacturer-filter,rating-filter',
                    ],
                    'propertyWhitelist' => null,
                ],
            ],
            [
                [
                    'manufacturer-filter' => true,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    'property-whitelist' => [],
                ],
                [
                    'filters' => [
                        'value' => 'manufacturer-filter,price-filter,rating-filter,property-filter,shipping-free-filter',
                    ],
                    'propertyWhitelist' => ['value' => []],
                ],
            ],
            [
                [
                    'manufacturer-filter' => false,
                    'price-filter' => true,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    'property-whitelist' => [],
                ],
                [
                    'filters' => [
                        'value' => 'price-filter,rating-filter,property-filter,shipping-free-filter',
                    ],
                    'propertyWhitelist' => ['value' => []],
                ],
            ],
            [
                [
                    'manufacturer-filter' => false,
                    'price-filter' => false,
                    'rating-filter' => true,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    'property-whitelist' => [],
                ],
                [
                    'filters' => [
                        'value' => 'rating-filter,property-filter,shipping-free-filter',
                    ],
                    'propertyWhitelist' => ['value' => []],
                ],
            ],
            [
                [
                    'manufacturer-filter' => false,
                    'price-filter' => false,
                    'rating-filter' => false,
                    'shipping-free-filter' => true,
                    'property-filter' => true,
                    'property-whitelist' => [],
                ],
                [
                    'filters' => [
                        'value' => 'property-filter,shipping-free-filter',
                    ],
                    'propertyWhitelist' => ['value' => []],
                ],
            ],
            [
                [
                    'manufacturer-filter' => false,
                    'price-filter' => false,
                    'rating-filter' => false,
                    'shipping-free-filter' => false,
                    'property-filter' => true,
                    'property-whitelist' => [],
                ],
                [
                    'filters' => [
                        'value' => 'property-filter',
                    ],
                    'propertyWhitelist' => ['value' => []],
                ],
            ],
            [
                [
                    'manufacturer-filter' => false,
                    'price-filter' => false,
                    'rating-filter' => false,
                    'shipping-free-filter' => false,
                    'property-filter' => false,
                    'property-whitelist' => [],
                ],
                [
                    'filters' => [
                        'value' => '',
                    ],
                    'propertyWhitelist' => ['value' => []],
                ],
            ],
            [
                [
                    'manufacturer-filter' => false,
                    'price-filter' => false,
                    'rating-filter' => false,
                    'shipping-free-filter' => false,
                    'property-filter' => false,
                    'property-whitelist' => [$sizeId, $textileId],
                ],
                [
                    'filters' => [
                        'value' => '',
                    ],
                    'propertyWhitelist' => ['value' => [$sizeId, $textileId]],
                ],
            ],
            [
                [
                    'manufacturer-filter' => false,
                    'price-filter' => false,
                    'rating-filter' => false,
                    'shipping-free-filter' => false,
                    'property-filter' => true,
                    'property-whitelist' => [],
                ],
                [
                    'filters' => [
                        'value' => 'property-filter',
                    ],
                    'propertyWhitelist' => ['value' => [$sizeId, $textileId]],
                ],
            ],
        ];
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    /**
     * @return array<string|int, mixed>
     */
    private function getProductSortings(): array
    {
        $sortings = $this->connection->fetchAllKeyValue('SELECT url_key, id FROM product_sorting;');

        $sortings = array_map(fn ($value) => Uuid::fromBytesToHex($value), $sortings);

        return $sortings;
    }
}
