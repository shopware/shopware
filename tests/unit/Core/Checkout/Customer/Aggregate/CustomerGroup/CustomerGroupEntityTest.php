<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Aggregate\CustomerGroup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(CustomerGroupEntity::class)]
#[Package('discovery')]
class CustomerGroupEntityTest extends TestCase
{
    public function testTranslatedPropertiesDefaultToNull(): void
    {
        $customerGroup = new CustomerGroupEntity();

        static::assertNull($customerGroup->getName());
        static::assertNull($customerGroup->getRegistrationTitle());
        static::assertNull($customerGroup->getRegistrationIntroduction());
        static::assertNull($customerGroup->getRegistrationOnlyCompanyRegistration());
        static::assertNull($customerGroup->getRegistrationSeoMetaDescription());
    }
}
