<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\Event\FlowEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

class AppFlowActionEventTest extends TestCase
{
    public function testGetter(): void
    {
        $eventName = 'AppFlowActionEvent';
        $appFlowActionId = Uuid::randomHex();
        $flowEvent = $this->createMock(FlowEvent::class);
        $flowEvent->method('getActionName')->willReturn($eventName);

        $event = new AppFlowActionEvent(
            $appFlowActionId,
            $flowEvent
        );

        static::assertEquals($appFlowActionId, $event->getAppFlowActionId());
        static::assertEquals($flowEvent, $event->getEvent());
        static::assertEquals($eventName, $event->getName());
        static::assertEquals([], $event->getWebhookPayload());
        static::assertTrue($event->isAllowed($appFlowActionId, new AclPrivilegeCollection([])));
    }
}
