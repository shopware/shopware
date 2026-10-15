<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\CommandGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CommandGenerator::class)]
class CommandGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $generator = new CommandGenerator();

        $option = $generator->getCommandOption();
        static::assertSame(CommandGenerator::OPTION_NAME, $option->getName());
        static::assertNotSame('', $option->getDescription());
        static::assertSame('Custom Command', $generator->getCommandOptionTitle());
        static::assertNotSame('', $generator->getCommandOptionDescriptionLong());
    }

    #[DataProvider('addScaffoldConfigProvider')]
    public function testAddScaffoldConfig(bool $optionAlreadySet, string $answer, bool $expectedHasOption): void
    {
        $configuration = $this->getConfig();

        (new CommandGenerator())->addScaffoldConfig(
            $configuration,
            ScaffoldConsole::input(option: $optionAlreadySet, answer: $answer),
            ScaffoldConsole::output(),
        );

        static::assertSame($expectedHasOption, $configuration->hasOption(CommandGenerator::OPTION_NAME));
    }

    public static function addScaffoldConfigProvider(): \Generator
    {
        yield 'cli option stores the scaffold option' => [
            'optionAlreadySet' => true,
            'answer' => '',
            'expectedHasOption' => true,
        ];

        yield 'answering yes stores the scaffold option' => [
            'optionAlreadySet' => false,
            'answer' => 'y',
            'expectedHasOption' => true,
        ];

        yield 'answering no skips the scaffold option' => [
            'optionAlreadySet' => false,
            'answer' => 'n',
            'expectedHasOption' => false,
        ];
    }

    /**
     * @param array<int, string> $expected
     */
    #[DataProvider('generateProvider')]
    public function testGenerate(PluginScaffoldConfiguration $config, array $expected): void
    {
        $stubs = new StubCollection();

        (new CommandGenerator())
            ->generateStubs($config, $stubs);

        static::assertCount(\count($expected), $stubs);

        foreach ($expected as $stub) {
            static::assertTrue($stubs->has($stub));
        }
    }

    public function testGeneratedCommandIsInvokable(): void
    {
        $configuration = self::getConfig([CommandGenerator::OPTION_NAME => true]);
        $stubs = new StubCollection();
        (new CommandGenerator())->generateStubs($configuration, $stubs);

        $content = $stubs->get('src/Command/ExampleCommand.php')?->getContent();
        static::assertNotNull($content);

        $filesystem = new Filesystem();
        $path = $filesystem->tempnam(sys_get_temp_dir(), 'shopware-command-', '.php');

        try {
            $filesystem->dumpFile($path, $content);
            require $path;

            $class = $configuration->namespace . '\\Command\\ExampleCommand';
            static::assertTrue(class_exists($class));
            $command = new $class();
            static::assertInstanceOf(Command::class, $command);
            static::assertIsCallable($command);

            $tester = new CommandTester($command);
            static::assertSame(Command::SUCCESS, $tester->execute([]));
            static::assertSame('It works!' . \PHP_EOL, $tester->getDisplay());
        } finally {
            $filesystem->remove($path);
        }
    }

    public static function generateProvider(): \Generator
    {
        yield 'No option, no stubs' => [
            'config' => self::getConfig(),
            'expected' => [],
        ];

        yield 'Option false, no stubs' => [
            'config' => self::getConfig([CommandGenerator::OPTION_NAME => false]),
            'expected' => [],
        ];

        yield 'Option true, stubs' => [
            'config' => self::getConfig([CommandGenerator::OPTION_NAME => true]),
            'expected' => [
                'src/Resources/config/services.php',
                'src/Command/ExampleCommand.php',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function getConfig(array $options = []): PluginScaffoldConfiguration
    {
        return new PluginScaffoldConfiguration('TestPlugin', 'MyNamespace', '/path/to/directory', $options);
    }
}
