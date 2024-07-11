<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Services\Event\ServiceOutdatedEvent;
use Shopware\Core\Services\ServiceLifecycle;
use Shopware\Core\Services\Subscriber\ServiceOutdatedSubscriber;

/**
 * @internal
 */
#[CoversClass(ServiceOutdatedSubscriber::class)]
class ServiceOutdatedSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        $subscriber = new ServiceOutdatedSubscriber(static::createMock(ServiceLifecycle::class));

        static::assertSame(
            [ServiceOutdatedEvent::class => 'updateService'],
            $subscriber->getSubscribedEvents()
        );
    }

    public function testUpdateServiceDelegatesToServiceLifecycle(): void
    {
        $context = new Context(new SystemSource());
        $serviceLifecycle = static::createMock(ServiceLifecycle::class);
        $serviceLifecycle->expects(static::once())
            ->method('update')
            ->with('MyCoolService', $context);

        $subscriber = new ServiceOutdatedSubscriber($serviceLifecycle);
        $subscriber->updateService(new ServiceOutdatedEvent('MyCoolService', $context));
    }
}
