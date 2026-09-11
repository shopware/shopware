<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerPasswordChangedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerPasswordChangedEvent::class)]
class CustomerPasswordChangedEventTest extends TestCase
{
    use MailRecipientNameTestBehaviour;

    public function testTheMailRecipientCarriesTheResolvedName(): void
    {
        $this->assertRecipientNames(
            fn (CustomerEntity $customer, SalesChannelContext $context) => new CustomerPasswordChangedEvent($context, $customer)
        );
    }
}
