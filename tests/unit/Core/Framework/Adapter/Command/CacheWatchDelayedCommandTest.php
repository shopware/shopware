<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Command\CacheWatchDelayedCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CacheWatchDelayedCommand::class)]
class CacheWatchDelayedCommandTest extends TestCase
{
    #[TestDox('fails outside of a console context')]
    public function testFailsOutsideConsoleContext(): void
    {
        $command = new CacheWatchDelayedCommand(static::createStub(ContainerInterface::class));

        $output = new BufferedOutput();
        $status = $command->run(new ArrayInput([]), $output);

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString('only available in console context', $output->fetch());
    }

    #[TestDox('fails when redis cache invalidation is not configured')]
    public function testFailsWhenRedisNotConfigured(): void
    {
        $container = static::createStub(ContainerInterface::class);
        $container->method('has')->willReturn(false);

        $command = new CacheWatchDelayedCommand($container);
        $tester = $this->runWithConsoleOutput($command);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('Redis cache invalidation is not configured.', $tester->getDisplay());
    }

    #[TestDox('fails when the redis adapter does not support sMembers')]
    public function testFailsWhenAdapterHasNoSMembers(): void
    {
        $container = static::createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn(new \stdClass());

        $command = new CacheWatchDelayedCommand($container);
        $tester = $this->runWithConsoleOutput($command);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('Redis adapter does not support sMembers method.', $tester->getDisplay());
    }

    #[TestDox('subscribes to SIGINT and SIGTERM')]
    public function testGetSubscribedSignals(): void
    {
        $command = new CacheWatchDelayedCommand(static::createStub(ContainerInterface::class));

        static::assertSame([\SIGINT, \SIGTERM], $command->getSubscribedSignals());
    }

    #[TestDox('handling a signal requests a graceful stop without forcing an exit')]
    public function testHandleSignalRequestsGracefulStop(): void
    {
        $command = new CacheWatchDelayedCommand(static::createStub(ContainerInterface::class));

        static::assertFalse($command->handleSignal(\SIGTERM));
        static::assertFalse($command->handleSignal(\SIGINT));
    }

    #[TestDox('the interval option defaults to 1000 microseconds')]
    public function testIntervalOptionDefault(): void
    {
        $command = new CacheWatchDelayedCommand(static::createStub(ContainerInterface::class));

        $option = $command->getDefinition()->getOption('interval');
        static::assertTrue($option->isValueRequired());
        static::assertSame(1000, $option->getDefault());
    }

    #[TestDox('runs the watch once and exits when a stop was already requested')]
    public function testWatchRunsOnceWhenStopAlreadyRequested(): void
    {
        $adapter = $this->createMock(\Redis::class);
        $adapter->expects($this->atLeastOnce())
            ->method('sMembers')
            ->willReturnCallback(static function (string $key): array {
                self::assertSame('invalidation', $key);

                return ['theme-config'];
            });

        $container = static::createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn($adapter);

        $command = new CacheWatchDelayedCommand($container);
        // requesting the stop up front makes the watch loop exit after its first poll
        $command->handleSignal(\SIGTERM);

        $tester = new CommandTester($command);
        $tester->execute(['--interval' => '5000'], ['capture_stderr_separately' => true]);

        $tester->assertCommandIsSuccessful();
        static::assertStringContainsString('theme-config', $tester->getDisplay());
    }

    private function runWithConsoleOutput(CacheWatchDelayedCommand $command): CommandTester
    {
        // The watch command requires a ConsoleOutputInterface; "capture_stderr_separately" makes the
        // tester run the command with a ConsoleOutput (writing into memory streams), and execute()
        // returns before the watch loop in the failure paths.
        $tester = new CommandTester($command);
        $tester->execute([], ['capture_stderr_separately' => true]);

        return $tester;
    }
}
