<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\PluginCreateCommand;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\EntityGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginCreateCommand::class)]
class PluginCreateCommandTest extends TestCase
{
    private string $projectDir;

    private Filesystem $projectFilesystem;

    private string|false $previousColumns;

    protected function setUp(): void
    {
        // Keep Symfony's error blocks from wrapping the directory path in the middle of a word.
        $this->previousColumns = getenv('COLUMNS');
        putenv('COLUMNS=200');

        $this->projectFilesystem = new Filesystem();
        $this->projectDir = sys_get_temp_dir() . '/sw-pc-' . bin2hex(random_bytes(4));
        $this->projectFilesystem->mkdir($this->projectDir . '/custom/plugins');
    }

    protected function tearDown(): void
    {
        $this->projectFilesystem->remove($this->projectDir);

        if ($this->previousColumns === false) {
            putenv('COLUMNS');
        } else {
            putenv('COLUMNS=' . $this->previousColumns);
        }
    }

    public function testCreatesPluginFromArguments(): void
    {
        $directory = $this->projectDir . '/custom/plugins/TestPlugin';
        $tester = $this->commandTester(writer: $this->writerExpectingPlugin($directory));

        $status = $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ], $this->consoleOptions(interactive: false));

        static::assertSame(Command::SUCCESS, $status);
        $display = $this->display($tester);
        static::assertStringContainsString('Successfully generated Plugin', $display);
        static::assertStringContainsString('1) run bin/console plugin:refresh', $display);
        static::assertStringContainsString('2) run bin/console plugin:install', $display);
    }

    public function testCreatesPluginInStaticPluginsDirectory(): void
    {
        $directory = $this->projectDir . '/custom/static-plugins/TestPlugin';
        $tester = $this->commandTester(writer: $this->writerExpectingPlugin($directory));

        $status = $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
            '--static' => true,
        ], $this->consoleOptions(interactive: false));

        static::assertSame(Command::SUCCESS, $status);
    }

    public function testNoScaffoldSkipsOptionalGenerators(): void
    {
        $optionalGenerator = $this->generatorWithOption();
        $optionalGenerator->expects($this->never())->method('addScaffoldConfig');

        $requiredGenerator = $this->requiredGenerator();
        $requiredGenerator->expects($this->once())->method('addScaffoldConfig');

        $tester = $this->commandTester([$optionalGenerator, $requiredGenerator]);

        $status = $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
            '--no-scaffold' => true,
        ], $this->consoleOptions(interactive: false));

        static::assertSame(Command::SUCCESS, $status);
    }

    /**
     * @param array<string, string> $arguments
     */
    #[DataProvider('missingArgumentProvider')]
    public function testRequiresNameAndNamespaceWhenNotInteractive(array $arguments): void
    {
        $tester = $this->commandTester();

        $status = $tester->execute($arguments, $this->consoleOptions(interactive: false));

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString(
            'Plugin name and namespace are required in non-interactive mode.',
            $this->display($tester),
        );
    }

    public static function missingArgumentProvider(): \Generator
    {
        yield 'missing name' => [
            'arguments' => ['plugin-namespace' => 'Test'],
        ];

        yield 'missing namespace' => [
            'arguments' => ['plugin-name' => 'TestPlugin'],
        ];

        yield 'missing both' => [
            'arguments' => [],
        ];
    }

    /**
     * @param array<string, string> $arguments
     */
    #[DataProvider('invalidArgumentProvider')]
    public function testRejectsArgumentThatIsNotPascalCase(array $arguments, string $message): void
    {
        $tester = $this->commandTester();

        $status = $tester->execute($arguments, $this->consoleOptions(interactive: false));

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString($message, $this->display($tester));
    }

    public static function invalidArgumentProvider(): \Generator
    {
        yield 'invalid plugin name' => [
            'arguments' => [
                'plugin-name' => 'testPlugin',
                'plugin-namespace' => 'Test',
            ],
            'message' => 'Invalid plugin name provided. Use PascalCase format.',
        ];

        yield 'invalid plugin namespace' => [
            'arguments' => [
                'plugin-name' => 'TestPlugin',
                'plugin-namespace' => 'test',
            ],
            'message' => 'Invalid plugin namespace provided. Use PascalCase format.',
        ];
    }

    public function testRejectsExistingPluginDirectory(): void
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);

        $tester = $this->commandTester(filesystem: $filesystem);

        $status = $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ], $this->consoleOptions(interactive: false));

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString(
            'Plugin directory ' . $this->projectDir . '/custom/plugins/TestPlugin already exists',
            $this->display($tester),
        );
    }

    public function testRejectsRunOutsideAShopwareProject(): void
    {
        $tester = $this->commandTester(projectDir: $this->projectDir . '/missing');

        $status = $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ], $this->consoleOptions(interactive: false));

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString(
            'This command must be run inside a Shopware project.',
            $this->display($tester),
        );
    }

    public function testRemovesDirectoryWhenWritingFails(): void
    {
        $directory = $this->projectDir . '/custom/plugins/TestPlugin';

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturnOnConsecutiveCalls(false, true);
        $filesystem->expects($this->once())->method('remove')->with($directory);

        $writer = static::createStub(ScaffoldingWriter::class);
        $writer->method('write')->willThrowException(new \RuntimeException('Could not write plugin'));

        $tester = $this->commandTester(filesystem: $filesystem, writer: $writer);

        $status = $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ], $this->consoleOptions(interactive: false));

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString('Could not write plugin', $this->display($tester));
    }

    public function testPromptsForMissingNameAndNamespace(): void
    {
        $directory = $this->projectDir . '/custom/plugins/TestPlugin';
        $tester = $this->commandTester(writer: $this->writerExpectingPlugin($directory));
        $tester->setInputs(['TestPlugin', 'Test', 'n', 'y']);

        $status = $tester->execute([], $this->consoleOptions());

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('Successfully generated Plugin', $this->display($tester));
    }

    /**
     * @param list<string> $inputs
     */
    #[DataProvider('invalidPromptProvider')]
    public function testRejectsInvalidPrompt(array $inputs, string $message): void
    {
        $tester = $this->commandTester();
        $tester->setInputs($inputs);

        $status = $tester->execute([], $this->consoleOptions());

        static::assertSame(Command::FAILURE, $status);
        static::assertStringContainsString($message, $this->display($tester));
    }

    public static function invalidPromptProvider(): \Generator
    {
        yield 'empty plugin name' => [
            'inputs' => [''],
            'message' => 'The plugin name cannot be empty.',
        ];

        yield 'plugin name is not PascalCase' => [
            'inputs' => ['testPlugin'],
            'message' => 'The plugin name must be in PascalCase.',
        ];

        yield 'empty plugin namespace' => [
            'inputs' => ['TestPlugin', ''],
            'message' => 'The plugin namespace cannot be empty.',
        ];

        yield 'plugin namespace is not PascalCase' => [
            'inputs' => ['TestPlugin', 'vendor'],
            'message' => 'The plugin namespace must be in PascalCase.',
        ];
    }

    public function testDecliningAdditionalScaffoldingSkipsOptionalGenerators(): void
    {
        $optionalGenerator = $this->generatorWithOption();
        $optionalGenerator->expects($this->never())->method('addScaffoldConfig');

        $requiredGenerator = $this->requiredGenerator();
        $requiredGenerator->expects($this->once())->method('addScaffoldConfig');

        $tester = $this->commandTester([$optionalGenerator, $requiredGenerator]);
        $tester->setInputs(['n', 'y']);

        $status = $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ], $this->consoleOptions());

        static::assertSame(Command::SUCCESS, $status);
    }

    public function testAcceptingAdditionalScaffoldingRunsOptionalGenerators(): void
    {
        $optionalGenerator = $this->generatorWithOption();
        $optionalGenerator->expects($this->once())->method('addScaffoldConfig');

        $requiredGenerator = $this->requiredGenerator();
        $requiredGenerator->expects($this->once())->method('addScaffoldConfig');

        $tester = $this->commandTester([$optionalGenerator, $requiredGenerator]);
        $tester->setInputs(['y', 'y']);

        $status = $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ], $this->consoleOptions());

        static::assertSame(Command::SUCCESS, $status);
    }

    public function testCancellingGenerationDoesNotWrite(): void
    {
        $writer = $this->createMock(ScaffoldingWriter::class);
        $writer->expects($this->never())->method('write');

        $tester = $this->commandTester(writer: $writer);
        $tester->setInputs(['n', 'n']);

        $status = $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ], $this->consoleOptions());

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString('Plugin generation was cancelled.', $this->display($tester));
    }

    /**
     * @param true|list<string> $optionValue
     */
    #[DataProvider('scaffoldSummaryProvider')]
    public function testSummaryListsConfirmedScaffolding(mixed $optionValue, string $expectedLine): void
    {
        $generator = $this->generatorWithOption();
        $generator->expects($this->once())
            ->method('addScaffoldConfig')
            ->willReturnCallback(static function (PluginScaffoldConfiguration $configuration) use ($optionValue): void {
                $configuration->addOption('test-option', $optionValue);
            });

        $tester = $this->commandTester([$generator]);
        $tester->setInputs(['y', 'y']);

        $status = $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ], $this->consoleOptions());

        static::assertSame(Command::SUCCESS, $status);
        static::assertStringContainsString(
            'Adding the following scaffolding: - ' . $expectedLine,
            $this->display($tester),
        );
    }

    public static function scaffoldSummaryProvider(): \Generator
    {
        yield 'single scaffold option' => [
            'optionValue' => true,
            'expectedLine' => 'Title',
        ];

        yield 'scaffold option with several values' => [
            'optionValue' => ['A', 'B'],
            'expectedLine' => 'Title: A, B',
        ];
    }

    public function testRejectsOutputThatIsNotAConsole(): void
    {
        $tester = $this->commandTester();

        $this->expectExceptionObject(new \InvalidArgumentException(
            'This command accepts only an instance of "ConsoleOutputInterface".'
        ));

        $tester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ], ['interactive' => false]);
    }

    /**
     * @param list<ScaffoldingGenerator> $generators
     */
    private function commandTester(
        array $generators = [],
        ?Filesystem $filesystem = null,
        ?ScaffoldingWriter $writer = null,
        ?string $projectDir = null,
    ): CommandTester {
        $command = new PluginCreateCommand(
            $projectDir ?? $this->projectDir,
            static::createStub(ScaffoldingCollector::class),
            $writer ?? static::createStub(ScaffoldingWriter::class),
            $filesystem ?? $this->filesystemThatDoesNotFindPlugin(),
            $generators,
        );

        return new CommandTester($command);
    }

    private function filesystemThatDoesNotFindPlugin(): Filesystem
    {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('exists')->willReturn(false);

        return $filesystem;
    }

    private function writerExpectingPlugin(string $directory): ScaffoldingWriter&MockObject
    {
        $writer = $this->createMock(ScaffoldingWriter::class);
        $writer->expects($this->once())
            ->method('write')
            ->with(
                static::anything(),
                static::callback(static function (PluginScaffoldConfiguration $configuration) use ($directory): bool {
                    return $configuration->name === 'TestPlugin'
                        && $configuration->namespace === 'Test'
                        && $configuration->directory === $directory;
                }),
            );

        return $writer;
    }

    private function generatorWithOption(): MockObject&ScaffoldingGenerator
    {
        $generator = $this->createMock(ScaffoldingGenerator::class);
        $generator->method('hasCommandOption')->willReturn(true);
        $generator->method('getCommandOptionName')->willReturn('test-option');
        $generator->method('getCommandOptionDescription')->willReturn('Example option');
        $generator->method('getCommandOptionTitle')->willReturn('Title');

        return $generator;
    }

    private function requiredGenerator(): MockObject&ScaffoldingGenerator
    {
        $generator = $this->createMock(ScaffoldingGenerator::class);
        $generator->method('hasCommandOption')->willReturn(false);

        return $generator;
    }

    /**
     * @return array{interactive: bool, capture_stderr_separately: true}
     */
    private function consoleOptions(bool $interactive = true): array
    {
        return [
            'interactive' => $interactive,
            'capture_stderr_separately' => true,
        ];
    }

    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', trim($tester->getDisplay(true)));
    }
}
