<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerEntity::class)]
class CustomerEntityTest extends TestCase
{
    #[DataProvider('nameProvider')]
    public function testToString(string $accountType, string $firstName, string $lastName, ?string $company, string $expected): void
    {
        $customer = new CustomerEntity();
        $customer->setAccountType($accountType);
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);

        if ($company !== null) {
            $customer->setCompany($company);
        }

        static::assertSame($expected, (string) $customer);
    }

    public function testToStringWithoutAccountTypeUsesThePersonName(): void
    {
        $customer = new CustomerEntity();
        $customer->setFirstName('Max');
        $customer->setLastName('Mustermann');

        static::assertSame('Max Mustermann', (string) $customer);
    }

    /**
     * @return iterable<string, array{string, string, string, string|null, string}>
     */
    public static function nameProvider(): iterable
    {
        yield 'private account uses the person name' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, 'Max', 'Mustermann', null, 'Max Mustermann',
        ];

        yield 'private account ignores the company' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, 'Max', 'Mustermann', 'Acme GmbH', 'Max Mustermann',
        ];

        yield 'business account uses the company' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Max', 'Mustermann', 'Acme GmbH', 'Acme GmbH',
        ];

        yield 'business account without a company falls back to the person name' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Max', 'Mustermann', null, 'Max Mustermann',
        ];

        yield 'business account with a blank company falls back to the person name' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, 'Max', 'Mustermann', '   ', 'Max Mustermann',
        ];

        yield 'business account without a person name uses the company' => [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS, '', '', 'Acme GmbH', 'Acme GmbH',
        ];

        yield 'empty person name is not padded with a space' => [
            CustomerEntity::ACCOUNT_TYPE_PRIVATE, '', '', null, '',
        ];
    }
}
