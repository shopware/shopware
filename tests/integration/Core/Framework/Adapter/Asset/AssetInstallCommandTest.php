<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Asset;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Asset\AssetInstallCommand;
use Shopware\Core\Framework\Adapter\Asset\AssetService;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[Package('framework')]
class AssetInstallCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/' . uniqid('shopware', true);
        (new Filesystem())->mkdir($this->projectDir . '/public');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->projectDir);
    }

    public function testHtaccessCopy(): void
    {
        (new Filesystem())->dumpFile($this->projectDir . '/public/.htaccess.dist', 'FOO');

        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn($this->projectDir);

        $command = new AssetInstallCommand(
            $kernel,
            static::createStub(AssetService::class),
            static::createStub(ActiveAppsLoader::class)
        );

        $runner = new CommandTester($command);
        $status = $runner->execute([]);

        static::assertSame(Command::SUCCESS, $status);
        static::assertFileExists($this->projectDir . '/public/.htaccess');
        static::assertFileEquals($this->projectDir . '/public/.htaccess.dist', $this->projectDir . '/public/.htaccess');
    }
}
