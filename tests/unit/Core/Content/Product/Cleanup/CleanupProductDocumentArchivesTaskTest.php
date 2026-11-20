<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Cleanup\CleanupProductDocumentArchivesTask;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CleanupProductDocumentArchivesTask::class)]
class CleanupProductDocumentArchivesTaskTest extends TestCase
{
    public function testTaskMetadata(): void
    {
        static::assertSame('product_document.archive.cleanup', CleanupProductDocumentArchivesTask::getTaskName());
        static::assertSame(86400, CleanupProductDocumentArchivesTask::getDefaultInterval());
        static::assertTrue(CleanupProductDocumentArchivesTask::shouldRescheduleOnFailure());
    }
}
