<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\AbstractExtensionToolingCommand;
use Shopware\Administration\Command\CheckExtensionsCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 *
 * The bridge behaviour every tooling command inherits: the missing-dependency
 * guard, argument forwarding, exit-code propagation and app-root resolution.
 * `CheckExtensionsCommand` is only the concrete vehicle — driving a real named
 * subclass is what makes `execute()`/`configure()` coverage attributable.
 * The spawn itself is intercepted through the `runTooling()` seam; the real
 * process boundary is covered by the integration test.
 */
#[Package('framework')]
#[CoversClass(AbstractExtensionToolingCommand::class)]
class AbstractExtensionToolingCommandTest extends TestCase
{
    private const ADMINISTRATION_ROOT = __DIR__ . '/_fixtures/administration-root';

    private const ADMINISTRATION_ROOT_WITHOUT_DEPENDENCIES = __DIR__ . '/_fixtures/administration-root-without-dependencies';

    public function testFailsWithNpmCiGuidanceWhenNodeDependenciesAreMissing(): void
    {
        // A vendor/flex install ships the tooling code but not its node_modules.
        $command = $this->recordingCommand(self::ADMINISTRATION_ROOT_WITHOUT_DEPENDENCIES, toolingExitCode: Command::SUCCESS);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('npm ci', $tester->getDisplay(true));
        static::assertNull($command->spawned, 'the tooling must not be spawned when its dependencies are missing');
    }

    public function testForwardsArgumentsAndProjectRootAndPropagatesExitCode(): void
    {
        // A non-zero tooling exit (findings) must reach the shell unchanged.
        $command = $this->recordingCommand(self::ADMINISTRATION_ROOT, toolingExitCode: 1);

        $tester = new CommandTester($command);
        $exitCode = $tester->execute(['tooling-args' => ['--only=MyPlugin', '--all']]);

        static::assertSame(1, $exitCode);

        static::assertNotNull($command->spawned);
        static::assertSame(self::ADMINISTRATION_ROOT, $command->spawned['cwd']);
        static::assertSame(['PROJECT_ROOT' => '/shop'], $command->spawned['env']);
        static::assertSame(
            [
                self::ADMINISTRATION_ROOT . '/node_modules/.bin/ts-node',
                '--transpileOnly',
                self::ADMINISTRATION_ROOT . '/scripts/extensionTooling/check.ts',
                '--only=MyPlugin',
                '--all',
            ],
            $command->spawned['command'],
        );
    }

    public function testAdministrationRootResolvesToTheBundleResourcesPathByDefault(): void
    {
        $command = new class($this->kernel()) extends CheckExtensionsCommand {
            public function exposedAdministrationRoot(): string
            {
                return $this->administrationRoot();
            }
        };

        static::assertStringEndsWith('/Resources/app/administration', $command->exposedAdministrationRoot());
    }

    /**
     * @return CheckExtensionsCommand&object{spawned: array{command: list<string>, cwd: string, env: array<string, string>}|null}
     */
    private function recordingCommand(string $administrationRoot, int $toolingExitCode): CheckExtensionsCommand
    {
        return new class($this->kernel(), $administrationRoot, $toolingExitCode) extends CheckExtensionsCommand {
            /**
             * @var array{command: list<string>, cwd: string, env: array<string, string>}|null
             */
            public ?array $spawned = null;

            public function __construct(KernelInterface $kernel, string $administrationRoot, private readonly int $toolingExitCode)
            {
                parent::__construct($kernel, $administrationRoot);
            }

            protected function runTooling(array $command, string $cwd, array $env, OutputInterface $output): int
            {
                $this->spawned = ['command' => $command, 'cwd' => $cwd, 'env' => $env];

                return $this->toolingExitCode;
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
