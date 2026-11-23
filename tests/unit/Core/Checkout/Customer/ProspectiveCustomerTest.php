<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\ProspectiveAddress;
use Shopware\Core\Checkout\Customer\ProspectiveCustomer;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ProspectiveCustomer::class)]
class ProspectiveCustomerTest extends TestCase
{
    public function testTheRuleCustomerIsNotSerialized(): void
    {
        $address = new ProspectiveAddress('country-id', zipcode: '48624');

        $serialized = (new ProspectiveCustomer($address, null, new CustomerEntity()))->jsonSerialize();

        static::assertArrayNotHasKey('ruleCustomer', $serialized);
        static::assertSame($address, $serialized['shippingAddress']);
        static::assertNull($serialized['billingAddress']);
    }
}
