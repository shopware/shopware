<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\Message\CleanupOldCacheFolders;

/**
 * @internal
 */
#[CoversClass(CleanupOldCacheFolders::class)]
class CleanupOldCacheFoldersTest extends TestCase
{
    public function testDeduplicationId(): void
    {
        $message = new CleanupOldCacheFolders();
        static::assertSame('cleanup-old-cache-folders', $message->deduplicationId());
    }
}
