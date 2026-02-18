<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class ApiControllerAggregateTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testAggregate(): void
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
            'aggregations' => [
                [
                    'name' => 'total',
                    'field' => 'id',
                    'type' => 'count',
                ],
            ],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/aggregate/product', $data);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // data is empty as we only do aggregations
        static::assertEmpty($content['data']);
        static::assertArrayHasKey('aggregations', $content);
        static::assertSame(1, $content['aggregations']['total']['count']);
    }
}
