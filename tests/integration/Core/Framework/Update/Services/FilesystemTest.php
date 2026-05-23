<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Update\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Services\Filesystem;

/**
 * Lives in tests/integration because the class under test wraps native
 * filesystem calls (mkdir, chmod, is_writable). The scratch directory is
 * built up and torn down per test under _fixtures/, which is gitignored.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(Filesystem::class)]
class FilesystemTest extends TestCase
{
    private const SCRATCH = __DIR__ . '/_fixtures/scratch';

    protected function setUp(): void
    {
        $this->resetScratch();
    }

    protected function tearDown(): void
    {
        $this->resetScratch();
    }

    public function testReturnsEmptyForExistingWritableDirectory(): void
    {
        mkdir(self::SCRATCH);

        static::assertSame([], (new Filesystem())->checkSingleDirectoryPermissions(self::SCRATCH));
    }

    public function testCreatesMissingDirectoryAndReturnsEmpty(): void
    {
        static::assertDirectoryDoesNotExist(self::SCRATCH);

        $errors = (new Filesystem())->checkSingleDirectoryPermissions(self::SCRATCH);

        static::assertSame([], $errors);
        static::assertDirectoryExists(self::SCRATCH);
    }

    public function testFixesPermissionsWhenRequested(): void
    {
        mkdir(self::SCRATCH, 0o500);
        static::assertFalse(is_writable(self::SCRATCH));

        $errors = (new Filesystem())->checkSingleDirectoryPermissions(self::SCRATCH, fixPermission: true);

        static::assertSame([], $errors);
        static::assertTrue(is_writable(self::SCRATCH));
    }

    public function testReportsErrorWhenNotWritableAndFixDisabled(): void
    {
        mkdir(self::SCRATCH, 0o500);

        $errors = (new Filesystem())->checkSingleDirectoryPermissions(self::SCRATCH);

        static::assertSame([self::SCRATCH], $errors);
    }

    private function resetScratch(): void
    {
        if (is_dir(self::SCRATCH)) {
            @chmod(self::SCRATCH, 0o755);
            rmdir(self::SCRATCH);
        }
    }
}
