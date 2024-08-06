<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(EntityWriteGateway::class)]
class EntityWriteGatewayTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $productRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->ids = new IdsCollection();

        $this->createProduct($this->ids->create('product'));
        $this->createProduct($this->ids->create('product-2'));
    }

    public function testFetchChangeSet(): void
    {
        $update = ['id' => $this->ids->get('product'), 'stock' => 100];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('stock'));
        static::assertEquals(1, $changeSet->getBefore('stock'));
        static::assertEquals(100, $changeSet->getAfter('stock'));
    }

    public function testUpdateWithSameValue(): void
    {
        $id = $this->ids->get('product');

        $update = ['id' => $id, 'stock' => 1];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductDefinition::ENTITY_NAME, $result);

        static::assertFalse($changeSet->hasChanged('stock'));
        static::assertEquals('1', $changeSet->getBefore('stock'));
        static::assertNull($changeSet->getAfter('stock'));
    }

    public function testChangeSetWithDeletes(): void
    {
        $id = $this->ids->get('product');

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->delete([['id' => $id]], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductDefinition::ENTITY_NAME, $result);
        static::assertTrue($changeSet->hasChanged('id'));
        static::assertTrue($changeSet->hasChanged('product_number'));
        static::assertTrue($changeSet->hasChanged('price'));

        static::assertNull($changeSet->getAfter('id'));
        static::assertNull($changeSet->getAfter('product_number'));
        static::assertNull($changeSet->getAfter('price'));

        $changeSet = $this->getChangeSet(ProductCategoryDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('product_id'));
        static::assertEquals(Uuid::fromHexToBytes($id), $changeSet->getBefore('product_id'));
        static::assertNull($changeSet->getAfter('product_id'));
    }

    public function testChangeSetWithTranslations(): void
    {
        $id = $this->ids->get('product');

        $update = ['id' => $id, 'name' => 'updated'];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductTranslationDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('name'));
        static::assertEquals('test', $changeSet->getBefore('name'));
        static::assertEquals('updated', $changeSet->getAfter('name'));
    }

    public function testChangeSetWithOneToMany(): void
    {
        $id = $this->ids->get('product');

        $update = [
            'id' => $id,
            'visibilities' => [
                ['id' => $id, 'visibility' => ProductVisibilityDefinition::VISIBILITY_LINK],
            ],
        ];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductVisibilityDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('visibility'));
        static::assertEquals(ProductVisibilityDefinition::VISIBILITY_ALL, $changeSet->getBefore('visibility'));
        static::assertEquals(ProductVisibilityDefinition::VISIBILITY_LINK, $changeSet->getAfter('visibility'));
    }

    public function testChangeSetWithManyToOne(): void
    {
        $id = $this->ids->get('product');
        $newId = Uuid::randomHex();

        $update = [
            'id' => $id,
            'manufacturer' => [
                'id' => $newId,
                'name' => 'new manufacturer',
            ],
        ];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(ProductDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('product_manufacturer_id'));
        static::assertEquals($id, Uuid::fromBytesToHex($changeSet->getBefore('product_manufacturer_id')));
        static::assertEquals($newId, Uuid::fromBytesToHex($changeSet->getAfter('product_manufacturer_id')));
    }

    public function testChangeSetWithMultipleCommandsForSameEntityType(): void
    {
        $productId1 = $this->ids->get('product');
        $productId2 = $this->ids->get('product-2');

        $updates = [
            ['id' => $productId1, 'stock' => 100],
            ['id' => $productId2, 'stock' => 50],
        ];

        $this->getContainer()->get('event_dispatcher')
            ->addListener(PreWriteValidationEvent::class, function (PreWriteValidationEvent $event): void {
                foreach ($event->getCommands() as $command) {
                    if (!$command instanceof ChangeSetAware) {
                        continue;
                    }
                    $command->requestChangeSet();
                }
            });

        $result = $this->productRepository->update($updates, Context::createDefaultContext());

        $changeSets = $this->getChangeSets(ProductDefinition::ENTITY_NAME, $result, 2);
        $changeSetForProduct1 = array_values(array_filter($changeSets, function (ChangeSet $changeSet) use (&$productId1) {
            return $changeSet->getBefore('id') === hex2bin($productId1);
        }))[0];
        $changeSetForProduct2 = array_values(array_filter($changeSets, function (ChangeSet $changeSet) use (&$productId2) {
            return $changeSet->getBefore('id') === hex2bin($productId2);
        }))[0];

        static::assertNotNull($changeSetForProduct1);
        static::assertTrue($changeSetForProduct1->hasChanged('stock'));
        static::assertEquals(1, $changeSetForProduct1->getBefore('stock'));
        static::assertEquals(100, $changeSetForProduct1->getAfter('stock'));

        static::assertNotNull($changeSetForProduct2);
        static::assertTrue($changeSetForProduct2->hasChanged('stock'));
        static::assertEquals(1, $changeSetForProduct2->getBefore('stock'));
        static::assertEquals(50, $changeSetForProduct2->getAfter('stock'));
    }

    public function testCustomFieldsMergeWithIntegers(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'customFields' => ['a' => 1234],
        ];

        /** @var EntityRepository<CountryCollection> $countryRepo */
        $countryRepo = $this->getContainer()->get('country.repository');

        $countryRepo->upsert([$data], Context::createDefaultContext());

        $data['customFields']['b'] = 1;
        $data['customFields']['c'] = 1.56;
        $data['customFields']['d'] = true;
        $data['customFields']['e'] = ['a'];
        $data['customFields']['f'] = new \stdClass();
        $data['customFields']['g'] = 'test';

        $countryRepo->upsert([$data], Context::createDefaultContext());

        $country = $countryRepo
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(CountryEntity::class, $country);

        $customFields = $country->getCustomFields();
        static::assertNotNull($customFields);

        static::assertIsInt($customFields['a']);
        static::assertIsInt($customFields['b']);
        static::assertSame(1234, $customFields['a']);
        static::assertSame(1, $customFields['b']);

        static::assertIsFloat($customFields['c']);
        static::assertSame(1.56, $customFields['c']);

        static::assertIsBool($customFields['d']);
        static::assertTrue($customFields['d']);

        static::assertIsArray($customFields['e']);
        static::assertSame(['a'], $customFields['e']);

        static::assertIsArray($customFields['f']);
        static::assertEmpty($customFields['f']);
        static::assertSame($customFields['g'], 'test');
    }

    public function testEntityDeleteEventNotDispatched(): void
    {
        $id = $this->ids->get('product');

        $update = ['id' => $id, 'stock' => 1];

        $spy = $this->eventListenerCalledSpy();

        $this->getContainer()->get('event_dispatcher')->addListener(EntityDeleteEvent::class, $spy);

        $this->productRepository->update([$update], Context::createDefaultContext());

        static::assertNull($spy->event);
    }

    public function testEntityDeleteEventDispatched(): void
    {
        $id1 = $this->ids->get('product');
        $id2 = $this->ids->get('product-2');

        $delete = [['id' => $id1], ['id' => $id2]];

        $spy = $this->eventListenerCalledSpy();

        $this->getContainer()->get('event_dispatcher')->addListener(EntityDeleteEvent::class, $spy);

        $this->productRepository->delete($delete, Context::createDefaultContext());

        /** @var EntityDeleteEvent $event */
        $event = $spy->event;
        static::assertInstanceOf(EntityDeleteEvent::class, $event);
        static::assertTrue($event->filled());
        static::assertEquals([$id1, $id2], $event->getIds('product'));
    }

    public function testEntityDeleteEventSuccessCallbacksCalled(): void
    {
        $id1 = $this->ids->get('product');

        $delete = [['id' => $id1]];

        $successSpy = $this->callbackSpy();
        $errorSpy = $this->callbackSpy();

        $spy = $this->eventListenerCalledSpy(function (EntityDeleteEvent $event) use ($successSpy, $errorSpy): void {
            $event->addSuccess($successSpy(...));
            $event->addError($errorSpy(...));
        });

        $this->getContainer()->get('event_dispatcher')->addListener(EntityDeleteEvent::class, $spy);

        $this->productRepository->delete($delete, Context::createDefaultContext());

        static::assertTrue($successSpy->called);
        static::assertFalse($errorSpy->called);

        $this->getContainer()->get('event_dispatcher')->removeListener(EntityDeleteEvent::class, $spy);
    }

    public function testMultipleCallbacksAreCalledOnTheSameEntityDeleteEvent(): void
    {
        $id = $this->ids->get('product');

        $delete = [['id' => $id]];

        $successSpy1 = $this->callbackSpy();
        $successSpy2 = $this->callbackSpy();

        $eventSpy1 = $this->eventListenerCalledSpy(fn (EntityDeleteEvent $event) => $event->addSuccess($successSpy1(...)));
        $eventSpy2 = $this->eventListenerCalledSpy(fn (EntityDeleteEvent $event) => $event->addSuccess($successSpy2(...)));

        $this->getContainer()->get('event_dispatcher')->addListener(EntityDeleteEvent::class, $eventSpy1);
        $this->getContainer()->get('event_dispatcher')->addListener(EntityDeleteEvent::class, $eventSpy2);

        $this->productRepository->delete($delete, Context::createDefaultContext());

        static::assertTrue($successSpy1->called);
        static::assertTrue($successSpy2->called);

        $this->getContainer()->get('event_dispatcher')->removeListener(EntityDeleteEvent::class, $eventSpy1);
        $this->getContainer()->get('event_dispatcher')->removeListener(EntityDeleteEvent::class, $eventSpy2);
    }

    public function testEntityDeleteEventErrorCallbacksCalled(): void
    {
        $delete = [['id' => Uuid::randomBytes(), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]];

        $connection = $this->getContainer()->get(Connection::class);

        $connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([
                array_merge(
                    $connection->getParams(),
                    [
                        'url' => $_SERVER['DATABASE_URL'],
                        'dbname' => $connection->getDatabase(),
                    ]
                ),
                $connection->getDriver(),
                $connection->getConfiguration(),
            ])
            ->onlyMethods(['delete'])
            ->getMock();

        $connection->method('delete')->willThrowException(new Exception('test'));

        $successSpy = $this->callbackSpy();
        $errorSpy = $this->callbackSpy();

        $spy = $this->eventListenerCalledSpy(function (EntityDeleteEvent $event) use ($successSpy, $errorSpy): void {
            $event->addSuccess($successSpy(...));
            $event->addError($errorSpy(...));
        });

        $this->getContainer()->get('event_dispatcher')->addListener(EntityDeleteEvent::class, $spy);

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        $gateway = new EntityWriteGateway(
            1,
            $connection,
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(ExceptionHandlerRegistry::class),
            $definitionRegistry
        );

        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());

        $command = new DeleteCommand(
            $definitionRegistry->getByEntityName('product'),
            $delete[0],
            new EntityExistence('product', $delete[0], true, true, true, [])
        );

        $exceptionThrown = null;

        try {
            $gateway->execute([$command], $writeContext);
        } catch (Exception $exception) {
            $exceptionThrown = $exception;
        }

        static::assertInstanceOf(Exception::class, $exceptionThrown);
        static::assertEquals('test', $exceptionThrown->getMessage());

        static::assertTrue($errorSpy->called);
        static::assertFalse($successSpy->called);

        $this->getContainer()->get('event_dispatcher')->removeListener(EntityDeleteEvent::class, $spy);
    }

    /**
     * @param 'delete'|'upsert'|'update'|'create' $method
     */
    #[DataProvider('methodProvider')]
    public function testEntityWriteEventDispatched(string $method): void
    {
        $id1 = $this->ids->get('p-1');
        $id2 = $this->ids->get('p-2');

        $p1Data = $this->getProductData($id1);
        $p2Data = $this->getProductData($id2);

        if ($method !== 'create') {
            $this->productRepository->create([$p1Data, $p2Data], Context::createDefaultContext());
        }

        $ids = [['id' => $id1], ['id' => $id2]];

        $spy = $this->eventListenerCalledSpy();

        $this->getContainer()->get('event_dispatcher')->addListener(EntityWriteEvent::class, $spy);

        match ($method) {
            'delete' => $this->productRepository->delete($ids, Context::createDefaultContext()),
            'upsert' => $this->productRepository->upsert($ids, Context::createDefaultContext()),
            'update' => $this->productRepository->update($ids, Context::createDefaultContext()),
            'create' => $this->productRepository->create([$p1Data, $p2Data], Context::createDefaultContext()),
        };

        static::assertInstanceOf(EntityWriteEvent::class, $spy->event);
        static::assertEquals([$id1, $id2], $spy->event->getIds('product'));
    }

    /**
     * @return array<array<string>>
     */
    public static function methodProvider(): array
    {
        return [
            ['create'],
            ['upsert'],
            ['update'],
            ['delete'],
        ];
    }

    public function testEntityWriteEventSuccessCallbacksCalled(): void
    {
        $id1 = $this->ids->get('product');

        $delete = [['id' => $id1]];

        $successSpy = $this->callbackSpy();
        $errorSpy = $this->callbackSpy();

        $spy = $this->eventListenerCalledSpy(function (EntityWriteEvent $event) use ($successSpy, $errorSpy): void {
            $event->addSuccess($successSpy);
            $event->addError($errorSpy);
        });

        $this->getContainer()->get('event_dispatcher')->addListener(EntityWriteEvent::class, $spy);

        $this->productRepository->delete($delete, Context::createDefaultContext());

        static::assertTrue($successSpy->called);
        static::assertFalse($errorSpy->called);

        $this->getContainer()->get('event_dispatcher')->removeListener(EntityWriteEvent::class, $spy);
    }

    public function testMultipleCallbacksAreCalledOnTheSameEntityWriteEvent(): void
    {
        $id = $this->ids->get('product');

        $delete = [['id' => $id, 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]];

        $successSpy1 = $this->callbackSpy();
        $successSpy2 = $this->callbackSpy();

        $eventSpy1 = $this->eventListenerCalledSpy(fn (EntityWriteEvent $event) => $event->addSuccess($successSpy1));
        $eventSpy2 = $this->eventListenerCalledSpy(fn (EntityWriteEvent $event) => $event->addSuccess($successSpy2));

        $this->getContainer()->get('event_dispatcher')->addListener(EntityWriteEvent::class, $eventSpy1);
        $this->getContainer()->get('event_dispatcher')->addListener(EntityWriteEvent::class, $eventSpy2);

        $this->productRepository->delete($delete, Context::createDefaultContext());

        static::assertTrue($successSpy1->called);
        static::assertTrue($successSpy2->called);

        $this->getContainer()->get('event_dispatcher')->removeListener(EntityWriteEvent::class, $eventSpy1);
        $this->getContainer()->get('event_dispatcher')->removeListener(EntityWriteEvent::class, $eventSpy2);
    }

    public function testEntityWriteEventErrorCallbacksCalled(): void
    {
        $delete = [['id' => Uuid::randomBytes(), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]];

        $connection = $this->getContainer()->get(Connection::class);

        $connection = $this->getMockBuilder(Connection::class)
            ->setConstructorArgs([
                array_merge(
                    $connection->getParams(),
                    [
                        'url' => $_SERVER['DATABASE_URL'],
                        'dbname' => $connection->getDatabase(),
                    ]
                ),
                $connection->getDriver(),
                $connection->getConfiguration(),
            ])
            ->onlyMethods(['delete'])
            ->getMock();

        $connection->method('delete')->willThrowException(new Exception('test'));

        $successSpy = $this->callbackSpy();
        $errorSpy = $this->callbackSpy();

        $spy = $this->eventListenerCalledSpy(function (EntityWriteEvent $event) use ($successSpy, $errorSpy): void {
            $event->addSuccess($successSpy);
            $event->addError($errorSpy);
        });

        $this->getContainer()->get('event_dispatcher')->addListener(EntityWriteEvent::class, $spy);

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        $gateway = new EntityWriteGateway(
            1,
            $connection,
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(ExceptionHandlerRegistry::class),
            $definitionRegistry
        );

        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());

        $command = new DeleteCommand(
            $definitionRegistry->getByEntityName('product'),
            $delete[0],
            new EntityExistence('product', $delete[0], true, true, true, [])
        );

        $exceptionThrown = null;

        try {
            $gateway->execute([$command], $writeContext);
        } catch (Exception $exception) {
            $exceptionThrown = $exception;
        }

        static::assertInstanceOf(Exception::class, $exceptionThrown);
        static::assertEquals('test', $exceptionThrown->getMessage());

        static::assertTrue($errorSpy->called);
        static::assertFalse($successSpy->called);

        $this->getContainer()->get('event_dispatcher')->removeListener(EntityWriteEvent::class, $spy);
    }

    /**
     * @return callable(mixed...): void&object{called: bool}
     */
    private function callbackSpy(): callable
    {
        return new class() {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };
    }

    /**
     * @return callable(ShopwareEvent): void&object{event: ?ShopwareEvent}
     */
    private function eventListenerCalledSpy(?\Closure $callback = null): callable
    {
        return new class($callback) {
            public ?ShopwareEvent $event = null;

            public function __construct(private ?\Closure $callback = null)
            {
            }

            public function __invoke(ShopwareEvent $event): void
            {
                $this->event = $event;

                if ($this->callback instanceof \Closure) {
                    ($this->callback)($event);
                }
            }
        };
    }

    private function createProduct(string $id): void
    {
        $this->productRepository->create([$this->getProductData($id)], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function getProductData(?string $id = null): array
    {
        $id ??= Uuid::randomHex();

        return [
            'id' => $id,
            'name' => 'test',
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'test'],
            ],
            'visibilities' => [
                ['id' => $id, 'salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
    }

    private function getChangeSet(string $entity, EntityWrittenContainerEvent $result): ChangeSet
    {
        $event = $result->getEventByEntityName($entity);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(1, $event->getWriteResults());

        $changeSet = $event->getWriteResults()[0]->getChangeSet();
        static::assertInstanceOf(ChangeSet::class, $changeSet);

        return $changeSet;
    }

    /**
     * @return array<ChangeSet>
     */
    private function getChangeSets(string $entity, EntityWrittenContainerEvent $result, int $expectedSize): array
    {
        $event = $result->getEventByEntityName($entity);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount($expectedSize, $event->getWriteResults());

        return array_map(function (EntityWriteResult $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            static::assertInstanceOf(ChangeSet::class, $changeSet);

            return $changeSet;
        }, $event->getWriteResults());
    }
}
