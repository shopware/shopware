<?php declare(strict_types=1);

namespace Shopware\Rest\Test\Controller\Storefront;

use Ramsey\Uuid\Uuid;
use Shopware\Api\Product\Repository\ProductRepository;
use Shopware\CartBridge\Product\ProductProcessor;
use Shopware\Context\Struct\ShopContext;
use Shopware\Defaults;
use Shopware\Rest\Context\ApiStorefrontContextValueResolver;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

class CheckoutControllerTest extends ApiTestCase
{
    /**
     * @var ProductRepository
     */
    private $repository;

    /**
     * @var string
     */
    private $taxId;

    /**
     * @var string
     */
    private $manufacturerId;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->repository = self::$kernel->getContainer()->get(ProductRepository::class);
        $this->taxId = Uuid::uuid4()->toString();
        $this->manufacturerId = Uuid::uuid4()->toString();
    }

    public function testAddProduct()
    {
        $productId = Uuid::uuid4()->toString();
        $this->repository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'rate' => 17, 'name' => 'with id'],
            ],
        ], ShopContext::createDefaultContext());

        $client = $this->createCart();

        $this->addProduct($client, $productId);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('data', $content);
        $cart = $content['data'];

        $this->assertArrayHasKey('price', $cart);
        $this->assertEquals(10, $cart['price']['totalPrice']);
        $this->assertCount(1, $cart['calculatedLineItems']);

        $product = array_shift($cart['calculatedLineItems']);
        $this->assertEquals($productId, $product['identifier']);
    }

    public function testAddMultipleProducts()
    {
        $productId1 = Uuid::uuid4()->toString();
        $productId2 = Uuid::uuid4()->toString();

        $this->repository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'rate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => $productId2,
                'name' => 'Test 2',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 20, 'net' => 9],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
            ],
        ], ShopContext::createDefaultContext());

        $client = $this->createCart();

        $this->addProduct($client, $productId1);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId2);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $cart = $this->getCart($client);

        $this->assertNotEmpty($cart);
        $this->assertCount(2, $cart['calculatedLineItems']);
    }

    public function testChangeQuantity()
    {
        $productId1 = Uuid::uuid4()->toString();
        $productId2 = Uuid::uuid4()->toString();

        $this->repository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'rate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => $productId2,
                'name' => 'Test 2',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 20, 'net' => 9],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
            ],
        ], ShopContext::createDefaultContext());

        $client = $this->createCart();

        $this->addProduct($client, $productId1);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId2);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->changeQuantity($client, $productId1, 10);

        $cart = $this->getCart($client);

        $this->assertNotEmpty($cart);
        $this->assertCount(2, $cart['calculatedLineItems']);

        foreach ($cart['calculatedLineItems'] as $lineItem) {
            if ($lineItem['identifier'] === $productId1) {
                $this->assertEquals(10, $lineItem['quantity']);
            }
        }
    }

    public function testRemoveLineItem()
    {
        $productId1 = Uuid::uuid4()->toString();
        $productId2 = Uuid::uuid4()->toString();

        $this->repository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'rate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => $productId2,
                'name' => 'Test 2',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 20, 'net' => 9],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
            ],
        ], ShopContext::createDefaultContext());

        $client = $this->createCart();

        $this->addProduct($client, $productId1);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId2);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->removeLineItem($client, $productId1);

        $cart = $this->getCart($client);

        $this->assertNotEmpty($cart);
        $this->assertCount(1, $cart['calculatedLineItems']);

        $identifiers = array_column($cart['calculatedLineItems'], 'identifier');
        $this->assertNotContains($productId1, $identifiers);
    }

    public function testMergeSameProduct()
    {
        $productId1 = Uuid::uuid4()->toString();
        $productId2 = Uuid::uuid4()->toString();

        $this->repository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'rate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => $productId2,
                'name' => 'Test 2',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 20, 'net' => 9],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
            ],
        ], ShopContext::createDefaultContext());

        $client = $this->createCart();

        //add product 1 three times with quantity 1
        $this->addProduct($client, $productId1);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId1);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId1);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        //add product 2 one time with quantity 1 and one time with quantity 10
        $this->addProduct($client, $productId2);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId2, 10);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $cart = $this->getCart($client);

        $this->assertNotEmpty($cart);
        $this->assertCount(2, $cart['calculatedLineItems']);

        foreach ($cart['calculatedLineItems'] as $lineItem) {
            if ($lineItem['identifier'] === $productId1) {
                $this->assertEquals(3, $lineItem['quantity']);
            } else {
                $this->assertEquals(11, $lineItem['quantity']);
            }
        }
    }

    public function testUseAddProductRoute()
    {
        $productId = Uuid::uuid4()->toString();
        $this->repository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'rate' => 17, 'name' => 'with id'],
            ],
        ], ShopContext::createDefaultContext());

        $client = $this->createCart();

        $client->request('PUT', '/storefront-api/checkout/add-product/' . $productId);

        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('data', $content);
        $cart = $content['data'];

        $this->assertArrayHasKey('price', $cart);
        $this->assertEquals(10, $cart['price']['totalPrice']);
        $this->assertCount(1, $cart['calculatedLineItems']);

        $product = array_shift($cart['calculatedLineItems']);
        $this->assertEquals($productId, $product['identifier']);
    }

    public function createCart(): Client
    {
        $client = $this->getCartClient();
        $client->request('POST', '/storefront-api/checkout');

        $content = json_decode($client->getResponse()->getContent(), true);

        return $this->getCartClient(
            $content[ApiStorefrontContextValueResolver::CONTEXT_TOKEN_KEY]
        );
    }

    public function getCart(Client $client)
    {
        $client->request('GET', '/storefront-api/checkout');

        $cart = json_decode($client->getResponse()->getContent(), true);

        return $cart['data'];
    }

    public function getCartClient(?string $token = null)
    {
        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => ['application/json'],
        ];

        if ($token !== null) {
            $headers['HTTP_X_CONTEXT_TOKEN'] = $token;
        }

        return self::createClient(
            ['test_case' => 'ApiTest'],
            $headers
        );
    }

    private function addProduct(Client $client, string $id, int $quantity = 1)
    {
        $client->request(
            'PUT',
            '/storefront-api/checkout',
            [],
            [],
            [],
            json_encode([
                'type' => ProductProcessor::TYPE_PRODUCT,
                'identifier' => $id,
                'quantity' => $quantity,
                'payload' => ['id' => $id],
            ])
        );
    }

    private function changeQuantity(Client $client, string $productId, int $quantity): void
    {
        $client->request('PUT', '/storefront-api/checkout/' . $productId . '/' . $quantity);
    }

    private function removeLineItem(Client $client, string $productId): void
    {
        $client->request('DELETE', '/storefront-api/checkout/' . $productId);
    }
}
