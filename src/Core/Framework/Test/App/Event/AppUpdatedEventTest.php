<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Webhook\AclPrivilegeCollection;

class AppUpdatedEventTest extends TestCase
{
    public function testGetter(): void
    {
        $app = new AppEntity();
        $context = Context::createDefaultContext();
        $event = new AppUpdatedEvent(
            $app,
            Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml'),
            $context
        );

        static::assertEquals($app, $event->getApp());
        static::assertInstanceOf(Manifest::class, $event->getManifest());
        static::assertEquals($context, $event->getContext());
        static::assertEquals(AppUpdatedEvent::NAME, $event->getName());
        static::assertEquals([
            'appVersion' => '1.0.0',
        ], $event->getWebhookPayload());
    }

    public function testIsAllowed(): void
    {
        $appId = Uuid::randomHex();
        $app = (new AppEntity())
            ->assign(['id' => $appId]);
        $context = Context::createDefaultContext();
        $event = new AppInstalledEvent(
            $app,
            Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml'),
            $context
        );

        static::assertTrue($event->isAllowed($appId, new AclPrivilegeCollection([])));
        static::assertFalse($event->isAllowed(Uuid::randomHex(), new AclPrivilegeCollection([])));
    }
}
