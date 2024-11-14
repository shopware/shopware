<?php declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\CleanupFiles;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[CoversClass(CleanupFiles::class)]
class CleanupFilesTest extends TestCase
{
    public function testCleanupFilesDoesNotFilesTheyDontMatch(): void
    {
        $fs = new Filesystem();

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('cleanup-files-test', true);

        $fs->mkdir($tmpDir);

        $hashContaingFile = Path::join($tmpDir, 'config/packages/shopware.yaml');
        $fileNotMentioned = Path::join($tmpDir, 'config/packages/z-shopware.yaml');

        $fs->dumpFile($hashContaingFile, 'shopware');
        $fs->dumpFile($fileNotMentioned, 'my custom fuu');

        $cleanupFiles = new CleanupFiles();
        $cleanupFiles->cleanup($tmpDir);

        static::assertFileExists($hashContaingFile);
        static::assertStringEqualsFile($hashContaingFile, 'shopware');
        static::assertFileExists($fileNotMentioned);
        static::assertStringEqualsFile($fileNotMentioned, 'my custom fuu');

        $fs->remove($tmpDir);
    }

    public function testCleanupHashMatchesGetDeleted(): void
    {
        $fs = new Filesystem();

        $tmpDir = sys_get_temp_dir() . '/' . uniqid('cleanup-files-test', true);

        $fs->mkdir($tmpDir);

        $hashContaingFile = Path::join($tmpDir, 'config/packages/shopware.yaml');

        $fs->dumpFile($hashContaingFile, (string) file_get_contents(__DIR__ . '/../_fixtures/hashed-file-for-deletion.yaml'));

        $cleanupFiles = new CleanupFiles();
        $cleanupFiles->cleanup($tmpDir);

        static::assertFileDoesNotExist($hashContaingFile);

        $fs->remove($tmpDir);
    }
}
