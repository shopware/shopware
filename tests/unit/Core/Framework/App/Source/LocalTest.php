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

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Local::class)]
class LocalTest extends TestCase
{
    public function testName(): void
    {
        $source = new Local('/');
        static::assertEquals('local', $source->name());
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
        $manifest = static::createMock(Manifest::class);
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
        $manifest = static::createMock(Manifest::class);
        $manifest->method('getPath')->willReturn('/not/existing/path');

        $source = new Local('/');

        static::assertFalse($source->supports($manifest));
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
            $manifest = $testCase->createMock(Manifest::class);

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
