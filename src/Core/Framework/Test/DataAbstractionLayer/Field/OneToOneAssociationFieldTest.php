<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\SumAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\RootDefinition;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\TestDefinition\SubDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class OneToOneAssociationFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = $this->getContainer()->get(Connection::class);

        $this->repository = new EntityRepository(
            RootDefinition::class,
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get('event_dispatcher')
        );

        $this->connection->executeUpdate('
DROP TABLE IF EXISTS `root`;
DROP TABLE IF EXISTS `root_sub`;

CREATE TABLE `root` (
  `id` binary(16) NOT NULL,
  `name` varchar(255) NOT NULL,
  `version_id` binary(16) NOT NULL
);
CREATE TABLE `root_sub` (
  `id` binary(16) NOT NULL,
  `version_id` binary(16) NOT NULL,
  `root_version_id` binary(16) NOT NULL,
  `root_id` binary(16) NOT NULL,
  `name` varchar(255) NULL,
  `stock` int NULL
);

ALTER TABLE `root_sub`
ADD FOREIGN KEY (`root_id`, `root_version_id`) REFERENCES `root` (`id`, `version_id`) ON DELETE RESTRICT ON UPDATE NO ACTION
        ');

        $this->getContainer()->get(DefinitionRegistry::class)->add(RootDefinition::class);
        $this->getContainer()->get(DefinitionRegistry::class)->add(SubDefinition::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->connection->executeUpdate('
DROP TABLE IF EXISTS `root`;
DROP TABLE IF EXISTS `root_sub`;        
        ');
    }

    public function testWrite()
    {
        $id = Uuid::uuid4()->getHex();

        $data = [
            'id' => $id,
            'name' => 'root 1',
            'sub' => [
                'id' => $id,
                'name' => 'sub 1',
            ],
        ];

        $context = Context::createDefaultContext();

        $event = $this->repository->create([$data], $context);

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $event);

        $rootEvent = $event->getEventByDefinition(RootDefinition::class);
        static::assertInstanceOf(EntityWrittenEvent::class, $rootEvent);
        static::assertCount(1, $rootEvent->getWriteResults());
        static::assertSame([$id], $rootEvent->getIds());

        $subEvent = $event->getEventByDefinition(SubDefinition::class);
        static::assertInstanceOf(EntityWrittenEvent::class, $subEvent);
        static::assertCount(1, $subEvent->getWriteResults());
        static::assertSame([$id], $subEvent->getIds());

        $this->getContainer()->get(DefinitionRegistry::class)->remove(RootDefinition::class);
        $this->getContainer()->get(DefinitionRegistry::class)->remove(SubDefinition::class);
    }

    public function testRead()
    {
        $id = Uuid::uuid4()->getHex();

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

        $entity = $this->repository->search(new Criteria([$id]), $context)->first();

        /** @var ArrayEntity $entity */
        static::assertInstanceOf(ArrayEntity::class, $entity);
        static::assertSame($id, $entity->getId());

        $sub = $entity->get('sub');
        static::assertInstanceOf(ArrayEntity::class, $sub);
        static::assertSame('sub 1', $sub->get('name'));
    }

    public function testSearch()
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

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

    public function testAggregate()
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

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
        $criteria->addAggregation(new SumAggregation('root.sub.stock', 'stock_sum'));
        $result = $this->repository->search($criteria, $context);

        static::assertTrue($result->getAggregations()->has('stock_sum'));
        $sum = $result->getAggregations()->get('stock_sum');

        /** @var SumAggregationResult $sum */
        static::assertInstanceOf(SumAggregationResult::class, $sum);
        static::assertEquals(11, $sum->getSum());
    }

    public function testCreateVersioning()
    {
        $id = Uuid::uuid4()->getHex();

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
}
