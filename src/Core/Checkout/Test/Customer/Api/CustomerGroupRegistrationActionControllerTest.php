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
use Shopware\Core\PlatformRequest;

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

        $browser->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/_action/customer-group-registration/accept/' . Defaults::CURRENCY);
        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame('Cannot find Customer', $json['errors'][0]['detail']);
    }

    public function testAcceptRouteWithoutRequestedGroup(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer();

        $browser->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/_action/customer-group-registration/accept/' . $customer);
        $json = json_decode($browser->getResponse()->getContent(), true);

        static::assertSame('User dont have approval', $json['errors'][0]['detail']);
    }

    public function testAccept(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer(true);

        $browser->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/_action/customer-group-registration/accept/' . $customer);

        $criteria = new Criteria([$customer]);
        $criteria->addAssociation('group');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertNull($customerEntity->getRequestedGroupId());
        static::assertSame('foo', $customerEntity->getGroup()->getName());

        static::assertSame(204, $browser->getResponse()->getStatusCode());
    }

    public function testDecline(): void
    {
        $browser = $this->createClient();
        $customer = $this->createCustomer(true);

        $browser->request('POST', '/api/v' . PlatformRequest::API_VERSION . '/_action/customer-group-registration/decline/' . $customer);

        $criteria = new Criteria([$customer]);
        $criteria->addAssociation('group');

        /** @var CustomerEntity $customerEntity */
        $customerEntity = $this->getContainer()->get('customer.repository')->search($criteria, Context::createDefaultContext())->first();

        static::assertNull($customerEntity->getRequestedGroupId());
        static::assertNotSame('foo', $customerEntity->getGroup()->getName());

        static::assertSame(204, $browser->getResponse()->getStatusCode());
    }

    private function createCustomer(bool $requestedGroup = false): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->customerRepository->create([
            array_merge([
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
                    'salesChannels' => [
                        [
                            'id' => Defaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
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
