<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;

/**
 * @group store-api
 */
class CartOrderRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var \Shopware\Core\Framework\DataAbstractionLayer\EntityRepository
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->createTestData();
    }

    public function testOrderNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_NOT_LOGGED_IN', $response['errors'][0]['code']);
    }

    public function testOrderEmptyCart(): void
    {
        $this->createCustomerAndLogin();

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CART_EMPTY', $response['errors'][0]['code']);
    }

    public function testOrderOneProduct(): void
    {
        $this->createCustomerAndLogin();

        // Fill product
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('order', $response['apiAlias']);
        static::assertSame(10, $response['transactions'][0]['amount']['totalPrice']);
        static::assertCount(1, $response['lineItems']);
    }

    public function testOrderWithComment(): void
    {
        $this->createCustomerAndLogin();

        // Fill product
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [
                    'customerComment' => '  test comment  ',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('order', $response['apiAlias']);
        static::assertSame('test comment', $response['customerComment']);
    }

    public function testOrderWithAffiliateAndCampaignTracking(): void
    {
        $this->createCustomerAndLogin();

        // Fill product
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [
                    'affiliateCode' => 'test affiliate code',
                    'campaignCode' => 'test campaign code',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('order', $response['apiAlias']);
        static::assertSame('test affiliate code', $response['affiliateCode']);
        static::assertSame('test campaign code', $response['campaignCode']);
    }

    public function testOrderWithAffiliateTrackingOnly(): void
    {
        $this->createCustomerAndLogin();

        // Fill product
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [
                    'affiliateCode' => 'test affiliate code',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('order', $response['apiAlias']);
        static::assertSame('test affiliate code', $response['affiliateCode']);
        static::assertNull($response['campaignCode']);
    }

    public function testOrderWithCampaignTrackingOnly(): void
    {
        $this->createCustomerAndLogin();

        // Fill product
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
                [
                    'campaignCode' => 'test campaign code',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertSame('order', $response['apiAlias']);
        static::assertNull($response['affiliateCode']);
        static::assertSame('test campaign code', $response['campaignCode']);
    }

    public function testContextTokenExpiring(): void
    {
        /**
         * - login
         * - add product p1
         * - simulate context token expiring
         * - check for new context token
         * - cart is empty
         * - add product p2
         * - login
         * - check for new context token
         * - cart should contain both products
         */
        $connection = $this->getContainer()->get(Connection::class);
        $this->productRepository->create([
            [
                'id' => $this->ids->create('p2'),
                'productNumber' => $this->ids->get('p2'),
                'stock' => 10,
                'name' => 'Test p2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturerId' => $this->ids->get('manufacturerId'),
                'taxId' => $this->ids->get('tax'),
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->ids->context);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomerAndLogin($email, $password);

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ]
            );

        $response = $this->browser->getResponse();
        $originalToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertNotNull($originalToken);
        $data = json_decode($response->getContent(), true);
        static::assertCount(1, $data['lineItems']);

        $interval = new \DateInterval($this->getContainer()->getParameter('shopware.api.store.context_lifetime'));
        $intervalInSeconds = (new \DateTime())->setTimeStamp(0)->add($interval)->getTimeStamp();
        $intervalInDays = $intervalInSeconds / 86400 + 1;

        // expire $originalToken context
        $connection->executeUpdate(
            '
            UPDATE sales_channel_api_context
            SET updated_at = DATE_ADD(updated_at, INTERVAL :intervalInDays DAY)',
            ['intervalInDays' => -$intervalInDays]
        );

        $this->browser->request('GET', '/store-api/checkout/cart');

        $response = $this->browser->getResponse();
        $guestToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $guestToken);

        // we should get a new token and it should be different from the expired token context
        static::assertNotNull($guestToken);
        static::assertNotEquals($originalToken, $guestToken);

        $data = json_decode($response->getContent(), true);
        static::assertEmpty($data['lineItems']);

        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [
                    'items' => [
                        [
                            'id' => $this->ids->get('p2'),
                            'type' => 'product',
                            'referencedId' => $this->ids->get('p2'),
                        ],
                    ],
                ]
            );

        $response = $this->browser->getResponse();
        $token = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertSame($guestToken, $token);

        $data = json_decode($response->getContent(), true);
        static::assertCount(1, $data['lineItems']);

        // the cart should be merged on login and a new token should be created
        $this->login($email, $password);

        $this->browser->request('GET', '/store-api/checkout/cart');

        $response = $this->browser->getResponse();
        $mergedToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        $data = json_decode($response->getContent(), true);
        static::assertCount(2, $data['lineItems']);

        static::assertNotSame($guestToken, $mergedToken);
        static::assertNotSame($originalToken, $mergedToken);
    }

    private function createTestData(): void
    {
        $this->addCountriesToSalesChannel();

        $this->productRepository->create([
            [
                'id' => $this->ids->create('p1'),
                'productNumber' => $this->ids->get('p1'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['salesChannelId' => $this->ids->get('sales-channel'), 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], $this->ids->context);
    }

    private function createCustomerAndLogin($email = null, $password = null): void
    {
        $email = $email ?? (Uuid::randomHex() . '@example.com');
        $password = $password ?? 'shopware';
        $this->createCustomer($password, $email);

        $this->login($email, $password);
    }

    private function login($email = null, $password = null): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
    }

    private function createCustomer(string $password, ?string $email = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => $this->ids->get('sales-channel'),
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'salesChannels' => [
                        [
                            'id' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], $this->ids->context);

        return $customerId;
    }
}
