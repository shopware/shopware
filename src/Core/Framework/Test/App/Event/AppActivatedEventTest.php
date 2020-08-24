<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

class AppActivatedEventTest extends TestCase
{
    public function testGetter(): void
    {
        $app = new AppEntity();
        $context = Context::createDefaultContext();
        $event = new AppActivatedEvent(
            $app,
            $context
        );

        static::assertEquals($app, $event->getApp());
        static::assertEquals($context, $event->getContext());
        static::assertEquals(AppActivatedEvent::NAME, $event->getName());
        static::assertEquals([], $event->getWebhookPayload());
    }

    public function testIsAllowed(): void
    {
        $appId = Uuid::randomHex();
        $app = (new AppEntity())
            ->assign(['id' => $appId]);
        $context = Context::createDefaultContext();
        $event = new AppActivatedEvent(
            $app,
            $context
        );

        static::assertTrue($event->isAllowed($appId, new AclPrivilegeCollection([])));
        static::assertFalse($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection([])));
    }
}
