<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[CoversClass(AppArchiveValidator::class)]
class AppExtractorTest extends TestCase
{
    private string $temporaryFilePath;

    private string $extractPath;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->temporaryFilePath = realpath(sys_get_temp_dir()) . '/' . Uuid::randomHex() . '.zip';
        $this->extractPath = realpath(sys_get_temp_dir()) . '/' . Uuid::randomHex();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->temporaryFilePath);
        $this->filesystem->remove($this->extractPath);
    }

    public function testAppIsExtracted(): void
    {
        $this->createZip(__DIR__ . '/_fixtures/App/TestApp', 'TestApp');

        $validator = $this->createMock(AppArchiveValidator::class);
        $validator->expects(static::once())
            ->method('validate')
            ->with(static::isInstanceOf(\ZipArchive::class), 'TestApp');

        $extractor = new AppExtractor($validator);

        $extractor->extract($this->temporaryFilePath, $this->extractPath, 'TestApp');

        static::assertFileExists($this->extractPath . '/TestApp');
        static::assertFileExists($this->temporaryFilePath);
    }

    public function testAppIsNotExtractedWhenValidationFails(): void
    {
        $this->createZip(__DIR__ . '/_fixtures/App/TestApp', 'TestApp');

        $validator = $this->createMock(AppArchiveValidator::class);
        $validator->expects(static::once())
            ->method('validate')
            ->with(static::isInstanceOf(\ZipArchive::class), 'TestApp')
            ->willThrowException(AppArchiveValidationFailure::appEmpty());

        try {
            $extractor = new AppExtractor($validator);
            $extractor->extract($this->temporaryFilePath, $this->extractPath, 'TestApp');
            static::fail(AppArchiveValidationFailure::class . ' exception should be thrown');
        } catch (\Exception $e) {
            static::assertFileDoesNotExist($this->extractPath . '/TestApp');
        }
    }

    private function createZip(string $appDirectory, string $appName): void
    {
        $appDirectory = (string) realpath($appDirectory);

        $zip = new \ZipArchive();
        $zip->open($this->temporaryFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            /** @var \SplFileInfo $file */
            $zip->addFile($file->getRealPath(), Path::join($appName, $files->getSubPathName()));
        }

        $zip->close();
    }
}
