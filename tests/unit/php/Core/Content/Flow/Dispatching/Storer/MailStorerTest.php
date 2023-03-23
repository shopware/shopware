<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching\Storer;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Event\OrderStateMachineStateChangeEvent;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Storer\MailStorer;
use Shopware\Core\Content\Test\Flow\TestFlowBusinessEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Unit\Common\Stubs\Flow\DummyEvent;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Dispatching\Storer\MailStorer
 */
class MailStorerTest extends TestCase
{
    private MailStorer $storer;

    public function setUp(): void
    {
        $this->storer = new MailStorer();
    }

    public function testStoreWithAware(): void
    {
        $event = $this->createMock(OrderStateMachineStateChangeEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayHasKey(MailAware::MAIL_STRUCT, $stored);
        static::assertArrayHasKey(MailAware::SALES_CHANNEL_ID, $stored);
    }

    public function testStoreWithNotAware(): void
    {
        $event = $this->createMock(TestFlowBusinessEvent::class);
        $stored = [];
        $stored = $this->storer->store($event, $stored);
        static::assertArrayNotHasKey(MailAware::MAIL_STRUCT, $stored);
        static::assertArrayNotHasKey(MailAware::SALES_CHANNEL_ID, $stored);
    }

    public function testRestoreHasStored(): void
    {
        $store = [
            'recipients' => ['firstName' => 'test'],
            'bcc' => 'bcc',
            'cc' => 'cc',
        ];

        $flow = new StorableFlow('test', Context::createDefaultContext(), [MailAware::MAIL_STRUCT => $store]);

        $this->storer->restore($flow);

        static::assertTrue($flow->hasData(MailAware::MAIL_STRUCT));

        static::assertInstanceOf(MailRecipientStruct::class, $flow->getData(MailAware::MAIL_STRUCT));

        static::assertEquals('test', $flow->getData(MailAware::MAIL_STRUCT)->getRecipients()['firstName']);
        static::assertEquals('bcc', $flow->getData(MailAware::MAIL_STRUCT)->getBcc());
        static::assertEquals('cc', $flow->getData(MailAware::MAIL_STRUCT)->getCc());
    }

    public function testRestoreEmptyStored(): void
    {
        $mailStruct = new MailRecipientStruct(['firstName' => 'test']);
        $mailStruct->setBcc('bcc');
        $mailStruct->setCc('cc');

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

/**
 * @internal
 */
class MailEvent extends DummyEvent implements MailAware
{
    public function __construct(private readonly MailRecipientStruct $recipients)
    {
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->recipients;
    }

    public function getSalesChannelId(): ?string
    {
        return TestDefaults::SALES_CHANNEL;
    }
}
