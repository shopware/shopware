<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\RootDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubCascadeDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class OneToOneAssociationFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var EntityRepositoryInterface
     */
    private $subRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->repository = new EntityRepository(
            $this->registerDefinition(RootDefinition::class),
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get('event_dispatcher')
        );

        $this->subRepository = new EntityRepository(
            $this->registerDefinition(SubDefinition::class),
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get('event_dispatcher')
        );

        $this->registerDefinition(SubCascadeDefinition::class);

        $this->connection->executeUpdate('
DROP TABLE IF EXISTS `root`;
DROP TABLE IF EXISTS `root_sub`;
DROP TABLE IF EXISTS `root_sub_cascade`;

CREATE TABLE `root` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY `primary` (`id`, `version_id`)
);
CREATE TABLE `root_sub` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `root_version_id` binary(16),
  `root_id` binary(16),
  `name` varchar(255) NULL,
  `stock` int NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY `primary` (`id`, `version_id`)
);
CREATE TABLE `root_sub_cascade` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `root_version_id` binary(16),
  `root_id` binary(16),
  `name` varchar(255) NULL,
  `stock` int NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY `primary` (`id`, `version_id`)
);


CREATE TABLE `root_sub_many` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `root_sub_version_id` binary(16) NOT NULL,
  `root_sub_id` binary(16) NOT NULL,
  `name` varchar(255) NULL,
  `created_at` DATETIME(3) NOT NULL,
  `updated_at` DATETIME(3) NULL,
  PRIMARY KEY `primary` (`id`, `version_id`)
);

ALTER TABLE `root_sub`
ADD FOREIGN KEY (`root_id`, `root_version_id`) REFERENCES `root` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE NO ACTION;

ALTER TABLE `root_sub_cascade`
ADD FOREIGN KEY (`root_id`, `root_version_id`) REFERENCES `root` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE NO ACTION;

