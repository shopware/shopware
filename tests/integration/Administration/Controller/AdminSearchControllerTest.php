<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Group('slow')]
class AdminSearchControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    protected function setup(): void
    {
        $roles = ['product:read', 'product_manufacturer:read', 'user:read'];

        $this->authorizeBrowser($this->getBrowser(), [UserVerifiedScope::IDENTIFIER], $roles);

        $this->prepareTestData();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, int> $expectedEntities
     * @param array<string, string> $expectedErrors
     */
    #[DataProvider('searchDataProvider')]
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

            static::assertSame($expectedErrorDetail, $actual['detail']);
        }

        static::assertSame(\count($expectedEntities), is_countable($data) ? \count($data) : 0);

        foreach ($expectedEntities as $entity => $expectedTotal) {
            static::assertArrayHasKey($entity, $data);

            $actual = $data[$entity];

            static::assertSame($expectedTotal, $actual['total']);
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

        static::assertSame('user', $user['apiAlias']);
        static::assertArrayNotHasKey('password', $user);
    }

    /**
     * @return iterable<array{0: array<string, mixed>, 1: bool, 2: array<string, int>, 3?: array<string, string>}>
     */
    public static function searchDataProvider(): iterable
    {
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
                ], true, ['product' => 1],
            ],
            'basic test with term' => [
                [
                    'product' => [
                        'term' => 'ABC123',
                    ],
                ], true, ['product' => 1],
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
                ], true, ['product' => 0],
            ],
            'not found entity payload' => [
                [
                    'not-found-entity' => [
                        'term' => 'ABC',
                    ],
                ], false, [],
            ],
            'duplicate entities in payload (the later one will be used)' => [
                json_decode('{"product":{"term":"ABC"},"product":{"term":"XYZ"}}', true), true, ['product' => 0],
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
                ], true, ['product' => 2, 'product_manufacturer' => 1],
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
                ], true, ['product' => 2], [
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
                ], true, [], [
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
                ], true, ['product' => 4],
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
                ], true, ['product_manufacturer' => 1], ['product' => '{"message":"Missing privilege","missingPrivileges":["category:read"]}'],
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
                ], true, ['product' => 4],
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
                ], true, [], ['product' => '{"message":"Missing privilege","missingPrivileges":["category:read"]}'],
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
