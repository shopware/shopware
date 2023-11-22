<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Property\PropertyGroupCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductListingRoute::class)]
class ProductListingRouteTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param array<string, mixed> $product
     * @param array<string, mixed> $expected
     */
    #[DataProvider('filterAggregationsWithProducts')]
    public function testFilterAggregationsWithProducts(IdsCollection $ids, array $product, Request $request, array $expected): void
    {
        $parent = $this->getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM sales_channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL)]
        );

        $this->getContainer()->get('category.repository')
            ->create([['id' => $ids->get('category'), 'name' => 'test', 'parentId' => $parent]], Context::createDefaultContext());

        $categoryId = $product['categories'][0]['id'];

        $this->getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $listing = $this->getContainer()
            ->get(ProductListingRoute::class)
            ->load($categoryId, $request, $context, new Criteria())
            ->getResult();

        $aggregation = $listing->getAggregations()->get($expected['aggregation']);

        if ($expected['instanceOf'] === null) {
            static::assertNull($aggregation);
        } else {
            static::assertInstanceOf($expected['instanceOf'], $aggregation);
        }

        if ($expected['aggregation'] === 'properties' && isset($expected['propertyWhitelistIds'])) {
            static::assertInstanceOf(EntityResult::class, $aggregation);
            /** @var PropertyGroupCollection $properties */
            $properties = $aggregation->getEntities();

            static::assertSame($expected['propertyWhitelistIds'], $properties->getIds());
        }
    }

    /**
     * @return list<array{0: IdsCollection, 1: array<string, mixed>, 2: Request, 3: array<string, mixed>}>
     */
    public static function filterAggregationsWithProducts(): array
    {
        $ids = new TestDataCollection();

        $defaults = [
            'id' => $ids->get('product'),
            'name' => 'test-product',
            'productNumber' => $ids->get('product'),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
            'categories' => [
                ['id' => $ids->get('category')],
            ],
        ];

        return [
            // property-filter
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request(),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => EntityResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => true]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => EntityResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => false]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => null,
                ],
            ],

            // property-whitelist
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => false, 'property-whitelist' => null]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => null,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => false, 'property-whitelist' => null]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => null,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => false, 'property-whitelist' => [$ids->get('textile')]]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => EntityResult::class,
                    'propertyWhitelistIds' => [$ids->get('textile')],
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => false]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => null,
                ],
            ],

            // manufacturer-filter
            [
                $ids,
                $defaults,
                new Request(),
                [
                    'aggregation' => 'manufacturer',
                    'instanceOf' => EntityResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'manufacturer' => [
                        'id' => $ids->get('test-manufacturer'),
                        'name' => 'test-manufacturer',
                    ],
                ]),
                new Request([], ['manufacturer-filter' => true]),
                [
                    'aggregation' => 'manufacturer',
                    'instanceOf' => EntityResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'manufacturer' => [
                        'id' => $ids->get('test-manufacturer'),
                        'name' => 'test-manufacturer',
                    ],
                ]),
                new Request([], ['manufacturer-filter' => false]),
                [
                    'aggregation' => 'manufacturer',
                    'instanceOf' => null,
                ],
            ],

            // price-filter
            [
                $ids,
                $defaults,
                new Request(),
                [
                    'aggregation' => 'price',
                    'instanceOf' => StatsResult::class,
                ],
            ],
            [
                $ids,
                $defaults,
                new Request([], ['manufacturer-filter' => true]),
                [
                    'aggregation' => 'price',
                    'instanceOf' => StatsResult::class,
                ],
            ],
            [
                $ids,
                $defaults,
                new Request([], ['price-filter' => false]),
                [
                    'aggregation' => 'price',
                    'instanceOf' => null,
                ],
            ],

            // rating-filter
            [
                $ids,
                $defaults,
                new Request(),
                [
                    'aggregation' => 'rating',
                    'instanceOf' => MaxResult::class,
                ],
            ],
            [
                $ids,
                $defaults,
                new Request([], ['rating-filter' => true]),
                [
                    'aggregation' => 'rating',
                    'instanceOf' => MaxResult::class,
                ],
            ],
            [
                $ids,
                $defaults,
                new Request([], ['rating-filter' => false]),
                [
                    'aggregation' => 'rating',
                    'instanceOf' => null,
                ],
            ],

            // shipping-free-filter
            [
                $ids,
                array_merge($defaults, [
                    'shippingFree' => false,
                ]),
                new Request(),
                [
                    'aggregation' => 'shipping-free',
                    'instanceOf' => MaxResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'shippingFree' => true,
                ]),
                new Request([], ['shipping-free-filter' => true]),
                [
                    'aggregation' => 'shipping-free',
                    'instanceOf' => MaxResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'shippingFree' => true,
                ]),
                new Request([], ['shipping-free-filter' => false]),
                [
                    'aggregation' => 'shipping-free',
                    'instanceOf' => null,
                ],
            ],
        ];
    }
}
