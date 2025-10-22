<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(ProductIndexer::class)]
class ProductIndexerTest extends TestCase
{
    public function testUpdateSkipChildCountUpdater(): void
    {
        $indexer = new ProductIndexer(
            $this->createMock(IteratorFactory::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(Connection::class),
            $this->createMock(VariantListingUpdater::class),
            $this->createMock(ProductCategoryDenormalizer::class),
            $this->createMock(InheritanceUpdater::class),
            $this->createMock(RatingAverageUpdater::class),
            $this->createMock(SearchKeywordUpdater::class),
            $this->createMock(ChildCountUpdater::class),
            $this->createMock(ManyToManyIdFieldUpdater::class),
            $this->createMock(StockStorage::class),
            $this->createMock(EventDispatcher::class),
            $this->createMock(CheapestPriceUpdater::class),
            $this->createMock(ProductStreamUpdater::class),
            $this->createMock(StatesUpdater::class),
            $this->createMock(MessageBusInterface::class),
        );

        $context = Context::createDefaultContext();
        $nestedEvents = $this->prepareEvent($context, [Uuid::randomHex()]);
        $writtenEvent = new EntityWrittenContainerEvent($context, $nestedEvents, []);
        $writtenEvent->setCloned(true);

        $message = $indexer->update($writtenEvent);
        static::assertNotNull($message);
        static::assertContains(ProductIndexer::CHILD_COUNT_UPDATER, $message->getSkip());
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
