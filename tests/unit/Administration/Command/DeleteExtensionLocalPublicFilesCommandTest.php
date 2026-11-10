<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\DeleteExtensionLocalPublicFilesCommand;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DeleteExtensionLocalPublicFilesCommand::class)]
class DeleteExtensionLocalPublicFilesCommandTest extends TestCase
{
    public function testSymfonyBundle(): void
    {
        $kernel = static::createStub(KernelInterface::class);
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
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn([
            static::createStub(Bundle::class),
        ]);

        $command = new DeleteExtensionLocalPublicFilesCommand($kernel);
        $tester = new CommandTester($command);

        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        static::assertSame('', $tester->getDisplay(true));
    }
}
