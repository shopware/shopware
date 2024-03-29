<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Query\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\EntitySync\IterateEntitiesQueryBuilder;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\Services\UsageDataAllowListService;
use Shopware\Core\System\UsageData\UsageDataException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(IterateEntitiesQueryBuilder::class)]
class IterateEntitiesQueryBuilderTest extends TestCase
{
    private IterateEntitiesQueryBuilder $iteratorFactory;

    protected function setUp(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())
            ->method('createQueryBuilder');
        $connection->expects(static::any())
            ->method('getExpressionBuilder')
            ->willReturn(new ExpressionBuilder($connection));

        $entityDefinitions = [
            new IterableTestEntityDefinition(),
            new VersionAwareTestDefinition(),
            new TestMappingEntityDefinition(),
        ];

        new StaticDefinitionInstanceRegistry(
            $entityDefinitions,
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $this->iteratorFactory = new IterateEntitiesQueryBuilder(
            new EntityDefinitionService(
                $entityDefinitions,
                new UsageDataAllowListService(),
            ),
            $connection,
            12,
        );
    }

    public function testThrowsEntityDoesNotHaveCreatedAndUpdatedAtFields(): void
    {
        static::expectException(UsageDataException::class);
        static::expectExceptionMessage('Entity "test_mapping_entity" is not allowed to be used for usage data');
        $this->iteratorFactory->create(TestMappingEntityDefinition::ENTITY_NAME, Operation::CREATE, new \DateTimeImmutable(), new \DateTimeImmutable('2023-09-11'), null);
    }

    public function testCreateThrowsExceptionIfEntityDoesNotExist(): void
    {
        $lastApprovalDate = new \DateTimeImmutable('2023-09-11');

        static::expectException(UsageDataException::class);
        static::expectExceptionMessage('Entity "no_entity" is not allowed to be used for usage data');
        $this->iteratorFactory->create('no_entity', Operation::CREATE, new \DateTimeImmutable(), $lastApprovalDate, null);
    }

    public function testCreateReturnsQueryForFetchingAllEntities(): void
    {
        $lastApprovalDate = new \DateTimeImmutable('2023-09-11');
        $queryBuilder = $this->iteratorFactory->create(IterableTestEntityDefinition::ENTITY_NAME, Operation::CREATE, new \DateTimeImmutable(), $lastApprovalDate, null);

        static::assertEquals([
            [
                'table' => EntityDefinitionQueryHelper::escape(IterableTestEntityDefinition::ENTITY_NAME),
                'alias' => null,
            ],
        ], $queryBuilder->getQueryPart('from'));
        static::assertEquals(12, $queryBuilder->getMaxResults());
    }

