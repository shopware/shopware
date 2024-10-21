<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Command\PluginCreateCommand;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScaffoldingGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingCollector;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\ScaffoldingWriter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(PluginCreateCommand::class)]
class PluginCreateCommandTest extends TestCase
{
    /**
     * @param array<string, string> $arguments
     * @param array<string, string> $inputs
     * @param array<int, array<string, mixed>> $generators
     */
    #[DataProvider('commandProvider')]
    public function testSuccessfulCreateCommandWithArguments(
        array $arguments,
        array $inputs,
        array $generators = []
    ): void {
        $generatorMocks = [];
        foreach ($generators as $generator) {
            /** @var MockObject&ScaffoldingGenerator $generatorMock */
            $generatorMock = $this->createMock(ScaffoldingGenerator::class);

            $generatorMock->method('hasCommandOption')->willReturn($generator['hasCommandOption']);
            $generatorMock->method('getCommandOptionName')->willReturn($generator['getCommandOptionName']);

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
            'generators' => [
                [
                    'hasCommandOption' => true,
                    'getCommandOptionName' => 'test-option',
                ],
            ],
        ];

        yield 'with generators but no option' => [
            'arguments' => [
                'plugin-name' => 'TestPlugin',
                'plugin-namespace' => 'Test',
            ],
            'inputs' => [],
            'generators' => [
                [
                    'hasCommandOption' => false,
                    'getCommandOptionName' => '',
                ],
            ],
        ];
    }

    /**
     * @param array<int, string> $inputs
     */
    #[DataProvider('invalidInputsProvider')]
    public function testInvalidInputs(array $inputs, string $expectedErrorMessage): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->setInputs($inputs);

        $commandTester->execute([]);

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
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn($directoryExists);

        $command = new PluginCreateCommand(
            'shopware',
            $this->createMock(ScaffoldingCollector::class),
            $this->createMock(ScaffoldingWriter::class),
            $filesystem,
            $generators
        );

        $commandTester = new CommandTester($command);
        $application = new Application();
        $application->add($command);

        return $commandTester;
    }
}
