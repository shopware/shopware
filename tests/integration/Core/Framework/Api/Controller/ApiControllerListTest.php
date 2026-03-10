<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerListTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testSimpleFilter(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'Wool Shirt',
            'tax' => ['name' => 'test', 'taxRate' => 10],
            'manufacturer' => ['name' => 'Shopware AG'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 8300, 'net' => 8300, 'linked' => false]],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/product', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/product/' . $id, $response->headers->get('Location'));

        $data = [
            'filter' => [
                'product.id' => $id,
                'product.price' => 8300,
                'product.name' => 'Wool Shirt',
            ],
        ];

        $this->getBrowser()->jsonRequest('GET', '/api/product', $data);
        $response = $this->getBrowser()->getResponse();
        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $content['meta']['total']);
        static::assertSame($id, $content['data'][0]['id']);
    }

    public function testJsonApiResponseMulti(): void
    {
        $insertData = [
            ['name' => 'test'],
            ['name' => 'test_2'],
        ];

        $this->getBrowser()->jsonRequest('POST', '/api/category', $insertData[0]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->jsonRequest('POST', '/api/category', $insertData[1]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $this->getBrowser()->jsonRequest('GET', '/api/category?sort=name');
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $respData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($respData);
        static::assertArrayHasKey('data', $respData);
        static::assertArrayHasKey('links', $respData);
        static::assertArrayHasKey('included', $respData);
        static::assertCount(3, $respData['data']);

        $data = $respData['data'];
        static::assertSame('category', $data[0]['type']);
        static::assertSame('Home', $data[0]['attributes']['name']);
        static::assertSame('Home', $data[0]['attributes']['translated']['name']);

        static::assertSame('category', $data[1]['type']);
        static::assertSame($insertData[0]['name'], $data[1]['attributes']['name']);
        static::assertSame($insertData[0]['name'], $data[1]['attributes']['translated']['name']);

        static::assertSame('category', $data[2]['type']);
        static::assertSame($insertData[1]['name'], $data[2]['attributes']['name']);
        static::assertSame($insertData[1]['name'], $data[2]['attributes']['translated']['name']);
    }
}
