<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\EventDispatcherBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\Debug\TraceableEventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerGroupRegistrationActionControllerTest extends TestCase
{
    use EventDispatcherBehaviour;
    use IntegrationTestBehaviour;

    public const B2B_CUSTOMER_GROUP_NAME = 'B2B_GROUP';

    public function testDeclineDeclinedCustomerGroupIsSetCorrectly(): void
    {
        $requestedCustomerGroup = $this->createCustomerGroup();
        $customerId = $this->createCustomer($requestedCustomerGroup->getId());

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        static::assertInstanceOf(TraceableEventDispatcher::class, $eventDispatcher);
        $controller = $this->createController($eventDispatcher);

        $this->addEventListener(
            $eventDispatcher,
            CustomerGroupRegistrationDeclined::class,
            function (CustomerGroupRegistrationDeclined $event) use ($customerId, $requestedCustomerGroup): void {
                // Check requested customerGroup is set in event
                static::assertSame($customerId, $event->getCustomer()->getId());
                static::assertSame($requestedCustomerGroup->getId(), $event->getCustomerGroup()->getId());
                static::assertSame(self::B2B_CUSTOMER_GROUP_NAME, $event->getCustomerGroup()->getName());
            }
        );

        $request = new Request();
        $request->request->add(['customerIds' => [$customerId]]);

        $controller->decline($request, Context::createDefaultContext());

        // Make sure requested group id is set to null
        $criteria = new Criteria([$customerId]);
        $customerResult = $this->getContainer()->get('customer.repository')
            ->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(CustomerEntity::class, $customerResult);
        static::assertNull($customerResult->getRequestedGroupId());
    }

    private function createCustomer(string $requestedCustomerGroupId): string
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
                'city' => 'Schöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddressId' => $addressId,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'requestedGroupId' => $requestedCustomerGroupId,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => Uuid::randomHex(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'guest' => false,
            'salutationId' => null,
            'customerNumber' => '12345',
        ];

        $this->getContainer()->get('customer.repository')
            ->create([$customer], Context::createDefaultContext());

        return $customerId;
    }

    private function createCustomerGroup(): CustomerGroupEntity
    {
        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setId(Uuid::randomHex());
        $customerGroup->setName(self::B2B_CUSTOMER_GROUP_NAME);
        $customerGroup->setRegistrationActive(true);
        $customerGroup->setRegistrationOnlyCompanyRegistration(true);

        $this->getContainer()->get('customer_group.repository')
            ->create([$customerGroup->jsonSerialize()], Context::createDefaultContext());

        return $customerGroup;
    }

    private function createController(TraceableEventDispatcher $eventDispatcher): CustomerGroupRegistrationActionController
    {
        return new CustomerGroupRegistrationActionController(
            $this->getContainer()->get('customer.repository'),
            $this->getContainer()->get('customer_group.repository'),
            $eventDispatcher,
            $this->getContainer()->get(SalesChannelContextRestorer::class)
        );
    }
}
