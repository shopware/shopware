<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\ScheduledTask\PruneMfaChallengesTask;

/**
 * @internal
 */
#[CoversClass(PruneMfaChallengesTask::class)]
class PruneMfaChallengesTaskTest extends TestCase
{
    public function testTaskName(): void
    {
        static::assertSame('admin_auth.prune_mfa_challenges', PruneMfaChallengesTask::getTaskName());
    }

    public function testTaskRunsHourly(): void
    {
        static::assertSame(3600, PruneMfaChallengesTask::getDefaultInterval());
    }
}
