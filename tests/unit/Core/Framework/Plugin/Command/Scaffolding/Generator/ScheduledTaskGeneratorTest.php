<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\ScheduledTaskGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ScheduledTaskGenerator::class)]
class ScheduledTaskGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $generator = new ScheduledTaskGenerator();

        static::assertTrue($generator->hasCommandOption());
        static::assertNotEmpty($generator->getCommandOptionName());
        static::assertNotEmpty($generator->getCommandOptionDescription());
        static::assertSame('Scheduled Task', $generator->getCommandOptionTitle());
        static::assertNotEmpty($generator->getCommandOptionDescriptionLong());
    }

    #[DataProvider('addScaffoldConfigProvider')]
    public function testAddScaffoldConfig(bool $optionAlreadySet, string $answer, bool $expectedHasOption): void
    {
        $configuration = $this->getConfig();

        (new ScheduledTaskGenerator())->addScaffoldConfig(
            $configuration,
            ScaffoldConsole::input(option: $optionAlreadySet, answer: $answer),
            ScaffoldConsole::output(),
        );

        static::assertSame($expectedHasOption, $configuration->hasOption(ScheduledTaskGenerator::OPTION_NAME));
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

        (new ScheduledTaskGenerator())
            ->generateStubs($config, $stubs);

        static::assertCount(\count($expected), $stubs);

        foreach ($expected as $stub) {
            static::assertTrue($stubs->has($stub));
        }
    }

    public static function generateProvider(): \Generator
    {
        yield 'No option, no stubs' => [
            'config' => self::getConfig(),
            'expected' => [],
        ];

        yield 'Option false, no stubs' => [
            'config' => self::getConfig([ScheduledTaskGenerator::OPTION_NAME => false]),
            'expected' => [],
        ];

        yield 'Option true, stubs' => [
            'config' => self::getConfig([ScheduledTaskGenerator::OPTION_NAME => true]),
            'expected' => [
                'src/Resources/config/services.php',
                'src/ScheduledTask/ExampleTask.php',
                'src/ScheduledTask/ExampleTaskHandler.php',
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
