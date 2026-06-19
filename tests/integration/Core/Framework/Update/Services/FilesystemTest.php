<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Update\Services;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Update\Services\Filesystem;

/**
 * @internal
 *
 * Exercises real directory permissions, so it lives in the integration suite (the
 * runner is non-root, so the write/permission branches are genuinely reachable).
 */
class FilesystemTest extends TestCase
{
    private string $baseDir;

    protected function setUp(): void
    {
        $this->baseDir = sys_get_temp_dir() . '/sw_update_fs_' . bin2hex(random_bytes(6));
        mkdir($this->baseDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeRecursively($this->baseDir);
    }

    #[TestDox('a writable directory yields no errors')]
    public function testWritableDirectory(): void
    {
        static::assertSame([], (new Filesystem())->checkSingleDirectoryPermissions($this->baseDir));
    }

    #[TestDox('a missing directory is created and yields no errors')]
    public function testCreatesMissingDirectory(): void
    {
        $dir = $this->baseDir . '/created';

        static::assertSame([], (new Filesystem())->checkSingleDirectoryPermissions($dir));
        static::assertDirectoryExists($dir);
    }

    #[TestDox('a non-writable directory is reported as an error')]
    public function testNonWritableDirectoryReported(): void
    {
        $dir = $this->baseDir . '/readonly';
        mkdir($dir, 0500);

        static::assertSame([$dir], (new Filesystem())->checkSingleDirectoryPermissions($dir));
    }

    #[TestDox('fixPermission makes a non-writable directory writable')]
    public function testFixPermissionMakesDirectoryWritable(): void
    {
        $dir = $this->baseDir . '/fixme';
        mkdir($dir, 0500);
        static::assertDirectoryIsNotWritable($dir);

        static::assertSame([], (new Filesystem())->checkSingleDirectoryPermissions($dir, true));
        static::assertDirectoryIsWritable($dir);
    }

    private function removeRecursively(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        @chmod($dir, 0775);
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeRecursively($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
