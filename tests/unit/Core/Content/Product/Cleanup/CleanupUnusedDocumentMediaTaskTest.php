<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedDocumentMediaTask;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CleanupUnusedDocumentMediaTask::class)]
class CleanupUnusedDocumentMediaTaskTest extends TestCase
{
    public function testTaskMetadata(): void
    {
        static::assertSame('product_document.media.cleanup', CleanupUnusedDocumentMediaTask::getTaskName());
        static::assertSame(2628000, CleanupUnusedDocumentMediaTask::getDefaultInterval());
        static::assertTrue(CleanupUnusedDocumentMediaTask::shouldRescheduleOnFailure());
    }
}
