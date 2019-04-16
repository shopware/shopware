<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\PaginationCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Locale\LocaleDefinition;

class EntityRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    public function testWrite(): void
    {
        $repository = $this->createRepository(LocaleDefinition::class);

        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();

        $event = $repository->create(
            [
                ['id' => $id, 'name' => 'Test', 'territory' => 'test', 'code' => 'test' . $id],
            ],
            $context
        );

        static::assertInstanceOf(EntityWrittenEvent::class, $event->getEventByDefinition(LocaleDefinition::class));
    }

    public function testWrittenEventsFired(): void
    {
        $repository = $this->createRepository(LocaleDefinition::class);

        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('locale.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('locale_translation.written', $listener);

        $repository->create(
            [
                ['id' => $id, 'name' => 'Test', 'territory' => 'test', 'code' => 'test' . $id],
            ],
            $context
        );
    }

    public function testRead(): void
    {
        $repository = $this->createRepository(LocaleDefinition::class);

        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();

        $repository->create(
            [
                ['id' => $id, 'name' => 'Test', 'territory' => 'test', 'code' => 'test' . $id],
            ],
            $context
        );

        $locale = $repository->search(new Criteria([$id]), $context);

        static::assertInstanceOf(EntityCollection::class, $locale);
        static::assertCount(1, $locale);

        static::assertTrue($locale->has($id));
        static::assertInstanceOf(Entity::class, $locale->get($id));

        static::assertSame('Test', $locale->get($id)->getName());
    }

    public function testLoadedEventFired(): void
    {
        $repository = $this->createRepository(LocaleDefinition::class);

        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();

        $repository->create(
            [
                ['id' => $id, 'name' => 'Test', 'territory' => 'test', 'code' => 'test' . $id],
            ],
            $context
        );

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('locale.loaded', $listener);

        $locale = $repository->search(new Criteria([$id]), $context);

        static::assertInstanceOf(EntityCollection::class, $locale);
        static::assertCount(1, $locale);

        static::assertTrue($locale->has($id));
        static::assertInstanceOf(Entity::class, $locale->get($id));

        static::assertSame('Test', $locale->get($id)->getName());
    }

    public function testReadWithManyToOneAssociation(): void
    {
        $repository = $this->createRepository(ProductDefinition::class);

        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $repository->create(
            [
                [
                    'id' => $id,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 1,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 10, 'net' => 5, 'linked' => false],
                ],
                [
                    'id' => $id2,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 1,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 10, 'net' => 5, 'linked' => false],
                ],
            ],
            $context
        );

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('product.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('product_manufacturer.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('tax.loaded', $listener);

        $locale = $repository->search(new Criteria([$id, $id2]), $context);

        static::assertInstanceOf(EntityCollection::class, $locale);
        static::assertCount(2, $locale);

        static::assertTrue($locale->has($id));
        static::assertInstanceOf(Entity::class, $locale->get($id));

        static::assertSame('Test', $locale->get($id)->getName());
    }

    public function testReadAndWriteWithOneToMany(): void
    {
        $repository = $this->createRepository(ProductDefinition::class);

        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('product.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('product_manufacturer.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('tax.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('product_price.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('rule.written', $listener);

        $repository->create(
            [
                [
                    'id' => $id,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 1,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 10, 'net' => 5, 'linked' => false],
                    'prices' => [
                        [
                            'price' => ['gross' => 10, 'net' => 5, 'linked' => false],
                            'currencyId' => Defaults::CURRENCY,
                            'quantityStart' => 1,
                            'quantityEnd' => 9,
                            'rule' => [
                                'name' => 'rule 1',
                                'priority' => 1,
                            ],
                        ],
                        [
                            'price' => ['gross' => 10, 'net' => 5, 'linked' => false],
                            'currencyId' => Defaults::CURRENCY,
                            'quantityStart' => 10,
                            'rule' => [
                                'name' => 'rule 2',
                                'priority' => 1,
                            ],
                        ],
                    ],
                ],
                [
                    'id' => $id2,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 1,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 10, 'net' => 5, 'linked' => false],
                    'prices' => [
                        [
                            'price' => ['gross' => 10, 'net' => 5, 'linked' => false],
                            'currencyId' => Defaults::CURRENCY,
                            'quantityStart' => 1,
                            'quantityEnd' => 9,
                            'rule' => [
                                'name' => 'rule 3',
                                'priority' => 1,
                            ],
                        ],
                        [
                            'price' => ['gross' => 10, 'net' => 5, 'linked' => false],
                            'currencyId' => Defaults::CURRENCY,
                            'quantityStart' => 10,
                            'rule' => [
                                'name' => 'rule 4',
                                'priority' => 1,
                            ],
                        ],
                    ],
                ],
            ],
            $context
        );

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('product.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('product_manufacturer.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('tax.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('product_price.loaded', $listener);

        $criteria = new Criteria([$id, $id2]);
        $criteria->addAssociation('prices');

        $locale = $repository->search($criteria, $context);

        static::assertInstanceOf(EntityCollection::class, $locale);
        static::assertCount(2, $locale);

        static::assertTrue($locale->has($id));
        static::assertInstanceOf(Entity::class, $locale->get($id));

        static::assertSame('Test', $locale->get($id)->getName());
    }

    public function testClone(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
        ];

        $repository = $this->createRepository(ProductDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);
        $newId = Uuid::randomHex();

        $result = $repository->clone($id, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByDefinition(ProductDefinition::class);
        static::assertCount(1, $written->getIds());
        static::assertContains($newId, $written->getIds());

        $entities = $repository->search(new Criteria([$id, $newId]), $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        /** @var ProductEntity $old */
        $old = $entities->get($id);
        /** @var ProductEntity $new */
        $new = $entities->get($newId);

        static::assertInstanceOf(ProductEntity::class, $old);
        static::assertInstanceOf(ProductEntity::class, $new);

        static::assertSame($old->getName(), $new->getName());
        static::assertSame($old->getTaxId(), $new->getTaxId());
        static::assertSame($old->getManufacturerId(), $new->getManufacturerId());
    }

    public function testCloneWithUnknownId(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
        ];

        $repository = $this->createRepository(ProductDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);

        $result = $repository->clone($id, $context);

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByDefinition(ProductDefinition::class);

        static::assertCount(1, $written->getIds());
        $newId = $result->getEventByDefinition(ProductDefinition::class)->getIds();
        $newId = array_shift($newId);
        static::assertNotEquals($id, $newId);

        $entities = $repository->search(new Criteria([$id, $newId]), $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        /** @var ProductEntity $old */
        $old = $entities->get($id);
        /** @var ProductEntity $new */
        $new = $entities->get($newId);

        static::assertInstanceOf(ProductEntity::class, $old);
        static::assertInstanceOf(ProductEntity::class, $new);

        static::assertSame($old->getName(), $new->getName());
        static::assertSame($old->getTaxId(), $new->getTaxId());
        static::assertSame($old->getManufacturerId(), $new->getManufacturerId());
    }

    public function testCloneWithOneToMany(): void
    {
        $ruleA = Uuid::randomHex();
        $ruleB = Uuid::randomHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 10, 'net' => 8, 'linked' => false],
                ],
            ],
        ];

        $repository = $this->createRepository(ProductDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);
        $newId = Uuid::randomHex();

        $result = $repository->clone($id, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByDefinition(ProductPriceDefinition::class);
        static::assertCount(2, $written->getIds());

        $criteria = new Criteria([$id, $newId]);
        $criteria->addAssociation('prices');

        $entities = $repository->search($criteria, $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        /** @var ProductEntity $old */
        $old = $entities->get($id);
        /** @var ProductEntity $new */
        $new = $entities->get($newId);

        static::assertInstanceOf(ProductEntity::class, $old);
        static::assertInstanceOf(ProductEntity::class, $new);

        static::assertCount(2, $old->getPrices());
        static::assertCount(2, $new->getPrices());

        $oldPriceIds = $old->getPrices()->map(function (ProductPriceEntity $price) {
            return $price->getId();
        });
        $newPriceIds = $new->getPrices()->map(function (ProductPriceEntity $price) {
            return $price->getId();
        });

        foreach ($oldPriceIds as $id) {
            static::assertNotContains($id, $newPriceIds);
        }
    }

    public function testCloneWithManyToMany(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'test'],
            ],
        ];

        $repository = $this->createRepository(ProductDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);
        $newId = Uuid::randomHex();

        $result = $repository->clone($id, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByDefinition(ProductCategoryDefinition::class);
        static::assertCount(1, $written->getIds());

        $written = $result->getEventByDefinition(CategoryDefinition::class);
        static::assertNull($written);

        $criteria = new Criteria([$id, $newId]);
        $criteria->addAssociation('categories');
        $criteria->addAssociation('categoriesRo');
        $entities = $repository->search($criteria, $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        /** @var ProductEntity $old */
        $old = $entities->get($id);
        /** @var ProductEntity $new */
        $new = $entities->get($newId);

        static::assertInstanceOf(ProductEntity::class, $old);
        static::assertInstanceOf(ProductEntity::class, $new);

        static::assertCount(1, $old->getCategories());
        static::assertCount(1, $new->getCategories());

        static::assertCount(1, $old->getCategoryTree());
        static::assertCount(1, $new->getCategoryTree());

        static::assertCount(1, $old->getCategoriesRo());
        static::assertCount(1, $new->getCategoriesRo());
    }

    public function testCloneWithChildren(): void
    {
        $id = Uuid::randomHex();
        $child1 = Uuid::randomHex();
        $child2 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'parent',
            'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
            'children' => [
                [
                    'id' => $child1,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 1,
                ],
                [
                    'id' => $child2,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 1,
                ],
            ],
        ];

        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('product.repository');

        $context = Context::createDefaultContext();

        $repo->create([$data], $context);

        $newId = Uuid::randomHex();

        $repo->clone($id, $context, $newId);

        $childrenIds = $this->getContainer()->get(Connection::class)
            ->fetchAll(
                'SELECT id FROM product WHERE parent_id IN (:ids)',
                ['ids' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($newId)]],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );

        static::assertCount(4, $childrenIds);

        $Criteria = new Criteria([$newId]);
        $Criteria->addAssociation('children');
        /** @var ProductEntity $product */
        $product = $repo->search($Criteria, $context)->get($newId);

        static::assertCount(2, $product->getChildren());
    }

    public function testCloneWithNestedChildren(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'name' => 'test rule',
            'priority' => 1,
            'conditions' => [
                [
                    'type' => (new AndRule())->getName(),
                    'children' => [
                        [
                            'type' => (new AndRule())->getName(),
                            'children' => [
                                [
                                    'type' => (new AndRule())->getName(),
                                    'children' => [
                                        [
                                            'type' => (new AndRule())->getName(),
                                            'children' => [
                                                [
                                                    'type' => (new AndRule())->getName(),
                                                    'children' => [
                                                        [
                                                            'type' => (new AndRule())->getName(),
                                                            'children' => [
                                                                [
                                                                    'type' => (new AndRule())->getName(),
                                                                ],
                                                            ],
                                                        ],
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $repo = $this->getContainer()->get('rule.repository');

        $context = Context::createDefaultContext();
        $repo->create([$data], $context);

        //check count of conditions
        $conditions = $this->getContainer()->get(Connection::class)->fetchAll(
            'SELECT id, parent_id FROM rule_condition WHERE rule_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );
        static::assertCount(7, $conditions);
        $withParent = array_filter($conditions, function ($condition) {
            return $condition['parent_id'] !== null;
        });
        static::assertCount(6, $withParent);

        $newId = Uuid::randomHex();
        $repo->clone($id, $context, $newId);

        //check that existing rule conditions are not touched
        $conditions = $this->getContainer()->get(Connection::class)->fetchAll(
            'SELECT id, parent_id FROM rule_condition WHERE rule_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        foreach ($conditions as &$condition) {
            $condition['id'] = Uuid::fromBytesToHex($condition['id']);
            if (!$condition['parent_id']) {
                continue;
            }
            $condition['parent_id'] = Uuid::fromBytesToHex($condition['parent_id']);
        }
        unset($condition);

        static::assertCount(7, $conditions);

        //check that existing rule conditions are not touched
        $newConditions = $this->getContainer()->get(Connection::class)->fetchAll(
            'SELECT id, parent_id FROM rule_condition WHERE rule_id = :id',
            ['id' => Uuid::fromHexToBytes($newId)]
        );

        foreach ($newConditions as &$condition) {
            $condition['id'] = Uuid::fromBytesToHex($condition['id']);
            if (!$condition['parent_id']) {
                continue;
            }
            $condition['parent_id'] = Uuid::fromBytesToHex($condition['parent_id']);
        }
        unset($condition);

        static::assertCount(7, $newConditions);

        $parentIds = array_column($conditions, 'parent_id');
        $ids = array_column($conditions, 'id');

        //check that parent ids and ids of all new conditions are new
        foreach ($newConditions as $condition) {
            static::assertNotContains($condition['id'], $ids);
            static::assertNotContains($condition['id'], $parentIds);

            if (!$condition['parent_id']) {
                continue;
            }
            static::assertNotContains($condition['parent_id'], $ids);
            static::assertNotContains($condition['parent_id'], $parentIds);
        }
    }

    public function testReadPaginatedOneToManyChildrenAssociation(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'default folder',
            'configuration' => [
                'id' => $id,
                'createThumbnails' => true,
            ],
            'children' => [
                ['name' => 'test', 'configurationId' => $id],
                ['name' => 'test', 'configurationId' => $id],
                ['name' => 'test', 'configurationId' => $id],
                ['name' => 'test', 'configurationId' => $id],
                ['name' => 'test', 'configurationId' => $id],
                ['name' => 'test', 'configurationId' => $id],
                ['name' => 'test', 'configurationId' => $id],
                ['name' => 'test', 'configurationId' => $id],
                ['name' => 'test', 'configurationId' => $id],
                ['name' => 'test', 'configurationId' => $id],
                ['name' => 'test', 'configurationId' => $id],
            ],
        ];

        $context = Context::createDefaultContext();
        $repository = $this->getContainer()->get('media_folder.repository');

        $event = $repository->create([$data], $context)->getEventByDefinition(MediaFolderDefinition::class);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(12, $event->getIds());

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('media_folder.children', new PaginationCriteria(2, 0));

        /** @var MediaFolderEntity $folder */
        $folder = $repository->search($criteria, $context)->get($id);

        static::assertInstanceOf(MediaFolderEntity::class, $folder);
        static::assertInstanceOf(MediaFolderCollection::class, $folder->getChildren());
        static::assertCount(2, $folder->getChildren());

        $firstIds = $folder->getChildren()->getIds();

        $criteria = new Criteria([$id]);
        $criteria->addAssociation('media_folder.children', new PaginationCriteria(3, 2));

        /** @var MediaFolderEntity $folder */
        $folder = $repository->search($criteria, $context)->get($id);

        static::assertInstanceOf(MediaFolderEntity::class, $folder);
        static::assertInstanceOf(MediaFolderCollection::class, $folder->getChildren());
        static::assertCount(3, $folder->getChildren());

        $secondIds = $folder->getChildren()->getIds();
        foreach ($firstIds as $id) {
            static::assertNotContains($id, $secondIds);
        }
    }

    protected function createRepository(string $definition): EntityRepository
    {
        return new EntityRepository(
            $definition,
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get('event_dispatcher')
        );
    }
}

class CallableClass
{
    public function __invoke()
    {
    }
}
