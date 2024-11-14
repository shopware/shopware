<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryBreadcrumbUpdater;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryIndexer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
class CategoryIndexerTest extends TestCase
{
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private const AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION = 7085;
    private const UPDATE_IDS_CHUNK_SIZE_OF_INDEXER = 50;
    private const MAX_AMOUNT_OF_IDS_TO_BE_BELOW_CHUNK_SIZE = 49;
    private const AMOUNT_OF_IDS_JUST_ABOVE_CHUNK_SIZE = 51;

    private CategoryIndexer $indexer;

    private Connection&MockObject $connectionMock;

    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->messageBus = self::getContainer()->get('messenger.bus.shopware');

        $this->indexer = new CategoryIndexer(
            $this->connectionMock,
            self::getContainer()->get(IteratorFactory::class),
            self::getContainer()->get('category.repository'),
            self::getContainer()->get(ChildCountUpdater::class),
            self::getContainer()->get(TreeUpdater::class),
            self::getContainer()->get(CategoryBreadcrumbUpdater::class),
            self::getContainer()->get('event_dispatcher'),
            $this->messageBus
        );
    }

    #[Group('slow')]
    public function testUpdateDoesNotReturnTooBigMessage(): void
    {
        $uuids = $this->getUuids(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION);
        $this->prepareFetchChildrenMethod($uuids);
        $context = Context::createDefaultContext();
        $nestedEvents = $this->prepareEvent($context, $uuids);

        $message = $this->indexer->update(new EntityWrittenContainerEvent($context, $nestedEvents, []));
        static::assertNotNull($message);
        $this->messageBus->dispatch($message);

        $this->runWorker();

        static::assertInstanceOf(TraceableMessageBus::class, $this->messageBus);
        $messages = $this->messageBus->getDispatchedMessages();

        $messagesDispatchedInCategoryIndexer = array_filter($messages, static function ($message) {
            return $message['caller']['name'] === 'CategoryIndexer.php';
        });

        // Round down because one chunk is returned by the method and not sent in the CategoryIndexer directly
        $expectedAmountOfMessages = (int) floor(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION / self::UPDATE_IDS_CHUNK_SIZE_OF_INDEXER);
        static::assertCount($expectedAmountOfMessages, $messagesDispatchedInCategoryIndexer);
    }

    #[DataProvider('updateCases')]
    public function testUpdate(
        int $numberOfIds,
        int $expectedCountOfMessagesDispatchedInCategoryIndexer
    ): void {
        $uuids = $this->getUuids($numberOfIds);
        $this->prepareFetchChildrenMethod($uuids);
        $context = Context::createDefaultContext();
        $nestedEvents = $this->prepareEvent($context, $uuids);

        $message = $this->indexer->update(new EntityWrittenContainerEvent($context, $nestedEvents, []));
        static::assertNotNull($message);
        $this->messageBus->dispatch($message);

        $this->runWorker();

        static::assertInstanceOf(TraceableMessageBus::class, $this->messageBus);
        $messages = $this->messageBus->getDispatchedMessages();

        $messagesDispatchedInCategoryIndexer = array_filter($messages, static function ($message) {
            return $message['caller']['name'] === 'CategoryIndexer.php';
        });

        static::assertCount($expectedCountOfMessagesDispatchedInCategoryIndexer, $messagesDispatchedInCategoryIndexer);
    }

    public static function updateCases(): \Generator
    {
        yield 'Amount of Uuids so low, that the message bus is not used' => [
            'numberOfIds' => self::MAX_AMOUNT_OF_IDS_TO_BE_BELOW_CHUNK_SIZE,
            'expectedCountOfMessagesDispatchedInCategoryIndexer' => 0,
        ];
        yield 'Amount of Uuids just so high, that the message bus is used exactly once' => [
            'numberOfIds' => self::AMOUNT_OF_IDS_JUST_ABOVE_CHUNK_SIZE,
            'expectedCountOfMessagesDispatchedInCategoryIndexer' => 1,
        ];
    }

    /**
     * @return list<string>
     */
    private function getUuids(int $numberOfIds): array
    {
        $uuids = [];
        for ($i = 0; $i < $numberOfIds; ++$i) {
            $uuids[] = Uuid::randomHex();
        }

        return $uuids;
    }

    /**
     * @param list<string> $uuids
     */
    private function prepareFetchChildrenMethod(array $uuids): void
    {
        $result = $this->createMock(Result::class);
        $result->method('fetchFirstColumn')->willReturn($uuids);
        $query = $this->createMock(QueryBuilder::class);
        $query->method('executeQuery')->willReturn($result);
        $this->connectionMock->method('createQueryBuilder')->willReturn($query);
    }

    /**
     * @param list<string> $uuids
     */
    private function prepareEvent(Context $context, array $uuids): NestedEventCollection
    {
        $results = [];
        foreach ($uuids as $uuid) {
            $results[] = new EntityWriteResult(
                $uuid,
                [],
                CategoryDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            );
        }

        return new NestedEventCollection([
            new EntityWrittenEvent(
                CategoryDefinition::ENTITY_NAME,
                $results,
                $context
            ),
        ]);
    }
}
