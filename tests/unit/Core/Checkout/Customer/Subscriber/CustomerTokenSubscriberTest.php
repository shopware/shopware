<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerTokenSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerTokenSubscriber::class)]
class CustomerTokenSubscriberTest extends TestCase
{
    public function testOnlyPasswordUpdatesRevokeCustomerTokens(): void
    {
        $contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $contextPersister->expects($this->once())
            ->method('revokeAllCustomerTokens')
            ->with('updated-password');
        $subscriber = new CustomerTokenSubscriber($contextPersister, new RequestStack());
        $context = Context::createDefaultContext();
        $event = new EntityWrittenEvent('customer', [
            new EntityWriteResult('inserted', ['id' => 'inserted', 'password' => 'hash'], 'customer', EntityWriteResult::OPERATION_INSERT),
            new EntityWriteResult('updated-email', ['id' => 'updated-email', 'email' => 'new@example.com'], 'customer', EntityWriteResult::OPERATION_UPDATE),
            new EntityWriteResult('updated-password', ['id' => 'updated-password', 'password' => 'hash'], 'customer', EntityWriteResult::OPERATION_UPDATE),
        ], $context);

        $subscriber->onCustomerWritten($event);
    }

    public function testDeletedCustomerTokensAreRevoked(): void
    {
        $contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $contextPersister->expects($this->once())
            ->method('revokeAllCustomerTokens')
            ->with('deleted-customer');
        $subscriber = new CustomerTokenSubscriber($contextPersister, new RequestStack());
        $event = new EntityDeletedEvent('customer', [
            new EntityWriteResult('deleted-customer', [], 'customer', EntityWriteResult::OPERATION_DELETE),
        ], Context::createDefaultContext());

        $subscriber->onCustomerDeleted($event);
    }
}
