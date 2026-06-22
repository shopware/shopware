<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentScope;
use Shopware\Core\System\Consent\ConsentStatus;
use Shopware\Core\System\Consent\Definition\BackendData;
use Shopware\Core\System\Consent\DTO\ConsentState;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\UsageData\EntitySync\CollectEntityDataMessage;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Core\System\UsageData\Services\GatewayStatusService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\System\UsageData\Services\UsageDataAllowListService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(EntityDispatchService::class)]
class EntityDispatchServiceTest extends TestCase
{
    private DefinitionInstanceRegistry $registry;

    private ShopIdProvider $shopIdProvider;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [new ProductDefinition(), new SalesChannelDefinition(), new RuleTagDefinition()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);
        $this->shopIdProvider->method('getShopId')->willReturn('current-shop-id');
    }

    public function testItReturnsCorrectAppConfigKey(): void
    {
        static::assertSame(
            'usageData-entitySync-lastRun-sales_channel',
            EntityDispatchService::getLastRunKeyForEntity('sales_channel')
        );
    }

    public function testItDispatchesCollectEntityDataMessage(): void
    {
        $messageBus = new CollectingMessageBus();

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage(),
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-03'),
            true,
        );

        $entityDispatchService->dispatchCollectEntityDataMessage();

        $messages = $messageBus->getMessages();
        static::assertCount(1, $messages);
        static::assertEquals(new CollectEntityDataMessage('current-shop-id'), $messages[0]->getMessage());
    }

    public function testItDoesNotDispatchesCollectEntityDataMessageIfConsentIsNotGiven(): void
    {
        $messageBus = new CollectingMessageBus();

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage(),
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::UNSET, null),
            true,
        );

        $entityDispatchService->dispatchCollectEntityDataMessage();

        $messages = $messageBus->getMessages();
        static::assertCount(0, $messages);
    }

    public function testItDoesNotDispatchesCollectEntityDataMessageIfCollectionIsDisabled(): void
    {
        $messageBus = new CollectingMessageBus();

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage(),
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-03'),
            false,
        );

        $entityDispatchService->dispatchCollectEntityDataMessage();

        $messages = $messageBus->getMessages();
        static::assertCount(0, $messages);
    }

    public function testItStoresTheCorrectLastRunDateForEachEntity(): void
    {
        $beforeDispatch = new \DateTimeImmutable();

        $appConfig = new ArrayKeyValueStorage([]);
        $messageBus = new CollectingMessageBus();
        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            $appConfig,
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-03'),
            true,
        );

        static::assertNull($appConfig->get('usageData-entitySync-lastRun-product'));
        static::assertNull($appConfig->get('usageData-entitySync-lastRun-sales_channel'));

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();

        $productMessage = $messages[0]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $productMessage);

        $salesChannelMessage = $messages[1]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $salesChannelMessage);

        $afterDispatch = new \DateTimeImmutable();
        $productRunDate = $appConfig->get('usageData-entitySync-lastRun-product');
        $salesChannelRunDate = $appConfig->get('usageData-entitySync-lastRun-sales_channel');

        static::assertIsString($productRunDate);
        static::assertIsString($salesChannelRunDate);

        $productRunDateTime = new \DateTimeImmutable($productRunDate);
        static::assertGreaterThanOrEqual($beforeDispatch->getTimestamp(), $productRunDateTime->getTimestamp());
        static::assertLessThanOrEqual($afterDispatch->getTimestamp(), $productRunDateTime->getTimestamp());

        $salesChannelRunDateTime = new \DateTimeImmutable($salesChannelRunDate);
        static::assertGreaterThanOrEqual($beforeDispatch->getTimestamp(), $salesChannelRunDateTime->getTimestamp());
        static::assertLessThanOrEqual($afterDispatch->getTimestamp(), $salesChannelRunDateTime->getTimestamp());
    }

    #[DataProvider('lastRunDateProvider')]
    public function testItStoresCorrectLastRunDate(bool $isConsentGiven, ?\DateTimeImmutable $lastConsentDate, \DateTimeImmutable $now, ?\DateTimeImmutable $expectedLastRunDate): void
    {
        $systemConfigService = new StaticSystemConfigService([]);
        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('getConsentState')->willReturn(new ConsentState(
            BackendData::NAME,
            'system',
            'system',
            $isConsentGiven ? ConsentStatus::ACCEPTED : ($lastConsentDate === null ? ConsentStatus::DECLINED : ConsentStatus::REVOKED),
            'admin',
            $lastConsentDate?->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        ));

        $appConfig = new ArrayKeyValueStorage([]);
        $messageBus = new CollectingMessageBus();
        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            $appConfig,
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            $systemConfigService,
            $consentService,
            true,
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $storedRunDate = $systemConfigService->get('core.usageData.lastEntitySyncRunDate');
        if (!$isConsentGiven) {
            static::assertSame(
                $expectedLastRunDate?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                $storedRunDate,
            );

            return;
        }

        static::assertIsString($storedRunDate);
        $storedRunDateTime = new \DateTimeImmutable($storedRunDate);
        $after = new \DateTimeImmutable();

        static::assertGreaterThanOrEqual($now->getTimestamp(), $storedRunDateTime->getTimestamp());
        static::assertLessThanOrEqual($after->getTimestamp(), $storedRunDateTime->getTimestamp());
    }

    public function testItDoesNotStartMultipleRuns(): void
    {
        $lastConsentDate = new \DateTimeImmutable('2023-07-25T07:00:19.803422+0000');
        $now = new \DateTimeImmutable();

        $appConfig = new ArrayKeyValueStorage([]);
        $messageBus = new CollectingMessageBus();
        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            $appConfig,
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-03'),
            true,
        );

        // first run
        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        $messageCountFirstRun = \count($messages);
        static::assertSame(2, $messageCountFirstRun);

        // second run --> should not start another run as the time has not changed
        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        // expect to still have two messages and not more than before
        static::assertCount($messageCountFirstRun, $messages);
    }

    public function testItCanStartSecondRunAfterGivenAmountOfTime(): void
    {
        $lastConsentDate = new \DateTimeImmutable('2023-07-25T07:00:19.803422+0000');

        $consentService = $this->createMock(ConsentService::class);
        $consentService->method('getConsentState')->willReturnOnConsecutiveCalls(
            $this->createConsentState(ConsentStatus::REVOKED, $lastConsentDate),
            $this->createConsentState(ConsentStatus::REVOKED, $lastConsentDate->modify('+8 hours')), // should not start new run
            $this->createConsentState(ConsentStatus::REVOKED, $lastConsentDate->modify('+1 day')), // should start new run
        );

        $appConfig = new ArrayKeyValueStorage([]);
        $messageBus = new CollectingMessageBus();
        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            $appConfig,
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $consentService,
            true,
        );

        // first run
        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        // 1 create message
        static::assertCount(1, $messages);

        // second run --> should not start another run as the timeframe for collecting is only 8 hours (12 required)
        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        // 1 create message
        static::assertCount(1, $messages);

        // third run --> should start a new run as the timeframe for collecting is at least 12 hours
        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        // 2 create (one from the first run, one from the second run), 1 update, 1 delete message
        static::assertCount(4, $messages);
    }

    public function testItSchedulesIterateMessagesForEveryEntity(): void
    {
        $now = new \DateTimeImmutable();
        $messageBus = new CollectingMessageBus();

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage(),
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-03'),
            true,
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        static::assertCount(2, $messages);

        $productMessage = $messages[0]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $productMessage);

        static::assertSame('product', $productMessage->entityName);
        static::assertNull($productMessage->lastRun);
        static::assertGreaterThanOrEqual($now->getTimestamp(), $productMessage->runDate->getTimestamp());

        $salesChannelMessage = $messages[1]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $salesChannelMessage);

        static::assertSame('sales_channel', $salesChannelMessage->entityName);
        static::assertNull($salesChannelMessage->lastRun);
        static::assertSame($productMessage->runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT), $salesChannelMessage->runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }

    public function testItAddsLastRunDateIfExists(): void
    {
        $lastScRunDatetime = new \DateTimeImmutable('2023-07-25T07:00:19.803422+0000');
        $messageBus = new CollectingMessageBus();
        $now = new \DateTimeImmutable();

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage([
                'usageData-entitySync-lastRun-sales_channel' => $lastScRunDatetime->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]),
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-03'),
            true,
        );
        $storedScLastRunDatetime = new \DateTimeImmutable($lastScRunDatetime->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        static::assertCount(4, $messages);

        $productMessage = $messages[0]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $productMessage);

        static::assertSame('product', $productMessage->entityName);
        static::assertNull($productMessage->lastRun);
        static::assertGreaterThanOrEqual($now->getTimestamp(), $productMessage->runDate->getTimestamp());

        $salesChannelMessage = $messages[1]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $salesChannelMessage);

        static::assertSame('sales_channel', $salesChannelMessage->entityName);
        static::assertSame($storedScLastRunDatetime->format(Defaults::STORAGE_DATE_TIME_FORMAT), $salesChannelMessage->lastRun?->format(Defaults::STORAGE_DATE_TIME_FORMAT));
        static::assertSame($productMessage->runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT), $salesChannelMessage->runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT));
    }

    public function testReturnsEarlyIfGatewayDoesNotAllowPush(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method('dispatch');

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage(),
            $messageBusMock,
            $this->createGatewayStatusService(false),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-03'),
            true,
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));
    }

    public function testReturnsEarlyIfNoEntitiesAreRegistered(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method('dispatch');

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService([], new UsageDataAllowListService()),
            new ArrayKeyValueStorage(),
            $messageBusMock,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-02'),
            true,
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));
    }

    public function testReturnsEarlyIfNoConsentIsGiven(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method('dispatch');

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage(),
            $messageBusMock,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::UNSET, null),
            true,
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));
    }

    public function testItReturnsEarlyIfCollectEntityMessageHasDifferentShopId(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method('dispatch');

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage(),
            $messageBusMock,
            $this->createGatewayStatusService(false),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-02'),
            true,
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('old-shop-id'));
    }

    public function testItSchedulesCreateOperationIterateMessagesInTheFirstRun(): void
    {
        $messageBus = new CollectingMessageBus();

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage(),
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-02'),
            true,
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        static::assertCount(2, $messages);

        foreach ($messages as $envelope) {
            $message = $envelope->getMessage();
            static::assertInstanceOf(IterateEntityMessage::class, $message);
            static::assertNull($message->lastRun);
            static::assertSame(Operation::CREATE, $message->operation);
        }
    }

    public function testItSkipsAssociations(): void
    {
        $messageBus = new CollectingMessageBus();

        $ruleTagRunKey = EntityDispatchService::getLastRunKeyForEntity(RuleTagDefinition::ENTITY_NAME);

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [$this->registry->get(RuleTagDefinition::class)],
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage([
                $ruleTagRunKey => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]),
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-02'),
            true,
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();

        // association should be skipped
        static::assertCount(0, $messages);
    }

    public function testItSchedulesCorrectOperationIterateMessages(): void
    {
        $messageBus = new CollectingMessageBus();
        $entityDefinitions = [
            $this->registry->get(ProductDefinition::class),
            $this->registry->get(SalesChannelDefinition::class),
            $this->registry->get(RuleTagDefinition::class),
        ];

        $productRunKey = EntityDispatchService::getLastRunKeyForEntity(ProductDefinition::ENTITY_NAME);
        $salesChannelRunKey = EntityDispatchService::getLastRunKeyForEntity(SalesChannelDefinition::ENTITY_NAME);
        $ruleTagRunKey = EntityDispatchService::getLastRunKeyForEntity(RuleTagDefinition::ENTITY_NAME);

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                $entityDefinitions,
                new UsageDataAllowListService(),
            ),
            new ArrayKeyValueStorage([
                $productRunKey => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                $salesChannelRunKey => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                $ruleTagRunKey => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]),
            $messageBus,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-02'),
            true,
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();

        $expectedMessages = [
            ProductDefinition::ENTITY_NAME => [
                Operation::CREATE->value => 1,
                Operation::UPDATE->value => 1,
                Operation::DELETE->value => 1,
            ],
            SalesChannelDefinition::ENTITY_NAME => [
                Operation::CREATE->value => 1,
                Operation::UPDATE->value => 1,
                Operation::DELETE->value => 1,
            ],
            // this one will be skipped because it has no createdAt and updatedAt fields
            RuleTagDefinition::ENTITY_NAME => [
                Operation::CREATE->value => 0,
                Operation::UPDATE->value => 0,
                Operation::DELETE->value => 0,
            ],
        ];
        $foundMessages = [
            ProductDefinition::ENTITY_NAME => [
                Operation::CREATE->value => 0,
                Operation::UPDATE->value => 0,
                Operation::DELETE->value => 0,
            ],
            SalesChannelDefinition::ENTITY_NAME => [
                Operation::CREATE->value => 0,
                Operation::UPDATE->value => 0,
                Operation::DELETE->value => 0,
            ],
            RuleTagDefinition::ENTITY_NAME => [
                Operation::CREATE->value => 0,
                Operation::UPDATE->value => 0,
                Operation::DELETE->value => 0,
            ],
        ];

        foreach ($messages as $envelope) {
            $message = $envelope->getMessage();
            static::assertInstanceOf(IterateEntityMessage::class, $message);
            static::assertNotNull($message->lastRun);
            ++$foundMessages[$message->entityName][$message->operation->value];
        }

        static::assertSame($expectedMessages, $foundMessages);
    }

    public function testResetLastRunDateForAllEntities(): void
    {
        $productRunKey = EntityDispatchService::getLastRunKeyForEntity(ProductDefinition::ENTITY_NAME);
        $salesChannelRunKey = EntityDispatchService::getLastRunKeyForEntity(SalesChannelDefinition::ENTITY_NAME);
        $ruleTagRunKey = EntityDispatchService::getLastRunKeyForEntity(RuleTagDefinition::ENTITY_NAME);

        $appConfig = new ArrayKeyValueStorage([
            $productRunKey => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $salesChannelRunKey => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $ruleTagRunKey => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService(
                [
                    $this->registry->get(ProductDefinition::class),
                    $this->registry->get(SalesChannelDefinition::class),
                ],
                new UsageDataAllowListService(),
            ),
            $appConfig,
            new CollectingMessageBus(),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
            $this->createConsentService(ConsentStatus::ACCEPTED, '2026-03-02'),
            true,
        );

        $entityDispatchService->resetLastRunDateForAllEntities();

        static::assertNull($appConfig->get($productRunKey));
        static::assertNull($appConfig->get($salesChannelRunKey));

        // definition is not given --> should not be null
        static::assertNotNull($appConfig->get($ruleTagRunKey));
    }

    /**
     * @return iterable<string, array{isConsentGiven: bool, lastConsentDate: ?\DateTimeImmutable, now: ?\DateTimeImmutable, expectedLastRunDate: ?\DateTimeImmutable}>
     */
    public static function lastRunDateProvider(): iterable
    {
        $now = new \DateTimeImmutable();
        $lastConsentDate = new \DateTimeImmutable('2023-07-25T07:00:19.803422+0000');

        yield 'Consent was never given' => [
            'isConsentGiven' => false,
            'lastConsentDate' => null,
            'now' => $now,
            'expectedLastRunDate' => null,
        ];
        yield 'Consent was revoked' => [
            'isConsentGiven' => false,
            'lastConsentDate' => $lastConsentDate,
            'now' => $now,
            'expectedLastRunDate' => $lastConsentDate,
        ];
        yield 'Consent is given and was never revoked before' => [
            'isConsentGiven' => true,
            'lastConsentDate' => null,
            'now' => $now,
            'expectedLastRunDate' => $now,
        ];
        yield 'Consent is given but was revoked in the past' => [
            'isConsentGiven' => true,
            'lastConsentDate' => $lastConsentDate,
            'now' => $now,
            'expectedLastRunDate' => $now,
        ];
    }

    private function createConsentService(ConsentStatus $consentStatus, ?string $updatedAt): ConsentService
    {
        $service = $this->createMock(ConsentService::class);
        $service->method('getConsentState')->with(BackendData::NAME)->willReturn(new ConsentState(
            BackendData::NAME,
            'system',
            'system',
            $consentStatus,
            'admin',
            $updatedAt
        ));

        return $service;
    }

    private function createConsentState(ConsentStatus $status, ?\DateTimeImmutable $updatedAt): ConsentState
    {
        return new ConsentState(
            BackendData::NAME,
            ConsentScope\System::NAME,
            ConsentScope\System::NAME,
            $status,
            'actor',
            $updatedAt?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        );
    }

    private function createGatewayStatusService(bool $isAcceptingEntities): GatewayStatusService&MockObject
    {
        $service = $this->createMock(GatewayStatusService::class);
        $service->method('isGatewayAllowsPush')->willReturn($isAcceptingEntities);

        return $service;
    }
}
