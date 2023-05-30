<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
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
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class EntityRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

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

        static::assertInstanceOf(EntityCollection::class, $locale);
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

        static::assertInstanceOf(EntityCollection::class, $locale);
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
        static::assertNotNull($criteria->getAssociation('manufacturer'));
        static::assertEmpty($criteria->getAssociation('manufacturer')->getSorting());
        static::assertEmpty($criteria->getAssociation('manufacturer')->getFilters());
        static::assertEmpty($criteria->getAssociation('manufacturer')->getPostFilters());
        static::assertEmpty($criteria->getAssociation('manufacturer')->getAggregations());
        static::assertEmpty($criteria->getAssociation('manufacturer')->getAssociations());
        static::assertNull($criteria->getAssociation('manufacturer')->getLimit());
        static::assertNull($criteria->getAssociation('manufacturer')->getOffset());

        static::assertInstanceOf(EntityCollection::class, $products);
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
        static::assertNotNull($criteria->getAssociation('prices'));
        static::assertEmpty($criteria->getAssociation('prices')->getSorting());
        static::assertEmpty($criteria->getAssociation('prices')->getFilters());
        static::assertEmpty($criteria->getAssociation('prices')->getPostFilters());
        static::assertEmpty($criteria->getAssociation('prices')->getAggregations());
        static::assertEmpty($criteria->getAssociation('prices')->getAssociations());
        static::assertNull($criteria->getAssociation('prices')->getLimit());
        static::assertNull($criteria->getAssociation('prices')->getOffset());
        static::assertNotNull($criteria->getAssociation('manufacturer'));
        static::assertEmpty($criteria->getAssociation('manufacturer')->getSorting());
        static::assertEmpty($criteria->getAssociation('manufacturer')->getFilters());
        static::assertEmpty($criteria->getAssociation('manufacturer')->getPostFilters());
        static::assertEmpty($criteria->getAssociation('manufacturer')->getAggregations());
        static::assertEmpty($criteria->getAssociation('manufacturer')->getAssociations());
        static::assertNull($criteria->getAssociation('manufacturer')->getLimit());
        static::assertNull($criteria->getAssociation('manufacturer')->getOffset());

        static::assertInstanceOf(EntityCollection::class, $products);
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
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

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

        /** @var CategoryEntity $old */
        $old = $entities->get($id);
        /** @var CategoryEntity $new */
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

        $repository = $this->createRepository(CategoryDefinition::class);

        $context = Context::createDefaultContext();

        $repository->create([$data], $context);
        $newId = Uuid::randomHex();

        // Ensure updatedAt is set
        $repository->update([
            [
                'id' => $id,
                'name' => 'Test',
            ],
        ], $context);

        $criteria = new Criteria([$id]);
        /** @var CategoryEntity $preCloneEntity */
        $preCloneEntity = $repository->search($criteria, $context)->first();

        $result = $repository->clone($id, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

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

        /** @var CategoryEntity $postClone */
        $postClone = $entities->get($id);
        /** @var CategoryEntity $cloned */
        $cloned = $entities->get($newId);
        static::assertInstanceOf(CategoryEntity::class, $postClone);
        static::assertInstanceOf(CategoryEntity::class, $cloned);

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

        $repository = $this->createRepository(CategoryDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);

        $result = $repository->clone($id, $context);

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);

        static::assertNotNull($written);
        static::assertCount(3, $written->getIds());
        $newId = $written->getIds();
        $newId = array_shift($newId);
        static::assertNotEquals($id, $newId);

        $criteria = new Criteria([$id, $newId]);
        $criteria->addAssociation('children');
        $entities = $repository->search($criteria, $context);
        static::assertEquals([$id, $newId], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(1, $criteria->getAssociations());
        static::assertNotNull($criteria->getAssociation('children'));
        static::assertEmpty($criteria->getAssociation('children')->getSorting());
        static::assertEmpty($criteria->getAssociation('children')->getFilters());
        static::assertEmpty($criteria->getAssociation('children')->getPostFilters());
        static::assertEmpty($criteria->getAssociation('children')->getAggregations());
        static::assertEmpty($criteria->getAssociation('children')->getAssociations());
        static::assertNull($criteria->getAssociation('children')->getLimit());
        static::assertNull($criteria->getAssociation('children')->getOffset());

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
        static::assertNotNull($oldChildren = $old->getChildren());
        static::assertNotNull($newChildren = $new->getChildren());
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

        $paymentMethod = $this->getValidPaymentMethodId();
        $record = [
            'id' => $recordA,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => $address,
            'defaultPaymentMethodId' => $paymentMethod,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'password' => 'not12345',
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
        static::assertNotNull($criteria->getAssociation('addresses'));
        static::assertEmpty($criteria->getAssociation('addresses')->getSorting());
        static::assertEmpty($criteria->getAssociation('addresses')->getFilters());
        static::assertEmpty($criteria->getAssociation('addresses')->getPostFilters());
        static::assertEmpty($criteria->getAssociation('addresses')->getAggregations());
        static::assertEmpty($criteria->getAssociation('addresses')->getAssociations());
        static::assertNull($criteria->getAssociation('addresses')->getLimit());
        static::assertNull($criteria->getAssociation('addresses')->getOffset());

        static::assertCount(2, $entities);
        static::assertTrue($entities->has($recordA));
        static::assertTrue($entities->has($newId));

        /** @var CustomerEntity $old */
        $old = $entities->get($recordA);
        /** @var CustomerEntity $new */
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
        static::assertNotNull($written);
        static::assertCount(1, $written->getIds());

        $result = $repository->clone($recordA, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByEntityName(ShippingMethodDefinition::ENTITY_NAME);
        static::assertNotNull($written);
        static::assertCount(1, $written->getIds());

        $criteria = new Criteria([$recordA, $newId]);
        $criteria->addAssociation('tags');
        $entities = $repository->search($criteria, $context);
        static::assertEquals([$recordA, $newId], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(1, $criteria->getAssociations());
        static::assertNotNull($criteria->getAssociation('tags'));
        static::assertEmpty($criteria->getAssociation('tags')->getSorting());
        static::assertEmpty($criteria->getAssociation('tags')->getFilters());
        static::assertEmpty($criteria->getAssociation('tags')->getPostFilters());
        static::assertEmpty($criteria->getAssociation('tags')->getAggregations());
        static::assertEmpty($criteria->getAssociation('tags')->getAssociations());
        static::assertNull($criteria->getAssociation('tags')->getLimit());
        static::assertNull($criteria->getAssociation('tags')->getOffset());

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
            ->fetchAllAssociative(
                'SELECT id FROM category WHERE parent_id IN (:ids)',
                ['ids' => [Uuid::fromHexToBytes($id), Uuid::fromHexToBytes($newId)]],
                ['ids' => ArrayParameterType::STRING]
            );

        static::assertCount(4, $childrenIds);

        $Criteria = new Criteria([$newId]);
        $Criteria->addAssociation('children');
        /** @var CategoryEntity $category */
        $category = $repo->search($Criteria, $context)->get($newId);
        static::assertEquals([$newId], $Criteria->getIds());
        static::assertEmpty($Criteria->getSorting());
        static::assertEmpty($Criteria->getFilters());
        static::assertEmpty($Criteria->getPostFilters());
        static::assertEmpty($Criteria->getAggregations());
        static::assertNull($Criteria->getLimit());
        static::assertNull($Criteria->getOffset());
        static::assertCount(1, $Criteria->getAssociations());
        static::assertNotNull($Criteria->getAssociation('children'));
        static::assertEmpty($Criteria->getAssociation('children')->getSorting());
        static::assertEmpty($Criteria->getAssociation('children')->getFilters());
        static::assertEmpty($Criteria->getAssociation('children')->getPostFilters());
        static::assertEmpty($Criteria->getAssociation('children')->getAggregations());
        static::assertEmpty($Criteria->getAssociation('children')->getAssociations());
        static::assertNull($Criteria->getAssociation('children')->getLimit());
        static::assertNull($Criteria->getAssociation('children')->getOffset());

        static::assertNotNull($children = $category->getChildren());
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

        //check count of conditions
        $conditions = $this->getContainer()->get(Connection::class)->fetchAllAssociative(
            'SELECT id, parent_id FROM rule_condition WHERE rule_id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );
        static::assertCount(7, $conditions);
        $withParent = array_filter($conditions, static fn ($condition) => $condition['parent_id'] !== null);
        static::assertCount(6, $withParent);

        $newId = Uuid::randomHex();
        $repo->clone($id, $context, $newId);

        //check that existing rule conditions are not touched
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

        //check that existing rule conditions are not touched
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

        /** @var EntityWrittenContainerEvent $result */
        $result = $this->getContainer()->get('product.repository')
            ->create($data, Context::createDefaultContext());

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
        /** @var EntityRepository $repository */
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
        static::assertEquals([$id], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(1, $criteria->getAssociations());
        static::assertNotNull($criteria->getAssociation('children'));
        static::assertEmpty($criteria->getAssociation('children')->getSorting());
        static::assertEmpty($criteria->getAssociation('children')->getFilters());
        static::assertEmpty($criteria->getAssociation('children')->getPostFilters());
        static::assertEmpty($criteria->getAssociation('children')->getAggregations());
        static::assertEmpty($criteria->getAssociation('children')->getAssociations());
        static::assertEquals(2, $criteria->getAssociation('children')->getLimit());
        static::assertEquals(0, $criteria->getAssociation('children')->getOffset());

        static::assertInstanceOf(MediaFolderEntity::class, $folder);
        static::assertInstanceOf(MediaFolderCollection::class, $folder->getChildren());
        static::assertCount(2, $folder->getChildren());

        $firstIds = $folder->getChildren()->getIds();

        $criteria = new Criteria([$id]);
        $criteria->getAssociation('children')->setLimit(3)->setOffset(2);

        /** @var MediaFolderEntity $folder */
        $folder = $repository->search($criteria, $context)->get($id);
        static::assertEquals([$id], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertEmpty($criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertCount(1, $criteria->getAssociations());
        static::assertNotNull($criteria->getAssociation('children'));
        static::assertEmpty($criteria->getAssociation('children')->getSorting());
        static::assertEmpty($criteria->getAssociation('children')->getFilters());
        static::assertEmpty($criteria->getAssociation('children')->getPostFilters());
        static::assertEmpty($criteria->getAssociation('children')->getAggregations());
        static::assertEmpty($criteria->getAssociation('children')->getAssociations());
        static::assertEquals(3, $criteria->getAssociation('children')->getLimit());
        static::assertEquals(2, $criteria->getAssociation('children')->getOffset());

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

        $repository = $this->createRepository(CategoryDefinition::class);
        $context = Context::createDefaultContext();

        $repository->create([$data], $context);
        $newId = Uuid::randomHex();

        $result = $repository->clone($id, $context, $newId);
        static::assertInstanceOf(EntityWrittenContainerEvent::class, $result);

        $written = $result->getEventByEntityName(CategoryDefinition::ENTITY_NAME);
        static::assertNotNull($written);
        static::assertCount(3, $written->getIds());
        static::assertContains($newId, $written->getIds());

        $criteria = new Criteria();
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('name', 'Child1'),
            new EqualsFilter('name', 'Child2'),
        ]));
        $repository->search($criteria, $context);
        static::assertEquals([], $criteria->getIds());
        static::assertEmpty($criteria->getSorting());
        static::assertCount(1, $criteria->getFilters());
        static::assertEmpty($criteria->getPostFilters());
        static::assertEmpty($criteria->getAggregations());
        static::assertEmpty($criteria->getAssociations());
        static::assertNull($criteria->getLimit());
        static::assertNull($criteria->getOffset());
        static::assertInstanceOf(MultiFilter::class, $criteria->getFilters()[0]);
        /** @var MultiFilter $multiFilter */
        $multiFilter = $criteria->getFilters()[0];
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

    /**
     * @deprecated tag:v6.6.0 - can be removed when `defaultRunInterval` is required in `ScheduledTaskDefinition`
     */
    public function testScheduledTaskBackwardsCompatibility(): void
    {
        Feature::skipTestIfActive('v6.6.0.0', $this);

        $repository = $this->createRepository(ScheduledTaskDefinition::class);

        $id = Uuid::randomHex();

        $repository->create([
            [
                'id' => $id,
                'name' => 'test',
                'scheduledTaskClass' => 'test',
                'runInterval' => 1,
                'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
            ],
        ], Context::createDefaultContext());

        $criteria = new Criteria([$id]);
        $result = $repository->search($criteria, Context::createDefaultContext());
        static::assertCount(1, $result->getEntities());
        $task = $result->getEntities()->first();
        static::assertInstanceOf(ScheduledTaskEntity::class, $task);
        static::assertEquals(1, $task->getRunInterval());
        static::assertEquals(1, $task->getDefaultRunInterval());
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     */
    private function createRepository(
        string $definitionClass,
        ?EntityLoadedEventFactory $eventFactory = null
    ): EntityRepository {
        /** @var EntityDefinition $definition */
        $definition = $this->getContainer()->get($definitionClass);

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
