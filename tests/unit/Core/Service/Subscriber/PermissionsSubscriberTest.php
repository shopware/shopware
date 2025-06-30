<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\Subscriber\PermissionsSubscriber;

/**
 * @internal
 */
#[CoversClass(PermissionsSubscriber::class)]
class PermissionsSubscriberTest extends TestCase
{
    private LifecycleManager&MockObject $manager;

    private PermissionsSubscriber $subscriber;

    private Context $context;

    protected function setUp(): void
    {
        $this->manager = $this->createMock(LifecycleManager::class);
        $this->subscriber = new PermissionsSubscriber($this->manager);
        $this->context = Context::createDefaultContext();
    }

    public function testEnableServices(): void
    {
        $revision = new \DateTimeImmutable('2025-06-13');
        $event = new PermissionsGrantedEvent($revision, $this->context);

        $this->manager
            ->expects($this->once())
            ->method('start')
            ->with($this->context);

        $this->subscriber->startServices($event);
    }

    public function testDisableServices(): void
    {
        $event = new PermissionsRevokedEvent($this->context);

        $this->manager
            ->expects($this->once())
            ->method('stop')
            ->with($this->context);

        $this->subscriber->stopServices($event);
    }
}
