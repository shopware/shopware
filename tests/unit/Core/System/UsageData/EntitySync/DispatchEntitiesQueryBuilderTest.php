<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntitiesQueryBuilder;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(DispatchEntitiesQueryBuilder::class)]
class DispatchEntitiesQueryBuilderTest extends TestCase
{
    private DispatchEntitiesQueryBuilder $queryHelper;

    private MockObject&Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn(new MySQL80Platform());

        $this->connection->expects(static::never())
            ->method('createQueryBuilder');
        $this->connection->expects(static::any())
            ->method('getExpressionBuilder')
            ->willReturn(new ExpressionBuilder($this->connection));

        $this->queryHelper = new DispatchEntitiesQueryBuilder($this->connection);
    }

    public function testForEntityAddsTable(): void
    {
        static::assertSame($this->queryHelper, $this->queryHelper->forEntity('test_entity'));

        static::assertCount(1, $this->queryHelper->getQueryBuilder()->getQueryPart('from'));
        static::assertSame(
            EntityDefinitionQueryHelper::escape('test_entity'),
            $this->queryHelper->getQueryBuilder()->getQueryPart('from')[0]['table'],
        );
    }

    public function testWithFieldsAddsStorageAwareFields(): void
    {
        static::assertSame(
            $this->queryHelper,
            $this->queryHelper->withFields(new FieldCollection([
                new StringField('storage_aware', 'storageAware'),
            ]))
        );

        static::assertCount(1, $this->queryHelper->getQueryBuilder()->getQueryPart('select'));
        static::assertSame(
            EntityDefinitionQueryHelper::escape('storage_aware'),
            $this->queryHelper->getQueryBuilder()->getQueryPart('select')[0],
        );
    }

    public function testWithFieldsRemovesNonStorageAwareFields(): void
    {
        static::assertSame(
            $this->queryHelper,
            $this->queryHelper->withFields(new FieldCollection([
                new OneToOneAssociationField('OneToOne', 'one_to_one', 'reverse_one_to_one', 'reference_class'),
            ]))
        );

        static::assertEmpty($this->queryHelper->getQueryBuilder()->getQueryPart('select'));
    }

    public function testWithPrimaryKeyAddsNothingForEmptyArray(): void
    {
        static::assertSame($this->queryHelper, $this->queryHelper->withPrimaryKeys([]));

        static::assertEmpty($this->queryHelper->getQueryBuilder()->getQueryPart('where'));
    }

    public function testWithPrimaryKeysWithCombinedPrimaryKey(): void
    {
        $primaryKeys = [
            ['product_id' => '0189b18c26d87161aaa4a10465bfe164', 'category_id' => '018a27bbfb0771e2a1344024f48eb0fd'],
        ];

        static::assertSame($this->queryHelper, $this->queryHelper->withPrimaryKeys($primaryKeys));

        static::assertEquals(
            CompositeExpression::and( // wrapper
                CompositeExpression::or(
                    CompositeExpression::and(
                        '`product_id` = :pk_1',
                        '`category_id` = :pk_2',
                    ),
                ),
            ),
            $this->queryHelper->getQueryBuilder()->getQueryPart('where'),
        );

        $parameters = $this->queryHelper->getQueryBuilder()->getParameters();
        static::assertCount(2, $parameters);
        static::assertContains(Uuid::fromHexToBytes('0189b18c26d87161aaa4a10465bfe164'), $parameters);
        static::assertContains(Uuid::fromHexToBytes('018a27bbfb0771e2a1344024f48eb0fd'), $parameters);
    }

    public function testWithPrimaryKeysWithMultipleElements(): void
    {
        $primaryKeys = [
            ['id' => '0189b18c26d87161aaa4a10465bfe164'],
            ['id' => '018a27bbfb0771e2a1344024f6634aa5'],
        ];

        static::assertSame($this->queryHelper, $this->queryHelper->withPrimaryKeys($primaryKeys));

        static::assertEquals(
            CompositeExpression::and( // wrapper
                CompositeExpression::or(
                    CompositeExpression::and(
                        '`id` = :pk_1',
                    ),
                    CompositeExpression::and(
                        '`id` = :pk_2',
                    ),
                ),
            ),
            $this->queryHelper->getQueryBuilder()->getQueryPart('where'),
        );

        $parameters = $this->queryHelper->getQueryBuilder()->getParameters();
        static::assertCount(2, $parameters);
        static::assertContains(Uuid::fromHexToBytes('0189b18c26d87161aaa4a10465bfe164'), $parameters);
        static::assertContains(Uuid::fromHexToBytes('018a27bbfb0771e2a1344024f6634aa5'), $parameters);
    }

    public function testWithUniquePersonalIdentifier(): void
    {
        static::assertSame($this->queryHelper, $this->queryHelper->withPersonalUniqueIdentifier());

        static::assertCount(1, $this->queryHelper->getQueryBuilder()->getQueryPart('select'));
        static::assertSame(
            'SHA2(CONCAT(LOWER(`first_name`), LOWER(`last_name`), LOWER(`email`)), 512) AS `puid`',
            $this->queryHelper->getQueryBuilder()->getQueryPart('select')[0],
        );
    }

    public function testExecute(): void
    {
        $this->connection->expects(static::once())
            ->method('executeQuery')
            ->willReturn($this->createStub(Result::class));

        $this->queryHelper->execute();
    }

    public function testFetchesOnlyLiveVersion(): void
    {
        $definition = new TestEntityDefinition();
        new StaticDefinitionInstanceRegistry(
            [$definition],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGateway::class),
        );

        static::assertSame($this->queryHelper, $this->queryHelper->checkLiveVersion($definition));

        static::assertEquals(
            CompositeExpression::and(
                sprintf('%s = :versionId', EntityDefinitionQueryHelper::escape('version_id')),
                sprintf('%s = :versionId', EntityDefinitionQueryHelper::escape('test_version_id')),
            ),
            $this->queryHelper->getQueryBuilder()->getQueryPart('where'),
        );

        $parameters = $this->queryHelper->getQueryBuilder()->getParameters();
        static::assertCount(1, $parameters);
        static::assertContains(Uuid::fromHexToBytes(Defaults::LIVE_VERSION), $parameters);
    }

    public function testWithRunDateConstraintCreatedOperation(): void
    {
        $runDate = new \DateTimeImmutable();
        $message = new DispatchEntityMessage('product', Operation::CREATE, $runDate, []);

        static::assertSame(
            $this->queryHelper,
            $this->queryHelper->withLastApprovalDateConstraint($message, $runDate),
        );

        static::assertEquals(
            CompositeExpression::and(
                CompositeExpression::or(
                    '`updated_at` IS NULL',
                    '`updated_at` <= :lastApprovalDate',
                ),
            ),
            $this->queryHelper->getQueryBuilder()->getQueryPart('where'),
        );

        $parameters = $this->queryHelper->getQueryBuilder()->getParameters();
        static::assertCount(1, $parameters);
        static::assertArrayHasKey('lastApprovalDate', $parameters);
        static::assertEquals($runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT), $parameters['lastApprovalDate']);
    }

    public function testWithRunDateConstraintUpdatedOperation(): void
    {
        $runDate = new \DateTimeImmutable();

        $message = new DispatchEntityMessage('product', Operation::UPDATE, $runDate, []);

        static::assertSame(
            $this->queryHelper,
            $this->queryHelper->withLastApprovalDateConstraint($message, $runDate),
        );

        static::assertEquals(
            CompositeExpression::and(
                '`updated_at` <= :lastApprovalDate',
            ),
            $this->queryHelper->getQueryBuilder()->getQueryPart('where'),
        );

        $parameters = $this->queryHelper->getQueryBuilder()->getParameters();
        static::assertCount(1, $parameters);
        static::assertArrayHasKey('lastApprovalDate', $parameters);
        static::assertEquals($runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT), $parameters['lastApprovalDate']);
    }
}

/**
 * @internal
 */
class TestEntityDefinition extends EntityDefinition
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
            new VersionField(),
            new ReferenceVersionField(self::class, 'test_version_id'),
        ]);
    }
}
