<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\StorefrontControllerGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontControllerGenerator::class)]
class StorefrontControllerGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $generator = new StorefrontControllerGenerator();

        $option = $generator->getCommandOption();
        static::assertNotEmpty($option->getName());
        static::assertNotEmpty($option->getDescription());
        static::assertSame('Storefront Controller', $generator->getCommandOptionTitle());
        static::assertNotEmpty($generator->getCommandOptionDescriptionLong());
    }

    #[DataProvider('addScaffoldConfigProvider')]
    public function testAddScaffoldConfig(bool $optionAlreadySet, string $answer, bool $expectedHasOption): void
    {
        $configuration = $this->getConfig();

        (new StorefrontControllerGenerator())->addScaffoldConfig(
            $configuration,
            $input = ScaffoldConsole::input(option: $optionAlreadySet, answer: $answer),
            ScaffoldConsole::style($input),
        );

        static::assertSame($expectedHasOption, $configuration->hasOption(StorefrontControllerGenerator::OPTION_NAME));
        static::assertSame($expectedHasOption, $configuration->hasOption(PluginScaffoldConfiguration::ROUTE_XML_OPTION_NAME));
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

        (new StorefrontControllerGenerator())
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
            'config' => self::getConfig([StorefrontControllerGenerator::OPTION_NAME => false]),
            'expected' => [],
        ];

        yield 'Option true, stubs' => [
            'config' => self::getConfig([StorefrontControllerGenerator::OPTION_NAME => true]),
            'expected' => [
                'src/Resources/config/services.php',
                'src/Resources/config/routes.php',
                'src/Storefront/Controller/ExampleController.php',
                'src/Resources/views/storefront/page/example.html.twig',
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
