<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerMailNameSubscriber;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerMailNameSubscriber::class)]
class CustomerMailNameSubscriberTest extends TestCase
{
    public function testSubscribesToTheMailEvent(): void
    {
        static::assertArrayHasKey(MailBeforeValidateEvent::class, CustomerMailNameSubscriber::getSubscribedEvents());
    }

    #[DataProvider('customerProvider')]
    public function testRenderedName(string $accountType, string $firstName, string $lastName, ?string $company, string $expected): void
    {
        $customer = $this->customer($accountType, $firstName, $lastName, $company);
        $event = new MailBeforeValidateEvent([], Context::createDefaultContext(), ['customer' => $customer]);

        (new CustomerMailNameSubscriber())->onMailBeforeValidate($event);

        $rendered = $event->getTemplateData()['customer'];
        static::assertInstanceOf(CustomerEntity::class, $rendered);
        static::assertSame($expected, $rendered->getFirstName() . ' ' . $rendered->getLastName());
    }

    public function testTheCustomerHandedToTheEventIsNotMutated(): void
    {
        $customer = $this->customer(CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', 'Acme GmbH');
        $event = new MailBeforeValidateEvent([], Context::createDefaultContext(), ['customer' => $customer]);

        (new CustomerMailNameSubscriber())->onMailBeforeValidate($event);

        static::assertSame('', $customer->getLastName());
        static::assertNotSame($customer, $event->getTemplateData()['customer']);
    }

    public function testTemplateDataWithoutACustomerIsLeftAlone(): void
    {
        $event = new MailBeforeValidateEvent([], Context::createDefaultContext(), ['order' => 'something']);

        (new CustomerMailNameSubscriber())->onMailBeforeValidate($event);

        static::assertSame(['order' => 'something'], $event->getTemplateData());
    }

    /**
     * @return iterable<string, array{string, string, string, string|null, string}>
     */
    public static function customerProvider(): iterable
    {
        yield 'company account without a contact person renders the company' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', 'Acme GmbH', ' Acme GmbH',
        ];

        yield 'company account with a contact person is left alone' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Ada', 'Lovelace', 'Acme GmbH', 'Ada Lovelace',
        ];

        yield 'company account without a company is left alone' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', null, ' ',
        ];

        yield 'company account with a blank company is left alone' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', '   ', ' ',
        ];

        yield 'private account never borrows the company' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, '', '', 'Acme GmbH', ' ',
        ];
    }

    private function customer(string $accountType, string $firstName, string $lastName, ?string $company): CustomerEntity
    {
        $customer = new CustomerEntity();
        $customer->setUniqueIdentifier('customer-id');
        $customer->setAccountType($accountType);
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);

        if ($company !== null) {
            $customer->setCompany($company);
        }

        return $customer;
    }
}
