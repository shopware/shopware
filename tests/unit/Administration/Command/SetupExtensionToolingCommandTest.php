<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\SetupExtensionToolingCommand;
use Shopware\Core\Framework\Log\Package;
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
#[CoversClass(SetupExtensionToolingCommand::class)]
class SetupExtensionToolingCommandTest extends TestCase
{
    private Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
    }

    public function testSetupCommandRunsTheSetupEntryScript(): void
    {
        $administrationRoot = $this->createAdministrationRoot(withDependencies: true);

        /** @var ToolingCall|null $captured */
        $captured = null;
        $command = $this->setupCommand($administrationRoot, function (array $command, string $cwd, array $env) use (&$captured): int {
            $captured = ['command' => $command, 'cwd' => $cwd, 'env' => $env];

            return 0;
        });
        $tester = new CommandTester($command);

        $tester->execute(['tooling-args' => ['--check']]);

        static::assertNotNull($captured);
        static::assertStringEndsWith('scripts/extensionTooling/setup.ts', $captured['command'][2]);
        static::assertContains('--check', $captured['command']);

        $this->filesystem->remove($administrationRoot);
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
    private function setupCommand(string $administrationRoot, \Closure $runTooling): SetupExtensionToolingCommand
    {
        return new class($this->kernel(), $administrationRoot, $runTooling) extends SetupExtensionToolingCommand {
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
