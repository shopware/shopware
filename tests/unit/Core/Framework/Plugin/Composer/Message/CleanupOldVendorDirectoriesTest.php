<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Composer\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\Message\CleanupOldVendorDirectories;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupOldVendorDirectories::class)]
class CleanupOldVendorDirectoriesTest extends TestCase
{
    public function testDeduplicationId(): void
    {
        static::assertSame('cleanup-old-vendor-directories', (new CleanupOldVendorDirectories())->deduplicationId());
    }
}
