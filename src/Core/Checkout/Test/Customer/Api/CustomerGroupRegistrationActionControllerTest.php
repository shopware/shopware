<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

class CustomerGroupRegistrationActionControllerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection(Context::createDefaultContext());
        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testAcceptRouteWithoutUser(): void
    {
        $browser = $this->createClient();

        $browser->request('POST', '/api/_action/customer-group-registration/accept/' . Defaults::CURRENCY);
        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame('Cannot find Customers', $json['errors'][0]['detail']);
    }

    public function testAcceptRouteWithoutCustomerId(): void
    {
        $browser = $this->createClient();

        $browser->request('POST', '/api/_action/customer-group-registration/accept');
        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame('customerId or customerIds parameter are missing', $json['errors'][0]['detail']);
    }

    public function testAcceptRouteWithoutRequestedGroup(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer();

        $browser->request('POST', '/api/_action/customer-group-registration/accept/' . $customer);
        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame('User ' . $customer . ' dont have approval', $json['errors'][0]['detail']);
    }

    public function testAccept(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer(true);

        $browser->request('POST', '/api/_action/customer-group-registration/accept/' . $customer);

        $criteria = new Criteria([$customer]);
        $criteria->addAssociation('group');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertNull($customerEntity->getRequestedGroupId());
        static::assertSame('foo', $customerEntity->getGroup()->getName());

        static::assertSame(204, $browser->getResponse()->getStatusCode());
    }

    public function testAcceptUsingCustomerIds(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer(true);

        $browser->request('POST', '/api/_action/customer-group-registration/accept', [
            'customerIds' => [$customer],
        ]);

        $criteria = new Criteria([$customer]);
        $criteria->addAssociation('group');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertNull($customerEntity->getRequestedGroupId());
        static::assertSame('foo', $customerEntity->getGroup()->getName());

        static::assertSame(204, $browser->getResponse()->getStatusCode());
    }

    public function testAcceptInBatch(): void
    {
        $browser = $this->createClient();
        $customerA = $this->createCustomer(true);
        $customerB = $this->createCustomer(true);

        $customerIds = [$customerA, $customerB];

        $browser->request('POST', '/api/_action/customer-group-registration/accept', [
            'customerIds' => $customerIds,
        ]);

        $criteria = new Criteria($customerIds);
        $criteria->addAssociation('group');

        $customerEntities = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->getElements();

        /** @var CustomerEntity $customerEntity */
        foreach ($customerEntities as $customerEntity) {
            static::assertNull($customerEntity->getRequestedGroupId());
            static::assertSame('foo', $customerEntity->getGroup()->getName());

            static::assertSame(204, $browser->getResponse()->getStatusCode());
        }
    }

    public function testDecline(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer(true);

        $browser->request('POST', '/api/_action/customer-group-registration/decline/' . $customer);

        $criteria = new Criteria([$customer]);
        $criteria->addAssociation('group');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertNull($customerEntity->getRequestedGroupId());
        static::assertNotSame('foo', $customerEntity->getGroup()->getName());

        static::assertSame(204, $browser->getResponse()->getStatusCode());
    }

    public function testDeclineInBatch(): void
    {
        $browser = $this->createClient();
        $customerA = $this->createCustomer(true);
        $customerB = $this->createCustomer(true);

        $customerIds = [$customerA, $customerB];

        $browser->request('POST', '/api/_action/customer-group-registration/decline', [
            'customerIds' => $customerIds,
        ]);

        $criteria = new Criteria($customerIds);
        $criteria->addAssociation('group');

        $customerEntities = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        /** @var CustomerEntity $customerEntity */
        foreach ($customerEntities as $customerEntity) {
            static::assertNull($customerEntity->getRequestedGroupId());
            static::assertNotSame('foo', $customerEntity->getGroup()->getName());

            static::assertSame(204, $browser->getResponse()->getStatusCode());
        }
    }

    public function testAcceptWithSilentError(): void
    {
        $browser = $this->createClient();
        $customerA = $this->createCustomer(true);
        $customerB = $this->createCustomer();

        $customerIds = [$customerA, $customerB];

        $browser->request('POST', '/api/_action/customer-group-registration/accept', [
            'customerIds' => $customerIds,
        ]);

        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $browser->getResponse()->getStatusCode());
        static::assertSame('User ' . $customerB . ' dont have approval', $json['errors'][0]['detail']);

        $browser->request('POST', '/api/_action/customer-group-registration/accept', [
            'customerIds' => $customerIds,
            'silentError' => true,
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());

        $criteria = new Criteria($customerIds);
        $criteria->addAssociation('group');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->get($customerA);

        static::assertNull($customerEntity->getRequestedGroupId());
        static::assertSame('foo', $customerEntity->getGroup()->getName());
    }

    public function testDeclineWithSilentError(): void
    {
        $browser = $this->createClient();
        $customerA = $this->createCustomer(true);
        $customerB = $this->createCustomer();

        $customerIds = [$customerA, $customerB];

        $browser->request('POST', '/api/_action/customer-group-registration/decline', [
            'customerIds' => $customerIds,
        ]);

        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $browser->getResponse()->getStatusCode());
        static::assertSame('User ' . $customerB . ' dont have approval', $json['errors'][0]['detail']);

        $browser->request('POST', '/api/_action/customer-group-registration/decline', [
            'customerIds' => $customerIds,
            'silentError' => true,
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $browser->getResponse()->getStatusCode());
    }

    private function createCustomer(bool $requestedGroup = false): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            array_merge([
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
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'salesChannels' => [
                        [
                            'id' => TestDefaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => Uuid::randomHex() . '@' . Uuid::randomHex() . '.de',
                'password' => Uuid::randomHex(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ], $requestedGroup ? ['requestedGroup' => ['name' => 'foo']] : []),
        ], $this->ids->context);

        return $customerId;
    }
}
