<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Composer\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Composer\Message\CleanupOldVendorDirectories;
use Shopware\Core\Framework\Plugin\Composer\Message\CleanupOldVendorDirectoriesHandler;
use Shopware\Core\Framework\Plugin\Composer\WorkingCopy;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupOldVendorDirectoriesHandler::class)]
class CleanupOldVendorDirectoriesHandlerTest extends TestCase
{
    public function testRemovesReplacedVendorDirectories(): void
    {
        $filesystem = new Filesystem();
        $projectDir = sys_get_temp_dir() . '/cleanup-old-vendor-' . bin2hex(random_bytes(6));
        $filesystem->dumpFile($projectDir . '/.vendor-old-replaced/autoload.php', 'old');
        $filesystem->dumpFile($projectDir . '/vendor/autoload.php', 'live');

        $handler = new CleanupOldVendorDirectoriesHandler(
            new WorkingCopy($projectDir, $filesystem, new LockFactory(new InMemoryStore()), new CollectingMessageBus())
        );

        try {
            $handler(new CleanupOldVendorDirectories());

            static::assertDirectoryDoesNotExist($projectDir . '/.vendor-old-replaced');
            static::assertFileExists($projectDir . '/vendor/autoload.php');
        } finally {
            $filesystem->remove($projectDir);
        }
    }
}