    public function testCreateAddsLastRunConditionIfGiven(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::any())
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($connection));

        $expressionBuilder = new ExpressionBuilder($connection);
        $connection->method('getExpressionBuilder')->willReturn($expressionBuilder);

        $queryBuilderMock = $this->createMock(QueryBuilder::class);
        $connection->method('createQueryBuilder')->willReturn($queryBuilderMock);

        $lastApprovalDate = new \DateTimeImmutable('2023-09-11');
        $lastRun = new \DateTimeImmutable('2023-08-11');
        $queryBuilder = $this->iteratorFactory->create(IterableTestEntityDefinition::ENTITY_NAME, Operation::CREATE, new \DateTimeImmutable(), $lastApprovalDate, $lastRun);

        static::assertEquals([
            [
                'table' => EntityDefinitionQueryHelper::escape(IterableTestEntityDefinition::ENTITY_NAME),
                'alias' => null,
            ],
        ], $queryBuilder->getQueryPart('from'));
        static::assertEquals(12, $queryBuilder->getMaxResults());
        static::assertEquals(
            '(created_at > :lastRun) AND (created_at <= :lastApprovalDate) AND ((updated_at IS NULL) OR (updated_at <= :lastApprovalDate))',
            (string) $queryBuilder->getQueryPart('where')
        );
        static::assertEquals('2023-08-11 00:00:00.000', $queryBuilder->getParameter('lastRun'));
    }

    public function testCreateThrowsForUpdatesIfLastRunIsNotSet(): void
    {
        $lastApprovalDate = new \DateTimeImmutable('2023-09-11');

        static::expectException(UsageDataException::class);
        $this->iteratorFactory->create(IterableTestEntityDefinition::ENTITY_NAME, Operation::UPDATE, new \DateTimeImmutable(), $lastApprovalDate, null);
    }

    public function testCreateFetchesAllEntitiesUpdatedButNotCreatedSinceLastRun(): void
    {
        $lastApprovalDate = new \DateTimeImmutable('2023-09-11');
        $lastRun = new \DateTimeImmutable('2023-08-11');
        $queryBuilder = $this->iteratorFactory->create(IterableTestEntityDefinition::ENTITY_NAME, Operation::UPDATE, new \DateTimeImmutable(), $lastApprovalDate, $lastRun);

        static::assertEquals([
            [
                'table' => EntityDefinitionQueryHelper::escape(IterableTestEntityDefinition::ENTITY_NAME),
                'alias' => null,
            ],
        ], $queryBuilder->getQueryPart('from'));
        static::assertEquals(12, $queryBuilder->getMaxResults());
        static::assertEquals(
            '(created_at <= :lastRun) AND (updated_at > :lastRun) AND (updated_at <= :lastApprovalDate)',
            (string) $queryBuilder->getQueryPart('where')
        );
        static::assertEquals('2023-08-11 00:00:00.000', $queryBuilder->getParameter('lastRun'));
    }

    public function testCreateThrowsExceptionForDeletionsIfLastRunIsNotSet(): void
    {
        $lastApprovalDate = new \DateTimeImmutable('2023-09-11');

        static::expectException(UsageDataException::class);
        $this->iteratorFactory->create(IterableTestEntityDefinition::ENTITY_NAME, Operation::DELETE, new \DateTimeImmutable(), $lastApprovalDate, null);
    }

    public function testCreateReturnsIteratorForDeletions(): void
    {
        $lastApprovalDate = new \DateTimeImmutable('2023-09-11');
        $lastRun = new \DateTimeImmutable('2023-08-11');
        $queryBuilder = $this->iteratorFactory->create(
            IterableTestEntityDefinition::ENTITY_NAME,
            Operation::DELETE,
            new \DateTimeImmutable(),
            $lastApprovalDate,
            $lastRun,
        );

        static::assertEquals([
            [
                'table' => EntityDefinitionQueryHelper::escape('usage_data_entity_deletion'),
                'alias' => null,
            ],
        ], $queryBuilder->getQueryPart('from'));
        static::assertEquals(12, $queryBuilder->getMaxResults());
        static::assertEquals(
            '(`entity_name` = :entityName) AND (`deleted_at` <= :currentRunDate)',
            (string) $queryBuilder->getQueryPart('where'),
        );
        static::assertEquals(IterableTestEntityDefinition::ENTITY_NAME, $queryBuilder->getParameter('entityName'));
    }

    /**
     * It filters non-live version entities. And it will exclude the version fields and non storage aware
     * fields from the select clause.
     */
    public function testFiltersNonLiveVersionEntities(): void
    {
        $lastApprovalDate = new \DateTimeImmutable('2023-09-11');
        $queryBuilder = $this->iteratorFactory->create(
            VersionAwareTestDefinition::ENTITY_NAME,
            Operation::CREATE,
            new \DateTimeImmutable(),
            $lastApprovalDate,
            null,
        );

        static::assertEquals([
            sprintf(
                'LOWER(HEX(%s.%s)) as %s',
                EntityDefinitionQueryHelper::escape('category'),
                EntityDefinitionQueryHelper::escape('id'),
                EntityDefinitionQueryHelper::escape('id')
            ),
        ], $queryBuilder->getQueryPart('select'));

        static::assertEquals(
            CompositeExpression::and(
                EntityDefinitionQueryHelper::escape('version_id') . ' = :versionId',
                EntityDefinitionQueryHelper::escape('version_aware_test_version_id') . ' = :versionId',
            ),
            $queryBuilder->getQueryPart('where')
        );

        static::assertEquals(Uuid::fromHexToBytes(Defaults::LIVE_VERSION), $queryBuilder->getParameter('versionId'));
    }
}

/**
 * @internal
 */
class IterableTestEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'product';

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
class VersionAwareTestDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'category';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new VersionField())->addFlags(new PrimaryKey(), new Required()),
            (new ReferenceVersionField(self::class, 'version_aware_test_version_id'))->addFlags(new PrimaryKey(), new Required()),
            (new OneToManyAssociationField('association_field', self::class, 'id'))->addFlags(new PrimaryKey(), new Required()),
        ]);
    }
}

/**
 * @internal
 */
class TestMappingEntityDefinition extends MappingEntityDefinition
{
    public const ENTITY_NAME = 'test_mapping_entity';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection();
    }
}
