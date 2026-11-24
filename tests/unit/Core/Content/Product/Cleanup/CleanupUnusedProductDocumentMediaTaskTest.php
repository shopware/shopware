<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Cleanup\CleanupUnusedProductDocumentMediaTask;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CleanupUnusedProductDocumentMediaTask::class)]
class CleanupUnusedProductDocumentMediaTaskTest extends TestCase
{
    public function testTaskMetadata(): void
    {
        static::assertSame('product_document.media.cleanup', CleanupUnusedProductDocumentMediaTask::getTaskName());
        static::assertSame(2628000, CleanupUnusedProductDocumentMediaTask::getDefaultInterval());
        static::assertTrue(CleanupUnusedProductDocumentMediaTask::shouldRescheduleOnFailure());
    }
}
