<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationDeclined;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerGroupRegistrationDeclined::class)]
class CustomerGroupRegistrationDeclinedTest extends TestCase
{
    public function testTheMailRecipientCarriesTheResolvedName(): void
    {
        MailRecipientNameFixture::assertRecipientNames(
            fn (CustomerEntity $customer, SalesChannelContext $context) => new CustomerGroupRegistrationDeclined($customer, $this->customerGroup(), Context::createDefaultContext())
        );
    }

    private function customerGroup(): CustomerGroupEntity
    {
        $group = new CustomerGroupEntity();
        $group->setId('group-id');
        $group->setUniqueIdentifier('group-id');

        return $group;
    }
}
