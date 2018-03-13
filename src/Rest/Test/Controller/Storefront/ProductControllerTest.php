<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller\Storefront;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\Context\Struct\ShopContext;
use Shopware\Rest\Test\ApiTestCase;

class ProductControllerTest extends ApiTestCase
{
    /**
     * @var ProductRepository
     */
    private $repository;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->repository = self::$kernel->getContainer()->get(ProductRepository::class);
    }

    public function testProductList()
    {
        $manufacturerId = Uuid::uuid4()->toString();
        $taxId = Uuid::uuid4()->toString();

        $this->repository->create([
            [
                'id' => Uuid::uuid4()->toString(),
                'name' => 'Test',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'rate' => 17, 'name' => 'with id'],
            ],
        ], ShopContext::createDefaultContext());

        $client = self::createClient(
            ['test_case' => 'ApiTest'],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => ['application/json'],
            ]
        );

        $client->request('GET', '/storefront-api/product/');

        self::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('total', $content);
        $this->assertArrayHasKey('data', $content);
        $this->assertGreaterThan(0, $content['total']);
        $this->assertNotEmpty($content['data']);

        foreach ($content['data'] as $product) {
            $this->assertArrayHasKey('calculatedListingPrice', $product);
            $this->assertArrayHasKey('calculatedContextPrices', $product);
            $this->assertArrayHasKey('calculatedPrice', $product);
            $this->assertArrayHasKey('price', $product);
            $this->assertArrayHasKey('name', $product);
            $this->assertArrayHasKey('id', $product);
        }
    }
}
