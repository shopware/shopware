<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Routing\Router;

class SalesChannelCartControllerTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var EntityRepositoryInterface
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

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->taxId = Uuid::randomHex();
        $this->manufacturerId = Uuid::randomHex();
        $this->context = Context::createDefaultContext();
        $this->router = $this->getContainer()->get('router');
    }

    public function testAddNonExistingProduct(): void
    {
        $productId = Uuid::randomHex();

        $browser = $this->createCart();

        $this->addProduct($browser, $productId);

        $content = json_decode($browser->getResponse()->getContent(), true);
        static::assertArrayHasKey('data', $content);
        static::assertArrayHasKey('errors', $content['data']);
        static::assertEquals('product-not-found', $content['data']['errors'][0]['messageKey']);
    }

    public function testAddProduct(): void
    {
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $browser = $this->createCart();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        $this->addProduct($browser, $productId);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $content = json_decode($browser->getResponse()->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        $cart = $content['data'];

        static::assertArrayHasKey('price', $cart);
        static::assertEquals(10, $cart['price']['totalPrice']);
        static::assertCount(1, $cart['lineItems']);

        $product = array_shift($cart['lineItems']);
        static::assertEquals($productId, $product['id']);
    }

    public function testAddMultipleProducts(): void
    {
        $productId1 = Uuid::randomHex();
        $productNumber1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $productNumber2 = Uuid::randomHex();

        $browser = $this->createCart();
        $this->productRepository->create([
            [
                'id' => $productId1,
                'productNumber' => $productNumber1,
                'stock' => 1,
                'name' => 'Test 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => $productId2,
                'productNumber' => $productNumber2,
                'stock' => 1,
                'name' => 'Test 2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 20, 'net' => 9, 'linked' => false]],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        $this->addProduct($browser, $productId1);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->addProduct($browser, $productId2);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $cart = $this->getCart($browser);

        static::assertNotEmpty($cart);
        static::assertCount(2, $cart['lineItems']);
    }

    public function testChangeQuantity(): void
    {
        $productId1 = Uuid::randomHex();
        $productNumber1 = Uuid::randomHex();

        $browser = $this->createCart();
        $this->productRepository->create([
            [
                'id' => $productId1,
                'productNumber' => $productNumber1,
                'stock' => 1,
                'name' => 'Test 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        $this->addProduct($browser, $productId1);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->changeQuantity($browser, $productId1, 10);

        $cart = $this->getCart($browser);

        static::assertNotEmpty($cart);
        static::assertCount(1, $cart['lineItems']);

        $lineItem = array_shift($cart['lineItems']);
        static::assertEquals(10, $lineItem['quantity']);
    }

    public function testChangeLineItemQuantity(): void
    {
        $productId1 = Uuid::randomHex();
        $productNumber1 = Uuid::randomHex();

        $browser = $this->createCart();
        $this->productRepository->create([
            [
                'id' => $productId1,
                'productNumber' => $productNumber1,
                'stock' => 1,
                'name' => 'Test 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        $this->addProduct($browser, $productId1);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->updateLineItemQuantity($browser, $productId1, 10);

        $cart = $this->getCart($browser);

        static::assertNotEmpty($cart);
        static::assertCount(1, $cart['lineItems']);

        $lineItem = array_shift($cart['lineItems']);
        static::assertEquals(10, $lineItem['quantity']);
    }

    public function testChangeWithInvalidQuantity(): void
    {
        $productId1 = Uuid::randomHex();
        $productNumber1 = Uuid::randomHex();

        $browser = $this->createCart();
        $this->productRepository->create([
            [
                'id' => $productId1,
                'productNumber' => $productNumber1,
                'stock' => 1,
                'name' => 'Test 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        $this->addProduct($browser, $productId1);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->changeQuantity($browser, $productId1, -1);

        $cart = $this->getCart($browser);

        static::assertNotEmpty($cart);
        static::assertArrayHasKey('errors', $cart);
        static::assertEquals('CHECKOUT__CART_INVALID_LINEITEM_QUANTITY', $cart['errors'][0]['code']);
    }

    public function testRemoveLineItem(): void
    {
        $productId1 = Uuid::randomHex();
        $productNumber1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $productNumber2 = Uuid::randomHex();

        $browser = $this->createCart();
        $this->productRepository->create([
            [
                'id' => $productId1,
                'productNumber' => $productNumber1,
                'stock' => 1,
                'name' => 'Test 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => $productId2,
                'productNumber' => $productNumber2,
                'stock' => 1,
                'name' => 'Test 2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 20, 'net' => 9, 'linked' => false]],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        $this->addProduct($browser, $productId1);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->addProduct($browser, $productId2);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->removeLineItem($browser, $productId1);

        $cart = $this->getCart($browser);

        static::assertNotEmpty($cart);
        static::assertCount(1, $cart['lineItems']);

        $keys = array_column($cart['lineItems'], 'id');
        static::assertNotContains($productId1, $keys);
    }

    public function testRemoveNonExistingLineItem(): void
    {
        $productId1 = Uuid::randomHex();
        $browser = $this->createCart();

        $this->removeLineItem($browser, $productId1);

        $cart = $this->getCart($browser);

        static::assertNotEmpty($cart);
        static::assertArrayHasKey('errors', $cart);

        static::assertArrayHasKey(
            'CHECKOUT__CART_LINEITEM_NOT_FOUND',
            array_flip(array_column($cart['errors'], 'code')),
            print_r($cart, true)
        );
    }

    public function testMergeSameProduct(): void
    {
        $productId1 = Uuid::randomHex();
        $productNumber1 = Uuid::randomHex();
        $productId2 = Uuid::randomHex();
        $productNumber2 = Uuid::randomHex();

        $browser = $this->createCart();
        $this->productRepository->create([
            [
                'id' => $productId1,
                'productNumber' => $productNumber1,
                'stock' => 1,
                'name' => 'Test 1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
            [
                'id' => $productId2,
                'productNumber' => $productNumber2,
                'stock' => 1,
                'name' => 'Test 2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 20, 'net' => 9, 'linked' => false]],
                'manufacturerId' => $this->manufacturerId,
                'taxId' => $this->taxId,
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        //add product 1 three times with quantity 1
        $this->addProduct($browser, $productId1);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->addProduct($browser, $productId1);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        //add product 2 one time with quantity 1 and one time with quantity 10
        $this->addProduct($browser, $productId2);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $this->addProduct($browser, $productId2, 10);
        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $cart = $this->getCart($browser);

        static::assertNotEmpty($cart);
        static::assertCount(2, $cart['lineItems']);

        foreach ($cart['lineItems'] as $lineItem) {
            if ($lineItem['id'] === $productId1) {
                static::assertEquals(2, $lineItem['quantity']);
            } else {
                static::assertEquals(11, $lineItem['quantity']);
            }
        }
    }

    public function testAddProductUsingGenericLineItemRoute(): void
    {
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $browser = $this->createCart();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([
            [
                'id' => $mediaId,
            ],
        ], $this->context);

        $quantity = 10;
        $type = LineItem::PRODUCT_LINE_ITEM_TYPE;
        $stackable = true;
        $removable = true;

        $browser->request(
            'POST',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/line-item/' . $productId,
            [
                'type' => $type,
                'referencedId' => $productId,
                'quantity' => $quantity,
                'stackable' => $stackable,
                'removable' => $removable,
                'coverId' => $mediaId,
            ]
        );

        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $content = json_decode($browser->getResponse()->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        $cart = $content['data'];

        static::assertArrayHasKey('price', $cart);
        static::assertEquals(100, $cart['price']['totalPrice']);
        static::assertCount(1, $cart['lineItems']);

        $product = array_shift($cart['lineItems']);

        static::assertEquals($productId, $product['id']);
        static::assertEquals($type, $product['type']);
        static::assertEquals($quantity, $product['quantity']);

        static::assertEquals($stackable, $product['stackable']);
        static::assertEquals($removable, $product['removable']);

        static::assertEquals($mediaId, $product['cover']['id']);
    }

    public function testUpdateLineItem(): void
    {
        $productId = Uuid::randomHex();
        $productNumber = Uuid::randomHex();
        $browser = $this->createCart();

        $this->productRepository->create([
            [
                'id' => $productId,
                'productNumber' => $productNumber,
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $this->taxId, 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $browser->getServerParameter('test-sales-channel-id'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->context);

        $this->addProduct($browser, $productId);

        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $content = json_decode($browser->getResponse()->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        $cart = $content['data'];

        static::assertArrayHasKey('price', $cart);
        static::assertEquals(10, $cart['price']['totalPrice']);
        static::assertCount(1, $cart['lineItems']);

        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([
            [
                'id' => $mediaId,
            ],
        ], $this->context);

        $quantity = 10;
        $stackable = true;
        $removable = true;

        $browser->request(
            'PATCH',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/line-item/' . $productId,
            [
                'referencedId' => $productId,
                'quantity' => $quantity,
                'stackable' => $stackable,
                'removable' => $removable,
                'coverId' => $mediaId,
            ]
        );

        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $content = json_decode($browser->getResponse()->getContent(), true);

        static::assertNotEmpty($content);
        static::assertArrayHasKey('data', $content);
        $cart = $content['data'];
        $product = array_shift($cart['lineItems']);

        static::assertEquals($productId, $product['id']);
        static::assertEquals(LineItem::PRODUCT_LINE_ITEM_TYPE, $product['type']);
        static::assertEquals($quantity, $product['quantity']);

        static::assertEquals($stackable, $product['stackable']);
        static::assertEquals($removable, $product['removable']);

        static::assertEquals($mediaId, $product['cover']['id']);
    }

    public function testGetCartWithoutAccessKey(): void
    {
        $accessHeader = 'HTTP_' . str_replace('-', '_', mb_strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $this->getSalesChannelBrowser()->setServerParameter($accessHeader, '');

        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart');
        $response = $this->getSalesChannelBrowser()->getResponse();
        static::assertEquals(500, $response->getStatusCode(), $response->getContent());
        $content = json_decode($response->getContent(), true);
        static::assertEquals('Access key is invalid and could not be identified.', $content['errors'][0]['detail']);
    }

    public function testAddingCreditWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $browser = $this->createCart();

        $browser->request(
            'POST',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/line-item/' . $id,
            [
                'label' => 'Test',
                'type' => 'credit',
                'priceDefinition' => [
                    'price' => 100,
                    'type' => 'absolute',
                    'absolute' => 1,
                ],
            ]
        );

        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $content = json_decode($browser->getResponse()->getContent(), true);
        static::assertSame(0, $content['data']['price']['totalPrice']);
    }

    public function testAddingCreditWithPermission(): void
    {
        $id = Uuid::randomHex();
        $browser = $this->createCart();

        $token = $browser->getServerParameter('HTTP_SW_CONTEXT_TOKEN');

        $payload = $this->getContainer()->get(SalesChannelContextPersister::class)->load($token);
        $payload[SalesChannelContextService::PERMISSIONS] = [ProductCartProcessor::ALLOW_PRODUCT_PRICE_OVERWRITES => true];
        $this->getContainer()->get(SalesChannelContextPersister::class)->save($token, $payload);

        $browser->request(
            'POST',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/line-item/' . $id,
            [
                'label' => 'Test',
                'type' => 'credit',
                'priceDefinition' => [
                    'price' => 100,
                    'type' => 'absolute',
                    'absolute' => 1,
                ],
            ]
        );

        static::assertSame(200, $browser->getResponse()->getStatusCode(), $browser->getResponse()->getContent());

        $content = json_decode($browser->getResponse()->getContent(), true);
        static::assertSame(100, $content['data']['price']['totalPrice']);
    }

    private function createCart(): KernelBrowser
    {
        $this->assignSalesChannelContext();
        $this->getSalesChannelBrowser()->request('POST', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart');
        $response = $this->getSalesChannelBrowser()->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true);

        $browser = clone $this->getSalesChannelBrowser();
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content[PlatformRequest::HEADER_CONTEXT_TOKEN]);

        return $browser;
    }

    private function getCart(KernelBrowser $browser)
    {
        $this->getSalesChannelBrowser()->request('GET', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart');

        $cart = json_decode($browser->getResponse()->getContent(), true);

        return $cart['data'] ?? $cart;
    }

    private function addProduct(KernelBrowser $browser, string $id, int $quantity = 1): void
    {
        $browser->request(
            'POST',
            '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/product/' . $id,
            [
                'quantity' => $quantity,
            ]
        );
    }

    private function changeQuantity(KernelBrowser $browser, string $lineItemId, int $quantity): void
    {
        $browser->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/line-item/' . $lineItemId, ['quantity' => $quantity]);
    }

    private function updateLineItemQuantity(KernelBrowser $browser, string $lineItemId, int $quantity): void
    {
        $browser->request('PATCH', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/line-item/' . $lineItemId, ['quantity' => $quantity]);
    }

    private function removeLineItem(KernelBrowser $browser, string $lineItemId): void
    {
        $browser->request('DELETE', '/sales-channel-api/v' . PlatformRequest::API_VERSION . '/checkout/cart/line-item/' . $lineItemId);
    }
}
