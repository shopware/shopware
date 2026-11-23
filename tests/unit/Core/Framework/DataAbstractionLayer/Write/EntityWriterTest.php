<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\CascadeDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\SetNullOnDeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteResultFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageLoaderInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\_fixtures\CoverChildDefinition;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write\_fixtures\CoverParentDefinition;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityWriter::class)]
class EntityWriterTest extends TestCase
{
    private const DELETED_PARENT = '0190a6b2c3d47e8f9a0b1c2d3e4f5a01';
    private const OTHER_PARENT = '0190a6b2c3d47e8f9a0b1c2d3e4f5a02';
    private const CHILD = '0190a6b2c3d47e8f9a0b1c2d3e4f5a03';

    /**
     * @param list<string> $coverOf parents whose cover is the cascade-deleted child
     * @param list<string> $expected commands as "<command class>:<entity>:<id>"
     */
    #[DataProvider('cascadeProvider')]
    #[TestDox('A cascade delete sets the cover to null only on parents that stay: $_dataName')]
    public function testCascadeDeleteSetsNullOnlyOnRowsThatStay(array $coverOf, array $expected): void
    {
        $executed = [];
        $gateway = $this->createMock(EntityWriteGatewayInterface::class);
        $gateway->method('getExistence')->willReturnCallback(
            static fn (EntityDefinition $definition, array $primaryKey): EntityExistence => new EntityExistence($definition->getEntityName(), $primaryKey, true, false, false, [])
        );
        $gateway->expects($this->once())->method('execute')->willReturnCallback(
            static function (array $commands) use (&$executed): void {
                $executed = $commands;
            }
        );

        $resolver = static::createStub(EntityForeignKeyResolver::class);
        $resolver->method('getAffectedDeleteRestrictions')->willReturn([]);
        $resolver->method('getAllReverseInherited')->willReturn([]);
        $resolver->method('getAffectedDeletes')->willReturnCallback(
            static fn (EntityDefinition $definition): array => $definition->getEntityName() === CoverParentDefinition::ENTITY_NAME
                ? [CoverChildDefinition::ENTITY_NAME => [self::CHILD]]
                : []
        );
        $resolver->method('getAffectedSetNulls')->willReturnCallback(
            static fn (EntityDefinition $definition): array => $definition->getEntityName() === CoverChildDefinition::ENTITY_NAME
                ? [CoverParentDefinition::ENTITY_NAME . '.cover_id' => $coverOf]
                : []
        );

        $extractor = static::createStub(WriteCommandExtractor::class);
        $extractor->method('normalize')->willReturnArgument(1);

        $registry = new StaticDefinitionInstanceRegistry(
            [CoverParentDefinition::class, CoverChildDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $writer = new EntityWriter(
            $extractor,
            $resolver,
            $gateway,
            static::createStub(LanguageLoaderInterface::class),
            $registry,
            static::createStub(EntityWriteResultFactory::class),
        );

        $writer->delete(
            $registry->getByEntityName(CoverParentDefinition::ENTITY_NAME),
            [['id' => self::DELETED_PARENT]],
            WriteContext::createFromContext(Context::createDefaultContext()),
        );

        $actual = array_map(
            static fn (WriteCommand $command): string => $command::class . ':' . $command->getEntityName() . ':' . Uuid::fromBytesToHex($command->getPrimaryKey()['id']),
            $executed,
        );
        sort($actual);
        sort($expected);

        static::assertSame($expected, $actual);
    }

    public static function cascadeProvider(): \Generator
    {
        $delete = DeleteCommand::class . ':cover_parent:' . self::DELETED_PARENT;
        $cascade = CascadeDeleteCommand::class . ':cover_child:' . self::CHILD;
        $setNullOther = SetNullOnDeleteCommand::class . ':cover_parent:' . self::OTHER_PARENT;

        yield 'cover of the deleted parent' => [
            [self::DELETED_PARENT],
            [$delete, $cascade],
        ];

        yield 'cover of another parent' => [
            [self::OTHER_PARENT],
            [$delete, $cascade, $setNullOther],
        ];

        yield 'cover of both' => [
            [self::DELETED_PARENT, self::OTHER_PARENT],
            [$delete, $cascade, $setNullOther],
        ];
    }
}
