<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Asset;

use League\Flysystem\Filesystem as Flysystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\AssetInstallCommand;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Util\Filesystem as UtilFilesystem;
use Shopware\Core\Installer\Installer;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\ExampleBundle;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(AssetInstallCommand::class)]
class AssetInstallCommandTest extends TestCase
{
    public function testHtaccessCopy(): void
    {
        $fs = new Filesystem();
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('shopware', true);
        $fs->mkdir($tmpDir . '/public');
        $fs->dumpFile($tmpDir . '/public/.htaccess.dist', 'FOO');

        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($tmpDir);

        $command = new AssetInstallCommand(
            $kernel,
            $this->createMock(AssetService::class),
            $this->createMock(ActiveAppsLoader::class)
        );

        $runner = new CommandTester($command);
        $status = $runner->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertFileExists($tmpDir . '/public/.htaccess');
        static::assertFileEquals($tmpDir . '/public/.htaccess.dist', $tmpDir . '/public/.htaccess');

        $fs->remove($tmpDir);
    }

    public function testForceOptionIsForwardedToService(): void
    {
        $exampleBundle = $this->getBundle();
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([$exampleBundle]);

        $service = $this->createMock(AssetService::class);
        $appLoader = $this->createMock(ActiveAppsLoader::class);
        $appLoader->method('getActiveApps')->willReturn([]);

        $invokedCount = $this->exactly(2);
        $service->expects($invokedCount)
            ->method('copyAssets')
            ->willReturnCallback(static function ($bundle, $force) use ($invokedCount, $exampleBundle): void {
                if ($invokedCount->numberOfInvocations() === 1) {
                    static::assertSame($exampleBundle, $bundle);
                    static::assertTrue($force);
                }
                if ($invokedCount->numberOfInvocations() === 2) {
                    static::assertInstanceOf(Installer::class, $bundle);
                    static::assertTrue($force);
                }
            });

        $command = new AssetInstallCommand(
            $kernel,
            $service,
            $appLoader
        );

        $runner = new CommandTester($command);
        $runner->execute(['--force' => true]);
    }

    public function testItInstallsAppAssets(): void
    {
        $testAssetFilesystem = new Flysystem(new InMemoryFilesystemAdapter());

        $fixturePath = Path::canonicalize(__DIR__ . '/../../App/Manifest/_fixtures/test');

        $relativeFixturePath = Path::makeRelative($fixturePath, __DIR__ . '/../../../../../../');

        $activeAppsLoaderMock = $this->createMock(ActiveAppsLoader::class);
        $activeAppsLoaderMock->expects($this->once())
            ->method('getActiveApps')
            ->willReturn([
                [
                    'name' => 'test',
                    'path' => $relativeFixturePath,
                    'author' => 'shopware AG',
                ],
            ]);

        $kernel = $this->createMock(KernelInterface::class);
        $command = new AssetInstallCommand(
            $kernel,
            new AssetService(
                $testAssetFilesystem,
                new Flysystem(new InMemoryFilesystemAdapter()),
                $kernel,
                $this->createMock(KernelPluginLoader::class),
                $this->createMock(CacheInvalidator::class),
                new StaticSourceResolver(['test' => new UtilFilesystem($fixturePath)]),
                $this->createMock(ParameterBagInterface::class),
                new EventDispatcher()
            ),
            $activeAppsLoaderMock
        );

        $runner = new CommandTester($command);

        static::assertSame(0, $runner->execute([]));
        static::assertTrue($testAssetFilesystem->has('bundles/test/asset.txt'));

        $testAssetFilesystem->deleteDirectory('bundles/test');
        $testAssetFilesystem->delete('asset-manifest.json');
    }

    private function getBundle(): ExampleBundle
    {
        return new ExampleBundle(true, __DIR__ . '/_fixtures/ExampleBundle');
    }
}