ALTER TABLE `root_sub_many`
ADD FOREIGN KEY (`root_sub_id`, `root_sub_version_id`) REFERENCES `root_sub` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE NO ACTION;
        ');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->connection->executeUpdate('
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `root`;
DROP TABLE IF EXISTS `root_sub`;
DROP TABLE IF EXISTS `root_sub_cascade`;
DROP TABLE IF EXISTS `root_sub_many`;
SET FOREIGN_KEY_CHECKS = 1;
        ');
    }

    public function testWriteRootOverSub(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'sub 1',
            'root' => [
                'id' => $id2,
                'name' => 'root 1',
            ],
        ];

        $context = Context::createDefaultContext();

        $event = $this->subRepository->create([$data], $context);

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $event);

        $rootEvent = $event->getEventByEntityName(RootDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $rootEvent);
        static::assertCount(1, $rootEvent->getWriteResults());
        static::assertSame([$id2], $rootEvent->getIds());

        $subEvent = $event->getEventByEntityName(SubDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $subEvent);
        static::assertCount(1, $subEvent->getWriteResults());
        static::assertSame([$id], $subEvent->getIds());
    }

    public function testWriteSubOverRoot(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $data = [
            'id' => $id2,
            'name' => 'root 1',
            'sub' => [
                'id' => $id,
                'name' => 'sub 1',
            ],
        ];

        $context = Context::createDefaultContext();

        $event = $this->repository->create([$data], $context);

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $event);

        $rootEvent = $event->getEventByEntityName(RootDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $rootEvent);
        static::assertCount(1, $rootEvent->getWriteResults());
        static::assertSame([$id2], $rootEvent->getIds());

        $subEvent = $event->getEventByEntityName(SubDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $subEvent);
        static::assertCount(1, $subEvent->getWriteResults());
        static::assertSame([$id], $subEvent->getIds());
    }

    public function testRead(): void
    {
        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();
        $id4 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'root 1',
            'sub' => [
                'id' => $id2,
                'name' => 'sub 1',
                'manies' => [
                    ['id' => $id3, 'name' => 'many 1'],
                    ['id' => $id4, 'name' => 'many 2'],
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create([$data], $context);

        $entity = $this->repository->search(new Criteria([$id]), $context)->first();

        /** @var ArrayEntity $entity */
        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertSame($id, $entity->getId());

        $sub = $entity->get('sub');
        static::assertInstanceOf(ArrayEntity::class, $sub);
        static::assertSame('sub 1', $sub->get('name'));
        static::assertSame($id, $sub->get('rootId'));

        $criteria = new Criteria([$id2]);
        $criteria->addAssociation('root');
        $criteria->addAssociation('manies');

        $sub = $this->subRepository->search($criteria, $context)->first();
        static::assertInstanceOf(ArrayEntity::class, $sub->get('root'));

        /** @var EntityCollection|null $many */
        $many = $sub->get('manies');
        static::assertInstanceOf(EntityCollection::class, $many);
        static::assertCount(2, $many);

        static::assertTrue($many->has($id3));
        static::assertTrue($many->has($id4));
    }

    public function testSearch(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $data = [
            [
                'id' => $id1,
                'name' => 'root 1',
                'sub' => [
                    'id' => $id1,
                    'name' => 'sub 1',
                ],
            ],
            [
                'id' => $id2,
                'name' => 'root 2',
                'sub' => [
                    'id' => $id2,
                    'name' => 'sub 2',
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($data, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('root.sub.name', 'sub 2'));
        $result = $this->repository->search($criteria, $context);

        static::assertCount(1, $result);
        static::assertTrue($result->has($id2));
    }

    public function testAggregate(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $data = [
            [
                'id' => $id1,
                'name' => 'root 1',
                'sub' => [
                    'id' => $id1,
                    'name' => 'sub 1',
                    'stock' => 1,
                ],
            ],
            [
                'id' => $id2,
                'name' => 'root 2',
                'sub' => [
                    'id' => $id2,
                    'name' => 'sub 2',
                    'stock' => 10,
                ],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create($data, $context);

        $criteria = new Criteria();
        $criteria->addAggregation(new SumAggregation('stock_sum', 'root.sub.stock'));
        $result = $this->repository->search($criteria, $context);

        static::assertTrue($result->getAggregations()->has('stock_sum'));
        $sum = $result->getAggregations()->get('stock_sum');

        /** @var SumResult $sum */
        static::assertInstanceOf(SumResult::class, $sum);

        static::assertEquals(11, $sum->getSum());
    }

    public function testCreateVersioning(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'root 1',
            'sub' => [
                'id' => $id,
                'name' => 'sub 1',
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create([$data], $context);

        $versionId = $this->repository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->repository->update([
            [
                'id' => $id,
                'name' => 'updated root',
                'sub' => [
                    'id' => $id,
                    'name' => 'updated sub',
                ],
            ],
        ], $versionContext);

        /** @var ArrayEntity $root */
        $root = $this->repository->search(new Criteria([$id]), $context)->first();
        static::assertSame('root 1', $root->get('name'));

        $sub = $root->get('sub');
        static::assertInstanceOf(ArrayEntity::class, $sub);
        static::assertSame('sub 1', $sub->get('name'));

        $root = $this->repository->search(new Criteria([$id]), $versionContext)->first();
        static::assertSame('updated root', $root->get('name'));

        $sub = $root->get('sub');
        static::assertInstanceOf(ArrayEntity::class, $sub);
        static::assertSame('updated sub', $sub->get('name'));

        $this->repository->merge($versionId, $context);

        $root = $this->repository->search(new Criteria([$id]), $context)->first();
        static::assertSame('updated root', $root->get('name'));

        $sub = $root->get('sub');
        static::assertInstanceOf(ArrayEntity::class, $sub);
        static::assertSame('updated sub', $sub->get('name'));
    }

    public function testCascadeDelete(): void
    {
        $idRoot = Uuid::randomHex();
        $idSubCascade = Uuid::randomHex();

        $data = [
            'id' => $idRoot,
            'name' => 'root 1',
            'subCascade' => [
                'id' => $idSubCascade,
                'name' => 'sub cascade 1',
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create([$data], $context);

        $delete = $this->repository->delete([['id' => $idRoot]], $context);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $delete);
        static::assertCount(2, $delete->getEvents());

        $rootEvent = $delete->getEventByEntityName(RootDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityDeletedEvent::class, $rootEvent);
        static::assertCount(1, $rootEvent->getWriteResults());
        static::assertSame([$idRoot], $rootEvent->getIds());

        $subCascadeEvent = $delete->getEventByEntityName(SubCascadeDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityDeletedEvent::class, $subCascadeEvent);
        static::assertCount(1, $subCascadeEvent->getWriteResults());
        static::assertSame([$idRoot], $subCascadeEvent->getIds());
    }

    public function testRestrictDelete(): void
    {
        $idRoot = Uuid::randomHex();
        $idSub = Uuid::randomHex();

        $data = [
            'id' => $idRoot,
            'name' => 'root 1',
            'sub' => [
                'id' => $idSub,
                'name' => 'sub 1',
            ],
        ];

        $context = Context::createDefaultContext();

        $this->repository->create([$data], $context);

        $e = null;

        try {
            $this->repository->delete([['id' => $idRoot]], $context);
        } catch (RestrictDeleteViolationException $e) {
        }

        static::assertInstanceOf(RestrictDeleteViolationException::class, $e);

        $deleteSub = $this->subRepository->delete([['id' => $idSub]], $context);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $deleteSub);

        $subEvent = $deleteSub->getEventByEntityName(SubDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityDeletedEvent::class, $subEvent);
        static::assertCount(1, $subEvent->getWriteResults());
        static::assertSame([$idSub], $subEvent->getIds());

        $deleteRoot = $this->repository->delete([['id' => $idRoot]], $context);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $deleteRoot);

        $rootEvent = $deleteRoot->getEventByEntityName(RootDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityDeletedEvent::class, $rootEvent);
        static::assertCount(1, $rootEvent->getWriteResults());
        static::assertSame([$idRoot], $rootEvent->getIds());
    }

    public function testItInvalidatesTheCacheOnBothSides(): void
    {
        $idRoot = Uuid::randomHex();
        $idSub = Uuid::randomHex();

        $data = [
            'id' => $idRoot,
            'name' => 'root 1',
            'sub' => [
                'id' => $idSub,
                'name' => 'sub 1',
            ],
        ];
        $context = Context::createDefaultContext();
        $this->repository->create([$data], $context);

        $updatedRoot = $this->repository->search(new Criteria([$idRoot]), $context)->getEntities()->get($idRoot);
        $updatedSub = $this->subRepository->search((new Criteria([$idSub]))->addAssociation('root'), $context)->getEntities()->get($idSub);

        static::assertNotNull($updatedRoot->get('sub'));
        static::assertNotNull($updatedSub->get('root'));

        $this->subRepository->update([
            [
                'id' => $idSub,
                'rootId' => null,
            ],
        ], $context);

        $updatedRoot = $this->repository->search(new Criteria([$idRoot]), $context)->getEntities()->get($idRoot);
        $updatedSub = $this->subRepository->search((new Criteria([$idSub]))->addAssociation('root'), $context)->getEntities()->get($idSub);

        static::assertNull($updatedRoot->get('sub'));
        static::assertNull($updatedSub->get('root'));

        $this->subRepository->update([
            [
                'id' => $idSub,
                'rootId' => $idRoot,
            ],
        ], $context);

        $updatedRoot = $this->repository->search(new Criteria([$idRoot]), $context)->getEntities()->get($idRoot);
        $updatedSub = $this->subRepository->search((new Criteria([$idSub]))->addAssociation('root'), $context)->getEntities()->get($idSub);

        static::assertNotNull($updatedRoot->get('sub'));
        static::assertNotNull($updatedSub->get('root'));
    }
}
