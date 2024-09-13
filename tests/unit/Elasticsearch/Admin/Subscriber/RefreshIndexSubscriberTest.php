<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Admin\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Event\RefreshIndexEvent;
use Shopware\Elasticsearch\Admin\AdminIndexingBehavior;
use Shopware\Elasticsearch\Admin\AdminSearchRegistry;
use Shopware\Elasticsearch\Admin\Subscriber\RefreshIndexSubscriber;

/**
 * @internal
 */
#[CoversClass(RefreshIndexSubscriber::class)]
class RefreshIndexSubscriberTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertArrayHasKey(RefreshIndexEvent::class, RefreshIndexSubscriber::getSubscribedEvents());
    }

    public function testHandedWithSkipOption(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects(static::once())->method('iterate')->with(new AdminIndexingBehavior(false, ['product']));

        $subscriber = new RefreshIndexSubscriber($registry);
        $subscriber->handled(new RefreshIndexEvent(false, ['product']));
    }

    public function testHandedWithOnlyOption(): void
    {
        $registry = $this->createMock(AdminSearchRegistry::class);
        $registry->expects(static::once())->method('iterate')->with(new AdminIndexingBehavior(false, [], ['product']));

        $subscriber = new RefreshIndexSubscriber($registry);
        $subscriber->handled(new RefreshIndexEvent(false, [], ['product']));
    }
}
