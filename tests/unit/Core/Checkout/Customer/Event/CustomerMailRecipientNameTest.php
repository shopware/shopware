<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Checkout\Customer\Event\DoubleOptInGuestOrderEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 *
 * The To: header name is built in the events, not in the mail template, so a nameless commercial
 * account would arrive as a bare space unless every one of them resolves the name.
 */
#[Package('checkout')]
#[CoversClass(CustomerRegisterEvent::class)]
#[CoversClass(CustomerLoginEvent::class)]
#[CoversClass(CustomerLogoutEvent::class)]
#[CoversClass(CustomerDeletedEvent::class)]
#[CoversClass(CustomerPasswordChangedEvent::class)]
#[CoversClass(CustomerAccountRecoverRequestEvent::class)]
#[CoversClass(CustomerDoubleOptInRegistrationEvent::class)]
#[CoversClass(CustomerGroupRegistrationAccepted::class)]
#[CoversClass(CustomerGroupRegistrationDeclined::class)]
#[CoversClass(DoubleOptInGuestOrderEvent::class)]
class CustomerMailRecipientNameTest extends TestCase
{
    #[DataProvider('eventProvider')]
    public function testACompanyAccountWithoutAContactPersonIsAddressedByItsCompany(string $event): void
    {
        $customer = $this->customer('', '', 'Acme GmbH');
        $customer->setDisplayName('Acme GmbH');

        static::assertSame(
            ['info@acme.example' => 'Acme GmbH'],
            $this->build($event, $customer)->getMailStruct()->getRecipients()
        );
    }

    #[DataProvider('eventProvider')]
    public function testAContactPersonIsStillAddressedByName(string $event): void
    {
        $customer = $this->customer('Ada', 'Lovelace', 'Acme GmbH');
        $customer->setDisplayName('Ada Lovelace');

        static::assertSame(
            ['info@acme.example' => 'Ada Lovelace'],
            $this->build($event, $customer)->getMailStruct()->getRecipients()
        );
    }

    /**
     * The display name is a runtime field, so an entity that never came through the data abstraction
     * layer carries nothing and the person name has to stand in.
     */
    #[DataProvider('eventProvider')]
    public function testAnUnresolvedCustomerFallsBackToThePersonName(string $event): void
    {
        static::assertSame(
            ['info@acme.example' => 'Ada Lovelace'],
            $this->build($event, $this->customer('Ada', 'Lovelace', 'Acme GmbH'))->getMailStruct()->getRecipients()
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function eventProvider(): iterable
    {
        yield 'register' => [CustomerRegisterEvent::class];
        yield 'login' => [CustomerLoginEvent::class];
        yield 'logout' => [CustomerLogoutEvent::class];
        yield 'deleted' => [CustomerDeletedEvent::class];
        yield 'password changed' => [CustomerPasswordChangedEvent::class];
        yield 'account recovery' => [CustomerAccountRecoverRequestEvent::class];
        yield 'double opt in registration' => [CustomerDoubleOptInRegistrationEvent::class];
        yield 'group registration accepted' => [CustomerGroupRegistrationAccepted::class];
        yield 'group registration declined' => [CustomerGroupRegistrationDeclined::class];
        yield 'guest order double opt in' => [DoubleOptInGuestOrderEvent::class];
    }

    private function build(string $event, CustomerEntity $customer): MailAware
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        // Two of the events read the shop name out of the sales channel translation on construction.
        $salesChannelContext->getSalesChannel()->setTranslated(['name' => 'Demostore']);

        return match ($event) {
            CustomerRegisterEvent::class => new CustomerRegisterEvent($salesChannelContext, $customer),
            CustomerLoginEvent::class => new CustomerLoginEvent($salesChannelContext, $customer, 'context-token'),
            CustomerLogoutEvent::class => new CustomerLogoutEvent($salesChannelContext, $customer),
            CustomerDeletedEvent::class => new CustomerDeletedEvent($salesChannelContext, $customer),
            CustomerPasswordChangedEvent::class => new CustomerPasswordChangedEvent($salesChannelContext, $customer),
            CustomerAccountRecoverRequestEvent::class => new CustomerAccountRecoverRequestEvent(
                $salesChannelContext,
                $this->recovery($customer),
                'https://example.com/reset'
            ),
            CustomerDoubleOptInRegistrationEvent::class => new CustomerDoubleOptInRegistrationEvent(
                $customer,
                $salesChannelContext,
                'https://example.com/confirm'
            ),
            CustomerGroupRegistrationAccepted::class => new CustomerGroupRegistrationAccepted(
                $customer,
                $this->customerGroup(),
                Context::createDefaultContext()
            ),
            CustomerGroupRegistrationDeclined::class => new CustomerGroupRegistrationDeclined(
                $customer,
                $this->customerGroup(),
                Context::createDefaultContext()
            ),
            DoubleOptInGuestOrderEvent::class => new DoubleOptInGuestOrderEvent(
                $customer,
                $salesChannelContext,
                'https://example.com/confirm'
            ),
            default => static::fail('unhandled event ' . $event),
        };
    }

    private function customer(string $firstName, string $lastName, string $company): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setId('customer-id');
        $customer->setUniqueIdentifier('customer-id');
        $customer->setEmail('info@acme.example');
        $customer->setAccountType(CustomerEntity::ACCOUNT_TYPE_BUSINESS);
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        $customer->setCompany($company);

        return $customer;
    }

    private function recovery(CustomerEntity $customer): CustomerRecoveryEntity
    {
        $recovery = new CustomerRecoveryEntity();
        $recovery->setId('recovery-id');
        $recovery->setUniqueIdentifier('recovery-id');
        $recovery->setCustomer($customer);

        return $recovery;
    }

    private function customerGroup(): CustomerGroupEntity
    {
        $group = new CustomerGroupEntity();
        $group->setId('group-id');
        $group->setUniqueIdentifier('group-id');

        return $group;
    }
}
