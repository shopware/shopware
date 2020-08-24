<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppDeactivatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

class AppDeactivatedEventTest extends TestCase
{
    public function testGetter(): void
    {
        $app = new AppEntity();
        $context = Context::createDefaultContext();
        $event = new AppDeactivatedEvent(
            $app,
            $context
        );

        static::assertEquals($app, $event->getApp());
        static::assertEquals($context, $event->getContext());
        static::assertEquals(AppDeactivatedEvent::NAME, $event->getName());
        static::assertEquals([], $event->getWebhookPayload());
    }

    public function testIsAllowed(): void
    {
        $appId = Uuid::randomHex();
        $app = (new AppEntity())
            ->assign(['id' => $appId]);
        $context = Context::createDefaultContext();
        $event = new AppDeactivatedEvent(
            $app,
            $context
        );

        static::assertTrue($event->isAllowed($appId, new AclPrivilegeCollection([])));
        static::assertFalse($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection([])));
    }
}
