<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\SalesChannelContextStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelContextAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub\SalesChannelContextAwareEvent;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SalesChannelContextStorer::class)]
class SalesChannelContextStorerTest extends TestCase
{
    private AbstractSalesChannelContextFactory&MockObject $factory;

    private SalesChannelContextStorer $storer;

    protected function setUp(): void
    {
        $this->factory = $this->createMock(AbstractSalesChannelContextFactory::class);
        $this->storer = new SalesChannelContextStorer($this->factory);
    }

    public function testStoreWithNonAwareEventReturnsUnchanged(): void
    {
        $event = $this->createMock(FlowEventAware::class);

        $stored = $this->storer->store($event, ['existing' => 'value']);

        static::assertSame(['existing' => 'value'], $stored);
    }

    public function testStoreWithAuthenticatedContext(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getDomainId')->willReturn('domain-id');
        $salesChannelContext->method('getCustomerId')->willReturn('customer-id');
        $event = new SalesChannelContextAwareEvent('sales-channel-id', $salesChannelContext);

        $stored = $this->storer->store($event, []);

        static::assertSame('sales-channel-id', $stored[MailAware::SALES_CHANNEL_ID]);
        static::assertSame('domain-id', $stored[SalesChannelContextAware::SALES_CHANNEL_DOMAIN_ID]);
        static::assertSame('customer-id', $stored[SalesChannelContextAware::SALES_CHANNEL_CUSTOMER_ID]);
    }

    public function testStoreWithAnonymousContextDoesNotStoreCustomerId(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getDomainId')->willReturn('domain-id');
        $salesChannelContext->method('getCustomerId')->willReturn(null);
        $event = new SalesChannelContextAwareEvent('sales-channel-id', $salesChannelContext);

        $stored = $this->storer->store($event, []);

        static::assertSame('sales-channel-id', $stored[MailAware::SALES_CHANNEL_ID]);
        static::assertSame('domain-id', $stored[SalesChannelContextAware::SALES_CHANNEL_DOMAIN_ID]);
        static::assertArrayNotHasKey(SalesChannelContextAware::SALES_CHANNEL_CUSTOMER_ID, $stored);
    }

    public function testStoreWithNullDomainIdDoesNotStoreDomainId(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getDomainId')->willReturn(null);
        $salesChannelContext->method('getCustomerId')->willReturn(null);
        $event = new SalesChannelContextAwareEvent('sales-channel-id', $salesChannelContext);

        $stored = $this->storer->store($event, []);

        static::assertSame('sales-channel-id', $stored[MailAware::SALES_CHANNEL_ID]);
        static::assertArrayNotHasKey(SalesChannelContextAware::SALES_CHANNEL_DOMAIN_ID, $stored);
    }

    public function testRestoreWithoutSalesChannelIdDoesNothing(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), []);

        $this->factory->expects($this->never())->method('create');

        $this->storer->restore($storable);

        static::assertNull($storable->getData(SalesChannelContextAware::SALES_CHANNEL_CONTEXT));
    }

    public function testRestoreSkipsReconstructionForAuthenticatedContext(): void
    {
        $storable = new StorableFlow('name', Context::createDefaultContext(), [
            MailAware::SALES_CHANNEL_ID => 'sales-channel-id',
            SalesChannelContextAware::SALES_CHANNEL_CUSTOMER_ID => 'customer-id',
        ]);

        $this->factory->expects($this->never())->method('create');

        $this->storer->restore($storable);

        static::assertNull($storable->getData(SalesChannelContextAware::SALES_CHANNEL_CONTEXT));
    }

    public function testRestoreReconstructsAnonymousContextLazily(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->factory->method('create')->willReturn($salesChannelContext);

        $storable = new StorableFlow('name', Context::createDefaultContext(), [
            MailAware::SALES_CHANNEL_ID => 'sales-channel-id',
            SalesChannelContextAware::SALES_CHANNEL_DOMAIN_ID => 'domain-id',
        ]);

        $this->storer->restore($storable);

        static::assertSame($salesChannelContext, $storable->getData(SalesChannelContextAware::SALES_CHANNEL_CONTEXT));
    }
}
