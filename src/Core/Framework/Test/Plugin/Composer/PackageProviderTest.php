<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Composer;

use Composer\IO\NullIO;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Composer\PackageProvider;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;

/**
 * @internal
 */
class PackageProviderTest extends TestCase
{
    public function testGetPluginInformation(): void
    {
        $packageProvider = $this->createProvider();
        $pluginPath = __DIR__ . '/_fixture/valid';
        $package = $packageProvider->getPluginComposerPackage($pluginPath, new NullIO());

        static::assertSame('test/test', $package->getName());
    }

    public function testGetPluginInformationInvalidJson(): void
    {
        $packageProvider = $this->createProvider();
        $pluginPath = __DIR__ . '/_fixture/invalid';

        $this->expectException(PluginComposerJsonInvalidException::class);
        $this->expectExceptionMessage('name : The property name is required');
        $packageProvider->getPluginComposerPackage($pluginPath, new NullIO());
    }

    public function testGetPluginInformationInvalidJsonPath(): void
    {
        $packageProvider = $this->createProvider();
        $pluginPath = __DIR__ . '/invalid_path';

        $this->expectException(PluginComposerJsonInvalidException::class);
        $this->expectExceptionMessage('The file "' . $pluginPath . '/composer.json" is not readable.');

        $packageProvider->getPluginComposerPackage($pluginPath, new NullIO());
    }

    private function createProvider(): PackageProvider
    {
        return new PackageProvider();
    }
}
