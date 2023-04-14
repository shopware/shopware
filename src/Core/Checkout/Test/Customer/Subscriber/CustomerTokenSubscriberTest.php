<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use function json_decode;

/**
 * @internal
 */
#[Package('customer-order')]
class CustomerTokenSubscriberTest extends TestCase
{
    use KernelTestBehaviour;
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use RequestStackTestBehaviour;

    private Connection $connection;

    private EntityRepository $customerRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testCustomerTokenSubscriber(): void
    {
        $customerId = $this->createCustomer();

        $this->connection->insert('sales_channel_api_context', [
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'token' => 'test',
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"customerId": "1234"}',
        ]);

        $this->customerRepository->update([
            [
                'id' => $customerId,
                'password' => 'fooo12345',
            ],
        ], Context::createDefaultContext());

        static::assertSame(
            [
                'customerId' => null,
                'billingAddressId' => null,
                'shippingAddressId' => null,
            ],
            json_decode((string) $this->connection->fetchOne('SELECT payload FROM sales_channel_api_context WHERE token = "test"'), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testCustomerTokenSubscriberStorefrontShouldStillBeLoggedIn(): void
    {
        $customerId = $this->createCustomer();

        $request = Request::create('/');
        $request->setSession(new Session(new MockArraySessionStorage()));

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getToken')->willReturn('test');
        $context->method('getCustomer')->willReturn((new CustomerEntity())->assign(['id' => $customerId]));
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $context);

        $this->getContainer()->get('request_stack')->push($request);

        $newToken = null;

        $context->method('assign')->withAnyParameters()->willReturnCallback(function ($array) use ($context, &$newToken) {
            $newToken = $array['token'];

            return $context;
        });

        $this->connection->insert('sales_channel_api_context', [
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'token' => 'test',
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"customerId": "1234"}',
        ]);

        $this->customerRepository->update([
            [
                'id' => $customerId,
                'password' => 'fooo12345',
            ],
        ], Context::createDefaultContext());

        static::assertNotNull($newToken);

        static::assertSame(
            [
                'customerId' => '1234',
            ],
            json_decode((string) $this->connection->fetchOne('SELECT payload FROM sales_channel_api_context WHERE token = ?', [$newToken]), true, 512, \JSON_THROW_ON_ERROR)
        );
    }

    public function testDeleteCustomer(): void
    {
        $customerId = $this->createCustomer();

        $this->connection->insert('sales_channel_api_context', [
            'customer_id' => Uuid::fromHexToBytes($customerId),
            'token' => 'test',
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'updated_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => '{"customerId": "1234"}',
        ]);

        $this->customerRepository->delete([
            [
                'id' => $customerId,
            ],
        ], Context::createDefaultContext());

        static::assertCount(0, $this->connection->fetchAllAssociative('SELECT * FROM sales_channel_api_context WHERE token = ?', ['test']));
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->customerRepository->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
