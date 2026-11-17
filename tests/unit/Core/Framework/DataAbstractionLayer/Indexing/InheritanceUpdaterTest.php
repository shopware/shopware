<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InheritanceUpdater::class)]
class InheritanceUpdaterTest extends TestCase
{
    public function testUpdateResolvesEveryInheritedToManyAssociationInASingleStatement(): void
    {
        $statements = $this->captureStatements(InheritanceUpdaterRootTestDefinition::class, [Uuid::randomHex()]);

        static::assertCount(1, $statements, 'All inherited to-many associations must be resolved by one statement.');

        $sql = $statements[0]['sql'];

        static::assertSame(1, substr_count($sql, 'UPDATE'));
        foreach (['`prices`', '`details`', '`labels`'] as $property) {
            static::assertStringContainsString($property . ' = IFNULL(', $sql, $property . ' must be assigned by the statement.');
        }
    }

    public function testUpdateHonoursThePerAssociationForeignKeyAndVersionScope(): void
    {
        $context = Context::createDefaultContext();
        $statements = $this->captureStatements(InheritanceUpdaterRootTestDefinition::class, [Uuid::randomHex()], $context);

        $sql = $statements[0]['sql'];

        static::assertStringContainsString('`inheritance_root_id`', $sql, 'An association without an explicit foreign key falls back to <entity>_id.');
        static::assertStringContainsString('`custom_root_id`', $sql, 'An association must keep the foreign key declared on its Inherited flag.');
        static::assertStringContainsString('`inheritance_root`.version_id = :version', $sql);
        static::assertSame(Uuid::fromHexToBytes($context->getVersionId()), $statements[0]['params']['version']);
    }

    public function testUpdateScopesEveryStatementToTheContextVersion(): void
    {
        $context = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());

        $statements = $this->captureStatements(InheritanceUpdaterMixedTestDefinition::class, [Uuid::randomHex()], $context);

        static::assertCount(3, $statements);
        foreach ($statements as $statement) {
            static::assertStringContainsString('`inheritance_mixed`.version_id = :version', $statement['sql']);
            static::assertSame(Uuid::fromHexToBytes($context->getVersionId()), $statement['params']['version']);
        }

        static::assertStringContainsString('`inheritance_mixed`.version_id = parent.version_id', $statements[1]['sql'], 'A variant must only inherit from its parent in the same version.');
    }

    public function testUpdateOmitsTheVersionScopeForEntitiesWithoutVersions(): void
    {
        $statements = $this->captureStatements(InheritanceUpdaterUnversionedTestDefinition::class, [Uuid::randomHex()]);

        static::assertCount(3, $statements);
        foreach ($statements as $statement) {
            static::assertStringNotContainsString('version_id', $statement['sql']);
            static::assertArrayNotHasKey('version', $statement['params']);
        }
    }

    public function testUpdatePassesEveryGivenIdOnceAsBinary(): void
    {
        $first = Uuid::randomHex();
        $second = Uuid::randomHex();

        $statements = $this->captureStatements(InheritanceUpdaterMixedTestDefinition::class, [$first, $second, $first, '']);

        static::assertCount(3, $statements);
        foreach ($statements as $statement) {
            static::assertSame(
                [Uuid::fromHexToBytes($first), Uuid::fromHexToBytes($second)],
                array_values($statement['params']['ids']),
            );
            static::assertSame(['ids' => ArrayParameterType::BINARY], $statement['types']);
        }
    }

    public function testUpdateNeedsThreeStatementsForAnEntityWithBothAssociationKinds(): void
    {
        $statements = $this->captureStatements(InheritanceUpdaterMixedTestDefinition::class, [Uuid::randomHex()]);

        static::assertCount(3, $statements);

        [$toMany, $parented, $root] = $statements;

        static::assertStringContainsString('`prices` = IFNULL(', $toMany['sql']);
        static::assertStringContainsString('`tax` = IFNULL(`inheritance_mixed`.`tax_id`, parent.`tax_id`)', $parented['sql']);
        static::assertStringContainsString('`tax` = `inheritance_mixed`.`tax_id`', $root['sql']);
        static::assertStringContainsString('parent_id IS NULL', $root['sql']);
    }

    public function testUpdateDoesNotTouchTheDatabaseWithoutIds(): void
    {
        static::assertSame([], $this->captureStatements(InheritanceUpdaterRootTestDefinition::class, []));
    }

    public function testUpdateResolvesEveryInheritedToOneAssociationInOneStatementPerParentage(): void
    {
        $statements = $this->captureStatements(InheritanceUpdaterManyToOneTestDefinition::class, [Uuid::randomHex()]);

        static::assertCount(2, $statements, 'Inherited to-one associations need one statement for parented rows and one for root rows.');

        [$parented, $root] = $statements;

        static::assertStringContainsString('`tax` = IFNULL(`inheritance_to_one`.`tax_id`, parent.`tax_id`)', $parented['sql']);
        static::assertStringContainsString('`unit` = IFNULL(`inheritance_to_one`.`unit_id`, parent.`unit_id`)', $parented['sql']);
        static::assertStringContainsString('parent_id IS NOT NULL', $parented['sql']);

        static::assertStringContainsString('`tax` = `inheritance_to_one`.`tax_id`', $root['sql']);
        static::assertStringContainsString('`unit` = `inheritance_to_one`.`unit_id`', $root['sql']);
        static::assertStringContainsString('parent_id IS NULL', $root['sql']);
    }

    /**
     * @param class-string<EntityDefinition> $definition
     * @param list<string> $ids
     *
     * @return list<array{sql: string, params: array<string, mixed>, types: array<int|string, mixed>}>
     */
    private function captureStatements(string $definition, array $ids, ?Context $context = null): array
    {
        $statements = [];

        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            function (string $sql, array $params = [], array $types = []) use (&$statements): int {
                $statements[] = ['sql' => $sql, 'params' => $params, 'types' => $types];

                return 1;
            }
        );

        $registry = new StaticDefinitionInstanceRegistry(
            [
                InheritanceUpdaterRootTestDefinition::class,
                InheritanceUpdaterManyToOneTestDefinition::class,
                InheritanceUpdaterMixedTestDefinition::class,
                InheritanceUpdaterUnversionedTestDefinition::class,
                InheritanceUpdaterReferenceTestDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        (new InheritanceUpdater($connection, $registry))
            ->update($registry->get($definition)->getEntityName(), $ids, $context ?? Context::createDefaultContext());

        return $statements;
    }
}

