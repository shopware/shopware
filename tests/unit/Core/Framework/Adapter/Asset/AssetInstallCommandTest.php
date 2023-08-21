<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Asset;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\AssetInstallCommand;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Asset\AssetInstallCommand
 */
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
}
