<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('checkout')]
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

        $customerId1 = $this->createCustomer($email1);
        $customerId2 = $this->createCustomer($email2);

        $context = Context::createDefaultContext();

        $caughtEvents = [];

        $listenerClosure = function (Event $event) use (&$caughtEvents): void {
            $caughtEvents[] = $event;
        };

        $this->getContainer()->get('event_dispatcher')->addListener(CustomerDeletedEvent::class, $listenerClosure);

        $this->customerRepository->delete([
            ['id' => $customerId1],
            ['id' => $customerId2],
        ], $context);

        static::assertCount(2, $caughtEvents);

        foreach ($caughtEvents as $event) {
            static::assertInstanceOf(CustomerDeletedEvent::class, $event);
            static::assertContains($event->getCustomer()->getId(), [$customerId1, $customerId2]);
        }
    }

    private function createCustomer(string $email): string
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
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'firstName' => 'encryption',
            'lastName' => 'Mustermann',
            'salutationId' => $this->getValidSalutationId(),
            'customerNumber' => '12345',
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $customer['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $this->getContainer()->get('customer.repository')->create([$customer], Context::createDefaultContext());

        return $customerId;
    }
}
