<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\ConnectionException;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\IterateEntitiesQueryBuilder;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\IterateEntityMessageHandler;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(IterateEntityMessageHandler::class)]
class IterateEntityMessageHandlerTest extends TestCase
{
    public function testDispatchesNoMessageToFetchDeletedOrUpdatedEntitiesOnInitialRun(): void
    {
        $messageBus = new CollectingMessageBus();

        $iteratorFactory = $this->createMock(IterateEntitiesQueryBuilder::class);
        $iteratorFactory->expects(static::never())
            ->method('create');

        $handler = new IterateEntityMessageHandler(
            $messageBus,
            $iteratorFactory,
            $this->createMock(ConsentService::class),
            $this->createMock(EntityDefinitionService::class),
            $this->createMock(LoggerInterface::class),
        );

        $handler(new IterateEntityMessage('test-entity', Operation::DELETE, new \DateTimeImmutable('2023-08-16'), null));
        $handler(new IterateEntityMessage('test-entity', Operation::UPDATE, new \DateTimeImmutable('2023-08-16'), null));

        $dispatchedMessages = $messageBus->getMessages();
        static::assertCount(0, $dispatchedMessages);
    }

    public function testDispatchesNoMessageToFetchDeletedOrUpdatedEntitiesWithoutLastApprovalDate(): void
    {
        $messageBus = new CollectingMessageBus();

        $iteratorFactory = $this->createMock(IterateEntitiesQueryBuilder::class);
        $iteratorFactory->expects(static::never())
            ->method('create');

        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::exactly(1))
            ->method('getLastConsentIsAcceptedDate')
            ->willReturn(null);

        $entityDefinitionService = $this->createMock(EntityDefinitionService::class);
        $entityDefinitionService->expects(static::exactly(1))
            ->method('getAllowedEntityDefinition')
            ->with('test-entity')
            ->willReturn(new ProductDefinition());

        $handler = new IterateEntityMessageHandler(
            $messageBus,
            $iteratorFactory,
            $consentService,
            $entityDefinitionService,
            $this->createMock(LoggerInterface::class),
        );

        static::expectException(UnrecoverableMessageHandlingException::class);
        static::expectExceptionMessage('No approval date found. Skipping dispatching of entity sync message. Entity: test-entity, Operation: delete');
        $handler(new IterateEntityMessage('test-entity', Operation::DELETE, new \DateTimeImmutable('2023-08-16'), new \DateTimeImmutable()));

