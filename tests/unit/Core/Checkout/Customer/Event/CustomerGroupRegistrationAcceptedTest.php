<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerGroupRegistrationAccepted;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerGroupRegistrationAccepted::class)]
class CustomerGroupRegistrationAcceptedTest extends TestCase
{
    use MailRecipientNameTestBehaviour;

    public function testTheMailRecipientCarriesTheResolvedName(): void
    {
        $this->assertRecipientNames(
            fn (CustomerEntity $customer, SalesChannelContext $context) => new CustomerGroupRegistrationAccepted($customer, $this->customerGroup(), Context::createDefaultContext())
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
