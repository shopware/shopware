<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('customer-order')]
class CustomerBeforeDeleteSubscriberTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->customerRepository = $this->getContainer()->get('customer.repository');
    }

    public function testCustomerDeletedEventDispatched(): void
    {
        $email1 = Uuid::randomHex() . '@shopware.com';
        $email2 = Uuid::randomHex() . '@shopware.com';
        $password = 'ThisIsPassword';

        $customerId1 = $this->createCustomer($email1, $password);
        $customerId2 = $this->createCustomer($email2, $password);

        $context = Context::createDefaultContext();

        $caughtEvents = [];

        $listenerClosure = function (CustomerDeletedEvent $event) use (&$caughtEvents): void {
            $caughtEvents[] = $event;
        };

        $this->getContainer()->get('event_dispatcher')->addListener(CustomerDeletedEvent::class, $listenerClosure);

        $this->customerRepository->delete([
            ['id' => $customerId1],
            ['id' => $customerId2],
        ], $context);

        static::assertCount(2, $caughtEvents);

        $deleteCustomer1Event = null;
        $deleteCustomer2Event = null;

        foreach ($caughtEvents as $event) {
            static::assertInstanceOf(CustomerDeletedEvent::class, $event);
            static::assertInstanceOf(CustomerEntity::class, $event->getCustomer());

            if ($event->getCustomer()->getId() === $customerId1) {
                $deleteCustomer1Event = $event;

                continue;
            }

            if ($event->getCustomer()->getId() === $customerId2) {
                $deleteCustomer2Event = $event;
            }
        }
        static::assertInstanceOf(CustomerDeletedEvent::class, $deleteCustomer1Event);
        static::assertInstanceOf(CustomerDeletedEvent::class, $deleteCustomer2Event);
    }

    private function createCustomer(string $email, string $password): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $this->getContainer()->get('customer.repository')->create([
            [
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
                'defaultPaymentMethodId' => $this->getValidPaymentMethodId(),
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => null,
                'legacyPassword' => md5($password),
                'legacyEncoder' => 'Md5',
                'firstName' => 'encryption',
                'lastName' => 'Mustermann',
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], Context::createDefaultContext());

        return $customerId;
    }
}
