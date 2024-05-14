<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedCriteriaEvent;
use Shopware\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Shopware\Core\Checkout\Cart\SalesChannel\CartOrderRoute;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Salutation\SalutationDefinition;
use Shopware\Core\Test\Integration\PaymentHandler\PreparedTestPaymentHandler;
use Shopware\Core\Test\Integration\PaymentHandler\SyncTestPaymentHandler;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Unit\Core\Checkout\Cart\TaxProvider\_fixtures\TestConstantTaxRateProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[CoversClass(CartOrderRoute::class)]
#[Group('store-api')]
class CartOrderRouteTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private EntityRepository $productRepository;

    private EntityRepository $customerRepository;

    private EntityRepository $taxProviderRepository;

    private string $validSalutationId;

    private string $validCountryId;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->taxProviderRepository = $this->getContainer()->get('tax_provider.repository');
        $this->validSalutationId = $this->getValidSalutationId();
        $this->validCountryId = $this->getValidCountryId($this->ids->get('sales-channel'));

        PreparedTestPaymentHandler::$preOrderPaymentStruct = null;
        PreparedTestPaymentHandler::$fail = false;

        $this->createTestData();
    }

    public function testOrderNotLoggedIn(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('cart', $response['apiAlias']);
        static::assertSame(10, $response['price']['totalPrice']);
        static::assertCount(1, $response['lineItems']);

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
        ], Context::createDefaultContext());

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
        static::assertNotFalse($response->getContent());
        $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $data['lineItems']);

        $interval = new \DateInterval($this->getContainer()->getParameter('shopware.api.store.context_lifetime'));
        $intervalInSeconds = (new \DateTime())->setTimeStamp(0)->add($interval)->getTimeStamp();
        $intervalInDays = $intervalInSeconds / 86400 + 1;

        // expire $originalToken context
        $connection->executeStatement(
            '
            UPDATE sales_channel_api_context
            SET updated_at = DATE_ADD(updated_at, INTERVAL :intervalInDays DAY)',
            ['intervalInDays' => -$intervalInDays]
        );

        $this->browser->request('GET', '/store-api/checkout/cart');

        $response = $this->browser->getResponse();
        $guestToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertNotNull($guestToken);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $guestToken);

        // we should get a new token and it should be different from the expired token context
        static::assertNotEquals($originalToken, $guestToken);
        static::assertNotFalse($response->getContent());

        $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
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
        static::assertNotFalse($response->getContent());

        $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(1, $data['lineItems']);

        // the cart should be merged on login and a new token should be created
        $this->login($email, $password);

        $this->browser->request('GET', '/store-api/checkout/cart');

        $response = $this->browser->getResponse();
        $mergedToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        static::assertNotFalse($response->getContent());

        $data = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertCount(2, $data['lineItems']);

        static::assertNotSame($guestToken, $mergedToken);
        static::assertNotSame($originalToken, $mergedToken);
    }

    public function testOrderPlacedCriteriaEventFired(): void
    {
        $this->createCustomerAndLogin();

        $event = null;
        $this->catchEvent(CheckoutOrderPlacedCriteriaEvent::class, $event);

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
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        static::assertInstanceOf(CheckoutOrderPlacedCriteriaEvent::class, $event);
    }

    public function testPreparedPaymentStructForwarded(): void
    {
        $this->createCustomerAndLogin(null, null, PreparedTestPaymentHandler::class);
        PreparedTestPaymentHandler::$preOrderPaymentStruct = null;

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
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order'
            );

        static::assertNotNull(PreparedTestPaymentHandler::$preOrderPaymentStruct);
        static::assertSame(PreparedTestPaymentHandler::TEST_STRUCT_CONTENT, PreparedTestPaymentHandler::$preOrderPaymentStruct->all());
    }

    public function testTaxProviderAppliedIfGiven(): void
    {
        $taxProvider = [
            'id' => $this->ids->get('tax-provider'),
            'active' => true,
            'priority' => 1,
            'identifier' => TestConstantTaxRateProvider::class, // 7% tax rate
            'availabilityRule' => [
                'id' => $this->ids->get('rule'),
                'name' => 'test',
                'priority' => 1,
                'conditions' => [
                    ['type' => (new AlwaysValidRule())->getName()],
                ],
            ],
        ];

        $this->taxProviderRepository->create([$taxProvider], Context::createDefaultContext());
        $this->createCustomerAndLogin();

        $this->browser
            ->request(
                Request::METHOD_POST,
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

        $this->browser
            ->request(
                Request::METHOD_POST,
                '/store-api/checkout/order'
            );

        static::assertNotFalse($this->browser->getResponse()->getContent());

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotEmpty($response);
        static::assertArrayHasKey('price', $response);

        $price = $response['price'];

        static::assertArrayHasKey('netPrice', $price);
        static::assertArrayHasKey('totalPrice', $price);
        static::assertArrayHasKey('calculatedTaxes', $price);

        static::assertSame(9.3, $price['netPrice']);
        static::assertSame(10, $price['totalPrice']);
        static::assertCount(1, $price['calculatedTaxes']);

        $tax = $price['calculatedTaxes'][0];

        static::assertArrayHasKey('tax', $tax);
        static::assertArrayHasKey('taxRate', $tax);
        static::assertArrayHasKey('price', $tax);

        static::assertSame(0.7, $tax['tax']);
        static::assertSame(7, $tax['taxRate']);
        static::assertSame(10, $tax['price']);
    }

    public function testOrderWithExistingNotSpecifiedSalutation(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';

        $this->createCustomerAndLogin($email, $password, PreparedTestPaymentHandler::class, true);

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
        static::assertNotFalse($this->browser->getResponse()->getContent());

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
            );

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotNull($response['orderCustomer']);
        static::assertNotNull($response['orderCustomer']['salutation']);
        static::assertSame($response['orderCustomer']['salutation']['salutationKey'], SalutationDefinition::NOT_SPECIFIED);
    }

    public function testOrderToNotSpecifiedWithoutExistingSalutation(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';

        $connection->executeStatement(
            '
					DELETE FROM salutation WHERE salutation_key = :salutationKey
				',
            ['salutationKey' => SalutationDefinition::NOT_SPECIFIED]
        );

        $salutations = $connection->fetchAllKeyValue('SELECT salutation_key, id FROM salutation');
        static::assertArrayNotHasKey(SalutationDefinition::NOT_SPECIFIED, $salutations);

        $this->createCustomerAndLogin($email, $password, PreparedTestPaymentHandler::class, true);

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
        static::assertNotFalse($this->browser->getResponse()->getContent());

        // Order
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/order',
            );

        $response = \json_decode($this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotNull($response['orderCustomer']);
        static::assertNull($response['orderCustomer']['salutationId']);
    }

    protected function catchEvent(string $eventName, ?Event &$eventResult): void
    {
        $this->addEventListener($this->getContainer()->get('event_dispatcher'), $eventName, static function (Event $event) use (&$eventResult): void {
            $eventResult = $event;
        });
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
        ], Context::createDefaultContext());
    }

    /**
     * @param class-string $paymentHandler
     */
    private function createCustomerAndLogin(
        ?string $email = null,
        ?string $password = null,
        string $paymentHandler = SyncTestPaymentHandler::class,
        bool $invalidSalutationId = false
    ): void {
        $email ??= Uuid::randomHex() . '@example.com';
        $password ??= 'shopware';
        $this->createCustomer(
            $password,
            $email,
            $paymentHandler,
            $invalidSalutationId,
            $this->validSalutationId,
            $this->validCountryId
        );

        $this->login($email, $password);
    }

    private function login(?string $email = null, ?string $password = null): void
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

        $response = $this->browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);
    }

    /**
     * @param class-string $paymentHandler
     */
    private function createCustomer(
        string $password,
        ?string $email = null,
        string $paymentHandler = SyncTestPaymentHandler::class,
        bool $invalidSalutaionId = false,
        ?string $validSalutationId = null,
        ?string $validCountryId = null
    ): string {
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
                    'salutationId' => $validSalutationId ?? $this->getValidSalutationId(),
                    'countryId' => $validCountryId ?? $this->getValidCountryId($this->ids->get('sales-channel')),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'technicalName' => Uuid::randomHex(),
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => $paymentHandler,
                    'salesChannels' => [
                        [
                            'id' => $this->ids->get('sales-channel'),
                        ],
                    ],
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => ($invalidSalutaionId ? null : $validSalutationId ?? $this->getValidSalutationId()),
                'customerNumber' => '12345',
            ],
        ], Context::createDefaultContext());

        return $customerId;
    }
}
