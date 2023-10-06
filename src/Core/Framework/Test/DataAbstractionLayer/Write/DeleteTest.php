<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity\DeleteCascadeChildDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity\DeleteCascadeManyToOneDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity\DeleteCascadeParentDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class DeleteTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityWriter $writer;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->writer = $this->getContainer()->get(EntityWriter::class);
        $this->connection = $this->getContainer()->get(Connection::class);

        $registry = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        $registry->register(new DeleteCascadeParentDefinition());
        $registry->register(new DeleteCascadeManyToOneDefinition());
        $registry->register(new DeleteCascadeChildDefinition());

        $this->connection->rollBack();

        $this->connection->executeStatement(
            'DROP TABLE IF EXISTS delete_cascade_child;
             DROP TABLE IF EXISTS delete_cascade_parent;
             DROP TABLE IF EXISTS delete_cascade_many_to_one;

             CREATE TABLE `delete_cascade_parent` (
               `id` binary(16) NOT NULL,
               `delete_cascade_many_to_one_id` binary(16) NOT NULL,
               `name` varchar(255) NOT NULL,
               `version_id` binary(16) NOT NULL,
               `created_at` DATETIME(3) NOT NULL,
               `updated_at` DATETIME(3) NULL,
               PRIMARY KEY `primary` (`id`, `version_id`)
             );

             CREATE TABLE `delete_cascade_child` (
               `id` binary(16) NOT NULL,
               `delete_cascade_parent_id` binary(16) NOT NULL,
               `delete_cascade_parent_version_id` binary(16) NOT NULL,
               `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
               `created_at` DATETIME(3) NOT NULL,
               `updated_at` DATETIME(3) NULL,
               KEY `delete_cascade_parent_id` (`delete_cascade_parent_id`,`delete_cascade_parent_version_id`),
               CONSTRAINT `delete_cascade_child_ibfk_1` FOREIGN KEY (`delete_cascade_parent_id`, `delete_cascade_parent_version_id`)
                   REFERENCES `delete_cascade_parent` (`id`, `version_id`) ON DELETE CASCADE,
               PRIMARY KEY `primary` (`id`)
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

             CREATE TABLE `delete_cascade_many_to_one` (
               `id` binary(16) NOT NULL,
               `name` varchar(255) NOT NULL,
               `created_at` DATETIME(3) NOT NULL,
               `updated_at` DATETIME(3) NULL,
               PRIMARY KEY `primary` (`id`)
             );

             ALTER TABLE `delete_cascade_parent`
             ADD FOREIGN KEY (`delete_cascade_many_to_one_id`) REFERENCES `delete_cascade_many_to_one` (`id`) ON DELETE CASCADE;'
        );

        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();

        $this->connection->executeStatement(
            'DROP TABLE IF EXISTS delete_cascade_child;
             DROP TABLE IF EXISTS delete_cascade_parent;
             DROP TABLE IF EXISTS delete_cascade_many_to_one;'
        );

        $this->connection->beginTransaction();
    }

    public function testDeleteOneToManyIfParentHasVersionId(): void
    {
        $id = Uuid::randomHex();

        $this->writer->insert(
            $this->getContainer()->get(DeleteCascadeParentDefinition::class),
            [
                [
                    'id' => $id,
                    'productNumber' => Uuid::randomHex(),
                    'name' => 'test',
                    'manyToOne' => [
                        'id' => $id,
                        'name' => 'test child',
                    ],
                    'cascades' => [
                        ['id' => $id, 'name' => 'test child'],
                    ],
                ],
            ],
            WriteContext::createFromContext(Context::createDefaultContext())
        );

        $parents = $this->connection->fetchAllAssociative('SELECT * FROM delete_cascade_parent');
        static::assertCount(1, $parents);

        $children = $this->connection->fetchAllAssociative('SELECT * FROM delete_cascade_child');
        static::assertCount(1, $children);

        $this->writer->delete(
            $this->getContainer()->get(DeleteCascadeParentDefinition::class),
            [
                ['id' => $id],
            ],
            WriteContext::createFromContext(Context::createDefaultContext())
        );

        $parents = $this->connection->fetchAllAssociative('SELECT * FROM delete_cascade_parent');
        static::assertCount(0, $parents);

        $children = $this->connection->fetchAllAssociative('SELECT * FROM delete_cascade_child');
        static::assertCount(0, $children);
    }

    public function testDeleteOneToManyIfChildHasVersionId(): void
    {
        $id = Uuid::randomHex();

        $this->writer->insert(
            $this->getContainer()->get(DeleteCascadeParentDefinition::class),
            [
                [
                    'id' => $id,
                    'productNumber' => Uuid::randomHex(),
                    'name' => 'test',
                    'manyToOne' => [
                        'id' => $id,
                        'name' => 'test child',
                    ],
                    'cascades' => [
                        ['id' => $id, 'name' => 'test child'],
                    ],
                ],
            ],
            WriteContext::createFromContext(Context::createDefaultContext())
        );

        $parents = $this->connection->fetchAllAssociative('SELECT * FROM delete_cascade_parent');
        static::assertCount(1, $parents);

        $children = $this->connection->fetchAllAssociative('SELECT * FROM delete_cascade_child');
        static::assertCount(1, $children);

        $manyToOne = $this->connection->fetchAllAssociative('SELECT * FROM delete_cascade_many_to_one');
        static::assertCount(1, $manyToOne);

        $this->writer->delete(
            $this->getContainer()->get(DeleteCascadeManyToOneDefinition::class),
            [
                ['id' => $id],
            ],
            WriteContext::createFromContext(Context::createDefaultContext())
        );

        $parents = $this->connection->fetchAllAssociative('SELECT * FROM delete_cascade_parent');
        static::assertCount(0, $parents);

        $children = $this->connection->fetchAllAssociative('SELECT * FROM delete_cascade_child');
        static::assertCount(0, $children);

        $manyToOne = $this->connection->fetchAllAssociative('SELECT * FROM delete_cascade_many_to_one');
        static::assertCount(0, $manyToOne);
    }
}
