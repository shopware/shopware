<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Price\PriceRounding;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\FetchModeHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPrice;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\ListingPriceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
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

    /**
     * @var Price
     */
    private $priceStruct;

    /**
     * @var ListingPrice
     */
    private $listingPrice;

    public function __construct(Connection $connection, PriceRounding $priceRounding)
    {
        $this->connection = $connection;
        $this->priceRounding = $priceRounding;
        $this->priceStruct = new Price('', 0, 0, true);
        $this->listingPrice = new ListingPrice();
    }

    public function update(array $ids, Context $context): void
    {
        $ids = array_unique(array_filter($ids));

        if (empty($ids)) {
            return;
        }

        $currencies = $this->connection->fetchAll('SELECT LOWER(HEX(id)) as array_key, LOWER(HEX(id)) as id, decimal_precision, factor FROM currency');

        $currencies = FetchModeHelper::groupUnique($currencies);

        $prices = $this->fetchPrices($ids, $currencies, $context);

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

        foreach ($prices as $productId => $productPrices) {
            $ruleIds = array_keys(array_flip(array_column($productPrices, 'rule_id')));

            $listingPrices = [];

            foreach ($ruleIds as $ruleId) {
                foreach ($currencies as $currencyId => $_currency) {
                    $range = $this->calculatePriceRange($currencyId, $ruleId, $productPrices);

                    $currencyKey = 'c' . $currencyId;
                    $ruleKey = 'r' . $ruleId;

                    $listingPrices[$ruleKey][$currencyKey] = $range;
                }
            }

            $structs = $this->hydrate($listingPrices);
            $encoded = json_encode(['structs' => serialize($structs), 'formatted' => $listingPrices]);

            $query->execute([
                'price' => $encoded,
                'id' => Uuid::fromHexToBytes($productId),
                'version' => $versionId,
            ]);
        }
    }

    private function fetchPrices(array $ids, array $currencies, Context $context): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'LOWER(HEX(IFNULL(product.parent_id, product.id))) as id',
            'LOWER(HEX(price.id)) as price_id',
            'LOWER(HEX(product.id)) as variant_id',
            'LOWER(HEX(price.rule_id)) as rule_id',
            'price.price',
        ]);

        $query->from('product', 'product');
        $query->innerJoin('product', 'product_price', 'price', 'price.product_id = product.id AND product.version_id = price.product_version_id');
        $query->andWhere('product.id IN (:ids) OR product.parent_id IN (:ids)');
        $query->andWhere('product.version_id = :version');

        $ids = Uuid::fromHexToBytesList($ids);

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);
        $query->setParameter('version', Uuid::fromHexToBytes($context->getVersionId()));

        $data = $query->execute()->fetchAll();

        foreach ($data as &$row) {
            $price = json_decode($row['price'], true);

            $key = 'c' . Defaults::CURRENCY;

            if (!isset($price[$key])) {
                throw new \RuntimeException(sprintf('Missing default price for product %s', $row['variant_id']));
            }

            $default = $price[$key];

            foreach ($currencies as $currencyId => $currency) {
                $key = 'c' . $currencyId;

                if (isset($price[$key])) {
                    continue;
                }

                $currencyPrice = $default;

                $currencyPrice['gross'] = $this->priceRounding->round(
                    $currencyPrice['gross'] * $currency['factor'],
                    (int) $currency['decimal_precision']
                );

                $currencyPrice['net'] = $this->priceRounding->round(
                    $currencyPrice['net'] * $currency['factor'],
                    (int) $currency['decimal_precision']
                );

                $currencyPrice['currencyId'] = $currencyId;

                $price[$key] = $currencyPrice;
            }

            $row['price'] = $price;
        }

        return FetchModeHelper::group($data);
    }

    private function calculatePriceRange(string $currencyId, string $ruleId, array $prices): array
    {
        $highest = null;
        $cheapest = null;

        foreach ($prices as $price) {
            if ($price['rule_id'] !== $ruleId) {
                continue;
            }

            $currencyPrice = $price['price']['c' . $currencyId];

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

    private function hydrate(array $listingPrices): ListingPriceCollection
    {
        $prices = [];

        foreach ($listingPrices as $rulePrices) {
            foreach ($rulePrices as $price) {
                $to = clone $this->priceStruct;
                $from = clone $this->priceStruct;

                $to->assign($price['to']);
                $from->assign($price['from']);

                $price['to'] = $to;
                $price['from'] = $from;

                $listingPrice = clone $this->listingPrice;
                $listingPrice->assign($price);

                $prices[] = $listingPrice;
            }
        }

        return new ListingPriceCollection($prices);
    }
}
