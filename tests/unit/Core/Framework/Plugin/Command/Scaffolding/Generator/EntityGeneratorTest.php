<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding\Generator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\Generator\EntityGenerator;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\PluginScaffoldConfiguration;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\StubCollection;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityGenerator::class)]
class EntityGeneratorTest extends TestCase
{
    public function testCommandOptions(): void
    {
        $generator = new EntityGenerator(new MockClock());

        static::assertTrue($generator->hasCommandOption());
        static::assertNotEmpty($generator->getCommandOptionName());
        static::assertNotEmpty($generator->getCommandOptionDescription());
        static::assertSame('Custom Entities', $generator->getCommandOptionTitle());
        static::assertNotEmpty($generator->getCommandOptionDescriptionLong());
    }

    /**
     * @param list<string> $expectedEntities
     */
    #[DataProvider('cliEntitiesProvider')]
    public function testParsesEntitiesFromTheCliOption(string $entities, array $expectedEntities): void
    {
        $configuration = $this->getConfig();

        $input = static::createStub(InputInterface::class);
        $input->method('getOption')->willReturn($entities);

        (new EntityGenerator(new MockClock()))->addScaffoldConfig(
            $configuration,
            $input,
            static::createStub(OutputInterface::class),
        );

        static::assertSame($expectedEntities, $configuration->getOption(EntityGenerator::OPTION_NAME));
    }

    public static function cliEntitiesProvider(): \Generator
    {
        yield 'single entity' => [
            'entities' => 'TestEntity',
            'expectedEntities' => ['TestEntity'],
        ];

        yield 'several entities separated by commas' => [
            'entities' => 'TestEntity,TestEntity2',
            'expectedEntities' => ['TestEntity', 'TestEntity2'],
        ];

        yield 'spaces around names are ignored' => [
            'entities' => 'TestEntity, TestEntity2',
            'expectedEntities' => ['TestEntity', 'TestEntity2'],
        ];

        yield 'backslashes around names are ignored' => [
            'entities' => 'TestEntity, \TestEntity2',
            'expectedEntities' => ['TestEntity', 'TestEntity2'],
        ];

        yield 'quotes around names are ignored' => [
            'entities' => 'TestEntity, \TestEntity2, "TestEntity3", \'TestEntity4\'',
            'expectedEntities' => ['TestEntity', 'TestEntity2', 'TestEntity3', 'TestEntity4'],
        ];
    }

    public function testSkipsEntitiesWhenTheUserDeclines(): void
    {
        $configuration = $this->getConfig();

        (new EntityGenerator(new MockClock()))->addScaffoldConfig(
            $configuration,
            ScaffoldConsole::input(option: false, answer: 'n'),
            ScaffoldConsole::output(),
        );

        static::assertFalse($configuration->hasOption(EntityGenerator::OPTION_NAME));
    }

    public function testStoresEntitiesWhenTheUserProvidesThem(): void
    {
        $configuration = $this->getConfig();

        (new EntityGenerator(new MockClock()))->addScaffoldConfig(
            $configuration,
            ScaffoldConsole::input(option: false, answer: "y\nTestEntity, TestEntity2"),
            ScaffoldConsole::output(),
        );

        static::assertSame(
            ['TestEntity', 'TestEntity2'],
            $configuration->getOption(EntityGenerator::OPTION_NAME),
        );
    }

    #[DataProvider('emptyEntityAnswerProvider')]
    public function testSkipsEntitiesWhenTheAnswerIsEmpty(string $answer): void
    {
        $configuration = $this->getConfig();

        (new EntityGenerator(new MockClock()))->addScaffoldConfig(
            $configuration,
            ScaffoldConsole::input(option: false, answer: "y\n" . $answer),
            ScaffoldConsole::output(),
        );

        static::assertFalse($configuration->hasOption(EntityGenerator::OPTION_NAME));
    }

    public static function emptyEntityAnswerProvider(): \Generator
    {
        yield 'empty answer' => ['answer' => ''];

        yield 'only commas' => ['answer' => ',,,,'];

        yield 'commas and spaces' => ['answer' => ', , , ,'];

        yield 'commas and backslashes' => ['answer' => ',\\,\\,\\,'];

        yield 'commas and quotes' => ['answer' => ',"",\'\''];
    }

