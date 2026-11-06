<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\CreateMigrationCommand;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationFileRenderer;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreateMigrationCommand::class)]
class CreateMigrationCommandTest extends TestCase
{
    /**
     * @param array<int, string> $entities
     * @param array<int, string> $expectedNamespaces
     * @param array<int, string> $expectedClassNames
     * @param array<int, string> $expectedPaths
     */
    #[DataProvider('commandProvider')]
    public function testExecute(
        array $entities,
        ?string $namespace,
        ?string $package,
        ?string $bundle,
        \DateTimeImmutable $now,
        array $expectedNamespaces,
        array $expectedClassNames,
        array $expectedPaths
    ): void {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);
        $queryGenerator = $this->createMock(MigrationQueryGenerator::class);
        $kernel = static::createStub(KernelInterface::class);
        $filesystem = $this->createMock(Filesystem::class);
        $migrationFileRenderer = $this->createMock(MigrationFileRenderer::class);

        $coreDir = '/path/to/core';
        $shopwareVersion = '6.5.0';

        $command = new CreateMigrationCommand($registry, $queryGenerator, $kernel, $filesystem, $migrationFileRenderer, $coreDir, $shopwareVersion, new MockClock($now));

        $commandTester = new CommandTester($command);

        $definition = static::createStub(EntityDefinition::class);
        $registry->expects($this->exactly(\count($entities)))->method('getByEntityName')->willReturn($definition);

        $queries = ['CREATE TABLE test_entity (id INT);'];

        $queryGenerator->expects($this->exactly(\count($entities)))->method('generateQueries')->willReturn($queries);

        if ($bundle !== null) {
            $kernel->method('getBundle')->willReturnMap([[$bundle, $this->getBundle()]]);
        }

        $fileRendererInvocation = $this->exactly(\count($entities));

        $migrationFileRenderer
            ->expects($fileRendererInvocation)
            ->method('render')
            ->willReturnCallback(static function (string $namespace, string $className) use ($expectedNamespaces, $expectedClassNames, $fileRendererInvocation) {
                static::assertSame($expectedNamespaces[$fileRendererInvocation->numberOfInvocations() - 1], $namespace);
                static::assertSame($expectedClassNames[$fileRendererInvocation->numberOfInvocations() - 1], $className);

                return 'Migration file content';
            });

        $filesystemInvocation = $this->exactly(\count($entities));

        $filesystem
            ->expects($filesystemInvocation)
            ->method('dumpFile')
            ->willReturnCallback(static function (string $path) use ($filesystemInvocation, $expectedPaths): void {
                static::assertSame($expectedPaths[$filesystemInvocation->numberOfInvocations() - 1], $path);
            });

        $input = [
            'entities' => implode(',', $entities),
        ];

        if ($package !== null) {
            $input['--package'] = $package;
        }

        if ($namespace !== null) {
            $input['--namespace'] = $namespace;
        }

        if ($bundle !== null) {
            $input['--bundle'] = $bundle;
        }

