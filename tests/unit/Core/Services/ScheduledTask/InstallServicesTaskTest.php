<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Services\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Services\ScheduledTask\InstallServicesTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[CoversClass(InstallServicesTask::class)]
class InstallServicesTaskTest extends TestCase
{
    public function testMeta(): void
    {
        static::assertSame('services.install', InstallServicesTask::getTaskName());
        static::assertSame(86_400, InstallServicesTask::getDefaultInterval());

        static::assertTrue(InstallServicesTask::shouldRun(new ParameterBag()));
        static::assertFalse(InstallServicesTask::shouldRescheduleOnFailure());
    }
}
