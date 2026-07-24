<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\AbstractExtensionToolingCommand;
use Shopware\Administration\Command\CheckExtensionsCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 *
 * @phpstan-type ToolingCall array{command: list<string>, cwd: string, env: array<string, string>}
 */
#[Package('framework')]
#[CoversClass(AbstractExtensionToolingCommand::class)]
#[CoversClass(CheckExtensionsCommand::class)]
class CheckExtensionsCommandTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
    }

    public function testFailsWithNpmCiGuidanceWhenNodeDependenciesAreMissing(): void
    {
        // A vendor/flex install ships the tooling code but not its node_modules.
        $administrationRoot = $this->createAdministrationRoot(withDependencies: false);
        $spawned = false;

        $command = $this->checkCommand($administrationRoot, function () use (&$spawned): int {
            $spawned = true;

            return 0;
        });
        $tester = new CommandTester($command);

        $exitCode = $tester->execute([]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertFalse($spawned, 'the tooling must not be spawned when its dependencies are missing');
        static::assertStringContainsString('npm ci', $tester->getDisplay(true));

        $this->filesystem->remove($administrationRoot);
    }

    public function testForwardsArgumentsAndProjectRootAndPropagatesExitCode(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withDependencies: true);

        /** @var ToolingCall|null $captured */
        $captured = null;
        $command = $this->checkCommand($administrationRoot, function (array $command, string $cwd, array $env) use (&$captured): int {
            $captured = ['command' => $command, 'cwd' => $cwd, 'env' => $env];

            // A non-zero tooling exit (findings) must reach the shell unchanged.
            return 1;
        });
        $tester = new CommandTester($command);

        $exitCode = $tester->execute(['tooling-args' => ['--only=MyPlugin', '--all']]);

        static::assertSame(1, $exitCode);
        static::assertNotNull($captured);
        static::assertSame($administrationRoot, $captured['cwd']);
        static::assertSame('/shop', $captured['env']['PROJECT_ROOT']);
        static::assertSame($administrationRoot . '/node_modules/.bin/ts-node', $captured['command'][0]);
        static::assertContains('--transpileOnly', $captured['command']);
        static::assertStringEndsWith('scripts/extensionTooling/check.ts', $captured['command'][2]);
        static::assertContains('--only=MyPlugin', $captured['command']);
        static::assertContains('--all', $captured['command']);

        $this->filesystem->remove($administrationRoot);
    }

    public function testAdministrationRootResolvesToTheBundleResourcesPath(): void
    {
        $root = $this->exposedCommand()->exposedAdministrationRoot();

        static::assertStringEndsWith('/Resources/app/administration', $root);
    }

    public function testRunToolingSpawnsTheProcessAndPropagatesItsExitCode(): void
    {
        $output = new BufferedOutput();

        $exitCode = $this->exposedCommand()->exposedRunTooling(
            [\PHP_BINARY, '-r', 'fwrite(STDOUT, "tooling-ran"); exit(5);'],
            sys_get_temp_dir(),
            [],
            $output,
        );

        static::assertSame(5, $exitCode, 'the tooling exit code reaches the shell unchanged');
    }

    private function exposedCommand(): CheckExtensionsCommand
    {
        return new class($this->kernel()) extends CheckExtensionsCommand {
            public function exposedAdministrationRoot(): string
            {
                return $this->administrationRoot();
            }

            /**
             * @param list<string> $command
             * @param array<string, string> $env
             */
            public function exposedRunTooling(array $command, string $cwd, array $env, OutputInterface $output): int
            {
                return $this->runTooling($command, $cwd, $env, $output);
            }
        };
    }

    private function createAdministrationRoot(bool $withDependencies): string
    {
        $root = sys_get_temp_dir() . '/' . uniqid('sw-admin-tooling-', true);
        $this->filesystem->mkdir($root);

        if ($withDependencies) {
            $this->filesystem->mkdir($root . '/node_modules/.bin');
            $this->filesystem->touch($root . '/node_modules/.bin/ts-node');
        }

        return $root;
    }

    /**
     * @param \Closure(list<string>, string, array<string, string>): int $runTooling
     */
    private function checkCommand(string $administrationRoot, \Closure $runTooling): CheckExtensionsCommand
    {
        return new class($this->kernel(), $administrationRoot, $runTooling) extends CheckExtensionsCommand {
            /**
             * @param \Closure(list<string>, string, array<string, string>): int $runTooling
             */
            public function __construct(
                KernelInterface $kernel,
                private readonly string $administrationRootOverride,
                private readonly \Closure $runTooling,
            ) {
                parent::__construct($kernel);
            }

            protected function administrationRoot(): string
            {
                return $this->administrationRootOverride;
            }

            protected function runTooling(array $command, string $cwd, array $env, OutputInterface $output): int
            {
                return ($this->runTooling)($command, $cwd, $env);
            }
        };
    }

    private function kernel(): KernelInterface
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/shop');

        return $kernel;
    }
}
