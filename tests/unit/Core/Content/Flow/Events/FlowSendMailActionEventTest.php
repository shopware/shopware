<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Events;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\DataBag\DataBag;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Content\Flow\Events\FlowSendMailActionEvent
 */
class FlowSendMailActionEventTest extends TestCase
{
    public function testGetContextWithFlowEvent(): void
    {
        $event = $this->createMock(StorableFlow::class);
        $event->expects(static::once())
            ->method('getContext')
            ->willReturn(Context::createDefaultContext());

        $mailTemplate = new MailTemplateEntity();

        $mailEvent = new FlowSendMailActionEvent(new DataBag(), $mailTemplate, $event);
        $context = $mailEvent->getContext();

        static::assertEquals($context, Context::createDefaultContext());
    }

    public function testGetDataBag(): void
    {
        $mailTemplate = new MailTemplateEntity();
        $flowEvent = $this->createMock(StorableFlow::class);

        $expectDataBag = new DataBag(['data' => 'data']);
        $event = new FlowSendMailActionEvent($expectDataBag, $mailTemplate, $flowEvent);
        $actualDataBag = $event->getDataBag();

        static::assertEquals($actualDataBag, $expectDataBag);
    }

    public function testGetMailTemplate(): void
    {
        $mailTemplate = new MailTemplateEntity();
        $flowEvent = $this->createMock(StorableFlow::class);

        $event = new FlowSendMailActionEvent(new DataBag(), $mailTemplate, $flowEvent);

        static::assertEquals($mailTemplate, $event->getMailTemplate());
    }

    public function testGetStorableFlow(): void
    {
        $event = $this->createMock(StorableFlow::class);

        $mailTemplate = new MailTemplateEntity();

        $mailEvent = new FlowSendMailActionEvent(new DataBag(), $mailTemplate, $event);

        static::assertEquals($event, $mailEvent->getStorableFlow());
    }

    public function testGetStorableFlowHasOriginalFlowEvent(): void
    {
        /** @var StorableFlow&MockObject $event */
        $event = $this->createMock(StorableFlow::class);

        $mailTemplate = new MailTemplateEntity();
        $mailEvent = new FlowSendMailActionEvent(new DataBag(), $mailTemplate, $event);
        static::assertEquals($event, $mailEvent->getStorableFlow());
    }
}
