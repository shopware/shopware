<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\DeleteExtensionLocalPublicFilesCommand;
use Shopware\Core\Framework\Bundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[CoversClass(DeleteExtensionLocalPublicFilesCommand::class)]
class DeleteExtensionLocalPublicFilesCommandTest extends TestCase
{
    public function testSymfonyBundle(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([
            new FrameworkBundle(),
        ]);

        $command = new DeleteExtensionLocalPublicFilesCommand($kernel);
        $tester = new CommandTester($command);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        static::assertSame('', $tester->getDisplay());
    }

    public function testNotPersistentPublicDir(): void
    {
        $kernel = $this->createMock(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([
            $this->createMock(Bundle::class),
        ]);

        $command = new DeleteExtensionLocalPublicFilesCommand($kernel);
        $tester = new CommandTester($command);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        static::assertSame('', $tester->getDisplay());
    }

    public function testBundleWithJSAndCss(): void
    {
        $fs = new Filesystem();
        $extensionDir = sys_get_temp_dir() . '/' . uniqid('sw-extension-', true);

        $fs->mkdir($extensionDir . '/Resources/public/administration/js');
        $fs->mkdir($extensionDir . '/Resources/public/administration/css');

        $kernel = $this->createMock(KernelInterface::class);
        $bundle = $this->createMock(Bundle::class);
        $bundle->method('getPath')->willReturn($extensionDir);
        $kernel->method('getBundles')->willReturn([
            $bundle,
        ]);

        $command = new DeleteExtensionLocalPublicFilesCommand($kernel);
        $tester = new CommandTester($command);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('Removed public assets for bundle', $tester->getDisplay());

        static::assertFileExists($extensionDir . '/Resources/.administration-css');
        static::assertFileExists($extensionDir . '/Resources/.administration-js');
        static::assertFileDoesNotExist($extensionDir . '/Resources/public');

        $fs->remove($extensionDir);
    }
}
