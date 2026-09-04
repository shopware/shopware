<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Order\Aggregate\OrderCustomer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerNameFormatter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderCustomerNameFormatter::class)]
class OrderCustomerNameFormatterTest extends TestCase
{
    #[DataProvider('buyerNameProvider')]
    public function testBuyerName(string $firstName, string $lastName, ?string $company, string $expected): void
    {
        $customer = new OrderCustomerEntity();
        $customer->setUniqueIdentifier('order-customer-id');
        $customer->setFirstName($firstName);
        $customer->setLastName($lastName);

        if ($company !== null) {
            $customer->setCompany($company);
        }

        static::assertSame($expected, OrderCustomerNameFormatter::buyerName($customer));
    }

    public function testBuyerNameWithoutAnOrderCustomer(): void
    {
        static::assertSame('', OrderCustomerNameFormatter::buyerName(null));
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
}
