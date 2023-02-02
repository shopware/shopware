<?php declare(strict_types=1);

namespace Adapter\Asset;

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
        $filesystem = $this->getContainer()->get('shopware.filesystem.asset');
        // make sure that the dir does not exist beforehand
        $filesystem->deleteDir('bundles/test');

        $fixturePath = realpath(__DIR__ . '/../../App/Manifest/_fixtures/test');
        $relativeFixturePath = ltrim(
            str_replace($this->getContainer()->getParameter('kernel.project_dir'), '', $fixturePath),
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

        $filesystem->deleteDir('bundles/test');
    }
}
