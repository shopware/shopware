<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ScheduledTask\UpdateAppsTask;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[CoversClass(UpdateAppsTask::class)]
class UpdateAppsTaskTest extends TestCase
{
    public function testTask(): void
    {
        $task = new UpdateAppsTask();

        static::assertSame('app_update', $task::getTaskName());
        static::assertSame(86400, $task::getDefaultInterval());
        static::assertTrue($task::shouldRescheduleOnFailure());

        $c = new ContainerBuilder();
        $c->setParameter('shopware.deployment.runtime_extension_management', true);
        static::assertTrue($task::shouldRun($c->getParameterBag()));

        $c->setParameter('shopware.deployment.runtime_extension_management', false);
        static::assertFalse($task::shouldRun($c->getParameterBag()));
    }
}
