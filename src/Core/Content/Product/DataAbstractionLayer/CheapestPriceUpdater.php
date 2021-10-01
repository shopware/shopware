<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Shopware\Core\Framework\Uuid\Uuid;

class CheapestPriceUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var AbstractCheapestPriceQuantitySelector
     */
    private $quantitySelector;

    public function __construct(Connection $connection, AbstractCheapestPriceQuantitySelector $quantitySelector)
    {
        $this->connection = $connection;
        $this->quantitySelector = $quantitySelector;
    }

    public function update(array $parentIds, Context $context): void
    {
        $parentIds = array_unique(array_filter($parentIds));

        if (empty($parentIds)) {
            return;
        }

        $all = $this->fetchPrices($parentIds, $context);

        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        RetryableQuery::retryable($this->connection, function () use ($parentIds, $versionId): void {
            $this->connection->executeUpdate(
                'UPDATE product SET cheapest_price = NULL, cheapest_price_accessor = NULL WHERE (id IN (:ids) OR parent_id IN (:ids)) AND version_id = :version',
                ['ids' => Uuid::fromHexToBytesList($parentIds), 'version' => $versionId],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        });

        $cheapestPrice = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE product SET cheapest_price = :price WHERE id = :id AND version_id = :version')
        );

        $accessorQuery = new RetryableQuery(
            $this->connection,
            $this->connection->prepare('UPDATE product SET cheapest_price_accessor = :accessor WHERE id = :id AND version_id = :version')
        );

        foreach ($all as $productId => $prices) {
            $container = new CheapestPriceContainer($prices);

            $cheapestPrice->execute([
                'price' => serialize($container),
                'id' => Uuid::fromHexToBytes($productId),
                'version' => $versionId,
            ]);

            foreach ($container->getVariantIds() as $variantId) {
                $accessorQuery->execute([
                    'accessor' => JsonFieldSerializer::encodeJson($this->buildAccessor($container, $variantId)),
                    'id' => Uuid::fromHexToBytes($variantId),
                    'version' => $versionId,
                ]);
            }
        }
    }

    private function buildAccessor(CheapestPriceContainer $container, string $variantId): array
    {
        $rules = $container->getRuleIds();
        $rules[] = 'default';

        $variantPrices = $container->getPricesForVariant($variantId);
        $formattedPrices = [];
        foreach ($rules as $ruleId) {
            $cheapest = $this->getCheapest($ruleId, $variantPrices, $container->getDefault());

            if ($cheapest === null) {
                throw new \RuntimeException(sprintf(
                    'Could not find CheapestPrice for Variant "%s" for Rule "%s"',
                    $variantId,
                    $ruleId
                ));
            }
            $mapped = [];
            foreach ($cheapest['price'] as $price) {
                $mapped['currency' . $price['currencyId']] = $this->mapPrice($price);
            }

            $formattedPrices['rule' . $ruleId] = $mapped;
        }

        return $formattedPrices;
    }

    private function getCheapest(?string $ruleId, array $prices, ?array $default): ?array
    {
        if (isset($prices[$ruleId])) {
            return $prices[$ruleId];
        }

        return $prices['default'] ?? $default;
    }

    private function mapPrice(array $price): array
    {
        $array = ['gross' => $price['gross'], 'net' => $price['net']];

        if (isset($price['listPrice'])) {
            $array['listPrice'] = [
                'gross' => $price['listPrice']['gross'],
                'net' => $price['listPrice']['net'],
            ];
        }

        return $array;
    }

    private function fetchPrices(array $ids, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(IFNULL(product.parent_id, product.id))) as parent_id',
            'LOWER(HEX(product.id)) as variant_id',
            'LOWER(HEX(price.rule_id)) as rule_id',
            'LOWER(HEX(IFNULL(product.unit_id, parent.unit_id))) as unit_id',
            'IFNULL(product.purchase_unit, parent.purchase_unit) as purchase_unit',
            'IFNULL(product.reference_unit, parent.reference_unit) as reference_unit',
            'IFNULL(product.min_purchase, parent.min_purchase) as min_purchase',
            'price.price',
        ]);

        $query->from('product', 'product');
        $query->innerJoin('product', 'product_price', 'price', 'price.product_id = product.prices AND product.version_id = price.product_version_id');
        $query->leftJoin('product', 'product', 'parent', 'parent.id = product.parent_id');

        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('product.version_id = :version');
        $query->andWhere('IFNULL(product.active, parent.active) = 1');
        $query->andWhere('(product.child_count = 0 OR product.parent_id IS NOT NULL)');

        $this->quantitySelector->add($query);

        $ids = Uuid::fromHexToBytesList($ids);

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);
        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));

        $data = $query->execute()->fetchAllAssociative();

        $grouped = [];
        /** @var array $row */
        foreach ($data as $row) {
            $row['price'] = json_decode($row['price'], true);
            $grouped[$row['parent_id']][$row['variant_id']][$row['rule_id']] = $row;
        }

        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(IFNULL(product.parent_id, product.id))) as parent_id',
            'LOWER(HEX(product.id)) as variant_id',
            'NULL as rule_id',
            '0 AS is_ranged',
            'product.price as price',
            'IFNULL(product.min_purchase, parent.min_purchase) as min_purchase',
            'LOWER(HEX(IFNULL(product.unit_id, parent.unit_id))) as unit_id',
            'IFNULL(product.purchase_unit, parent.purchase_unit) as purchase_unit',
            'IFNULL(product.reference_unit, parent.reference_unit) as reference_unit',
            'product.child_count as child_count',
        ]);

        $query->from('product', 'product');
        $query->leftJoin('product', 'product', 'parent', 'product.parent_id = parent.id');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('product.version_id = :version');
        $query->andWhere('IFNULL(product.active, parent.active) = 1');

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);
        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));

        $defaults = $query->execute()->fetchAllAssociative();

        /** @var array $row */
        foreach ($defaults as $row) {
            if ($row['price'] === null) {
                $grouped[$row['parent_id']][$row['variant_id']]['default'] = null;

                continue;
            }

            $row['price'] = json_decode($row['price'], true);
            $row['price'] = $this->normalizePrices($row['price']);
            if ($row['child_count'] > 0) {
                $grouped[$row['parent_id']]['default'] = $row;

                continue;
            }

            $grouped[$row['parent_id']][$row['variant_id']]['default'] = $row;
        }

        return $grouped;
    }

    private function normalizePrices(array $prices): array
    {
        foreach ($prices as &$price) {
            $price['net'] = (float) $price['net'];
            $price['gross'] = (float) $price['gross'];
        }

        return $prices;
    }
}
