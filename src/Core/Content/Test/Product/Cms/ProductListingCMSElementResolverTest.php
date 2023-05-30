<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Cms;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Shopware\Core\Content\Cms\SalesChannel\Struct\ProductListingStruct;
use Shopware\Core\Content\Product\Cms\ProductListingCmsElementResolver;
use Shopware\Core\Content\Product\SalesChannel\Exception\ProductSortingNotFoundException;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingFeaturesSubscriber;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductListingCMSElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ProductListingCmsElementResolver
     */
    private $productListingCMSElementResolver;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productListingCMSElementResolver = $this->getContainer()->get(ProductListingCmsElementResolver::class);
        $this->salesChannelContext = $this->createSalesChannelContext();
    }

    public function testSortings(): void
    {
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

        /** @var ProductListingStruct $data */
        $data = $slot->getData();

        /** @var ProductListingResult $listing */
        $listing = $data->getListing();

        static::assertEquals('name-asc', $listing->getSorting());

        if ($availableSortings) {
            foreach ($listing->getAvailableSortings() as $availableSorting) {
                static::assertArrayHasKey($availableSorting->getKey(), $availableSortings);
            }
        }
    }

    public function testUnavailableSortingThrowsException(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    'price-desc' => 1,
                    'name-asc' => 0,
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

        static::expectException(ProductSortingNotFoundException::class);

        $this->productListingCMSElementResolver->enrich($slot, $resolverContext, $result);
    }

    public function testOnlyRestrictedSortingsAreAvailable(): void
    {
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

        /** @var ProductListingStruct $data */
        $data = $slot->getData();

        /** @var ProductListingResult $listing */
        $listing = $data->getListing();

        $actualSortings = $listing->getAvailableSortings()->map(fn (ProductSortingEntity $actualSorting) => $actualSorting->getKey());

        $availableSortings = array_keys($availableSortings);

        sort($actualSortings);
        sort($availableSortings);

        static::assertEquals($availableSortings, $actualSortings);
    }

    public function testAvailableSortingsPriority(): void
    {
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

        /** @var ProductListingStruct $data */
        $data = $slot->getData();

        /** @var ProductListingResult $listing */
        $listing = $data->getListing();

        $actualSortings = $listing->getAvailableSortings()->map(fn (ProductSortingEntity $actualSorting) => $actualSorting->getKey());

        $actualSortings = array_values($actualSortings);

        arsort($availableSortings, \SORT_DESC | \SORT_NUMERIC);
        $availableSortings = array_keys($availableSortings);

        static::assertEquals($availableSortings, $actualSortings);
    }

    public function testHighestPrioritySortingIsDefaultSorting(): void
    {
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

        /** @var ProductListingStruct $data */
        $data = $slot->getData();

        /** @var ProductListingResult $listing */
        $listing = $data->getListing();

        $sorting = $listing->getSorting();

        static::assertEquals($sorting, 'price-asc');
    }

    /**
     * @dataProvider filtersProvider
     */
    public function testFiltersAndPropertyWhitelist($expectations, $slotConfig): void
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
            if ($field === ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM) {
                $value = $request->request->all($field);
            } else {
                $value = $request->request->get($field, true);
            }

            static::assertSame($expected, $value);
        }
    }

    public static function filtersProvider()
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
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [],
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
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [],
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
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [],
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
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [],
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
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [],
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
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [],
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
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [],
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
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [],
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
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [],
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
                    ProductListingFeaturesSubscriber::PROPERTY_GROUP_IDS_REQUEST_PARAM => [$sizeId, $textileId],
                ],
                [
                    'filters' => [
                        'value' => '',
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
}