    public function testAddScaffoldConfigRejectsOutputThatIsNotAConsole(): void
    {
        $input = static::createStub(InputInterface::class);
        $input->method('getOption')->willReturn(false);

        $this->expectExceptionObject(new \InvalidArgumentException(
            'This command accepts only an instance of "ConsoleOutputInterface".'
        ));

        (new EntityGenerator(new MockClock()))->addScaffoldConfig(
            $this->getConfig(),
            $input,
            static::createStub(OutputInterface::class),
        );
    }

    /**
     * @param array<int, string> $expected
     */
    #[DataProvider('generateProvider')]
    public function testGenerate(PluginScaffoldConfiguration $config, array $expected): void
    {
        $stubs = new StubCollection();

        (new EntityGenerator(new MockClock(new \DateTimeImmutable('1988-01-01 00:00:00'))))
            ->generateStubs($config, $stubs);

        static::assertCount(\count($expected), $stubs);

        foreach ($expected as $stub) {
            static::assertTrue($stubs->has($stub));
        }
    }

    public function testGeneratesAttributeStyleEntity(): void
    {
        $stubs = new StubCollection();

        (new EntityGenerator(new MockClock(new \DateTimeImmutable('1988-01-01 00:00:00'))))
            ->generateStubs(
                self::getConfig([EntityGenerator::OPTION_NAME => ['Test']]),
                $stubs,
            );

        static::assertFalse($stubs->has('src/Core/Content/Test/TestDefinition.php'));

        $expectedEntity = <<<'PHP'
<?php declare(strict_types=1);

namespace MyNamespace\Core\Content\Test;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;

#[Entity('test', collectionClass: TestCollection::class)]
class TestEntity extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID, api: ['admin-api' => true, 'store-api' => false])]
    public string $id;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false])]
    public ?string $name = null;

    #[Field(type: FieldType::STRING, api: ['admin-api' => true, 'store-api' => false])]
    public ?string $description = null;

    #[Field(type: FieldType::BOOL, api: ['admin-api' => true, 'store-api' => false])]
    public ?bool $active = null;
}

PHP;

        $expectedServices = <<<'PHP'

    $services->set(\MyNamespace\Core\Content\Test\TestEntity::class)
        ->tag('shopware.entity');

PHP;

        static::assertSame($expectedEntity, $stubs->get('src/Core/Content/Test/TestEntity.php')?->getContent());
        static::assertSame($expectedServices, $stubs->get('src/Resources/config/services.php')?->getContent());
    }

    public function testDoesNotGenerateMigrationWhenEntityMigrationAlreadyExists(): void
    {
        $filesystem = new Filesystem();
        $directory = sys_get_temp_dir() . '/shopware-entity-generator-' . uniqid('', true);
        $filesystem->dumpFile(
            $directory . '/src/Migration/Migration123456789CreateTestTable.php',
            '<?php'
        );

        try {
            $stubs = new StubCollection();
            $timestamp = (new \DateTimeImmutable('1988-01-01 00:00:00'))->getTimestamp();

            (new EntityGenerator(new MockClock(new \DateTimeImmutable('1988-01-01 00:00:00'))))
                ->generateStubs(
                    new PluginScaffoldConfiguration(
                        'TestPlugin',
                        'MyNamespace',
                        $directory,
                        [EntityGenerator::OPTION_NAME => ['Test']],
                    ),
                    $stubs,
                );

            static::assertCount(3, $stubs);
            static::assertTrue($stubs->has('src/Core/Content/Test/TestEntity.php'));
            static::assertFalse($stubs->has('src/Migration/Migration' . $timestamp . 'CreateTestTable.php'));
        } finally {
            $filesystem->remove($directory);
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
                'src/Resources/config/services.php',
                'src/Migration/Migration' . $timeStamp . 'CreateTestTable.php',
                'src/Core/Content/Test/TestEntity.php',
                'src/Core/Content/Test/TestCollection.php',
            ],
        ];

        yield 'Option with entity, multiple stubs' => [
            'config' => self::getConfig([EntityGenerator::OPTION_NAME => ['Test1', 'Test2']]),
            'expected' => [
                'src/Resources/config/services.php',
                'src/Migration/Migration' . $timeStamp . 'CreateTest1Table.php',
                'src/Migration/Migration' . $timeStamp . 'CreateTest2Table.php',
                'src/Core/Content/Test1/Test1Entity.php',
                'src/Core/Content/Test1/Test1Collection.php',
                'src/Core/Content/Test2/Test2Entity.php',
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
