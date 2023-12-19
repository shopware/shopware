<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\DataAbstractionLayer\Command\CreateMigrationCommand;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationFileRenderer;
use Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @internal
 */
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
        $kernel = $this->createMock(KernelInterface::class);
        $filesystem = $this->createMock(Filesystem::class);
        $migrationFileRenderer = $this->createMock(MigrationFileRenderer::class);

        $coreDir = '/path/to/core';
        $shopwareVersion = '6.5.0';

        $command = new CreateMigrationCommand($registry, $queryGenerator, $kernel, $filesystem, $migrationFileRenderer, $coreDir, $shopwareVersion, $now);

        $commandTester = new CommandTester($command);

        $definition = $this->createMock(EntityDefinition::class);
        $registry->expects(static::exactly(\count($entities)))->method('getByEntityName')->willReturn($definition);

        $queries = ['CREATE TABLE test_entity (id INT);'];

        $queryGenerator->expects(static::exactly(\count($entities)))->method('generateQueries')->willReturn($queries);

        if ($bundle !== null) {
            $kernel->method('getBundle')->with($bundle)->willReturn($this->getBundle());
        }

        $fileRendererInvocation = static::exactly(\count($entities));

        $migrationFileRenderer
            ->expects($fileRendererInvocation)
            ->method('render')
            ->willReturnCallback(function (string $namespace, string $className) use ($expectedNamespaces, $expectedClassNames, $fileRendererInvocation) {
                static::assertEquals($expectedNamespaces[$fileRendererInvocation->numberOfInvocations() - 1], $namespace);
                static::assertEquals($expectedClassNames[$fileRendererInvocation->numberOfInvocations() - 1], $className);

                return 'Migration file content';
            });

        $filesystemInvocation = static::exactly(\count($entities));

        $filesystem
            ->expects($filesystemInvocation)
            ->method('dumpFile')
            ->willReturnCallback(function (string $path) use ($filesystemInvocation, $expectedPaths): void {
                static::assertEquals($expectedPaths[$filesystemInvocation->numberOfInvocations() - 1], $path);
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
            'package' => 'system-settings',
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

    private function getBundle(): Bundle
    {
        $bundle = $this->createMock(Bundle::class);
        $bundle->method('getMigrationNamespace')->willReturn('TestPlugin\Migration');
        $bundle->method('getMigrationPath')->willReturn('/path/to/core/TestPlugin/Migration');

        return $bundle;
    }
}
