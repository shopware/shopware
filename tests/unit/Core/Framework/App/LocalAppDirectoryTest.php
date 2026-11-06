<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\AppArchiveValidator;
use Shopware\Core\Framework\App\AppExtractor;
use Shopware\Core\Framework\App\Event\AppUploadValidationEvent;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\LocalAppDirectory;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(LocalAppDirectory::class)]
class LocalAppDirectoryTest extends TestCase
{
    private const APP_ZIP = __DIR__ . '/../Plugin/_fixtures/archives/App.zip';

    private string $appDir;

    private Filesystem $filesystem;

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->appDir = sys_get_temp_dir() . '/local-app-directory-' . bin2hex(random_bytes(5));
        $this->filesystem = new Filesystem();
        $this->dispatcher = new EventDispatcher();
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->appDir);
    }

    public function testStoreReplacesOldFilesAndReturnsMetadata(): void
    {
        $this->filesystem->dumpFile($this->appDir . '/plugin/removed.txt', 'old file');
        $metadata = $this->createDirectory()->store(self::APP_ZIP);

        static::assertSame('SwagApp', $metadata->name);
        static::assertSame('1.0.0', $metadata->version);
        static::assertFileExists($this->appDir . '/plugin/manifest.xml');
        static::assertFileDoesNotExist($this->appDir . '/plugin/removed.txt');
        static::assertCount(1, $this->createDirectory()->load());
        static::assertCount(1, glob($this->appDir . '/*') ?: []);
        static::assertSame([], glob($this->appDir . '/.upload-*'));
    }

    public function testRejectedUploadLeavesOldFilesUntouched(): void
    {
        $this->filesystem->dumpFile($this->appDir . '/plugin/old.txt', 'old file');
        $exception = new \RuntimeException('Rejected app');
        $this->dispatcher->addListener(AppUploadValidationEvent::class, static function (AppUploadValidationEvent $event) use ($exception): void {
            static::assertSame(self::APP_ZIP, $event->zipPath);
            throw $exception;
        });

        try {
            $this->expectExceptionObject($exception);
            $this->createDirectory()->store(self::APP_ZIP);
        } finally {
            static::assertFileExists($this->appDir . '/plugin/old.txt');
            static::assertFileDoesNotExist($this->appDir . '/plugin/manifest.xml');
        }
    }

    public function testInvalidManifestLeavesOldAppUntouched(): void
    {
        $this->filesystem->dumpFile($this->appDir . '/TestShippingApp/old.txt', 'old file');
        $this->expectException(AppXmlParsingException::class);

        try {
            $this->createDirectory()->store(__DIR__ . '/../../../../integration/Core/Framework/Plugin/_fixtures/archives/InvalidManifestShippingApp.zip');
        } finally {
            static::assertFileExists($this->appDir . '/TestShippingApp/old.txt');
            static::assertSame([], glob($this->appDir . '/.upload-*'));
        }
    }

    public function testFailedReplacementRestoresPreviousDirectory(): void
    {
        $this->filesystem->dumpFile($this->appDir . '/plugin/old.txt', 'old file');
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturnCallback($this->filesystem->exists(...));
        $filesystem->expects($this->exactly(3))->method('rename')->willReturnCallback(function (string $origin, string $target): void {
            if (str_contains($origin, '/extracted/')) {
                throw new IOException('Cannot replace app');
            }
            $this->filesystem->rename($origin, $target);
        });
        $filesystem->expects($this->once())->method('remove')->willReturnCallback($this->filesystem->remove(...));

        try {
            $this->expectExceptionObject(new IOException('Cannot replace app'));
            $this->createDirectory($filesystem)->store(self::APP_ZIP);
        } finally {
            static::assertFileExists($this->appDir . '/plugin/old.txt');
            static::assertSame([], glob($this->appDir . '/.upload-*'));
        }
    }

    public function testLoadAndDelete(): void
    {
        $this->filesystem->mirror(__DIR__ . '/_fixtures/App/TestApp', $this->appDir . '/TestApp');
        $directory = $this->createDirectory();

        static::assertArrayHasKey('minimal', $directory->load());
        $directory->delete('minimal');
        static::assertDirectoryDoesNotExist($this->appDir . '/TestApp');
    }

    public function testDeleteUnknownAppDoesNothing(): void
    {
        $this->filesystem->mkdir($this->appDir);
        $this->createDirectory()->delete('UnknownApp');
        static::assertDirectoryExists($this->appDir);
    }

    private function createDirectory(?Filesystem $filesystem = null): LocalAppDirectory
    {
        return new LocalAppDirectory(
            $this->appDir,
            new NullLogger(),
            new AppExtractor(new AppArchiveValidator()),
            $this->dispatcher,
            $filesystem ?? $this->filesystem,
        );
    }
}
