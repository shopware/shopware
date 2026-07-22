<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Command\AbstractAppActivationCommand;
use Shopware\Core\Framework\App\Command\DeactivateAppCommand;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AbstractAppActivationCommand::class)]
#[CoversClass(DeactivateAppCommand::class)]
class DeactivateAppCommandTest extends TestCase
{
    public function testDeactivateDelegatesToLifecycle(): void
    {
        $app = AppFixture::createAppEntity('TestApp', 'app-id');

        $appStorage = $this->createMock(AppStorage::class);
        $appStorage
            ->expects($this->once())
            ->method('findByName')
            ->with('TestApp', static::isInstanceOf(Context::class))
            ->willReturn($app);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle
            ->expects($this->once())
            ->method('deactivate')
            ->with('app-id', static::isInstanceOf(Context::class));

        $command = new DeactivateAppCommand(
            $appStorage,
            $appLifecycle,
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute(['name' => 'TestApp']);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringContainsString('[OK] App deactivated successfully.', $commandTester->getDisplay());
    }

    public function testDeactivateFailsWhenAppCannotBeFound(): void
    {
        $appStorage = $this->createMock(AppStorage::class);
        $appStorage
            ->expects($this->once())
            ->method('findByName')
            ->with('UnknownApp', static::isInstanceOf(Context::class))
            ->willReturn(null);

        $appLifecycle = $this->createMock(AbstractAppLifecycle::class);
        $appLifecycle->expects($this->never())->method('deactivate');

        $command = new DeactivateAppCommand(
            $appStorage,
            $appLifecycle,
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute(['name' => 'UnknownApp']);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('[ERROR] No app found for "UnknownApp".', $commandTester->getDisplay());
    }
}
