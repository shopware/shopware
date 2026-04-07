<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\WasModifiedByUserFieldDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class WasModifiedByUserFieldTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use KernelTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $entityRepository;

    protected function setUp(): void
    {
        $definition = $this->registerDefinition(WasModifiedByUserFieldDefinition::class);
        $this->connection = static::getContainer()->get(Connection::class);
        $this->entityRepository = new EntityRepository(
            $definition,
            static::getContainer()->get(EntityReaderInterface::class),
            static::getContainer()->get(VersionManager::class),
            static::getContainer()->get(EntitySearcherInterface::class),
            static::getContainer()->get(EntityAggregatorInterface::class),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(EntityLoadedEventFactory::class)
        );

        $this->connection->executeStatement('DROP TABLE IF EXISTS `_test_was_modified_by_user`');
        $this->connection->executeStatement(
            'CREATE TABLE `_test_was_modified_by_user` (
                `id` VARBINARY(16) NOT NULL,
                `name` VARCHAR(500) NULL,
                `was_modified_by_user` TINYINT(1) NOT NULL DEFAULT 0,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`)
            )'
        );
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE IF EXISTS `_test_was_modified_by_user`');
    }

    public function testSystemScopeCreateSetsFalse(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->create([['id' => $id]], $context);
        });

        $entity = $this->entityRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertFalse($entity->get('wasModifiedByUser'));
    }

    public function testUserScopeCreateSetsTrue(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $context->scope(Context::USER_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->create([['id' => $id]], $context);
        });

        $entity = $this->entityRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertTrue($entity->get('wasModifiedByUser'));
    }

    public function testCrudApiScopeCreateSetsTrue(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $context->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->create([['id' => $id]], $context);
        });

        $entity = $this->entityRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertTrue($entity->get('wasModifiedByUser'));
    }

    public function testUserScopeUpdateSetsTrue(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        // create in system scope => false
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->create([['id' => $id]], $context);
        });

        $entity = $this->entityRepository->search(new Criteria([$id]), $context)->get($id);
        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertFalse($entity->get('wasModifiedByUser'));

        // update in user scope => true
        $context->scope(Context::USER_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->update([['id' => $id, 'name' => 'updated']], $context);
        });

        $entity = $this->entityRepository->search(new Criteria([$id]), $context)->get($id);
        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertTrue($entity->get('wasModifiedByUser'));
    }

    public function testSystemScopeUpdateDoesNotChangeValue(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        // create in system scope => false
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->create([['id' => $id]], $context);
        });

        $entity = $this->entityRepository->search(new Criteria([$id]), $context)->get($id);
        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertFalse($entity->get('wasModifiedByUser'));

        // update in system scope => still false
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->update([['id' => $id, 'name' => 'updated']], $context);
        });

        $entity = $this->entityRepository->search(new Criteria([$id]), $context)->get($id);
        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertFalse($entity->get('wasModifiedByUser'));
    }

    public function testSystemScopeUpdatePreservesWasModifiedByUserTrue(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        // create in user scope => true
        $context->scope(Context::USER_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->create([['id' => $id]], $context);
        });

        $entity = $this->entityRepository->search(new Criteria([$id]), $context)->get($id);
        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertTrue($entity->get('wasModifiedByUser'));

        // update in system scope => still true (preserved)
        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->update([['id' => $id, 'name' => 'updated']], $context);
        });

        $entity = $this->entityRepository->search(new Criteria([$id]), $context)->get($id);
        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertTrue($entity->get('wasModifiedByUser'));
    }

    public function testUserScopeCannotExplicitlyWriteField(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->expectException(WriteException::class);

        $context->scope(Context::USER_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->create([['id' => $id, 'wasModifiedByUser' => false]], $context);
        });
    }

    public function testSystemScopeCannotExplicitlyWriteField(): void
    {
        $id = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->expectException(WriteException::class);

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($id): void {
            $this->entityRepository->create([['id' => $id, 'wasModifiedByUser' => true]], $context);
        });
    }
}
