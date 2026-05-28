<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Command\AbstractAppActivationCommand;
use Shopware\Core\Framework\App\Command\DeactivateAppCommand;
use Shopware\Core\Framework\Context;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(AbstractAppActivationCommand::class)]
#[CoversClass(DeactivateAppCommand::class)]
class DeactivateAppCommandTest extends TestCase
{
    public function testDeactivateFailsWhenAppCannotBeFound(): void
    {
        $appStorage = $this->createMock(AppStorage::class);
        $appStorage
            ->expects($this->once())
            ->method('findByName')
            ->with('UnknownApp', static::isInstanceOf(Context::class))
            ->willReturn(null);

        $stateService = $this->createMock(AppStateService::class);
        $stateService->expects($this->never())->method('deactivateApp');

        $command = new DeactivateAppCommand(
            $appStorage,
            $stateService,
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute(['name' => 'UnknownApp']);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('[ERROR] No app found for "UnknownApp".', $commandTester->getDisplay());
    }
}
