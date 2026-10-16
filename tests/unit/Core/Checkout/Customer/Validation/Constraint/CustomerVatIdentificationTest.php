<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Validation\Constraint;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentification;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerVatIdentification::class)]
class CustomerVatIdentificationTest extends TestCase
{
    public function testTheSalesChannelIsUnsetByDefault(): void
    {
        $constraint = new CustomerVatIdentification(countryId: Uuid::randomHex());

        static::assertNull($constraint->getSalesChannelId());
    }

    public function testTheSalesChannelIsKept(): void
    {
        $salesChannelId = Uuid::randomHex();

        $constraint = new CustomerVatIdentification(countryId: Uuid::randomHex(), salesChannelId: $salesChannelId);

        static::assertSame($salesChannelId, $constraint->getSalesChannelId());
    }
}
