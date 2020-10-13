<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\Uuid\Uuid;

class ListingPriceUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var PriceRounding
     */
    private $priceRounding;

    public function __construct(Connection $connection, PriceRounding $priceRounding)
    {
        $this->connection = $connection;
        $this->priceRounding = $priceRounding;
    }

    public function update(array $ids, Context $context): void
    {
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $currencies = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as array_key, LOWER(HEX(id)) as id, decimal_precision, factor FROM currency');

        $currencies = FetchModeHelper::groupUnique($currencies);

        /**
         * $prices is now grouped for parent_id
         *
         * parent_id = [
         *      ['variant_id' => '..', 'rule_id' => '..', 'price' => '..'],
         *      ['variant_id' => '..', 'rule_id' => '..', 'price' => '..'],
         * ],
         * parent_id = [
         *      ['variant_id' => '..', 'rule_id' => '..', 'price' => '..'],
         *      ['variant_id' => '..', 'rule_id' => '..', 'price' => '..'],
         * ]
         *
         * Each product contains a list of all advanced prices (table: product_price => rule_id != null)
         * Each product contains a list of all simple prices   (product.price => rule_id === null)
         */
        $prices = $this->fetchPrices($ids, $context);
        $versionId = Uuid::fromHexToBytes($context->getVersionId());

        RetryableQuery::retryable(function () use ($ids, $versionId): void {
            $this->connection->executeUpdate(
                'UPDATE product SET listing_prices = NULL WHERE id IN (:ids) AND version_id = :version',
                ['ids' => Uuid::fromHexToBytesList($ids), 'version' => $versionId],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
        });

        $query = new RetryableQuery(
            $this->connection->prepare('UPDATE product SET listing_prices = :price WHERE id = :id AND version_id = :version')
        );

        // now calculate the price range for each "parent" product
        foreach ($prices as $productId => $productPrices) {
            $ruleIds = array_unique(array_column($productPrices, 'rule_id'));

            $listingPrices = $this->calculateListingPrices($ruleIds, $productPrices, $currencies);

            $encoded = json_encode($listingPrices);

            $query->execute([
                'price' => $encoded,
                'id' => Uuid::fromHexToBytes($productId),
                'version' => $versionId,
            ]);
        }
    }

    private function fetchPrices(array $ids, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(IFNULL(product.parent_id, product.id))) as group_id',
            'LOWER(HEX(product.id)) as variant_id',
            'LOWER(HEX(price.rule_id)) as rule_id',
            'price.price',
        ]);

        $query->from('product', 'product');
        $query->innerJoin('product', 'product_price', 'price', 'price.product_id = product.prices AND product.version_id = price.product_version_id');
        $query->leftJoin('product', 'product', 'parent', 'parent.id = product.parent_id');

        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('product.available = 1');
        $query->andWhere('IFNULL(product.active, parent.active) = 1');
        $query->andWhere('(product.child_count = 0 OR product.parent_id IS NOT NULL)');
        $query->andWhere('product.version_id = :version');

        $ids = Uuid::fromHexToBytesList($ids);

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);
        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));

        $data = $query->execute()->fetchAll();

        $grouped = [];
        foreach ($data as &$row) {
            $row['price'] = json_decode($row['price'], true);
            $grouped[$row['group_id']][] = $row;
        }

        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(IFNULL(product.parent_id, product.id))) as group_id',
            'LOWER(HEX(product.id)) as variant_id',
            'NULL as rule_id',
            'IFNULL(product.price, parent.price) as price',
        ]);

        $query->from('product', 'product');
        $query->leftJoin('product', 'product', 'parent', 'product.parent_id = parent.id');
        $query->andWhere('(product.child_count = 0 OR product.parent_id IS NOT NULL)');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('product.available = 1');
        $query->andWhere('IFNULL(product.active, parent.active) = 1');
        $query->andWhere('product.version_id = :version');

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);
        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));

        $defaults = $query->execute()->fetchAll();

        foreach ($defaults as $row) {
            $row['price'] = json_decode($row['price'], true);
            $row['price'] = $this->normalizePrices($row['price']);
            $grouped[$row['group_id']][] = $row;
        }

        return $grouped;
    }

    private function calculateListingPrices(array $ruleIds, array $prices, array $currencies): array
    {
        $ranges = [];

        // at this point $prices contains only prices of the parent or his variants
        // if a variant has a rule-price, we will add a fallback row with the simple price of the variant
        // (inheritance of simple price is considered here)
        $prices = $this->addFallbackToRulePrices($ruleIds, $prices);

        foreach ($ruleIds as $ruleId) {
            // check if the product contains prices for the currency rule
            $rulePrices = array_filter($prices, function (array $price) use ($ruleId) {
                return $price['rule_id'] === $ruleId;
            });

            if (empty($rulePrices)) {
                continue;
            }

            // now calculate currency prices that all prices are based on the same currencies
            [$rulePrices, $currencyIds] = $this->unifyPrices($rulePrices, $currencies);

            foreach ($currencyIds as $currencyId) {
                $range = $this->calculatePriceRange($currencyId, $ruleId, $rulePrices);

                if (empty($range)) {
                    continue;
                }
                $currencyKey = 'c' . $currencyId;

                $ruleKey = 'r' . $ruleId;
                if ($ruleId === null) {
                    $ruleKey = 'default';
                }

                $ranges[$ruleKey][$currencyKey] = $range;
            }
        }

        return $ranges;
    }

    private function calculatePriceRange(string $currencyId, ?string $ruleId, array $prices): array
    {
        $highest = null;
        $cheapest = null;

        foreach ($prices as $price) {
            $key = 'c' . $currencyId;

            $currencyPrice = $price['price'][$key];

            if (!$highest || $currencyPrice['gross'] > $highest['gross']) {
                $highest = $currencyPrice;
            }
            if (!$cheapest || $currencyPrice['gross'] < $cheapest['gross']) {
                $cheapest = $currencyPrice;
            }
        }

        return [
            'currencyId' => $currencyId,
            'ruleId' => $ruleId,
            'from' => $cheapest,
            'to' => $highest,
        ];
    }

    /**
     * Calculates all required currency prices.
     * If the provided prices include different currency prices, they are all brought to a common denominator.
     */
    private function unifyPrices(array $prices, array $currencies): array
    {
        $requiredCurrencies = [];
        foreach ($prices as $price) {
            $requiredCurrencies = array_merge($requiredCurrencies, array_column($price['price'], 'currencyId'));
        }
        $requiredCurrencies = array_unique($requiredCurrencies);

        foreach ($requiredCurrencies as $currencyId) {
            $currency = $currencies[$currencyId];

            foreach ($prices as &$raw) {
                $price = $raw['price'];

                $key = 'c' . $currencyId;

                // currency price set?
                if (isset($price[$key])) {
                    continue;
                }

                // no default price? calculation not possible!
                $default = 'c' . Defaults::CURRENCY;
                if (!isset($price[$default])) {
                    throw new \RuntimeException(sprintf('Missing default price'));
                }

                $price[$key] = $this->calculateCurrencyPrice($currency, $price[$default]);

                $raw['price'] = $price;
            }
        }

        return [$prices, $requiredCurrencies];
    }

    private function calculateCurrencyPrice(array $currency, array $default): array
    {
        return array_replace(
            $default,
            [
                'currencyId' => $currency['id'],
                'gross' => $this->priceRounding->round(
                    $default['gross'] * $currency['factor'],
                    (int) $currency['decimal_precision']
                ),
                'net' => $this->priceRounding->round(
                    $default['net'] * $currency['factor'],
                    (int) $currency['decimal_precision']
                ),
            ]
        );
    }

    private function addFallbackToRulePrices(array $ruleIds, array $prices): array
    {
        $ids = array_column($prices, 'variant_id');
        $ids = array_unique($ids);

        // filter null id for default price
        $ruleIds = array_filter($ruleIds);

        foreach ($ruleIds as $ruleId) {
            foreach ($ids as $id) {
                if ($this->hasRulePrice($ruleId, $id, $prices)) {
                    continue;
                }

                $default = $this->getDefaultPrice($id, $prices);

                $prices[] = array_replace($default, ['rule_id' => $ruleId]);
            }
        }

        return $prices;
    }

    private function hasRulePrice(string $ruleId, string $id, array $prices): bool
    {
        foreach ($prices as $price) {
            if ($price['rule_id'] === $ruleId && $price['variant_id'] === $id) {
                return true;
            }
        }

        return false;
    }

    private function getDefaultPrice(string $id, array $prices)
    {
        foreach ($prices as $price) {
            if ($price['variant_id'] === $id && $price['rule_id'] === null) {
                return $price;
            }
        }

        throw new \RuntimeException(sprintf('Missing default price for variant %s', $id));
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
