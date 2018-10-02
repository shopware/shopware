<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\Framework\ORM\EntityCollection;
use Shopware\Core\Framework\ORM\EntityRepository;
use Shopware\Core\Framework\ORM\Event\EntityWrittenEvent;
use Shopware\Core\Framework\ORM\Read\EntityReaderInterface;
use Shopware\Core\Framework\ORM\Read\ReadCriteria;
use Shopware\Core\Framework\ORM\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\ORM\Search\EntitySearcherInterface;
use Shopware\Core\Framework\ORM\VersionManager;
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

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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

        $context = Context::createDefaultContext(Defaults::TENANT_ID);

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
