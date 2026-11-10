<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Exception\AppArchiveValidationFailure;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
class AppExtractorTest extends TestCase
{
    private Filesystem $filesystem;

    private string $directory;

    private string $zipPath;

    private string $extractPath;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/' . uniqid('app-extractor-', true);
        $this->filesystem->mkdir($this->directory);
        $this->zipPath = $this->directory . '/TestApp.zip';
        $this->extractPath = $this->directory . '/extracted';

        $zip = new \ZipArchive();
        $zip->open($this->zipPath, \ZipArchive::CREATE);
        $zip->addFromString('TestApp/manifest.xml', '<manifest/>');
        $zip->close();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testAppIsExtracted(): void
    {
        $validator = $this->createMock(AppArchiveValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(static::isInstanceOf(\ZipArchive::class), 'TestApp');

        $extractor = new AppExtractor($validator);

        static::assertSame($this->extractPath . '/TestApp', $extractor->extract($this->zipPath, $this->extractPath, 'TestApp'));

        static::assertFileExists($this->extractPath . '/TestApp/manifest.xml');
        static::assertFileExists($this->zipPath);
    }

    public function testAppIsNotExtractedWhenValidationFails(): void
    {
        $validator = $this->createMock(AppArchiveValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with(static::isInstanceOf(\ZipArchive::class), 'TestApp')
            ->willThrowException(AppArchiveValidationFailure::appEmpty());

        $extractor = new AppExtractor($validator);

        try {
            $extractor->extract($this->zipPath, $this->extractPath, 'TestApp');
            static::fail(AppArchiveValidationFailure::class . ' exception should be thrown');
        } catch (AppArchiveValidationFailure) {
            static::assertFileDoesNotExist($this->extractPath . '/TestApp');
        }
    }
}
