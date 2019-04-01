<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Util\EventIdExtractor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceRulesJsonFieldSerializer;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductListingPriceIndexer implements IndexerInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EventIdExtractor
     */
    private $eventIdExtractor;

    /**
     * @var Connection
     */
    private $connection;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor,
        Connection $connection
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
        $this->connection = $connection;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        $iterator = $this->createIterator();

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing listing prices', $iterator->fetchCount())
        );

        while ($ids = $iterator->fetch()) {
            $ids = array_map(function ($id) {
                return Uuid::fromBytesToHex($id);
            }, $ids);

            $this->update($ids, $context);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(\count($ids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished indexing listing prices')
        );
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $ids = $this->eventIdExtractor->getProductIds($event);

        $this->update($ids, $event->getContext());
    }

    private function update(array $ids, Context $context): void
    {
        if (empty($ids)) {
            return;
        }

        $prices = $this->fetchPrices($ids, $context);

        foreach ($prices as $id => $productPrices) {
            $productPrices = $this->convertPrices($productPrices);
            $ruleIds = array_keys(array_flip(array_column($productPrices, 'ruleId')));
            $listingPrices = [];

            foreach ($ruleIds as $ruleId) {
                $listingPrices[] = $this->findCheapestRulePrice($productPrices, $ruleId);
            }

            $listingPrices = PriceRulesJsonFieldSerializer::convertToStorage($listingPrices);

            $this->connection->executeUpdate(
                'UPDATE product SET listing_prices = :price WHERE id = :id',
                [
                    'price' => json_encode($listingPrices),
                    'id' => Uuid::fromHexToBytes($id),
                ]
            );
        }
    }

    private function fetchPrices(array $ids, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'IFNULL(HEX(product.parent_id), HEX(product.id)) as id',
            'price.id as price_id',
            'product.id as variant_id',
            'price.rule_id',
            'price.price',
            'price.currency_id',
        ]);

        $query->from('product', 'product');
        $query->innerJoin('product', 'product_price', 'price', 'price.product_id = product.id');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('price.quantity_end IS NULL');

        $ids = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        $data = $query->execute()->fetchAll();

        return FetchModeHelper::group($data);
    }

    private function convertPrices(array $productPrices): array
    {
        $productPrices = array_map(
            function (array $price) {
                return [
                    'id' => bin2hex($price['price_id']),
                    'variantId' => bin2hex($price['variant_id']),
                    'ruleId' => bin2hex($price['rule_id']),
                    'currencyId' => bin2hex($price['currency_id']),
                    'price' => json_decode($price['price'], true),
                ];
            },
            $productPrices
        );

        return $productPrices;
    }

    private function findCheapestRulePrice(array $productPrices, string $ruleId): array
    {
        $cheapest = null;
        foreach ($productPrices as $price) {
            if ($price['ruleId'] !== $ruleId) {
                continue;
            }
            if ($cheapest === null) {
                $cheapest = $price;
                continue;
            }

            if ($price['price']['gross'] < $cheapest['price']['gross']) {
                $cheapest = $price;
            }
        }

        return $cheapest;
    }

    private function createIterator(): LastIdQuery
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['product.auto_increment', 'product.id']);
        $query->from('product');
        $query->andWhere('product.auto_increment > :lastId');
        $query->addOrderBy('product.auto_increment');

        $query->setMaxResults(50);

        $query->setParameter('lastId', 0);

        return new LastIdQuery($query);
    }
}
