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
use Shopware\Core\Framework\Routing\SessionContextTokenAccessor;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

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
        $subscriber = new CustomerTokenSubscriber($contextPersister, new RequestStack(), $this->sessionContextToken());
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
        $subscriber = new CustomerTokenSubscriber($contextPersister, new RequestStack(), $this->sessionContextToken());
        $event = new EntityDeletedEvent('customer', [
            new EntityWriteResult('deleted-customer', [], 'customer', EntityWriteResult::OPERATION_DELETE),
        ], Context::createDefaultContext());

        $subscriber->onCustomerDeleted($event);
    }

    public function testAPasswordChangeRotatesTheStorefrontSessionAndKeepsTheNewToken(): void
    {
        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomerId')->willReturn('customer-id');
        $context->method('getToken')->willReturn('old-token');
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');

        $request = new Request(attributes: [
            SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true,
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $context,
        ]);
        $session = new Session(new MockArraySessionStorage());
        $session->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'old-token');
        $request->setSession($session);

        $contextPersister = $this->createMock(SalesChannelContextPersister::class);
        $contextPersister->method('replace')->willReturn('new-token');
        $contextPersister->expects($this->once())
            ->method('revokeAllCustomerTokens')
            ->with('customer-id', 'new-token');

        $subscriber = new CustomerTokenSubscriber($contextPersister, new RequestStack([$request]), $this->sessionContextToken());
        $subscriber->onCustomerWritten(new EntityWrittenEvent('customer', [
            new EntityWriteResult('customer-id', ['id' => 'customer-id', 'password' => 'hash'], 'customer', EntityWriteResult::OPERATION_UPDATE),
        ], Context::createDefaultContext()));

        static::assertSame('new-token', $session->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertSame('new-token', $request->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    private function sessionContextToken(): SessionContextTokenAccessor
    {
        return new SessionContextTokenAccessor([], true, new StaticSystemConfigService());
    }
}
