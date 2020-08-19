<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Lifecycle;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\AppLoader;
use Shopware\Core\Framework\App\Manifest\Manifest;

class AppLoaderTest extends TestCase
{
    public function testLoad(): void
    {
        $appLoader = new AppLoader(__DIR__ . '/../Manifest/_fixtures/test');

        $manifests = $appLoader->load();

        static::assertCount(1, $manifests);
        static::assertInstanceOf(Manifest::class, $manifests[0]);
    }

    public function testLoadIgnoresInvalid(): void
    {
        $appLoader = new AppLoader(__DIR__ . '/../Manifest/_fixtures/invalid');

        $manifests = $appLoader->load();

        static::assertCount(0, $manifests);
    }

    public function testLoadCombinesFolders(): void
    {
        $appLoader = new AppLoader(__DIR__ . '/../Manifest/_fixtures');

        $manifests = $appLoader->load();

        static::assertCount(3, $manifests);
        foreach ($manifests as $manifest) {
            static::assertInstanceOf(Manifest::class, $manifest);
        }
    }

    public function testGetIcon(): void
    {
        $appLoader = new AppLoader(__DIR__ . '/../Manifest/_fixtures/test');

        $manifests = $appLoader->load();

        static::assertCount(1, $manifests);
        $manifest = $manifests[0];

        static::assertStringEqualsFile(
            __DIR__ . '/../Manifest/_fixtures/test/icon.png',
            $appLoader->getIcon($manifest)
        );
    }

    public function testGetIconReturnsNullOnInvalidIconPath(): void
    {
        $appLoader = new AppLoader(__DIR__ . '/../Manifest/_fixtures/test');

        $manifests = $appLoader->load();

        static::assertCount(1, $manifests);
        $manifest = $manifests[0];

        $manifest->getMetadata()->assign(['icon' => 'file/that/dont/exist.png']);

        static::assertNull($appLoader->getIcon($manifest));
    }
}
