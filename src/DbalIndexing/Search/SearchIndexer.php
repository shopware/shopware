<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Search;

use Doctrine\DBAL\Connection;
use Shopware\Api\Search\Criteria;
use Shopware\Context\Struct\TranslationContext;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Indexer\IndexerInterface;
use Shopware\Framework\Doctrine\MultiInsertQueryQueue;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Repository\ProductRepository;
use Shopware\Product\Struct\ProductDetailStruct;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SearchIndexer implements IndexerInterface
{
    public const TABLE = 'search_keyword';

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var SearchAnalyzerRegistry
     */
    private $analyzerRegistry;

    public function __construct(
        Connection $connection,
        ProductRepository $productRepository,
        EventDispatcherInterface $eventDispatcher,
        SearchAnalyzerRegistry $analyzerRegistry
    ) {
        $this->connection = $connection;
        $this->productRepository = $productRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->analyzerRegistry = $analyzerRegistry;
    }

    public function index(TranslationContext $context, \DateTime $timestamp): void
    {
        $this->createTable();

        $criteria = new Criteria();
        $criteria->setOffset(0);
        $criteria->setLimit(5);

        $iterator = new RepositoryIterator($this->productRepository, $context, $criteria);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start analyzing search keywords', $iterator->getTotal())
        );

        while ($uuids = $iterator->fetchUuids()) {
            $products = $this->productRepository->readDetail($uuids, $context);

            /** @var ProductDetailStruct $product */
            $queue = new MultiInsertQueryQueue($this->connection, 250, true);

            foreach ($products as $product) {
                $keywords = $this->analyzerRegistry->analyze($product, $context);

                foreach ($keywords as $keyword) {
                    $queue->addInsert(
                        'tmp_search_keyword',
                        [
                            'shop_uuid' => $context->getShopUuid(),
                            'keyword' => $keyword,
                        ]
                    );
                }
            }

            $queue->execute();

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($uuids))
            );
        }

        $this->renameTable($context->getShopUuid());

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished analyzing search keywords')
        );
    }

    public function refresh(NestedEventCollection $events, TranslationContext $context): void
    {
    }

    private function renameTable(string $shopUuid): void
    {
        $this->connection->transactional(function () use ($shopUuid) {
            $this->connection->executeUpdate(
                'DELETE FROM ' . self::TABLE . ' WHERE shop_uuid = :id',
                ['id' => $shopUuid]
            );

            $this->connection->executeUpdate('
                INSERT INTO ' . self::TABLE . ' (shop_uuid, keyword, document_count) 
                SELECT shop_uuid, keyword, COUNT(keyword) as document_count
                FROM `tmp_search_keyword`
                WHERE shop_uuid = :id
                GROUP BY shop_uuid, keyword
            ', [
                'id' => $shopUuid,
            ]);

            $this->connection->executeUpdate('DROP TABLE `tmp_search_keyword`');
        });
    }

    private function createTable(): void
    {
        $this->connection->executeUpdate('
            DROP TABLE IF EXISTS `tmp_search_keyword`;
            
            CREATE TABLE `tmp_search_keyword` (
              `keyword` varchar(500) NOT NULL,
              `shop_uuid` varchar(42) NOT NULL
            );
        ');
    }
}
