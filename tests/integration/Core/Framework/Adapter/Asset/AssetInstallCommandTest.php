<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Asset;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\AssetInstallCommand;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class AssetInstallCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testItInstallsAppAssets(): void
    {
        /** @var FilesystemOperator $filesystem */
        $filesystem = $this->getContainer()->get('shopware.filesystem.asset');
        // make sure that the dir does not exist beforehand
        $filesystem->deleteDirectory('bundles/test');
        $filesystem->delete('asset-manifest.json');

        $fixturePath = __DIR__ . '/../../App/Manifest/_fixtures/test';
        $fixturePath = \realpath($fixturePath);
        static::assertIsString($fixturePath);

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        static::assertIsString($projectDir);

        $relativeFixturePath = \ltrim(
            \str_replace($projectDir, '', $fixturePath),
            '/'
        );

        $activeAppsLoaderMock = $this->createMock(ActiveAppsLoader::class);
        $activeAppsLoaderMock->expects(static::once())
            ->method('getActiveApps')
            ->willReturn([
                [
                    'name' => 'test',
                    'path' => $relativeFixturePath,
                    'author' => 'shopware AG',
                ],
            ]);

        $command = new AssetInstallCommand(
            $this->getKernel(),
            new AssetService(
                $filesystem,
                $this->getContainer()->get('shopware.filesystem.private'),
                $this->getContainer()->get('kernel'),
                $this->getContainer()->get(KernelPluginLoader::class),
                $this->getContainer()->get(CacheInvalidator::class),
                new StaticSourceResolver(['test' => new Filesystem($fixturePath)]),
                $this->getContainer()->get('parameter_bag')
            ),
            $activeAppsLoaderMock
        );

        $runner = new CommandTester($command);

        static::assertSame(0, $runner->execute([]));
        static::assertTrue($filesystem->has('bundles/test/asset.txt'));

        $filesystem->deleteDirectory('bundles/test');
        $filesystem->delete('asset-manifest.json');
    }
}
