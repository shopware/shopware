<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\SalesChannelContextStorer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\SalesChannelContextAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub\SalesChannelContextAwareEvent;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(SalesChannelContextStorer::class)]
class SalesChannelContextStorerTest extends TestCase
{
    private SalesChannelContextStorer $storer;

    protected function setUp(): void
    {
        $this->storer = new SalesChannelContextStorer();
    }

    public function testStoreWithNonAwareEventReturnsUnchanged(): void
    {
        $event = $this->createMock(FlowEventAware::class);

        $stored = $this->storer->store($event, ['existing' => 'value']);

        static::assertSame(['existing' => 'value'], $stored);
    }

    public function testStoreWithAwareEventAndContext(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $event = new SalesChannelContextAwareEvent('sales-channel-id', $salesChannelContext);

        $stored = $this->storer->store($event, []);

        static::assertSame($salesChannelContext, $stored[SalesChannelContextAware::SALES_CHANNEL_CONTEXT]);
    }

    public function testRestoreWithContextSetsContextInData(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $storable = new StorableFlow('name', Context::createDefaultContext(), [
            SalesChannelContextAware::SALES_CHANNEL_CONTEXT => $salesChannelContext,
        ]);

        $this->storer->restore($storable);

        static::assertSame($salesChannelContext, $storable->getData(SalesChannelContextAware::SALES_CHANNEL_CONTEXT));
    }
}
