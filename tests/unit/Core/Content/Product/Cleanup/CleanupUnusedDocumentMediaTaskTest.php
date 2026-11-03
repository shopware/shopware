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
    public function testGetTaskName(): void
    {
        static::assertSame('product_document.media.cleanup', CleanupUnusedDocumentMediaTask::getTaskName());
    }

    public function testGetDefaultInterval(): void
    {
        static::assertSame(2628000, CleanupUnusedDocumentMediaTask::getDefaultInterval());
    }
}
