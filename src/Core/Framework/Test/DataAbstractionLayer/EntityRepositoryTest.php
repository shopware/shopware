<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
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

        static::assertInstanceOf(EntityWrittenEvent::class, $event->getEventByEntityName(LocaleDefinition::ENTITY_NAME));
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
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
                ],
                [
                    'id' => $id2,
                    'productNumber' => Uuid::randomHex(),
                    'stock' => 1,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
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

        $criteria = new Criteria([$id, $id2]);
        $criteria->addAssociation('manufacturer');

        $locale = $repository->search($criteria, $context);

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
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
                    'prices' => [
                        [
                            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
                            'quantityStart' => 1,
                            'quantityEnd' => 9,
                            'rule' => [
                                'name' => 'rule 1',
                                'priority' => 1,
                            ],
                        ],
                        [
                            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
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
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
                    'prices' => [
                        [
                            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
                            'quantityStart' => 1,
                            'quantityEnd' => 9,
                            'rule' => [
                                'name' => 'rule 3',
                                'priority' => 1,
                            ],
                        ],
                        [
                            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
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
        $criteria->addAssociation('manufacturer');

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
            'name' => 'Main',
            'children' => [
                ['id' => Uuid::randomHex(), 'name' => 'Child1'],
                ['id' => Uuid::randomHex(), 'name' => 'Child2'],
            ],
        ];

        $repository = $this->createRepository(CategoryDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);
        $newId = Uuid::randomHex();

        $result = $repository->clone($id, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);
        static::assertCount(3, $written->getIds());
        static::assertContains($newId, $written->getIds());

        $entities = $repository->search(new Criteria([$id, $newId]), $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        /** @var CategoryEntity $old */
        $old = $entities->get($id);
        /** @var CategoryEntity $new */
        $new = $entities->get($newId);

        static::assertInstanceOf(CategoryEntity::class, $old);
        static::assertInstanceOf(CategoryEntity::class, $new);

        static::assertSame($old->getName(), $new->getName());
        static::assertSame($old->getChildren(), $new->getChildren());
    }

    public function testCloneWithUnknownId(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'Main',
            'children' => [
                ['id' => Uuid::randomHex(), 'name' => 'Child1'],
                ['id' => Uuid::randomHex(), 'name' => 'Child2'],
            ],
        ];

        $repository = $this->createRepository(CategoryDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);

        $result = $repository->clone($id, $context);

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);

        static::assertCount(3, $written->getIds());
        $newId = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME)->getIds();
        $newId = array_shift($newId);
        static::assertNotEquals($id, $newId);

        $criteria = new Criteria([$id, $newId]);
        $criteria->addAssociation('children');
        $entities = $repository->search($criteria, $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        /** @var CategoryEntity $old */
        $old = $entities->get($id);
        /** @var CategoryEntity $new */
        $new = $entities->get($newId);

        static::assertInstanceOf(CategoryEntity::class, $old);
        static::assertInstanceOf(CategoryEntity::class, $new);

        static::assertSame($old->getName(), $new->getName());
        static::assertCount($old->getChildren()->count(), $new->getChildren());
    }

    public function testCloneWithOneToMany(): void
    {
        $recordA = Uuid::randomHex();

        $salutation = $this->getValidSalutationId();
        $address = [
            'firstName' => 'not',
            'lastName' => 'not',
            'city' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'salutationId' => $salutation,
            'country' => ['name' => 'not'],
        ];
        $address2 = [
            'firstName' => 'not',
            'lastName' => 'not',
            'city' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'salutationId' => $salutation,
            'country' => ['name' => 'not'],
        ];

        $matchTerm = Random::getAlphanumericString(20);

        $paymentMethod = $this->getValidPaymentMethodId();
        $record = [
            'id' => $recordA,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultShippingAddress' => $address,
            'defaultPaymentMethodId' => $paymentMethod,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'not',
            'lastName' => 'not',
            'firstName' => $matchTerm,
            'salutationId' => $salutation,
            'customerNumber' => 'not',
            'addresses' => [
                $address2,
            ],
        ];

        $repository = $this->createRepository(CustomerDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$record], $context);
        $newId = Uuid::randomHex();

        $result = $repository->clone($recordA, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByEntityName(CustomerAddressDefinition::ENTITY_NAME);
        static::assertCount(2, $written->getIds());

        $criteria = new Criteria([$recordA, $newId]);
        $criteria->addAssociation('addresses');

        $entities = $repository->search($criteria, $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($recordA));
        static::assertTrue($entities->has($newId));

        /** @var CustomerEntity $old */
        $old = $entities->get($recordA);
        /** @var CustomerEntity $new */
        $new = $entities->get($newId);

        static::assertInstanceOf(CustomerEntity::class, $old);
        static::assertInstanceOf(CustomerEntity::class, $new);

        static::assertCount(2, $old->getAddresses());
        static::assertCount(2, $new->getAddresses());

        $oldAddressIds = $old->getAddresses()->map(static function (CustomerAddressEntity $address) {
            return $address->getId();
        });
        $newAddressIds = $new->getAddresses()->map(static function (CustomerAddressEntity $address) {
            return $address->getId();
        });

        foreach ($oldAddressIds as $id) {
            static::assertNotContains($id, $newAddressIds);
        }
    }

    public function testCloneWithManyToMany(): void
    {
        static::markTestSkipped('ManyToMany are currently intendedly not cloned - to be discussed');
        $recordA = Uuid::randomHex();
        $data = [
            'id' => $recordA,
            'bindShippingfree' => false,
            'name' => 'test',
            'availabilityRule' => [
                'id' => Uuid::randomHex(),
                'name' => 'asd',
                'priority' => 2,
            ],
            'deliveryTime' => [
                'id' => Uuid::randomHex(),
                'name' => 'testDeliveryTime',
                'min' => 1,
                'max' => 90,
                'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            ],
            'tags' => [
                [
                    'name' => 'tag1',
                ],
                [
                    'name' => 'tag2',
                ],
            ],
        ];

        $repository = $this->createRepository(ShippingMethodDefinition::class);
        $context = Context::createDefaultContext();

        $result = $repository->create([$data], $context);
        $newId = Uuid::randomHex();

        $written = $result->getEventByEntityName(ShippingMethodDefinition::ENTITY_NAME);
        static::assertCount(1, $written->getIds());

        $result = $repository->clone($recordA, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByEntityName(ShippingMethodDefinition::ENTITY_NAME);
        static::assertCount(1, $written->getIds());

        $criteria = new Criteria([$recordA, $newId]);
        $criteria->addAssociation('tags');
        $entities = $repository->search($criteria, $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($recordA));
        static::assertTrue($entities->has($newId));

        /** @var ShippingMethodEntity $old */
        $old = $entities->get($recordA);
        /** @var ShippingMethodEntity $new */
        $new = $entities->get($newId);

        static::assertInstanceOf(ShippingMethodEntity::class, $old);
        static::assertInstanceOf(ShippingMethodEntity::class, $new);

        static::assertCount(2, $old->getTags());
        static::assertCount(2, $new->getTags());
    }

    public function testCloneWithChildren(): void
    {
        $id = Uuid::randomHex();
        $child1 = Uuid::randomHex();
        $child2 = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'Main',
            'children' => [
                ['id' => $child1, 'name' => 'Child1'],
                ['id' => $child2, 'name' => 'Child2'],
            ],
        ];

        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('category.repository');

        $context = Context::createDefaultContext();

        $repo->create([$data], $context);

        $newId = Uuid::randomHex();

        $repo->clone($id, $context, $newId);

        $childrenIds = $this->getContainer()->get(Connection::class)
            ->fetchAll(
                'SELECT id FROM category WHERE parent_id IN (:ids)',
                ['ids' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($newId)]],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );

        static::assertCount(4, $childrenIds);

        $Criteria = new Criteria([$newId]);
        $Criteria->addAssociation('children');
        /** @var CategoryEntity $category */
        $category = $repo->search($Criteria, $context)->get($newId);

        static::assertCount(2, $category->getChildren());
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
        $withParent = array_filter($conditions, static function ($condition) {
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

    public function testCloneWithOverrides(): void
    {
        $id = Uuid::randomHex();
        $tags = [
            ['id' => Uuid::randomHex(), 'name' => 'tag1'],
            ['id' => Uuid::randomHex(), 'name' => 'tag2'],
            ['id' => Uuid::randomHex(), 'name' => 'tag3'],
        ];
        $productNumber = Uuid::randomHex();

        $data = [
            'id' => $id,
            'productNumber' => $productNumber,
            'stock' => 1,
            'name' => 'Test',
            'tax' => ['name' => 'test', 'taxRate' => 5],
            'manufacturer' => ['name' => 'test'],
            'tags' => $tags,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 5, 'linked' => false]],
        ];

        $repository = $this->getContainer()->get('product.repository');
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);
        $newId = Uuid::randomHex();

        $result = $repository->clone($id, $context, $newId, ['productNumber' => 'abc']);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByEntityName(ProductDefinition::ENTITY_NAME);
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
        static::assertSame($old->getTags(), $new->getTags());
        static::assertSame($old->getTagIds(), $new->getTagIds());
        static::assertNotSame($old->getProductNumber(), $new->getProductNumber());
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
        /** @var EntityRepositoryInterface $repository */
        $repository = $this->getContainer()->get('media_folder.repository');

        $event = $repository->create([$data], $context)->getEventByEntityName(MediaFolderDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(12, $event->getIds());

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('children')
            ->setLimit(2)
            ->setOffset(0);

        /** @var MediaFolderEntity $folder */
        $folder = $repository->search($criteria, $context)->get($id);

        static::assertInstanceOf(MediaFolderEntity::class, $folder);
        static::assertInstanceOf(MediaFolderCollection::class, $folder->getChildren());
        static::assertCount(2, $folder->getChildren());

        $firstIds = $folder->getChildren()->getIds();

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('children')->setLimit(3)->setOffset(2);

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
            $this->getContainer()->get($definition),
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get('event_dispatcher')
        );
    }
}
