<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\SalesChannel;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\Exception\CustomerNotFoundException;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\ContextTokenResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[Package('checkout')]
#[Group('store-api')]
class LoginRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    /**
     * @var EntityRepository<CustomerCollection>
     */
    private EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

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
                    'password' => 'foo12345',
                ]
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('CHECKOUT__CUSTOMER_AUTH_BAD_CREDENTIALS', $response['errors'][0]['code']);
    }

    public function testValidLogin(): void
    {
        $email = Uuid::randomHex() . '@exämple.com';
        $this->createCustomer($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    public function testItNotUpdatesCustomerLanguageIdOnValidLogin(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($email, null, true, $this->getDeDeLanguageId());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ],
            );

        static::assertEquals(
            $this->getDeDeLanguageId(),
            $this->customerRepository->search(
                new Criteria([$customerId]),
                Context::createDefaultContext()
            )->getEntities()->first()?->getLanguageId()
        );
    }

    public function testValidLoginWithOneInactive(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        // Inactive user with different password
        $this->createCustomer($email, null, false);
        // Active user with correct password
        $this->createCustomer($email);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    public function testLoginWithInvalidBoundSalesChannelId(): void
    {
        static::expectException(CustomerNotFoundException::class);

        $email = Uuid::randomHex() . '@example.com';
        $salesChannel = $this->createSalesChannel([
            'id' => Uuid::randomHex(),
        ]);

        $salesChannelContext = $this->createSalesChannelContext(Uuid::randomHex(), [
            'id' => Uuid::randomHex(),
        ], null);

        $this->createCustomer($email, $salesChannel['id']);

        $loginRoute = $this->getContainer()->get(LoginRoute::class);

        $requestDataBag = new RequestDataBag(['email' => $email, 'password' => 'shopware']);

        $success = $loginRoute->login($requestDataBag, $salesChannelContext);
        static::assertInstanceOf(ContextTokenResponse::class, $success);

        $loginRoute->login($requestDataBag, $salesChannelContext);
    }

    public function testLoginSuccessRestoreCustomerContext(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer($email);
        $contextToken = Uuid::randomHex();

        $salesChannelContext = $this->createSalesChannelContext($contextToken, [], $customerId);
        $this->createCart($contextToken, $salesChannelContext);

        $loginRoute = $this->getContainer()->get(LoginRoute::class);

        $request = new RequestDataBag(['email' => $email, 'password' => 'shopware']);

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
        $customerId = $this->createCustomer($email);

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

        $this->createCart($this->ids->get('context-1'), $salesChannelContext1);

        $this->createCart($this->ids->get('context-2'), $salesChannelContext2);

        $loginRoute = $this->getContainer()->get(LoginRoute::class);

        $request = new RequestDataBag(['email' => $email, 'password' => 'shopware']);

        $responseSalesChannel1 = $loginRoute->login($request, $salesChannelContext1);

        $responseSalesChannel2 = $loginRoute->login($request, $salesChannelContext2);

        static::assertNotEquals($responseSalesChannel1->getToken(), $responseSalesChannel2->getToken());

        $cartService = $this->getContainer()->get(CartService::class);

        $cartFromSalesChannel1 = $cartService->getCart($responseSalesChannel1->getToken(), $salesChannelContext1, false);
        $cartFromSalesChannel2 = $cartService->getCart($responseSalesChannel2->getToken(), $salesChannelContext2, false);

        static::assertNotEquals($cartFromSalesChannel1->getToken(), $cartFromSalesChannel2->getToken());
    }

    private function createCart(string $contextToken, SalesChannelContext $context): void
    {
        $persister = $this->getContainer()->get(CartPersister::class);

        $persister->save(new Cart($contextToken), $context);
    }

    /**
     * @param array<string, mixed> $salesChannelData
     */
    private function createSalesChannelContext(string $contextToken, array $salesChannelData, ?string $customerId, ?string $salesChannelId = null): SalesChannelContext
    {
        if ($customerId) {
            $salesChannelData[SalesChannelContextService::CUSTOMER_ID] = $customerId;
        }

        return $this->getContainer()->get(SalesChannelContextFactory::class)->create(
            $contextToken,
            $salesChannelId ?? TestDefaults::SALES_CHANNEL,
            $salesChannelData
        );
    }

    private function createCustomer(?string $email = null, ?string $boundSalesChannelId = null, bool $active = true, ?string $languageId = null): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
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
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
            'boundSalesChannelId' => $boundSalesChannelId,
            'active' => $active,
        ];

        if ($languageId !== null) {
            $customer['languageId'] = $languageId;
        }

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $this->customerRepository->create([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
