<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cleanup;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTask;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTask
 */
class CleanupUnusedDownloadMediaTaskTest extends TestCase
{
    public function testGetTaskName(): void
    {
        static::assertEquals('product_download.media.cleanup', CleanupUnusedDownloadMediaTask::getTaskName());
    }

    public function testGetDefaultInterval(): void
    {
        static::assertEquals(2628000, CleanupUnusedDownloadMediaTask::getDefaultInterval());
    }
}
