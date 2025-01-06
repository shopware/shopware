<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\Service\WebhookManager;
use Shopware\Core\Framework\Webhook\WebhookDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[CoversClass(WebhookDispatcher::class)]
class WebhookDispatcherTest extends TestCase
{
    public function testDispatchDispatchesToInnerAndManager(): void
    {
        $e = new TestEvent();

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with($e, 'event')
            ->willReturnArgument(0);

        $webhookManager = $this->createMock(WebhookManager::class);
        $webhookManager->expects(static::once())->method('dispatch')->with($e);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcher,
            $webhookManager,
        );

        $webhookDispatcher->dispatch($e, 'event');
    }

    public function testDispatchReturnsSameEventAsDispatched(): void
    {
        $e = new TestEvent();

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with($e, 'event')
            ->willReturnArgument(0);

        $webhookManager = $this->createMock(WebhookManager::class);
        $webhookManager->expects(static::once())->method('dispatch')->with($e);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcher,
            $webhookManager,
        );

        static::assertSame(
            $e,
            $webhookDispatcher->dispatch($e, 'event')
        );
    }

    public function testManagerIsNotExecutedWhenExtensionsDisabled(): void
    {
        $_ENV['DISABLE_EXTENSIONS'] = true;

        $e = new \stdClass();

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher->expects(static::once())
            ->method('dispatch')
            ->with($e, 'event')
            ->willReturn($e);

        $webhookManager = $this->createMock(WebhookManager::class);
        $webhookManager->expects(static::never())->method('dispatch')->with($e);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcher,
            $webhookManager,
        );

        static::assertSame(
            $e,
            $webhookDispatcher->dispatch($e, 'event')
        );

        $_ENV['DISABLE_EXTENSIONS'] = false;
    }

    public function testAddSubscriberForwardsToInner(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public static function getSubscribedEvents(): array
            {
                return [];
            }
        };

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects(static::once())
            ->method('addSubscriber')
            ->with($subscriber);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            $this->createMock(WebhookManager::class),
        );

        $webhookDispatcher->addSubscriber($subscriber);
    }

    public function testRemoveSubscriberForwardsToInner(): void
    {
        $subscriber = new class implements EventSubscriberInterface {
            public static function getSubscribedEvents(): array
            {
                return [];
            }
        };

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects(static::once())
            ->method('removeSubscriber')
            ->with($subscriber);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            $this->createMock(WebhookManager::class),
        );

        $webhookDispatcher->removeSubscriber($subscriber);
    }

    public function testAddListenerForwardsToInner(): void
    {
        $listener = function (): void {};

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects(static::once())
            ->method('addListener')
            ->with('event', $listener, 5);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            $this->createMock(WebhookManager::class),
        );

        $webhookDispatcher->addListener('event', $listener, 5);
    }

    public function testRemoveListenerForwardsToInner(): void
    {
        $listener = function (): void {};

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects(static::once())
            ->method('removeListener')
            ->with('event', $listener);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            $this->createMock(WebhookManager::class),
        );

        $webhookDispatcher->removeListener('event', $listener);
    }

    public function testGetListenersForwardsToInner(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects(static::once())
            ->method('getListeners')
            ->with('event');

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            $this->createMock(WebhookManager::class),
        );

        $webhookDispatcher->getListeners('event');
    }

    public function testGetListenerPriorityForwardsToInner(): void
    {
        $listener = function (): void {};

        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects(static::once())
            ->method('getListenerPriority')
            ->with('event', $listener);

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            $this->createMock(WebhookManager::class),
        );

        $webhookDispatcher->getListenerPriority('event', $listener);
    }

    public function testHasListenersForwardsToInner(): void
    {
        $eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $eventDispatcherMock->expects(static::once())
            ->method('hasListeners')
            ->with('event');

        $webhookDispatcher = new WebhookDispatcher(
            $eventDispatcherMock,
            $this->createMock(WebhookManager::class),
        );

        $webhookDispatcher->hasListeners('event');
    }
}

/**
 * @internal
 */
class TestEvent implements Hookable
{
    public function getName(): string
    {
        return 'test';
    }

    public function getWebhookPayload(?AppEntity $app = null): array
    {
        return [];
    }

    public function isAllowed(string $appId, \Shopware\Core\Framework\Webhook\AclPrivilegeCollection $permissions): bool
    {
        return true;
    }
}