        $dispatchedMessages = $messageBus->getMessages();
        static::assertCount(0, $dispatchedMessages);
    }

    public function testItDispatchesAMessageToTheQueue(): void
    {
        $messageBus = new CollectingMessageBus();

        $iterableQuery = $this->createMock(QueryBuilder::class);
        $iterableQuery->expects(static::exactly(2))
            ->method('fetchAllAssociative')
            ->willReturnOnConsecutiveCalls(
                [
                    ['id' => 'first-id'],
                    ['id' => 'second-id'],
                ],
                [],
            );

        $iteratorFactory = $this->createMock(IterateEntitiesQueryBuilder::class);
        $iteratorFactory->expects(static::once())
            ->method('create')
            ->willReturn($iterableQuery);

        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('getLastConsentIsAcceptedDate')
            ->willReturn(new \DateTimeImmutable());

        $entityDefinitionService = $this->createMock(EntityDefinitionService::class);
        $entityDefinitionService->expects(static::once())
            ->method('getAllowedEntityDefinition')
            ->with('test-entity')
            ->willReturn(new ProductDefinition());

        $handler = new IterateEntityMessageHandler(
            $messageBus,
            $iteratorFactory,
            $consentService,
            $entityDefinitionService,
            $this->createMock(LoggerInterface::class),
        );

        $handler(new IterateEntityMessage(
            'test-entity',
            Operation::CREATE,
            new \DateTimeImmutable('2023-08-16'),
            new \DateTimeImmutable(),
        ));

        $dispatchedMessages = $messageBus->getMessages();

        static::assertCount(1, $dispatchedMessages);

        $entitySyncMessage = $dispatchedMessages[0]->getMessage();

        static::assertInstanceOf(DispatchEntityMessage::class, $entitySyncMessage);

        static::assertEquals('test-entity', $entitySyncMessage->entityName);
        static::assertEquals([
            ['id' => 'first-id'],
            ['id' => 'second-id'],
        ], $entitySyncMessage->primaryKeys);
    }

    public function testSkipEntityIfDefinitionIsNotFound(): void
    {
        $messageBus = new CollectingMessageBus();

        $iterableQuery = $this->createMock(QueryBuilder::class);
        $iterableQuery->expects(static::never())
            ->method('fetchAllAssociative');

        $iteratorFactory = $this->createMock(IterateEntitiesQueryBuilder::class);
        $iteratorFactory->expects(static::never())
            ->method('create');

        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::never())
            ->method('getLastConsentIsAcceptedDate');

        $entityDefinitionService = $this->createMock(EntityDefinitionService::class);
        $entityDefinitionService->expects(static::once())
            ->method('getAllowedEntityDefinition')
            ->with('test-entity')
            ->willReturn(null);

        $handler = new IterateEntityMessageHandler(
            $messageBus,
            $iteratorFactory,
            $consentService,
            $entityDefinitionService,
            $this->createMock(LoggerInterface::class),
        );

        static::expectException(UnrecoverableMessageHandlingException::class);
        static::expectExceptionMessage('Entity definition for entity test-entity not found.');
        $handler(new IterateEntityMessage('test-entity', Operation::CREATE, new \DateTimeImmutable('2023-08-16'), new \DateTimeImmutable()));

        $dispatchedMessages = $messageBus->getMessages();
        static::assertCount(0, $dispatchedMessages);
    }

    public function testItLogsExceptionWithNonDBALServerExceptionIsThrown(): void
    {
        $iteratorFactory = $this->createMock(IterateEntitiesQueryBuilder::class);
        $iteratorFactory->expects(static::any())
            ->method('create')
            ->willThrowException(new \Exception('An exception occurred while executing...'));

        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('getLastConsentIsAcceptedDate')
            ->willReturn(new \DateTimeImmutable());

        $entityDefinitionService = $this->createMock(EntityDefinitionService::class);
        $entityDefinitionService->expects(static::once())
            ->method('getAllowedEntityDefinition')
            ->with('product')
            ->willReturn(new ProductDefinition());

        $logger = $this->createMock(Logger::class);
        $logger->expects(static::once())
            ->method('error')
            ->with(
                'Could not iterate over entity: An exception occurred while executing...',
                [
                    'exception' => new \Exception('An exception occurred while executing...'),
                    'entity' => 'product',
                    'operation' => Operation::CREATE->value,
                ],
            );

        $messageHandler = new IterateEntityMessageHandler(
            $this->createMock(CollectingMessageBus::class),
            $iteratorFactory,
            $consentService,
            $entityDefinitionService,
            $logger,
        );

        $messageHandler(new IterateEntityMessage(
            'product',
            Operation::CREATE,
            new \DateTimeImmutable('2023-08-16'),
            new \DateTimeImmutable('2023-08-01'),
        ));
    }

    public function testItLogsAndThrowsExceptionWithDBALConnectionExceptionIsThrown(): void
    {
        $iteratorFactory = $this->createMock(IterateEntitiesQueryBuilder::class);
        $iteratorFactory->method('create')
            ->willThrowException(new ConnectionException());

        $this->expectException(ConnectionException::class);

        $consentService = $this->createMock(ConsentService::class);
        $consentService->expects(static::once())
            ->method('getLastConsentIsAcceptedDate')
            ->willReturn(new \DateTimeImmutable());

        $entityDefinitionService = $this->createMock(EntityDefinitionService::class);
        $entityDefinitionService->expects(static::once())
            ->method('getAllowedEntityDefinition')
            ->with('product')
            ->willReturn(new ProductDefinition());

        $logger = $this->createMock(Logger::class);
        $logger->expects(static::never())
            ->method('error');

        $messageHandler = new IterateEntityMessageHandler(
            $this->createMock(CollectingMessageBus::class),
            $iteratorFactory,
            $consentService,
            $entityDefinitionService,
            $logger,
        );

        $messageHandler(new IterateEntityMessage(
            'product',
            Operation::CREATE,
            new \DateTimeImmutable('2023-08-16'),
            new \DateTimeImmutable('2023-08-01'),
        ));
    }
}
