<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Fixtures\CascadeDeleteChild;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Fixtures\GrandParentDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Fixtures\ParentDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Fixtures\RestrictDeleteChild;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal\Fixtures\SetNullChild;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class EntityForeignKeyResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var string
     */
    private $parentId;

    /**
     * @var string
     */
    private $grandParentId;

    /**
     * @var EntityForeignKeyResolver
     */
    private $entityForeignKeyResolver;

    /**
     * @var GrandParentDefinition
     */
    private $grandParentDefinition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->deleteTestTables();

        $this->registerDefinition(
            GrandParentDefinition::class,
            ParentDefinition::class,
            RestrictDeleteChild::class,
            CascadeDeleteChild::class,
            SetNullChild::class
        );

        $this->connection->executeUpdate('CREATE TABLE `EntityForeignKeyResolverTest_grand_parent` (
                `id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            )
        ');
        $this->connection->executeUpdate('
            CREATE TABLE `EntityForeignKeyResolverTest_parent` (
                `id` BINARY(16) NOT NULL,
                `grand_parent_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                FOREIGN KEY (`grand_parent_id`)
                    REFERENCES `EntityForeignKeyResolverTest_grand_parent` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');
        $this->connection->executeUpdate('CREATE TABLE `EntityForeignKeyResolverTest_child_cascadeDelete` (
                `id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                FOREIGN KEY (`parent_id`)
                    REFERENCES `EntityForeignKeyResolverTest_parent` (`id`)
                    ON DELETE CASCADE ON UPDATE CASCADE
            )
        ');
        $this->connection->executeUpdate('
            CREATE TABLE `EntityForeignKeyResolverTest_child_restrictDelete` (
                `id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NOT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                FOREIGN KEY (`parent_id`)
                    REFERENCES `EntityForeignKeyResolverTest_parent` (`id`)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            )
        ');
        $this->connection->executeUpdate('
            CREATE TABLE `EntityForeignKeyResolverTest_child_setNull` (
                `id` BINARY(16) NOT NULL,
                `parent_id` BINARY(16) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                FOREIGN KEY (`parent_id`)
                    REFERENCES `EntityForeignKeyResolverTest_parent` (`id`)
                    ON DELETE RESTRICT ON UPDATE CASCADE
            )
        ');

        $this->parentId = Uuid::randomHex();
        $this->grandParentId = Uuid::randomHex();

        $this->connection->executeUpdate(
            'INSERT INTO `EntityForeignKeyResolverTest_grand_parent` (
                `id`,
                 `created_at`
            ) VALUES (
                UNHEX(:id),
                NOW(3)
            )',
            ['id' => $this->grandParentId]
        );
        $this->connection->executeUpdate(
            'INSERT INTO `EntityForeignKeyResolverTest_parent` (
                `id`,
                `grand_parent_id`,
                 `created_at`
            ) VALUES (
                UNHEX(:id),
                UNHEX(:grandParentId),
                NOW(3)
            )',
            [
                'id' => $this->parentId,
                'grandParentId' => $this->grandParentId,
            ]
        );


        $this->grandParentDefinition = $this->getContainer()->get(GrandParentDefinition::class);
        $this->entityForeignKeyResolver = $this->getContainer()->get(EntityForeignKeyResolver::class);
    }

    protected function tearDown(): void
    {
        $this->deleteTestTables();

        parent::tearDown();
    }

    private function deleteTestTables(): void
    {
        $this->connection->executeUpdate('DROP TABLE IF EXISTS `EntityForeignKeyResolverTest_child_cascadeDelete`');
        $this->connection->executeUpdate('DROP TABLE IF EXISTS `EntityForeignKeyResolverTest_child_restrictDelete`');
        $this->connection->executeUpdate('DROP TABLE IF EXISTS `EntityForeignKeyResolverTest_child_setNull`');
        $this->connection->executeUpdate('DROP TABLE IF EXISTS `EntityForeignKeyResolverTest_parent`');
        $this->connection->executeUpdate('DROP TABLE IF EXISTS `EntityForeignKeyResolverTest_grand_parent`');
    }

    public function testRespectsNestedRestrictDelete(): void
    {
        $childId = Uuid::randomHex();
        $this->connection->executeUpdate(
            'INSERT INTO `EntityForeignKeyResolverTest_child_restrictDelete` (
                `id`,
                `parent_id`,
                 `created_at`
            ) VALUES (
                UNHEX(:id),
                UNHEX(:parentId),
                NOW(3)
            )',
            [
                'parentId' => $this->parentId,
                'id' => $childId,
            ]
        );

        $deleteRestrictions = $this->entityForeignKeyResolver->getAffectedDeleteRestrictions(
            $this->grandParentDefinition,
            [
                ['id' => $this->grandParentId],
            ],
            Context::createDefaultContext()
        );

        $deleteRestrictionsForDeletedEntity = $this->getRestrictionsForEntity(
            $deleteRestrictions,
            $this->grandParentId
        );
        self::assertEquals(
            [$childId],
            $deleteRestrictionsForDeletedEntity['EntityForeignKeyResolverTest_child_restrictDelete']
        );
    }

    public function testRespectsNestedCascadeDelete(): void
    {
        $childId = Uuid::randomHex();
        $this->connection->executeUpdate(
            'INSERT INTO `EntityForeignKeyResolverTest_child_cascadeDelete` (
                `id`,
                `parent_id`,
                 `created_at`
            ) VALUES (
                UNHEX(:id),
                UNHEX(:parentId),
                NOW(3)
            )',
            [
                'parentId' => $this->parentId,
                'id' => $childId,
            ]
        );

        $deletes = $this->entityForeignKeyResolver->getAffectedDeletes(
            $this->grandParentDefinition,
            [
                ['id' => $this->grandParentId],
            ],
            Context::createDefaultContext()
        );

        $deletesForDeletedEntity = $this->getRestrictionsForEntity($deletes, $this->grandParentId);
        self::assertEquals([$childId], $deletesForDeletedEntity['EntityForeignKeyResolverTest_child_cascadeDelete']);
    }

    public function testRespectsNestedSetNullOnDelete(): void
    {
        $childId = Uuid::randomHex();
        $this->connection->executeUpdate(
            'INSERT INTO `EntityForeignKeyResolverTest_child_setNull` (
                `id`,
                `parent_id`,
                 `created_at`
            ) VALUES (
                UNHEX(:id),
                UNHEX(:parentId),
                NOW(3)
            )',
            [
                'parentId' => $this->parentId,
                'id' => $childId,
            ]
        );

        $setNulls = $this->entityForeignKeyResolver->getAffectedSetNulls(
            $this->grandParentDefinition,
            [
                ['id' => $this->grandParentId],
            ],
            Context::createDefaultContext()
        );

        $setNullsForDeletedEntity = $this->getRestrictionsForEntity($setNulls, $this->grandParentId);
        self::assertEquals([$childId], $setNullsForDeletedEntity['EntityForeignKeyResolverTest_child_setNull']);
    }

    private function getRestrictionsForEntity(array $restrictions, string $entityId)
    {
        foreach ($restrictions as $restriction) {
            if ($restriction['pk'] === $entityId) {
                return $restriction['restrictions'];
            }
        }

        return [];
    }
}
