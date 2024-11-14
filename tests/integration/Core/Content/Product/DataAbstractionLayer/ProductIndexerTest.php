<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\RatingAverageUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
class ProductIndexerTest extends TestCase
{
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    private const AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION = 7085;
    private const UPDATE_IDS_CHUNK_SIZE_OF_INDEXER = 50;
    private const MAX_AMOUNT_OF_IDS_TO_BE_BELOW_CHUNK_SIZE = 49;
    private const AMOUNT_OF_IDS_JUST_ABOVE_CHUNK_SIZE = 51;

    private ProductIndexer $indexer;

    private Connection&MockObject $connectionMock;

    private MessageBusInterface $messageBus;

    protected function setUp(): void
    {
        $this->connectionMock = $this->createMock(Connection::class);
        $this->messageBus = self::getContainer()->get('messenger.bus.shopware');

        $this->indexer = new ProductIndexer(
            self::getContainer()->get(IteratorFactory::class),
            self::getContainer()->get('product.repository'),
            $this->connectionMock,
            self::getContainer()->get(VariantListingUpdater::class),
            self::getContainer()->get(ProductCategoryDenormalizer::class),
            self::getContainer()->get(InheritanceUpdater::class),
            self::getContainer()->get(RatingAverageUpdater::class),
            self::getContainer()->get(SearchKeywordUpdater::class),
            self::getContainer()->get(ChildCountUpdater::class),
            self::getContainer()->get(ManyToManyIdFieldUpdater::class),
            self::getContainer()->get(StockStorage::class),
            self::getContainer()->get('event_dispatcher'),
            self::getContainer()->get(CheapestPriceUpdater::class),
            self::getContainer()->get(ProductStreamUpdater::class),
            self::getContainer()->get(StatesUpdater::class),
            $this->messageBus
        );
    }

    #[Group('slow')]
    public function testUpdateDoesNotReturnTooBigMessage(): void
    {
        $uuids = $this->getUuids(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION);
        $this->prepareGetChildrenIdsMethod($uuids);
        $context = Context::createDefaultContext();
        $nestedEvents = $this->prepareEvent($context, $uuids);

        $message = $this->indexer->update(new EntityWrittenContainerEvent($context, $nestedEvents, []));
        static::assertNotNull($message);
        $this->messageBus->dispatch($message);

        $this->runWorker();

        static::assertInstanceOf(TraceableMessageBus::class, $this->messageBus);
        $messages = $this->messageBus->getDispatchedMessages();

        $messagesDispatchedInProductIndexer = array_filter($messages, static function ($message) {
            return $message['caller']['name'] === 'ProductIndexer.php';
        });

        $expectedAmountOfMessagesForParentsAndChildren = (int) ceil(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION / self::UPDATE_IDS_CHUNK_SIZE_OF_INDEXER);
        // Round down because one chunk is returned by the method and not sent in the ProductIndexer directly
        $expectedAmountOfMessagesForUpdatedProducts = (int) floor(self::AMOUNT_OF_UUIDS_NEEDED_TO_TRIGGER_MESSAGE_SIZE_RESTRICTION / self::UPDATE_IDS_CHUNK_SIZE_OF_INDEXER);
        $expectedAmountOfMessages = $expectedAmountOfMessagesForParentsAndChildren + $expectedAmountOfMessagesForUpdatedProducts;
        static::assertCount($expectedAmountOfMessages, $messagesDispatchedInProductIndexer);
    }

    #[DataProvider('updateCases')]
    public function testUpdate(
        int $numberOfIds,
        int $expectedCountOfMessagesDispatchedInProductIndexer
    ): void {
        $uuids = $this->getUuids($numberOfIds);
        $this->prepareGetChildrenIdsMethod($uuids);
        $context = Context::createDefaultContext();
        $nestedEvents = $this->prepareEvent($context, $uuids);

        $message = $this->indexer->update(new EntityWrittenContainerEvent($context, $nestedEvents, []));
        static::assertNotNull($message);
        $this->messageBus->dispatch($message);

        $this->runWorker();

        static::assertInstanceOf(TraceableMessageBus::class, $this->messageBus);
        $messages = $this->messageBus->getDispatchedMessages();

        $messagesDispatchedInProductIndexer = array_filter($messages, static function ($message) {
            return $message['caller']['name'] === 'ProductIndexer.php';
        });

        static::assertCount($expectedCountOfMessagesDispatchedInProductIndexer, $messagesDispatchedInProductIndexer);
    }

    public static function updateCases(): \Generator
    {
        yield 'Amount of Uuids so low, that the message bus is only used once for parents and children' => [
            'numberOfIds' => self::MAX_AMOUNT_OF_IDS_TO_BE_BELOW_CHUNK_SIZE,
            'expectedCountOfMessagesDispatchedInProductIndexer' => 1,
        ];
        yield 'Amount of Uuids just so high, that the message bus is used once for to-be-updated products and two times for parents and children' => [
            'numberOfIds' => self::AMOUNT_OF_IDS_JUST_ABOVE_CHUNK_SIZE,
            'expectedCountOfMessagesDispatchedInProductIndexer' => 3,
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
    private function prepareGetChildrenIdsMethod(array $uuids): void
    {
        $this->connectionMock->method('fetchFirstColumn')->willReturn($uuids);
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
                ProductDefinition::ENTITY_NAME,
                EntityWriteResult::OPERATION_UPDATE
            );
        }

        return new NestedEventCollection([
            new EntityWrittenEvent(
                ProductDefinition::ENTITY_NAME,
                $results,
                $context
            ),
        ]);
    }
}
