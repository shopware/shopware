<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Source;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Source\Local;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Local::class)]
class LocalTest extends TestCase
{
    public function testName(): void
    {
        $source = new Local('/');
        static::assertSame('local', $source->name());
    }

    public function testSupportsExistingAppWithLocalType(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setSourceType('local');

        $source = new Local('/');

        static::assertTrue($source->supports($app));
    }

    public function testSupportsManifestOnDisk(): void
    {
        $manifest = static::createStub(Manifest::class);
        $manifest->method('getPath')->willReturn(__FILE__);

        $source = new Local('/');

        static::assertTrue($source->supports($manifest));
    }

    public function testDoesNotSupportExistingAppWithNonLocalType(): void
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setSourceType('remote');

        $source = new Local('/');

        static::assertFalse($source->supports($app));
    }

    public function testDoesNotSupportManifestNotOnDisk(): void
    {
        $manifest = static::createStub(Manifest::class);
        $manifest->method('getPath')->willReturn('/not/existing/path');

        $source = new Local('/');

        static::assertFalse($source->supports($manifest));
    }

    public function testCanRestoreAnAppDirectoryFromItsUploadBackup(): void
    {
        $projectRoot = sys_get_temp_dir() . '/local-app-source-' . bin2hex(random_bytes(5));
        $appPath = $projectRoot . '/custom/apps/TestApp';
        $filesystem = new Filesystem();
        $filesystem->dumpFile($appPath . '/manifest.xml', 'old manifest');
        $filesystem->dumpFile($appPath . '/old-file.txt', 'old file');
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setPath('custom/apps/TestApp');

        $source = new Local($projectRoot, io: $filesystem);
        $backupPath = $source->backup($app);
        $filesystem->remove($appPath);
        $filesystem->dumpFile($appPath . '/new-file.txt', 'new file');

        try {
            $source->restore($app, $backupPath);

            static::assertFileExists($appPath . '/old-file.txt');
            static::assertFileDoesNotExist($appPath . '/new-file.txt');
            static::assertSame('old manifest', file_get_contents($appPath . '/manifest.xml'));
        } finally {
            $filesystem->remove([$projectRoot, $backupPath]);
        }
    }

    public static function appProvider(): \Generator
    {
        $appFactory = static function (): AppEntity {
            $app = new AppEntity();
            $app->setId(Uuid::randomHex());
            $app->setName('TestApp');
            $app->setPath('/path');

            return $app;
        };

        yield 'app' => [$appFactory];

        $appFactory = static function (TestCase $testCase): Manifest {
            $manifest = $testCase->createStub(Manifest::class);

            $metadata = Metadata::fromArray([
                'name' => 'TestApp',
                'label' => [],
                'author' => 'Shopware',
                'copyright' => 'Shopware',
                'license' => 'Shopware',
                'version' => '1.0',
            ]);

            $manifest->method('getMetadata')->willReturn($metadata);
            $manifest->method('getPath')->willReturn('/root/path');

            return $manifest;
        };

        yield 'manifest' => [$appFactory];
    }

    /**
     * @param callable(TestCase):(AppEntity|Manifest) $appFactory
     */
    #[DataProvider('appProvider')]
    public function testFilesystem(callable $appFactory): void
    {
        $app = $appFactory($this);

        $source = new Local('/root');
        $fs = $source->filesystem($app);

        static::assertSame('/root/path', $fs->location);
    }
}
