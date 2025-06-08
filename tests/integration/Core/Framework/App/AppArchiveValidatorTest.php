<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(AppArchiveValidator::class)]
class AppArchiveValidatorTest extends TestCase
{
    private string $temporaryFilePath;

    protected function setUp(): void
    {
        $this->temporaryFilePath = realpath(sys_get_temp_dir()) . '/' . Uuid::randomHex() . '.zip';
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->temporaryFilePath);
    }

    public function testExceptionIsThrownWhenManifestIsMissing(): void
    {
        $this->createZip(__DIR__ . '/_fixtures/AppWithoutManifestFile');

        $archive = new \ZipArchive();
        $archive->open($this->temporaryFilePath);

        $validator = new AppArchiveValidator();

        $this->expectException(AppArchiveValidationFailure::class);
        $this->expectExceptionMessage('App archive does not contain a manifest.xml file');

        $validator->validate($archive, 'TestApp');
    }

    public function testExceptionIsThrownWhenAppNameDoesNotMatch(): void
    {
        $this->createZip(__DIR__ . '/_fixtures/App');

        $archive = new \ZipArchive();
        $archive->open($this->temporaryFilePath);

        $validator = new AppArchiveValidator();

        $this->expectException(AppArchiveValidationFailure::class);
        $this->expectExceptionMessage('App name does not match expected. Expected: "WrongName". Got: "TestApp"');

        $validator->validate($archive, 'WrongName');
    }

    public function testExceptionIsThrownWhenArchiveHasInvalidPrefix(): void
    {
        $this->createZip(__DIR__ . '/_fixtures/AppInvalidPrefix');

        $archive = new \ZipArchive();
        $archive->open($this->temporaryFilePath);

        $validator = new AppArchiveValidator();

        $this->expectException(AppArchiveValidationFailure::class);
        $this->expectExceptionMessage('Detected invalid file/directory "some-file.txt" in the app zip. Expected the directory: "TestApp"');

        $validator->validate($archive, 'TestApp');
    }

    public function testExceptionIsThrownWhenArchiveHasNoTopLevelFolder(): void
    {
        $this->createZip(__DIR__ . '/_fixtures/AppNoTobLevelFolder');

        $archive = new \ZipArchive();
        $archive->open($this->temporaryFilePath);

        $validator = new AppArchiveValidator();

        $this->expectException(AppArchiveValidationFailure::class);
        $this->expectExceptionMessage('App zip does not contain any top level folder');

        $validator->validate($archive, 'TestApp');
    }

    public function testExceptionIsThrownOnDirectoryTraversal(): void
    {
        \copy(__DIR__ . '/_fixtures/FileTraversal.zip', $this->temporaryFilePath);

        $archive = new \ZipArchive();
        $archive->open($this->temporaryFilePath);

        $validator = new AppArchiveValidator();

        $this->expectException(AppArchiveValidationFailure::class);
        $this->expectExceptionMessage('Directory traversal detected');

        $validator->validate($archive, 'TestApp');
    }

    public function testWithValidArchive(): void
    {
        $this->createZip(__DIR__ . '/_fixtures/App');

        $archive = new \ZipArchive();
        $archive->open($this->temporaryFilePath);
        $validator = new AppArchiveValidator();

        $this->expectNotToPerformAssertions();

        $validator->validate($archive, 'TestApp');
    }

    public function testWithValidArchiveWithoutExpectedName(): void
    {
        $this->createZip(__DIR__ . '/_fixtures/App');

        $archive = new \ZipArchive();
        $archive->open($this->temporaryFilePath);
        $validator = new AppArchiveValidator();

        $this->expectNotToPerformAssertions();

        $validator->validate($archive);
    }

    private function createZip(string $appDirectory): void
    {
        $appDirectory = (string) realpath($appDirectory);

        $zip = new \ZipArchive();
        $zip->open($this->temporaryFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDirectory),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }
            if ($file->isDir()) {
                continue;
            }

            // Get real and relative path for current file
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, \strlen($appDirectory) + 1);

            // Add current file to archive
            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();
    }
}
