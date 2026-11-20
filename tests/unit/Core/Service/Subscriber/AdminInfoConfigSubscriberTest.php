<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Service\LifecycleManager;
use Shopware\Core\Service\ServicesAvailability;
use Shopware\Core\Service\Subscriber\AdminInfoConfigSubscriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminInfoConfigSubscriber::class)]
class AdminInfoConfigSubscriberTest extends TestCase
{
    public function testSubscribesToTheAdminInfoConfig(): void
    {
        static::assertSame(
            [AdminInfoConfigEvent::class => 'addServicesConfig'],
            AdminInfoConfigSubscriber::getSubscribedEvents(),
        );
    }

    public function testAddsWhetherServicesAreAvailable(): void
    {
        $lifecycleManager = static::createStub(LifecycleManager::class);
        $lifecycleManager->method('availability')->willReturn(new ServicesAvailability(ServicesAvailability::AUTO, 'dev'));

        $event = new AdminInfoConfigEvent(['version' => '6.7.0.0']);

        (new AdminInfoConfigSubscriber($lifecycleManager))->addServicesConfig($event);

        static::assertSame(
            '{"version":"6.7.0.0","services":{"enabled":false,"setting":"auto"}}',
            json_encode($event->getConfig()),
        );
    }
}
