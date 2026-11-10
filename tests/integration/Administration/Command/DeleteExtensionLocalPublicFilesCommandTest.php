<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Administration\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\DeleteExtensionLocalPublicFilesCommand;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * The command removes and marks the bundle's public directory on the real filesystem without a seam,
 * so it runs against a disposable bundle tree here; the bundle filtering is covered by the unit test.
 *
 * @internal
 */
#[Package('framework')]
class DeleteExtensionLocalPublicFilesCommandTest extends TestCase
{
    private string $extensionDir;

    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->extensionDir = sys_get_temp_dir() . '/' . uniqid('sw-extension-', true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->extensionDir);
    }

    public function testBundleWithJSAndCss(): void
    {
        $this->filesystem->mkdir($this->extensionDir . '/Resources/public/administration/js');
        $this->filesystem->mkdir($this->extensionDir . '/Resources/public/administration/css');

        $kernel = static::createStub(KernelInterface::class);
        $bundle = static::createStub(Bundle::class);
        $bundle->method('getPath')->willReturn($this->extensionDir);
        $kernel->method('getBundles')->willReturn([
            $bundle,
        ]);

        $command = new DeleteExtensionLocalPublicFilesCommand($kernel);
        $tester = new CommandTester($command);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('Removed public assets for bundle', $tester->getDisplay(true));

        static::assertFileExists($this->extensionDir . '/Resources/.administration-css');
        static::assertFileExists($this->extensionDir . '/Resources/.administration-js');
        static::assertFileDoesNotExist($this->extensionDir . '/Resources/public');
    }
}
