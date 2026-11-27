<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppExtractor::class)]
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
        $this->createZip(__DIR__ . '/_fixtures/App/TestApp');

        $validator = $this->createMock(AppArchiveValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(static::isInstanceOf(\ZipArchive::class), 'TestApp');

        $extractor = new AppExtractor($validator);

        $extractor->extract($this->temporaryFilePath, $this->extractPath, 'TestApp');

        static::assertFileExists($this->extractPath . '/TestApp');
        static::assertFileExists($this->temporaryFilePath);
    }

    public function testAppIsNotExtractedWhenValidationFails(): void
    {
        $this->createZip(__DIR__ . '/_fixtures/App/TestApp');

        $validator = $this->createMock(AppArchiveValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(static::isInstanceOf(\ZipArchive::class), 'TestApp')
            ->willThrowException(AppArchiveValidationFailure::appEmpty());

        try {
            $extractor = new AppExtractor($validator);
            $extractor->extract($this->temporaryFilePath, $this->extractPath, 'TestApp');
            static::fail(AppArchiveValidationFailure::class . ' exception should be thrown');
        } catch (\Exception) {
            static::assertFileDoesNotExist($this->extractPath . '/TestApp');
        }
    }

    /**
     * The app filesystem relies on this: a symlink inside an app ZIP must not become a symlink on disk.
     */
    public function testSymlinkEntriesAreExtractedAsRegularFiles(): void
    {
        $zip = new \ZipArchive();
        $zip->open($this->temporaryFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('TestApp/manifest.xml', '<manifest/>');
        $zip->addFromString('TestApp/icon.png', '/etc/passwd');
        // marks the entry as a symlink to "/etc/passwd" for unzip tools that honour Unix attributes
        $zip->setExternalAttributesName('TestApp/icon.png', \ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();

        $extractor = new AppExtractor(static::createStub(AppArchiveValidator::class));
        $extractor->extract($this->temporaryFilePath, $this->extractPath, 'TestApp');

        $icon = $this->extractPath . '/TestApp/icon.png';
        static::assertFalse(is_link($icon));
        static::assertTrue(is_file($icon));
        static::assertSame('/etc/passwd', file_get_contents($icon));
    }

    private function createZip(string $appDirectory): void
    {
        $appDirectory = (string) realpath($appDirectory);

        $zip = new \ZipArchive();
        $zip->open($this->temporaryFilePath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($appDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            static::assertInstanceOf(\SplFileInfo::class, $file);
            $zip->addFile($file->getRealPath(), Path::join('TestApp', $files->getSubPathname()));
        }

        $zip->close();
    }
}
