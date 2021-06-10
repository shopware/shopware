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
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * @group store-api
 */
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
                '/store-api/account/login',
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
                '/store-api/account/login',
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
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => $password,
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);
    }

    public function testLoginWithInvalidBoundSalesChannelId(): void
    {
        static::expectException(UnauthorizedHttpException::class);

        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $salesChannel = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
        ]);

        $salesChannelContext = $this->createSalesChannelContext(Uuid::randomHex(), [
            'id' => Uuid::randomHex(),
        ], null);

        $this->createCustomer($password, $email, $salesChannel['id']);

        $loginRoute = $this->getContainer()->get(LoginRoute::class);

        $requestDataBag = new RequestDataBag(['email' => $email, 'password' => $password]);

        $success = $loginRoute->login($requestDataBag, $salesChannelContext);
        static::assertInstanceOf(ContextTokenResponse::class, $success);

        $loginRoute->login($requestDataBag, $salesChannelContext);
    }

    public function testLoginSuccessRestoreCustomerContext(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer($password, $email);
        $contextToken = Uuid::randomHex();

        $this->createCart($contextToken);

        $salesChannelContext = $this->createSalesChannelContext($contextToken, [], $customerId);

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

    public function testCustomerHaveDifferentCartsOnEachSalesChannel(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $customerId = $this->createCustomer($password, $email);

        $this->createSalesChannel([
            'id' => $this->ids->get('sales-channel-1'),
            'domains' => [
                [
                    'url' => 'http://test.de',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $this->createSalesChannel([
            'id' => $this->ids->get('sales-channel-2'),
            'domains' => [
                [
                    'url' => 'http://test.en',
                    'currencyId' => Defaults::CURRENCY,
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getRandomId('snippet_set'),
                ],
            ],
        ]);

        $salesChannelContext1 = $this->createSalesChannelContext($this->ids->get('context-1'), [], $customerId, $this->ids->get('sales-channel-1'));

        $salesChannelContext2 = $this->createSalesChannelContext($this->ids->get('context-2'), [], $customerId, $this->ids->get('sales-channel-2'));

        $this->createCart($this->ids->get('context-1'), 'cart-1');

        $this->createCart($this->ids->get('context-2'), 'cart-2');

        $loginRoute = $this->getContainer()->get(LoginRoute::class);

        $request = new RequestDataBag(['email' => $email, 'password' => $password]);

        $responseSalesChannel1 = $loginRoute->login($request, $salesChannelContext1);

        $responseSalesChannel2 = $loginRoute->login($request, $salesChannelContext2);

        static::assertNotEquals($responseSalesChannel1->getToken(), $responseSalesChannel2->getToken());

        $cartService = $this->getContainer()->get(CartService::class);

        $cartFromSalesChannel1 = $cartService->getCart($responseSalesChannel1->getToken(), $salesChannelContext1, 'cart-1', false);
        $cartFromSalesChannel2 = $cartService->getCart($responseSalesChannel2->getToken(), $salesChannelContext2, 'cart-2', false);

        static::assertNotEquals($cartFromSalesChannel1->getToken(), $cartFromSalesChannel2->getToken());
        static::assertNotEquals($cartFromSalesChannel1->getName(), $cartFromSalesChannel2->getName());
        static::assertEquals('cart-1', $cartFromSalesChannel1->getName());
        static::assertEquals('cart-2', $cartFromSalesChannel2->getName());
    }

    private function createCart(string $contextToken, ?string $cartName = CartService::SALES_CHANNEL): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $countryStatement = $connection->executeQuery('SELECT id FROM country WHERE active = 1 ORDER BY `position`');
        $defaultCountry = $countryStatement->fetchColumn();
        $defaultPaymentMethod = $connection->executeQuery('SELECT id FROM payment_method WHERE active = 1 ORDER BY `position`')->fetchColumn();
        $defaultShippingMethod = $connection->executeQuery('SELECT id FROM shipping_method WHERE active = 1')->fetchColumn();

        $connection->insert('cart', [
            'token' => $contextToken,
            'name' => $cartName,
            'cart' => serialize(new Cart($cartName, $contextToken)),
            'line_item_count' => 1,
            'rule_ids' => json_encode([]),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'country_id' => $defaultCountry,
            'price' => 1,
            'payment_method_id' => $defaultPaymentMethod,
            'shipping_method_id' => $defaultShippingMethod,
            'sales_channel_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function createSalesChannelContext(string $contextToken, array $salesChannelData, ?string $customerId, ?string $salesChannelId = null): SalesChannelContext
    {
        if ($customerId) {
            $salesChannelData[SalesChannelContextService::CUSTOMER_ID] = $customerId;
        }

        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            $salesChannelId ?? Defaults::SALES_CHANNEL,
            $salesChannelData
        );
    }

    private function createCustomer(string $password, ?string $email = null, ?string $boundSalesChannelId = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
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
                'country' => ['name' => 'Germany'],
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
            'boundSalesChannelId' => $boundSalesChannelId,
        ];

        $this->customerRepository->create([$customer], $this->ids->context);

        return $customerId;
    }
}
