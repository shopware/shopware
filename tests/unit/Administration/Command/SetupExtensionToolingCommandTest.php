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
 */
#[Package('framework')]
#[CoversClass(SetupExtensionToolingCommand::class)]
class SetupExtensionToolingCommandTest extends TestCase
{
    private Filesystem $filesystem;

    private string $administrationRoot;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->administrationRoot = sys_get_temp_dir() . '/' . uniqid('sw-extension-setup-', true);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->administrationRoot);
    }

    public function testMissingNodeDependenciesIsAToolError(): void
    {
        $this->filesystem->mkdir($this->administrationRoot);

        $tester = new CommandTester(new SetupExtensionToolingCommand($this->kernel(), $this->administrationRoot));

        static::assertSame(3, $tester->execute([]));
        static::assertStringContainsString('npm ci', $tester->getDisplay(true));
    }

    public function testItSpawnsTheSetupScriptWithTheProjectRoot(): void
    {
        $command = $this->recordingCommand();
        $tester = new CommandTester($command);

        static::assertSame(0, $tester->execute([]));
        static::assertSame([
            $this->administrationRoot . '/node_modules/.bin/ts-node',
            '--transpileOnly',
            $this->administrationRoot . '/scripts/extensionTooling/setup.ts',
        ], $command->command);
        static::assertSame(['PROJECT_ROOT' => '/project'], $command->env);
    }

    public function testItTakesNoArgumentsOrOptions(): void
    {
        $definition = (new SetupExtensionToolingCommand($this->kernel(), $this->administrationRoot))->getDefinition();

        static::assertSame([], $definition->getArguments());
        static::assertSame([], $definition->getOptions());
    }

    public function testItReturnsTheToolingExitCodeVerbatim(): void
    {
        foreach ([
            0,
            2,
            3,
        ] as $exitCode) {
            $tester = new CommandTester($this->recordingCommand($exitCode));

            static::assertSame($exitCode, $tester->execute([]));
        }
    }

    private function recordingCommand(int $exitCode = 0): RecordingSetupExtensionToolingCommand
    {
        $this->filesystem->mkdir($this->administrationRoot . '/node_modules/.bin');
        $this->filesystem->touch($this->administrationRoot . '/node_modules/.bin/ts-node');

        return new RecordingSetupExtensionToolingCommand($this->kernel(), $this->administrationRoot, $exitCode);
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
class RecordingSetupExtensionToolingCommand extends SetupExtensionToolingCommand
{
    /**
     * @var list<string>
     */
    public array $command = [];

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

    protected function runTooling(array $command, string $cwd, array $env, OutputInterface $output): int
    {
        $this->command = $command;
        $this->env = $env;

        return $this->exitCode;
    }
}
