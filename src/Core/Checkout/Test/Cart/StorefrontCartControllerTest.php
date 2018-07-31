<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;

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
     * @var RepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var RepositoryInterface
     */
    private $mediaAlbumRepository;

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

    /**
     * @var Context
     */
    private $context;

    protected function setUp()
    {
        parent::setUp();

        $this->storefrontApiClient->setServerParameter('CONTENT_TYPE', 'application/json');

        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->mediaAlbumRepository = $this->getContainer()->get('media_album.repository');
        $this->taxId = Uuid::uuid4()->getHex();
        $this->manufacturerId = Uuid::uuid4()->getHex();
        $this->context = Context::createDefaultContext(Defaults::TENANT_ID);
    }

    public function testAddNonExistingProduct()
    {
        $productId = Uuid::uuid4()->getHex();

        $client = $this->createCart();

        $this->addProduct($client, $productId);

        $content = json_decode($client->getResponse()->getContent(), true);
        static::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());

        static::assertNotEmpty($content);
        static::assertArrayHasKey('errors', $content);
    }

    public function testAddProduct(): void
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
        ], $this->context);

        $client = $this->createCart();

        $this->addProduct($client, $productId);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        $cart = $content['data'];

        static::assertArrayHasKey('price', $cart);
        static::assertEquals(10, $cart['price']['totalPrice']);
        static::assertCount(1, $cart['lineItems']);

        $product = array_shift($cart['lineItems']);
        static::assertEquals($productId, $product['key']);
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
        ], $this->context);

        $client = $this->createCart();

        $this->addProduct($client, $productId1);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId2);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $cart = $this->getCart($client);

        static::assertNotEmpty($cart);
        static::assertCount(2, $cart['lineItems']);
    }

    public function testChangeQuantity()
    {
        $productId1 = Uuid::uuid4()->getHex();

        $this->productRepository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $this->context);

        $client = $this->createCart();

        $this->addProduct($client, $productId1);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->changeQuantity($client, $productId1, 10);

        $cart = $this->getCart($client);

        static::assertNotEmpty($cart);
        static::assertCount(1, $cart['lineItems']);

        $lineItem = array_shift($cart['lineItems']);
        static::assertEquals(10, $lineItem['quantity']);
    }

    public function testChangeLineItemQuantity()
    {
        $productId1 = Uuid::uuid4()->getHex();

        $this->productRepository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $this->context);

        $client = $this->createCart();

        $this->addProduct($client, $productId1);
        $this->assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->updateLineItemQuantity($client, $productId1, 10);

        $cart = $this->getCart($client);

        $this->assertNotEmpty($cart);
        $this->assertCount(1, $cart['lineItems']);

        $lineItem = array_shift($cart['lineItems']);
        $this->assertEquals(10, $lineItem['quantity']);
    }

    public function testChangeWithInvalidQuantity()
    {
        $productId1 = Uuid::uuid4()->getHex();

        $this->productRepository->create([
            [
                'id' => $productId1,
                'name' => 'Test 1',
                'catalogId' => Defaults::CATALOG,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
            ],
        ], $this->context);

        $client = $this->createCart();

        $this->addProduct($client, $productId1);
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
        ], $this->context);

        $client = $this->createCart();

        $this->addProduct($client, $productId1);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId2);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->removeLineItem($client, $productId1);

        $cart = $this->getCart($client);

        static::assertNotEmpty($cart);
        static::assertCount(1, $cart['lineItems']);

        $keys = array_column($cart['lineItems'], 'key');
        static::assertNotContains($productId1, $keys);
    }

    public function testRemoveNonExistingLineItem()
    {
        $productId1 = Uuid::uuid4()->getHex();
        $client = $this->createCart();

        $this->removeLineItem($client, $productId1);

        $cart = $this->getCart($client);

        static::assertNotEmpty($cart);
        static::assertArrayHasKey('errors', $cart);

        static::assertTrue(
            array_key_exists(
                'CART-LINE-ITEM-NOT-FOUND',
                array_flip(array_column($cart['errors'], 'code'))
            ),
            print_r($cart, true)
        );
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
        ], $this->context);

        $client = $this->createCart();

        //add product 1 three times with quantity 1
        $this->addProduct($client, $productId1);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId1);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        //add product 2 one time with quantity 1 and one time with quantity 10
        $this->addProduct($client, $productId2);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->addProduct($client, $productId2, 10);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $cart = $this->getCart($client);

        static::assertNotEmpty($cart);
        static::assertCount(2, $cart['lineItems']);

        foreach ($cart['lineItems'] as $lineItem) {
            if ($lineItem['key'] === $productId1) {
                static::assertEquals(2, $lineItem['quantity']);
            } else {
                static::assertEquals(11, $lineItem['quantity']);
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
        ], $this->context);

        $albumId = Uuid::uuid4()->getHex();
        $this->mediaAlbumRepository->create([
            [
                'id' => $albumId,
                'name' => 'Products',
            ],
        ], $this->context);

        $mediaId = Uuid::uuid4()->getHex();
        $coverName = 'My custom line item name';
        $this->mediaRepository->create([
            [
                'id' => $mediaId,
                'albumId' => $albumId,
                'name' => $coverName,
            ],
        ], $this->context);

        $client = $this->createCart();

        $quantity = 10;
        $type = ProductCollector::LINE_ITEM_TYPE;
        $stackable = true;
        $removeable = true;
        $priority = 500;
        $label = 'My custom label';
        $description = 'My custom description';

        $client->request(
            'POST',
            '/storefront-api/checkout/cart/line-item/' . $productId,
            [
                'type' => $type,
                'quantity' => $quantity,
                'stackable' => $stackable,
                'removeable' => $removeable,
                'priority' => $priority,
                'label' => $label,
                'description' => $description,
                'coverId' => $mediaId,
            ]
        );

        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        $cart = $content['data'];

        static::assertArrayHasKey('price', $cart);
        static::assertEquals(100, $cart['price']['totalPrice']);
        static::assertCount(1, $cart['lineItems']);

        $product = array_shift($cart['lineItems']);

        static::assertEquals($productId, $product['key']);
        static::assertEquals($type, $product['type']);
        static::assertEquals($quantity, $product['quantity']);

        static::assertEquals($stackable, $product['stackable']);
        static::assertEquals($removeable, $product['removeable']);
        static::assertEquals($priority, $product['priority']);
        static::assertEquals($label, $product['label']);
        static::assertEquals($description, $product['description']);

        static::assertEquals($mediaId, $product['cover']['id']);
        static::assertEquals($coverName, $product['cover']['name']);
        static::assertEquals($albumId, $product['cover']['albumId']);
    }

    public function testUpdateLineItem()
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
        ], $this->context);

        $client = $this->createCart();

        $type = ProductCollector::LINE_ITEM_TYPE;

        $client->request(
            'POST',
            '/storefront-api/checkout/cart/line-item/' . $productId,
            [
                'type' => $type,
                'stackable' => true,
            ]
        );

        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        $cart = $content['data'];

        static::assertArrayHasKey('price', $cart);
        static::assertEquals(10, $cart['price']['totalPrice']);
        static::assertCount(1, $cart['lineItems']);

        $albumId = Uuid::uuid4()->getHex();
        $this->mediaAlbumRepository->create([
            [
                'id' => $albumId,
                'name' => 'Products',
            ],
        ], $this->context);

        $mediaId = Uuid::uuid4()->getHex();
        $coverName = 'My custom line item name';
        $this->mediaRepository->create([
            [
                'id' => $mediaId,
                'albumId' => $albumId,
                'name' => $coverName,
            ],
        ], $this->context);

        $quantity = 10;
        $stackable = true;
        $removeable = true;
        $priority = 500;
        $label = 'My custom label';
        $description = 'My custom description';

        $client->request(
            'PATCH',
            '/storefront-api/checkout/cart/line-item/' . $productId,
            [
                'quantity' => $quantity,
                'stackable' => $stackable,
                'removeable' => $removeable,
                'priority' => $priority,
                'label' => $label,
                'description' => $description,
                'coverId' => $mediaId,
            ]
        );

        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $content = json_decode($client->getResponse()->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        $cart = $content['data'];
        $product = array_shift($cart['lineItems']);

        static::assertEquals($productId, $product['key']);
        static::assertEquals($type, $product['type']);
        static::assertEquals($quantity, $product['quantity']);

        static::assertEquals($stackable, $product['stackable']);
        static::assertEquals($removeable, $product['removeable']);
        static::assertEquals($priority, $product['priority']);
        static::assertEquals($label, $product['label']);
        static::assertEquals($description, $product['description']);

        static::assertEquals($mediaId, $product['cover']['id']);
        static::assertEquals($coverName, $product['cover']['name']);
        static::assertEquals($albumId, $product['cover']['albumId']);
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
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->login($client, $mail, $password);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->order($client);
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $order = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $order);

        $order = $order['data'];
        static::assertNotEmpty($order);

        static::assertEquals($mail, $order['customer']['email']);
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
        static::assertSame(200, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $this->order($client);
        static::assertSame(400, $client->getResponse()->getStatusCode(), $client->getResponse()->getContent());

        $response = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('errors', $response);

        static::assertTrue(array_key_exists('CART-EMPTY', array_flip(array_column($response['errors'], 'code'))));
    }

    private function createCart(): Client
    {
        $this->storefrontApiClient->request('POST', '/storefront-api/checkout/cart');
        $response = $this->storefrontApiClient->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        $client = clone $this->storefrontApiClient;
        $client->setServerParameter('HTTP_X_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $client;
    }

    private function getCart(Client $client)
    {
        $this->storefrontApiClient->request('GET', '/storefront-api/checkout/cart');

        $cart = json_decode($client->getResponse()->getContent(), true);

        return $cart['data'] ?? $cart;
    }

    private function addProduct(Client $client, string $id, int $quantity = 1)
    {
        $client->request(
            'POST',
            '/storefront-api/checkout/cart/product/' . $id,
            [
                'quantity' => $quantity,
            ]
        );
    }

    private function changeQuantity(Client $client, string $lineItemId, $quantity): void
    {
        $client->request('PATCH', sprintf('/storefront-api/checkout/cart/line-item/%s/quantity/%s', $lineItemId, $quantity));
    }

    private function updateLineItemQuantity(Client $client, string $lineItemId, $quantity): void
    {
        $client->request('PATCH', sprintf('/storefront-api/checkout/cart/line-item/%s', $lineItemId), ['quantity' => $quantity]);
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
        $client->request('POST', '/storefront-api/customer/login', [
            'username' => $email,
            'password' => $password,
        ]);
    }
}
