<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Command\CreateEntitiesCommand;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityGenerator;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreateEntitiesCommand::class)]
class CreateEntitiesCommandTest extends TestCase
{
    #[TestDox('The schema generation succeeds when no definitions are registered')]
    public function testSucceedsWithoutDefinitions(): void
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([]);

        $entityGenerator = $this->createMock(EntityGenerator::class);
        $entityGenerator->expects($this->never())->method('generate');

        $commandTester = new CommandTester(new CreateEntitiesCommand(
            $entityGenerator,
            $registry,
            __DIR__ . '/_fixtures/root'
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));
        static::assertStringContainsString('Created schema in', $commandTester->getDisplay());
    }

    #[TestDox('Definitions without generated classes are skipped without writing files')]
    public function testSkipsDefinitionsWithoutGeneratedClasses(): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('product_media');

        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getDefinitions')->willReturn([$definition]);

        $entityGenerator = $this->createMock(EntityGenerator::class);
        $entityGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($definition)
            ->willReturn([]);

        $commandTester = new CommandTester(new CreateEntitiesCommand(
            $entityGenerator,
            $registry,
            __DIR__ . '/_fixtures/root'
        ));

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));
        static::assertSame([], glob(__DIR__ . '/_fixtures/schema/*'));
    }
}
