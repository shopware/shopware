<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\ScheduledTask\CleanupCorruptedMediaTask;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CleanupCorruptedMediaTask::class)]
class CleanupCorruptedMediaTaskTest extends TestCase
{
    public function testGetTaskName(): void
    {
        static::assertSame('media.cleanup_corrupted_media', CleanupCorruptedMediaTask::getTaskName());
    }

    public function testGetDefaultInterval(): void
    {
        static::assertSame(86400, CleanupCorruptedMediaTask::getDefaultInterval());
    }
}
