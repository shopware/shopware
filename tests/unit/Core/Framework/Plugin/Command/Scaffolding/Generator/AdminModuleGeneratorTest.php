<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\AdminModuleGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AdminModuleGenerator::class)]
class AdminModuleGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $generator = new AdminModuleGenerator();

        $option = $generator->getCommandOption();
        static::assertNotEmpty($option->getName());
        static::assertNotEmpty($option->getDescription());
        static::assertSame('Admin Module', $generator->getCommandOptionTitle());
        static::assertNotEmpty($generator->getCommandOptionDescriptionLong());
    }

    #[DataProvider('addScaffoldConfigProvider')]
    public function testAddScaffoldConfig(bool $optionAlreadySet, string $answer, bool $expectedHasOption): void
    {
        $configuration = $this->getConfig();

        (new AdminModuleGenerator())->addScaffoldConfig(
            $configuration,
            $input = ScaffoldConsole::input(option: $optionAlreadySet, answer: $answer),
            ScaffoldConsole::style($input),
        );

        static::assertSame($expectedHasOption, $configuration->hasOption(AdminModuleGenerator::OPTION_NAME));
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

        (new AdminModuleGenerator())
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
            'config' => self::getConfig([AdminModuleGenerator::OPTION_NAME => false]),
            'expected' => [],
        ];

        yield 'Option true, stubs' => [
            'config' => self::getConfig([AdminModuleGenerator::OPTION_NAME => true]),
            'expected' => [
                'src/Resources/app/administration/src/module/swag-example/index.js',
                'src/Resources/app/administration/src/module/swag-example/page/swag-example-list/index.js',
                'src/Resources/app/administration/src/module/swag-example/page/swag-example-list/swag-example-list.html.twig',
                'src/Resources/app/administration/src/module/swag-example/page/swag-example-list/swag-example-list.scss',
                'src/Resources/app/administration/src/main.js',
                'src/Resources/app/administration/src/snippet/en.json',
                'src/Resources/app/administration/src/snippet/de.json',
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
