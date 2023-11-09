<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\ScheduledTask;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\ScheduledTask\CollectEntityDataTask;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\ScheduledTask\CollectEntityDataTask
 */
#[Package('merchant-services')]
class CollectEntityDataTaskTest extends TestCase
{
    public function testItHandlesCorrectTask(): void
    {
        static::assertEquals('usage_data.entity_data.collect', CollectEntityDataTask::getTaskName());
    }

    public function testItIsRescheduledEvery24Hours(): void
    {
        static::assertEquals(60 * 60 * 24, CollectEntityDataTask::getDefaultInterval());
    }
}
