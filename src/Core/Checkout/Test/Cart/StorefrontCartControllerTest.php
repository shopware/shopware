<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\StorefrontFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Router;

class StorefrontCartControllerTest extends TestCase
{
    use StorefrontFunctionalTestBehaviour;

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

    /**
     * @var Router
     */
    private $router;

    public function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->taxId = Uuid::uuid4()->getHex();
        $this->manufacturerId = Uuid::uuid4()->getHex();
        $this->context = Context::createDefaultContext();
        $this->router = $this->getContainer()->get('router');
    }

    public function testAddNonExistingProduct(): void
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

    public function testAddMultipleProducts(): void
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

    public function testChangeQuantity(): void
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

    public function testChangeLineItemQuantity(): void
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

        $this->updateLineItemQuantity($client, $productId1, 10);

        $cart = $this->getCart($client);

        static::assertNotEmpty($cart);
        static::assertCount(1, $cart['lineItems']);

        $lineItem = array_shift($cart['lineItems']);
        static::assertEquals(10, $lineItem['quantity']);
    }

    public function testChangeWithInvalidQuantity(): void
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

        $this->changeQuantity($client, $productId1, -1);

        $cart = $this->getCart($client);

        static::assertNotEmpty($cart);
        static::assertArrayHasKey('errors', $cart);
        static::assertEquals('CART-INVALID-QUANTITY', $cart['errors'][0]['code']);
    }

    public function testRemoveLineItem(): void
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

    public function testRemoveNonExistingLineItem(): void
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

    public function testMergeSameProduct(): void
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

    public function testAddProductUsingGenericLineItemRoute(): void
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

        $mediaId = Uuid::uuid4()->getHex();
        $this->mediaRepository->create([
            [
                'id' => $mediaId,
            ],
        ], $this->context);

        $client = $this->createCart();

        $quantity = 10;
        $type = ProductCollector::LINE_ITEM_TYPE;
        $stackable = true;
        $removable = true;
        $priority = 500;
        $label = 'My custom label';
        $description = 'My custom description';

        $client->request(
            'POST',
            '/storefront-api/v1/checkout/cart/line-item/' . $productId,
            [
                'type' => $type,
                'quantity' => $quantity,
                'stackable' => $stackable,
                'removable' => $removable,
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
        static::assertEquals($removable, $product['removable']);
        static::assertEquals($priority, $product['priority']);
        static::assertEquals($label, $product['label']);
        static::assertEquals($description, $product['description']);

        static::assertEquals($mediaId, $product['cover']['id']);
    }

    public function testUpdateLineItem(): void
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

        $mediaId = Uuid::uuid4()->getHex();
        $this->mediaRepository->create([
            [
                'id' => $mediaId,
            ],
        ], $this->context);

        $quantity = 10;
        $stackable = true;
        $removable = true;
        $priority = 500;
        $label = 'My custom label';
        $description = 'My custom description';

        $client->request(
            'PATCH',
            '/storefront-api/v1/checkout/cart/line-item/' . $productId,
            [
                'quantity' => $quantity,
                'stackable' => $stackable,
                'removable' => $removable,
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
        static::assertEquals(ProductCollector::LINE_ITEM_TYPE, $product['type']);
        static::assertEquals($quantity, $product['quantity']);

        static::assertEquals($stackable, $product['stackable']);
        static::assertEquals($removable, $product['removable']);
        static::assertEquals($priority, $product['priority']);
        static::assertEquals($label, $product['label']);
        static::assertEquals($description, $product['description']);

        static::assertEquals($mediaId, $product['cover']['id']);
    }

    public function testGetCartWithoutAccessKey(): void
    {
        $accessHeader = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $this->getStorefrontClient()->setServerParameter($accessHeader, '');

        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/checkout/cart');
        $response = $this->getStorefrontClient()->getResponse();
        static::assertEquals(500, $response->getStatusCode(), $response->getContent());
        $content = json_decode($response->getContent(), true);
        static::assertEquals('Access key is invalid and could not be identified.', $content['errors'][0]['detail']);
    }

    private function createCart(): Client
    {
        $this->getStorefrontClient()->request('POST', '/storefront-api/v1/checkout/cart');
        $response = $this->getStorefrontClient()->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        $client = clone $this->getStorefrontClient();
        $client->setServerParameter('HTTP_X_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $client;
    }

    private function getCart(Client $client)
    {
        $this->getStorefrontClient()->request('GET', '/storefront-api/v1/checkout/cart');

        $cart = json_decode($client->getResponse()->getContent(), true);

        return $cart['data'] ?? $cart;
    }

    private function addProduct(Client $client, string $id, int $quantity = 1): void
    {
        $client->request(
            'POST',
            '/storefront-api/v1/checkout/cart/product/' . $id,
            [
                'quantity' => $quantity,
            ]
        );
    }

    private function changeQuantity(Client $client, string $lineItemId, int $quantity): void
    {
        $client->request('PATCH', '/storefront-api/v1/checkout/cart/line-item/' . $lineItemId, ['quantity' => $quantity]);
    }

    private function updateLineItemQuantity(Client $client, string $lineItemId, int $quantity): void
    {
        $client->request('PATCH', '/storefront-api/v1/checkout/cart/line-item/' . $lineItemId, ['quantity' => $quantity]);
    }

    private function removeLineItem(Client $client, string $lineItemId): void
    {
        $client->request('DELETE', '/storefront-api/v1/checkout/cart/line-item/' . $lineItemId);
    }
}
