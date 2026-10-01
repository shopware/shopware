<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\ScheduledTask\UpdateTranslationsTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(UpdateTranslationsTask::class)]
class UpdateTranslationsTaskTest extends TestCase
{
    public function testTask(): void
    {
        static::assertSame('translation.update', UpdateTranslationsTask::getTaskName());
        static::assertSame(86400, UpdateTranslationsTask::getDefaultInterval());
        static::assertTrue(UpdateTranslationsTask::shouldRescheduleOnFailure());
    }

    public function testTaskDoesNotRunWhenDisabled(): void
    {
        static::assertFalse(UpdateTranslationsTask::shouldRun(new ParameterBag([
            'shopware.translation.scheduled_task.enabled' => false,
        ])));
    }
}
