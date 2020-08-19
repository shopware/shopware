<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class AppDeletedEventTest extends TestCase
{
    public function testGetter(): void
    {
        $appId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $event = new AppDeletedEvent(
            $appId,
            $context
        );

        static::assertEquals($appId, $event->getAppId());
        static::assertEquals($context, $event->getContext());
        static::assertEquals(AppDeletedEvent::NAME, $event->getName());
        // ToDo reactivate tests once webhooks are migrated
//        static::assertEquals([], $event->getWebhookPayload());
    }

//    public function testIsAllowed(): void
//    {
//        $appId = Uuid::randomHex();
//        $context = Context::createDefaultContext();
//        $event = new AppDeletedEvent(
//            $appId,
//            $context
//        );
//
//        static::assertTrue($event->isAllowed($appId, new AclPrivilegeCollection()));
//        static::assertFalse($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection()));
//    }
}
