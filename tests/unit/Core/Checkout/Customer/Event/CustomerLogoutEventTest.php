<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerLogoutEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerLogoutEvent::class)]
class CustomerLogoutEventTest extends TestCase
{
    use MailRecipientNameTestBehaviour;

    public function testTheMailRecipientCarriesTheResolvedName(): void
    {
        $this->assertRecipientNames(
            fn (CustomerEntity $customer, SalesChannelContext $context) => new CustomerLogoutEvent($context, $customer)
        );
    }
}
