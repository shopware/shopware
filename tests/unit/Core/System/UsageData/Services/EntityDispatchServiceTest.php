<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigEntity;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\EntitySync\CollectEntityDataMessage;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\Services\EntityDispatchService;
use Shopware\Core\System\UsageData\Services\GatewayStatusService;
use Shopware\Core\System\UsageData\Services\ShopIdProvider;
use Shopware\Core\System\UsageData\Services\UsageDataAllowListService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\System\UsageData\Services\EntityDispatchService
 */
#[Package('data-services')]
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
        static::assertEquals(
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
            $this->createConsentService(true, null),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
        );

        $entityDispatchService->dispatchCollectEntityDataMessage();

        $messages = $messageBus->getMessages();
        static::assertCount(1, $messages);
        static::assertEquals(new CollectEntityDataMessage('current-shop-id'), $messages[0]->getMessage());
    }

    public function testItStoresTheCorrectLastRunDateForEachEntity(): void
    {
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
            $this->createConsentService(true, $now, $now),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
        );

        static::assertNull($appConfig->get('usageData-entitySync-lastRun-product'));
        static::assertNull($appConfig->get('usageData-entitySync-lastRun-sales_channel'));

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();

        $productMessage = $messages[0]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $productMessage);

        /* The message->getRunDate is not 100% equal to the one stored in the storage because
         * the last 3 decimals are lost in the formatting.
         */
        static::assertEquals(
            $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $appConfig->get('usageData-entitySync-lastRun-product'),
        );

        $salesChannelMessage = $messages[1]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $salesChannelMessage);

        static::assertEquals(
            $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $appConfig->get('usageData-entitySync-lastRun-sales_channel'),
        );
    }

    /**
     * @dataProvider lastRunDateProvider
     */
    public function testItStoresCorrectLastRunDate(bool $isConsentGiven, ?\DateTimeImmutable $lastConsentDate, \DateTimeImmutable $now, ?\DateTimeImmutable $expectedLastRunDate): void
    {
        $systemConfigService = new StaticSystemConfigService([]);

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
            $this->createConsentService($isConsentGiven, $lastConsentDate, $now),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            $systemConfigService,
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        static::assertEquals(
            $expectedLastRunDate?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $systemConfigService->get('core.usageData.lastEntitySyncRunDate'),
        );
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
            $this->createConsentService(false, $lastConsentDate, $now),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
        );

        // first run
        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        $messageCountFirstRun = \count($messages);
        static::assertEquals(2, $messageCountFirstRun);

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
        $consentService->method('getLastConsentIsAcceptedDate')->willReturnOnConsecutiveCalls(
            $lastConsentDate,
            $lastConsentDate->modify('+8 hours'), // should not start new run
            $lastConsentDate->modify('+1 day'), // should start new run
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
            $consentService,
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
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
            $this->createConsentService(true, $now, $now),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        static::assertCount(2, $messages);

        $productMessage = $messages[0]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $productMessage);

        static::assertEquals('product', $productMessage->entityName);
        static::assertNull($productMessage->lastRun);
        static::assertEquals($now, $productMessage->runDate);

        $salesChannelMessage = $messages[1]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $salesChannelMessage);

        static::assertEquals('sales_channel', $salesChannelMessage->entityName);
        static::assertNull($salesChannelMessage->lastRun);
        static::assertEquals($now, $salesChannelMessage->runDate);
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
            $this->createConsentService(true, $now, $now),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
        );
        $storedScLastRunDatetime = new \DateTimeImmutable($lastScRunDatetime->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        static::assertCount(4, $messages);

        $productMessage = $messages[0]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $productMessage);

        static::assertEquals('product', $productMessage->entityName);
        static::assertNull($productMessage->lastRun);
        static::assertEquals($now, $productMessage->runDate);

        $salesChannelMessage = $messages[1]->getMessage();
        static::assertInstanceOf(IterateEntityMessage::class, $salesChannelMessage);

        static::assertEquals('sales_channel', $salesChannelMessage->entityName);
        static::assertEquals($storedScLastRunDatetime, $salesChannelMessage->lastRun);
        static::assertEquals($now, $salesChannelMessage->runDate);
    }

    public function testReturnsEarlyIfGatewayDoesNotAllowPush(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects(static::never())->method('dispatch');

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
            $this->createConsentService(true, null),
            $this->createGatewayStatusService(false),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));
    }

    public function testReturnsEarlyIfNoEntitiesAreRegistered(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects(static::never())->method('dispatch');

        $entityDispatchService = new EntityDispatchService(
            new EntityDefinitionService([], new UsageDataAllowListService()),
            new ArrayKeyValueStorage(),
            $messageBusMock,
            $this->createConsentService(true, null),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));
    }

    public function testReturnsEarlyIfNoConsentIsGiven(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects(static::never())->method('dispatch');

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
            $this->createConsentService(false, null),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));
    }

    public function testItReturnsEarlyIfCollectEntityMessageHasDifferentShopId(): void
    {
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects(static::never())->method('dispatch');

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
            $this->createConsentService(true, null),
            $this->createGatewayStatusService(false),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
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
            $this->createConsentService(true, null),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
        );

        $entityDispatchService->dispatchIterateEntityMessages(new CollectEntityDataMessage('current-shop-id'));

        $messages = $messageBus->getMessages();
        static::assertCount(2, $messages);

        foreach ($messages as $envelope) {
            $message = $envelope->getMessage();
            static::assertInstanceOf(IterateEntityMessage::class, $message);
            static::assertNull($message->lastRun);
            static::assertEquals(Operation::CREATE, $message->operation);
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
            $this->createConsentService(true, null),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
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
            $this->createConsentService(true, null),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
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

        static::assertEquals($expectedMessages, $foundMessages);
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
            $this->createConsentService(true, null),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider,
            new StaticSystemConfigService([]),
        );

        $entityDispatchService->resetLastRunDateForAllEntities();

        static::assertNull($appConfig->get($productRunKey));
        static::assertNull($appConfig->get($salesChannelRunKey));

        // definition is not given --> should not be null
        static::assertNotNull($appConfig->get($ruleTagRunKey));
    }

    /**
     * @return array<string, array{isConsentGiven: bool, lastConsentDate: ?\DateTimeImmutable, now: ?\DateTimeImmutable, expectedLastRunDate: ?\DateTimeImmutable}>
     */
    public static function lastRunDateProvider(): array
    {
        $now = new \DateTimeImmutable();
        $lastConsentDate = new \DateTimeImmutable('2023-07-25T07:00:19.803422+0000');

        return [
            'Consent was never given' => [
                'isConsentGiven' => false,
                'lastConsentDate' => null,
                'now' => $now,
                'expectedLastRunDate' => null,
            ],
            'Consent was revoked' => [
                'isConsentGiven' => false,
                'lastConsentDate' => $lastConsentDate,
                'now' => $now,
                'expectedLastRunDate' => $lastConsentDate,
            ],
            'Consent is given and was never revoked before' => [
                'isConsentGiven' => true,
                'lastConsentDate' => null,
                'now' => $now,
                'expectedLastRunDate' => $now,
            ],
            'Consent is given but was revoked in the past' => [
                'isConsentGiven' => true,
                'lastConsentDate' => $lastConsentDate,
                'now' => $now,
                'expectedLastRunDate' => $now,
            ],
        ];
    }

    private function createConsentService(bool $isApprovalGiven, ?\DateTimeImmutable $lastConsentDate, \DateTimeImmutable $now = new \DateTimeImmutable()): ConsentService
    {
        $systemConfigEntity = new SystemConfigEntity();
        if ($lastConsentDate) {
            $systemConfigEntity->setUpdatedAt($lastConsentDate);
        }

        $entitySearchResult = $this->createMock(EntitySearchResult::class);
        $entitySearchResult->method('first')
            ->willReturn($systemConfigEntity);

        $systemConfigRepository = $this->createMock(EntityRepository::class);
        $systemConfigRepository->method('search')
            ->willReturn($entitySearchResult);

        $service = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REQUESTED->value,
                'core.usageData.lastEntitySyncRunDate' => $lastConsentDate?->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]),
            $systemConfigRepository,
            new CollectingEventDispatcher(),
            new MockClock($now),
        );

        if ($isApprovalGiven) {
            $service->acceptConsent();
        }

        return $service;
    }

    private function createGatewayStatusService(bool $isAcceptingEntities): GatewayStatusService&MockObject
    {
        $service = $this->createMock(GatewayStatusService::class);
        $service->expects(static::any())->method('isGatewayAllowsPush')->willReturn($isAcceptingEntities);

        return $service;
    }
}
