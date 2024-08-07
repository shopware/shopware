<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\IterateEntitiesQueryBuilder;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessageHandler;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @internal
 */
#[Package('data-services')]
class IterateEntityMessageHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected function setUp(): void
    {
        /** @var MockHttpClient $client */
        $client = $this->getContainer()->get('shopware.usage_data.gateway.client');
        $client->setResponseFactory(function (string $method, string $url): ResponseInterface {
            if (\str_ends_with($url, '/killswitch')) {
                $body = json_encode(['killswitch' => false]);
                static::assertIsString($body);

                return new MockResponse($body);
            }

            return new MockResponse();
        });
    }

    public function testItFetchesEverythingIfLastRunIsNotSet(): void
    {
        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, ConsentState::ACCEPTED->value);

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        $entityDefinitionService = $this->createMock(EntityDefinitionService::class);
        $entityDefinitionService->expects(static::any())
            ->method('getAllowedEntityDefinition')
            ->with('product')
            ->willReturn($definitionRegistry->get(ProductDefinition::class));

        $productIds = $this->setUpProducts();

        $messageBus = new CollectingMessageBus();

        $messageHandler = new IterateEntityMessageHandler(
            $messageBus,
            new IterateEntitiesQueryBuilder(
                $entityDefinitionService,
                $this->getContainer()->get(Connection::class),
                $this->getContainer()->getParameter('shopware.usage_data.gateway.batch_size'),
            ),
            $this->getContainer()->get(ConsentService::class),
            $entityDefinitionService,
            $this->getContainer()->get(LoggerInterface::class),
        );

        $messageHandler(new IterateEntityMessage(
            'product',
            Operation::CREATE,
            new \DateTimeImmutable('2023-08-16'),
            null,
        ));

        $dispatchedMessages = $messageBus->getMessages();

        static::assertNotEmpty($dispatchedMessages);

        $entitySyncMessage = $dispatchedMessages[0]->getMessage();

        static::assertInstanceOf(DispatchEntityMessage::class, $entitySyncMessage);

        static::assertEquals('product', $entitySyncMessage->entityName);
        static::assertEquals([
            ['id' => $productIds->get('product-from-the-past')],
            ['id' => $productIds->get('product-created-on-last-run-date')],
            ['id' => $productIds->get('product-created-today')],
            ['id' => $productIds->get('product-updated-today')],
        ], array_values($entitySyncMessage->primaryKeys));
    }

    public function testItFetchesOnlyNewChangesIfLastRunIsSet(): void
    {
        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, ConsentState::ACCEPTED->value);

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        $entityDefinitionService = $this->createMock(EntityDefinitionService::class);
        $entityDefinitionService->expects(static::any())
            ->method('getAllowedEntityDefinition')
            ->with('product')
            ->willReturn($definitionRegistry->get(ProductDefinition::class));

        $productIds = $this->setUpProducts();

        $messageBus = new CollectingMessageBus();

        $messageHandler = new IterateEntityMessageHandler(
            $messageBus,
            new IterateEntitiesQueryBuilder(
                $entityDefinitionService,
                $this->getContainer()->get(Connection::class),
                $this->getContainer()->getParameter('shopware.usage_data.gateway.batch_size'),
            ),
            $this->getContainer()->get(ConsentService::class),
            $entityDefinitionService,
            $this->getContainer()->get(LoggerInterface::class),
        );

        $messageHandler(new IterateEntityMessage(
            'product',
            Operation::CREATE,
            new \DateTimeImmutable('2023-08-16'),
            new \DateTimeImmutable('2023-08-01'),
        ));

        $dispatchedMessages = $messageBus->getMessages();

        static::assertNotEmpty($dispatchedMessages);

        $entitySyncMessage = $dispatchedMessages[0]->getMessage();

        static::assertInstanceOf(DispatchEntityMessage::class, $entitySyncMessage);

        static::assertEquals('product', $entitySyncMessage->entityName);
        static::assertEquals([
            ['id' => $productIds->get('product-created-on-last-run-date')],
            ['id' => $productIds->get('product-created-today')],
        ], array_values($entitySyncMessage->primaryKeys));
    }

    public function testItFetchesOnlyDeletionsUpToTheCurrentRunDate(): void
    {
        /** @var SystemConfigService $systemConfigService */
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, ConsentState::ACCEPTED->value);
        // trigger an update
        $systemConfigService->set(ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE, ConsentState::ACCEPTED->value);

        $definitionRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        $entityDefinitionService = $this->createMock(EntityDefinitionService::class);
        $entityDefinitionService->expects(static::any())
            ->method('getAllowedEntityDefinition')
            ->with('product')
            ->willReturn($definitionRegistry->get(ProductDefinition::class));

        $ids = new IdsCollection();

        $this->insertProductDeletion($ids->get('product-from-the-past'), (new \DateTimeImmutable())->sub(new \DateInterval('P1D')));
        $this->insertProductDeletion($ids->get('product-from-the-future'), (new \DateTimeImmutable())->add(new \DateInterval('P1D')));

        $messageBus = new CollectingMessageBus();

        $messageHandler = new IterateEntityMessageHandler(
            $messageBus,
            new IterateEntitiesQueryBuilder(
                $entityDefinitionService,
                $this->getContainer()->get(Connection::class),
                $this->getContainer()->getParameter('shopware.usage_data.gateway.batch_size'),
            ),
            $this->getContainer()->get(ConsentService::class),
            $entityDefinitionService,
            $this->getContainer()->get(LoggerInterface::class),
        );

        $messageHandler(new IterateEntityMessage(
            'product',
            Operation::DELETE,
            new \DateTimeImmutable(),
            new \DateTimeImmutable('2023-08-01'),
        ));

        $dispatchedMessages = $messageBus->getMessages();

        static::assertNotEmpty($dispatchedMessages);

        $entitySyncMessage = $dispatchedMessages[0]->getMessage();

        static::assertInstanceOf(DispatchEntityMessage::class, $entitySyncMessage);
        static::assertEquals(
            [
                ['id' => $ids->get('product-from-the-past')],
            ],
            $entitySyncMessage->primaryKeys,
        );
    }

    public function testItLogsExceptionWithTableDoesNotExistExceptionIsThrown(): void
    {
        $logger = $this->createMock(Logger::class);
        $logger->expects(static::once())->method('error');

        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('getLastConsentIsAcceptedDate')
            ->willReturn(new \DateTimeImmutable());

        $entityDefinitionService = $this->createMock(EntityDefinitionService::class);
        $entityDefinitionService->expects(static::once())
            ->method('getAllowedEntityDefinition')
            ->with('test_entity')
            ->willReturn(new TestEntityDefinition());

        $messageHandler = new IterateEntityMessageHandler(
            new CollectingMessageBus(),
            $this->getContainer()->get(IterateEntitiesQueryBuilder::class),
            $consentService,
            $entityDefinitionService,
            $logger,
        );

        /** @var DefinitionInstanceRegistry $registry */
        $registry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        $registry->register(new TestEntityDefinition());

        $messageHandler(new IterateEntityMessage(
            TestEntityDefinition::ENTITY_NAME,
            Operation::CREATE,
            new \DateTimeImmutable('2023-08-16'),
            new \DateTimeImmutable('2023-08-01'),
        ));
    }

    private function setUpProducts(): IdsCollection
    {
        $ids = new IdsCollection();

        $products = [
            (new ProductBuilder($ids, 'product-from-the-past'))
                ->price(2.00)
                ->build(),
            (new ProductBuilder($ids, 'product-created-on-last-run-date'))
                ->price(2.00)
                ->build(),
            (new ProductBuilder($ids, 'product-created-today'))
                ->price(2.00)
                ->build(),
            (new ProductBuilder($ids, 'product-updated-today'))
                ->price(2.00)
                ->build(),
        ];

        $this->getContainer()->get('product.repository')->upsert($products, Context::createDefaultContext());

        $connection = $this->getContainer()->get(Connection::class);

        static::assertEquals(1, $connection->update(
            '`product`',
            ['`created_at`' => '2023-05-24', '`updated_at`' => null],
            ['`product_number`' => 'product-from-the-past'],
        ));

        static::assertEquals(1, $connection->update(
            '`product`',
            ['`created_at`' => '2023-08-02', '`updated_at`' => null],
            ['`product_number`' => 'product-created-on-last-run-date'],
        ));

        static::assertEquals(1, $connection->update(
            '`product`',
            ['`created_at`' => '2023-08-03', '`updated_at`' => null],
            ['`product_number`' => 'product-created-today'],
        ));

        static::assertEquals(1, $connection->update(
            '`product`',
            ['`created_at`' => '2022-08-02', '`updated_at`' => '2023-08-02'],
            ['`product_number`' => 'product-updated-today'],
        ));

        return $ids;
    }

    private function insertProductDeletion(string $id, \DateTimeImmutable $deletedAt): void
    {
        /** @var Connection $connection */
        $connection = $this->getContainer()->get(Connection::class);
        $qb = $connection->createQueryBuilder();
        $qb->insert('usage_data_entity_deletion');
        $qb->values([
            'id' => ':id',
            'entity_name' => ':entity_name',
            'entity_ids' => ':entity_ids',
            'deleted_at' => ':deleted_at',
        ]);
        $statement = $connection->prepare($qb->getSQL());
        $statement->bindValue(':id', Uuid::fromHexToBytes($id), ParameterType::BINARY);
        $statement->bindValue(':entity_name', 'product');
        $statement->bindValue(':entity_ids', \json_encode(Uuid::randomHex(), \JSON_THROW_ON_ERROR));

        // this deletion is in the future
        $statement->bindValue(':deleted_at', $deletedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT));

        $statement->executeStatement();
    }
}

/**
 * @internal
 */
class TestEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'test_entity';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): string
    {
        return 'test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
        ]);
    }
}
