<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin\Helper;

use Composer\IO\NullIO;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Exception\PluginComposerJsonInvalidException;
use Shopware\Core\Framework\Plugin\Helper\ComposerPackageProvider;

class ComposerPackageProviderTest extends TestCase
{
    public function testGetPluginInformation(): void
    {
        $packageProvider = $this->createProvider();
        $pluginPath = __DIR__ . '/_fixture/valid';
        $package = $packageProvider->getPluginInformation($pluginPath, new NullIO());

        self::assertSame('test/test', $package->getName());
    }

    public function testGetPluginInformationInvalidJson(): void
    {
        $packageProvider = $this->createProvider();
        $pluginPath = __DIR__ . '/_fixture/invalid';

        $this->expectException(PluginComposerJsonInvalidException::class);
        $this->expectExceptionMessage('name : The property name is required');
        $packageProvider->getPluginInformation($pluginPath, new NullIO());
    }

    public function testGetPluginInformationInvalidJsonPath(): void
    {
        $packageProvider = $this->createProvider();
        $pluginPath = __DIR__ . '/invalid_path';

        $this->expectException(PluginComposerJsonInvalidException::class);
        $this->expectExceptionMessage('failed to open stream: No such file or directory');
        $packageProvider->getPluginInformation($pluginPath, new NullIO());
    }

    private function createProvider(): ComposerPackageProvider
    {
        return new ComposerPackageProvider();
    }
}
