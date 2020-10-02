<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.4.0 - Will be removed in 6.4.0
 */
class ProductApiConverterTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testPurchasePriceConverter(): void
    {
        $ids = new TestDataCollection(Context::createDefaultContext());

        $data = [
            'id' => $ids->create('product'),
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'purchasePrice' => 10.4,
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $this->getBrowser()->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/search/product');

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $products = json_decode($response->getContent(), true);

        $product = $products['data'][0];

        static::assertArrayHasKey('purchasePrices', $product['attributes']);
        static::assertEquals(10.4, $product['attributes']['purchasePrices'][0]['gross']);
    }
}
