<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Asset;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\AssetInstallCommand;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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

        $fixturePath = __DIR__ . '/../../../../../../tests/integration/php/Core/Framework/App/Manifest/_fixtures/test';
        $fixturePath = \realpath($fixturePath);
        static::assertNotFalse($fixturePath);

        $projectDir = $this->getContainer()->getParameter('kernel.project_dir');
        static::assertNotFalse($projectDir);

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
            $this->getContainer()->get(AssetService::class),
            $activeAppsLoaderMock
        );

        $runner = new CommandTester($command);

        static::assertSame(0, $runner->execute([]));
        static::assertTrue($filesystem->has('bundles/test/asset.txt'));

        $filesystem->deleteDirectory('bundles/test');
        $filesystem->delete('asset-manifest.json');
    }
}