        $commandTester->execute($input);
    }

    public static function commandProvider(): \Generator
    {
        $now = new \DateTimeImmutable('2020-09-13 12:00:00');
        $timestamp = (string) $now->getTimestamp();

        yield 'without options' => [
            'entities' => ['test_entity'],
            'namespace' => null,
            'package' => 'core',
            'bundle' => null,
            'now' => $now,
            'expectedNamespaces' => [
                'Shopware\Core\Migration\V6_5',
            ],
            'expectedClassNames' => [
                'Migration' . $timestamp . 'TestEntity',
            ],
            'expectedPaths' => [
                '/path/to/core/Migration/V6_5/Migration' . $timestamp . 'TestEntity.php',
            ],
        ];

        yield 'with namespace' => [
            'entities' => ['test_entity'],
            'namespace' => 'V6_6',
            'package' => 'after-sales',
            'bundle' => null,
            'now' => $now,
            'expectedNamespaces' => [
                'Shopware\Core\Migration\V6_6',
            ],
            'expectedClassNames' => [
                'Migration' . $timestamp . 'TestEntity',
            ],
            'expectedPaths' => [
                '/path/to/core/Migration/V6_6/Migration' . $timestamp . 'TestEntity.php',
            ],
        ];

        yield 'with bundle' => [
            'entities' => ['test_entity'],
            'namespace' => null,
            'package' => null,
            'bundle' => 'TestPlugin',
            'now' => $now,
            'expectedNamespaces' => [
                'TestPlugin\Migration',
            ],
            'expectedClassNames' => [
                'Migration' . $timestamp . 'TestEntity',
            ],
            'expectedPaths' => [
                '/path/to/core/TestPlugin/Migration/Migration' . $timestamp . 'TestEntity.php',
            ],
        ];

        yield 'with namespace and bundle' => [
            'entities' => ['test_entity'],
            'namespace' => 'V6_6',
            'package' => null,
            'bundle' => 'TestPlugin',
            'now' => $now,
            'expectedNamespaces' => [
                'TestPlugin\Migration',
            ],
            'expectedClassNames' => [
                'Migration' . $timestamp . 'TestEntity',
            ],
            'expectedPaths' => [
                '/path/to/core/TestPlugin/Migration/Migration' . $timestamp . 'TestEntity.php',
            ],
        ];

        yield 'with multiple entities' => [
            'entities' => ['test_entity', 'another_entity'],
            'namespace' => null,
            'package' => null,
            'bundle' => null,
            'now' => $now,
            'expectedNamespaces' => [
                'Shopware\Core\Migration\V6_5',
                'Shopware\Core\Migration\V6_5',
            ],
            'expectedClassNames' => [
                'Migration' . $timestamp . 'TestEntity',
                'Migration' . $timestamp . 'AnotherEntity',
            ],
            'expectedPaths' => [
                '/path/to/core/Migration/V6_5/Migration' . $timestamp . 'TestEntity.php',
                '/path/to/core/Migration/V6_5/Migration' . $timestamp . 'AnotherEntity.php',
            ],
        ];
    }

    /**
     * @param list<string> $options
     * @param list<string> $expectedClassNames
     */
    #[DataProvider('relatedDefinitionsProvider')]
    public function testRelatedDefinitions(string $entities, array $options, array $expectedClassNames): void
    {
        new StaticDefinitionInstanceRegistry(
            [
                $entity = new MigrationTestEntityDefinition(),
                $translation = new MigrationTestEntityTranslationDefinition(),
                new MigrationTestTagDefinition(),
                $mapping = new MigrationTestEntityTagDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturnMap([
            [MigrationTestEntityDefinition::ENTITY_NAME, $entity],
            [MigrationTestEntityTranslationDefinition::ENTITY_NAME, $translation],
            [MigrationTestEntityTagDefinition::ENTITY_NAME, $mapping],
        ]);

        $queryGenerator = static::createStub(MigrationQueryGenerator::class);
        $queryGenerator->method('generateQueries')->willReturn(['CREATE TABLE test (id INT);']);

        $classNames = [];
        $migrationFileRenderer = static::createStub(MigrationFileRenderer::class);
        $migrationFileRenderer->method('render')->willReturnCallback(
            static function (string $namespace, string $className) use (&$classNames): string {
                $classNames[] = $className;

                return 'Migration file content';
            }
        );

        $command = new CreateMigrationCommand(
            $registry,
            $queryGenerator,
            static::createStub(KernelInterface::class),
            static::createStub(Filesystem::class),
            $migrationFileRenderer,
            '/path/to/core',
            '6.7.0',
            new MockClock(new \DateTimeImmutable('@1000'))
        );

        $input = ['entities' => $entities];
        foreach ($options as $option) {
            $input[$option] = true;
        }

        $commandTester = new CommandTester($command);
        $commandTester->execute($input);

        $commandTester->assertCommandIsSuccessful();
        static::assertSame($expectedClassNames, $classNames);
    }

    public static function relatedDefinitionsProvider(): \Generator
    {
        yield 'without options' => [
            'migration_test_entity',
            [],
            ['Migration1000MigrationTestEntity'],
        ];

        yield 'with translations' => [
            'migration_test_entity',
            ['--auto-translation'],
            ['Migration1000MigrationTestEntity', 'Migration1001MigrationTestEntityTranslation'],
        ];

        yield 'with mappings' => [
            'migration_test_entity',
            ['--auto-mapping'],
            ['Migration1000MigrationTestEntity', 'Migration1001MigrationTestEntityTag'],
        ];

        yield 'with translations and mappings' => [
            'migration_test_entity',
            ['--auto-translation', '--auto-mapping'],
            [
                'Migration1000MigrationTestEntity',
                'Migration1001MigrationTestEntityTranslation',
                'Migration1002MigrationTestEntityTag',
            ],
        ];

        yield 'related definitions that are passed explicitly are not created twice' => [
            'migration_test_entity,migration_test_entity_translation',
            ['--auto-translation', '--auto-mapping'],
            [
                'Migration1000MigrationTestEntity',
                'Migration1000MigrationTestEntityTranslation',
                'Migration1001MigrationTestEntityTag',
            ],
        ];
    }

    #[DataProvider('pluginProvider')]
    public function testPluginOption(string $plugin, string $expectedNamespace, string $expectedDirectory): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getByEntityName')->willReturn(static::createStub(EntityDefinition::class));

        $queryGenerator = static::createStub(MigrationQueryGenerator::class);
        $queryGenerator->method('generateQueries')->willReturn(['CREATE TABLE test (id INT);']);

        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn($this->getPlugins());

        $namespaces = [];
        $migrationFileRenderer = static::createStub(MigrationFileRenderer::class);
        $migrationFileRenderer->method('render')->willReturnCallback(
            static function (string $namespace) use (&$namespaces): string {
                $namespaces[] = $namespace;

                return 'Migration file content';
            }
        );

        $paths = [];
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('exists')->willReturn(true);
        $filesystem->expects($this->never())->method('mkdir');
        $filesystem->method('dumpFile')->willReturnCallback(static function (string $path) use (&$paths): void {
            $paths[] = $path;
        });

        $command = new CreateMigrationCommand(
            $registry,
            $queryGenerator,
            $kernel,
            $filesystem,
            $migrationFileRenderer,
            '/path/to/core',
            '6.7.0',
            new MockClock(new \DateTimeImmutable('@1000'))
        );

        $commandTester = new CommandTester($command);
        $commandTester->execute(['entities' => 'test_entity', '--plugin' => $plugin]);

        $commandTester->assertCommandIsSuccessful();
        static::assertSame([$expectedNamespace], $namespaces);
        static::assertSame([$expectedDirectory . '/Migration1000TestEntity.php'], $paths);
    }

    public static function pluginProvider(): \Generator
    {
        yield 'exact name that is also a prefix of another plugin' => ['SwagMigrationTest', 'SwagMigrationTest\Migration', '/plugins/SwagMigrationTest/src/Migration'];
        yield 'unique prefix' => ['SwagMigrationTestExt', 'SwagMigrationTestExtension\Migration', '/plugins/SwagMigrationTestExtension/src/Migration'];
    }

    public function testPluginOptionWithAmbiguousPrefix(): void
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn($this->getPlugins());

        $command = new CreateMigrationCommand(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(MigrationQueryGenerator::class),
            $kernel,
            static::createStub(Filesystem::class),
            static::createStub(MigrationFileRenderer::class),
            '/path/to/core',
            '6.7.0',
            new MockClock()
        );

        $this->expectExceptionObject(
            DataAbstractionLayerException::migrationMoreThanOnePluginFound('SwagMigration', ['SwagMigrationTest', 'SwagMigrationTestExtension'])
        );

        (new CommandTester($command))->execute(['entities' => 'test_entity', '--plugin' => 'SwagMigration']);
    }

    public function testPluginOptionWithUnknownPlugin(): void
    {
        $kernel = static::createStub(KernelInterface::class);
        $kernel->method('getBundles')->willReturn($this->getPlugins());

        $command = new CreateMigrationCommand(
            static::createStub(DefinitionInstanceRegistry::class),
            static::createStub(MigrationQueryGenerator::class),
            $kernel,
            static::createStub(Filesystem::class),
            static::createStub(MigrationFileRenderer::class),
            '/path/to/core',
            '6.7.0',
            new MockClock()
        );

        $this->expectExceptionObject(DataAbstractionLayerException::migrationPluginNotFound('Unknown'));

        (new CommandTester($command))->execute(['entities' => 'test_entity', '--plugin' => 'Unknown']);
    }

    /**
     * @return array<string, Bundle>
     */
    private function getPlugins(): array
    {
        return [
            'SwagMigrationTest' => MigrationTestPlugin::create('SwagMigrationTest'),
            'SwagMigrationTestExtension' => MigrationTestPlugin::create('SwagMigrationTestExtension'),
            // Bundles that are not plugins are ignored, even if their name matches
            'SwagMigrationTestBundle' => new MigrationTestBundle(),
        ];
    }

    private function getBundle(): Bundle
    {
        $bundle = static::createStub(Bundle::class);
        $bundle->method('getMigrationNamespace')->willReturn('TestPlugin\Migration');
        $bundle->method('getMigrationPath')->willReturn('/path/to/core/TestPlugin/Migration');

        return $bundle;
    }
}

