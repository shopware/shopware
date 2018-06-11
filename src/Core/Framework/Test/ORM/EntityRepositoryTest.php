<?php

namespace Shopware\Core\Framework\Test\ORM;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Dbal\EntityAggregator;
use Shopware\Core\Framework\ORM\Dbal\EntityReader;
use Shopware\Core\Framework\ORM\Dbal\EntitySearcher;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\ORM\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\ORM\Version\Service\VersionManager;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class EntityRepositoryTest extends KernelTestCase
{
    /**
     * @var Connection
     */
    private $connection;

    protected function setUp()
    {
        self::bootKernel();
        parent::setUp();
        $this->connection = self::$container->get(Connection::class);
        $this->connection->beginTransaction();
    }

    protected function tearDown()
    {
        $this->connection->rollBack();
        parent::tearDown();
    }

    public function testWrite()
    {
        $repository = $this->createRepository(LocaleDefinition::class);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id = Uuid::uuid4()->getHex();

        $event = $repository->create(
            [
                ['id' => $id, 'name' => 'Test', 'territory' => 'test', 'code' => 'test' . $id]
            ],
            $context
        );

        $this->assertInstanceOf(EntityWrittenContainerEvent::class, $event);
        $this->assertInstanceOf(EntityWrittenEvent::class, $event->getEventByDefinition(LocaleDefinition::class));
    }

    public function testWrittenEventsFired()
    {
        $repository = $this->createRepository(LocaleDefinition::class);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id = Uuid::uuid4()->getHex();

        $dispatcher = self::$container->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('locale.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('locale_translation.written', $listener);

        $repository->create(
            [
                ['id' => $id, 'name' => 'Test', 'territory' => 'test', 'code' => 'test' . $id]
            ],
            $context
        );
    }

    public function testRead()
    {
        $repository = $this->createRepository(LocaleDefinition::class);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id = Uuid::uuid4()->getHex();

        $repository->create(
            [
                ['id' => $id, 'name' => 'Test', 'territory' => 'test', 'code' => 'test' . $id]
            ],
            $context
        );

        $locale = $repository->read([$id], $context);

        $this->assertInstanceOf(EntityCollection::class, $locale);
        $this->assertCount(1, $locale);

        $this->assertTrue($locale->has($id));
        $this->assertInstanceOf(Entity::class, $locale->get($id));

        $this->assertSame('Test', $locale->get($id)->getName());
    }

    public function testLoadedEventFired(): void
    {
        $repository = $this->createRepository(LocaleDefinition::class);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id = Uuid::uuid4()->getHex();

        $repository->create(
            [
                ['id' => $id, 'name' => 'Test', 'territory' => 'test', 'code' => 'test' . $id]
            ],
            $context
        );

        $dispatcher = self::$container->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('locale.loaded', $listener);

        $locale = $repository->read([$id], $context);

        $this->assertInstanceOf(EntityCollection::class, $locale);
        $this->assertCount(1, $locale);

        $this->assertTrue($locale->has($id));
        $this->assertInstanceOf(Entity::class, $locale->get($id));

        $this->assertSame('Test', $locale->get($id)->getName());
    }

    public function testReadWithManyToOneAssociation()
    {
        $repository = $this->createRepository(ProductDefinition::class);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $repository->create(
            [
                [
                    'id' => $id,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'rate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 10, 'net' => 5]
                ],
                [
                    'id' => $id2,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'rate' => 5],
                    'manufacturer' => ['name' => 'test'],
                    'price' => ['gross' => 10, 'net' => 5]
                ]
            ],
            $context
        );

        $dispatcher = self::$container->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('product.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('product_manufacturer.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('tax.loaded', $listener);

        $locale = $repository->read([$id, $id2], $context);

        $this->assertInstanceOf(EntityCollection::class, $locale);
        $this->assertCount(2, $locale);

        $this->assertTrue($locale->has($id));
        $this->assertInstanceOf(Entity::class, $locale->get($id));

        $this->assertSame('Test', $locale->get($id)->getName());
    }


    public function testReadAndWriteWithOneToMany()
    {
        $repository = $this->createRepository(ProductDefinition::class);

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

        $id = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $dispatcher = self::$container->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('product.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('product_manufacturer.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('tax.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('product_price_rule.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('rule.written', $listener);

        $repository->create(
            [
                [
                    'id' => $id,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'rate' => 5],
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
                                'payload' => new AndRule()
                            ]
                        ],
                        [
                            'price' => ['gross' => 10, 'net' => 5],
                            'currencyId' => Defaults::CURRENCY,
                            'quantityStart' => 10,
                            'rule' => [
                                'name' => 'rule 2',
                                'priority' => 1,
                                'payload' => new AndRule()
                            ]
                        ],
                    ]
                ],
                [
                    'id' => $id2,
                    'name' => 'Test',
                    'tax' => ['name' => 'test', 'rate' => 5],
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
                                'payload' => new AndRule()
                            ]
                        ],
                        [
                            'price' => ['gross' => 10, 'net' => 5],
                            'currencyId' => Defaults::CURRENCY,
                            'quantityStart' => 10,
                            'rule' => [
                                'name' => 'rule 4',
                                'priority' => 1,
                                'payload' => new AndRule()
                            ]
                        ],
                    ]
                ]
            ],
            $context
        );

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('product.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('product_manufacturer.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('tax.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->exactly(1))->method('__invoke');
        $dispatcher->addListener('product_price_rule.loaded', $listener);

        $locale = $repository->read([$id, $id2], $context);

        $this->assertInstanceOf(EntityCollection::class, $locale);
        $this->assertCount(2, $locale);

        $this->assertTrue($locale->has($id));
        $this->assertInstanceOf(Entity::class, $locale->get($id));

        $this->assertSame('Test', $locale->get($id)->getName());
    }


    protected function createRepository(string $definition)
    {
        return new EntityRepository(
            $definition,
            self::$container->get(EntityReader::class),
            self::$container->get(VersionManager::class),
            self::$container->get(EntitySearcher::class),
            self::$container->get(EntityAggregator::class),
            self::$container->get('event_dispatcher')
        );
    }
}

class CallableClass
{
    public function __invoke()
    {
    }
}
