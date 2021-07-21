<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Flow;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Content\Flow\Action\FlowAction;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * @internal (FEATURE_NEXT_8225)
 */
class CallWebhookActionTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use SalesChannelApiTestBehaviour;
    use CountryAddToSalesChannelTestBehaviour;

    private const FLOW_ID = '5982d416a326447ebe495228b4cf6f61';
    private const SEQUENCE_ID = '5982d416a326447ebe495228b4cf6f62';

    private ?EntityRepositoryInterface $flowRepository;

    private ?Connection $connection;

    private KernelBrowser $browser;

    private TestDataCollection $ids;

    private ?EntityRepository $customerRepository;

    protected function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_8225', $this);

        $this->flowRepository = $this->getContainer()->get('flow.repository');

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->ids = new TestDataCollection(Context::createDefaultContext());

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->connection->executeStatement('DELETE FROM flow');
    }

    public function testWebhookActionGETMethod(): void
    {
        $this->resetHistory();
        $host = 'test.com';
        $config = [
            'description' => 'Test GET',
            'baseUrl' => 'https://' . $host . '/',
            'method' => 'GET',
            'authActive' => true,
            'options' => [
                'headers' => [
                    'Accept' => '*/*',
                    'User-Agent' => 'GuzzleHttp/7',
                ],
                'auth' => ['user', '123456'],
                'query' => [
                    'firstName' => 'Hello {{ customer.firstName }}',
                    'lastName' => 'Bye {{ customer.lastName }}',
                ],
            ],
        ];
        $this->createDataTest($config);

        $this->appendNewResponse(new Response(200));

        $this->createCustomerAndLogin();

        $request = $this->getLastRequest();
        static::assertEquals('GET', $request->getMethod());

        $headers = $request->getHeaders();
        static::assertEquals($config['options']['headers']['Accept'], $headers['Accept'][0]);
        static::assertEquals($config['options']['headers']['User-Agent'], $headers['User-Agent'][0]);
        static::assertEquals($host, $headers['Host'][0]);

        $authToken = 'Basic ' . base64_encode(sprintf('%s:%s', $config['options']['auth'][0], $config['options']['auth'][1]));
        static::assertEquals($authToken, $headers['Authorization'][0]);
    }

    public function methodPostPutPatchBody(): array
    {
        return [
            'type none' => [[
                'headers' => [
                    'Accept' => '*/*',
                    'User-Agent' => 'GuzzleHttp/7',
                ],
                'auth' => ['user', '123456'],
            ]],
            'type raw' => [[
                'headers' => [
                    'Accept' => '*/*',
                    'User-Agent' => 'GuzzleHttp/7',
                ],
                'auth' => ['user', '123456'],
                RequestOptions::BODY => 'Hello {{ customer.firstName }} {{ customer.lastName }}',
            ]],
            'type x-www-form-urlencoded' => [[
                'headers' => [
                    'Accept' => '*/*',
                    'User-Agent' => 'GuzzleHttp/7',
                ],
                'auth' => ['user', '123456'],
                RequestOptions::FORM_PARAMS => [
                    'firstName' => 'Hello {{ customer.firstName }}',
                    'lastName' => 'Bye {{ customer.lastName }}',
                ],
            ]],
        ];
    }

    /**
     * @dataProvider methodPostPutPatchBody
     */
    public function testWebhookActionPOSTMethod(array $options): void
    {
        $this->resetHistory();
        $host = 'test.com';
        $config = [
            'description' => 'Test POST',
            'baseUrl' => 'https://' . $host . '/',
            'method' => 'POST',
            'authActive' => true,
            'options' => $options,
        ];
        $this->createDataTest($config);

        $this->appendNewResponse(new Response(200));

        $this->createCustomerAndLogin();

        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());

        $headers = $request->getHeaders();
        static::assertEquals($config['options']['headers']['Accept'], $headers['Accept'][0]);
        static::assertEquals($config['options']['headers']['User-Agent'], $headers['User-Agent'][0]);
        static::assertEquals($host, $headers['Host'][0]);

        $authToken = 'Basic ' . base64_encode(sprintf('%s:%s', $config['options']['auth'][0], $config['options']['auth'][1]));
        static::assertEquals($authToken, $headers['Authorization'][0]);
    }

    /**
     * @dataProvider methodPostPutPatchBody
     */
    public function testWebhookActionPUTMethod(array $options): void
    {
        $this->resetHistory();
        $host = 'test.com';
        $config = [
            'description' => 'Test PUT',
            'baseUrl' => 'https://' . $host . '/',
            'method' => 'PUT',
            'authActive' => true,
            'options' => $options,
        ];
        $this->createDataTest($config);

        $this->appendNewResponse(new Response(200));

        $this->createCustomerAndLogin();

        $request = $this->getLastRequest();

        static::assertEquals('PUT', $request->getMethod());

        $headers = $request->getHeaders();
        static::assertEquals($config['options']['headers']['Accept'], $headers['Accept'][0]);
        static::assertEquals($config['options']['headers']['User-Agent'], $headers['User-Agent'][0]);
        static::assertEquals($host, $headers['Host'][0]);

        $authToken = 'Basic ' . base64_encode(sprintf('%s:%s', $config['options']['auth'][0], $config['options']['auth'][1]));
        static::assertEquals($authToken, $headers['Authorization'][0]);
    }

    /**
     * @dataProvider methodPostPutPatchBody
     */
    public function testWebhookActionPATCHMethod(array $options): void
    {
        $this->resetHistory();
        $host = 'test.com';
        $config = [
            'description' => 'Test PATCH',
            'baseUrl' => 'https://' . $host . '/',
            'method' => 'PATCH',
            'authActive' => true,
            'options' => $options,
        ];
        $this->createDataTest($config);

        $this->appendNewResponse(new Response(200));

        $this->createCustomerAndLogin();

        $request = $this->getLastRequest();

        static::assertEquals('PATCH', $request->getMethod());

        $headers = $request->getHeaders();
        static::assertEquals($config['options']['headers']['Accept'], $headers['Accept'][0]);
        static::assertEquals($config['options']['headers']['User-Agent'], $headers['User-Agent'][0]);
        static::assertEquals($host, $headers['Host'][0]);

        $authToken = 'Basic ' . base64_encode(sprintf('%s:%s', $config['options']['auth'][0], $config['options']['auth'][1]));
        static::assertEquals($authToken, $headers['Authorization'][0]);
    }

    public function testWebhookActionDELETEMethod(): void
    {
        $this->resetHistory();
        $host = 'test.com';
        $config = [
            'description' => 'Test DELETE',
            'baseUrl' => 'https://' . $host . '/100',
            'method' => 'DELETE',
            'authActive' => true,
            'options' => [
                'headers' => [
                    'Accept' => '*/*',
                    'User-Agent' => 'GuzzleHttp/7',
                ],
                'auth' => ['user', '123456'],
            ],
        ];
        $this->createDataTest($config);

        $this->appendNewResponse(new Response(200));

        $this->createCustomerAndLogin();

        $request = $this->getLastRequest();

        static::assertEquals('DELETE', $request->getMethod());

        $headers = $request->getHeaders();
        static::assertEquals($config['options']['headers']['Accept'], $headers['Accept'][0]);
        static::assertEquals($config['options']['headers']['User-Agent'], $headers['User-Agent'][0]);
        static::assertEquals($host, $headers['Host'][0]);

        $authToken = 'Basic ' . base64_encode(sprintf('%s:%s', $config['options']['auth'][0], $config['options']['auth'][1]));
        static::assertEquals($authToken, $headers['Authorization'][0]);
    }

    private function createCustomerAndLogin(?string $email = null, ?string $password = null): void
    {
        $email = $email ?? (Uuid::randomHex() . '@example.com');
        $password = $password ?? 'shopware';
        $this->createCustomer($password, $email);

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

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true);

        static::assertArrayHasKey('contextToken', $response);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $response['contextToken']);
    }

    private function createCustomer(string $password, ?string $email = null): void
    {
        $this->customerRepository->create([
            [
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
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
                'vatIds' => ['DE123456789'],
                'company' => 'Test',
            ],
        ], $this->ids->context);
    }

    private function createDataTest(array $config): void
    {
        $this->flowRepository->upsert([[
            'id' => self::FLOW_ID,
            'name' => 'User login',
            'eventName' => CustomerLoginEvent::EVENT_NAME,
            'priority' => 1,
            'active' => true,
            'sequences' => [
                [
                    'id' => self::SEQUENCE_ID,
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => FlowAction::CALL_WEBHOOK,
                    'config' => $config,
                    'position' => 1,
                    'trueCase' => true,
                ],
            ],
        ]], $this->ids->context);
    }
}
