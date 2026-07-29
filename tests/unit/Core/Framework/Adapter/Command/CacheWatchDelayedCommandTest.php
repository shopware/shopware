<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Command\CacheWatchDelayedCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
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
        $status = $this->runWithConsoleOutput($command);

        static::assertSame(Command::FAILURE, $status);
    }

    #[TestDox('fails when the redis adapter does not support sMembers')]
    public function testFailsWhenAdapterHasNoSMembers(): void
    {
        $container = static::createStub(ContainerInterface::class);
        $container->method('has')->willReturn(true);
        $container->method('get')->willReturn(new \stdClass());

        $command = new CacheWatchDelayedCommand($container);
        $status = $this->runWithConsoleOutput($command);

        static::assertSame(Command::FAILURE, $status);
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

    #[TestDox('the poll interval is clamped to the supported range')]
    #[DataProvider('intervalProvider')]
    public function testResolveIntervalClampsToSupportedRange(int $microseconds, int $expected): void
    {
        $command = new CacheWatchDelayedCommand(static::createStub(ContainerInterface::class));

        $resolved = (new \ReflectionMethod($command, 'resolveInterval'))->invoke($command, $microseconds);

        static::assertSame($expected, $resolved);
    }

    /**
     * @return \Generator<string, array{0: int, 1: int}>
     */
    public static function intervalProvider(): \Generator
    {
        yield 'within range is kept' => [500, 500];
        yield 'lower bound is kept' => [1, 1];
        yield 'upper bound is kept' => [1000, 1000];
        yield 'below minimum is raised to 1' => [0, 1];
        yield 'negative is raised to 1' => [-50, 1];
        yield 'above maximum is capped at 1000' => [5000, 1000];
    }

    private function runWithConsoleOutput(CacheWatchDelayedCommand $command): int
    {
        // The watch command requires a ConsoleOutputInterface; execute() returns before the
        // watch loop in the failure paths, so a stubbed console output is sufficient.
        $status = (new \ReflectionMethod($command, 'execute'))
            ->invoke($command, new ArrayInput([]), static::createStub(ConsoleOutputInterface::class));

        \assert(\is_int($status));

        return $status;
    }
}
