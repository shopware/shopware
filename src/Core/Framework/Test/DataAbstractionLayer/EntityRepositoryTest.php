<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleEntity;
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
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
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

        $id = Uuid::uuid4()->getHex();

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

        $id = Uuid::uuid4()->getHex();

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

        $id = Uuid::uuid4()->getHex();

        $repository->create(
            [
                ['id' => $id, 'name' => 'Test', 'territory' => 'test', 'code' => 'test' . $id],
            ],
            $context
        );

        $locale = $repository->read(new ReadCriteria([$id]), $context);

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

        $id = Uuid::uuid4()->getHex();

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

        $locale = $repository->read(new ReadCriteria([$id]), $context);

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

        $id = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $repository->create(
            [
                [
                    'id' => $id,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 10, 'net' => 5],
                ],
                [
                    'id' => $id2,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 10, 'net' => 5],
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

        $locale = $repository->read(new ReadCriteria([$id, $id2]), $context);

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

        $id = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

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
        $dispatcher->addListener('product_price_rule.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $dispatcher->addListener('rule.written', $listener);

        $repository->create(
            [
                [
                    'id' => $id,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 10, 'net' => 5],
                    'priceRules' => [
                        [
                            'price' => ['gross' => 10, 'net' => 5],
                            'currencyId' => Defaults::CURRENCY,
                            'quantityStart' => 1,
                            'quantityEnd' => 9,
                            'rule' => [
                                'name' => 'rule 1',
                                'priority' => 1,
                                'payload' => new AndRule(),
                            ],
                        ],
                        [
                            'price' => ['gross' => 10, 'net' => 5],
                            'currencyId' => Defaults::CURRENCY,
                            'quantityStart' => 10,
                            'rule' => [
                                'name' => 'rule 2',
                                'priority' => 1,
                                'payload' => new AndRule(),
                            ],
                        ],
                    ],
                ],
                [
                    'id' => $id2,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'taxRate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 10, 'net' => 5],
                    'priceRules' => [
                        [
                            'price' => ['gross' => 10, 'net' => 5],
                            'currencyId' => Defaults::CURRENCY,
                            'quantityStart' => 1,
                            'quantityEnd' => 9,
                            'rule' => [
                                'name' => 'rule 3',
                                'priority' => 1,
                                'payload' => new AndRule(),
                            ],
                        ],
                        [
                            'price' => ['gross' => 10, 'net' => 5],
                            'currencyId' => Defaults::CURRENCY,
                            'quantityStart' => 10,
                            'rule' => [
                                'name' => 'rule 4',
                                'priority' => 1,
                                'payload' => new AndRule(),
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
        $dispatcher->addListener('product_price_rule.loaded', $listener);

        $locale = $repository->read(new ReadCriteria([$id, $id2]), $context);

        static::assertInstanceOf(EntityCollection::class, $locale);
        static::assertCount(2, $locale);

        static::assertTrue($locale->has($id));
        static::assertInstanceOf(Entity::class, $locale->get($id));

        static::assertSame('Test', $locale->get($id)->getName());
    }

    public function testClone()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
        ];

        $repository = $this->createRepository(ProductDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);
        $newId = Uuid::uuid4()->getHex();

        $result = $repository->clone($id, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByDefinition(ProductDefinition::class);
        static::assertCount(1, $written->getIds());
        static::assertContains($newId, $written->getIds());

        $entities = $repository->read(new ReadCriteria([$id, $newId]), $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        $old = $entities->get($id);
        $new = $entities->get($newId);

        static::assertInstanceOf(ProductEntity::class, $old);
        static::assertInstanceOf(ProductEntity::class, $new);

        /** @var ProductEntity $old */
        /** @var ProductEntity $new */
        static::assertSame($old->getName(), $new->getName());
        static::assertSame($old->getTaxId(), $new->getTaxId());
        static::assertSame($old->getManufacturerId(), $new->getManufacturerId());
    }

    public function testCloneWithUnknownId()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
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

        $entities = $repository->read(new ReadCriteria([$id, $newId]), $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        $old = $entities->get($id);
        $new = $entities->get($newId);

        static::assertInstanceOf(ProductEntity::class, $old);
        static::assertInstanceOf(ProductEntity::class, $new);

        /** @var ProductEntity $old */
        /** @var ProductEntity $new */
        static::assertSame($old->getName(), $new->getName());
        static::assertSame($old->getTaxId(), $new->getTaxId());
        static::assertSame($old->getManufacturerId(), $new->getManufacturerId());
    }

    public function testCloneWithOneToMany()
    {
        $ruleA = Uuid::uuid4()->getHex();
        $ruleB = Uuid::uuid4()->getHex();

        $this->getContainer()->get('rule.repository')->create([
            ['id' => $ruleA, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 1],
            ['id' => $ruleB, 'name' => 'test', 'payload' => new AndRule(), 'priority' => 2],
        ], Context::createDefaultContext());

        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
            'priceRules' => [
                [
                    'id' => $ruleA,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleA,
                    'price' => ['gross' => 15, 'net' => 10],
                ],
                [
                    'id' => $ruleB,
                    'currencyId' => Defaults::CURRENCY,
                    'quantityStart' => 1,
                    'ruleId' => $ruleB,
                    'price' => ['gross' => 10, 'net' => 8],
                ],
            ],
        ];

        $repository = $this->createRepository(ProductDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);
        $newId = Uuid::uuid4()->getHex();

        $result = $repository->clone($id, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByDefinition(ProductPriceRuleDefinition::class);
        static::assertCount(2, $written->getIds());

        $entities = $repository->read(new ReadCriteria([$id, $newId]), $context);

        /** @var ProductEntity $old */
        /** @var ProductEntity $new */
        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        $old = $entities->get($id);
        $new = $entities->get($newId);

        static::assertInstanceOf(ProductEntity::class, $old);
        static::assertInstanceOf(ProductEntity::class, $new);

        static::assertCount(2, $old->getPriceRules());
        static::assertCount(2, $new->getPriceRules());

        $oldPriceIds = $old->getPriceRules()->map(function (ProductPriceRuleEntity $price) {
            return $price->getId();
        });
        $newPriceIds = $new->getPriceRules()->map(function (ProductPriceRuleEntity $price) {
            return $price->getId();
        });

        foreach ($oldPriceIds as $id) {
            static::assertNotContains($id, $newPriceIds);
        }
    }

    public function testCloneWithManyToMany()
    {
        $id = Uuid::uuid4()->getHex();
        $data = [
            'id' => $id,
            'name' => 'test',
            'price' => ['gross' => 15, 'net' => 10],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'test'],
            ],
        ];

        $repository = $this->createRepository(ProductDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);
        $newId = Uuid::uuid4()->getHex();

        $result = $repository->clone($id, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByDefinition(ProductCategoryDefinition::class);
        static::assertCount(1, $written->getIds());

        $written = $result->getEventByDefinition(CategoryDefinition::class);
        static::assertNull($written);

        $criteria = new ReadCriteria([$id, $newId]);
        $criteria->addAssociation('product.categories');
        $criteria->addAssociation('product.categoriesRo');
        $entities = $repository->read($criteria, $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        $old = $entities->get($id);
        $new = $entities->get($newId);

        static::assertInstanceOf(ProductEntity::class, $old);
        static::assertInstanceOf(ProductEntity::class, $new);

        /** @var ProductEntity $old */
        /** @var ProductEntity $new */
        static::assertCount(1, $old->getCategories());
        static::assertCount(1, $new->getCategories());

        static::assertCount(1, $old->getCategoryTree());
        static::assertCount(1, $new->getCategoryTree());

        static::assertCount(1, $old->getCategoriesRo());
        static::assertCount(1, $new->getCategoriesRo());
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
