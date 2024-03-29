<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\EntityGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(EntityGenerator::class)]
class EntityGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $generator = new EntityGenerator();

        static::assertTrue($generator->hasCommandOption());
        static::assertNotEmpty($generator->getCommandOptionName());
        static::assertNotEmpty($generator->getCommandOptionDescription());
    }

    /**
     * @param array<int, string>|null $expectedEntities
     */
    #[DataProvider('addScaffoldConfigProvider')]
    #[DataProvider('provideEntities')]
    #[DataProvider('provideEmptyEntities')]
    public function testAddScaffoldConfig(
        mixed $getOptionResponse,
        bool $confirmResponse,
        mixed $entitiesAnswerInput,
        bool $expectedHasOption,
        ?array $expectedEntities = []
    ): void {
        $configuration = $this->getConfig();

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')->willReturn($getOptionResponse);

        $io = $this->createMock(SymfonyStyle::class);
        $io->method('confirm')->willReturn($confirmResponse);
        $io->method('ask')->willReturn($entitiesAnswerInput);

        (new EntityGenerator())
            ->addScaffoldConfig($configuration, $input, $io);

        static::assertEquals($expectedHasOption, $configuration->hasOption(EntityGenerator::OPTION_NAME));
        static::assertEquals($expectedEntities, $configuration->getOption(EntityGenerator::OPTION_NAME));
    }

    public static function addScaffoldConfigProvider(): \Generator
    {
        yield 'with command option and with confirm' => [
            'getOptionResponse' => 'TestEntity,TestEntity2',
            'confirmResponse' => true,
            'entitiesAnswerInput' => '',
            'expectedHasOption' => true,
            'expectedEntities' => [
                'TestEntity',
                'TestEntity2',
            ],
        ];

        yield 'with command option and without confirm' => [
            'getOptionResponse' => 'TestEntity,TestEntity2',
            'confirmResponse' => false,
            'entitiesAnswerInput' => '',
            'expectedHasOption' => true,
            'expectedEntities' => [
                'TestEntity',
                'TestEntity2',
            ],
        ];

        yield 'without command option and with confirm' => [
            'getOptionResponse' => false,
            'confirmResponse' => true,
            'entitiesAnswerInput' => 'TestEntity,TestEntity2',
            'expectedHasOption' => true,
            'expectedEntities' => [
                'TestEntity',
                'TestEntity2',
            ],
        ];

        yield 'without command option and without confirm' => [
            'getOptionResponse' => false,
            'confirmResponse' => false,
            'entitiesAnswerInput' => 'TestEntity,TestEntity2',
            'expectedHasOption' => false,
            'expectedEntities' => null,
        ];
    }

    public static function provideEntities(): \Generator
    {
        yield 'single entity' => [
            'getOptionResponse' => 'TestEntity',
            'confirmResponse' => false,
            'entitiesAnswerInput' => '',
            'expectedHasOption' => true,
            'expectedEntities' => [
                'TestEntity',
            ],
        ];

        yield 'multiple entities with comma' => [
            'getOptionResponse' => 'TestEntity,TestEntity2',
            'confirmResponse' => false,
            'entitiesAnswerInput' => '',
            'expectedHasOption' => true,
            'expectedEntities' => [
                'TestEntity',
                'TestEntity2',
            ],
        ];

        yield 'multiple entities with comma and spaces' => [
            'getOptionResponse' => 'TestEntity, TestEntity2',
            'confirmResponse' => false,
            'entitiesAnswerInput' => '',
            'expectedHasOption' => true,
            'expectedEntities' => [
                'TestEntity',
                'TestEntity2',
            ],
        ];

        yield 'multiple entities with comma and spaces and backslash' => [
            'getOptionResponse' => 'TestEntity, \TestEntity2',
            'confirmResponse' => false,
            'entitiesAnswerInput' => '',
            'expectedHasOption' => true,
            'expectedEntities' => [
                'TestEntity',
                'TestEntity2',
            ],
        ];

        yield 'multiple entities with comma and spaces and backslash and quotes and double quotes' => [
            'getOptionResponse' => 'TestEntity, \TestEntity2, "TestEntity3", \'TestEntity4\'',
            'confirmResponse' => false,
            'entitiesAnswerInput' => '',
            'expectedHasOption' => true,
            'expectedEntities' => [
                'TestEntity',
                'TestEntity2',
                'TestEntity3',
                'TestEntity4',
            ],
        ];
    }

    public static function provideEmptyEntities(): \Generator
    {
        yield 'empty string' => [
            'getOptionResponse' => false,
            'confirmResponse' => true,
            'entitiesAnswerInput' => '',
            'expectedHasOption' => false,
            'expectedEntities' => null,
        ];

        yield 'null' => [
            'getOptionResponse' => false,
            'confirmResponse' => true,
            'entitiesAnswerInput' => null,
            'expectedHasOption' => false,
            'expectedEntities' => null,
        ];

        yield 'only comma' => [
            'getOptionResponse' => false,
            'confirmResponse' => true,
            'entitiesAnswerInput' => ',,,,',
            'expectedHasOption' => false,
            'expectedEntities' => null,
        ];

        yield 'only comma and spaces' => [
            'getOptionResponse' => false,
            'confirmResponse' => true,
            'entitiesAnswerInput' => ', , , ,',
            'expectedHasOption' => false,
            'expectedEntities' => null,
        ];

        yield 'only comma and backslash' => [
            'getOptionResponse' => false,
            'confirmResponse' => true,
            'entitiesAnswerInput' => ',\,\,\,',
            'expectedHasOption' => false,
            'expectedEntities' => null,
        ];

        yield 'only comma and quotes' => [
            'getOptionResponse' => false,
            'confirmResponse' => true,
            'entitiesAnswerInput' => ',"",\'\'',
            'expectedHasOption' => false,
            'expectedEntities' => null,
        ];
    }

    /**
     * @param array<int, string> $expected
     */
    #[DataProvider('generateProvider')]
    public function testGenerate(PluginScaffoldConfiguration $config, array $expected): void
    {
        $stubs = new StubCollection();

        (new EntityGenerator(new \DateTimeImmutable('1988-01-01 00:00:00')))
            ->generateStubs($config, $stubs);

        static::assertCount(\count($expected), $stubs);

        foreach ($expected as $stub) {
            static::assertTrue($stubs->has($stub));
        }
    }

    public static function generateProvider(): \Generator
    {
        $timeStamp = (new \DateTimeImmutable('1988-01-01 00:00:00'))->getTimestamp();

        yield 'No option, no stubs' => [
            'config' => self::getConfig(),
            'expected' => [],
        ];

        yield 'Option false, no stubs' => [
            'config' => self::getConfig([EntityGenerator::OPTION_NAME => false]),
            'expected' => [],
        ];

        yield 'Option not array, no stubs' => [
            'config' => self::getConfig([EntityGenerator::OPTION_NAME => true]),
            'expected' => [],
        ];

        yield 'Option empty array, no stubs' => [
            'config' => self::getConfig([EntityGenerator::OPTION_NAME => []]),
            'expected' => [],
        ];

        yield 'Option with entity, one stub' => [
            'config' => self::getConfig([EntityGenerator::OPTION_NAME => ['Test']]),
            'expected' => [
                'src/Resources/config/services.xml',
                'src/Migration/Migration' . $timeStamp . 'CreateTestTable.php',
                'src/Core/Content/Test/TestEntity.php',
                'src/Core/Content/Test/TestDefinition.php',
                'src/Core/Content/Test/TestCollection.php',
            ],
        ];

        yield 'Option with entity, multiple stubs' => [
            'config' => self::getConfig([EntityGenerator::OPTION_NAME => ['Test1', 'Test2']]),
            'expected' => [
                'src/Resources/config/services.xml',
                'src/Migration/Migration' . $timeStamp . 'CreateTest1Table.php',
                'src/Migration/Migration' . $timeStamp . 'CreateTest2Table.php',
                'src/Core/Content/Test1/Test1Entity.php',
                'src/Core/Content/Test1/Test1Definition.php',
                'src/Core/Content/Test1/Test1Collection.php',
                'src/Core/Content/Test2/Test2Entity.php',
                'src/Core/Content/Test2/Test2Definition.php',
                'src/Core/Content/Test2/Test2Collection.php',
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
