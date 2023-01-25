<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Aware\RecipientsAware;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\RecipientsStorer;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\RecipientsStorer
 */
class RecipientsStorerTest extends TestCase
{
    private RecipientsStorer $storer;

    public function setUp(): void
    {
        $this->storer = new RecipientsStorer();
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(MailSentEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(RecipientsAware::RECIPIENTS, $stored);
    }

    public function testStoreWitNotAware(): void
    {
        $event = $this->createMock(TestFlowBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(RecipientsAware::RECIPIENTS, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $recipients = ['test'];

        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(true);

        $storable->expects(static::exactly(1))
            ->method('getStore')
            ->willReturn($recipients);

        $storable->expects(static::exactly(1))
            ->method('setData')
            ->with(RecipientsAware::RECIPIENTS, $recipients);

        $this->storer->restore($storable);
    }

    public function testRestoreEmptyStored(): void
    {
        $recipients = ['test'];

        /** @var MockObject&StorableFlow $storable */
        $storable = $this->createMock(StorableFlow::class);

        $storable->expects(static::exactly(1))
            ->method('hasStore')
            ->willReturn(false);

        $storable->expects(static::never())
            ->method('getStore');

        $storable->expects(static::never())
            ->method('setData');

        $this->storer->restore($storable);
    }
}
