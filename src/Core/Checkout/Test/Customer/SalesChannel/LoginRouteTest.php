<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LoginRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignSalesChannelContext($this->browser);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testInvalidCredentials(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => 'foo',
                    'password' => 'foo',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('Unauthorized', $response['errors'][0]['title']);
    }

    public function testEmptyRequest(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_AUTH_BAD_CREDENTIALS', $response['errors'][0]['code']);
    }

    public function testValidLogin(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email);

        $this->browser
            ->request(
                'POST',
                '/store-api/v' . PlatformRequest::API_VERSION . '/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);
    }

    public function testLoginSuccessRestoreCustomerContext(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_10058', $this);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer($password, $email);
        $contextToken = Uuid::randomHex();

        $this->createCart($contextToken);

        $salesChannelContext = $this->createSalesChannelContext($contextToken, [], $customerId);

        /** @var LoginRoute $loginRoute */
        $loginRoute = $this->getContainer()->get(LoginRoute::class);

        $request = new RequestDataBag(['email' => $email, 'password' => $password]);

        $response = $loginRoute->login($request, $salesChannelContext);

        // Token is replace as there're no customer token in the database
        static::assertNotEquals($contextToken, $oldToken = $response->getToken());

        $salesChannelContext = $this->createSalesChannelContext('123456789', [], $customerId);

        $response = $loginRoute->login($request, $salesChannelContext);

        // Previous token is restored
        static::assertEquals($oldToken, $response->getToken());

        // Previous Cart is restored
        $salesChannelContext = $this->createSalesChannelContext($oldToken, [], $customerId);
        $oldCartExists = $this->getContainer()->get(CartService::class)->getCart($oldToken, $salesChannelContext);

        static::assertInstanceOf(Cart::class, $oldCartExists);
        static::assertEquals($oldToken, $oldCartExists->getToken());
    }

    private function createCart(string $contextToken): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $countryStatement = $connection->executeQuery('SELECT id FROM country WHERE active = 1 ORDER BY `position`');
        $defaultCountry = $countryStatement->fetchColumn();
        $defaultPaymentMethod = $connection->executeQuery('SELECT id FROM payment_method WHERE active = 1 ORDER BY `position`')->fetchColumn();
        $defaultShippingMethod = $connection->executeQuery('SELECT id FROM shipping_method WHERE active = 1')->fetchColumn();

        $connection->insert('cart', [
            'token' => $contextToken,
            'name' => 'test',
            'cart' => serialize(new Cart('test', $contextToken)),
            'line_item_count' => 1,
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'country_id' => $defaultCountry,
            'price' => 1,
            'payment_method_id' => $defaultPaymentMethod,
            'shipping_method_id' => $defaultShippingMethod,
            'sales_channel_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createSalesChannelContext(string $contextToken, array $salesChannelData, ?string $customerId): SalesChannelContext
    {
        if ($customerId) {
            $salesChannelData[SalesChannelContextService::CUSTOMER_ID] = $customerId;
        }

        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            Defaults::SALES_CHANNEL,
            $salesChannelData
        );
    }

    private function createCustomer(string $password, ?string $email = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => Defaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schoöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                    'salesChannels' => [
                        [
                            'id' => Defaults::SALES_CHANNEL,
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
