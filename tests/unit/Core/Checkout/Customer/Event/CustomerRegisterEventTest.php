<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerRegisterEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerRegisterEvent::class)]
class CustomerRegisterEventTest extends TestCase
{
    public function testTheMailRecipientCarriesTheResolvedName(): void
    {
        MailRecipientNameFixture::assertRecipientNames(
            fn (CustomerEntity $customer, SalesChannelContext $context) => new CustomerRegisterEvent($context, $customer)
        );
    }
}
