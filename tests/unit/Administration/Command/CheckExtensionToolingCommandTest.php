<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Command\CheckExtensionToolingCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CheckExtensionToolingCommand::class)]
class CheckExtensionToolingCommandTest extends TestCase
{
    private Filesystem $filesystem;

    private string $administrationRoot;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->administrationRoot = sys_get_temp_dir() . '/' . uniqid('sw-extension-check-', true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->administrationRoot);
    }

    public function testMissingNodeDependenciesIsAToolError(): void
    {
        $this->filesystem->mkdir($this->administrationRoot);

        $tester = new CommandTester(new CheckExtensionToolingCommand($this->kernel(), $this->administrationRoot));

        static::assertSame(3, $tester->execute([]));
        static::assertStringContainsString('Node dependencies are not installed', $tester->getDisplay(true));
        static::assertStringContainsString('npm ci', $tester->getDisplay(true));
    }

    public function testItSpawnsTheCheckerWithTheProjectRootAndNoArguments(): void
    {
        $command = $this->recordingCommand();
        $tester = new CommandTester($command);

        static::assertSame(0, $tester->execute([]));
        static::assertSame([
            $this->administrationRoot . '/node_modules/.bin/ts-node',
            '--transpileOnly',
            $this->administrationRoot . '/scripts/extensionTooling/cli.ts',
        ], $command->command);
        static::assertSame($this->administrationRoot, $command->cwd);
        static::assertSame(['PROJECT_ROOT' => '/project'], $command->env);
    }

    public function testItForwardsNamesAndOptions(): void
    {
        $command = $this->recordingCommand();
        $tester = new CommandTester($command);

        $tester->execute([
            'names' => ['SwagCommercial', 'SwagPayPal'],
            '--types' => true,
            '--lint' => true,
            '--fix' => true,
            '--include-platform' => true,
        ]);

        static::assertSame([
            'SwagCommercial',
            'SwagPayPal',
            '--types',
            '--lint',
            '--fix',
            '--include-platform',
        ], \array_slice($command->command, 3));
    }

    public function testItOmitsOptionsThatWereNotPassed(): void
    {
        $command = $this->recordingCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--lint' => true]);

        static::assertSame(['--lint'], \array_slice($command->command, 3));
    }

    /**
     * The three states of the checker must stay distinguishable through the bridge:
     * a crash must never arrive as "findings".
     */
    public function testItReturnsTheCheckerExitCodeVerbatim(): void
    {
        foreach ([
            0,
            1,
            2,
            3,
        ] as $exitCode) {
            $command = $this->recordingCommand($exitCode);
            $tester = new CommandTester($command);

            static::assertSame($exitCode, $tester->execute([]));
        }
    }

    private function recordingCommand(int $exitCode = 0): RecordingCheckExtensionToolingCommand
    {
        $this->filesystem->mkdir($this->administrationRoot . '/node_modules/.bin');
        $this->filesystem->touch($this->administrationRoot . '/node_modules/.bin/ts-node');

        return new RecordingCheckExtensionToolingCommand($this->kernel(), $this->administrationRoot, $exitCode);
    }

    private function kernel(): KernelInterface
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getProjectDir')->willReturn('/project');

        return $kernel;
    }
}

/**
 * @internal
 */
#[Package('framework')]
class RecordingCheckExtensionToolingCommand extends CheckExtensionToolingCommand
{
    /**
     * @var list<string>
     */
    public array $command = [];

    public string $cwd = '';

    /**
     * @var array<string, string>
     */
    public array $env = [];

    public function __construct(
        KernelInterface $kernel,
        string $administrationRootPath,
        private readonly int $exitCode,
    ) {
        parent::__construct($kernel, $administrationRootPath);
    }

    protected function runChecker(array $command, string $cwd, array $env, OutputInterface $output): int
    {
        $this->command = $command;
        $this->cwd = $cwd;
        $this->env = $env;

        return $this->exitCode;
    }
}
