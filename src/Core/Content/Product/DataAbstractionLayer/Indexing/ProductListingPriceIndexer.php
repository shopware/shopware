<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Util\EventIdExtractor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceRulesJsonField;
use Shopware\Core\Framework\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Pricing\PriceRuleStruct;
use Shopware\Core\Framework\Pricing\PriceStruct;
use Shopware\Core\Framework\Struct\Uuid;
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

    public function index(\DateTime $timestamp, string $tenantId): void
    {
        $context = Context::createDefaultContext($tenantId);

        $iterator = $this->createIterator($tenantId);

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

        $field = new PriceRulesJsonField('tmp', 'tmp');

        foreach ($prices as $id => $productPrices) {
            $productPrices = $this->convertPrices($productPrices);
            $ruleIds = array_keys(array_flip(array_column($productPrices, 'ruleId')));
            $listingPrices = [];

            foreach ($ruleIds as $ruleId) {
                $listingPrices[] = $this->findCheapestRulePrice($productPrices, $ruleId);
            }

            $listingPrices = $field->convertToStorage($listingPrices);

            $this->connection->executeUpdate(
                'UPDATE product SET listing_prices = :price WHERE id = :id AND tenant_id = :tenant',
                [
                    'price' => json_encode($listingPrices),
                    'id' => Uuid::fromStringToBytes($id),
                    'tenant' => Uuid::fromHexToBytes($context->getTenantId()),
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
        $query->innerJoin('product', 'product_price_rule', 'price', 'price.product_id = product.id AND product.tenant_id = price.tenant_id');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('price.quantity_end IS NULL');
        $query->andWhere('price.tenant_id = :tenant');

        $ids = array_map(function ($id) {
            return Uuid::fromHexToBytes($id);
        }, $ids);

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);
        $query->setParameter('tenant', Uuid::fromHexToBytes($context->getTenantId()));

        $data = $query->execute()->fetchAll();

        return FetchModeHelper::group($data);
    }

    private function convertPrices(array $productPrices): array
    {
        $productPrices = array_map(
            function (array $price) {
                $value = json_decode($price['price'], true);
                $value['_class'] = PriceStruct::class;

                return [
                    'id' => Uuid::fromBytesToHex($price['price_id']),
                    'variantId' => Uuid::fromBytesToHex($price['variant_id']),
                    'ruleId' => Uuid::fromBytesToHex($price['rule_id']),
                    'currencyId' => Uuid::fromBytesToHex($price['currency_id']),
                    'price' => $value,
                    '_class' => PriceRuleStruct::class,
                ];
            },
            $productPrices
        );

        return $productPrices;
    }

    private function findCheapestRulePrice(array $productPrices, string $ruleId): array
    {
        $rulePrices = array_filter(
            $productPrices,
            function (array $price) use ($ruleId) {
                return $price['ruleId'] === $ruleId;
            }
        );

        usort(
            $rulePrices,
            function (array $a, array $b) {
                return $a['price']['gross'] <=> $b['price']['gross'];
            }
        );

        return array_shift($rulePrices);
    }

    private function createIterator(string $tenantId): LastIdQuery
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['product.auto_increment', 'product.id']);
        $query->from('product');
        $query->andWhere('product.tenant_id = :tenantId');
        $query->andWhere('product.auto_increment > :lastId');
        $query->addOrderBy('product.auto_increment');

        $query->setMaxResults(50);

        $query->setParameter('tenantId', Uuid::fromHexToBytes($tenantId));
        $query->setParameter('lastId', 0);

        return new LastIdQuery($query);
    }
}
