<?php declare(strict_types=1);

namespace Shopware\DbalIndexing\Product;

use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Shopware\Api\Context\Struct\ContextPriceStruct;
use Shopware\Api\Entity\Field\ContextPricesJsonField;
use Shopware\Api\Product\Struct\PriceStruct;

class ListingPriceUpdater
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function update(array $ids): void
    {
        $prices = $this->fetchPrices($ids);

        $field = new ContextPricesJsonField('tmp', 'tmp');

        foreach ($prices as $id => $productPrices) {
            $productPrices = $this->convertPrices($productPrices);
            $ruleIds = array_keys(array_flip(array_column($productPrices, 'contextRuleId')));
            $listingPrices = [];

            foreach ($ruleIds as $ruleId) {
                $listingPrices[] = $this->findCheapestRulePrice($productPrices, $ruleId);
            }

            $listingPrices = $field->convertToStorage($listingPrices);

            $this->connection->executeUpdate(
                'UPDATE product SET listing_prices = :price WHERE id = :id',
                ['price' => json_encode($listingPrices), 'id' => Uuid::fromString($id)->getBytes()]
            );
        }
    }

    private function fetchPrices(array $ids): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select([
            'IFNULL(HEX(product.parent_id), HEX(product.id)) as id',
            'price.id as price_id',
            'product.id as variant_id',
            'price.context_rule_id',
            'price.price',
            'price.currency_id',
        ]);

        $query->from('product', 'product');
        $query->innerJoin('product', 'product_context_price', 'price', 'price.product_id = product.id');
        $query->where('product.id IN (:ids) OR product.parent_id IN (:ids)');

        $ids = array_map(function ($id) {
            return Uuid::fromString($id)->getBytes();
        }, $ids);

        $query->setParameter('ids', $ids, Connection::PARAM_STR_ARRAY);

        return $query->execute()->fetchAll(\PDO::FETCH_GROUP);
    }

    /**
     * @param $productPrices
     *
     * @return array
     */
    private function convertPrices($productPrices): array
    {
        $productPrices = array_map(
            function (array $price) {
                $value = json_decode($price['price'], true);
                $value['_class'] = PriceStruct::class;

                return [
                    'id' => Uuid::fromBytes($price['price_id'])->toString(),
                    'variantId' => Uuid::fromBytes($price['variant_id'])->toString(),
                    'contextRuleId' => Uuid::fromBytes($price['context_rule_id'])->toString(),
                    'currencyId' => Uuid::fromBytes($price['currency_id'])->toString(),
                    'price' => $value,
                    '_class' => ContextPriceStruct::class,
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
                return $price['contextRuleId'] === $ruleId;
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
}
