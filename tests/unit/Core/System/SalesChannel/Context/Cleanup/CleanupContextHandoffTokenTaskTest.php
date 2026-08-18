<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\Cleanup\CleanupContextHandoffTokenTask;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupContextHandoffTokenTask::class)]
class CleanupContextHandoffTokenTaskTest extends TestCase
{
    public function testGetTaskName(): void
    {
        static::assertSame('context_handoff_token.cleanup', CleanupContextHandoffTokenTask::getTaskName());
    }

    public function testGetDefaultInterval(): void
    {
        static::assertSame(86400, CleanupContextHandoffTokenTask::getDefaultInterval());
    }

    public function testShouldRescheduleOnFailure(): void
    {
        static::assertTrue(CleanupContextHandoffTokenTask::shouldRescheduleOnFailure());
    }
}
