<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
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

    public function testUpdateOmitsTheVersionScopeForEntitiesWithoutVersions(): void
    {
        $statements = $this->captureStatements(InheritanceUpdaterUnversionedTestDefinition::class, [Uuid::randomHex()]);

        static::assertCount(1, $statements);
        static::assertStringNotContainsString(':version', $statements[0]['sql']);
        static::assertArrayNotHasKey('version', $statements[0]['params']);
    }

    public function testUpdatePassesEveryGivenIdOnceAsBinary(): void
    {
        $first = Uuid::randomHex();
        $second = Uuid::randomHex();

        $statements = $this->captureStatements(InheritanceUpdaterRootTestDefinition::class, [$first, $second, $first, '']);

        static::assertSame(
            [Uuid::fromHexToBytes($first), Uuid::fromHexToBytes($second)],
            array_values($statements[0]['params']['ids']),
        );
    }

    public function testUpdateDoesNotTouchTheDatabaseWithoutIds(): void
    {
        static::assertSame([], $this->captureStatements(InheritanceUpdaterRootTestDefinition::class, []));
    }

    /**
     * @param class-string<EntityDefinition> $definition
     * @param list<string> $ids
     *
     * @return list<array{sql: string, params: array<string, mixed>}>
     */
    private function captureStatements(string $definition, array $ids, ?Context $context = null): array
    {
        $statements = [];

        $connection = static::createStub(Connection::class);
        $connection->method('executeStatement')->willReturnCallback(
            function (string $sql, array $params = []) use (&$statements): int {
                $statements[] = ['sql' => $sql, 'params' => $params];

                return 1;
            }
        );

        $registry = new StaticDefinitionInstanceRegistry(
            [
                InheritanceUpdaterRootTestDefinition::class,
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
            (new OneToManyAssociationField('prices', InheritanceUpdaterReferenceTestDefinition::class, 'inheritance_unversioned_id'))->addFlags(new Inherited()),
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
