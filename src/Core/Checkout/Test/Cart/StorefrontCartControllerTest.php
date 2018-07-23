<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Content\Product\Cart\ProductProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Client;

class StorefrontCartControllerTest extends ApiTestCase
{
    /**
     * @var RepositoryInterface
     */
    private $productRepository;

    /**
     * @var RepositoryInterface
     */
    private $customerRepository;

    /**
     * @var string
     */
    private $taxId;

    /**
     * @var string
     */
    private $manufacturerId;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        parent::setUp();

        $this->storefrontApiClient->setServerParameter('CONTENT_TYPE', 'application/json');

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->taxId = Uuid::uuid4()->getHex();
        $this->manufacturerId = Uuid::uuid4()->getHex();
    }

    public function testAddNonExistingProduct()
    {
        $productId = Uuid::uuid4()->getHex();

        $client = $this->createCart();

        $this->addProduct($client, $productId);

        $content = json_decode($client->getResponse()->getContent(), true);

        $this->assertNotEmpty($content);
        $this->assertArrayHasKey('data', $content);
        $cart = $content['data'];

        $this->assertArrayHasKey('price', $cart);
        $this->assertEquals(0, $cart['price']['totalPrice']);
        $this->assertCount(0, $cart['calculatedLineItems']);
    }

    public function testAddProduct()
    {
        $productId = Uuid::uuid4()->getHex();
        $this->productRepository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], Context:: createDefaultContext(Defaults::TENANT_ID));

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
        $productId1 = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();

        $this->productRepository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => $productId2,
                'name' => 'Test 2',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 20, 'net' => 9],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
            ],
        ], Context:: createDefaultContext(Defaults::TENANT_ID));

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
        $productId1 = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();

        $this->productRepository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => $productId2,
                'name' => 'Test 2',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 20, 'net' => 9],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
            ],
        ], Context:: createDefaultContext(Defaults::TENANT_ID));

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

    public function testUpdateLineItem()
    {
        $productId1 = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();

        $this->productRepository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => $productId2,
                'name' => 'Test 2',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 20, 'net' => 9],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
            ],
        ], Context:: createDefaultContext(Defaults::TENANT_ID));

        $client = $this->createCart();

        $this->addProduct($client, $productId1);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId2);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->updateLineItem($client, $productId1, 10);

        $cart = $this->getCart($client);

        $this->assertNotEmpty($cart);
        $this->assertCount(2, $cart['calculatedLineItems']);

        foreach ($cart['calculatedLineItems'] as $lineItem) {
            if ($lineItem['identifier'] === $productId1) {
                $this->assertEquals(10, $lineItem['quantity']);
            }
        }
    }

    public function testChangeWithInvalidQuantity()
    {
        $productId1 = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();

        $this->productRepository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => $productId2,
                'name' => 'Test 2',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 20, 'net' => 9],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
            ],
        ], Context:: createDefaultContext(Defaults::TENANT_ID));

        $client = $this->createCart();

        $this->addProduct($client, $productId1);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId2);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->changeQuantity($client, $productId1, -1);

        $cart = $this->getCart($client);

        $this->assertNotEmpty($cart);
        $this->assertArrayHasKey('errors', $cart);
        $this->assertEquals('CART-INVALID-QUANTITY', $cart['errors'][0]['code']);
    }

    public function testRemoveLineItem()
    {
        $productId1 = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();

        $this->productRepository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => $productId2,
                'name' => 'Test 2',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 20, 'net' => 9],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
            ],
        ], Context:: createDefaultContext(Defaults::TENANT_ID));

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

    public function testRemoveNonExistingLineItem()
    {
        $productId1 = Uuid::uuid4()->getHex();
        $client = $this->createCart();

        $this->removeLineItem($client, $productId1);

        $cart = $this->getCart($client);

        $this->assertNotEmpty($cart);
        $this->assertArrayHasKey('errors', $cart);

        $this->assertTrue(array_key_exists('CART-LINE-ITEM-NOT-FOUND', array_flip(array_column($cart['errors'], 'code'))));
    }

    public function testMergeSameProduct()
    {
        $productId1 = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();

        $this->productRepository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
            [
                'id' => $productId2,
                'name' => 'Test 2',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 20, 'net' => 9],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
            ],
        ], Context:: createDefaultContext(Defaults::TENANT_ID));

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

    public function testAddProductUsingGenericLineItemRoute()
    {
        $productId = Uuid::uuid4()->getHex();
        $this->productRepository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], Context:: createDefaultContext(Defaults::TENANT_ID));

        $client = $this->createCart();

        $client->request('POST', '/storefront-api/checkout/cart/line-item/' . $productId, [], [], [], json_encode(['type' => ProductProcessor::TYPE_PRODUCT]));

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

    public function testOrderProcess()
    {
        $productId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID);

        $this->productRepository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $context);

        $addressId = Uuid::uuid4()->getHex();

        $mail = Uuid::uuid4()->getHex();
        $password = 'shopware';

        $this->connection->executeUpdate('DELETE FROM customer WHERE email = :mail', [
            'mail' => $mail,
        ]);

        $this->customerRepository->create([
            [
                'touchpointId' => $context->getSourceContext()->getTouchpointId(),
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'not',
                    'lastName' => 'not',
                    'street' => 'test',
                    'city' => 'not',
                    'zipcode' => 'not',
                    'salutation' => 'not',
                    'country' => ['name' => 'not'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'test',
                    'additionalDescription' => 'test',
                    'technicalName' => Uuid::uuid4()->getHex(),
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $mail,
                'password' => $password,
                'lastName' => 'not',
                'firstName' => 'match',
                'salutation' => 'not',
                'number' => 'not',
            ],
        ], $context);

        $client = $this->createCart();

        $this->addProduct($client, $productId);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->login($client, $mail, $password);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->order($client);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $order = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('data', $order);

        $order = $order['data'];
        $this->assertNotEmpty($order);

        $this->assertEquals($mail, $order['customer']['email']);
    }

    public function testOrderProcessWithEmptyCart()
    {
        $addressId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext(\Shopware\Core\Defaults::TENANT_ID);

        $mail = Uuid::uuid4()->getHex();
        $password = 'shopware';

        $this->connection->executeUpdate('DELETE FROM customer WHERE email = :mail', [
            'mail' => $mail,
        ]);

        $this->customerRepository->create([
            [
                'touchpointId' => $context->getSourceContext()->getTouchpointId(),
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'not',
                    'lastName' => 'not',
                    'street' => 'test',
                    'city' => 'not',
                    'zipcode' => 'not',
                    'salutation' => 'not',
                    'country' => ['name' => 'not'],
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'test',
                    'additionalDescription' => 'test',
                    'technicalName' => Uuid::uuid4()->getHex(),
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $mail,
                'password' => $password,
                'lastName' => 'not',
                'firstName' => 'match',
                'salutation' => 'not',
                'number' => 'not',
            ],
        ], $context);

        $client = $this->createCart();

        $this->login($client, $mail, $password);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->order($client);
        $this->assertSame(400, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('errors', $response);

        $this->assertTrue(array_key_exists('CART-EMPTY', array_flip(array_column($response['errors'], 'code'))));
    }

    private function createCart(): Client
    {
        $this->storefrontApiClient->request('POST', '/storefront-api/checkout/cart');
        $response = $this->storefrontApiClient->getResponse();

        $this->assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        $client = clone $this->storefrontApiClient;
        $client->setServerParameter('HTTP_X_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $client;
    }

    private function getCart(Client $client)
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/checkout');

        $cart = json_decode($client->getResponse()->getContent(), true);

        return $cart['data'] ?? $cart;
    }

    private function addProduct(Client $client, string $id, int $quantity = 1)
    {
        $client->request(
            'POST',
            '/storefront-api/checkout/cart/product/' . $id,
            [],
            [],
            [],
            json_encode([
                'quantity' => $quantity,
                'payload' => ['id' => $id],
            ])
        );
    }

    private function changeQuantity(Client $client, string $lineItemId, $quantity): void
    {
        $client->request('PATCH', sprintf('/storefront-api/checkout/cart/line-item/%s/quantity/%s', $lineItemId, $quantity));
    }

    private function updateLineItem(Client $client, string $lineItemId, $quantity): void
    {
        $client->request('PATCH', sprintf('/storefront-api/checkout/cart/line-item/%s', $lineItemId), [], [], [], json_encode(['quantity' => $quantity]));
    }

    private function removeLineItem(Client $client, string $lineItemId): void
    {
        $client->request('DELETE', '/storefront-api/checkout/cart/line-item/' . $lineItemId);
    }

    private function order(Client $client)
    {
        $client->request('POST', '/storefront-api/checkout/order');
    }

    private function login(Client $client, string $email, string $password)
    {
        $client->request('POST', '/storefront-api/customer/login', [], [], [], json_encode([
            'username' => $email,
            'password' => $password,
        ]));
    }
}
