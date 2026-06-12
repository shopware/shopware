<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Command\UninstallAppCommand;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(UninstallAppCommand::class)]
class UninstallAppCommandTest extends TestCase
{
    public function testUninstallFailsWhenAppCannotBeFound(): void
    {
        $appStorage = $this->createMock(AppStorage::class);
        $appStorage
            ->expects($this->once())
            ->method('findByName')
            ->with('UnknownApp', static::isInstanceOf(Context::class))
            ->willReturn(null);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method(static::anything());

        $command = new UninstallAppCommand($appLifecycle, $appStorage);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['name' => 'UnknownApp']);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('[ERROR] No app with name "UnknownApp" installed.', $commandTester->getDisplay());
    }
}
