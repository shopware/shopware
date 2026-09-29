<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Aggregate\CustomerGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CustomerGroupEntity::class)]
class CustomerGroupEntityTest extends TestCase
{
    public function testSetPriceBasisAcceptsAnExplicitBasis(): void
    {
        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setPriceBasis(CustomerGroupEntity::PRICE_BASIS_NET);

        static::assertSame(CustomerGroupEntity::PRICE_BASIS_NET, $customerGroup->getPriceBasis());
    }

    public function testSetPriceBasisRejectsNullWithTheMajor(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Passing null to CustomerGroupEntity::setPriceBasis() will not be possible from v6.8.0.0 on, pass "net" or "gross" instead.'
        ));

        (new CustomerGroupEntity())->setPriceBasis(null);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testSetPriceBasisStillAcceptsNullBeforeTheMajor(): void
    {
        $customerGroup = new CustomerGroupEntity();
        $customerGroup->setPriceBasis(null);

        static::assertNull($customerGroup->getPriceBasis());
    }
}
