<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Context\Gateway;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Context\Gateway\AppContextGateway;
use Shopware\Core\Framework\App\Hmac\RequestSigner;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal
 */
#[CoversClass(AppContextGateway::class)]
#[Package('checkout')]
class AppContextGatewayTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DatabaseTransactionBehaviour;
    use GuzzleTestClientBehaviour;
    use KernelTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->createPaymentMethods();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
            'paymentMethodId' => $this->ids->get('payment'),
            'paymentMethods' => [
                ['id' => $this->ids->get('payment')],
                ['id' => $this->ids->get('new-payment')],
            ],
        ]);

        $this->assignUSDCurrency();
    }

    public function testContextGatewayCanExecuteSimpleCommands(): void
    {
        $commands = [
            [
                'command' => 'context_change-payment-method',
                'payload' => [
                    'technicalName' => 'payment_new-test',
                ],
            ],
            [
                'command' => 'context_change-currency',
                'payload' => [
                    'iso' => 'USD',
                ],
            ],
        ];

        $this->executeCommands($commands);

        $this->browser->request('GET', '/store-api/context');

        $response = $this->browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame(200, $response->getStatusCode());

        $response = \json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);

        static::assertArrayHasKey('context', $response);
        static::assertArrayHasKey('currencyId', $response['context']);
        static::assertSame($this->getCurrencyIdByIso('USD'), $response['context']['currencyId']);

        static::assertArrayHasKey('paymentMethod', $response);
        static::assertArrayHasKey('id', $response['paymentMethod']);
        static::assertSame($this->ids->get('new-payment'), $response['paymentMethod']['id']);
    }

    public function testContextGatewayCanLoginCustomer(): void
    {
        $this->createCustomerByEmail('customer@example.com');

        $commands = [
            [
                'command' => 'context_login-customer',
                'payload' => [
                    'customerEmail' => 'customer@example.com',
                ],
            ],
            [
                'command' => 'context_change-currency',
                'payload' => [
                    'iso' => 'USD',
                ],
            ],
        ];

        $this->executeCommands($commands);

        $this->browser->request('GET', '/store-api/context');

        $response = $this->browser->getResponse();

        static::assertNotFalse($response->getContent());
        static::assertSame(200, $response->getStatusCode());

        $response = \json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);

        static::assertArrayHasKey('customer', $response);
        static::assertArrayHasKey('email', $response['customer']);
        static::assertSame('customer@example.com', $response['customer']['email']);

        static::assertArrayHasKey('context', $response);
        static::assertArrayHasKey('currencyId', $response['context']);
        static::assertSame($this->getCurrencyIdByIso('USD'), $response['context']['currencyId']);
    }

    public function testContextGatewayCanRegisterCustomer(): void
    {
        $commands = [
            [
                'command' => 'context_register-customer',
                'payload' => [
                    'data' => [
                        'id' => $this->ids->create('customer'),
                        'salesChannelId' => $this->ids->get('sales-channel'),
                        'firstName' => 'Max',
                        'lastName' => 'Mustermann',
                        'billingAddress' => [
                            'id' => $this->ids->create('address'),
                            'firstName' => 'Max',
                            'lastName' => 'Mustermann',
                            'street' => 'Musterstraße 1',
                            'city' => 'Schöppingen',
                            'zipcode' => '12345',
                            'salutationId' => $this->getValidSalutationId(),
                            'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
                        ],
                        'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                        'email' => 'customer@example.com',
                        'password' => TestDefaults::HASHED_PASSWORD,
                        'salutationId' => $this->getValidSalutationId(),
                        'customerNumber' => '12345',
                        'vatIds' => ['DE123456789'],
                        'company' => 'Test',
                        'storefrontUrl' => 'http://localhost',
                    ],
                ],
            ],
            [
                'command' => 'context_change-currency',
                'payload' => [
                    'iso' => 'USD',
                ],
            ],
        ];

        $this->executeCommands($commands);

        $this->browser->request('GET', '/store-api/context');

        $response = $this->browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertNotFalse($response->getContent());

        $response = \json_decode($response->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);

        static::assertArrayHasKey('customer', $response);
        static::assertArrayHasKey('email', $response['customer']);
        static::assertSame('customer@example.com', $response['customer']['email']);

        static::assertArrayHasKey('context', $response);
        static::assertArrayHasKey('currencyId', $response['context']);
        static::assertSame($this->getCurrencyIdByIso('USD'), $response['context']['currencyId']);
    }

    /**
     * @param array<array{command: string, payload: array<string,mixed>}> $commands
     */
    private function executeCommands(array $commands): string
    {
        $this->loadAppsFromDir(__DIR__ . '/../_fixtures/testGateway');

        $app = $this->fetchApp('testGateway');

        static::assertNotNull($app);
        static::assertSame('https://foo.bar/example/context', $app->getContextGatewayUrl());

        $body = \json_encode($commands, flags: \JSON_THROW_ON_ERROR);

        static::assertNotNull($app->getAppSecret());

        $secret = \hash_hmac('sha256', $body, $app->getAppSecret());

        $this->appendNewResponse(new Response(200, [RequestSigner::SHOPWARE_APP_SIGNATURE => $secret], $body));
        $this->browser->request('POST', '/store-api/context/gateway', [
            'appName' => 'testGateway',
        ]);

        $response = $this->browser->getResponse();

        static::assertSame(200, $response->getStatusCode());
        static::assertTrue($response->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $token = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);

        static::assertNotNull($token);

        $this->browser->setServerParameter('HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN, $token);

        return $token;
    }

    private function createPaymentMethods(): void
    {
        $payments = [
            [
                'id' => $this->ids->create('payment'),
                'name' => 'Payment 1',
                'technicalName' => 'payment_test',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
            ],
            [
                'id' => $this->ids->create('new-payment'),
                'name' => 'Payment 2',
                'technicalName' => 'payment_new-test',
                'active' => true,
                'handlerIdentifier' => TestPaymentHandler::class,
            ],
        ];

        static::getContainer()
            ->get('payment_method.repository')
            ->create($payments, Context::createDefaultContext());
    }

    private function assignUSDCurrency(): void
    {
        $usd = $this->getCurrencyIdByIso('USD');

        static::getContainer()
            ->get('currency.repository')
            ->upsert([[
                'id' => $usd,
                'salesChannels' => [
                    ['id' => $this->ids->get('sales-channel')],
                ],
            ]], Context::createDefaultContext());
    }

    private function createCustomerByEmail(?string $email = null): void
    {
        $customer = [
            'id' => $this->ids->create('customer'),
            'salesChannelId' => $this->ids->get('sales-channel'),
            'defaultShippingAddress' => [
                'id' => $this->ids->create('address'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
            ],
            'defaultBillingAddressId' => $this->ids->get('address'),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
            'vatIds' => ['DE123456789'],
            'company' => 'Test',
        ];

        static::getContainer()->get('customer.repository')->create([$customer], Context::createDefaultContext());
    }

    private function fetchApp(string $appName): ?AppEntity
    {
        /** @var EntityRepository<AppCollection> $appRepository */
        $appRepository = static::getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $appName));

        return $appRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
    }
}
