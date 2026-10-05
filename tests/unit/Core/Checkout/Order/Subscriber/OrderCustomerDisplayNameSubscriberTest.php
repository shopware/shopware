<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Subscriber\OrderCustomerDisplayNameSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderCustomerDisplayNameSubscriber::class)]
class OrderCustomerDisplayNameSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                'order_customer.loaded' => 'loaded',
                'order_customer.partial_loaded' => 'loaded',
            ],
            OrderCustomerDisplayNameSubscriber::getSubscribedEvents()
        );
    }

    public function testLoadedFillsEveryEntity(): void
    {
        $person = new OrderCustomerEntity();
        $person->setUniqueIdentifier('person');
        $person->setFirstName('Ada');
        $person->setLastName('Lovelace');

        $company = new OrderCustomerEntity();
        $company->setUniqueIdentifier('company');
        $company->setFirstName('');
        $company->setLastName('');
        $company->setCompany('Acme GmbH');

        $event = new EntityLoadedEvent(new OrderCustomerDefinition(), [$person, $company], Context::createDefaultContext());

        (new OrderCustomerDisplayNameSubscriber())->loaded($event);

        static::assertSame('Ada Lovelace', $person->getDisplayName());
        static::assertSame('Acme GmbH', $company->getDisplayName());
    }

    #[DataProvider('displayNameProvider')]
    public function testLoadedResolvesTheName(string $firstName, string $lastName, ?string $company, string $expected): void
    {
        $customer = new OrderCustomerEntity();
        $customer->setUniqueIdentifier('customer');
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);

        if ($company !== null) {
            $customer->setCompany($company);
        }

        $event = new EntityLoadedEvent(new OrderCustomerDefinition(), [$customer], Context::createDefaultContext());

        (new OrderCustomerDisplayNameSubscriber())->loaded($event);

        static::assertSame($expected, $customer->getDisplayName());
    }

    /**
     * @return iterable<string, array{string, string, string|null, string}>
     */
    public static function displayNameProvider(): iterable
    {
        yield 'person name without a company' => ['Ada', 'Lovelace', null, 'Ada Lovelace'];
        yield 'person name wins over the company' => ['Ada', 'Lovelace', 'Acme GmbH', 'Ada Lovelace'];
        yield 'no contact person falls back to the company' => ['', '', 'Acme GmbH', 'Acme GmbH'];
        yield 'a blank contact person falls back to the company' => ['  ', '  ', 'Acme GmbH', 'Acme GmbH'];
        yield 'a single name is not padded' => ['', 'Lovelace', null, 'Lovelace'];
        yield 'nothing at all stays empty' => ['', '', null, ''];
        yield 'a blank company stays empty' => ['', '', '   ', ''];
    }

    public function testPartialLoadedFillsTheEntity(): void
    {
        $customer = new PartialEntity();
        $customer->assign(['firstName' => '', 'lastName' => '', 'company' => 'Acme GmbH']);

        $event = new PartialEntityLoadedEvent(new OrderCustomerDefinition(), [$customer], Context::createDefaultContext());

        (new OrderCustomerDisplayNameSubscriber())->loaded($event);

        static::assertSame('Acme GmbH', $customer->get('displayName'));
    }

    /**
     * @param array<string, string|null> $fields
     */
    #[DataProvider('partialWithoutSourcesProvider')]
    public function testPartialLoadedWithoutEverySourceLeavesTheEntityAlone(array $fields): void
    {
        $customer = new PartialEntity();
        $customer->assign($fields);

        $event = new PartialEntityLoadedEvent(new OrderCustomerDefinition(), [$customer], Context::createDefaultContext());

        (new OrderCustomerDisplayNameSubscriber())->loaded($event);

        static::assertFalse($customer->has('displayName'));
    }

    /**
     * @return iterable<string, array{array<string, string|null>}>
     */
    public static function partialWithoutSourcesProvider(): iterable
    {
        yield 'no sources' => [['id' => 'order-customer-id']];
        yield 'names without the company' => [['firstName' => '', 'lastName' => '']];
        yield 'company without the names' => [['company' => 'Acme GmbH']];
    }
}
