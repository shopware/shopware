<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Command;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Process\Process;

/**
 * Drives the real watch loop end to end: the command keeps polling until a signal arrives.
 * The loop cannot be reached in-process (it only returns on a delivered signal), so it is
 * exercised here by running the command in a subprocess and sending it SIGTERM.
 *
 * @internal
 */
class CacheWatchDelayedCommandTest extends TestCase
{
    use KernelTestBehaviour;

    #[TestDox('watch mode keeps rendering until it receives a signal and then exits cleanly')]
    public function testWatchModeRendersAndExitsOnSignal(): void
    {
        $projectDir = (string) static::getContainer()->getParameter('kernel.project_dir');

        // --interval=1 keeps the poll loop tight so the test stays fast.
        // SHELL_VERBOSITY is forced to normal: phpunit runs with -1 (quiet), which the
        // subprocess would otherwise inherit and suppress all command output.
        $process = new Process(
            ['php', 'bin/console', 'cache:watch:delayed', '--interval=1'],
            $projectDir,
            ['SHELL_VERBOSITY' => '0'],
        );
        $process->start();

        $deadline = microtime(true) + 15.0;
        while ($process->isRunning() && !str_contains($process->getOutput(), 'Tags at:') && microtime(true) < $deadline) {
            usleep(50_000);
        }

        // Environments without delayed redis invalidation cannot enter the watch loop.
        if (!str_contains($process->getOutput(), 'Tags at:')) {
            if ($process->isRunning()) {
                $process->stop();
            }
            static::markTestSkipped(
                'Delayed redis cache invalidation is not available in this environment: '
                . trim($process->getOutput() . ' ' . $process->getErrorOutput()),
            );
        }

        static::assertTrue($process->isRunning(), 'The watch command must keep running until it is signalled.');

        $process->signal(\SIGTERM);
        $process->wait();

        static::assertSame(0, $process->getExitCode());
    }
}
