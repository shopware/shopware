<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerDisplayNameSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerDisplayNameSubscriber::class)]
class CustomerDisplayNameSubscriberTest extends TestCase
{
    public function testItSubscribesToBothCustomerLoadedEvents(): void
    {
        static::assertSame(
            [
                'customer.loaded' => 'onCustomerLoaded',
                'customer.partial_loaded' => 'onCustomerLoaded',
            ],
            CustomerDisplayNameSubscriber::getSubscribedEvents()
        );
    }

    /**
     * A partial read hands over PartialEntity instances, which carry none of the typed getters, and
     * the field is ApiAware so it has to be filled there too.
     */
    #[DataProvider('displayNameProvider')]
    public function testItResolvesTheNameOnAPartialRead(
        string $accountType,
        string $firstName,
        string $lastName,
        ?string $company,
        string $expected
    ): void {
        $customer = new PartialEntity();
        $customer->assign([
            'accountType' => $accountType,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'company' => $company,
        ]);

        $event = new PartialEntityLoadedEvent(new CustomerDefinition(), [$customer], Context::createDefaultContext());

        (new CustomerDisplayNameSubscriber())->onCustomerLoaded($event);

        static::assertSame($expected, $customer->get('displayName'));
    }

    public function testAPartialReadWithoutTheNameFieldsRendersNothing(): void
    {
        $customer = new PartialEntity();
        $customer->assign(['id' => 'customer-id']);

        $event = new PartialEntityLoadedEvent(new CustomerDefinition(), [$customer], Context::createDefaultContext());

        (new CustomerDisplayNameSubscriber())->onCustomerLoaded($event);

        static::assertSame('', $customer->get('displayName'));
    }

    #[DataProvider('displayNameProvider')]
    public function testItResolvesTheNameToRender(
        string $accountType,
        string $firstName,
        string $lastName,
        ?string $company,
        string $expected
    ): void {
        $customer = new CustomerEntity();
        $customer->setAccountType($accountType);
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);

        if ($company !== null) {
            $customer->setCompany($company);
        }

        $this->load($customer);

        static::assertSame($expected, $customer->getDisplayName());
    }

    /**
     * @return \Generator<string, array{string, string, string, string|null, string}>
     */
    public static function displayNameProvider(): \Generator
    {
        yield 'private account uses the person name' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, 'Ada', 'Lovelace', null, 'Ada Lovelace',
        ];

        yield 'private account ignores the company' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, 'Ada', 'Lovelace', 'Analytical Engines', 'Ada Lovelace',
        ];

        yield 'private account without a name renders nothing' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, '', '', 'Analytical Engines', '',
        ];

        yield 'company account keeps an existing contact person' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Ada', 'Lovelace', 'Analytical Engines', 'Ada Lovelace',
        ];

        yield 'company account without a contact person uses the company' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', 'Analytical Engines', 'Analytical Engines',
        ];

        yield 'company account without a company falls back to the person name' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Ada', 'Lovelace', null, 'Ada Lovelace',
        ];

        yield 'company account with a blank company falls back to the person name' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Ada', 'Lovelace', '   ', 'Ada Lovelace',
        ];

        yield 'company account with neither renders nothing' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', null, '',
        ];
    }

    public function testItDoesNotRequireAnAccountType(): void
    {
        $customer = new CustomerEntity();
        $customer->setFirstName('Ada');
        $customer->setLastName('Lovelace');

        $this->load($customer);

        static::assertSame('Ada Lovelace', $customer->getDisplayName());
    }

    public function testItFillsEveryCustomerInTheEvent(): void
    {
        $person = new CustomerEntity();
        $person->setAccountType(CustomerEntity::ACCOUNT_TYPE_PRIVATE);
        $person->setFirstName('Ada');
        $person->setLastName('Lovelace');

        $company = new CustomerEntity();
        $company->setAccountType(CustomerEntity::ACCOUNT_TYPE_BUSINESS);
        $company->setFirstName('');
        $company->setLastName('');
        $company->setCompany('Analytical Engines');

        $this->load($person, $company);

        static::assertSame('Ada Lovelace', $person->getDisplayName());
        static::assertSame('Analytical Engines', $company->getDisplayName());
    }

    private function load(CustomerEntity ...$customers): void
    {
        $event = new EntityLoadedEvent(
            new CustomerDefinition(),
            $customers,
            Context::createDefaultContext()
        );

        (new CustomerDisplayNameSubscriber())->onCustomerLoaded($event);
    }
}
