<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Event\AppFlowActionEvent;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

/**
 * @internal
 */
#[CoversClass(AppFlowActionEvent::class)]
class AppFlowActionEventTest extends TestCase
{
    public function testGetter(): void
    {
        $eventName = 'AppFlowActionEvent';
        $headers = [
            'Content-Type' => 'application/json',
        ];
        $payload = [
            'name' => 'value',
        ];

        $event = new AppFlowActionEvent($eventName, $headers, $payload);

        static::assertSame($eventName, $event->getName());
        static::assertEquals($headers, $event->getWebhookHeaders());
        static::assertEquals($payload, $event->getWebhookPayload());
        static::assertTrue($event->isAllowed('11111', new AclPrivilegeCollection([])));
    }
}
