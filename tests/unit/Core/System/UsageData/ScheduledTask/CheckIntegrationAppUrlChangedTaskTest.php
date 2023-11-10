<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\ScheduledTask;

use PHPUnit\Framework\TestCase;
use Shopware\Core\System\UsageData\ScheduledTask\CheckIntegrationChangedTask;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\ScheduledTask\CheckIntegrationChangedTask
 */
class CheckIntegrationAppUrlChangedTaskTest extends TestCase
{
    public function testItHandlesCorrectTask(): void
    {
        static::assertSame('usage_data.integration_changed', CheckIntegrationChangedTask::getTaskName());
    }

    public function testItIsRescheduledEvery24Hours(): void
    {
        static::assertSame(60 * 60 * 24, CheckIntegrationChangedTask::getDefaultInterval());
    }
}
