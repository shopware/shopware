<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\MultiInsertQueryQueue;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(EntityRepository::class)]
class EntityRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->createRepository(CategoryDefinition::class);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=1;');
    }

    public function testReverseVersionJoin(): void
    {
        $repository = $this->getContainer()->get('product_visibility.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.orderLineItems.order.id', Uuid::randomHex()));
        $criteria->addFilter(new EqualsFilter('product.orderLineItems.type', LineItem::PRODUCT_LINE_ITEM_TYPE));

        $result = $repository->search($criteria, Context::createDefaultContext());

        static::assertEquals(0, $result->count());
    }

    /**
     * @param array<string, mixed> $products
     * @param array<string> $expected
     */
    #[DataProvider('productPropertiesQueryProvider')]
    public function testProductPropertiesQueries(array $products, Criteria $criteria, array $expected): void
    {
        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        $found = $this->getContainer()
            ->get('product.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertCount(\count($expected), $found->getIds());

        foreach ($expected as $id) {
            static::assertContains($id, $found->getIds());
        }
    }

    public static function productPropertiesQueryProvider(): \Generator
    {
        $ids = new IdsCollection();
        yield 'Test matching single property' => [
            [
                self::product($ids, 'p.1', ['red' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new EqualsFilter('properties.id', $ids->get('red'))),
            [$ids->get('p.1')],
        ];

        $ids = new IdsCollection();
        yield 'Test matching multiple properties of same group' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'green' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new EqualsFilter('properties.id', $ids->get('red')))
                ->addFilter(new EqualsFilter('properties.id', $ids->get('green'))),
            [$ids->get('p.1')],
        ];

        $ids = new IdsCollection();
        yield 'Test matching multiple properties of same group, not fit' => [
            [
                self::product($ids, 'p.1', ['red' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new EqualsFilter('properties.id', $ids->get('red')))
                ->addFilter(new EqualsFilter('properties.id', $ids->get('green'))),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Test match property and group id' => [
            [
                self::product($ids, 'p.1', ['red' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('properties.id', $ids->get('red')),
                    new EqualsFilter('properties.groupId', $ids->get('color')),
                ])),
            [$ids->get('p.1')],
        ];

        $ids = new IdsCollection();
        yield 'Test match property and group id, not fit' => [
            [
                self::product($ids, 'p.1', ['red' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('properties.id', $ids->get('red')),
                    new EqualsFilter('properties.groupId', $ids->get('size')),
                ])),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Test match property and group id using association, matches' => [
            [
                self::product($ids, 'p.1', ['red' => 'color']),
                self::product($ids, 'p.2', ['green' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('properties.id', $ids->get('red')),
                    new EqualsFilter('properties.group.id', $ids->get('color')),
                ])),
            [$ids->get('p.1')],
        ];

        $ids = new IdsCollection();
        yield 'Test match property and group id using association, not matches' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'xl' => 'size']),
                self::product($ids, 'p.2', ['green' => 'size', 'red' => 'color']),
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('properties.id', $ids->get('red')),
                    new EqualsFilter('properties.group.id', $ids->get('size')),
                ])),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Test for jonas' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'xl' => 'size']),
                self::product($ids, 'p.2', ['green' => 'color', 'l' => 'size']),
            ],
            (new Criteria())
                ->addFilter(
                    new AndFilter([
                        new EqualsFilter('properties.id', $ids->get('red')),
                        new EqualsFilter('properties.group.id', $ids->get('size')),
                    ]),
                ),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Works with no nested association' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'xl' => 'size']),
                self::product($ids, 'p.2', ['green' => 'color', 'l' => 'size']),
            ],
            (new Criteria())
                ->addFilter(
                    new OrFilter([
                        new AndFilter([
                            new EqualsFilter('properties.id', $ids->get('red')),
                            new EqualsFilter('properties.groupId', $ids->get('size')),
                        ]),
                        new EqualsFilter('properties.id', $ids->get('blue')),
                    ])
                ),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Works with nested association and or filter' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'xl' => 'size']),
                self::product($ids, 'p.2', ['green' => 'color', 'l' => 'size']),
            ],
            (new Criteria())
                ->addFilter(
                    new OrFilter([
                        new AndFilter([
                            new EqualsFilter('properties.id', $ids->get('red')),
                            new EqualsFilter('properties.group.id', $ids->get('size')),
                        ]),
                        new EqualsFilter('active', true),
                    ])
                ),
            [],
        ];

        $ids = new IdsCollection();
        yield 'Test not product fits the multi or filter and nested and filter' => [
            [
                self::product($ids, 'p.1', ['red' => 'color', 'xl' => 'size']),
                self::product($ids, 'p.2', ['green' => 'color', 'l' => 'size']),
            ],
            (new Criteria())
                ->addFilter(
                    new OrFilter([
                        new AndFilter([
                            new EqualsFilter('properties.id', $ids->get('red')),
                            new EqualsFilter('properties.group.id', $ids->get('size')),
                        ]),
                        new EqualsFilter('properties.id', $ids->get('blue')),
                    ])
                ),
            [],
        ];
    }

    /**
     * @param array<array{payment: string, state:string}> $transactions
     */
    #[DataProvider('orderTransactionsProvider')]
    public function testOrderTransactionsQueries(array $transactions, Criteria $criteria, bool $match): void
    {
        $this->getContainer()->get(Connection::class)->executeQuery('SET FOREIGN_KEY_CHECKS=0;');

        $ids = new IdsCollection();

        $queue = new MultiInsertQueryQueue($this->getContainer()->get(Connection::class));

        $queue->addInsert('order', self::order($ids->get('order-id')));

        foreach ($transactions as $transaction) {
            $queue->addInsert(
                'order_transaction',
                $this->transaction($ids->get('order-id'), $transaction['payment'], $transaction['state'])
            );
        }

        $queue->execute();

        $found = $this->getContainer()
            ->get('order.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        $found = !empty($found->getIds());

        static::assertEquals($match, $found);
    }

    public static function orderTransactionsProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Matching by just check for payment method' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal'))),
            true,
        ];

        yield 'Multi filter on transactions works, matches' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.amount.unitPrice', 100),
                ])),
            true,
        ];

        yield 'Multi filter on transactions works, not matches' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.amount.unitPrice', 110),
                ])),
            false,
        ];

        yield 'Match exact state of payment method' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.stateMachineState.technicalName', 'open'),
                ])),
            true,
        ];

        yield 'Payment method exists but state is not matching' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'open'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(new AndFilter([
                    new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                    new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                ])),
            false,
        ];

        yield 'Has open paypal or another paid transaction, matches' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'cancelled'],
                ['payment' => $ids->get('invoice'), 'state' => 'paid'],
            ],
            (new Criteria())
                ->addFilter(
                    new OrFilter([
                        new AndFilter([
                            new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                            new EqualsFilter('transactions.stateMachineState.technicalName', 'open'),
                        ]),
                        new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                    ]),
                ),
            true,
        ];

        yield 'Has open paypal or another paid transaction, does not match' => [
            [
                ['payment' => $ids->get('paypal'), 'state' => 'cancelled'],
                ['payment' => $ids->get('invoice'), 'state' => 'open'],
            ],
            (new Criteria())
                ->addFilter(
                    new OrFilter([
                        new AndFilter([
                            new EqualsFilter('transactions.paymentMethodId', $ids->get('paypal')),
                            new EqualsFilter('transactions.stateMachineState.technicalName', 'open'),
                        ]),
                        new EqualsFilter('transactions.stateMachineState.technicalName', 'paid'),
                    ]),
                ),
            false,
        ];
    }

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

    public function testMaxJoinBug(): void
    {
        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [Uuid::randomHex(), Uuid::randomHex(), Defaults::LANGUAGE_SYSTEM]
        );

        $context->setConsiderInheritance(true);

        // creates a select with 20x base tables
        // original each table gets 3x translation tables as join table
        // this results in a query of 79x joins
        $criteria = new Criteria();
        $criteria->addAssociation('type');
        $criteria->addAssociation('language.locale');
        $criteria->addAssociation('language.translationCode');
        $criteria->addAssociation('customerGroup');
        $criteria->addAssociation('currency');
        $criteria->addAssociation('paymentMethod.media');
        $criteria->addAssociation('paymentMethod.media.mediaFolder');
        $criteria->addAssociation('paymentMethod.availabilityRule');
        $criteria->addAssociation('shippingMethod.media');
        $criteria->addAssociation('shippingMethod.media.mediaFolder');
        $criteria->addAssociation('shippingMethod.availabilityRule');
        $criteria->addAssociation('shippingMethod.deliveryTime');
        $criteria->addAssociation('country');
        $criteria->addAssociation('navigationCategory');
        $criteria->addAssociation('footerCategory');
        $criteria->addAssociation('serviceCategory');

        $data = $this->getContainer()->get('sales_channel.repository')
            ->search($criteria, $context);

        static::assertInstanceOf(EntitySearchResult::class, $data);
    }

    public function testWrittenEventsFired(): void
    {
        $repository = $this->createRepository(LocaleDefinition::class);

        $context = Context::createDefaultContext();

        $id = Uuid::randomHex();

        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'locale.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'locale_translation.written', $listener);

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

        $criteria = new Criteria([$id]);
        $locale = $repository->search($criteria, $context);

        static::assertEquals([$id], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertEmpty($criteria->getAssociations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());

        static::assertCount(1, $locale);

        static::assertTrue($locale->has($id));
        $locale = $locale->get($id);
        static::assertInstanceOf(LocaleEntity::class, $locale);
        static::assertSame('Test', $locale->getName());
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
        $this->addEventListener($dispatcher, 'locale.loaded', $listener);

        $criteria = new Criteria([$id]);
        $locale = $repository->search($criteria, $context);
        static::assertEquals([$id], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertEmpty($criteria->getAssociations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());

        static::assertCount(1, $locale);

        static::assertTrue($locale->has($id));
        $locale = $locale->get($id);
        static::assertInstanceOf(LocaleEntity::class, $locale);
        static::assertSame('Test', $locale->getName());
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
        $this->addEventListener($dispatcher, 'product.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'product_manufacturer.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'tax.loaded', $listener);

        $criteria = new Criteria([$id, $id2]);
        $criteria->addAssociation('manufacturer');

        $products = $repository->search($criteria, $context);

        static::assertEquals([$id, $id2], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(1, $criteria->getAssociations());
        $manufacturerCriteria = $criteria->getAssociations()['manufacturer'];
        static::assertEmpty($manufacturerCriteria->getSorting());
        static::assertEmpty($manufacturerCriteria->getFilters());
        static::assertEmpty($manufacturerCriteria->getPostFilters());
        static::assertEmpty($manufacturerCriteria->getAggregations());
        static::assertEmpty($manufacturerCriteria->getAssociations());
        static::assertNull($manufacturerCriteria->getLimit());
        static::assertNull($manufacturerCriteria->getOffset());

        static::assertCount(2, $products);

        static::assertTrue($products->has($id));
        $product = $products->get($id);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('Test', $product->getName());
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
        $this->addEventListener($dispatcher, 'product.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'product_manufacturer.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'tax.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'product_price.written', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'rule.written', $listener);

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
        $this->addEventListener($dispatcher, 'product.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'product_manufacturer.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'tax.loaded', $listener);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::once())->method('__invoke');
        $this->addEventListener($dispatcher, 'product_price.loaded', $listener);

        $criteria = new Criteria([$id, $id2]);
        $criteria->addAssociation('prices');
        $criteria->addAssociation('manufacturer');

        $products = $repository->search($criteria, $context);
        static::assertEquals([$id, $id2], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(2, $criteria->getAssociations());
        $priceCriteria = $criteria->getAssociations()['prices'];
        static::assertNotNull($priceCriteria);
        static::assertEmpty($priceCriteria->getSorting());
        static::assertEmpty($priceCriteria->getFilters());
        static::assertEmpty($priceCriteria->getPostFilters());
        static::assertEmpty($priceCriteria->getAggregations());
        static::assertEmpty($priceCriteria->getAssociations());
        static::assertNull($priceCriteria->getLimit());
        static::assertNull($priceCriteria->getOffset());
        $manufacturerCriteria = $criteria->getAssociations()['manufacturer'];
        static::assertEmpty($manufacturerCriteria->getSorting());
        static::assertEmpty($manufacturerCriteria->getFilters());
        static::assertEmpty($manufacturerCriteria->getPostFilters());
        static::assertEmpty($manufacturerCriteria->getAggregations());
        static::assertEmpty($manufacturerCriteria->getAssociations());
        static::assertNull($manufacturerCriteria->getLimit());
        static::assertNull($manufacturerCriteria->getOffset());

        static::assertCount(2, $products);

        $product = $products->get($id);
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('Test', $product->getName());
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

        $written = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);
        static::assertNotNull($written);
        static::assertCount(3, $written->getIds());
        static::assertContains($newId, $written->getIds());

        $criteria = new Criteria([$id, $newId]);
        $entities = $repository->search($criteria, $context);
        static::assertEquals([$id, $newId], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertEmpty($criteria->getAssociations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        $old = $entities->get($id);
        $new = $entities->get($newId);

        static::assertInstanceOf(CategoryEntity::class, $old);
        static::assertInstanceOf(CategoryEntity::class, $new);

        static::assertSame($old->getName(), $new->getName());
        static::assertSame($old->getChildren(), $new->getChildren());
    }

    public function testCloneShouldUpdateDates(): void
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

        $context = Context::createDefaultContext();

        $this->categoryRepository->create([$data], $context);
        $newId = Uuid::randomHex();

        // Ensure updatedAt is set
        $this->categoryRepository->update([
            [
                'id' => $id,
                'name' => 'Test',
            ],
        ], $context);

        $criteria = new Criteria([$id]);
        $preCloneEntity = $this->categoryRepository->search($criteria, $context)->getEntities()->first();
        static::assertInstanceOf(CategoryEntity::class, $preCloneEntity);

        $result = $this->categoryRepository->clone($id, $context, $newId);

        $written = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);
        static::assertNotNull($written);
        static::assertCount(3, $written->getIds());
        static::assertContains($newId, $written->getIds());

        $criteria = new Criteria([$id, $newId]);
        $entities = $this->categoryRepository->search($criteria, $context)->getEntities();

        static::assertEquals([$id, $newId], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertEmpty($criteria->getAssociations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        $postClone = $entities->get($id);
        $cloned = $entities->get($newId);

        static::assertSame($postClone->getName(), $cloned->getName());
        static::assertSame($postClone->getChildren(), $cloned->getChildren());

        // Assert createdAt and updatedAt didn't change
        static::assertEquals($preCloneEntity->getCreatedAt(), $postClone->getCreatedAt());
        static::assertEquals($preCloneEntity->getUpdatedAt(), $postClone->getUpdatedAt());

        // Assert that createdAt changed
        static::assertNotEquals($postClone->getCreatedAt(), $cloned->getCreatedAt());
        static::assertNull($cloned->getUpdatedAt());
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

        $context = Context::createDefaultContext();

        $this->categoryRepository->create([$data], $context);

        $result = $this->categoryRepository->clone($id, $context);

        $written = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);

        static::assertNotNull($written);
        static::assertCount(3, $written->getIds());
        $newId = $written->getIds();
        $newId = array_shift($newId);
        static::assertNotEquals($id, $newId);

        $criteria = new Criteria([$id, $newId]);
        $criteria->addAssociation('children');
        $entities = $this->categoryRepository->search($criteria, $context)->getEntities();
        static::assertEquals([$id, $newId], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(1, $criteria->getAssociations());
        $childrenCriteria = $criteria->getAssociations()['children'];
        static::assertNotNull($childrenCriteria);
        static::assertEmpty($childrenCriteria->getSorting());
        static::assertEmpty($childrenCriteria->getFilters());
        static::assertEmpty($childrenCriteria->getPostFilters());
        static::assertEmpty($childrenCriteria->getAggregations());
        static::assertEmpty($childrenCriteria->getAssociations());
        static::assertNull($childrenCriteria->getLimit());
        static::assertNull($childrenCriteria->getOffset());

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        $old = $entities->get($id);
        $new = $entities->get($newId);

        static::assertSame($old->getName(), $new->getName());
        $oldChildren = $old->getChildren();
        static::assertNotNull($oldChildren);
        $newChildren = $new->getChildren();
        static::assertNotNull($newChildren);
        static::assertCount($oldChildren->count(), $newChildren);
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

        $record = [
            'id' => $recordA,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => $address,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'lastName' => 'not',
            'firstName' => $matchTerm,
            'salutationId' => $salutation,
            'customerNumber' => 'not',
            'addresses' => [
                $address2,
            ],
        ];

        if (!Feature::isActive('v6.7.0.0')) {
            $record['defaultPaymentMethodId'] = $this->getValidPaymentMethodId();
        }

        $repository = $this->createRepository(CustomerDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$record], $context);
        $newId = Uuid::randomHex();

        $result = $repository->clone($recordA, $context, $newId);

        $written = $result->getEventByEntityName(CustomerAddressDefinition::ENTITY_NAME);
        static::assertNotNull($written);
        static::assertCount(2, $written->getIds());

        $criteria = new Criteria([$recordA, $newId]);
        $criteria->addAssociation('addresses');

        $entities = $repository->search($criteria, $context);
        static::assertEquals([$recordA, $newId], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(0, $criteria->getAggregations());
        $addressCriteria = $criteria->getAssociations()['addresses'];
        static::assertNotNull($addressCriteria);
        static::assertEmpty($addressCriteria->getSorting());
        static::assertEmpty($addressCriteria->getFilters());
        static::assertEmpty($addressCriteria->getPostFilters());
        static::assertEmpty($addressCriteria->getAggregations());
        static::assertEmpty($addressCriteria->getAssociations());
        static::assertNull($addressCriteria->getLimit());
        static::assertNull($addressCriteria->getOffset());

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($recordA));
        static::assertTrue($entities->has($newId));

        $old = $entities->get($recordA);
        $new = $entities->get($newId);
        static::assertInstanceOf(CustomerEntity::class, $old);
        static::assertInstanceOf(CustomerEntity::class, $new);

        static::assertNotNull($oldAddresses = $old->getAddresses());
        static::assertNotNull($newAddresses = $new->getAddresses());
        static::assertCount(2, $oldAddresses);
        static::assertCount(2, $newAddresses);

        $oldAddressIds = $oldAddresses->map(static fn (CustomerAddressEntity $address) => $address->getId());
        $newAddressIds = $newAddresses->map(static fn (CustomerAddressEntity $address) => $address->getId());

        foreach ($oldAddressIds as $id) {
            static::assertNotContains($id, $newAddressIds);
        }
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

        /** @var EntityRepository<CategoryCollection> $repo */
        $repo = $this->getContainer()->get('category.repository');

        $context = Context::createDefaultContext();

        $repo->create([$data], $context);

        $newId = Uuid::randomHex();

        $repo->clone($id, $context, $newId);

        $childrenIds = $this->getContainer()->get(Connection::class)
            ->fetchAllAssociative(
                'SELECT id FROM category WHERE parent_id IN (:ids)',
                ['ids' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($newId)]],
                ['ids' => ArrayParameterType::BINARY]
            );

        static::assertCount(4, $childrenIds);

        $criteria = new Criteria([$newId]);
        $criteria->addAssociation('children');
        $category = $repo->search($criteria, $context)
            ->getEntities()
            ->get($newId);
        static::assertEquals([$newId], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(1, $criteria->getAssociations());
        $childrenCriteria = $criteria->getAssociations()['children'];
        static::assertNotNull($childrenCriteria);
        static::assertEmpty($childrenCriteria->getSorting());
        static::assertEmpty($childrenCriteria->getFilters());
        static::assertEmpty($childrenCriteria->getPostFilters());
        static::assertEmpty($childrenCriteria->getAggregations());
        static::assertEmpty($childrenCriteria->getAssociations());
        static::assertNull($childrenCriteria->getLimit());
        static::assertNull($childrenCriteria->getOffset());

        static::assertNotNull($category);
        $children = $category->getChildren();
        static::assertNotNull($children);
        static::assertCount(2, $children);
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

        // check count of conditions
        $conditions = $this->getContainer()->get(Connection::class)->fetchAllAssociative(
            'SELECT id, parent_id FROM rule_condition WHERE rule_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );
        static::assertCount(7, $conditions);
        $withParent = array_filter($conditions, static fn ($condition) => $condition['parent_id'] !== null);
        static::assertCount(6, $withParent);

        $newId = Uuid::randomHex();
        $repo->clone($id, $context, $newId);

        // check that existing rule conditions are not touched
        $conditions = $this->getContainer()->get(Connection::class)->fetchAllAssociative(
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

        // check that existing rule conditions are not touched
        $newConditions = $this->getContainer()->get(Connection::class)->fetchAllAssociative(
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

        // check that parent ids and ids of all new conditions are new
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

        $behavior = new CloneBehavior(['productNumber' => 'abc']);
        $result = $repository->clone($id, $context, $newId, $behavior);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByEntityName(ProductDefinition::ENTITY_NAME);
        static::assertNotNull($written);
        static::assertCount(1, $written->getIds());
        static::assertContains($newId, $written->getIds());

        $entities = $repository->search(new Criteria([$id, $newId]), $context);

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($id));
        static::assertTrue($entities->has($newId));

        $old = $entities->get($id);
        $new = $entities->get($newId);
        static::assertInstanceOf(ProductEntity::class, $old);
        static::assertInstanceOf(ProductEntity::class, $new);

        static::assertSame($old->getName(), $new->getName());
        static::assertSame($old->getTags(), $new->getTags());
        static::assertSame($old->getTagIds(), $new->getTagIds());
        static::assertNotSame($old->getProductNumber(), $new->getProductNumber());
    }

    public function testCloneWithoutChildren(): void
    {
        $ids = new TestDataCollection();

        $data = [
            'id' => $ids->create('parent'),
            'name' => 'parent',
            'children' => [
                ['id' => $ids->create('child-1'), 'name' => 'child'],
                ['id' => $ids->create('child-2'), 'name' => 'child'],
            ],
        ];

        $this->getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());

        $this->getContainer()->get('category.repository')
            ->clone($ids->get('parent'), Context::createDefaultContext(), $ids->create('parent-new'), new CloneBehavior([], false));

        $children = $this->getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM category WHERE parent_id = :parent', ['parent' => Uuid::fromHexToBytes($ids->get('parent-new'))]);

        static::assertCount(0, $children);

        $this->getContainer()->get('category.repository')
            ->clone($ids->get('parent'), Context::createDefaultContext(), $ids->create('parent-new-2'), new CloneBehavior([], true));

        $children = $this->getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM category WHERE parent_id = :parent', ['parent' => Uuid::fromHexToBytes($ids->get('parent-new-2'))]);

        static::assertCount(2, $children);
    }

    public function testDuplicateWrittenEvents(): void
    {
        $ids = new TestDataCollection();

        $this->getContainer()->get('property_group.repository')
            ->create([
                [
                    'name' => 'color',
                    'options' => [
                        ['id' => $ids->create('prop-1'), 'name' => 'test'],
                        ['id' => $ids->create('prop-2'), 'name' => 'test'],
                        ['id' => $ids->create('prop-3'), 'name' => 'test'],
                    ],
                ],
            ], Context::createDefaultContext());

        $this->getContainer()->get('category.repository')
            ->create([
                ['id' => $ids->create('cat-1'), 'name' => 'test'],
                ['id' => $ids->create('cat-2'), 'name' => 'test'],
                ['id' => $ids->create('cat-3'), 'name' => 'test'],
            ], Context::createDefaultContext());

        $data = [];
        for ($i = 0; $i <= 2; ++$i) {
            $data[] = [
                'id' => $ids->create('product' . $i),
                'productNumber' => $ids->get('product' . $i),
                'name' => 'product',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'tax' => [
                    'id' => $ids->create('tax'),
                    'name' => 'test',
                    'taxRate' => 15,
                ],
                'properties' => [
                    ['id' => $ids->create('prop-1')],
                    ['id' => $ids->create('prop-2')],
                    ['id' => $ids->create('prop-3')],
                ],
                'categories' => [
                    ['id' => $ids->create('cat-1')],
                    ['id' => $ids->create('cat-2')],
                    ['id' => $ids->create('cat-3')],
                ],
            ];
        }

        /** @var EntityRepository<ProductCollection> $repository */
        $repository = $this->getContainer()->get('product.repository');
        $result = $repository->create($data, Context::createDefaultContext());

        $products = $result->getEventByEntityName('product');
        static::assertNotNull($products);
        static::assertCount(3, $products->getIds());
        static::assertCount(3, $products->getWriteResults());

        $properties = $result->getEventByEntityName('property_group_option');
        static::assertNotNull($properties);
        static::assertCount(3, $properties->getIds());
        static::assertCount(3, $properties->getWriteResults());

        $categories = $result->getEventByEntityName('category');
        static::assertNotNull($categories);
        static::assertCount(3, $categories->getIds());
        static::assertCount(3, $categories->getWriteResults());
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
        /** @var EntityRepository<MediaFolderCollection> $repository */
        $repository = $this->getContainer()->get('media_folder.repository');

        $event = $repository->create([$data], $context)->getEventByEntityName(MediaFolderDefinition::ENTITY_NAME);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(12, $event->getIds());

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('children')
            ->setLimit(2)
            ->setOffset(0);

        $folder = $repository->search($criteria, $context)
            ->getEntities()
            ->get($id);
        static::assertEquals([$id], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(1, $criteria->getAssociations());
        $childrenCriteria = $criteria->getAssociations()['children'];
        static::assertNotNull($childrenCriteria);
        static::assertEmpty($childrenCriteria->getSorting());
        static::assertEmpty($childrenCriteria->getFilters());
        static::assertEmpty($childrenCriteria->getPostFilters());
        static::assertEmpty($childrenCriteria->getAggregations());
        static::assertEmpty($childrenCriteria->getAssociations());
        static::assertEquals(2, $childrenCriteria->getLimit());
        static::assertEquals(0, $childrenCriteria->getOffset());

        static::assertInstanceOf(MediaFolderEntity::class, $folder);
        static::assertInstanceOf(MediaFolderCollection::class, $folder->getChildren());
        static::assertCount(2, $folder->getChildren());

        $firstIds = $folder->getChildren()->getIds();

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('children')->setLimit(3)->setOffset(2);

        $folder = $repository->search($criteria, $context)
            ->getEntities()
            ->get($id);
        static::assertEquals([$id], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(1, $criteria->getAssociations());
        $childrenCriteria = $criteria->getAssociations()['children'];
        static::assertNotNull($childrenCriteria);
        static::assertEmpty($childrenCriteria->getSorting());
        static::assertEmpty($childrenCriteria->getFilters());
        static::assertEmpty($childrenCriteria->getPostFilters());
        static::assertEmpty($childrenCriteria->getAggregations());
        static::assertEmpty($childrenCriteria->getAssociations());
        static::assertEquals(3, $childrenCriteria->getLimit());
        static::assertEquals(2, $childrenCriteria->getOffset());

        static::assertInstanceOf(MediaFolderEntity::class, $folder);
        static::assertInstanceOf(MediaFolderCollection::class, $folder->getChildren());
        static::assertCount(3, $folder->getChildren());

        $secondIds = $folder->getChildren()->getIds();
        foreach ($firstIds as $id) {
            static::assertNotContains($id, $secondIds);
        }
    }

    public function testFilterConsistencyOnCriteriaObject(): void
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

        $context = Context::createDefaultContext();

        $this->categoryRepository->create([$data], $context);
        $newId = Uuid::randomHex();

        $result = $this->categoryRepository->clone($id, $context, $newId);

        $written = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);
        static::assertNotNull($written);
        static::assertCount(3, $written->getIds());
        static::assertContains($newId, $written->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('name', 'Child1'),
            new EqualsFilter('name', 'Child2'),
        ]));
        $this->categoryRepository->search($criteria, $context);
        static::assertEquals([], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertCount(1, $criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertEmpty($criteria->getAssociations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        $multiFilter = $criteria->getFilters()[0];
        static::assertInstanceOf(MultiFilter::class, $multiFilter);
        static::assertEquals(MultiFilter::CONNECTION_OR, $multiFilter->getOperator());
        static::assertCount(2, $multiFilter->getQueries());
    }

    public function testEmptyFiltersAreHandledByEntityReaderWithoutPriorSearch(): void
    {
        $searcherMock = $this->createMock(EntitySearcherInterface::class);
        $searcherMock->expects(static::never())
            ->method('search');

        $repository = new EntityRepository(
            $this->getContainer()->get(CurrencyDefinition::class),
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $searcherMock,
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(EntityLoadedEventFactory::class)
        );

        $result = $repository->search(new Criteria(), Context::createDefaultContext());
        $currencyCount = (int) $this->getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(`id`) FROM `currency`');

        static::assertEquals(
            $currencyCount,
            $result->getEntities()->count()
        );
    }

    public function testSnippetWriteWithoutValueFieldThrowsWriteValidationError(): void
    {
        $snippetRepo = $this->createRepository(SnippetDefinition::class);
        $snippetSetId = $this->getSnippetSetIdForLocale('en-GB');

        static::expectException(WriteException::class);
        $snippetRepo->create([
            [
                'id' => Uuid::randomHex(),
                'translationKey' => 'test',
                'setId' => $snippetSetId,
                'author' => 'test',
            ],
        ], Context::createDefaultContext());
    }

    /**
     * @param array<string, string> $properties
     *
     * @return array<string, mixed>
     */
    private static function product(IdsCollection $ids, string $key, array $properties): array
    {
        $builder = new ProductBuilder($ids, $key);
        $builder->price(100);
        foreach ($properties as $value => $group) {
            $builder->property($value, $group);
        }
        $builder->active(false);

        return $builder->build();
    }

    /**
     * @return array<string, mixed>
     */
    private static function order(string $id): array
    {
        return [
            'id' => Uuid::fromHexToBytes($id),
            'currency_factor' => 1.0,
            'order_date_time' => '2020-01-01 00:00:00.000000',
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'price' => json_encode([
                'netPrice' => 100,
                'taxStatus' => 'gross',
                'totalPrice' => 100,
                'positionPrice' => 1,
            ]),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'state_id' => Uuid::randomBytes(),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'billing_address_id' => Uuid::randomBytes(),
            'billing_address_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'shipping_costs' => '{}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function transaction(string $orderId, string $payment, string $state): array
    {
        $machineId = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT id FROM state_machine WHERE technical_name = :state', ['state' => 'order_transaction.state']);

        $stateId = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT id FROM state_machine_state WHERE technical_name = :state AND state_machine_id = :machineId', ['state' => $state, 'machineId' => $machineId]);

        return [
            'id' => Uuid::randomBytes(),
            'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'order_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'order_id' => Uuid::fromHexToBytes($orderId),
            'payment_method_id' => Uuid::fromHexToBytes($payment),
            'state_id' => $stateId,
            'amount' => json_encode(['unitPrice' => 100]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    private function createRepository(
        string $definitionClass,
        ?EntityLoadedEventFactory $eventFactory = null
    ): EntityRepository {
        $definition = $this->getContainer()->get($definitionClass);
        static::assertInstanceOf(EntityDefinition::class, $definition);

        return new EntityRepository(
            $definition,
            $this->getContainer()->get(EntityReaderInterface::class),
            $this->getContainer()->get(VersionManager::class),
            $this->getContainer()->get(EntitySearcherInterface::class),
            $this->getContainer()->get(EntityAggregatorInterface::class),
            $this->getContainer()->get('event_dispatcher'),
            $eventFactory ?: $this->getContainer()->get(EntityLoadedEventFactory::class)
        );
    }
}