/**
 * @internal
 */
class InheritanceUpdaterRootTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'inheritance_root';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new VersionField(),
            (new OneToManyAssociationField('prices', InheritanceUpdaterReferenceTestDefinition::class, 'inheritance_root_id'))->addFlags(new Inherited()),
            (new OneToOneAssociationField('details', 'id', 'inheritance_root_id', InheritanceUpdaterReferenceTestDefinition::class, false))->addFlags(new Inherited()),
            (new OneToManyAssociationField('labels', InheritanceUpdaterReferenceTestDefinition::class, 'custom_root_id'))->addFlags(new Inherited('custom_root_id')),
        ]);
    }
}

/**
 * @internal
 */
class InheritanceUpdaterManyToOneTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'inheritance_to_one';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new VersionField(),
            new FkField('tax_id', 'taxId', InheritanceUpdaterReferenceTestDefinition::class),
            new FkField('unit_id', 'unitId', InheritanceUpdaterReferenceTestDefinition::class),
            (new ManyToOneAssociationField('tax', 'tax_id', InheritanceUpdaterReferenceTestDefinition::class))->addFlags(new Inherited()),
            (new ManyToOneAssociationField('unit', 'unit_id', InheritanceUpdaterReferenceTestDefinition::class))->addFlags(new Inherited()),
        ]);
    }
}

/**
 * @internal
 */
class InheritanceUpdaterMixedTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'inheritance_mixed';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new VersionField(),
            new FkField('tax_id', 'taxId', InheritanceUpdaterReferenceTestDefinition::class),
            (new OneToManyAssociationField('prices', InheritanceUpdaterReferenceTestDefinition::class, 'inheritance_mixed_id'))->addFlags(new Inherited()),
            (new ManyToOneAssociationField('tax', 'tax_id', InheritanceUpdaterReferenceTestDefinition::class))->addFlags(new Inherited()),
        ]);
    }
}

/**
 * @internal
 */
class InheritanceUpdaterUnversionedTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'inheritance_unversioned';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('tax_id', 'taxId', InheritanceUpdaterReferenceTestDefinition::class),
            (new OneToManyAssociationField('prices', InheritanceUpdaterReferenceTestDefinition::class, 'inheritance_unversioned_id'))->addFlags(new Inherited()),
            (new ManyToOneAssociationField('tax', 'tax_id', InheritanceUpdaterReferenceTestDefinition::class))->addFlags(new Inherited()),
        ]);
    }
}

/**
 * @internal
 */
class InheritanceUpdaterReferenceTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'inheritance_reference';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new VersionField(),
            new StringField('name', 'name'),
        ]);
    }
}
