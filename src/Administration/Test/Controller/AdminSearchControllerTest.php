<?php declare(strict_types=1);

namespace Shopware\Administration\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 *
 * @group slow
 */
class AdminSearchControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminApiTestBehaviour;
    use AdminFunctionalTestBehaviour;

    protected function setup(): void
    {
        $roles = ['product:read', 'product_manufacturer:read', 'user:read'];

        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], $roles);

        $this->prepareTestData();
    }

    /**
     * @dataProvider searchDataProvider
     */
    public function testSearch(array $data, bool $hasResponse, array $expectedEntities, array $expectedErrors = []): void
    {
        $this->getBrowser()->request('POST', '/api/_admin/search', [], [], [], json_encode($data, \JSON_THROW_ON_ERROR) ?: null);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content, print_r($content, true));

        if (!$hasResponse) {
            static::assertEmpty($content['data']);

            return;
        }

        static::assertNotEmpty($content['data']);

        $data = $content['data'];

        foreach ($expectedErrors as $entity => $expectedErrorDetail) {
            static::assertArrayHasKey($entity, $data);

            $actual = $data[$entity];

            unset($data[$entity]);

            static::assertEquals($expectedErrorDetail, $actual['detail']);
        }

        static::assertEquals(\count($expectedEntities), is_countable($data) ? \count($data) : 0);

        foreach ($expectedEntities as $entity => $expectedTotal) {
            static::assertArrayHasKey($entity, $data);

            $actual = $data[$entity];

            static::assertEquals($expectedTotal, $actual['total']);
        }
    }

    public function testSearchResultWithoutApiAwareField(): void
    {
        $this->getBrowser()->request('POST', '/api/_admin/search', [], [], [], json_encode([
            'user' => [
                'query' => [],
            ],
        ]) ?: null);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('data', $content, print_r($content, true));

        static::assertNotEmpty($content['data']['user']['data']);

        $user = array_values($content['data']['user']['data'])[0];

        static::assertEquals('user', $user['apiAlias']);
        static::assertArrayNotHasKey('password', $user);
    }

    public static function searchDataProvider(): iterable
    {
        $hasResponse = true;

        return [
            'basic test with query' => [
                [
                    'product' => [
                        'query' => [
                            [
                                'score' => 2500,
                                'query' => [
                                    'type' => 'contains',
                                    'field' => 'name',
                                    'value' => 'Fancy Product ABC',
                                ],
                            ],
                        ],
                    ],
                ], $hasResponse, ['product' => 1],
            ],
            'basic test with term' => [
                [
                    'product' => [
                        'term' => 'ABC123',
                    ],
                ], $hasResponse, ['product' => 1],
            ],
            'empty payload' => [
                [
                ], false, [],
            ],
            'empty result' => [
                [
                    'product' => [
                        'term' => 'TheTermThatNotHappens',
                    ],
                ], $hasResponse, ['product' => 0],
            ],
            'not found entity payload' => [
                [
                    'not-found-entity' => [
                        'term' => 'ABC',
                    ],
                ], false, [],
            ],
            'duplicate entities in payload (the later one will be used)' => [
                json_decode('{"product":{"term":"ABC"},"product":{"term":"XYZ"}}', true), $hasResponse, ['product' => 0],
            ],
            'multiple entities in payload' => [
                [
                    'product' => [
                        'query' => [
                            [
                                'score' => 2500,
                                'query' => [
                                    'type' => 'contains',
                                    'field' => 'name',
                                    'value' => 'Fancy',
                                ],
                            ],
                        ],
                    ],
                    'product_manufacturer' => [
                        'term' => 'Cheapest ever 2nd',
                    ],
                ], $hasResponse, ['product' => 2, 'product_manufacturer' => 1],
            ],
            'non-granted privilege entities in payload' => [
                [
                    'product' => [
                        'query' => [
                            [
                                'score' => 2500,
                                'query' => [
                                    'type' => 'contains',
                                    'field' => 'name',
                                    'value' => 'Fancy',
                                ],
                            ],
                        ],
                    ],
                    'customer' => [
                        'term' => 'John doe',
                    ],
                ], $hasResponse, ['product' => 2], [
                    'customer' => '{"message":"Missing privilege","missingPrivileges":["customer:read"]}',
                ],
            ],
            'Only non-granted privilege entities in payload' => [
                [
                    'category' => [
                        'query' => [
                            [
                                'score' => 2500,
                                'query' => [
                                    'type' => 'contains',
                                    'field' => 'name',
                                    'value' => 'Fancy',
                                ],
                            ],
                        ],
                    ],
                    'customer' => [
                        'term' => 'John doe',
                    ],
                ], $hasResponse, [], [
                    'category' => '{"message":"Missing privilege","missingPrivileges":["category:read"]}',
                    'customer' => '{"message":"Missing privilege","missingPrivileges":["customer:read"]}',
                ],
            ],
            'search with association field' => [
                [
                    'product' => [
                        'query' => [
                            [
                                'score' => 2500,
                                'query' => [
                                    'type' => 'contains',
                                    'field' => 'manufacturer.name',
                                    'value' => 'Manufacturer',
                                ],
                            ],
                        ],
                    ],
                ], $hasResponse, ['product' => 4],
            ],
            'search with non-granted priviledge association field' => [
                [
                    'product' => [
                        'query' => [
                            [
                                'score' => 2500,
                                'query' => [
                                    'type' => 'contains',
                                    'field' => 'categories.name',
                                    'value' => 'test',
                                ],
                            ],
                        ],
                    ],
                    'product_manufacturer' => [
                        'term' => 'Cheapest ever 2nd',
                    ],
                ], $hasResponse, ['product_manufacturer' => 1], ['product' => '{"message":"Missing privilege","missingPrivileges":["category:read"]}'],
            ],
            'load association with granted privilege entities' => [
                [
                    'product' => [
                        'query' => [
                            [
                                'score' => 2500,
                                'query' => [
                                    'type' => 'contains',
                                    'field' => 'manufacturer.name',
                                    'value' => 'Manufacturer',
                                ],
                            ],
                        ],
                        'associations' => [
                            'manufacturer' => ['total-count-mode' => 1],
                        ],
                    ],
                ], $hasResponse, ['product' => 4],
            ],
            'load association with non-granted privilege entities' => [
                [
                    'product' => [
                        'query' => [
                            [
                                'score' => 2500,
                                'query' => [
                                    'type' => 'contains',
                                    'field' => 'manufacturer.name',
                                    'value' => 'Manufacturer',
                                ],
                            ],
                        ],
                        'associations' => [
                            'categories' => ['total-count-mode' => 1],
                        ],
                    ],
                ], $hasResponse, [], ['product' => '{"message":"Missing privilege","missingPrivileges":["category:read"]}'],
            ],
        ];
    }

    private function prepareTestData(): void
    {
        $fancyManufacturer = Uuid::randomHex();
        $cheapestManufacturer = Uuid::randomHex();

        $productRepository = $this->getContainer()->get('product.repository');
        $manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');

        $manufacturerRepository->upsert([
            ['id' => $fancyManufacturer, 'name' => 'Fancy Manufacturer'],
            ['id' => $cheapestManufacturer, 'name' => 'Cheapest Manufacturer'],
        ], Context::createDefaultContext());

        $productRepository->upsert([
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'categories' => [['name' => 'test']], 'stock' => 1, 'name' => 'Fancy Product ABC123', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['id' => $fancyManufacturer]],
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'categories' => [['name' => 'test']], 'stock' => 1, 'name' => 'Fancy Product DEF456', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['id' => $fancyManufacturer]],
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'categories' => [['name' => 'test']], 'stock' => 2, 'name' => 'Cheapest ever 1st', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['id' => $cheapestManufacturer]],
            ['id' => Uuid::randomHex(), 'productNumber' => Uuid::randomHex(), 'categories' => [['name' => 'test']], 'stock' => 2, 'name' => 'Cheapest ever 2nd', 'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]], 'tax' => ['name' => 'test', 'taxRate' => 5], 'manufacturer' => ['id' => $cheapestManufacturer]],
        ], Context::createDefaultContext());
    }
}
