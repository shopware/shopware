<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Service\Event\PermissionsGrantedEvent;
use Shopware\Core\Service\Event\PermissionsRevokedEvent;
use Shopware\Core\Service\Manager;
use Shopware\Core\Service\Subscriber\PermissionsSubscriber;

/**
 * @internal
 */
#[CoversClass(PermissionsSubscriber::class)]
class PermissionsSubscriberTest extends TestCase
{
    private Manager&MockObject $manager;

    private PermissionsSubscriber $subscriber;

    private Context $context;

    protected function setUp(): void
    {
        $this->manager = $this->createMock(Manager::class);
        $this->subscriber = new PermissionsSubscriber($this->manager);
        $this->context = Context::createDefaultContext();
    }

    public function testEnableServices(): void
    {
        $revision = new \DateTimeImmutable('2025-06-13');
        $event = new PermissionsGrantedEvent($revision, $this->context);

        $this->manager
            ->expects($this->once())
            ->method('enable')
            ->with($this->context);

        $this->subscriber->enableServices($event);
    }

    public function testDisableServices(): void
    {
        $event = new PermissionsRevokedEvent($this->context);

        $this->manager
            ->expects($this->once())
            ->method('disable')
            ->with($this->context);

        $this->subscriber->disableServices($event);
    }
}
