<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderCustomer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderCustomerEntity::class)]
class OrderCustomerEntityTest extends TestCase
{
    #[DataProvider('displayNameProvider')]
    public function testDisplayName(string $firstName, string $lastName, ?string $company, string $expected): void
    {
        static::assertSame($expected, $this->customer($firstName, $lastName, $company)->getDisplayName());
    }

    #[DataProvider('displayNameProvider')]
    public function testResolveDisplayName(string $firstName, string $lastName, ?string $company, string $expected): void
    {
        static::assertSame($expected, OrderCustomerEntity::resolveDisplayName($firstName, $lastName, $company));
    }

    public function testAnAssignedDisplayNameWinsOverTheLiveFields(): void
    {
        $customer = $this->customer('Ada', 'Lovelace', null);
        $customer->setDisplayName('Acme GmbH');

        static::assertSame('Acme GmbH', $customer->getDisplayName());

        $customer->setDisplayName(null);

        static::assertSame('Ada Lovelace', $customer->getDisplayName());
    }

    public function testDisplayNameOfAnEmptyEntity(): void
    {
        static::assertSame('', (new OrderCustomerEntity())->getDisplayName());
        static::assertSame('', (new OrderCustomerEntity())->getBuyerName());
    }

    #[DataProvider('buyerNameProvider')]
    public function testBuyerName(string $firstName, string $lastName, ?string $company, string $expected): void
    {
        static::assertSame($expected, $this->customer($firstName, $lastName, $company)->getBuyerName());
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

    /**
     * @return iterable<string, array{string, string, string|null, string}>
     */
    public static function buyerNameProvider(): iterable
    {
        yield 'person name without a company' => ['Ada', 'Lovelace', null, 'Ada Lovelace'];
        yield 'the company is appended to a person name' => ['Ada', 'Lovelace', 'Acme GmbH', 'Ada Lovelace - Acme GmbH'];
        yield 'a company already carried by the name is not repeated' => ['', 'Acme GmbH', 'Acme GmbH', 'Acme GmbH'];
        yield 'no contact person falls back to the company' => ['', '', 'Acme GmbH', 'Acme GmbH'];
        yield 'a blank company is ignored' => ['Ada', 'Lovelace', '   ', 'Ada Lovelace'];
        yield 'nothing at all stays empty' => ['', '', null, ''];
    }

    private function customer(string $firstName, string $lastName, ?string $company): OrderCustomerEntity
    {
        $customer = new OrderCustomerEntity();
        $customer->setUniqueIdentifier('order-customer-id');
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);
        $customer->setCompany($company);

        return $customer;
    }
}
