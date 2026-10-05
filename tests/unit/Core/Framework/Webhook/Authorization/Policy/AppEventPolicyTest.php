<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Event\AppActivatedEvent;
use Shopware\Core\Framework\App\Event\AppDeletedEvent;
use Shopware\Core\Framework\App\Event\AppPermissionsUpdated;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Policy\AppEventPolicy;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Webhook;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppEventPolicy::class)]
class AppEventPolicyTest extends TestCase
{
    private const APP_EVENTS = [
        'app.activated',
        'app.deactivated',
        'app.deleted',
        'app.installed',
        'app.updated',
        'app.permissions.updated',
        'app.config.changed',
    ];

    public function testHandlesEveryEvent(): void
    {
        static::assertTrue((new AppEventPolicy())->handles('product.written'));
        static::assertTrue((new AppEventPolicy())->handles('app.installed'));
    }

    #[DataProvider('appEventSubscriberProvider')]
    public function testOnlyAppsMaySubscribeToAppEvents(Subscriber $subscriber, bool $permitted): void
    {
        $policy = new AppEventPolicy();

        foreach (self::APP_EVENTS as $eventName) {
            static::assertSame($permitted, $policy->permitsSubscription($eventName, $subscriber), $eventName);
        }

        static::assertTrue($policy->permitsSubscription('product.written', $subscriber));
    }

    /**
     * @return \Generator<string, array{Subscriber, bool}>
     */
    public static function appEventSubscriberProvider(): \Generator
    {
        yield 'app' => [Subscriber::app(static::createStub(Manifest::class)), true];
        yield 'documentation' => [Subscriber::none(), true];
        yield 'admin' => [Subscriber::admin(), false];
        yield 'user' => [Subscriber::user(), false];
        yield 'integration' => [Subscriber::integration(), false];
        yield 'app integration' => [Subscriber::appIntegration(), false];
    }

    public function testWebhooksWithoutAnAppDoNotReceiveAppEvents(): void
    {
        $policy = new AppEventPolicy();
        $webhook = $this->webhook(appId: null);

        foreach (self::APP_EVENTS as $eventName) {
            static::assertFalse($policy->permitsDelivery($this->hookable($eventName), $webhook), $eventName);
        }

        static::assertTrue($policy->permitsDelivery($this->hookable('product.written'), $webhook));
    }

    public function testActiveAppsReceiveEveryEvent(): void
    {
        $policy = new AppEventPolicy();
        $webhook = $this->webhook(appId: 'app-id');

        static::assertTrue($policy->permitsDelivery($this->hookable('app.installed'), $webhook));
        static::assertTrue($policy->permitsDelivery($this->hookable('product.written'), $webhook));
    }

    public function testInactiveAppsOnlyReceiveLifecycleEvents(): void
    {
        $policy = new AppEventPolicy();
        $webhook = $this->webhook(appId: 'app-id', appActive: false);
        $context = Context::createDefaultContext();

        static::assertTrue($policy->permitsDelivery(new AppActivatedEvent(new AppEntity(), $context), $webhook));
        static::assertTrue($policy->permitsDelivery(new AppDeletedEvent('app-id', $context), $webhook));
        static::assertTrue($policy->permitsDelivery(new AppPermissionsUpdated('app-id', [], $context), $webhook));
        static::assertFalse($policy->permitsDelivery($this->hookable('app.config.changed'), $webhook));
        static::assertFalse($policy->permitsDelivery($this->hookable('product.written'), $webhook));
    }

    private function hookable(string $name): Hookable
    {
        $event = static::createStub(Hookable::class);
        $event->method('getName')->willReturn($name);

        return $event;
    }

    private function webhook(?string $appId, bool $appActive = true): Webhook
    {
        return new Webhook(
            id: 'webhook-id',
            webhookName: 'hook',
            eventName: 'app.installed',
            url: 'https://example.com',
            onlyLiveVersion: false,
            appId: $appId,
            appName: $appId === null ? null : 'app',
            appSourceType: null,
            appActive: $appActive,
            appVersion: null,
            appSecret: null,
            appAclRoleId: null,
        );
    }
}
