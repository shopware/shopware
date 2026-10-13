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
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginCreateCommand::class)]
class PluginCreateCommandTest extends TestCase
{
    /**
     * @param array<string, string|true> $arguments
     * @param list<string> $inputs
     * @param list<string|null> $generatorOptions option name per generator, null for a generator without option
     */
    #[DataProvider('commandProvider')]
    public function testSuccessfulCreateCommandWithArgumentsOrInputs(
        array $arguments,
        array $inputs,
        array $generatorOptions = []
    ): void {
        $generatorMocks = [];
        foreach ($generatorOptions as $optionName) {
            $generatorMock = static::createStub(ScaffoldingGenerator::class);
            $generatorMock->method('getCommandOption')->willReturn($optionName === null ? null : new InputOption($optionName));

            $generatorMocks[] = $generatorMock;
        }

        $commandTester = $this->getCommandTester($generatorMocks);

        $commandTester->setInputs($inputs);

        $commandTester->execute($arguments);

        $commandTester->assertCommandIsSuccessful();

        static::assertStringContainsString(
            'Plugin created successfully',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );
    }

    public static function commandProvider(): \Generator
    {
        yield 'with arguments' => [
            'arguments' => [
                'plugin-name' => 'TestPlugin',
                'plugin-namespace' => 'Test',
            ],
            'inputs' => [],
        ];

        yield 'with inputs' => [
            'arguments' => [],
            'inputs' => [
                'TestPlugin',
                'Test',
            ],
        ];

        yield 'with generators and options' => [
            'arguments' => [
                'plugin-name' => 'TestPlugin',
                'plugin-namespace' => 'Test',
                '--test-option' => true,
                '--static' => true,
            ],
            'inputs' => [],
            'generatorOptions' => ['test-option'],
        ];

        yield 'with generators but no option' => [
            'arguments' => [
                'plugin-name' => 'TestPlugin',
                'plugin-namespace' => 'Test',
            ],
            'inputs' => [],
            'generatorOptions' => [null],
        ];

        yield 'with --no-scaffold skips optional generators' => [
            'arguments' => [
                'plugin-name' => 'TestPlugin',
                'plugin-namespace' => 'Test',
                '--no-scaffold' => true,
            ],
            'inputs' => [],
            'generatorOptions' => ['test-option'],
        ];
    }

    /**
     * @param list<string> $inputs
     */
    #[DataProvider('invalidInputsProvider')]
    public function testInvalidInputs(array $inputs, string $expectedErrorMessage, bool $interactive = true): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->setInputs($inputs);

        $commandTester->execute([], ['interactive' => $interactive]);

        static::assertStringContainsString(
            $expectedErrorMessage,
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );
    }

    public static function invalidInputsProvider(): \Generator
    {
        yield 'empty inputs' => [
            'inputs' => [''],
            'expectedErrorMessage' => 'Answer cannot be empty',
        ];

        yield 'invalid plugin name' => [
            'inputs' => ['test'],
            'expectedErrorMessage' => 'The name must start with an uppercase character',
        ];

        yield 'non-interactive without arguments' => [
            'inputs' => [],
            'expectedErrorMessage' => 'This command requires interactive mode or the argument must be provided.',
            'interactive' => false,
        ];
    }

    public function testNoScaffoldSkipsOptionalGenerators(): void
    {
        /** @var MockObject&ScaffoldingGenerator $optionalGenerator */
        $optionalGenerator = $this->createMock(ScaffoldingGenerator::class);
        $optionalGenerator->method('getCommandOption')->willReturn(new InputOption('test-option'));
        $optionalGenerator->expects($this->never())->method('addScaffoldConfig');

        /** @var MockObject&ScaffoldingGenerator $requiredGenerator */
        $requiredGenerator = $this->createMock(ScaffoldingGenerator::class);
        $requiredGenerator->method('getCommandOption')->willReturn(null);
        $requiredGenerator->expects($this->once())->method('addScaffoldConfig');

        $commandTester = $this->getCommandTester([$optionalGenerator, $requiredGenerator]);

        $commandTester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
            '--no-scaffold' => true,
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testInteractiveScaffoldQuestionNo(): void
    {
        /** @var MockObject&ScaffoldingGenerator $optionalGenerator */
        $optionalGenerator = $this->createMock(ScaffoldingGenerator::class);
        $optionalGenerator->method('getCommandOption')->willReturn(new InputOption('test-option'));
        $optionalGenerator->expects($this->never())->method('addScaffoldConfig');

        $commandTester = $this->getCommandTester([$optionalGenerator]);
        $commandTester->setInputs(['no']);

        $commandTester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testInteractiveScaffoldQuestionYes(): void
    {
        /** @var MockObject&ScaffoldingGenerator $optionalGenerator */
        $optionalGenerator = $this->createMock(ScaffoldingGenerator::class);
        $optionalGenerator->method('getCommandOption')->willReturn(new InputOption('test-option'));
        $optionalGenerator->expects($this->once())->method('addScaffoldConfig');

        $commandTester = $this->getCommandTester([$optionalGenerator]);
        $commandTester->setInputs(['yes']);

        $commandTester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ]);

        $commandTester->assertCommandIsSuccessful();
    }

    public function testEntitiesOptionAcceptsCommaSeparatedList(): void
    {
        $collector = $this->createMock(ScaffoldingCollector::class);
        $collector->expects($this->once())
            ->method('collect')
            ->with(static::callback(static function (PluginScaffoldConfiguration $configuration): bool {
                static::assertSame(['Foo', 'Bar'], $configuration->getOption(EntityGenerator::OPTION_NAME));

                return true;
            }))
            ->willReturn(new StubCollection());

        $command = $this->createCommand([new EntityGenerator(new MockClock())], false, $collector);

        // StringInput parses like the real CLI; the ArrayInput used by CommandTester accepts values for flag options
        $input = new StringInput('TestPlugin Test --entities=Foo,Bar');
        $input->setInteractive(false);

        static::assertSame(Command::SUCCESS, $command->run($input, new NullOutput()));
    }

    public function testEntitiesOptionRequiresValue(): void
    {
        $command = $this->createCommand([new EntityGenerator(new MockClock())]);

        $input = new StringInput('TestPlugin Test --entities');
        $input->setInteractive(false);

        $this->expectExceptionObject(new RuntimeException('The "--entities" option requires a value.'));

        $command->run($input, new NullOutput());
    }

    public function testDirectoryExists(): void
    {
        $commandTester = $this->getCommandTester([], true);

        $commandTester->execute([
            'plugin-name' => 'TestPlugin',
            'plugin-namespace' => 'Test',
        ]);

        static::assertStringContainsString(
            'Plugin directory shopware/custom/plugins/TestPlugin already exists',
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );
    }

    /**
     * @param array<ScaffoldingGenerator> $generators
     */
    private function getCommandTester(array $generators = [], bool $directoryExists = false): CommandTester
    {
        $command = $this->createCommand($generators, $directoryExists);

        $commandTester = new CommandTester($command);
        $application = new Application();
        $application->addCommand($command);

        return $commandTester;
    }

    /**
     * @param array<ScaffoldingGenerator> $generators
     */
    private function createCommand(
        array $generators = [],
        bool $directoryExists = false,
        ?ScaffoldingCollector $collector = null,
    ): PluginCreateCommand {
        $filesystem = static::createStub(Filesystem::class);
        $filesystem->method('exists')->willReturn($directoryExists);

        return new PluginCreateCommand(
            'shopware',
            $collector ?? static::createStub(ScaffoldingCollector::class),
            static::createStub(ScaffoldingWriter::class),
            $filesystem,
            $generators
        );
    }
}
