<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Test\Controller;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Customer\Repository\CustomerRepository;
use Shopware\Content\Product\Repository\ProductRepository;
use Shopware\CartBridge\Product\ProductProcessor;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Defaults;
use Shopware\PlatformRequest;
use Shopware\Rest\Test\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Client;

class CheckoutControllerTest extends ApiTestCase
{
    /**
     * @var ProductRepository
     */
    private $repository;

    /**
     * @var CustomerRepository
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

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->repository = $this->getContainer()->get(ProductRepository::class);
        $this->customerRepository = $this->getContainer()->get(CustomerRepository::class);
        $this->taxId = Uuid::uuid4()->getHex();
        $this->manufacturerId = Uuid::uuid4()->getHex();
    }

    public function testAddProduct()
    {
        $productId = Uuid::uuid4()->getHex();
        $this->repository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'rate' => 17, 'name' => 'with id'],
            ],
        ], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

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
        ], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

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
        ], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

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
        $productId1 = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();

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
        ], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

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
        $productId1 = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();

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
        ], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

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
        $productId = Uuid::uuid4()->getHex();
        $this->repository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'rate' => 17, 'name' => 'with id'],
            ],
        ], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $client = $this->createCart();

        $client->request('POST', '/storefront-api/checkout/add-product/' . $productId);

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
        $this->repository->create([
            [
                'id' => $productId,
                'name' => 'Test',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'rate' => 17, 'name' => 'with id'],
            ],
        ], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

        $addressId = Uuid::uuid4()->getHex();

        $mail = Uuid::uuid4()->getHex();
        $password = 'shopware';

        $this->connection->executeUpdate('DELETE FROM customer WHERE email = :mail', [
            'mail' => $mail,
        ]);

        $this->customerRepository->create([
            [
                'applicationId' => Defaults::APPLICATION,
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
                'password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 13]),
                'lastName' => 'not',
                'firstName' => 'match',
                'salutation' => 'not',
                'number' => 'not',
            ],
        ], ApplicationContext:: createDefaultContext(\Shopware\Defaults::TENANT_ID));

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

        $this->assertCount(1, $order['deliveries']);
        $this->assertEquals($mail, $order['customer']['email']);
        $this->assertCount(1, $order['lineItems']);
    }

    public function createCart(): Client
    {
        $this->storefrontApiClient->request('POST', '/storefront-api/checkout');

        $content = json_decode($this->storefrontApiClient->getResponse()->getContent(), true);

        $client = clone $this->storefrontApiClient;
        $client->setServerParameter('HTTP_X_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $client;
    }

    public function getCart(Client $client)
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/checkout');

        $cart = json_decode($client->getResponse()->getContent(), true);

        return $cart['data'];
    }

    private function addProduct(Client $client, string $id, int $quantity = 1)
    {
        $client->request(
            'POST',
            '/storefront-api/checkout/add',
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
        $client->request('PUT', '/storefront-api/checkout/set-quantity/' . $productId, [], [], [], json_encode(['quantity' => $quantity]));
    }

    private function removeLineItem(Client $client, string $productId): void
    {
        $client->request('DELETE', '/storefront-api/checkout/' . $productId);
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
