<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class AppInstalledEventTest extends TestCase
{
    public function testGetter(): void
    {
        $app = new AppEntity();
        $context = Context::createDefaultContext();
        $event = new AppInstalledEvent(
            $app,
            Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml'),
            $context
        );

        static::assertEquals($app, $event->getApp());
        static::assertInstanceOf(Manifest::class, $event->getManifest());
        static::assertEquals($context, $event->getContext());
        static::assertEquals(AppInstalledEvent::NAME, $event->getName());
        // ToDo reactivate tests once webhooks are migrated
//        static::assertEquals([
//            'appVersion' => '1.0.0',
//        ], $event->getWebhookPayload());
    }

//    public function testIsAllowed(): void
//    {
//        $appId = Uuid::randomHex();
//        $context = Context::createDefaultContext();
//        $event = new AppInstalledEvent(
//            $appId,
//            Manifest::createFromXmlFile(__DIR__ . '/../../Manifest/_fixtures/test/manifest.xml'),
//            $context
//        );
//
//        static::assertTrue($event->isAllowed($appId, new AclPrivilegeCollection()));
//        static::assertFalse($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection()));
//    }
}
