<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Indexer;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\DbalIndexing\Common\RepositoryIterator;
use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Loader\VoteAverageLoader;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\Product\Event\Product\ProductWrittenEvent;
use Shopware\Product\Repository\ProductRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class VoteAverageIndexer implements IndexerInterface
{
    const TABLE = 'product_vote_average';

    /**
     * @var ProductRepository
     */
    private $productRepository;

    /**
     * @var VoteAverageLoader
     */
    private $loader;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        ProductRepository $productRepository,
        VoteAverageLoader $loader,
        Connection $connection,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->productRepository = $productRepository;
        $this->loader = $loader;
        $this->connection = $connection;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function index(TranslationContext $context, \DateTime $timestamp): void
    {
        if ($context->getShopUuid() !== 'SWAG-SHOP-UUID-1') {
            return;
        }

        $this->createTable($timestamp);

        $iterator = new RepositoryIterator($this->productRepository, $context);

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing vote averages', $iterator->getTotal())
        );

        while ($uuids = $iterator->fetchUuids()) {
            $this->indexVoteAverage($uuids, $timestamp);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(count($uuids))
            );
        }

        $this->renameTable($timestamp);
        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished vote average indexing')
        );
    }

    public function refresh(NestedEventCollection $events, TranslationContext $context): void
    {
        $uuids = $this->getProductUuids($events);
        if (empty($uuids)) {
            return;
        }
    }

    private function getIndexName(?\DateTime $timestamp): string
    {
        if ($timestamp === null) {
            return self::TABLE;
        }

        return self::TABLE . '_' . $timestamp->format('YmdHis');
    }

    /**
     * @param NestedEventCollection $events
     *
     * @return array
     */
    private function getProductUuids(NestedEventCollection $events): array
    {
        /** @var NestedEventCollection $events */
        $events = $events
            ->getFlatEventList()
            ->filterInstance(ProductWrittenEvent::NAME);

        $uuids = [];
        /** @var ProductWrittenEvent $event */
        foreach ($events as $event) {
            foreach ($event->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    private function renameTable(\DateTime $timestamp): void
    {
        $this->connection->transactional(function () use ($timestamp) {
            $name = $this->getIndexName($timestamp);
            $this->connection->executeUpdate('DROP TABLE ' . self::TABLE);
            $this->connection->executeUpdate('ALTER TABLE ' . $name . ' RENAME TO ' . self::TABLE);
        });
    }

    private function createTable(\DateTime $timestamp): void
    {
        $name = $this->getIndexName($timestamp);
        $this->connection->executeUpdate('
            DROP TABLE IF EXISTS ' . $name . ';
            CREATE TABLE ' . $name . ' SELECT * FROM ' . self::TABLE . ' LIMIT 0
        ');

        $this->connection->executeUpdate('ALTER TABLE ' . $name . ' ADD PRIMARY KEY (uuid)');
        $this->connection->executeUpdate('ALTER TABLE ' . $name . ' ADD CONSTRAINT FOREIGN KEY (`product_uuid`) REFERENCES `product` (`uuid`) ON DELETE CASCADE ON UPDATE CASCADE');
    }

    private function indexVoteAverage(array $uuids, \DateTime $timestamp)
    {
        $votes = $this->loader->load($uuids);

        $table = $this->getIndexName($timestamp);

        $insert = $this->connection->prepare('
INSERT INTO `' . $table . '` (`uuid`, `product_uuid`, `shop_uuid`, `average`, `total`, `five_point_count`, `four_point_count`, `three_point_count`, `two_point_count`, `one_point_count`)
VALUES (:uuid, :product_uuid, :shop_uuid, :average, :total, :five_point_count, :four_point_count, :three_point_count, :two_point_count, :one_point_count);
        ');

        /** @var ProductVoteAverageBasicStruct $vote */
        foreach ($votes as $vote) {
            $insert->execute([
                'uuid' => $vote->getUuid(),
                'product_uuid' => $vote->getProductUuid(),
                'shop_uuid' => $vote->getShopUuid(),
                'average' => $vote->getAverage(),
                'total' => $vote->getTotal(),
                'five_point_count' => $vote->getFivePointCount(),
                'four_point_count' => $vote->getFourPointCount(),
                'three_point_count' => $vote->getThreePointCount(),
                'two_point_count' => $vote->getTwoPointCount(),
                'one_point_count' => $vote->getOnePointCount(),
            ]);
        }
    }
}