/**
 * @internal
 */
class MigrationTestPlugin extends Plugin
{
    public static function create(string $name): self
    {
        $plugin = new self(true, '/plugins/' . $name);
        $plugin->name = $name;
        $plugin->path = '/plugins/' . $name . '/src';

        return $plugin;
    }

    public function getMigrationNamespace(): string
    {
        return $this->name . '\Migration';
    }

    public function getMigrationPath(): string
    {
        return $this->path . '/Migration';
    }
}

/**
 * @internal
 */
class MigrationTestBundle extends Bundle
{
    public function __construct()
    {
        $this->name = 'SwagMigrationTestBundle';
    }
}

/**
 * @internal
 */
class MigrationTestEntityDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'migration_test_entity';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            (new TranslationsAssociationField(MigrationTestEntityTranslationDefinition::class, 'migration_test_entity_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('tags', MigrationTestTagDefinition::class, MigrationTestEntityTagDefinition::class, 'migration_test_entity_id', 'migration_test_tag_id'),
        ]);
    }
}

/**
 * @internal
 */
class MigrationTestEntityTranslationDefinition extends EntityTranslationDefinition
{
    final public const ENTITY_NAME = 'migration_test_entity_translation';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function getParentDefinitionClass(): string
    {
        return MigrationTestEntityDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new StringField('name', 'name'))->addFlags(new Required()),
        ]);
    }
}

/**
 * @internal
 */
class MigrationTestTagDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'migration_test_tag';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
        ]);
    }
}

/**
 * @internal
 */
class MigrationTestEntityTagDefinition extends MappingEntityDefinition
{
    final public const ENTITY_NAME = 'migration_test_entity_tag';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('migration_test_entity_id', 'migrationTestEntityId', MigrationTestEntityDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('migration_test_tag_id', 'migrationTestTagId', MigrationTestTagDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            new ManyToOneAssociationField('migrationTestEntity', 'migration_test_entity_id', MigrationTestEntityDefinition::class, 'id', false),
            new ManyToOneAssociationField('migrationTestTag', 'migration_test_tag_id', MigrationTestTagDefinition::class, 'id', false),
        ]);
    }
}
