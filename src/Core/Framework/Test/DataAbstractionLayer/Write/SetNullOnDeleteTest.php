<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity\SetNullOnDeleteChildDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity\SetNullOnDeleteManyToOneDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Write\Entity\SetNullOnDeleteParentDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SetNullOnDeleteTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityWriter
     */
    private $writer;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        $this->writer = $this->getContainer()->get(EntityWriter::class);

        $registry = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        $registry->register(new SetNullOnDeleteParentDefinition());
        $registry->register(new SetNullOnDeleteManyToOneDefinition());
        $registry->register(new SetNullOnDeleteChildDefinition());

        $this->repository = new EntityRepository(
            $this->getContainer()->get(SetNullOnDeleteParentDefinition::class),
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get('event_dispatcher')
        );

        $this->getContainer()->get(Connection::class)->executeUpdate(
            'DROP TABLE IF EXISTS set_null_on_delete_child;
             DROP TABLE IF EXISTS set_null_on_delete_parent;
             DROP TABLE IF EXISTS set_null_on_delete_many_to_one;'
        );

        $this->getContainer()->get(Connection::class)->executeUpdate(
            'CREATE TABLE `set_null_on_delete_parent` (
               `id` binary(16) NOT NULL,
               `set_null_on_delete_many_to_one_id` binary(16) NULL,
               `name` varchar(255) NOT NULL,
               `version_id` binary(16) NOT NULL,
               `created_at` DATETIME(3) NOT NULL,
               `updated_at` DATETIME(3) NULL,
               PRIMARY KEY `primary` (`id`, `version_id`)
             );'
        );

        $this->getContainer()->get(Connection::class)->executeUpdate(
            'CREATE TABLE `set_null_on_delete_child` (
               `id` binary(16) NOT NULL,
               `set_null_on_delete_parent_id` binary(16) NULL,
               `set_null_on_delete_parent_version_id` binary(16) NULL,
               `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
               `created_at` DATETIME(3) NOT NULL,
               `updated_at` DATETIME(3) NULL,
               KEY `set_null_on_delete_parent_id` (`set_null_on_delete_parent_id`,`set_null_on_delete_parent_version_id`),
               CONSTRAINT `set_null_on_delete_child_ibfk_1` FOREIGN KEY (`set_null_on_delete_parent_id`, `set_null_on_delete_parent_version_id`)
                   REFERENCES `set_null_on_delete_parent` (`id`, `version_id`) ON DELETE SET NULL
             ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
        );

        $this->getContainer()->get(Connection::class)->executeUpdate(
            'CREATE TABLE `set_null_on_delete_many_to_one` (
               `id` binary(16) NOT NULL,
               `name` varchar(255) NOT NULL,
               `created_at` DATETIME(3) NOT NULL,
               `updated_at` DATETIME(3) NULL,
               PRIMARY KEY `primary` (`id`)
             );'
        );

        $this->getContainer()->get(Connection::class)->executeUpdate(
            'ALTER TABLE `set_null_on_delete_parent`
             ADD FOREIGN KEY (`set_null_on_delete_many_to_one_id`) REFERENCES `set_null_on_delete_many_to_one` (`id`) ON DELETE SET NULL;'
        );
    }

    public function tearDown(): void
    {
        $this->getContainer()->get(Connection::class)->exec(
            'DROP TABLE IF EXISTS set_null_on_delete_child;
             DROP TABLE IF EXISTS set_null_on_delete_parent;
             DROP TABLE IF EXISTS set_null_on_delete_many_to_one;'
        );
    }

    public function testDeleteOneToManyIfParentHasVersionId(): void
    {
        $id = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->writer->insert(
            $this->getContainer()->get(SetNullOnDeleteParentDefinition::class),
            [
                [
                    'id' => $id,
                    'productNumber' => Uuid::randomHex(),
                    'name' => 'test',
                    'setNulls' => [
                        ['id' => $childId, 'name' => 'test child'],
                    ],
                ],
            ],
            WriteContext::createFromContext(Context::createDefaultContext())
        );

        $parents = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM set_null_on_delete_parent');
        static::assertCount(1, $parents);

        $children = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM set_null_on_delete_child');
        static::assertCount(1, $children);

        $result = $this->writer->delete(
            $this->getContainer()->get(SetNullOnDeleteParentDefinition::class),
            [
                ['id' => $id],
            ],
            WriteContext::createFromContext(Context::createDefaultContext())
        );

        $deleted = $result->getDeleted();
        static::assertCount(1, $deleted);
        static::assertArrayHasKey(SetNullOnDeleteParentDefinition::ENTITY_NAME, $deleted);

        static::assertCount(1, $deleted[SetNullOnDeleteParentDefinition::ENTITY_NAME]);
        static::assertEquals($id, $deleted[SetNullOnDeleteParentDefinition::ENTITY_NAME][0]->getPrimaryKey());

        $updated = $result->getUpdated();
        static::assertCount(1, $updated);
        static::assertArrayHasKey(SetNullOnDeleteChildDefinition::ENTITY_NAME, $updated);

        static::assertCount(1, $updated[SetNullOnDeleteChildDefinition::ENTITY_NAME]);
        /** @var EntityWriteResult $updateResult */
        $updateResult = $updated[SetNullOnDeleteChildDefinition::ENTITY_NAME][0];
        static::assertEquals($childId, $updateResult->getPrimaryKey());
        static::assertEquals([
            'id' => $childId,
            'setNullOnDeleteParentId' => null,
            'setNullOnDeleteParentVersionId' => null,
        ], $updateResult->getPayload());

        $parents = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM set_null_on_delete_parent');
        static::assertCount(0, $parents);

        $children = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM set_null_on_delete_child');
        static::assertCount(1, $children);
        static::assertNull($children[0]['set_null_on_delete_parent_id']);
        static::assertNull($children[0]['set_null_on_delete_parent_version_id']);
    }

    public function testDeleteManyToOne(): void
    {
        $id = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->writer->insert(
            $this->getContainer()->get(SetNullOnDeleteParentDefinition::class),
            [
                [
                    'id' => $id,
                    'productNumber' => Uuid::randomHex(),
                    'name' => 'test',
                    'manyToOne' => [
                        'id' => $childId,
                        'name' => 'test child',
                    ],
                ],
            ],
            WriteContext::createFromContext(Context::createDefaultContext())
        );

        $parents = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM set_null_on_delete_parent');
        static::assertCount(1, $parents);

        $manyToOne = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM set_null_on_delete_many_to_one');
        static::assertCount(1, $manyToOne);

        $result = $this->writer->delete(
            $this->getContainer()->get(SetNullOnDeleteManyToOneDefinition::class),
            [
                ['id' => $childId],
            ],
            WriteContext::createFromContext(Context::createDefaultContext())
        );

        $deleted = $result->getDeleted();
        static::assertCount(1, $deleted);
        static::assertArrayHasKey(SetNullOnDeleteManyToOneDefinition::ENTITY_NAME, $deleted);

        static::assertCount(1, $deleted[SetNullOnDeleteManyToOneDefinition::ENTITY_NAME]);
        static::assertEquals($childId, $deleted[SetNullOnDeleteManyToOneDefinition::ENTITY_NAME][0]->getPrimaryKey());

        $updated = $result->getUpdated();
        static::assertCount(1, $updated);
        static::assertArrayHasKey(SetNullOnDeleteParentDefinition::ENTITY_NAME, $updated);

        static::assertCount(1, $updated[SetNullOnDeleteParentDefinition::ENTITY_NAME]);
        /** @var EntityWriteResult $updateResult */
        $updateResult = $updated[SetNullOnDeleteParentDefinition::ENTITY_NAME][0];
        static::assertEquals($id, $updateResult->getPrimaryKey());
        static::assertEquals([
            'id' => $id,
            'versionId' => Defaults::LIVE_VERSION,
            'setNullOnDeleteManyToOneId' => null,
        ], $updateResult->getPayload());

        $parents = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM set_null_on_delete_parent');
        static::assertCount(1, $parents);
        static::assertNull($parents[0]['set_null_on_delete_many_to_one_id']);

        $manyToOne = $this->getContainer()->get(Connection::class)->fetchAll('SELECT * FROM set_null_on_delete_many_to_one');
        static::assertCount(0, $manyToOne);
    }

    public function testSetNullOnDeleteThrowsWrittenEvent(): void
    {
        $id = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->repository->create(
            [
                [
                    'id' => $id,
                    'productNumber' => Uuid::randomHex(),
                    'name' => 'test',
                    'setNulls' => [
                        ['id' => $childId, 'name' => 'test child'],
                    ],
                ],
            ],
            Context::createDefaultContext()
        );

        $eventWasThrown = false;

        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDispatcher->addListener(
            SetNullOnDeleteChildDefinition::ENTITY_NAME . '.written',
            static function (EntityWrittenEvent $event) use ($childId, &$eventWasThrown): void {
                static::assertCount(1, $event->getPayloads());
                static::assertEquals(
                    [
                        'id' => $childId,
                        'setNullOnDeleteParentId' => null,
                        'setNullOnDeleteParentVersionId' => null,
                    ],
                    $event->getPayloads()[0]
                );

                $eventWasThrown = true;
            }
        );

        $this->repository->delete([['id' => $id]], Context::createDefaultContext());

        static::assertTrue($eventWasThrown);
    }
}
