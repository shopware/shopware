<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerSearchTest extends TestCase
{
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testSearchTerm(): void
    {
        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => 'SW-API-14999',
            'stock' => 1,
            'name' => 'asdf',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/product', $product);

        $data = [
            'page' => 1,
            'limit' => 5,
            'sort' => [
                [
                    'field' => 'productNumber',
                    'order' => 'desc',
                ],
            ],
            'term' => 'SW-API-14999',
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/search/product', $data);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('meta', $content, print_r($content, true));
        static::assertSame(1, $content['meta']['total']);
        static::assertSame($id, $content['data'][0]['id']);
    }

    public function testSearchNonTokenizeTerm(): void
    {
        // Create two customers with different email but same suffix example.com
        $this->createCustomer();
        $ids = $this->createCustomer();

        $data = [
            'page' => 1,
            'limit' => 5,
            'sort' => [
                [
                    'field' => 'customerNumber',
                    'order' => 'desc',
                ],
            ],
            'term' => $ids->get('email') . '@example.com',
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/search/customer', $data);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('meta', $content, print_r($content, true));
        static::assertSame(1, $content['meta']['total']);
        static::assertSame($ids->get('customer'), $content['data'][0]['id']);

        $data['term'] = 'example.com';

        $this->getBrowser()->jsonRequest('POST', '/api/search/customer', $data);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('meta', $content, print_r($content, true));
        static::assertSame(2, $content['meta']['total']);
    }

    public function testAggregationWorksForAdminStartPage(): void
    {
        $data = [
            'page' => 1,
            'limit' => 10,
            'filter' => [
                [
                    'type' => 'range',
                    'field' => 'orderDate',
                    'parameters' => [
                        'gte' => '2020-05-16',
                    ],
                ],
            ],
            'aggregations' => [
                [
                    'type' => 'histogram',
                    'name' => 'order_count_month',
                    'field' => 'orderDateTime',
                    'interval' => 'day',
                    'format' => null,
                    'aggregation' => [
                        'type' => 'sum',
                        'name' => 'totalAmount',
                        'field' => 'amountTotal',
                    ],
                ],
            ],
            'total-count-mode' => 1,
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/search/order', $data);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());

        $response = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('aggregations', $response);
        static::assertArrayHasKey('order_count_month', $response['aggregations']);
    }

    public function testSearch(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Cotton Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 25, 'linked' => false]],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'page' => 1,
            'limit' => 5,
            'total-count-mode' => Criteria::TOTAL_COUNT_MODE_EXACT,
            'sort' => [
                [
                    'field' => 'product.stock',
                    'order' => 'desc',
                ],
                [
                    'field' => 'product.name',
                    'order' => 'desc',
                ],
            ],
            'filter' => [
                [
                    'type' => 'multi',
                    'queries' => [
                        [
                            'type' => 'range',
                            'field' => 'product.price',
                            'parameters' => [
                                'gt' => 49,
                                'lte' => 50,
                            ],
                        ],
                        [
                            'type' => 'equals',
                            'field' => 'product.manufacturer.name',
                            'value' => 'Shopware AG',
                        ],
                        [
                            'type' => 'equalsAny',
                            'field' => 'product.id',
                            'value' => $id,
                        ],
                    ],
                ],
            ],
            'query' => [
                [
                    'type' => 'score',
                    'query' => [
                        'type' => 'contains',
                        'field' => 'product.name',
                        'value' => 'Cotton',
                    ],
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/search/product', $data);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('meta', $content, print_r($content, true));
        static::assertSame(1, $content['meta']['total']);
        static::assertSame($id, $content['data'][0]['id']);
    }

    public function testSearchWithoutPermission(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'Cotton Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'stock' => 12,
            'productNumber' => '1',
        ];

        $browser = $this->getBrowser();

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['product:create', 'tax:create', 'product_manufacturer:create', 'price:create', 'version_commit_data:create', 'version_commit:create']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/product', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'page' => 1,
            'limit' => 5,
        ];

        $browser->jsonRequest('POST', '/api/search/product', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
    }

    /**
     * Tests the API search endpoint. Asserts that an entity can be both part of the result data as well as the
     * associations when the entity is fetched as a top level entity result and through circular associations.
     */
    public function testEntityIsPresentInTopLevelEntityResultWhenAlsoPartOfAssociations(): void
    {
        // In this test case both products are created with the same base data (i.e. they are part of the same sales
        // channel).
        $productBase = [
            'name' => 'Some product',
            'stock' => 1,
            'tax' => [
                'name' => 'test',
                'taxRate' => 10,
            ],
            'manufacturer' => [
                'name' => 'Shopware AG',
            ],
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 50,
                    'net' => 25,
                    'linked' => false,
                ],
            ],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];

        $product1 = array_merge($productBase, [
            'id' => Uuid::randomHex(),
            'productNumber' => 'product-1',
        ]);
        $this->getBrowser()->jsonRequest('POST', '/api/product', $product1);

        $product2 = array_merge($productBase, [
            'id' => Uuid::randomHex(),
            'productNumber' => 'product-2',
        ]);
        $this->getBrowser()->jsonRequest('POST', '/api/product', $product2);

        // Add associations so that the products are both part of the top level entity result as well as the
        // associations through the circular association chain.
        $data = [
            'page' => 1,
            'limit' => 25,
            'associations' => [
                'visibilities' => [
                    'associations' => [
                        'salesChannel' => [
                            'associations' => [
                                'productVisibilities' => [
                                    'associations' => [
                                        'product' => [],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/search/product', $data);
        $response = $this->getBrowser()->getResponse();
        $searchResult = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(2, $searchResult['data']);
    }

    public function testNestedSearchOnOneToMany(): void
    {
        $id = Uuid::randomHex();

        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $ruleA,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $ruleB,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        static::getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/product/' . $id . '/prices';
        $this->getBrowser()->jsonRequest('GET', $path);
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertIsArray($responseData);
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(2, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'product_price.ruleId',
                    'value' => $ruleA,
                ],
            ],
        ];

        $path = '/api/search/product/' . $id . '/prices';
        $this->getBrowser()->jsonRequest('POST', $path, $filter);
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertIsArray($responseData);
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(1, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);
    }

    public function testNestedSearchOnOneToManyWithoutPermissionOnParent(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'states' => [
                [
                    'name' => 'test_state',
                    'shortCode' => 'test',
                ],
                [
                    'name' => 'test_state_2',
                    'shortCode' => 'test 2',
                ],
            ],
        ];

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/country', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['country_state:list']
        )->authorizeBrowser($browser);

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'country_state.name',
                    'value' => 'test_state',
                ],
            ],
        ];

        $path = '/api/search/country/' . $id . '/states';
        $browser->jsonRequest('POST', $path, $filter);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testNestedSearchOnOneToManyWithoutPermissionOnChild(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => $id,
            'states' => [
                [
                    'name' => 'test_state',
                    'shortCode' => 'test',
                ],
                [
                    'name' => 'test_state_2',
                    'shortCode' => 'test 2',
                ],
            ],
        ];

        $browser = $this->getBrowser();
        $browser->jsonRequest('POST', '/api/country', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/country/' . $id, $response->headers->get('Location'));

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['country:list']
        )->authorizeBrowser($browser);

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'country_state.name',
                    'value' => 'test_state',
                ],
            ],
        ];

        $path = '/api/search/country/' . $id . '/states';
        $browser->jsonRequest('POST', $path, $filter);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());
    }

    public function testNestedSearchOnOneToManyWithAggregation(): void
    {
        $id = Uuid::randomHex();

        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $ruleA,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $ruleB,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        static::getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/product/' . $id . '/prices';
        $this->getBrowser()->jsonRequest('GET', $path);
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertIsArray($responseData);
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(2, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);

        $filter = [
            'aggregations' => [
                [
                    'name' => 'price_stats',
                    'type' => 'stats',
                    'field' => 'product_price.price',
                ],
            ],
        ];

        $path = '/api/search/product/' . $id . '/prices';
        $this->getBrowser()->jsonRequest('POST', $path, $filter);
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertArrayHasKey('aggregations', $responseData);
        static::assertArrayHasKey('price_stats', $responseData['aggregations']);
    }

    public function testSearchOnManyToMany(): void
    {
        $id = Uuid::randomHex();
        $categoryA = Uuid::randomHex();
        $categoryB = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryA, 'name' => 'A'],
                ['id' => $categoryB, 'name' => 'B'],
            ],
        ];

        static::getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $path = '/api/product/' . $id . '/categories';
        $this->getBrowser()->jsonRequest('GET', $path);
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));

        static::assertIsArray($responseData);
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(2, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'category.name',
                    'value' => 'A',
                ],
            ],
        ];

        $path = '/api/search/product/' . $id . '/categories';
        $this->getBrowser()->jsonRequest('POST', $path, $filter);
        $responseData = json_decode((string) $this->getBrowser()->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), print_r($responseData, true));
        static::assertIsArray($responseData);
        static::assertArrayHasKey('meta', $responseData);
        static::assertArrayHasKey('total', $responseData['meta']);
        static::assertSame(1, $responseData['meta']['total']);
        static::assertArrayHasKey('data', $responseData);
    }

    public function testNestedSearchOnManyToManyWithoutPermissionOnParent(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 12,
            'productNumber' => '1',
            'categories' => [
                ['name' => 'category 1'],
                ['name' => 'category 2'],
            ],
        ];

        static::getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'category.name',
                    'value' => 'category 1',
                ],
            ],
        ];

        $path = '/api/search/product/' . $id . '/categories';
        $browser = $this->getBrowser();

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['category:list']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('POST', $path, $filter);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public function testNestedSearchOnManyToManyWithoutPermissionOnChild(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'price test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'stock' => 12,
            'productNumber' => '1',
            'categories' => [
                ['name' => 'category 1'],
                ['name' => 'category 2'],
            ],
        ];

        static::getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $filter = [
            'filter' => [
                [
                    'type' => 'equals',
                    'field' => 'category.name',
                    'value' => 'category 1',
                ],
            ],
        ];

        $path = '/api/search/product/' . $id . '/categories';
        $browser = $this->getBrowser();

        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['product:list']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('POST', $path, $filter);
        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
    }

    public function testAggregation(): void
    {
        $manufacturerName = Uuid::randomHex();

        $productA = Uuid::randomHex();
        $data = [
            'id' => $productA,
            'productNumber' => Uuid::randomHex(),
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => $manufacturerName],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 8300, 'net' => 8300, 'linked' => false]],
            'stock' => 50,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        $productB = Uuid::randomHex();
        $data = [
            'id' => $productB,
            'productNumber' => Uuid::randomHex(),
            'name' => 'Wool Shirt 2',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => $manufacturerName],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 8300, 'net' => 8300, 'linked' => false]],
            'stock' => 100,
        ];
        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());

        $data = [
            'aggregations' => [
                ['name' => 'product_count', 'type' => 'count', 'field' => 'product.id'],
                ['name' => 'product_stats', 'type' => 'stats', 'field' => 'product.stock'],
            ],
            'filter' => [
                [
                    'type' => 'multi',
                    'queries' => [
                        [
                            'type' => 'equals',
                            'field' => 'product.manufacturer.name',
                            'value' => $manufacturerName,
                        ],
                    ],
                ],
            ],
        ];

        $this->getBrowser()->setServerParameter('HTTP_ACCEPT', 'application/json');
        $this->getBrowser()->jsonRequest('POST', '/api/search/product', $data);
        $response = $this->getBrowser()->getResponse();

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), print_r((string) $response->getContent(), true));
        static::assertNotEmpty($content);

        static::assertArrayHasKey('aggregations', $content);
        $aggregations = $content['aggregations'];

        static::assertArrayHasKey('product_count', $aggregations, print_r($aggregations, true));
        $productCount = $aggregations['product_count'];
        static::assertSame(2, $productCount['count']);

        static::assertArrayHasKey('product_stats', $aggregations);
        $productStats = $aggregations['product_stats'];
        static::assertSame(75, $productStats['avg']);
        static::assertSame(150, $productStats['sum']);
        static::assertSame('50', $productStats['min']);
        static::assertSame('100', $productStats['max']);
    }

    public function testAccessDeniedAfterChangingUserPassword(): void
    {
        $browser = $this->getBrowser();

        $connection = $browser->getContainer()->get(Connection::class);
        $admin = TestUser::createNewTestUser($connection, ['product:read']);

        $admin->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/search/product');
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $userRepository = static::getContainer()->get('user.repository');

        // Change user password
        $userRepository->update([[
            'id' => $admin->getUserId(),
            'password' => Uuid::randomHex(),
        ]], Context::createDefaultContext());

        $browser->jsonRequest('POST', '/api/search/product');
        $response = $browser->getResponse();

        static::assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode(), (string) $response->getContent());
        $jsonResponse = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('Access token is expired', $jsonResponse['errors'][0]['detail']);
    }

    private function createCustomer(): IdsCollection
    {
        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('customer'),
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => $ids->get('email') . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $ids->get('address'),
            'defaultShippingAddressId' => $ids->get('address'),
            'addresses' => [
                [
                    'id' => $ids->get('address'),
                    'customerId' => $ids->get('customer'),
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()->get('customer.repository')
            ->create([$data], Context::createDefaultContext());

        return $ids;
    }
}
