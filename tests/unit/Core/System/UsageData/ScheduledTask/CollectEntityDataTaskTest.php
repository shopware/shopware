<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\ScheduledTask\CollectEntityDataTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(CollectEntityDataTask::class)]
class CollectEntityDataTaskTest extends TestCase
{
    public function testItHandlesCorrectTask(): void
    {
        static::assertSame('usage_data.entity_data.collect', CollectEntityDataTask::getTaskName());
    }

    public function testItIsRescheduledEvery24Hours(): void
    {
        static::assertSame(60 * 60 * 24, CollectEntityDataTask::getDefaultInterval());
    }

    public function testShouldRunWhenNotAirGapped(): void
    {
        static::assertTrue(CollectEntityDataTask::shouldRun(new ParameterBag()));
        static::assertTrue(CollectEntityDataTask::shouldRun(new ParameterBag([
            'shopware.deployment.air_gapped' => false,
        ])));
    }

    public function testShouldNotRunWhenAirGapped(): void
    {
        static::assertFalse(CollectEntityDataTask::shouldRun(new ParameterBag([
            'shopware.deployment.air_gapped' => true,
        ])));
    }
}
