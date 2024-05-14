<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\Aggregate\RuleTag\RuleTagDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
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
        static::assertEquals(
            'usageData-entitySync-lastRun-sales_channel',
            EntityDispatchService::getLastRunKeyForEntity('sales_channel')
        );
    }

    public function testItStoresTheCorrectLastRunDate(): void
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
            new MockClock($now),
            $this->createConsentService(true),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider
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
            new MockClock($now),
            $this->createConsentService(true),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider
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
            new MockClock($now),
            $this->createConsentService(true),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider
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
            new MockClock(),
            $this->createConsentService(true),
            $this->createGatewayStatusService(false),
            $this->shopIdProvider
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
            new MockClock(),
            $this->createConsentService(true),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider
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
            new MockClock(),
            $this->createConsentService(false),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider
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
            new MockClock(),
            $this->createConsentService(true),
            $this->createGatewayStatusService(false),
            $this->shopIdProvider
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
            new MockClock(),
            $this->createConsentService(true),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider
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
            new MockClock(),
            $this->createConsentService(true),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider
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
            new MockClock(),
            $this->createConsentService(true),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider
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

    public function testItDispatchesCollectEntityDataMessage(): void
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
            new MockClock($now),
            $this->createConsentService(true),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider
        );

        $entityDispatchService->dispatchCollectEntityDataMessage();

        $messages = $messageBus->getMessages();
        static::assertCount(1, $messages);
        static::assertEquals(new CollectEntityDataMessage('current-shop-id'), $messages[0]->getMessage());
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
            new MockClock(),
            $this->createConsentService(true),
            $this->createGatewayStatusService(true),
            $this->shopIdProvider
        );

        $entityDispatchService->resetLastRunDateForAllEntities();

        static::assertNull($appConfig->get($productRunKey));
        static::assertNull($appConfig->get($salesChannelRunKey));

        // definition is not given --> should not be null
        static::assertNotNull($appConfig->get($ruleTagRunKey));
    }

    private function createConsentService(bool $isApprovalGiven): ConsentService
    {
        $service = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REQUESTED->value,
            ]),
            $this->createMock(EntityRepository::class),
            new CollectingEventDispatcher(),
            new MockClock(),
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
